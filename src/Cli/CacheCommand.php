<?php

declare (strict_types=1);
namespace JooosiSocialImage\Cli;

use JooosiSocialImage\Rendering\ImageGenerator;
use WP_CLI;
/**
 * Manages generated image cache files.
 *
 * @since 0.1.0
 */
final class CacheCommand
{
    public function __construct(private ImageGenerator $imageGenerator)
    {
    }
    public function warm(array $args, array $assocArgs): void
    {
        $result = $this->imageGenerator->warm(absint($assocArgs['design'] ?? 0), sanitize_key($assocArgs['post-type'] ?? ''), \true);
        WP_CLI::success(sprintf('Scheduled %d post(s).', (int) $result['scheduled']));
    }
    public function flush(array $args, array $assocArgs): void
    {
        WP_CLI::confirm('Delete generated Social Image cache files?', $assocArgs);
        $result = $this->imageGenerator->flushCache(absint($assocArgs['design'] ?? 0));
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        WP_CLI::success(sprintf('Deleted %d file(s).', (int) $result['deleted']));
    }
}
