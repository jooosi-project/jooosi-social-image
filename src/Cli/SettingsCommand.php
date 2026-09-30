<?php

declare(strict_types=1);

namespace JooosiSocialImage\Cli;

use JooosiSocialImage\Settings\PluginSettings;
use WP_CLI;

/**
 * Reads and updates plugin settings.
 *
 * @since 0.1.0
 */
final class SettingsCommand
{
    public function get(array $args, array $assocArgs): void
    {
        WP_CLI::line((string) wp_json_encode(PluginSettings::all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function set(array $args, array $assocArgs): void
    {
        $allowed = array_intersect_key($assocArgs, array_flip(array_keys(PluginSettings::defaults())));

        if ($allowed === []) {
            WP_CLI::error('Provide at least one setting flag.');
        }

        WP_CLI::success((string) wp_json_encode(PluginSettings::update($allowed), JSON_UNESCAPED_SLASHES));
    }
}

