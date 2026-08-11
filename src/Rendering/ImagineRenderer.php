<?php

declare (strict_types=1);
namespace JooosiEgami\Rendering;

use JooosiEgamiDeps\Imagine\Driver\Info;
use JooosiEgamiDeps\Imagine\Gd\DriverInfo as GdDriverInfo;
use JooosiEgamiDeps\Imagine\Gd\Image as GdImage;
use JooosiEgamiDeps\Imagine\Gd\Imagine as GdImagine;
use JooosiEgamiDeps\Imagine\Image\Box;
use JooosiEgamiDeps\Imagine\Image\FontInterface;
use JooosiEgamiDeps\Imagine\Image\ImageInterface;
use JooosiEgamiDeps\Imagine\Image\ImagineInterface;
use JooosiEgamiDeps\Imagine\Image\Palette\Color\ColorInterface;
use JooosiEgamiDeps\Imagine\Image\Palette\RGB;
use JooosiEgamiDeps\Imagine\Image\Point;
use JooosiEgamiDeps\Imagine\Imagick\DriverInfo as ImagickDriverInfo;
use JooosiEgamiDeps\Imagine\Imagick\Image as ImagickImage;
use JooosiEgamiDeps\Imagine\Imagick\Imagine as ImagickImagine;
use Throwable;
use WP_Error;
defined('ABSPATH') || exit;
/**
 * Renders Egami documents through PHP Imagine.
 *
 * Imagick is preferred for output quality and GD is the portable fallback.
 * Imagine's Gmagick adapter is intentionally not selected because it cannot
 * reliably create transparent layers or composite them with partial opacity,
 * both of which are required by Egami's document model.
 *
 * @since 0.1.0
 */
