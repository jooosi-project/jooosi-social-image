<?php

declare (strict_types=1);
namespace JooosiSocialImageDeps;

\defined('WP_UNINSTALL_PLUGIN') || exit;
(static function (): void {
    $settings = \get_option('social_image_settings', []);
    if (empty($settings['delete_on_uninstall'])) {
        return;
    }
    $templateIds = \get_posts(['post_type' => 'social_image_design', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids']);
    foreach ($templateIds as $templateId) {
        \wp_delete_post((int) $templateId, \true);
    }
    $attachmentIds = \get_posts([
        'post_type' => 'attachment',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_key' => '_social_image_generated',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time uninstall cleanup.
        'meta_value' => '1',
    ]);
    foreach ($attachmentIds as $attachmentId) {
        \wp_delete_attachment((int) $attachmentId, \true);
    }
    foreach (['_social_image_og_image_url', '_social_image_twitter_image_url', '_social_image_featured_image_url', '_social_image_render_map', '_social_image_last_error'] as $metaKey) {
        \delete_post_meta_by_key($metaKey);
    }
    \delete_option('social_image_settings');
    \delete_option('social_image_preset_repositories');
    \delete_option('social_image_preset_repository_cache');
    \delete_option('social_image_bundled_preset_repository_enabled');
    \delete_option('social_image_last_cron_error');
    $uploads = \wp_upload_dir();
    $directory = \trailingslashit($uploads['basedir']) . 'social-image';
    if (\is_dir($directory)) {
        require_once \ABSPATH . 'wp-admin/includes/file.php';
        if (\WP_Filesystem()) {
            global $wp_filesystem;
            $wp_filesystem->delete($directory, \true);
        }
    }
})();
