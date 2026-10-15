<?php

declare (strict_types=1);
namespace JooosiSocialImage\Cli;

use JooosiSocialImage\Rendering\ImageGenerator;
use WP_CLI;
use function WP_CLI\Utils\get_flag_value;
/**
 * Renders a design from the terminal.
 *
 * @since 0.1.0
 */
final class RenderCommand
{
    public function __construct(private ImageGenerator $imageGenerator)
    {
    }
    /**
     * Render one design.
     *
     * ## OPTIONS
     *
     * <design-id>
     * : Design ID.
     *
     * [--post=<id>]
     * : Source post ID.
     *
     * [--output=<type>]
     * : manual, og, twitter, or featured.
     *
     * [--out=<path>]
     * : Copy the generated image to a path inside the existing uploads/social-image directory.
     *
     * [--force]
     * : Ignore an existing cached render.
     *
     * [--format=<format>]
     * : table or json for command output.
     */
    public function run(array $args, array $assocArgs): void
    {
        $designId = absint($args[0] ?? 0);
        if ($designId === 0) {
            WP_CLI::error('A design ID is required.');
        }
        $result = $this->imageGenerator->generate($designId, absint($assocArgs['post'] ?? 0), sanitize_key($assocArgs['output'] ?? 'manual'), [], get_flag_value($assocArgs, 'force', \false));
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        if (!empty($assocArgs['out'])) {
            $destination = $this->copyToUploadsDirectory($result['path'], (string) $assocArgs['out']);
            if ($destination === null) {
                WP_CLI::error('--out must be inside the existing uploads/social-image directory.');
            }
            $result['out'] = $destination;
        }
        if (($assocArgs['format'] ?? '') === 'json') {
            WP_CLI::line((string) wp_json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
            return;
        }
        WP_CLI::success($result['url']);
    }
    private function copyToUploadsDirectory(string $source, string $destination): ?string
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return null;
        }
        $uploadsDirectory = realpath(trailingslashit($uploads['basedir']) . 'social-image');
        $destination = wp_normalize_path($destination);
        $destinationDirectory = realpath(dirname($destination));
        if ($uploadsDirectory === \false || $destinationDirectory === \false) {
            return null;
        }
        $uploadsDirectory = wp_normalize_path($uploadsDirectory);
        $destinationDirectory = wp_normalize_path($destinationDirectory);
        $isInsideUploadsDirectory = $destinationDirectory === $uploadsDirectory || str_starts_with($destinationDirectory, trailingslashit($uploadsDirectory));
        if (!$isInsideUploadsDirectory) {
            return null;
        }
        $destination = trailingslashit($destinationDirectory) . basename($destination);
        if (!wp_is_writable($destinationDirectory) || is_dir($destination) || is_link($destination)) {
            return null;
        }
        $sourcePath = realpath($source);
        if ($sourcePath !== \false && wp_normalize_path($sourcePath) === $destination) {
            return $destination;
        }
        return copy($source, $destination) ? $destination : null;
    }
}
