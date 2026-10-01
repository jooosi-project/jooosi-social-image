<?php

declare (strict_types=1);
namespace JooosiSocialImage\Rendering;

defined('ABSPATH') || exit;
/**
 * Creates render files beside their final destination for atomic moves.
 *
 * @since 0.1.0
 */
final class TemporaryFile
{
    public static function create(string $directory): ?string
    {
        if (!is_dir($directory) || !wp_is_writable($directory)) {
            return null;
        }
        $path = @tempnam($directory, 'social-image-');
        if (!is_string($path)) {
            return null;
        }
        $resolvedDirectory = realpath($directory);
        $resolvedPathDirectory = realpath(dirname($path));
        if ($resolvedDirectory === \false || $resolvedPathDirectory !== $resolvedDirectory) {
            wp_delete_file($path);
            return null;
        }
        return $path;
    }
}
