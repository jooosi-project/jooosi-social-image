<?php

declare (strict_types=1);
namespace JooosiSocialImage\Integration\CustomFields;

use JooosiSocialImage\Content\PlaceholderProviderInterface;
defined('ABSPATH') || exit;
final class MetaBoxPlaceholderProvider implements PlaceholderProviderInterface
{
    public function namespace(): string
    {
        return 'metabox';
    }
    public function definitions(int $postId): array
    {
        $result = [];
        foreach ($this->fields($postId) as $key => $field) {
            $result[$key] = ['label' => (string) ($field['name'] ?? $key), 'group' => __('Meta Box', 'jooosi-social-image'), 'type' => $this->type((string) ($field['type'] ?? ''))];
        }
        return $result;
    }
    public function values(int $postId): array
    {
        if (!function_exists('JooosiSocialImageDeps\rwmb_get_value')) {
            return [];
        }
        $result = [];
        foreach ($this->fields($postId) as $key => $field) {
            $value = rwmb_get_value((string) ($field['id'] ?? $key), [], $postId);
            if (str_contains((string) ($field['type'] ?? ''), 'image')) {
                $value = $this->imageUrl($value);
            }
            $result[$key] = $value;
        }
        return $result;
    }
    private function fields(int $postId): array
    {
        if ($postId < 1 || !function_exists('JooosiSocialImageDeps\rwmb_get_object_fields')) {
            return [];
        }
        $fields = rwmb_get_object_fields($postId);
        if (!is_array($fields)) {
            return [];
        }
        $result = [];
        foreach ($fields as $key => $field) {
            if (!is_array($field)) {
                continue;
            }
            $id = sanitize_key((string) ($field['id'] ?? $key));
            if ($id !== '') {
                $result[$id] = $field;
            }
        }
        return $result;
    }
    private function type(string $type): string
    {
        if (str_contains($type, 'image')) {
            return 'image';
        }
        return match ($type) {
            'url' => 'url',
            'number', 'range', 'slider' => 'number',
            'date', 'datetime', 'time' => 'date',
            default => 'text',
        };
    }
    private function imageUrl(mixed $value): mixed
    {
        if (is_numeric($value)) {
            return wp_get_attachment_image_url((int) $value, 'full') ?: $value;
        }
        if (!is_array($value)) {
            return $value;
        }
        if (isset($value['url'])) {
            return $value['url'];
        }
        foreach ($value as $image) {
            if (is_array($image) && isset($image['url'])) {
                return $image['url'];
            }
            if (is_numeric($image)) {
                $url = wp_get_attachment_image_url((int) $image, 'full');
                if ($url) {
                    return $url;
                }
            }
        }
        return $value;
    }
}
