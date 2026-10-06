<?php

declare (strict_types=1);
namespace JooosiSocialImage\Admin\Menu;

use JooosiSocialImage\Admin\Controller\SocialImageController;
use JooosiSocialImage\Bootstrap\Paths;
use JooosiSocialImage\Diagnostics\SystemDiagnostics;
use JooosiSocialImage\Integration\JooosiFon;
use JooosiSocialImage\Template\TemplateRepository;
use JooosiSocialImageDeps\Nabasa\VitePlus\Assets;
/**
 * Registers the WordPress admin app shell and diagnostics.
 *
 * @since 0.1.0
 */
final class AdminMenu
{
    private const PAGE_SLUG = 'jooosi-social-image';
    private const ASSET_HANDLE = 'jooosi-social-image-admin';
    private string $pageHook = '';
    public function __construct(private Paths $paths, private SystemDiagnostics $diagnostics, private JooosiFon $fon)
    {
    }
    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_notices', [$this, 'rendererNotice']);
        add_filter('site_status_tests', [$this, 'siteHealth']);
        add_filter('plugin_action_links_' . \JOOOSI_SOCIAL_IMAGE_PLUGIN_BASENAME, [$this, 'actionLinks']);
    }
    public function registerMenu(): void
    {
        $this->pageHook = add_menu_page(
            __('Jooosi Social Image', 'jooosi-social-image'),
            __('Jooosi Social Image', 'jooosi-social-image'),
            'edit_posts',
            self::PAGE_SLUG,
            [$this, 'render'],
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin asset.
            'data:image/svg+xml;base64,' . base64_encode(file_get_contents($this->paths->rootDir . '/jooosi-social-image.svg')),
            100
        );
    }
    public function enqueueAssets(string $hookSuffix): void
    {
        if ($hookSuffix !== $this->pageHook) {
            return;
        }
        wp_enqueue_media();
        $fonStylesheet = $this->fon->stylesheetUrl();
        if ('' !== $fonStylesheet) {
            wp_enqueue_style('jooosi-social-image-jooosi-fon', $fonStylesheet);
        }
        (new Assets($this->paths->rootDir . '/assets/dist'))->enqueue('resources/App.tsx', ['handle' => self::ASSET_HANDLE, 'in_footer' => \true]);
        wp_add_inline_script(self::ASSET_HANDLE, 'window.SocialImageConfig = ' . wp_json_encode(['restUrl' => esc_url_raw(rest_url(SocialImageController::NAMESPACE)), 'nonce' => wp_create_nonce('wp_rest'), 'adminUrl' => admin_url('admin.php?page=' . self::PAGE_SLUG), 'pluginUrl' => \JOOOSI_SOCIAL_IMAGE_PLUGIN_URL, 'version' => \JOOOSI_SOCIAL_IMAGE_VERSION, 'postTypes' => $this->postTypes(), 'canManage' => current_user_can('manage_options')]) . ';', 'before');
    }
    public function render(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You are not allowed to access Social Image.', 'jooosi-social-image'));
        }
        echo '<div id="social-image-admin"></div>';
    }
    public function rendererNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page !== self::PAGE_SLUG) {
            return;
        }
        $status = $this->diagnostics->status();
        if ($status['renderer']['available']) {
            return;
        }
        echo '<div class="notice notice-error"><p><strong>' . esc_html__('Social Image cannot render images:', 'jooosi-social-image') . '</strong> ' . esc_html__('Enable the PHP Imagick extension or PHP GD with FreeType support, then reload this page.', 'jooosi-social-image') . '</p></div>';
    }
    public function siteHealth(array $tests): array
    {
        $tests['direct']['jooosi_social_image_renderer'] = ['label' => __('Social Image renderer', 'jooosi-social-image'), 'test' => [$this, 'siteHealthRenderer']];
        $tests['direct']['jooosi_social_image_svg'] = ['label' => __('Social Image SVG renderer', 'jooosi-social-image'), 'test' => [$this, 'siteHealthSvg']];
        $tests['direct']['jooosi_social_image_jooosi_fon'] = ['label' => __('Social Image Jooosi Fon integration', 'jooosi-social-image'), 'test' => [$this, 'siteHealthFon']];
        $tests['direct']['jooosi_social_image_fonts'] = ['label' => __('Social Image server fonts', 'jooosi-social-image'), 'test' => [$this, 'siteHealthFonts']];
        $tests['direct']['jooosi_social_image_filesystem'] = ['label' => __('Social Image storage', 'jooosi-social-image'), 'test' => [$this, 'siteHealthFilesystem']];
        $tests['direct']['jooosi_social_image_cron'] = ['label' => __('Social Image background generation', 'jooosi-social-image'), 'test' => [$this, 'siteHealthCron']];
        return $tests;
    }
    public function siteHealthRenderer(): array
    {
        $capabilities = $this->diagnostics->status()['renderer'];
        $available = $capabilities['available'];
        $activeDriver = $capabilities['active_driver'];
        $diagnostic = (string) ($capabilities['diagnostics'][0] ?? '');
        return ['label' => $available ? __('Social Image can render dynamic images', 'jooosi-social-image') : __('Social Image cannot render dynamic images', 'jooosi-social-image'), 'status' => $available ? 'good' : 'critical', 'badge' => ['label' => __('Social Image', 'jooosi-social-image'), 'color' => 'blue'], 'description' => sprintf('<p>%s</p>', esc_html($available ? sprintf(
            /* translators: %s: Active Imagine image driver, such as Imagick or GD. */
            __('The PHP Imagine %s driver is active.', 'jooosi-social-image'),
            $activeDriver
        ) . ($diagnostic !== '' ? ' ' . $diagnostic : '') : __('Enable the PHP Imagick extension or PHP GD with FreeType support on this server.', 'jooosi-social-image'))), 'actions' => '', 'test' => 'jooosi_social_image_renderer'];
    }
    public function siteHealthSvg(): array
    {
        $capabilities = $this->diagnostics->status()['svg'];
        $available = $capabilities['available'];
        $limited = $available && $capabilities['limited'];
        return ['label' => $available ? $limited ? __('Social Image renders SVG with limited MSVG compatibility', 'jooosi-social-image') : __('Social Image can render Jooosi Icon SVG elements', 'jooosi-social-image') : __('Social Image SVG elements are unavailable', 'jooosi-social-image'), 'status' => $available && !$limited ? 'good' : 'recommended', 'badge' => ['label' => __('Social Image', 'jooosi-social-image'), 'color' => 'blue'], 'description' => sprintf('<p>%s</p>', esc_html($limited ? $capabilities['notice'] : ($available ? sprintf(
            /* translators: %s: Active SVG rasterization engine. */
            __('Jooosi Icon is active and SVG rasterization uses %s.', 'jooosi-social-image'),
            $capabilities['engine']
        ) : $capabilities['reason']))), 'actions' => '', 'test' => 'jooosi_social_image_svg'];
    }
    public function siteHealthFon(): array
    {
        $capabilities = $this->diagnostics->status()['webfont'];
        $available = $capabilities['available'];
        return ['label' => $available ? __('Social Image can access Jooosi Fon', 'jooosi-social-image') : __('Jooosi Fon integration is inactive', 'jooosi-social-image'), 'status' => $available && $capabilities['notice'] === '' ? 'good' : 'recommended', 'badge' => ['label' => __('Social Image', 'jooosi-social-image'), 'color' => 'blue'], 'description' => sprintf('<p>%s</p>', esc_html($available ? $capabilities['notice'] ?: __('Social Image can read Jooosi Fon\'s enabled font catalogue.', 'jooosi-social-image') : $capabilities['reason'])), 'actions' => '', 'test' => 'jooosi_social_image_jooosi_fon'];
    }
    public function siteHealthFonts(): array
    {
        $capabilities = $this->diagnostics->status()['fonts'];
        $available = $capabilities['available'];
        $description = $available ? $capabilities['notice'] ?: __('Social Image found readable local fallback fonts for server-rendered text.', 'jooosi-social-image') : $capabilities['reason'];
        return $this->healthResult($available ? __('Social Image can render text with local fonts', 'jooosi-social-image') : __('Social Image cannot find a local fallback font', 'jooosi-social-image'), $available ? $capabilities['notice'] === '' ? 'good' : 'recommended' : 'critical', $description, 'jooosi_social_image_fonts');
    }
    public function siteHealthFilesystem(): array
    {
        $filesystem = $this->diagnostics->status()['filesystem'];
        return $this->healthResult($filesystem['ready'] ? __('Social Image can write generated images', 'jooosi-social-image') : __('Social Image cannot write generated images', 'jooosi-social-image'), $filesystem['ready'] ? 'good' : 'critical', $filesystem['ready'] ? __('The Social Image uploads cache is available and writable.', 'jooosi-social-image') : $filesystem['reason'], 'jooosi_social_image_filesystem');
    }
    public function siteHealthCron(): array
    {
        $cron = $this->diagnostics->status()['cron'];
        return $this->healthResult($cron['ready'] ? __('Social Image can queue background generation', 'jooosi-social-image') : __('Social Image background generation needs attention', 'jooosi-social-image'), $cron['ready'] ? $cron['alternate'] ? 'recommended' : 'good' : 'critical', $cron['reason'] ?: __('WP-Cron accepts Social Image image-generation events.', 'jooosi-social-image'), 'jooosi_social_image_cron');
    }
    public function actionLinks(array $links): array
    {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">' . esc_html__('Open Social Image', 'jooosi-social-image') . '</a>');
        return $links;
    }
    /**
     * @return list<array{value: string, label: string}>
     */
    private function postTypes(): array
    {
        $result = [];
        foreach (get_post_types(['show_ui' => \true], 'objects') as $type) {
            if ($type->name === TemplateRepository::POST_TYPE || $type->name === 'attachment' || str_starts_with($type->name, 'wp_')) {
                continue;
            }
            $result[] = ['value' => $type->name, 'label' => $type->labels->singular_name];
        }
        return $result;
    }
    private function healthResult(string $label, string $status, string $description, string $test): array
    {
        return ['label' => $label, 'status' => $status, 'badge' => ['label' => __('Social Image', 'jooosi-social-image'), 'color' => 'blue'], 'description' => sprintf('<p>%s</p>', esc_html($description)), 'actions' => '', 'test' => $test];
    }
}
