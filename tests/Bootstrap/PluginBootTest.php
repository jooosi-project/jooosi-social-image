<?php

declare(strict_types=1);

namespace JooosiEgami\Tests\Bootstrap;

use JooosiEgami\Api\Image;
use JooosiEgami\Bootstrap\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class PluginBootTest extends TestCase
{
    public function testComposerEntrypointRegistersACompleteCallableHookGraph(): void
    {
        require_once dirname(__DIR__, 2) . '/jooosi-egami.php';

        self::assertInstanceOf(Plugin::class, Plugin::instance());
        self::assertSame('jooosi-egami/jooosi-egami.php', JOOOSI_EGAMI_PLUGIN_BASENAME);
        self::assertFalse(function_exists('egami_get_image_url'));
        self::assertFalse(function_exists('egami_get_image'));
        self::assertFalse(function_exists('egami_render_image'));

        foreach (['url', 'html', 'render'] as $method) {
            self::assertTrue((new ReflectionMethod(Image::class, $method))->isStatic());
        }

        self::assertCount(1, $GLOBALS['egami_test_hooks']['activation']);
        self::assertCount(1, $GLOBALS['egami_test_hooks']['deactivation']);

        $actions = array_column($GLOBALS['egami_test_hooks']['actions'], 'hookName');
        $filters = array_column($GLOBALS['egami_test_hooks']['filters'], 'hookName');
        $shortcodes = array_column($GLOBALS['egami_test_hooks']['shortcodes'], 'tag');

        self::assertContains('init', $actions);
        self::assertContains('rest_api_init', $actions);
        self::assertContains('save_post', $actions);
        self::assertContains('template_redirect', $actions);
        self::assertContains('jooosi-egami/rendering:render_post', $actions);
        self::assertContains('wpseo_opengraph_image', $filters);
        self::assertContains('rank_math/opengraph/facebook/image', $filters);
        self::assertContains('aioseo_facebook_tags', $filters);
        self::assertContains('seopress_social_og_thumb', $filters);
        self::assertContains('egami', $shortcodes);

        foreach (['actions', 'filters', 'shortcodes', 'activation', 'deactivation'] as $kind) {
            foreach ($GLOBALS['egami_test_hooks'][$kind] as $registered) {
                self::assertIsCallable($registered['callback']);
            }
        }
    }
}
