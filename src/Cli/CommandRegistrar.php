<?php

declare (strict_types=1);
namespace JooosiSocialImage\Cli;

use JooosiSocialImage\Processing\TemplateInvalidator;
use JooosiSocialImage\Rendering\ImageGenerator;
use JooosiSocialImage\Template\TemplateRepository;
use WP_CLI;
/**
 * Registers the Social Image WP-CLI surface.
 *
 * @since 0.1.0
 */
final class CommandRegistrar
{
    public static function register(TemplateRepository $templateRepository, ImageGenerator $imageGenerator, TemplateInvalidator $templateInvalidator): void
    {
        $render = new \JooosiSocialImage\Cli\RenderCommand($imageGenerator);
        $cache = new \JooosiSocialImage\Cli\CacheCommand($imageGenerator);
        $designs = new \JooosiSocialImage\Cli\TemplateCommand($templateRepository, $templateInvalidator);
        $settings = new \JooosiSocialImage\Cli\SettingsCommand();
        WP_CLI::add_command('social-image render', [$render, 'run']);
        WP_CLI::add_command('social-image cache warm', [$cache, 'warm']);
        WP_CLI::add_command('social-image cache flush', [$cache, 'flush']);
        WP_CLI::add_command('social-image design list', [$designs, 'list']);
        WP_CLI::add_command('social-image design get', [$designs, 'get']);
        WP_CLI::add_command('social-image design delete', [$designs, 'delete']);
        WP_CLI::add_command('social-image settings get', [$settings, 'get']);
        WP_CLI::add_command('social-image settings set', [$settings, 'set']);
    }
}
