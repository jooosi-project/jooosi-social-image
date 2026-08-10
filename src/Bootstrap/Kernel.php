<?php

declare(strict_types=1);

namespace JooosiEgami\Bootstrap;

use JooosiEgami\Admin\Controller\EgamiController;
use JooosiEgami\Admin\Menu\AdminMenu;
use JooosiEgami\Assignment\TemplateMatcher;
use JooosiEgami\Cli\CommandRegistrar;
use JooosiEgami\Content\DynamicDataResolver;
use JooosiEgami\Diagnostics\SystemDiagnostics;
use JooosiEgami\Integration\SocialImageIntegration;
use JooosiEgami\Integration\OmniIcon;
use JooosiEgami\Integration\YabeWebfont;
use JooosiEgami\Processing\GenerationLifecycle;
use JooosiEgami\Processing\TemplateInvalidator;
use JooosiEgami\Preset\PresetRepositoryManager;
use JooosiEgami\Rendering\FontLocator;
use JooosiEgami\Rendering\ImageGenerator;
use JooosiEgami\Rendering\RendererFactory;
use JooosiEgami\Rendering\RendererInterface;
use JooosiEgami\Rendering\RemoteImageFetcher;
use JooosiEgami\Rendering\SvgRasterizer;
use JooosiEgami\Rendering\SvgSupport;
use JooosiEgami\Template\TemplateRepository;

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

    private OmniIcon $icons;

    private SvgSupport $svgSupport;

    private YabeWebfont $webfonts;

    private PresetRepositoryManager $presetRepositories;

    private DynamicDataResolver $dynamicData;

    private TemplateMatcher $templateMatcher;

    private SystemDiagnostics $diagnostics;

    public function __construct(private Paths $paths)
    {
        $this->templateRepository = new TemplateRepository();
        $this->icons = new OmniIcon();
        $this->webfonts = new YabeWebfont();
        $this->presetRepositories = new PresetRepositoryManager(
            $this->paths->rootDir . '/presets/repository.json',
            plugin_dir_url($this->paths->pluginFile) . 'schemas/',
        );
        $svgRasterizer = new SvgRasterizer();
        $this->svgSupport = new SvgSupport($this->icons, $svgRasterizer);
        $this->renderer = RendererFactory::create($svgRasterizer);
        $fontLocator = new FontLocator($this->webfonts);
        $this->diagnostics = new SystemDiagnostics($this->renderer, $this->svgSupport, $this->webfonts, $fontLocator);
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

        $controller = new EgamiController(
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

        (new AdminMenu($this->paths, $this->diagnostics, $this->webfonts))->registerHooks();

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
