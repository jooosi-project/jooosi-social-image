<?php

declare(strict_types=1);

namespace JooosiSocialImage\Tests\Processing;

use JooosiSocialImage\Assignment\TemplateMatcher;
use JooosiSocialImage\Content\DynamicDataResolver;
use JooosiSocialImage\Integration\JooosiFon;
use JooosiSocialImage\Rendering\FontLocator;
use JooosiSocialImage\Rendering\ImageGenerator;
use JooosiSocialImage\Rendering\RemoteImageFetcher;
use JooosiSocialImage\Rendering\RendererFactory;
use JooosiSocialImage\Template\TemplateRepository;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ImageGeneratorSchedulingTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['social_image_test_scheduled_events'] = [];
        $GLOBALS['social_image_test_schedule_result'] = true;
        $GLOBALS['social_image_test_options'] = [];
    }

    public function testSchedulingIsImmediateAndDeduplicated(): void
    {
        $repository = new TemplateRepository();
        $generator = new ImageGenerator(
            $repository,
            new TemplateMatcher($repository),
            new DynamicDataResolver(),
            RendererFactory::create(),
            new FontLocator(new JooosiFon()),
            new RemoteImageFetcher(),
        );
        $startedAt = time();

        $generator->schedulePost(42);
        $generator->schedulePost(42);

        self::assertCount(1, $GLOBALS['social_image_test_scheduled_events']);
        self::assertSame('jooosi-social-image/rendering:render_post', $GLOBALS['social_image_test_scheduled_events'][0]['hook']);
        self::assertSame([42, false], $GLOBALS['social_image_test_scheduled_events'][0]['args']);
        self::assertGreaterThanOrEqual($startedAt, $GLOBALS['social_image_test_scheduled_events'][0]['timestamp']);
        self::assertLessThanOrEqual(time(), $GLOBALS['social_image_test_scheduled_events'][0]['timestamp']);
    }

    public function testExplicitDelayAndForceArePreserved(): void
    {
        $repository = new TemplateRepository();
        $generator = new ImageGenerator(
            $repository,
            new TemplateMatcher($repository),
            new DynamicDataResolver(),
            RendererFactory::create(),
            new FontLocator(new JooosiFon()),
            new RemoteImageFetcher(),
        );
        $startedAt = time();

        $generator->schedulePost(7, true, 12);

        self::assertSame([7, true], $GLOBALS['social_image_test_scheduled_events'][0]['args']);
        self::assertGreaterThanOrEqual($startedAt + 12, $GLOBALS['social_image_test_scheduled_events'][0]['timestamp']);
    }

    public function testSchedulingFailureIsReturnedAndExposedToDiagnostics(): void
    {
        $repository = new TemplateRepository();
        $generator = new ImageGenerator(
            $repository,
            new TemplateMatcher($repository),
            new DynamicDataResolver(),
            RendererFactory::create(),
            new FontLocator(new JooosiFon()),
            new RemoteImageFetcher(),
        );
        $GLOBALS['social_image_test_schedule_result'] = new \WP_Error('schedule_event_false', 'Cron storage rejected the event.');

        $result = $generator->schedulePost(99, true);

        self::assertTrue(is_wp_error($result));
        self::assertSame([], $GLOBALS['social_image_test_scheduled_events']);
        self::assertSame('schedule_event_false', $GLOBALS['social_image_test_options'][ImageGenerator::OPTION_CRON_ERROR]['code']);
        self::assertSame('Cron storage rejected the event.', $GLOBALS['social_image_test_options'][ImageGenerator::OPTION_CRON_ERROR]['message']);

        $GLOBALS['social_image_test_schedule_result'] = true;
        self::assertTrue($generator->schedulePost(99, true));
        self::assertArrayNotHasKey(ImageGenerator::OPTION_CRON_ERROR, $GLOBALS['social_image_test_options']);
    }

    public function testPairedSocialOutputsUseOneGenerationTarget(): void
    {
        $repository = new TemplateRepository();
        $generator = new ImageGenerator(
            $repository,
            new TemplateMatcher($repository),
            new DynamicDataResolver(),
            RendererFactory::create(),
            new FontLocator(new JooosiFon()),
            new RemoteImageFetcher(),
        );
        $method = new ReflectionMethod($generator, 'generationPlan');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        $social = ['id' => 12, 'title' => 'Social'];
        $featured = ['id' => 18, 'title' => 'Featured'];

        $plan = $method->invoke($generator, [
            'og' => $social,
            'twitter' => $social,
            'featured' => $featured,
        ]);

        self::assertCount(2, $plan);
        self::assertSame('og', $plan[0]['output']);
        self::assertSame(['twitter'], array_keys($plan[0]['aliases']));
        self::assertSame('featured', $plan[1]['output']);
        self::assertSame([], $plan[1]['aliases']);
    }
}
