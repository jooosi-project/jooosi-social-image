<?php

declare(strict_types=1);

namespace JooosiSocialImage\Settings;

/**
 * Typed access to plugin-wide rendering settings.
 *
 * @since 0.1.0
 */
final class PluginSettings
{
    public const OPTION = 'social_image_settings';

    /**
     * @return array{format: string, quality: int, replace_featured: bool, delete_on_uninstall: bool}
     */
    public static function defaults(): array
    {
        return [
            'format' => 'png',
            'quality' => 90,
            'replace_featured' => false,
            'delete_on_uninstall' => false,
        ];
    }

    /**
     * @return array{format: string, quality: int, replace_featured: bool, delete_on_uninstall: bool}
     */
    public static function all(): array
    {
        $value = get_option(self::OPTION, []);

        return self::sanitize(array_merge(self::defaults(), is_array($value) ? $value : []));
    }

    public static function get(string $key, mixed $fallback = null): mixed
    {
        $settings = self::all();

        return array_key_exists($key, $settings) ? $settings[$key] : $fallback;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{format: string, quality: int, replace_featured: bool, delete_on_uninstall: bool}
     */
    public static function update(array $input): array
    {
        $clean = self::sanitize(array_merge(self::all(), $input));
        update_option(self::OPTION, $clean, false);

        return $clean;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{format: string, quality: int, replace_featured: bool, delete_on_uninstall: bool}
     */
    public static function sanitize(array $input): array
    {
        $format = strtolower((string) ($input['format'] ?? 'png'));
        return [
            'format' => in_array($format, ['png', 'jpeg', 'webp'], true) ? $format : 'png',
            'quality' => max(1, min(100, (int) ($input['quality'] ?? 90))),
            'replace_featured' => self::boolean($input['replace_featured'] ?? false),
            'delete_on_uninstall' => self::boolean($input['delete_on_uninstall'] ?? false),
        ];
    }

    private static function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
