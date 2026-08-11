<?php

declare (strict_types=1);
namespace JooosiEgami\Content;

use WP_Post;
defined('ABSPATH') || exit;
final class DynamicDataResolver
{
    public function __construct(private ?\JooosiEgami\Content\PlaceholderRegistry $placeholders = null)
    {
        $this->placeholders ??= new \JooosiEgami\Content\PlaceholderRegistry();
    }
    public function resolve(int $post_id = 0, array $overrides = []): array
    {
        $post = $post_id ? get_post($post_id) : null;
        $site = array('name' => get_bloginfo('name'), 'tagline' => get_bloginfo('description'), 'url' => home_url('/'));
        if ($post) {
            $author_id = (int) $post->post_author;
            $excerpt = has_excerpt($post) ? $post->post_excerpt : wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 36, '…');
            $featured = get_post_thumbnail_id($post->ID);
            $post_data = array('id' => (int) $post->ID, 'title' => get_the_title($post), 'excerpt' => wp_strip_all_tags($excerpt), 'content' => wp_strip_all_tags(strip_shortcodes($post->post_content)), 'url' => get_permalink($post), 'date' => get_the_date('', $post), 'modified' => get_the_modified_date('', $post), 'type' => $post->post_type, 'status' => $post->post_status, 'featured_image' => $featured ? wp_get_attachment_image_url($featured, 'full') : '', 'featured_image_id' => (int) $featured, 'author' => array('id' => $author_id, 'name' => get_the_author_meta('display_name', $author_id), 'url' => get_author_posts_url($author_id), 'avatar' => get_avatar_url($author_id, array('size' => 256))));
            $meta = $this->postMeta($post->ID);
            $taxonomies = $this->taxonomies($post);
        } else {
            $post_data = array('id' => 0, 'title' => __('A dynamic title that fits your design', 'jooosi-egami'), 'excerpt' => __('Preview your design with any WordPress post.', 'jooosi-egami'), 'content' => '', 'url' => home_url('/'), 'date' => wp_date(get_option('date_format')), 'modified' => wp_date(get_option('date_format')), 'type' => 'post', 'status' => 'draft', 'featured_image' => '', 'featured_image_id' => 0, 'author' => array('id' => 0, 'name' => '', 'url' => '', 'avatar' => ''));
            $meta = array();
            $taxonomies = array();
        }
        $data = array('site' => $site, 'post' => $post_data, 'meta' => $meta, 'taxonomy' => $taxonomies);
        $data = array_replace_recursive($data, $this->placeholders->values($post_id));
        $data = array_replace_recursive($data, $overrides);
        return apply_filters('jooosi-egami/content:data', $data, array('post_id' => $post_id, 'post' => $post));
    }
    public function replace(string $value, array $data): string
    {
        $value = (string) $value;
        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', function ($match) use ($data) {
            $resolved = $this->getValue($data, $match[1]);
            return $this->stringify($resolved);
        }, $value);
    }
    public function rawValue(mixed $value, array $data): mixed
    {
        if (preg_match('/^\s*\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}\s*$/', (string) $value, $match)) {
            return $this->getValue($data, $match[1]);
        }
        return $this->replace($value, $data);
    }
    public function definitions(int $post_id = 0): array
    {
        return $this->placeholders->definitions($post_id);
    }
    private function getValue(array $data, string $path): mixed
    {
        $value = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return '';
            }
            $value = $value[$segment];
        }
        return $value;
    }
    private function postMeta(int $post_id): array
    {
        $all = get_post_meta($post_id);
        $out = array();
        foreach ($all as $key => $values) {
            if (is_protected_meta($key, 'post') && !apply_filters('jooosi-egami/content:allow_protected_meta', \false, $key, $post_id)) {
                continue;
            }
            $values = array_map('maybe_unserialize', (array) $values);
            $out[$key] = 1 === count($values) ? $this->normalizeValue(reset($values)) : $this->normalizeValue($values);
        }
        return $out;
    }
    private function taxonomies(WP_Post $post): array
    {
        $out = array();
        foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
            $terms = get_the_terms($post, $taxonomy);
            $out[$taxonomy] = is_wp_error($terms) || !$terms ? '' : implode(', ', wp_list_pluck($terms, 'name'));
        }
        return $out;
    }
    private function normalizeValue(mixed $value): mixed
    {
        if (is_scalar($value) || null === $value) {
            return $value;
        }
        if (is_array($value)) {
            $out = array();
            foreach ($value as $key => $item) {
                $out[$key] = $this->normalizeValue($item);
            }
            return $out;
        }
        return '';
    }
    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            $flat = array();
            array_walk_recursive($value, static function ($item) use (&$flat) {
                if (is_scalar($item)) {
                    $flat[] = (string) $item;
                }
            });
            return implode(', ', $flat);
        }
        return '';
    }
}
