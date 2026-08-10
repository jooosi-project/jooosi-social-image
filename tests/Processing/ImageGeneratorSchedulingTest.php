<?php

declare(strict_types=1);

namespace JooosiEgami\Tests\Processing;

use JooosiEgami\Assignment\TemplateMatcher;
use JooosiEgami\Content\DynamicDataResolver;
use JooosiEgami\Integration\YabeWebfont;
use JooosiEgami\Rendering\FontLocator;
use JooosiEgami\Rendering\ImageGenerator;
use JooosiEgami\Rendering\RemoteImageFetcher;
use JooosiEgami\Rendering\RendererFactory;
use JooosiEgami\Template\TemplateRepository;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ImageGeneratorSchedulingTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['egami_test_scheduled_events'] = [];
        $GLOBALS['egami_test_schedule_result'] = true;
        $GLOBALS['egami_test_options'] = [];
    }

    public function testSchedulingIsImmediateAndDeduplicated(): void
    {
        $repository = new TemplateRepository();
        $generator = new ImageGenerator(
            $repository,
            new TemplateMatcher($repository),
            new DynamicDataResolver(),
            RendererFactory::create(),
            new FontLocator(new YabeWebfont()),
            new RemoteImageFetcher(),
        );
        $startedAt = time();

        $generator->schedulePost(42);
        $generator->schedulePost(42);

        self::assertCount(1, $GLOBALS['egami_test_scheduled_events']);
        self::assertSame('jooosi-egami/rendering:render_post', $GLOBALS['egami_test_scheduled_events'][0]['hook']);
        self::assertSame([42, false], $GLOBALS['egami_test_scheduled_events'][0]['args']);
        self::assertGreaterThanOrEqual($startedAt, $GLOBALS['egami_test_scheduled_events'][0]['timestamp']);
        self::assertLessThanOrEqual(time(), $GLOBALS['egami_test_scheduled_events'][0]['timestamp']);
    }

    public function testExplicitDelayAndForceArePreserved(): void
    {
        $repository = new TemplateRepository();
        $generator = new ImageGenerator(
            $repository,
            new TemplateMatcher($repository),
            new DynamicDataResolver(),
            RendererFactory::create(),
            new FontLocator(new YabeWebfont()),
            new RemoteImageFetcher(),
        );
        $startedAt = time();

        $generator->schedulePost(7, true, 12);

        self::assertSame([7, true], $GLOBALS['egami_test_scheduled_events'][0]['args']);
        self::assertGreaterThanOrEqual($startedAt + 12, $GLOBALS['egami_test_scheduled_events'][0]['timestamp']);
    }

    public function testSchedulingFailureIsReturnedAndExposedToDiagnostics(): void
    {
        $repository = new TemplateRepository();
        $generator = new ImageGenerator(
            $repository,
            new TemplateMatcher($repository),
            new DynamicDataResolver(),
            RendererFactory::create(),
            new FontLocator(new YabeWebfont()),
            new RemoteImageFetcher(),
        );
        $GLOBALS['egami_test_schedule_result'] = new \WP_Error('schedule_event_false', 'Cron storage rejected the event.');

        $result = $generator->schedulePost(99, true);

        self::assertTrue(is_wp_error($result));
        self::assertSame([], $GLOBALS['egami_test_scheduled_events']);
        self::assertSame('schedule_event_false', $GLOBALS['egami_test_options'][ImageGenerator::OPTION_CRON_ERROR]['code']);
        self::assertSame('Cron storage rejected the event.', $GLOBALS['egami_test_options'][ImageGenerator::OPTION_CRON_ERROR]['message']);

        $GLOBALS['egami_test_schedule_result'] = true;
        self::assertTrue($generator->schedulePost(99, true));
        self::assertArrayNotHasKey(ImageGenerator::OPTION_CRON_ERROR, $GLOBALS['egami_test_options']);
    }

    public function testPairedSocialOutputsUseOneGenerationTarget(): void
    {
        $repository = new TemplateRepository();
        $generator = new ImageGenerator(
            $repository,
            new TemplateMatcher($repository),
            new DynamicDataResolver(),
            RendererFactory::create(),
            new FontLocator(new YabeWebfont()),
            new RemoteImageFetcher(),
        );
        $method = new ReflectionMethod($generator, 'generationPlan');
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
