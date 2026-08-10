<?php

declare(strict_types=1);

namespace JooosiEgami\Content;

use JooosiEgami\Integration\CustomFields\AcfPlaceholderProvider;
use JooosiEgami\Integration\CustomFields\JetEnginePlaceholderProvider;
use JooosiEgami\Integration\CustomFields\MetaBoxPlaceholderProvider;
use JooosiEgami\Integration\CustomFields\ToolsetPlaceholderProvider;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Discovers placeholders for the editor and their render-time values.
 */
final class PlaceholderRegistry
{
    /** @var list<PlaceholderProviderInterface> */
    private array $providers;

    /**
     * @param list<PlaceholderProviderInterface>|null $providers
     */
    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?? [
            new AcfPlaceholderProvider(),
            new JetEnginePlaceholderProvider(),
            new MetaBoxPlaceholderProvider(),
            new ToolsetPlaceholderProvider(),
        ];
    }

    /**
     * @return list<array{key: string, label: string, group: string, type: string, description: string}>
     */
    public function definitions(int $postId = 0): array
    {
        $definitions = $this->builtinDefinitions($postId);

        foreach ($this->providers as $provider) {
            try {
                foreach ($provider->definitions($postId) as $key => $definition) {
                    $definitions[$provider->namespace() . '.' . $key] = $definition;
                }
            } catch (Throwable) {
                continue;
            }
        }

        /**
         * Filters the registered dynamic placeholder catalogue.
         *
         * Add entries keyed by their complete placeholder path. Each entry may
         * contain label, group, type, and description keys.
         *
         * @param array<string, array<string, string>> $definitions
         * @param array{post_id: int}                  $context
         */
        $definitions = apply_filters(
            'jooosi-egami/content:placeholder_definitions',
            $definitions,
            ['post_id' => $postId],
        );

        if (! is_array($definitions)) {
            return [];
        }

        $normalized = [];
        foreach ($definitions as $key => $definition) {
            $key = self::normalizePath((string) $key);
            if ($key === '' || ! is_array($definition)) {
                continue;
            }

            $type = sanitize_key((string) ($definition['type'] ?? 'text'));
            if (! in_array($type, ['text', 'image', 'url', 'number', 'date'], true)) {
                $type = 'text';
            }

            $normalized[] = [
                'key' => $key,
                'label' => sanitize_text_field((string) ($definition['label'] ?? self::labelFromKey($key))),
                'group' => sanitize_text_field((string) ($definition['group'] ?? __('Other', 'jooosi-egami'))),
                'type' => $type,
                'description' => sanitize_text_field((string) ($definition['description'] ?? '')),
            ];
        }

        usort(
            $normalized,
            static fn (array $left, array $right): int => [$left['group'], $left['label']] <=> [$right['group'], $right['label']],
        );

        return $normalized;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function values(int $postId): array
    {
        $values = [];

        if ($postId > 0) {
            foreach ($this->providers as $provider) {
                try {
                    $providerValues = $provider->values($postId);
                    if ($providerValues !== []) {
                        $values[$provider->namespace()] = $this->normalizeValues($providerValues);
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        /**
         * Filters render-time values for registered placeholders.
         *
         * Values use the same nested shape as their dot-separated paths, for
         * example: ['commerce' => ['price' => '29.00']].
         *
         * @param array<string, mixed> $values
         * @param array{post_id: int}  $context
         */
        $values = apply_filters(
            'jooosi-egami/content:placeholder_values',
            $values,
            ['post_id' => $postId],
        );

        return is_array($values) ? $this->normalizeValues($values) : [];
    }

    public static function normalizePath(string $path): string
    {
        $segments = array_filter(
            array_map('sanitize_key', explode('.', strtolower(trim($path)))),
            static fn (string $segment): bool => $segment !== '',
        );

        return implode('.', $segments);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function builtinDefinitions(int $postId): array
    {
        $definitions = [
            'site.name' => ['label' => __('Site name', 'jooosi-egami'), 'group' => __('Site', 'jooosi-egami')],
            'site.tagline' => ['label' => __('Site tagline', 'jooosi-egami'), 'group' => __('Site', 'jooosi-egami')],
            'site.url' => ['label' => __('Site URL', 'jooosi-egami'), 'group' => __('Site', 'jooosi-egami'), 'type' => 'url'],
            'post.id' => ['label' => __('Post ID', 'jooosi-egami'), 'group' => __('Post', 'jooosi-egami'), 'type' => 'number'],
            'post.title' => ['label' => __('Title', 'jooosi-egami'), 'group' => __('Post', 'jooosi-egami')],
            'post.excerpt' => ['label' => __('Excerpt', 'jooosi-egami'), 'group' => __('Post', 'jooosi-egami')],
            'post.content' => ['label' => __('Content', 'jooosi-egami'), 'group' => __('Post', 'jooosi-egami')],
            'post.url' => ['label' => __('Permalink', 'jooosi-egami'), 'group' => __('Post', 'jooosi-egami'), 'type' => 'url'],
            'post.date' => ['label' => __('Published date', 'jooosi-egami'), 'group' => __('Post', 'jooosi-egami'), 'type' => 'date'],
            'post.modified' => ['label' => __('Modified date', 'jooosi-egami'), 'group' => __('Post', 'jooosi-egami'), 'type' => 'date'],
            'post.type' => ['label' => __('Post type', 'jooosi-egami'), 'group' => __('Post', 'jooosi-egami')],
            'post.status' => ['label' => __('Post status', 'jooosi-egami'), 'group' => __('Post', 'jooosi-egami')],
            'post.featured_image' => ['label' => __('Featured image', 'jooosi-egami'), 'group' => __('Post', 'jooosi-egami'), 'type' => 'image'],
            'post.featured_image_id' => ['label' => __('Featured image ID', 'jooosi-egami'), 'group' => __('Post', 'jooosi-egami'), 'type' => 'number'],
            'post.author.id' => ['label' => __('Author ID', 'jooosi-egami'), 'group' => __('Author', 'jooosi-egami'), 'type' => 'number'],
            'post.author.name' => ['label' => __('Author name', 'jooosi-egami'), 'group' => __('Author', 'jooosi-egami')],
            'post.author.url' => ['label' => __('Author URL', 'jooosi-egami'), 'group' => __('Author', 'jooosi-egami'), 'type' => 'url'],
            'post.author.avatar' => ['label' => __('Author avatar', 'jooosi-egami'), 'group' => __('Author', 'jooosi-egami'), 'type' => 'image'],
        ];

        $post = $postId > 0 ? get_post($postId) : null;
        if (! $post) {
            return $definitions;
        }

        foreach (get_object_taxonomies($post->post_type, 'objects') as $taxonomy) {
            if (! is_object($taxonomy) || empty($taxonomy->name)) {
                continue;
            }
            $definitions['taxonomy.' . $taxonomy->name] = [
                'label' => (string) ($taxonomy->labels->singular_name ?? $taxonomy->label ?? $taxonomy->name),
                'group' => __('Taxonomies', 'jooosi-egami'),
            ];
        }

        foreach (array_keys((array) get_post_meta($postId)) as $metaKey) {
            if (is_protected_meta((string) $metaKey, 'post')) {
                continue;
            }
            $definitions['meta.' . $metaKey] = [
                'label' => self::labelFromKey((string) $metaKey),
                'group' => __('Custom fields', 'jooosi-egami'),
            ];
        }

        return $definitions;
    }

    private function normalizeValues(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            $key = is_int($key) ? $key : sanitize_key((string) $key);
            if ($key === '') {
                continue;
            }
            $normalized[$key] = $this->normalizeValue($value);
        }
        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if ($value instanceof \WP_Post) {
            return get_permalink($value);
        }

        if ($value instanceof \WP_Term || $value instanceof \WP_User) {
            return $value->name ?? $value->display_name ?? '';
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : '';
        }

        if (isset($value['url']) && is_scalar($value['url'])) {
            return (string) $value['url'];
        }

        if (isset($value['ID']) && is_numeric($value['ID'])) {
            $url = wp_get_attachment_image_url((int) $value['ID'], 'full');
            if ($url) {
                return $url;
            }
        }

        return $this->normalizeValues($value);
    }

    private static function labelFromKey(string $key): string
    {
        $segments = explode('.', $key);
        return ucwords(str_replace(['_', '-'], ' ', (string) end($segments)));
    }
}
