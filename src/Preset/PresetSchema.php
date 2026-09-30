<?php

declare(strict_types=1);

namespace JooosiSocialImage\Preset;

use JooosiSocialImage\Template\TemplateSchema;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Application-level validation and normalization for public preset manifests.
 *
 * The distributable JSON Schemas remain the language-neutral contract. This
 * class is the WordPress trust boundary used before repository data reaches
 * the administration app.
 */
final class PresetSchema
{
    public const VERSION = 1;

    public const MAX_PRESETS = 500;

    /**
     * @return array<string, mixed>|WP_Error
     */
    public static function repository(mixed $input): array|WP_Error
    {
        if (! is_array($input)) {
            return self::error('repository', __('The preset repository must be a JSON object.', 'jooosi-social-image'));
        }

        if ((int) ($input['schemaVersion'] ?? 0) !== self::VERSION) {
            return self::error('schema_version', __('The preset repository uses an unsupported schema version.', 'jooosi-social-image'));
        }

        $id = self::slug($input['id'] ?? '');
        $title = self::text($input['title'] ?? '', 120);
        $description = self::textarea($input['description'] ?? '', 500);
        $version = self::text($input['version'] ?? '', 64);
        $presets = $input['presets'] ?? null;

        if ($id === '' || $title === '' || $description === '' || $version === '') {
            return self::error('metadata', __('The preset repository is missing valid id, version, title, or description metadata.', 'jooosi-social-image'));
        }

        if (! is_array($presets) || $presets === [] || count($presets) > self::MAX_PRESETS || ! self::isList($presets)) {
            return self::error(
                'presets',
                /* translators: %d: Maximum number of presets in a repository. */
                sprintf(__('A preset repository must contain between 1 and %d presets.', 'jooosi-social-image'), self::MAX_PRESETS),
            );
        }

        $cleanPresets = [];
        $ids = [];

        foreach ($presets as $index => $preset) {
            $clean = self::preset($preset);

            if (is_wp_error($clean)) {
                return self::error(
                    'preset',
                    /* translators: 1: Preset position, 2: Validation error message. */
                    sprintf(__('Preset %1$d is invalid: %2$s', 'jooosi-social-image'), $index + 1, $clean->get_error_message()),
                );
            }

            if (isset($ids[$clean['id']])) {
                /* translators: %s: Duplicated preset identifier. */
                return self::error('duplicate_preset', sprintf(__('The preset id “%s” is duplicated.', 'jooosi-social-image'), $clean['id']));
            }

            $ids[$clean['id']] = true;
            $cleanPresets[] = $clean;
        }

        $homepage = self::url($input['homepage'] ?? '');
        $updatedAt = self::date($input['updatedAt'] ?? '');

        return [
            '$schema' => self::text($input['$schema'] ?? '', 2048),
            'schemaVersion' => self::VERSION,
            'id' => $id,
            'version' => $version,
            'title' => $title,
            'description' => $description,
            'homepage' => $homepage,
            'updatedAt' => $updatedAt,
            'presets' => $cleanPresets,
        ];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public static function preset(mixed $input): array|WP_Error
    {
        if (! is_array($input) || (int) ($input['schemaVersion'] ?? 0) !== self::VERSION) {
            return self::error('schema_version', __('The preset uses an unsupported schema version.', 'jooosi-social-image'));
        }

        $id = self::slug($input['id'] ?? '');
        $title = self::text($input['title'] ?? '', 120);
        $description = self::textarea($input['description'] ?? '', 500);
        $category = self::text($input['category'] ?? '', 80);

        if ($id === '' || $title === '' || $description === '' || $category === '') {
            return self::error('metadata', __('The preset is missing valid id, title, description, or category metadata.', 'jooosi-social-image'));
        }

        $document = $input['document'] ?? null;
        $documentError = self::documentError($document);

        if ($documentError !== '') {
            return self::error('document', $documentError);
        }

        $tags = [];
        foreach (array_slice(is_array($input['tags'] ?? null) ? $input['tags'] : [], 0, 20) as $tag) {
            $tag = self::text($tag, 40);

            if ($tag !== '') {
                $tags[$tag] = $tag;
            }
        }

        $clean = [
            'schemaVersion' => self::VERSION,
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'document' => TemplateSchema::normalizeDocument($document),
        ];

        if ($tags !== []) {
            $clean['tags'] = array_values($tags);
        }

        return $clean;
    }

    private static function documentError(mixed $document): string
    {
        if (! is_array($document) || (int) ($document['version'] ?? 0) !== TemplateSchema::DOCUMENT_VERSION) {
            return __('The preset document uses an unsupported Social Image document schema version.', 'jooosi-social-image');
        }

        $width = $document['width'] ?? null;
        $height = $document['height'] ?? null;

        if (! is_int($width) || ! is_int($height) || $width < 200 || $width > 2400 || $height < 200 || $height > 2400) {
            return __('The preset canvas dimensions must be integers between 200 and 2400 pixels.', 'jooosi-social-image');
        }

        if (! is_array($document['background'] ?? null)) {
            return __('The preset document requires a background object.', 'jooosi-social-image');
        }

        $elements = $document['elements'] ?? null;

        if (! is_array($elements) || $elements === [] || count($elements) > 100 || ! self::isList($elements)) {
            return __('The preset document must contain between 1 and 100 elements.', 'jooosi-social-image');
        }

        $ids = [];
        foreach ($elements as $index => $element) {
            if (! is_array($element)) {
                /* translators: %d: Element position in the preset document. */
                return sprintf(__('Element %d must be an object.', 'jooosi-social-image'), $index + 1);
            }

            $id = self::slug($element['id'] ?? '');
            $type = (string) ($element['type'] ?? '');

            if ($id === '' || ! in_array($type, ['text', 'image', 'svg', 'shape'], true)) {
                /* translators: %d: Element position in the preset document. */
                return sprintf(__('Element %d has an invalid id or type.', 'jooosi-social-image'), $index + 1);
            }

            if (isset($ids[$id])) {
                /* translators: %s: Duplicated element identifier. */
                return sprintf(__('Element id “%s” is duplicated.', 'jooosi-social-image'), $id);
            }

            $ids[$id] = true;
        }

        return '';
    }

    private static function slug(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return strlen($value) <= 100 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) ? $value : '';
    }

    private static function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    private static function text(mixed $value, int $length): string
    {
        return substr(sanitize_text_field((string) $value), 0, $length);
    }

    private static function textarea(mixed $value, int $length): string
    {
        return substr(sanitize_textarea_field((string) $value), 0, $length);
    }

    private static function url(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '' : esc_url_raw($value, ['http', 'https']);
    }

    private static function date(mixed $value): string
    {
        $value = trim((string) $value);

        return $value !== '' && strtotime($value) !== false ? $value : '';
    }

    private static function error(string $code, string $message): WP_Error
    {
        return new WP_Error('social_image_preset_' . $code, $message, ['status' => 422]);
    }
}
