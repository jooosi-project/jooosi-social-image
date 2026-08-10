<?php

declare(strict_types=1);

namespace JooosiEgami\Cli;

use JooosiEgami\Processing\TemplateInvalidator;
use JooosiEgami\Rendering\ImageGenerator;
use JooosiEgami\Template\TemplateRepository;
use WP_CLI;

/**
 * Registers the Egami WP-CLI surface.
 *
 * @since 0.1.0
 */
final class CommandRegistrar
{
    public static function register(
        TemplateRepository $templateRepository,
        ImageGenerator $imageGenerator,
        TemplateInvalidator $templateInvalidator,
    ): void {
        $render = new RenderCommand($imageGenerator);
        $cache = new CacheCommand($imageGenerator);
        $designs = new TemplateCommand($templateRepository, $templateInvalidator);
        $settings = new SettingsCommand();

        WP_CLI::add_command('egami render', [$render, 'run']);
        WP_CLI::add_command('egami cache warm', [$cache, 'warm']);
        WP_CLI::add_command('egami cache flush', [$cache, 'flush']);
        WP_CLI::add_command('egami design list', [$designs, 'list']);
        WP_CLI::add_command('egami design get', [$designs, 'get']);
        WP_CLI::add_command('egami design delete', [$designs, 'delete']);
        WP_CLI::add_command('egami settings get', [$settings, 'get']);
        WP_CLI::add_command('egami settings set', [$settings, 'set']);
    }
}
