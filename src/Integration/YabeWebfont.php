<?php

declare (strict_types=1);
namespace JooosiEgami\Integration;

use Yabe\Webfont\Core\Cache;
use Yabe\Webfont\Utils\Font;
defined('ABSPATH') || exit;
/**
 * Optional adapter for Yabe Webfont's public font catalogue and CSS cache.
 */
final class YabeWebfont
{
    /** @var list<array{family: string, weight: string, style: string, sources: list<array{url: string, format: string}>}>|null */
    private ?array $parsedFaces = null;
    public function available(): bool
    {
        return class_exists(Font::class) && class_exists(Cache::class);
    }
    /**
     * @return list<array{title: string, family: string, type: string, variants: list<string>, renderable: bool, reason: string}>
     */
    public function fonts(): array
    {
        if (!$this->available()) {
            return array();
        }
        $result = array();
        try {
            $fonts = Font::get_fonts();
        } catch (\Throwable) {
            return array();
        }
        foreach ($fonts as $font) {
            if (!is_array($font)) {
                continue;
            }
            $family = sanitize_text_field((string) ($font['family'] ?? ''));
            if ('' === $family) {
                continue;
            }
            $variants = array();
            foreach ((array) ($font['variants'] ?? array()) as $variant) {
                if (!is_array($variant)) {
                    continue;
                }
                $variants[] = sprintf('%s %s', sanitize_text_field((string) ($variant['weight'] ?? '400')), sanitize_key((string) ($variant['style'] ?? 'normal')));
            }
            $renderable = $this->hasRenderableFace($family);
            $result[] = array('title' => sanitize_text_field((string) ($font['title'] ?? $family)), 'family' => $family, 'type' => sanitize_key((string) ($font['type'] ?? 'custom')), 'variants' => array_values(array_unique($variants)), 'renderable' => $renderable, 'reason' => $renderable ? '' : __('This family does not provide a local TTF or OTF face for server image rendering.', 'jooosi-egami'));
        }
        return $result;
    }
    public function stylesheetUrl(): string
    {
        if (!$this->available()) {
            return '';
        }
        try {
            $path = Cache::get_cache_path(Cache::CSS_CACHE_FILE);
            return is_readable($path) ? esc_url_raw(Cache::get_cache_url(Cache::CSS_CACHE_FILE)) : '';
        } catch (\Throwable) {
            return '';
        }
    }
    /**
     * @return array{available: bool, stylesheet_url: string, reason: string, notice: string, renderable_count: int, unrenderable_count: int, fonts: array}
     */
    public function capabilities(): array
    {
        $available = $this->available();
        $fonts = $this->fonts();
        $renderable_count = count(array_filter($fonts, static fn(array $font): bool => $font['renderable']));
        $unrenderable_count = count($fonts) - $renderable_count;
        $notice = '';
        if ($available && $fonts && 0 === $renderable_count) {
            $notice = __('Yabe Webfont is active, but none of its enabled families provides a local TTF or OTF file for server rendering.', 'jooosi-egami');
        } elseif ($available && $unrenderable_count > 0) {
            /* translators: %d: Number of enabled Yabe Webfont families unavailable to the server renderer. */
            $notice = sprintf(_n('%d enabled Yabe Webfont family cannot be rendered on the server because it has no local TTF or OTF file.', '%d enabled Yabe Webfont families cannot be rendered on the server because they have no local TTF or OTF file.', $unrenderable_count, 'jooosi-egami'), $unrenderable_count);
        }
        return array('available' => $available, 'stylesheet_url' => $this->stylesheetUrl(), 'reason' => $available ? '' : __('Install and activate Yabe Webfont to use its font library.', 'jooosi-egami'), 'notice' => $notice, 'renderable_count' => $renderable_count, 'unrenderable_count' => $unrenderable_count, 'fonts' => $fonts);
    }
    public function locate(string $family, int $weight = 400, string $style = 'normal'): string
    {
        $face = $this->bestFace($family, $weight, $style);
        if (null === $face) {
            return '';
        }
        foreach ($face['sources'] as $source) {
            $path = $this->localPath($source['url']);
            if ('' === $path) {
                continue;
            }
            if (in_array(strtolower(pathinfo($path, \PATHINFO_EXTENSION)), array('ttf', 'otf'), \true)) {
                return $path;
            }
        }
        return '';
    }
    public function hasFamily(string $family): bool
    {
        foreach ($this->fonts() as $font) {
            if (0 === strcasecmp($font['family'], $family)) {
                return \true;
            }
        }
        return \false;
    }
    private function hasRenderableFace(string $family): bool
    {
        foreach ($this->faces() as $face) {
            if (0 !== strcasecmp($face['family'], $family)) {
                continue;
            }
            foreach ($face['sources'] as $source) {
                $path = $this->localPath($source['url']);
                $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));
                if (in_array($extension, array('ttf', 'otf'), \true)) {
                    return \true;
                }
            }
        }
        return \false;
    }
    /**
     * @return array{family: string, weight: string, style: string, sources: list<array{url: string, format: string}>}|null
     */
    private function bestFace(string $family, int $weight, string $style): ?array
    {
        $matches = array_values(array_filter($this->faces(), static fn(array $face): bool => 0 === strcasecmp($face['family'], $family)));
        if (!$matches) {
            return null;
        }
        usort($matches, static function (array $left, array $right) use ($weight, $style): int {
            $left_score = ($left['style'] === $style ? 0 : 1000) + abs(self::representativeWeight($left['weight']) - $weight);
            $right_score = ($right['style'] === $style ? 0 : 1000) + abs(self::representativeWeight($right['weight']) - $weight);
            return $left_score <=> $right_score;
        });
        return $matches[0];
    }
    private static function representativeWeight(string $value): int
    {
        if (preg_match_all('/\d{3}/', $value, $matches) && !empty($matches[0])) {
            $weights = array_map('intval', $matches[0]);
            return (int) round(array_sum($weights) / count($weights));
        }
        return 400;
    }
    /**
     * @return list<array{family: string, weight: string, style: string, sources: list<array{url: string, format: string}>}>
     */
    private function faces(): array
    {
        if (is_array($this->parsedFaces)) {
            return $this->parsedFaces;
        }
        if (!$this->available()) {
            return $this->parsedFaces = array();
        }
        try {
            $path = Cache::get_cache_path(Cache::CSS_CACHE_FILE);
            $css = is_readable($path) ? file_get_contents($path) : Cache::build_css();
        } catch (\Throwable) {
            return $this->parsedFaces = array();
        }
        if (!is_string($css) || !preg_match_all('/@font-face\s*\{(.*?)\}/si', $css, $blocks)) {
            return $this->parsedFaces = array();
        }
        $faces = array();
        foreach ($blocks[1] as $block) {
            if (!preg_match('/font-family\s*:\s*([\'\"]?)(.*?)\1\s*;/i', $block, $family_match)) {
                continue;
            }
            preg_match('/font-weight\s*:\s*([^;]+);/i', $block, $weight_match);
            preg_match('/font-style\s*:\s*([^;]+);/i', $block, $style_match);
            preg_match_all('/url\(\s*([\'\"]?)(.*?)\1\s*\)\s*(?:format\(\s*([\'\"]?)(.*?)\3\s*\))?/i', $block, $source_matches, \PREG_SET_ORDER);
            $sources = array();
            foreach ($source_matches as $source) {
                $sources[] = array('url' => esc_url_raw(html_entity_decode(trim((string) $source[2]))), 'format' => sanitize_key((string) ($source[4] ?? '')));
            }
            $faces[] = array('family' => sanitize_text_field(trim((string) $family_match[2])), 'weight' => sanitize_text_field(trim((string) ($weight_match[1] ?? '400'))), 'style' => sanitize_key(trim((string) ($style_match[1] ?? 'normal'))), 'sources' => $sources);
        }
        return $this->parsedFaces = $faces;
    }
    private function localPath(string $url): string
    {
        if ('' === $url) {
            return '';
        }
        $uploads = wp_upload_dir();
        $base_path = trailingslashit(wp_normalize_path((string) $uploads['basedir']));
        $base_url = trailingslashit((string) $uploads['baseurl']);
        $relative = '';
        if (0 === strpos($url, $base_url)) {
            $relative = ltrim(substr($url, strlen($base_url)), '/');
        } else {
            $url_path = wp_parse_url($url, \PHP_URL_PATH);
            $uploads_path = wp_parse_url($base_url, \PHP_URL_PATH);
            if (is_string($url_path) && is_string($uploads_path) && 0 === strpos($url_path, trailingslashit($uploads_path))) {
                $relative = ltrim(substr($url_path, strlen(trailingslashit($uploads_path))), '/');
            }
        }
        if ('' === $relative) {
            return '';
        }
        $path = wp_normalize_path($base_path . $relative);
        return 0 === strpos($path, $base_path) && is_readable($path) ? $path : '';
    }
}
