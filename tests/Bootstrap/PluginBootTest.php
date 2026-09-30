<?php

declare(strict_types=1);

namespace JooosiSocialImage\Tests\Bootstrap;

use JooosiSocialImage\Api\Image;
use JooosiSocialImage\Bootstrap\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class PluginBootTest extends TestCase
{
    public function testComposerEntrypointRegistersACompleteCallableHookGraph(): void
    {
        require_once dirname(__DIR__, 2) . '/jooosi-social-image.php';

        self::assertInstanceOf(Plugin::class, Plugin::instance());
        self::assertSame(basename(dirname(__DIR__, 2)) . '/jooosi-social-image.php', JOOOSI_SOCIAL_IMAGE_PLUGIN_BASENAME);
        self::assertFalse(function_exists('social_image_get_image_url'));
        self::assertFalse(function_exists('social_image_get_image'));
        self::assertFalse(function_exists('social_image_render_image'));

        foreach (['url', 'html', 'render'] as $method) {
            self::assertTrue((new ReflectionMethod(Image::class, $method))->isStatic());
        }

        self::assertCount(1, $GLOBALS['social_image_test_hooks']['activation']);
        self::assertCount(1, $GLOBALS['social_image_test_hooks']['deactivation']);

        $actions = array_column($GLOBALS['social_image_test_hooks']['actions'], 'hookName');
        $filters = array_column($GLOBALS['social_image_test_hooks']['filters'], 'hookName');
        $shortcodes = array_column($GLOBALS['social_image_test_hooks']['shortcodes'], 'tag');

        self::assertContains('init', $actions);
        self::assertContains('rest_api_init', $actions);
        self::assertContains('save_post', $actions);
        self::assertContains('template_redirect', $actions);
        self::assertContains('jooosi-social-image/rendering:render_post', $actions);
        self::assertContains('wpseo_opengraph_image', $filters);
        self::assertContains('rank_math/opengraph/facebook/image', $filters);
        self::assertContains('aioseo_facebook_tags', $filters);
        self::assertContains('seopress_social_og_thumb', $filters);
        self::assertContains('social-image', $shortcodes);

        foreach (['actions', 'filters', 'shortcodes', 'activation', 'deactivation'] as $kind) {
            foreach ($GLOBALS['social_image_test_hooks'][$kind] as $registered) {
                self::assertIsCallable($registered['callback']);
            }
        }
    }
}
