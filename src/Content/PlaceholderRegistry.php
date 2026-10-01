<?php

declare (strict_types=1);
namespace JooosiSocialImage\Content;

use JooosiSocialImage\Integration\CustomFields\AcfPlaceholderProvider;
use JooosiSocialImage\Integration\CustomFields\JetEnginePlaceholderProvider;
use JooosiSocialImage\Integration\CustomFields\MetaBoxPlaceholderProvider;
use JooosiSocialImage\Integration\CustomFields\ToolsetPlaceholderProvider;
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
        $this->providers = $providers ?? [new AcfPlaceholderProvider(), new JetEnginePlaceholderProvider(), new MetaBoxPlaceholderProvider(), new ToolsetPlaceholderProvider()];
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
        $definitions = apply_filters('jooosi-social-image/content:placeholder_definitions', $definitions, ['post_id' => $postId]);
        if (!is_array($definitions)) {
            return [];
        }
        $normalized = [];
        foreach ($definitions as $key => $definition) {
            $key = self::normalizePath((string) $key);
            if ($key === '' || !is_array($definition)) {
                continue;
            }
            $type = sanitize_key((string) ($definition['type'] ?? 'text'));
            if (!in_array($type, ['text', 'image', 'url', 'number', 'date'], \true)) {
                $type = 'text';
            }
            $normalized[] = ['key' => $key, 'label' => sanitize_text_field((string) ($definition['label'] ?? self::labelFromKey($key))), 'group' => sanitize_text_field((string) ($definition['group'] ?? __('Other', 'jooosi-social-image'))), 'type' => $type, 'description' => sanitize_text_field((string) ($definition['description'] ?? ''))];
        }
        usort($normalized, static fn(array $left, array $right): int => [$left['group'], $left['label']] <=> [$right['group'], $right['label']]);
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
        $values = apply_filters('jooosi-social-image/content:placeholder_values', $values, ['post_id' => $postId]);
        return is_array($values) ? $this->normalizeValues($values) : [];
    }
    public static function normalizePath(string $path): string
    {
        $segments = array_filter(array_map('sanitize_key', explode('.', strtolower(trim($path)))), static fn(string $segment): bool => $segment !== '');
        return implode('.', $segments);
    }
    /**
     * @return array<string, array<string, string>>
     */
    private function builtinDefinitions(int $postId): array
    {
        $definitions = ['site.name' => ['label' => __('Site name', 'jooosi-social-image'), 'group' => __('Site', 'jooosi-social-image')], 'site.tagline' => ['label' => __('Site tagline', 'jooosi-social-image'), 'group' => __('Site', 'jooosi-social-image')], 'site.url' => ['label' => __('Site URL', 'jooosi-social-image'), 'group' => __('Site', 'jooosi-social-image'), 'type' => 'url'], 'post.id' => ['label' => __('Post ID', 'jooosi-social-image'), 'group' => __('Post', 'jooosi-social-image'), 'type' => 'number'], 'post.title' => ['label' => __('Title', 'jooosi-social-image'), 'group' => __('Post', 'jooosi-social-image')], 'post.excerpt' => ['label' => __('Excerpt', 'jooosi-social-image'), 'group' => __('Post', 'jooosi-social-image')], 'post.content' => ['label' => __('Content', 'jooosi-social-image'), 'group' => __('Post', 'jooosi-social-image')], 'post.url' => ['label' => __('Permalink', 'jooosi-social-image'), 'group' => __('Post', 'jooosi-social-image'), 'type' => 'url'], 'post.date' => ['label' => __('Published date', 'jooosi-social-image'), 'group' => __('Post', 'jooosi-social-image'), 'type' => 'date'], 'post.modified' => ['label' => __('Modified date', 'jooosi-social-image'), 'group' => __('Post', 'jooosi-social-image'), 'type' => 'date'], 'post.type' => ['label' => __('Post type', 'jooosi-social-image'), 'group' => __('Post', 'jooosi-social-image')], 'post.status' => ['label' => __('Post status', 'jooosi-social-image'), 'group' => __('Post', 'jooosi-social-image')], 'post.featured_image' => ['label' => __('Featured image', 'jooosi-social-image'), 'group' => __('Post', 'jooosi-social-image'), 'type' => 'image'], 'post.featured_image_id' => ['label' => __('Featured image ID', 'jooosi-social-image'), 'group' => __('Post', 'jooosi-social-image'), 'type' => 'number'], 'post.author.id' => ['label' => __('Author ID', 'jooosi-social-image'), 'group' => __('Author', 'jooosi-social-image'), 'type' => 'number'], 'post.author.name' => ['label' => __('Author name', 'jooosi-social-image'), 'group' => __('Author', 'jooosi-social-image')], 'post.author.url' => ['label' => __('Author URL', 'jooosi-social-image'), 'group' => __('Author', 'jooosi-social-image'), 'type' => 'url'], 'post.author.avatar' => ['label' => __('Author avatar', 'jooosi-social-image'), 'group' => __('Author', 'jooosi-social-image'), 'type' => 'image']];
        $post = $postId > 0 ? get_post($postId) : null;
        if (!$post) {
            return $definitions;
        }
        foreach (get_object_taxonomies($post->post_type, 'objects') as $taxonomy) {
            if (!is_object($taxonomy) || empty($taxonomy->name)) {
                continue;
            }
            $definitions['taxonomy.' . $taxonomy->name] = ['label' => (string) ($taxonomy->labels->singular_name ?? $taxonomy->label ?? $taxonomy->name), 'group' => __('Taxonomies', 'jooosi-social-image')];
        }
        foreach (array_keys((array) get_post_meta($postId)) as $metaKey) {
            if (is_protected_meta((string) $metaKey, 'post')) {
                continue;
            }
            $definitions['meta.' . $metaKey] = ['label' => self::labelFromKey((string) $metaKey), 'group' => __('Custom fields', 'jooosi-social-image')];
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
