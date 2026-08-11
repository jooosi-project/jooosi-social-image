<?php

declare (strict_types=1);
namespace JooosiEgami\Admin\Menu;

use JooosiEgami\Admin\Controller\EgamiController;
use JooosiEgami\Bootstrap\Paths;
use JooosiEgami\Diagnostics\SystemDiagnostics;
use JooosiEgami\Integration\YabeWebfont;
use JooosiEgami\Template\TemplateRepository;
use JooosiEgamiDeps\Nabasa\VitePlus\Assets;
/**
 * Registers the WordPress admin app shell and diagnostics.
 *
 * @since 0.1.0
 */
final class AdminMenu
{
    private const PAGE_SLUG = 'jooosi-egami';
    private const ASSET_HANDLE = 'jooosi-egami-admin';
    private string $pageHook = '';
    public function __construct(private Paths $paths, private SystemDiagnostics $diagnostics, private YabeWebfont $webfonts)
    {
    }
    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_notices', [$this, 'rendererNotice']);
        add_filter('site_status_tests', [$this, 'siteHealth']);
        add_filter('plugin_action_links_' . \JOOOSI_EGAMI_PLUGIN_BASENAME, [$this, 'actionLinks']);
    }
    public function registerMenu(): void
    {
        $this->pageHook = add_menu_page(
            __('Jooosi Egami', 'jooosi-egami'),
            __('Jooosi Egami', 'jooosi-egami'),
            'edit_posts',
            self::PAGE_SLUG,
            [$this, 'render'],
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin asset.
            'data:image/svg+xml;base64,' . base64_encode(file_get_contents($this->paths->rootDir . '/jooosi-egami.svg')),
            100
        );
    }
    public function enqueueAssets(string $hookSuffix): void
    {
        if ($hookSuffix !== $this->pageHook) {
            return;
        }
        wp_enqueue_media();
        $webfontStylesheet = $this->webfonts->stylesheetUrl();
        if ('' !== $webfontStylesheet) {
            wp_enqueue_style('jooosi-egami-yabe-webfonts', $webfontStylesheet, [], \JOOOSI_EGAMI_VERSION);
        }
        (new Assets($this->paths->rootDir . '/assets/dist'))->enqueue('resources/App.tsx', ['handle' => self::ASSET_HANDLE, 'in_footer' => \true]);
        wp_add_inline_script(self::ASSET_HANDLE, 'window.EgamiConfig = ' . wp_json_encode(['restUrl' => esc_url_raw(rest_url(EgamiController::NAMESPACE)), 'nonce' => wp_create_nonce('wp_rest'), 'adminUrl' => admin_url('admin.php?page=' . self::PAGE_SLUG), 'pluginUrl' => \JOOOSI_EGAMI_PLUGIN_URL, 'version' => \JOOOSI_EGAMI_VERSION, 'postTypes' => $this->postTypes(), 'canManage' => current_user_can('manage_options')]) . ';', 'before');
    }
    public function render(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You are not allowed to access Egami.', 'jooosi-egami'));
        }
        echo '<div id="egami-admin"><div class="egami-loading"><span class="spinner is-active"></span><p>' . esc_html__('Loading Egami…', 'jooosi-egami') . '</p></div></div>';
    }
    public function rendererNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($page !== self::PAGE_SLUG) {
            return;
        }
        $status = $this->diagnostics->status();
        if ($status['renderer']['available']) {
            return;
        }
        echo '<div class="notice notice-error"><p><strong>' . esc_html__('Egami cannot render images:', 'jooosi-egami') . '</strong> ' . esc_html__('Enable the PHP Imagick extension or PHP GD with FreeType support, then reload this page.', 'jooosi-egami') . '</p></div>';
    }
    public function siteHealth(array $tests): array
    {
        $tests['direct']['jooosi_egami_renderer'] = ['label' => __('Egami image renderer', 'jooosi-egami'), 'test' => [$this, 'siteHealthRenderer']];
        $tests['direct']['jooosi_egami_svg'] = ['label' => __('Egami SVG renderer', 'jooosi-egami'), 'test' => [$this, 'siteHealthSvg']];
        $tests['direct']['jooosi_egami_webfont'] = ['label' => __('Egami Yabe Webfont integration', 'jooosi-egami'), 'test' => [$this, 'siteHealthWebfont']];
        $tests['direct']['jooosi_egami_fonts'] = ['label' => __('Egami server fonts', 'jooosi-egami'), 'test' => [$this, 'siteHealthFonts']];
        $tests['direct']['jooosi_egami_filesystem'] = ['label' => __('Egami generated-image storage', 'jooosi-egami'), 'test' => [$this, 'siteHealthFilesystem']];
        $tests['direct']['jooosi_egami_cron'] = ['label' => __('Egami background generation', 'jooosi-egami'), 'test' => [$this, 'siteHealthCron']];
        return $tests;
    }
    public function siteHealthRenderer(): array
    {
        $capabilities = $this->diagnostics->status()['renderer'];
        $available = $capabilities['available'];
        $activeDriver = $capabilities['active_driver'];
        $diagnostic = (string) ($capabilities['diagnostics'][0] ?? '');
        return ['label' => $available ? __('Egami can render dynamic images', 'jooosi-egami') : __('Egami cannot render dynamic images', 'jooosi-egami'), 'status' => $available ? 'good' : 'critical', 'badge' => ['label' => __('Egami', 'jooosi-egami'), 'color' => 'blue'], 'description' => sprintf('<p>%s</p>', esc_html($available ? sprintf(__('The PHP Imagine %s driver is active.', 'jooosi-egami'), $activeDriver) . ($diagnostic !== '' ? ' ' . $diagnostic : '') : __('Enable the PHP Imagick extension or PHP GD with FreeType support on this server.', 'jooosi-egami'))), 'actions' => '', 'test' => 'jooosi_egami_renderer'];
    }
    public function siteHealthSvg(): array
    {
        $capabilities = $this->diagnostics->status()['svg'];
        $available = $capabilities['available'];
        $limited = $available && $capabilities['limited'];
        return ['label' => $available ? $limited ? __('Egami renders SVG with limited MSVG compatibility', 'jooosi-egami') : __('Egami can render Omni Icon SVG elements', 'jooosi-egami') : __('Egami SVG elements are unavailable', 'jooosi-egami'), 'status' => $available && !$limited ? 'good' : 'recommended', 'badge' => ['label' => __('Egami', 'jooosi-egami'), 'color' => 'blue'], 'description' => sprintf('<p>%s</p>', esc_html($limited ? $capabilities['notice'] : ($available ? sprintf(__('Omni Icon is active and SVG rasterization uses %s.', 'jooosi-egami'), $capabilities['engine']) : $capabilities['reason']))), 'actions' => '', 'test' => 'jooosi_egami_svg'];
    }
    public function siteHealthWebfont(): array
    {
        $capabilities = $this->diagnostics->status()['webfont'];
        $available = $capabilities['available'];
        return ['label' => $available ? __('Egami can access Yabe Webfont', 'jooosi-egami') : __('Yabe Webfont integration is inactive', 'jooosi-egami'), 'status' => $available && $capabilities['notice'] === '' ? 'good' : 'recommended', 'badge' => ['label' => __('Egami', 'jooosi-egami'), 'color' => 'blue'], 'description' => sprintf('<p>%s</p>', esc_html($available ? $capabilities['notice'] ?: __('Egami can read Yabe Webfont\'s enabled font catalogue.', 'jooosi-egami') : $capabilities['reason'])), 'actions' => '', 'test' => 'jooosi_egami_webfont'];
    }
    public function siteHealthFonts(): array
    {
        $capabilities = $this->diagnostics->status()['fonts'];
        $available = $capabilities['available'];
        $description = $available ? $capabilities['notice'] ?: __('Egami found readable local fallback fonts for server-rendered text.', 'jooosi-egami') : $capabilities['reason'];
        return $this->healthResult($available ? __('Egami can render text with local fonts', 'jooosi-egami') : __('Egami cannot find a local fallback font', 'jooosi-egami'), $available ? $capabilities['notice'] === '' ? 'good' : 'recommended' : 'critical', $description, 'jooosi_egami_fonts');
    }
    public function siteHealthFilesystem(): array
    {
        $filesystem = $this->diagnostics->status()['filesystem'];
        return $this->healthResult($filesystem['ready'] ? __('Egami can write generated images', 'jooosi-egami') : __('Egami cannot write generated images', 'jooosi-egami'), $filesystem['ready'] ? 'good' : 'critical', $filesystem['ready'] ? __('The Egami uploads cache is available and writable.', 'jooosi-egami') : $filesystem['reason'], 'jooosi_egami_filesystem');
    }
    public function siteHealthCron(): array
    {
        $cron = $this->diagnostics->status()['cron'];
        return $this->healthResult($cron['ready'] ? __('Egami can queue background generation', 'jooosi-egami') : __('Egami background generation needs attention', 'jooosi-egami'), $cron['ready'] ? $cron['alternate'] ? 'recommended' : 'good' : 'critical', $cron['reason'] ?: __('WP-Cron accepts Egami image-generation events.', 'jooosi-egami'), 'jooosi_egami_cron');
    }
    public function actionLinks(array $links): array
    {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">' . esc_html__('Open Egami', 'jooosi-egami') . '</a>');
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
        return ['label' => $label, 'status' => $status, 'badge' => ['label' => __('Egami', 'jooosi-egami'), 'color' => 'blue'], 'description' => sprintf('<p>%s</p>', esc_html($description)), 'actions' => '', 'test' => $test];
    }
}
