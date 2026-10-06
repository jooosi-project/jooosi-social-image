<?php

declare (strict_types=1);
namespace JooosiSocialImage\Api;

use JooosiSocialImage\Bootstrap\Plugin;
use WP_Error;
/**
 * Public, namespaced API for on-demand image generation.
 *
 * @since 0.1.0
 */
final class Image
{
    private function __construct()
    {
    }
    /**
     * Generate or retrieve an image URL.
     *
     * @param array<string, mixed> $arguments Optional output, key, data, and force values.
     */
    public static function url(int $designId, int $postId = 0, array $arguments = []): string|WP_Error
    {
        $postId = $postId > 0 ? absint($postId) : (int) get_the_ID();
        $output = isset($arguments['output']) ? sanitize_key((string) $arguments['output']) : 'manual';
        $key = isset($arguments['key']) ? sanitize_key((string) $arguments['key']) : '';
        $data = isset($arguments['data']) && is_array($arguments['data']) ? $arguments['data'] : [];
        $result = Plugin::instance()->imageGenerator()->generate(absint($designId), $postId, $output, $data, !empty($arguments['force']), $key);
        return is_wp_error($result) ? $result : (string) $result['url'];
    }
    /**
     * Return an escaped image element.
     *
     * @param array<string, mixed> $arguments Optional image and rendering attributes.
     */
    public static function html(int $designId, int $postId = 0, array $arguments = []): string
    {
        $url = self::url($designId, $postId, $arguments);
        if (is_wp_error($url)) {
            return '';
        }
        $alt = isset($arguments['alt']) ? (string) $arguments['alt'] : get_the_title($postId ?: get_the_ID());
        $class = isset($arguments['class']) ? (string) $arguments['class'] : 'social-image-image';
        return sprintf('<img src="%s" alt="%s" class="%s" loading="lazy" decoding="async" />', esc_url($url), esc_attr($alt), esc_attr($class));
    }
    /**
     * Print an escaped image element.
     *
     * @param array<string, mixed> $arguments Optional image and rendering attributes.
     */
    public static function render(int $designId, int $postId = 0, array $arguments = []): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by self::html().
        echo self::html($designId, $postId, $arguments);
    }
}
