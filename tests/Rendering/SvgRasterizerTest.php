<?php

declare(strict_types=1);

namespace JooosiSocialImage\Tests\Rendering;

use JooosiSocialImage\Rendering\SvgRasterizer;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class SvgRasterizerTest extends TestCase
{
    public function testCapabilitiesRequireImagickAndACompleteSvgEngine(): void
    {
        $rasterizer = new SvgRasterizer();
        $capabilities = $rasterizer->capabilities();

        self::assertSame(extension_loaded('imagick') && class_exists(\Imagick::class), $capabilities['imagick']);
        self::assertContains($capabilities['engine'], ['librsvg', 'msvg', 'none']);
        self::assertSame(
            $capabilities['imagick'] && ($capabilities['librsvg'] || $capabilities['svg_format']),
            $capabilities['available'],
        );
        if ($capabilities['available']) {
            self::assertSame('', $capabilities['reason']);
        } else {
            self::assertNotSame('', $capabilities['reason']);
        }
    }

    public function testRasterizationReturnsPngWhenTheHostHasAQualifyingEngine(): void
    {
        $rasterizer = new SvgRasterizer();
        $result = $rasterizer->rasterize(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" color="#14b8a6"><path fill="currentColor" d="M2 2h20v20H2z"/></svg>',
            96,
            96,
        );

        if (! $rasterizer->available()) {
            self::assertInstanceOf(WP_Error::class, $result);
            self::assertSame('social_image_svg_renderer_unavailable', $result->get_error_code());

            return;
        }

        self::assertIsString($result);
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $result);
        $image = new \Imagick();
        $image->readImageBlob($result);
        self::assertSame(96, $image->getImageWidth());
        self::assertSame(96, $image->getImageHeight());
        $image->clear();
    }

    public function testMsvgDoesNotSilentlyAcceptACompletelyTransparentResult(): void
    {
        $rasterizer = new SvgRasterizer();

        if ($rasterizer->capabilities()['engine'] !== 'msvg') {
            self::markTestSkipped('This diagnostic applies only to the MSVG fallback.');
        }

        $result = $rasterizer->rasterize(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"></svg>',
            96,
            96,
        );

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('social_image_svg_render_failed', $result->get_error_code());
        self::assertStringContainsString('MSVG produced an empty image', $result->get_error_message());
    }
}
