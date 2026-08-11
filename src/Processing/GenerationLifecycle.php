<?php

declare (strict_types=1);
namespace JooosiEgami\Processing;

use JooosiEgami\Assignment\TemplateMatcher;
use JooosiEgami\Rendering\ImageGenerator;
use JooosiEgami\Template\TemplateRepository;
use WP_Post;
/**
 * Enqueues content-driven renders without blocking editorial or frontend requests.
 *
 * @since 0.1.0
 */
final class GenerationLifecycle
{
    public function __construct(private TemplateMatcher $matcher, private ImageGenerator $imageGenerator)
    {
    }
    public function registerHooks(): void
    {
        add_action('save_post', [$this, 'onSavePost'], 100, 3);
        add_action('template_redirect', [$this, 'onTemplateRedirect']);
    }
    public function onSavePost(int $postId, WP_Post $post, bool $update): void
    {
        unset($update);
        if ($post->post_type === TemplateRepository::POST_TYPE || $post->post_type === 'attachment') {
            return;
        }
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId) || defined('DOING_AUTOSAVE') && \DOING_AUTOSAVE) {
            return;
        }
        if ($this->matcher->forPost($postId) === []) {
            return;
        }
        $this->imageGenerator->schedulePost($postId);
    }
    public function onTemplateRedirect(): void
    {
        if (!is_singular()) {
            return;
        }
        $postId = absint(get_queried_object_id());
        if ($postId === 0 || !$this->needsGeneration($postId)) {
            return;
        }
        $this->imageGenerator->schedulePost($postId);
    }
    private function needsGeneration(int $postId): bool
    {
        $matches = $this->matcher->forPost($postId);
        if ($matches === []) {
            return \false;
        }
        $map = get_post_meta($postId, ImageGenerator::META_MAP, \true);
        $map = is_array($map) ? $map : [];
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return \false;
        }
        $cacheRoot = trailingslashit(wp_normalize_path($uploads['basedir'])) . 'egami/';
        $metaKeys = ['og' => ImageGenerator::META_OG, 'twitter' => ImageGenerator::META_TWITTER, 'featured' => ImageGenerator::META_FEATURED];
        foreach ($matches as $output => $template) {
            $entry = $map[$output] ?? null;
            if (!is_array($entry) || (int) ($entry['template_id'] ?? 0) !== (int) $template['id']) {
                return \true;
            }
            $file = wp_normalize_path((string) ($entry['file'] ?? ''));
            if ($file === '' || !str_starts_with($file, $cacheRoot) || !is_file($file) || !is_readable($file) || (int) filesize($file) <= 0) {
                return \true;
            }
            if (isset($metaKeys[$output]) && (string) get_post_meta($postId, $metaKeys[$output], \true) === '') {
                return \true;
            }
            if ($output === 'featured' && (empty($entry['attachment_id']) || !get_post((int) $entry['attachment_id']))) {
                return \true;
            }
        }
        return \false;
    }
}
