<?php

declare(strict_types=1);

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
     * : Copy the generated image to this path.
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

        $result = $this->imageGenerator->generate(
            $designId,
            absint($assocArgs['post'] ?? 0),
            sanitize_key($assocArgs['output'] ?? 'manual'),
            [],
            get_flag_value($assocArgs, 'force', false),
        );

        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }

        if (! empty($assocArgs['out'])) {
            $destination = wp_normalize_path((string) $assocArgs['out']);
            $directory = dirname($destination);

            if (! is_dir($directory) || ! wp_is_writable($directory) || ! copy($result['path'], $destination)) {
                WP_CLI::error('Could not copy the render to --out.');
            }

            $result['out'] = $destination;
        }

        if (($assocArgs['format'] ?? '') === 'json') {
            WP_CLI::line((string) wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        WP_CLI::success($result['url']);
    }
}
