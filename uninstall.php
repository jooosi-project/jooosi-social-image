<?php

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

(static function (): void {

$settings = get_option('egami_settings', []);

if (empty($settings['delete_on_uninstall'])) {
    return;
}

$templateIds = get_posts([
    'post_type' => 'egami_template',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids',
]);

foreach ($templateIds as $templateId) {
    wp_delete_post((int) $templateId, true);
}

$attachmentIds = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_key' => '_egami_generated', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time uninstall cleanup.
    'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One-time uninstall cleanup.
]);

foreach ($attachmentIds as $attachmentId) {
    wp_delete_attachment((int) $attachmentId, true);
}

foreach ([
    '_egami_og_image_url',
    '_egami_twitter_image_url',
    '_egami_featured_image_url',
    '_egami_render_map',
    '_egami_last_error',
] as $metaKey) {
    delete_post_meta_by_key($metaKey);
}

delete_option('egami_settings');
delete_option('egami_preset_repositories');
delete_option('egami_preset_repository_cache');
delete_option('egami_bundled_preset_repository_enabled');
delete_option('egami_last_cron_error');

$uploads = wp_upload_dir();
$directory = trailingslashit($uploads['basedir']) . 'egami';

if (is_dir($directory)) {
    require_once ABSPATH . 'wp-admin/includes/file.php';

    if (WP_Filesystem()) {
        global $wp_filesystem;
        $wp_filesystem->delete($directory, true);
    }
}
})();
