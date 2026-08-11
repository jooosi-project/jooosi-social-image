<?php

declare (strict_types=1);
namespace JooosiEgami\Bootstrap;

use JooosiEgami\Settings\PluginSettings;
use JooosiEgami\Template\TemplateRepository;
/**
 * Activation and deactivation lifecycle.
 *
 * @since 0.1.0
 */
final class LifecycleManager
{
    public function __construct(private TemplateRepository $templateRepository)
    {
    }
    public function activate(): void
    {
        // Activation runs before init, so avoid triggering just-in-time
        // translation loading while registering the private storage type.
        $this->templateRepository->registerPostType(\false);
        if (\false === get_option(PluginSettings::OPTION, \false)) {
            add_option(PluginSettings::OPTION, PluginSettings::defaults(), '', \false);
        }
        $existing = get_posts(['post_type' => TemplateRepository::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids']);
        if ($existing === []) {
            $this->templateRepository->create('Editorial gradient');
        }
    }
    public function deactivate(): void
    {
        wp_clear_scheduled_hook('jooosi-egami/rendering:render_post');
    }
}
