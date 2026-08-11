<?php

declare (strict_types=1);
namespace JooosiEgami\Template;

use WP_Error;
use WP_Post;
use WP_Query;
/**
 * Persists versioned image templates in a private post type.
 *
 * @since 0.1.0
 */
final class TemplateRepository
{
    public const POST_TYPE = 'egami_template';
    public const META_DOCUMENT = '_egami_document';
    public const META_RULES = '_egami_rules';
    public const META_REVISION = '_egami_revision';
    public function registerPostType(bool $translateLabels = \true): void
    {
        $name = $translateLabels ? __('Egami Designs', 'jooosi-egami') : 'Egami Designs';
        $singularName = $translateLabels ? __('Egami Design', 'jooosi-egami') : 'Egami Design';
        register_post_type(self::POST_TYPE, ['labels' => ['name' => $name, 'singular_name' => $singularName], 'public' => \false, 'publicly_queryable' => \false, 'show_ui' => \false, 'show_in_menu' => \false, 'show_in_rest' => \false, 'exclude_from_search' => \true, 'supports' => ['title'], 'capability_type' => 'post', 'map_meta_cap' => \true]);
    }
    /**
     * @return list<array<string, mixed>>
     */
    public function all(bool $publishedOnly = \false): array
    {
        $query = new WP_Query(['post_type' => self::POST_TYPE, 'post_status' => $publishedOnly ? 'publish' : ['publish', 'draft'], 'posts_per_page' => -1, 'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'], 'no_found_rows' => \true, 'update_post_term_cache' => \false]);
        return array_map(fn(WP_Post $post): array => $this->format($post), $query->posts);
    }
    /**
     * @return array<string, mixed>|WP_Error
     */
    public function get(int $id): array|WP_Error
    {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return new WP_Error('egami_template_not_found', __('Design not found.', 'jooosi-egami'), ['status' => 404]);
        }
        return $this->format($post);
    }
    /**
     * @return array<string, mixed>|WP_Error
     */
    public function create(string $title = '', ?array $document = null, ?array $rules = null): array|WP_Error
    {
        $title = trim($title) !== '' ? sanitize_text_field($title) : __('Untitled design', 'jooosi-egami');
        $id = wp_insert_post(['post_type' => self::POST_TYPE, 'post_status' => 'draft', 'post_title' => $title], \true);
        if (is_wp_error($id)) {
            return $id;
        }
        update_post_meta($id, self::META_DOCUMENT, $document === null ? \JooosiEgami\Template\TemplateSchema::defaultDocument() : \JooosiEgami\Template\TemplateSchema::normalizeDocument($document));
        update_post_meta($id, self::META_RULES, $rules === null ? \JooosiEgami\Template\TemplateSchema::defaultRules() : \JooosiEgami\Template\TemplateSchema::normalizeRules($rules));
        update_post_meta($id, self::META_REVISION, 1);
        return $this->get($id);
    }
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function update(int $id, array $payload): array|WP_Error
    {
        $template = $this->get($id);
        if (is_wp_error($template)) {
            return $template;
        }
        $postUpdate = ['ID' => $id];
        if (isset($payload['title'])) {
            $postUpdate['post_title'] = sanitize_text_field((string) $payload['title']);
        }
        if (isset($payload['status'])) {
            $postUpdate['post_status'] = $payload['status'] === 'draft' ? 'draft' : 'publish';
        }
        $result = wp_update_post(wp_slash($postUpdate), \true);
        if (is_wp_error($result)) {
            return $result;
        }
        if (array_key_exists('document', $payload)) {
            update_post_meta($id, self::META_DOCUMENT, \JooosiEgami\Template\TemplateSchema::normalizeDocument($payload['document']));
        }
        if (array_key_exists('rules', $payload)) {
            update_post_meta($id, self::META_RULES, \JooosiEgami\Template\TemplateSchema::normalizeRules($payload['rules']));
        }
        update_post_meta($id, self::META_REVISION, (int) $template['revision'] + 1);
        return $this->get($id);
    }
    /**
     * @return array<string, mixed>|WP_Error
     */
    public function duplicate(int $id): array|WP_Error
    {
        $template = $this->get($id);
        if (is_wp_error($template)) {
            return $template;
        }
        /* translators: %s: Original design title. */
        $copy = $this->create(sprintf(__('%s (copy)', 'jooosi-egami'), $template['title']));
        if (is_wp_error($copy)) {
            return $copy;
        }
        return $this->update($copy['id'], ['status' => $template['status'], 'document' => $template['document'], 'rules' => $template['rules']]);
    }
    public function delete(int $id): bool|WP_Error
    {
        $template = $this->get($id);
        if (is_wp_error($template)) {
            return $template;
        }
        return (bool) wp_delete_post($id, \true);
    }
    /**
     * @return array<string, mixed>
     */
    private function format(WP_Post $post): array
    {
        $document = get_post_meta($post->ID, self::META_DOCUMENT, \true);
        $rules = get_post_meta($post->ID, self::META_RULES, \true);
        return ['id' => $post->ID, 'title' => $post->post_title, 'status' => $post->post_status, 'document' => \JooosiEgami\Template\TemplateSchema::normalizeDocument(is_array($document) ? $document : \JooosiEgami\Template\TemplateSchema::defaultDocument()), 'rules' => \JooosiEgami\Template\TemplateSchema::normalizeRules(is_array($rules) ? $rules : \JooosiEgami\Template\TemplateSchema::defaultRules()), 'revision' => max(1, (int) get_post_meta($post->ID, self::META_REVISION, \true)), 'modified' => mysql_to_rfc3339($post->post_modified_gmt)];
    }
}