final class ImagineRenderer implements \JooosiEgami\Rendering\RendererInterface
{
    private const VERSION = 'imagine-renderer-8';
    /**
     * GD rasterizes most vector primitives without the edge coverage that
     * ImageMagick applies. Rendering at 2x and reducing once at the end gives
     * curves, masks, rotations, and text comparable antialiasing.
     */
    private const GD_RENDER_SCALE = 2;
    /**
     * Keep the quality pass inside a predictable memory envelope. Larger
     * documents still render at their requested size instead of failing.
     */
    private const GD_SUPERSAMPLE_MAX_OUTPUT_PIXELS = 2500000;
    private const AUTO_ORDER = ['imagick', 'gd'];
    private const DRIVER_CLASSES = ['imagick' => ImagickImagine::class, 'gd' => GdImagine::class];
    private const DRIVER_INFO_CLASSES = ['imagick' => ImagickDriverInfo::class, 'gd' => GdDriverInfo::class];
    /** @var list<string> */
    private array $warnings = [];
    private RGB $palette;
    private int $renderScale = 1;
    private function __construct(private ?ImagineInterface $imagine, private string $activeDriver, private array $startupWarnings = [], private ?\JooosiEgami\Rendering\SvgRasterizer $svgRasterizer = null)
    {
        $this->palette = new RGB();
    }
    public static function create(string $preferredDriver = 'auto', ?\JooosiEgami\Rendering\SvgRasterizer $svgRasterizer = null): self
    {
        $preferredDriver = strtolower(trim($preferredDriver));
        $order = $preferredDriver === 'auto' ? self::AUTO_ORDER : (isset(self::DRIVER_CLASSES[$preferredDriver]) ? [$preferredDriver] : self::AUTO_ORDER);
        $warnings = [];
        foreach ($order as $driver) {
            $class = self::DRIVER_CLASSES[$driver];
            try {
                /** @var ImagineInterface $imagine */
                $imagine = new $class();
                return new self($imagine, $driver, $warnings, $svgRasterizer);
            } catch (Throwable $exception) {
                $warnings[] = sprintf('The Imagine %1$s driver is unavailable: %2$s', $driver, $exception->getMessage());
            }
        }
        return new self(null, 'none', $warnings, $svgRasterizer);
    }
    public function available(): bool
    {
        return $this->imagine instanceof ImagineInterface;
    }
    public function driver(): string
    {
        return $this->activeDriver;
    }
    public function identity(): string
    {
        $capabilities = $this->capabilities();
        return implode(':', [self::VERSION, ImagineInterface::VERSION, $this->activeDriver, $capabilities['driver_version'], $capabilities['engine_version'], $this->svgRasterizer?->identity() ?? 'svg-none']);
    }
    public function capabilities(): array
    {
        $info = $this->driverInfo();
        return ['available' => $this->available(), 'active_driver' => $this->activeDriver, 'library' => 'imagine', 'library_version' => ImagineInterface::VERSION, 'driver_version' => $info instanceof Info ? (string) $info->getDriverVersion(\true) : '', 'engine_version' => $info instanceof Info ? (string) $info->getEngineVersion(\true) : '', 'formats' => $info instanceof Info ? array_values($info->getSupportedFormats()->getAllIDs()) : [], 'extensions' => ['imagick' => extension_loaded('imagick'), 'gd' => extension_loaded('gd'), 'gmagick' => extension_loaded('gmagick')], 'diagnostics' => $this->startupWarnings, 'svg' => $this->svgRasterizer?->capabilities()];
    }
    public function warnings(): array
    {
        return $this->warnings;
    }
    public function render(array $document, string $destination, string $format = 'png', int $quality = 90): array|WP_Error
    {
        $this->warnings = $this->startupWarnings;
        if (!$this->imagine instanceof ImagineInterface) {
            return new WP_Error('egami_renderer_unavailable', __('Egami requires either the PHP Imagick extension or PHP GD with FreeType support.', 'jooosi-egami'));
        }
        $format = in_array($format, ['png', 'jpeg', 'webp'], \true) ? $format : 'png';
        $quality = max(1, min(100, $quality));
        $capabilities = $this->capabilities();
        if (!in_array($format, $capabilities['formats'], \true)) {
            return new WP_Error('egami_format_unavailable', sprintf(
                /* translators: 1: Active image driver, 2: Requested output format. */
                __('The active %1$s image driver cannot create %2$s images.', 'jooosi-egami'),
                $this->activeDriver,
                strtoupper($format)
            ));
        }
        $width = max(1, (int) ($document['width'] ?? 1));
        $height = max(1, (int) ($document['height'] ?? 1));
        $this->renderScale = $this->renderScaleFor($width, $height);
        $renderDocument = $this->renderScale > 1 ? $this->scaleDocument($document, $this->renderScale) : $document;
        $renderWidth = $width * $this->renderScale;
        $renderHeight = $height * $this->renderScale;
        try {
            $canvas = $this->createCanvas($renderDocument['background'] ?? [], $renderWidth, $renderHeight);
            foreach ((array) ($renderDocument['elements'] ?? []) as $element) {
                if (!is_array($element) || !empty($element['hidden']) || ($element['width'] ?? 0) < 1 || ($element['height'] ?? 0) < 1) {
                    continue;
                }
                $this->drawElement($canvas, $element);
            }
            if ($this->renderScale > 1) {
                $canvas->resize(new Box($width, $height), ImageInterface::FILTER_LANCZOS);
            }
            $canvas->save($destination, ['format' => $format, 'quality' => $quality]);
        } catch (Throwable $exception) {
            return new WP_Error('egami_render_failed', sprintf(
                /* translators: 1: Active image driver, 2: Renderer error message. */
                __('The %1$s image renderer failed: %2$s', 'jooosi-egami'),
                $this->activeDriver,
                $exception->getMessage()
            ));
        } finally {
            $this->renderScale = 1;
        }
        if (!is_file($destination) || filesize($destination) < 1) {
            return new WP_Error('egami_write_failed', __('The generated image could not be written.', 'jooosi-egami'));
        }
        return ['path' => $destination, 'width' => $width, 'height' => $height, 'warnings' => $this->warnings];
    }
    private function renderScaleFor(int $width, int $height): int
    {
        if ($this->activeDriver !== 'gd') {
            return 1;
        }
        return $width * $height <= self::GD_SUPERSAMPLE_MAX_OUTPUT_PIXELS ? self::GD_RENDER_SCALE : 1;
    }
    private function scaleDocument(array $document, int $scale): array
    {
        $scaled = $document;
        $scaled['width'] = max(1, (int) ($document['width'] ?? 1)) * $scale;
        $scaled['height'] = max(1, (int) ($document['height'] ?? 1)) * $scale;
        $scaled['elements'] = [];
        $dimensions = ['x', 'y', 'width', 'height', 'radius', 'strokeWidth', 'fontSize', 'minFontSize'];
        foreach ((array) ($document['elements'] ?? []) as $element) {
            if (!is_array($element)) {
                $scaled['elements'][] = $element;
                continue;
            }
            foreach ($dimensions as $dimension) {
                if (isset($element[$dimension]) && is_numeric($element[$dimension])) {
                    $element[$dimension] = (float) $element[$dimension] * $scale;
                }
            }
            $scaled['elements'][] = $element;
        }
        return $scaled;
    }
    private function driverInfo(): ?Info
    {
        if (!isset(self::DRIVER_INFO_CLASSES[$this->activeDriver])) {
            return null;
        }
        $class = self::DRIVER_INFO_CLASSES[$this->activeDriver];
        try {
            return $class::get(\false);
        } catch (Throwable) {
            return null;
        }
    }
    private function createCanvas(array $background, int $width, int $height): ImageInterface
    {
        $first = $this->color((string) ($background['color'] ?? '#000000'));
        $canvas = $this->imagine->create(new Box($width, $height), $first);
        if (($background['type'] ?? 'solid') !== 'gradient') {
            return $canvas;
        }
        $stops = $this->gradientStops($background);
        $horizontal = ($background['direction'] ?? 'horizontal') === 'horizontal';
        $steps = max(1, $horizontal ? $width : $height);
        $drawer = $canvas->draw();
        for ($index = 0; $index < $steps; $index++) {
            $position = $steps > 1 ? $index / ($steps - 1) * 100 : 0.0;
            $color = $this->palette->color($this->gradientColorAt($stops, $position));
            if ($horizontal) {
                $drawer->line(new Point($index, 0), new Point($index, $height - 1), $color);
            } else {
                $drawer->line(new Point(0, $index), new Point($width - 1, $index), $color);
            }
        }
        return $canvas;
    }
    /**
     * @return list<array{color: array{int, int, int}, position: float}>
     */
    private function gradientStops(array $background): array
    {
        $rawStops = is_array($background['stops'] ?? null) ? array_slice($background['stops'], 0, 12) : [];
        $stops = [];
        foreach ($rawStops as $stop) {
            if (!is_array($stop)) {
                continue;
            }
            $parsed = $this->parseColor((string) ($stop['color'] ?? '#000000'));
            $stops[] = ['color' => [$parsed[0], $parsed[1], $parsed[2]], 'position' => max(0.0, min(100.0, (float) ($stop['position'] ?? 0)))];
        }
        if (count($stops) < 2) {
            $start = $this->parseColor((string) ($background['color'] ?? '#000000'));
            $end = $start;
            return [['color' => [$start[0], $start[1], $start[2]], 'position' => 0.0], ['color' => [$end[0], $end[1], $end[2]], 'position' => 100.0]];
        }
        usort($stops, static fn(array $left, array $right): int => $left['position'] <=> $right['position']);
        return $stops;
    }
    /**
     * @param list<array{color: array{int, int, int}, position: float}> $stops
     * @return array{int, int, int}
     */
    private function gradientColorAt(array $stops, float $position): array
    {
        if ($position <= $stops[0]['position']) {
            return $stops[0]['color'];
        }
        $last = count($stops) - 1;
        for ($index = 1; $index <= $last; $index++) {
            $right = $stops[$index];
            if ($position > $right['position']) {
                continue;
            }
            $left = $stops[$index - 1];
            $distance = $right['position'] - $left['position'];
            $ratio = $distance > 0 ? ($position - $left['position']) / $distance : 1.0;
            return [(int) round($left['color'][0] + ($right['color'][0] - $left['color'][0]) * $ratio), (int) round($left['color'][1] + ($right['color'][1] - $left['color'][1]) * $ratio), (int) round($left['color'][2] + ($right['color'][2] - $left['color'][2]) * $ratio)];
        }
        return $stops[$last]['color'];
    }
    private function drawElement(ImageInterface $canvas, array $element): void
    {
        $width = max(1, (int) round((float) $element['width']));
        $height = max(1, (int) round((float) $element['height']));
        $layer = $this->imagine->create(new Box($width, $height), $this->palette->color('#000000', 0));
        $type = (string) ($element['type'] ?? 'text');
        if ($type === 'image') {
            $this->drawImage($layer, $element, $width, $height);
        } elseif ($type === 'svg') {
            $this->drawSvg($layer, $element, $width, $height);
        } elseif ($type === 'shape') {
            $this->drawShape($layer, $element, $width, $height);
        } else {
            $this->drawText($layer, $element, $width, $height);
        }
        $x = (int) round((float) ($element['x'] ?? 0));
        $y = (int) round((float) ($element['y'] ?? 0));
        $rotation = (float) ($element['rotation'] ?? 0);
        if (abs($rotation) > 0.01) {
            $layer->rotate($rotation, $this->palette->color('#000000', 0));
            $this->restoreGdAlphaState($layer);
            $rotatedSize = $layer->getSize();
            $x -= (int) round(($rotatedSize->getWidth() - $width) / 2);
            $y -= (int) round(($rotatedSize->getHeight() - $height) / 2);
        }
        $opacity = max(0, min(100, (int) round((float) ($element['opacity'] ?? 1) * 100)));
        if ($opacity < 1) {
            return;
        }
        if ($opacity < 100) {
            $this->applyOpacityMask($layer, $opacity);
        }
        $this->pasteClipped($canvas, $layer, $x, $y);
    }
    private function restoreGdAlphaState(ImageInterface $image): void
    {
        if (!$image instanceof GdImage) {
            return;
        }
        // Imagine delegates rotation to imagerotate(), whose returned buffer
        // has alpha blending enabled. Subsequent masks would then blend their
        // alpha into RGB instead of replacing the alpha channel.
        $resource = $image->getGdResource();
        imagealphablending($resource, \false);
        imagesavealpha($resource, \true);
        if (function_exists('imageantialias')) {
            imageantialias($resource, \true);
        }
    }
    private function drawRectangle(ImageInterface $layer, array $element, int $width, int $height): void
    {
        $border = max(0, min((int) round((float) ($element['strokeWidth'] ?? 0)), (int) floor(min($width, $height) / 2)));
        $radius = max(0, min((int) round((float) ($element['radius'] ?? 0)), (int) floor(min($width, $height) / 2)));
        $background = is_array($element['background'] ?? null) ? $element['background'] : [];
        if ($border > 0) {
            $this->drawRoundedShape($layer, 0, 0, $width, $height, $radius, $this->color((string) ($element['strokeColor'] ?? '#ffffff')));
            if ($width > $border * 2 && $height > $border * 2) {
                $this->fillRoundedBackground($layer, $background, $border, $border, $width - $border * 2, $height - $border * 2, max(0, $radius - $border));
            }
            return;
        }
        $this->fillRoundedBackground($layer, $background, 0, 0, $width, $height, $radius);
    }
    private function drawShape(ImageInterface $layer, array $element, int $width, int $height): void
    {
        $shape = (string) ($element['shape'] ?? 'rectangle');
        $strokeWidth = max(0, min(100, (int) round((float) ($element['strokeWidth'] ?? 0))));
        $strokeColor = $this->color((string) ($element['strokeColor'] ?? '#ffffff'));
        if ($shape === 'rectangle') {
            $this->drawRectangle($layer, $element, $width, $height);
            return;
        }
        if (in_array($shape, ['line', 'arrow'], \true)) {
            $thickness = max(1, $strokeWidth);
            $padding = (int) ceil($thickness / 2);
            $middle = (int) floor(($height - 1) / 2);
            $minimumHead = 12 * $this->renderScale;
            $head = $shape === 'arrow' ? min(max($minimumHead, (int) round($height * 0.45)), max($minimumHead, (int) floor($width * 0.35))) : 0;
            $end = max($padding, $width - 1 - $padding - $head);
            $layer->draw()->line(new Point($padding, $middle), new Point($end, $middle), $strokeColor, $thickness);
            if ($shape === 'arrow') {
                $layer->draw()->polygon([new Point(max(0, $width - 1 - $padding - $head), max(0, $middle - $head)), new Point(max(0, $width - 1 - $padding), $middle), new Point(max(0, $width - 1 - $padding - $head), min($height - 1, $middle + $head))], $strokeColor, \true);
            }
            return;
        }
        $background = is_array($element['background'] ?? null) ? $element['background'] : [];
        $surface = $this->createCanvas($background, $width, $height);
        $this->applyShapeMask($surface, $width, $height, $shape);
        $layer->paste($surface, new Point(0, 0));
        if ($strokeWidth > 0) {
            $inset = (int) ceil($strokeWidth / 2);
            $this->drawShapeGeometry($layer, $shape, $strokeColor, \false, $strokeWidth, $inset);
        }
    }
    private function fillRoundedBackground(ImageInterface $image, array $background, int $x, int $y, int $width, int $height, int $radius): void
    {
        if ($width < 1 || $height < 1) {
            return;
        }
        $surface = $this->createCanvas($background, $width, $height);
        $radius = max(0, min($radius, (int) floor(min($width, $height) / 2)));
        if ($radius > 0) {
            $this->applyRoundedMask($surface, $width, $height, $radius);
        }
        $image->paste($surface, new Point($x, $y));
    }
    private function drawRoundedShape(ImageInterface $image, int $x, int $y, int $width, int $height, int $radius, ColorInterface $color): void
    {
        if ($width < 1 || $height < 1) {
            return;
        }
        $right = $x + $width - 1;
        $bottom = $y + $height - 1;
        $radius = max(0, min($radius, (int) floor(min($width, $height) / 2)));
        $drawer = $image->draw();
        if ($radius === 0) {
            $drawer->rectangle(new Point($x, $y), new Point($right, $bottom), $color, \true);
            return;
        }
        $drawer->rectangle(new Point($x + $radius, $y), new Point($right - $radius, $bottom), $color, \true);
        $drawer->rectangle(new Point($x, $y + $radius), new Point($right, $bottom - $radius), $color, \true);
        $drawer->circle(new Point($x + $radius, $y + $radius), $radius, $color, \true);
        $drawer->circle(new Point($right - $radius, $y + $radius), $radius, $color, \true);
        $drawer->circle(new Point($x + $radius, $bottom - $radius), $radius, $color, \true);
        $drawer->circle(new Point($right - $radius, $bottom - $radius), $radius, $color, \true);
    }
    private function drawImage(ImageInterface $layer, array $element, int $width, int $height): void
    {
        $path = isset($element['_path']) && is_string($element['_path']) ? $element['_path'] : '';
        if ($path === '' || !is_readable($path)) {
            $reason = isset($element['_source_error']) && is_string($element['_source_error']) ? $element['_source_error'] : 'no readable local source';
            $this->warnings[] = sprintf('Image layer %1$s could not be loaded: %2$s.', $element['id'] ?? 'unknown', rtrim($reason, '.'));
            return;
        }
        try {
            $source = $this->imagine->open($path);
            $sourceSize = $source->getSize();
            $sourceWidth = $sourceSize->getWidth();
            $sourceHeight = $sourceSize->getHeight();
            $fit = (string) ($element['fit'] ?? 'cover');
            if ($fit === 'none' && $this->renderScale > 1) {
                $sourceWidth *= $this->renderScale;
                $sourceHeight *= $this->renderScale;
                $source->resize(new Box($sourceWidth, $sourceHeight), ImageInterface::FILTER_LANCZOS);
            }
            if ($fit === 'fill') {
                $source->resize(new Box($width, $height), ImageInterface::FILTER_LANCZOS);
                $x = 0;
                $y = 0;
            } elseif ($fit === 'none') {
                $cropWidth = min($sourceWidth, $width);
                $cropHeight = min($sourceHeight, $height);
                $source->crop(new Point((int) floor(($sourceWidth - $cropWidth) / 2), (int) floor(($sourceHeight - $cropHeight) / 2)), new Box($cropWidth, $cropHeight));
                $x = (int) floor(($width - $cropWidth) / 2);
                $y = (int) floor(($height - $cropHeight) / 2);
            } elseif ($fit === 'contain') {
                $scale = min($width / $sourceWidth, $height / $sourceHeight);
                $targetWidth = max(1, (int) round($sourceWidth * $scale));
                $targetHeight = max(1, (int) round($sourceHeight * $scale));
                $source->resize(new Box($targetWidth, $targetHeight), ImageInterface::FILTER_LANCZOS);
                $x = (int) floor(($width - $targetWidth) / 2);
                $y = (int) floor(($height - $targetHeight) / 2);
            } else {
                $scale = max($width / $sourceWidth, $height / $sourceHeight);
                $targetWidth = max($width, (int) ceil($sourceWidth * $scale));
                $targetHeight = max($height, (int) ceil($sourceHeight * $scale));
                $source->resize(new Box($targetWidth, $targetHeight), ImageInterface::FILTER_LANCZOS);
                $source->crop(new Point((int) floor(($targetWidth - $width) / 2), (int) floor(($targetHeight - $height) / 2)), new Box($width, $height));
                $x = 0;
                $y = 0;
            }
            $layer->paste($source, new Point($x, $y));
            $mask = (string) ($element['mask'] ?? 'none');
            if (in_array($mask, ['ellipse', 'triangle', 'diamond', 'hexagon'], \true)) {
                $this->applyShapeMask($layer, $width, $height, $mask);
            } else {
                $radius = max(0, min((int) round((float) ($element['radius'] ?? 0)), (int) floor(min($width, $height) / 2)));
                if ($radius > 0) {
                    $this->applyRoundedMask($layer, $width, $height, $radius);
                }
            }
        } catch (Throwable $exception) {
            $this->warnings[] = sprintf('Image layer %1$s could not be rendered: %2$s', $element['id'] ?? 'unknown', $exception->getMessage());
        }
    }
    private function drawSvg(ImageInterface $layer, array $element, int $width, int $height): void
    {
        $svg = isset($element['_svg']) && is_string($element['_svg']) ? $element['_svg'] : '';
        if ($svg === '') {
            $this->warnings[] = sprintf('SVG layer %s has no Omni Icon source.', $element['id'] ?? 'unknown');
            return;
        }
        if (!$this->svgRasterizer instanceof \JooosiEgami\Rendering\SvgRasterizer) {
            $this->warnings[] = sprintf('SVG layer %s has no configured rasterizer.', $element['id'] ?? 'unknown');
            return;
        }
        $blob = $this->svgRasterizer->rasterize($svg, $width, $height);
        $capabilities = $this->svgRasterizer->capabilities();
        if ($capabilities['limited'] && !in_array($capabilities['notice'], $this->warnings, \true)) {
            $this->warnings[] = $capabilities['notice'];
        }
        if ($blob instanceof WP_Error) {
            $this->warnings[] = sprintf('SVG layer %1$s could not be rendered: %2$s', $element['id'] ?? 'unknown', $blob->get_error_message());
            return;
        }
        try {
            $source = $this->imagine->load($blob);
            $size = $source->getSize();
            $x = (int) floor(($width - $size->getWidth()) / 2);
            $y = (int) floor(($height - $size->getHeight()) / 2);
            $layer->paste($source, new Point($x, $y));
        } catch (Throwable $exception) {
            $this->warnings[] = sprintf('SVG layer %1$s could not be composited: %2$s', $element['id'] ?? 'unknown', $exception->getMessage());
        }
    }
    private function applyRoundedMask(ImageInterface $image, int $width, int $height, int $radius): void
    {
        if ($image instanceof ImagickImage) {
            $mask = $this->imagine->create(new Box($width, $height), $this->palette->color('#000000', 0));
            $this->drawRoundedShape($mask, 0, 0, $width, $height, $radius, $this->palette->color('#ffffff'));
            if (!$mask instanceof ImagickImage) {
                return;
            }
            // Imagine's Imagick applyMask() collapses partially transparent
            // source pixels while combining the mask. Multiply only the alpha
            // channels so antialiased edges inside uploaded PNGs stay intact.
            $image->getImagick()->compositeImage($mask->getImagick(), \Imagick::COMPOSITE_DSTIN, 0, 0);
            return;
        }
        // Imagine's portable mask convention is inverted: black pixels are
        // visible and white pixels are transparent.
        $mask = $this->imagine->create(new Box($width, $height), $this->palette->color('#ffffff'));
        $this->drawRoundedShape($mask, 0, 0, $width, $height, $radius, $this->palette->color('#000000'));
        $image->applyMask($mask);
    }
    private function applyShapeMask(ImageInterface $image, int $width, int $height, string $shape): void
    {
        $inverse = !$image instanceof ImagickImage;
        $mask = $this->imagine->create(new Box($width, $height), $this->palette->color($inverse ? '#ffffff' : '#000000', $inverse ? 100 : 0));
        $this->drawShapeGeometry($mask, $shape, $this->palette->color($inverse ? '#000000' : '#ffffff'), \true);
        if ($image instanceof ImagickImage && $mask instanceof ImagickImage) {
            $image->getImagick()->compositeImage($mask->getImagick(), \Imagick::COMPOSITE_DSTIN, 0, 0);
            return;
        }
        $image->applyMask($mask);
    }
    private function drawShapeGeometry(ImageInterface $image, string $shape, ColorInterface $color, bool $fill, int $thickness = 1, int $inset = 0): void
    {
        $size = $image->getSize();
        $width = $size->getWidth();
        $height = $size->getHeight();
        $maxInset = max(0, (int) floor(min($width, $height) / 2) - 1);
        $inset = max(0, min($inset, $maxInset));
        $drawWidth = max(1, $width - $inset * 2);
        $drawHeight = max(1, $height - $inset * 2);
        $drawer = $image->draw();
        if ($shape === 'ellipse') {
            $drawer->ellipse(new Point((int) floor(($width - 1) / 2), (int) floor(($height - 1) / 2)), new Box($drawWidth, $drawHeight), $color, $fill, max(1, $thickness));
            return;
        }
        $drawer->polygon($this->shapePoints($shape, $width, $height, $inset), $color, $fill, max(1, $thickness));
    }
    /**
     * @return list<Point>
     */
    private function shapePoints(string $shape, int $width, int $height, int $inset = 0): array
    {
        $left = $inset;
        $top = $inset;
        $right = max($left, $width - 1 - $inset);
        $bottom = max($top, $height - 1 - $inset);
        $centerX = ($left + $right) / 2;
        $centerY = ($top + $bottom) / 2;
        if ($shape === 'triangle') {
            return [new Point((int) round($centerX), $top), new Point($right, $bottom), new Point($left, $bottom)];
        }
        if ($shape === 'diamond') {
            return [new Point((int) round($centerX), $top), new Point($right, (int) round($centerY)), new Point((int) round($centerX), $bottom), new Point($left, (int) round($centerY))];
        }
        if ($shape === 'hexagon') {
            $quarter = ($right - $left) * 0.25;
            return [new Point((int) round($left + $quarter), $top), new Point((int) round($right - $quarter), $top), new Point($right, (int) round($centerY)), new Point((int) round($right - $quarter), $bottom), new Point((int) round($left + $quarter), $bottom), new Point($left, (int) round($centerY))];
        }
        $outer = max(1.0, min($right - $left, $bottom - $top) / 2);
        $inner = $outer * 0.43;
        $points = [];
        for ($index = 0; $index < 10; $index++) {
            $angle = -\M_PI / 2 + $index * \M_PI / 5;
            $radius = $index % 2 === 0 ? $outer : $inner;
            $points[] = new Point((int) round($centerX + cos($angle) * $radius), (int) round($centerY + sin($angle) * $radius));
        }
        return $points;
    }
    private function applyOpacityMask(ImageInterface $image, int $opacity): void
    {
        if ($image instanceof ImagickImage) {
            $image->getImagick()->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $opacity / 100, \Imagick::CHANNEL_ALPHA);
            return;
        }
        $size = $image->getSize();
        // applyMask() uses inverse luminance: black is fully visible and white
        // is fully transparent, so the requested opacity must be inverted.
        $value = max(0, min(255, (int) round(255 * (100 - $opacity) / 100)));
        $mask = $this->imagine->create(new Box($size->getWidth(), $size->getHeight()), $this->palette->color([$value, $value, $value]));
        $image->applyMask($mask);
    }
    private function drawText(ImageInterface $layer, array $element, int $width, int $height): void
    {
        $text = isset($element['_text']) ? (string) $element['_text'] : (string) ($element['content'] ?? '');
        $fontPath = isset($element['_font_path']) && is_string($element['_font_path']) ? $element['_font_path'] : '';
        if ($fontPath === '' || !is_readable($fontPath)) {
            $this->warnings[] = sprintf('Text layer %s has no readable TrueType or OpenType font.', $element['id'] ?? 'unknown');
            return;
        }
        $maxSize = max(6, (int) round((float) ($element['fontSize'] ?? 16)));
        $minSize = max(6, min($maxSize, (int) round((float) ($element['minFontSize'] ?? 6))));
        $lineHeightRatio = max(0.5, min(3.0, (float) ($element['lineHeight'] ?? 1.2)));
        $chosen = null;
        try {
            for ($size = $maxSize; $size >= $minSize; $size--) {
                $font = $this->imagine->font($fontPath, $size, $this->color((string) ($element['color'] ?? '#000000')));
                $lines = $this->wrapText($text, $font, $width);
                $glyphHeight = $this->textLineBoxHeight($font);
                $lineHeight = max(1.0, $glyphHeight * $lineHeightRatio);
                $totalHeight = $glyphHeight + max(0, count($lines) - 1) * $lineHeight;
                $fitsWidth = \true;
                foreach ($lines as $line) {
                    if ($this->measureWidth($line, $font) > $width) {
                        $fitsWidth = \false;
                        break;
                    }
                }
                if ($totalHeight <= $height && $fitsWidth || $size === $minSize) {
                    $chosen = [$font, $lines, $lineHeight, $totalHeight];
                    break;
                }
            }
            if (!is_array($chosen)) {
                return;
            }
            /** @var FontInterface $font */
            [$font, $lines, $lineHeight, $totalHeight] = $chosen;
            $verticalAlign = (string) ($element['verticalAlign'] ?? 'top');
            if ($verticalAlign === 'middle') {
                $top = ($height - $totalHeight) / 2;
            } elseif ($verticalAlign === 'bottom') {
                $top = $height - $totalHeight;
            } else {
                $top = 0.0;
            }
            $drawer = $layer->draw();
            foreach ($lines as $index => $line) {
                $lineWidth = $this->measureWidth($line, $font);
                $align = (string) ($element['align'] ?? 'left');
                if ($align === 'center') {
                    $x = ($width - $lineWidth) / 2;
                } elseif ($align === 'right') {
                    $x = $width - $lineWidth;
                } else {
                    $x = 0.0;
                }
                $point = new Point(max(0, (int) round($x)), max(0, (int) round($top + $index * $lineHeight + $this->textBaselineOffset($font))));
                if ($line === '') {
                    continue;
                }
                $drawer->text($line, $font, $point);
                $boldOffset = $this->renderScale;
                if ((int) ($element['fontWeight'] ?? 400) >= 600 && $point->getX() + $boldOffset < $width) {
                    $drawer->text($line, $font, new Point($point->getX() + $boldOffset, $point->getY()));
                }
            }
        } catch (Throwable $exception) {
            $this->warnings[] = sprintf('Text layer %1$s could not be rendered: %2$s', $element['id'] ?? 'unknown', $exception->getMessage());
        }
    }
    /**
     * @return list<string>
     */
    private function wrapText(string $text, FontInterface $font, int $maxWidth): array
    {
        $paragraphs = preg_split('/\R/u', $text);
        $lines = [];
        foreach (is_array($paragraphs) ? $paragraphs : [$text] as $paragraph) {
            $words = preg_split('/\s+/u', trim($paragraph));
            $current = '';
            foreach (is_array($words) ? array_filter($words, static fn(string $word): bool => $word !== '') : [] as $word) {
                $trial = $current === '' ? $word : $current . ' ' . $word;
                if ($current === '' || $this->measureWidth($trial, $font) <= $maxWidth) {
                    $current = $trial;
                } else {
                    $lines[] = $current;
                    $current = $word;
                }
            }
            $lines[] = $current;
        }
        return $lines !== [] ? $lines : [''];
    }
    private function measureWidth(string $text, FontInterface $font): int
    {
        return $text === '' ? 0 : $font->box($text)->getWidth();
    }
    private function textLineBoxHeight(FontInterface $font): int
    {
        $measured = max(1, $font->box('Ag')->getHeight());
        if ($this->activeDriver !== 'gd') {
            return $measured;
        }
        // Imagine's GD font box reports only the tight glyph bounds while its
        // Imagick font box reports the full line box. Normalize GD to the same
        // 96-DPI line metrics so wrapping and vertical fitting do not diverge.
        return max($measured, (int) round($font->getSize() * 1.54));
    }
    private function textBaselineOffset(FontInterface $font): int
    {
        if ($this->activeDriver !== 'gd') {
            return 0;
        }
        // Imagick converts the point size to its 96-DPI pixel baseline while
        // Imagine's GD drawer positions the baseline at the unconverted point
        // size. Account for the missing 96/72 conversion.
        return (int) round($font->getSize() / 3);
    }
    private function pasteClipped(ImageInterface $canvas, ImageInterface $layer, int $x, int $y): void
    {
        $canvasSize = $canvas->getSize();
        $layerSize = $layer->getSize();
        $cropX = max(0, -$x);
        $cropY = max(0, -$y);
        $targetX = max(0, $x);
        $targetY = max(0, $y);
        $width = min($layerSize->getWidth() - $cropX, $canvasSize->getWidth() - $targetX);
        $height = min($layerSize->getHeight() - $cropY, $canvasSize->getHeight() - $targetY);
        if ($width < 1 || $height < 1) {
            return;
        }
        if ($cropX > 0 || $cropY > 0 || $width !== $layerSize->getWidth() || $height !== $layerSize->getHeight()) {
            $layer->crop(new Point($cropX, $cropY), new Box($width, $height));
        }
        $canvas->paste($layer, new Point($targetX, $targetY));
    }
    private function color(string $value): ColorInterface
    {
        $parsed = $this->parseColor($value);
        return $this->palette->color([$parsed[0], $parsed[1], $parsed[2]], (int) round($parsed[3] * 100));
    }
    /**
     * @return array{int, int, int, float}
     */
    private function parseColor(string $value): array
    {
        $value = trim($value);
        if (preg_match('/^#([0-9a-f]{3})$/i', $value, $match)) {
            return [hexdec(str_repeat($match[1][0], 2)), hexdec(str_repeat($match[1][1], 2)), hexdec(str_repeat($match[1][2], 2)), 1.0];
        }
        if (preg_match('/^#([0-9a-f]{6})$/i', $value, $match)) {
            return [hexdec(substr($match[1], 0, 2)), hexdec(substr($match[1], 2, 2)), hexdec(substr($match[1], 4, 2)), 1.0];
        }
        if (preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([0-9.]+))?\s*\)$/i', $value, $match)) {
            return [min(255, (int) $match[1]), min(255, (int) $match[2]), min(255, (int) $match[3]), isset($match[4]) ? max(0.0, min(1.0, (float) $match[4])) : 1.0];
        }
        return [0, 0, 0, 1.0];
    }
}
