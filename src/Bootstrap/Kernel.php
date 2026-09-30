<?php

declare(strict_types=1);

namespace JooosiSocialImage\Bootstrap;

use JooosiSocialImage\Admin\Controller\SocialImageController;
use JooosiSocialImage\Admin\Menu\AdminMenu;
use JooosiSocialImage\Assignment\TemplateMatcher;
use JooosiSocialImage\Cli\CommandRegistrar;
use JooosiSocialImage\Content\DynamicDataResolver;
use JooosiSocialImage\Diagnostics\SystemDiagnostics;
use JooosiSocialImage\Integration\SocialImageIntegration;
use JooosiSocialImage\Integration\JooosiIcon;
use JooosiSocialImage\Integration\JooosiFon;
use JooosiSocialImage\Processing\GenerationLifecycle;
use JooosiSocialImage\Processing\TemplateInvalidator;
use JooosiSocialImage\Preset\PresetRepositoryManager;
use JooosiSocialImage\Rendering\FontLocator;
use JooosiSocialImage\Rendering\ImageGenerator;
use JooosiSocialImage\Rendering\RendererFactory;
use JooosiSocialImage\Rendering\RendererInterface;
use JooosiSocialImage\Rendering\RemoteImageFetcher;
use JooosiSocialImage\Rendering\SvgRasterizer;
use JooosiSocialImage\Rendering\SvgSupport;
use JooosiSocialImage\Template\TemplateRepository;

/**
 * Composes services and boots their WordPress adapters.
 *
 * @since 0.1.0
 */
final class Kernel
{
    private bool $booted = false;

    private TemplateRepository $templateRepository;

    private ImageGenerator $imageGenerator;

    private LifecycleManager $lifecycleManager;

    private GenerationLifecycle $generationLifecycle;

    private RendererInterface $renderer;

    private TemplateInvalidator $templateInvalidator;

    private JooosiIcon $icons;

    private SvgSupport $svgSupport;

    private JooosiFon $fon;

    private PresetRepositoryManager $presetRepositories;

    private DynamicDataResolver $dynamicData;

    private TemplateMatcher $templateMatcher;

    private SystemDiagnostics $diagnostics;

    public function __construct(private Paths $paths)
    {
        $this->templateRepository = new TemplateRepository();
        $this->icons = new JooosiIcon();
        $this->fon = new JooosiFon();
        $this->presetRepositories = new PresetRepositoryManager(
            $this->paths->rootDir . '/presets/repository.json',
            plugin_dir_url($this->paths->pluginFile) . 'schemas/',
        );
        $svgRasterizer = new SvgRasterizer();
        $this->svgSupport = new SvgSupport($this->icons, $svgRasterizer);
        $this->renderer = RendererFactory::create($svgRasterizer);
        $fontLocator = new FontLocator($this->fon);
        $this->diagnostics = new SystemDiagnostics($this->renderer, $this->svgSupport, $this->fon, $fontLocator);
        $this->templateMatcher = new TemplateMatcher($this->templateRepository);
        $this->dynamicData = new DynamicDataResolver();
        $this->imageGenerator = new ImageGenerator($this->templateRepository, $this->templateMatcher, $this->dynamicData, $this->renderer, $fontLocator, new RemoteImageFetcher(), $this->icons);
        $this->templateInvalidator = new TemplateInvalidator($this->templateMatcher, $this->imageGenerator);
        $this->lifecycleManager = new LifecycleManager($this->templateRepository);
        $this->generationLifecycle = new GenerationLifecycle($this->templateMatcher, $this->imageGenerator);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;
        add_action('init', [$this->templateRepository, 'registerPostType']);
        $this->imageGenerator->registerHooks();
        $this->generationLifecycle->registerHooks();
        (new SocialImageIntegration())->registerHooks();

        $controller = new SocialImageController(
            $this->templateRepository,
            $this->imageGenerator,
            $this->templateInvalidator,
            $this->presetRepositories,
            $this->icons,
            $this->svgSupport,
            $this->diagnostics,
            $this->dynamicData,
            $this->templateMatcher,
        );
        add_action('rest_api_init', [$controller, 'registerRoutes']);

        (new AdminMenu($this->paths, $this->diagnostics, $this->fon))->registerHooks();

        if (defined('WP_CLI') && WP_CLI) {
            add_action(
                'cli_init',
                fn () => CommandRegistrar::register(
                    $this->templateRepository,
                    $this->imageGenerator,
                    $this->templateInvalidator,
                ),
            );
        }
    }

    public function activate(): void
    {
        $this->lifecycleManager->activate();
    }

    public function deactivate(): void
    {
        $this->lifecycleManager->deactivate();
    }

    public function imageGenerator(): ImageGenerator
    {
        return $this->imageGenerator;
    }
}
