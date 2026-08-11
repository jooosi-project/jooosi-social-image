<?php

declare (strict_types=1);
namespace JooosiEgami\Rendering;

use Throwable;
use WP_Error;
defined('ABSPATH') || exit;
/**
 * Rasterizes sanitized SVG through Imagick. ImageMagick's librsvg delegate is
 * preferred and its internal MSVG renderer is a disclosed compatibility fallback.
 */
final class SvgRasterizer
{
    private ?array $detected = null;
    /**
     * @return array{
     *     available: bool,
     *     imagick: bool,
     *     svg_format: bool,
     *     librsvg: bool,
     *     limited: bool,
     *     engine: string,
     *     reason: string,
     *     notice: string
     * }
     */
    public function capabilities(): array
    {
        if (is_array($this->detected)) {
            return $this->detected;
        }
        $imagick = extension_loaded('imagick') && class_exists(\Imagick::class);
        $svgFormat = \false;
        $delegates = '';
        if ($imagick) {
            try {
                $svgFormat = in_array('SVG', \Imagick::queryFormats('SVG'), \true);
                $options = \Imagick::getConfigureOptions('DELEGATES');
                $delegates = strtolower(implode(' ', array_map('strval', $options)));
            } catch (Throwable) {
                $svgFormat = \false;
            }
        }
        $librsvg = (bool) preg_match('/(?:^|\s)rsvg(?:\s|$)/', $delegates);
        $engine = $librsvg ? 'librsvg' : ($svgFormat ? 'msvg' : 'none');
        $available = $imagick && $engine !== 'none';
        $limited = $available && $engine === 'msvg';
        if (!$imagick) {
            $reason = __('Enable the PHP Imagick extension to render SVG elements.', 'jooosi-egami');
        } elseif ($engine === 'none') {
            $reason = __('The installed ImageMagick build does not expose its SVG format.', 'jooosi-egami');
        } else {
            $reason = '';
        }
        $notice = $limited ? __('SVG uses ImageMagick MSVG with limited SVG compatibility. Ask the host to install the ImageMagick librsvg delegate for full support.', 'jooosi-egami') : '';
        $this->detected = ['available' => $available, 'imagick' => $imagick, 'svg_format' => $svgFormat, 'librsvg' => $librsvg, 'limited' => $limited, 'engine' => $engine, 'reason' => $reason, 'notice' => $notice];
        return $this->detected;
    }
    public function available(): bool
    {
        return $this->capabilities()['available'];
    }
    public function identity(): string
    {
        $capabilities = $this->capabilities();
        return $capabilities['available'] ? 'svg-' . $capabilities['engine'] : 'svg-none';
    }
    public function rasterize(string $svg, int $width, int $height): string|WP_Error
    {
        if (!$this->available()) {
            return new WP_Error('egami_svg_renderer_unavailable', $this->capabilities()['reason']);
        }
        $width = max(1, min(8192, $width));
        $height = max(1, min(8192, $height));
        $engine = $this->capabilities()['engine'];
        try {
            return $this->renderBlob($engine === 'msvg' ? $this->prepareForMsvg($svg) : $svg, $width, $height, $engine);
        } catch (Throwable $exception) {
            if ($engine === 'librsvg' && $this->capabilities()['svg_format']) {
                try {
                    $blob = $this->renderBlob($this->prepareForMsvg($svg), $width, $height, 'msvg');
                    if (is_array($this->detected)) {
                        $this->detected['engine'] = 'msvg';
                        $this->detected['limited'] = \true;
                        $this->detected['notice'] = __('The ImageMagick librsvg delegate failed, so Egami used limited MSVG compatibility. Ask the host to repair the librsvg delegate.', 'jooosi-egami');
                    }
                    return $blob;
                } catch (Throwable $fallbackException) {
                    $exception = $fallbackException;
                }
            }
            return new WP_Error(
                'egami_svg_render_failed',
                /* translators: %s: SVG rasterizer error message. */
                sprintf(__('The SVG rasterizer failed: %s', 'jooosi-egami'), $exception->getMessage())
            );
        }
    }
    private function renderBlob(string $source, int $width, int $height, string $engine): string
    {
        $image = new \Imagick();
        $image->setBackgroundColor(new \ImagickPixel('transparent'));
        $image->setOption('svg:background-color', 'transparent');
        if ($engine === 'msvg') {
            $image->setFormat('MSVG');
        }
        $image->readImageBlob($source);
        $image->setIteratorIndex(0);
        $image->setImageBackgroundColor(new \ImagickPixel('transparent'));
        $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
        $image->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1, \true);
        $image->setImagePage(0, 0, 0, 0);
        if ($engine === 'msvg') {
            $alpha = $image->getImageChannelRange(\Imagick::CHANNEL_ALPHA);
            if ((float) ($alpha['maxima'] ?? 0) <= 0.0) {
                $image->clear();
                throw new \RuntimeException(esc_html__('MSVG produced an empty image for this icon. Ask the host to install the ImageMagick librsvg delegate, or choose a simpler icon.', 'jooosi-egami'));
            }
        }
        $image->setFormat('png32');
        $image->stripImage();
        $blob = $image->getImageBlob();
        $image->clear();
        if (!is_string($blob) || $blob === '') {
            throw new \RuntimeException(esc_html__('Imagick returned an empty PNG.', 'jooosi-egami'));
        }
        return $blob;
    }
    private function prepareForMsvg(string $svg): string
    {
        $color = '#000000';
        if (preg_match('/<svg\b[^>]*\bcolor\s*=\s*(["\'])(.*?)\1/i', $svg, $match)) {
            $candidate = trim($match[2]);
            if (preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $candidate) || preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $candidate)) {
                $color = strtolower($candidate);
            }
        }
        return str_ireplace('currentcolor', $color, $svg);
    }
}
