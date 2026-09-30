<?php

declare(strict_types=1);

namespace JooosiSocialImage\Integration\CustomFields;

use JooosiSocialImage\Content\PlaceholderProviderInterface;

defined('ABSPATH') || exit;

final class AcfPlaceholderProvider implements PlaceholderProviderInterface
{
    public function namespace(): string
    {
        return 'acf';
    }

    public function definitions(int $postId): array
    {
        return $this->fields($postId, false);
    }

    public function values(int $postId): array
    {
        return $this->fields($postId, true);
    }

    private function fields(int $postId, bool $values): array
    {
        if ($postId < 1 || ! function_exists('get_field_objects')) {
            return [];
        }

        $fields = get_field_objects($postId, true);
        if (! is_array($fields)) {
            return [];
        }

        $result = [];
        foreach ($fields as $field) {
            if (! is_array($field) || empty($field['name'])) {
                continue;
            }
            $key = sanitize_key((string) $field['name']);
            if ($values) {
                $value = $field['value'] ?? null;
                if (($field['type'] ?? '') === 'image') {
                    if (is_numeric($value)) {
                        $value = wp_get_attachment_image_url((int) $value, 'full') ?: $value;
                    } elseif (is_array($value) && isset($value['url'])) {
                        $value = $value['url'];
                    }
                }
                $result[$key] = $value;
                continue;
            }
            $result[$key] = [
                'label' => (string) ($field['label'] ?? $field['name']),
                'group' => __('ACF', 'jooosi-social-image'),
                'type' => $this->type((string) ($field['type'] ?? '')),
            ];
        }
        return $result;
    }

    private function type(string $type): string
    {
        return match ($type) {
            'image' => 'image',
            'url', 'link' => 'url',
            'number', 'range' => 'number',
            'date_picker', 'date_time_picker', 'time_picker' => 'date',
            default => 'text',
        };
    }
}
