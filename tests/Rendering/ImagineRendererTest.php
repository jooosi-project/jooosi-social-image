<?php

declare(strict_types=1);

namespace JooosiSocialImage\Tests\Rendering;

use GdImage;
use JooosiSocialImage\Rendering\ImagineRenderer;
use JooosiSocialImage\Rendering\RendererFactory;
use JooosiSocialImage\Rendering\RendererInterface;
use JooosiSocialImage\Rendering\SvgRasterizer;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class ImagineRendererTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function testFactorySelectsACompatibleImagineDriver(): void
    {
        if (! extension_loaded('imagick') && ! extension_loaded('gd')) {
            self::markTestSkipped('Neither Imagick nor GD is installed.');
        }

        $renderer = RendererFactory::create();
        $imagick = ImagineRenderer::create('imagick');
        $expectedDriver = $imagick->available() ? 'imagick' : 'gd';

        self::assertInstanceOf(RendererInterface::class, $renderer);
        self::assertTrue($renderer->available());
        self::assertSame($expectedDriver, $renderer->driver());
        self::assertStringContainsString('imagine-renderer-8', $renderer->identity());
        self::assertSame('imagine', $renderer->capabilities()['library']);
        self::assertContains('png', $renderer->capabilities()['formats']);
    }

    /**
     * @dataProvider compatibleDriverProvider
     */
    public function testDriverRendersLayeredDocumentThroughImagine(string $driver): void
    {
        if (! extension_loaded($driver)) {
            self::markTestSkipped(sprintf('The %s extension is not installed.', $driver));
        }

        if (! extension_loaded('gd')) {
            self::markTestSkipped('GD is required to create the local image fixture.');
        }

        if ($driver === 'gd' && ! function_exists('imageftbbox')) {
            self::markTestSkipped('GD with FreeType is not installed.');
        }

        $font = $this->availableFont();

        if ($font === '') {
            self::markTestSkipped('No readable test font is installed.');
        }

        $renderer = ImagineRenderer::create($driver);
        $destination = $this->temporaryPath('png');
        $source = $this->sourceImage();
        $result = $renderer->render([
            'width' => 640,
            'height' => 336,
            'background' => [
                'type' => 'gradient',
                'color' => '#101828',
                'stops' => [
                    ['color' => '#101828', 'position' => 0],
                    ['color' => '#4f46e5', 'position' => 100],
                ],
                'direction' => 'horizontal',
            ],
            'elements' => [
                [
                    'id' => 'card',
                    'type' => 'shape',
                    'shape' => 'rectangle',
                    'x' => -8,
                    'y' => 54,
                    'width' => 380,
                    'height' => 230,
                    'opacity' => 0.82,
                    'rotation' => -2.0,
                    'hidden' => false,
                    'background' => ['type' => 'solid', 'color' => 'rgba(255, 255, 255, 0.18)'],
                    'strokeColor' => '#a5b4fc',
                    'strokeWidth' => 3.0,
                    'radius' => 28.0,
                ],
                [
                    'id' => 'photo',
                    'type' => 'image',
                    'x' => 420,
                    'y' => 70,
                    'width' => 160,
                    'height' => 196,
                    'opacity' => 0.9,
                    'rotation' => 3.0,
                    'hidden' => false,
                    '_path' => $source,
                    'fit' => 'cover',
                    'radius' => 24.0,
                ],
                [
                    'id' => 'title',
                    'type' => 'text',
                    'x' => 45,
                    'y' => 92,
                    'width' => 310,
                    'height' => 160,
                    'opacity' => 1.0,
                    'rotation' => 0.0,
                    'hidden' => false,
                    'content' => '{{post.title}}',
                    '_text' => 'Imagine renders dynamic social images',
                    '_font_path' => $font,
                    'fontSize' => 48.0,
                    'minFontSize' => 18.0,
                    'fontWeight' => 700,
                    'color' => '#ffffff',
                    'align' => 'left',
                    'verticalAlign' => 'middle',
                    'lineHeight' => 1.08,
                ],
            ],
        ], $destination, 'png', 90);

        self::assertNotInstanceOf(WP_Error::class, $result);
        self::assertSame($driver, $renderer->driver());
        self::assertSame([], $renderer->warnings());
        self::assertFileExists($destination);
        self::assertGreaterThan(5_000, filesize($destination));
        self::assertSame([640, 336], array_slice((array) getimagesize($destination), 0, 2));
        self::assertSame('image/png', mime_content_type($destination));
    }

    public function testGdSupersamplesShapeEdges(): void
    {
        if (! extension_loaded('gd')) {
            self::markTestSkipped('GD is not installed.');
        }

        $destination = $this->renderDocument('gd', [
            'width' => 80,
            'height' => 80,
            'background' => ['type' => 'solid', 'color' => '#ffffff'],
            'elements' => [[
                'id' => 'ellipse',
                'type' => 'shape',
                'shape' => 'ellipse',
                'x' => 10,
                'y' => 10,
                'width' => 61,
                'height' => 61,
                'opacity' => 1,
                'rotation' => 0,
                'hidden' => false,
                'background' => ['type' => 'solid', 'color' => '#000000'],
                'strokeColor' => '#000000',
                'strokeWidth' => 0,
            ]],
        ]);
        $image = imagecreatefrompng($destination);
        self::assertInstanceOf(GdImage::class, $image);
        $transitionPixels = 0;

        for ($y = 0; $y < 80; $y++) {
            for ($x = 0; $x < 80; $x++) {
                $color = imagecolorat($image, $x, $y);
                $components = imagecolorsforindex($image, $color);
                $red = $components['red'];

                if ($red > 0 && $red < 255) {
                    $transitionPixels++;
                }
            }
        }

        unset($image);
        self::assertGreaterThan(80, $transitionPixels);
    }

    public function testGdPreservesOpacityAfterRotatingALayer(): void
    {
        if (! extension_loaded('gd') || ! extension_loaded('imagick')) {
            self::markTestSkipped('GD and Imagick are required for the parity check.');
        }

        $document = [
            'width' => 80,
            'height' => 80,
            'background' => ['type' => 'solid', 'color' => '#7b3bec'],
            'elements' => [[
                'id' => 'translucent-card',
                'type' => 'shape',
                'shape' => 'rectangle',
                'x' => 10,
                'y' => 10,
                'width' => 60,
                'height' => 60,
                'opacity' => 0.82,
                'rotation' => -2.7,
                'hidden' => false,
                'background' => [
                    'type' => 'gradient',
                    'color' => '#ffffff',
                    'direction' => 'vertical',
                    'stops' => [
                        ['color' => '#ffffff', 'position' => 0],
                        ['color' => '#c4b5fd', 'position' => 100],
                    ],
                ],
                'strokeColor' => '#ffffff',
                'strokeWidth' => 3,
                'radius' => 12,
            ]],
        ];
        $gd = $this->pixelAt($this->renderDocument('gd', $document), 40, 40);
        $imagick = $this->pixelAt($this->renderDocument('imagick', $document), 40, 40);

        foreach (['red', 'green', 'blue'] as $channel) {
            self::assertEqualsWithDelta($imagick[$channel], $gd[$channel], 8, sprintf('%s channel differs', $channel));
        }
    }

    public function testGdUsesImagickCompatibleTextLayoutMetrics(): void
    {
        if (! extension_loaded('gd') || ! extension_loaded('imagick') || ! function_exists('imageftbbox')) {
            self::markTestSkipped('GD with FreeType and Imagick are required for the parity check.');
        }

        $font = $this->availableFont();

        if ($font === '') {
            self::markTestSkipped('No readable test font is installed.');
        }

        $document = [
            'width' => 360,
            'height' => 180,
            'background' => ['type' => 'solid', 'color' => '#ffffff'],
            'elements' => [[
                'id' => 'title',
                'type' => 'text',
                'x' => 20,
                'y' => 20,
                'width' => 302,
                'height' => 135,
                'opacity' => 1,
                'rotation' => 0,
                'hidden' => false,
                '_text' => "GD quality\ncompared fairly",
                '_font_path' => $font,
                'fontSize' => 46,
                'minFontSize' => 18,
                'fontWeight' => 700,
                'color' => '#111827',
                'align' => 'left',
                'verticalAlign' => 'middle',
                'lineHeight' => 1.08,
            ]],
        ];
        $gdBounds = $this->visibleBounds($this->renderDocument('gd', $document));
        $imagickBounds = $this->visibleBounds($this->renderDocument('imagick', $document));

        foreach (array_keys($gdBounds) as $edge) {
            self::assertEqualsWithDelta(
                $imagickBounds[$edge],
                $gdBounds[$edge],
                8,
                sprintf('%s text edge differs', $edge),
            );
        }
    }

    public function testImagickExportsAllConfiguredSocialFormatsWithoutRelyingOnFileExtension(): void
    {
        if (! extension_loaded('imagick')) {
            self::markTestSkipped('Imagick is not installed.');
        }

        $renderer = ImagineRenderer::create('imagick');
        $mimeTypes = [
            'png' => 'image/png',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
        ];

        foreach ($mimeTypes as $format => $mimeType) {
            if (! in_array($format, $renderer->capabilities()['formats'], true)) {
                self::markTestSkipped(sprintf('The installed ImageMagick build cannot encode %s.', $format));
            }

            $destination = $this->temporaryPath('tmp');
            $result = $renderer->render([
                'width' => 120,
                'height' => 63,
                'background' => [
                    'type' => 'gradient',
                    'color' => '#101828',
                    'stops' => [
                        ['color' => '#101828', 'position' => 0],
                        ['color' => '#4f46e5', 'position' => 100],
                    ],
                    'direction' => 'vertical',
                ],
                'elements' => [],
            ], $destination, $format, 86);

            self::assertNotInstanceOf(WP_Error::class, $result);
            self::assertFileExists($destination);
            self::assertSame($mimeType, mime_content_type($destination));
        }
    }

    public function testImagickRendersMultipleGradientStopsAtTheirConfiguredPositions(): void
    {
        if (! extension_loaded('imagick')) {
            self::markTestSkipped('Imagick is not installed.');
        }

        $destination = $this->temporaryPath('png');
        $renderer = ImagineRenderer::create('imagick');
        $result = $renderer->render([
            'width' => 101,
            'height' => 3,
            'background' => [
                'type' => 'gradient',
                'color' => '#ff0000',
                'stops' => [
                    ['color' => '#ff0000', 'position' => 0],
                    ['color' => '#00ff00', 'position' => 50],
                    ['color' => '#0000ff', 'position' => 100],
                ],
                'direction' => 'horizontal',
            ],
            'elements' => [],
        ], $destination, 'png', 90);

        self::assertNotInstanceOf(WP_Error::class, $result);
        $output = new \Imagick($destination);
        $start = $output->getImagePixelColor(0, 1)->getColor();
        $middle = $output->getImagePixelColor(50, 1)->getColor();
        $end = $output->getImagePixelColor(100, 1)->getColor();
        $output->clear();

        self::assertGreaterThan(240, $start['r']);
        self::assertLessThan(15, $start['g']);
        self::assertGreaterThan(240, $middle['g']);
        self::assertLessThan(15, $middle['r']);
        self::assertGreaterThan(240, $end['b']);
        self::assertLessThan(15, $end['g']);
    }

    public function testImagickRendersRoundedRectangleShapeWithMultipleGradientStopsAndStroke(): void
    {
        if (! extension_loaded('imagick')) {
            self::markTestSkipped('Imagick is not installed.');
        }

        $destination = $this->temporaryPath('png');
        $renderer = ImagineRenderer::create('imagick');
        $result = $renderer->render([
            'width' => 121,
            'height' => 61,
            'background' => ['type' => 'solid', 'color' => '#ffffff'],
            'elements' => [[
                'id' => 'gradient-rectangle',
                'type' => 'shape',
                'shape' => 'rectangle',
                'x' => 10,
                'y' => 10,
                'width' => 101,
                'height' => 41,
                'opacity' => 1,
                'rotation' => 0,
                'hidden' => false,
                'background' => [
                    'type' => 'gradient',
                    'color' => '#ff0000',
                    'direction' => 'horizontal',
                    'stops' => [
                        ['color' => '#ff0000', 'position' => 0],
                        ['color' => '#00ff00', 'position' => 50],
                        ['color' => '#0000ff', 'position' => 100],
                    ],
                ],
                'strokeColor' => '#000000',
                'strokeWidth' => 2,
                'radius' => 10,
            ]],
        ], $destination, 'png', 90);

        self::assertNotInstanceOf(WP_Error::class, $result);
        $output = new \Imagick($destination);
        $left = $output->getImagePixelColor(13, 30)->getColor();
        $middle = $output->getImagePixelColor(60, 30)->getColor();
        $right = $output->getImagePixelColor(107, 30)->getColor();
        $border = $output->getImagePixelColor(10, 30)->getColor();
        $corner = $output->getImagePixelColor(10, 10)->getColor();
        $output->clear();

        self::assertGreaterThan(220, $left['r']);
        self::assertGreaterThan(220, $middle['g']);
        self::assertGreaterThan(220, $right['b']);
        self::assertLessThan(20, $border['r']);
        self::assertGreaterThan(240, $corner['r']);
        self::assertGreaterThan(240, $corner['g']);
        self::assertGreaterThan(240, $corner['b']);
    }

    public function testImagickCompositesAnSvgLayerThroughTheDetectedEngine(): void
    {
        if (! extension_loaded('imagick')) {
            self::markTestSkipped('Imagick is not installed.');
        }

        $rasterizer = new SvgRasterizer();

        if (! $rasterizer->available()) {
            self::markTestSkipped($rasterizer->capabilities()['reason']);
        }

        $destination = $this->temporaryPath('png');
        $renderer = ImagineRenderer::create('imagick', $rasterizer);
        $result = $renderer->render([
            'width' => 120,
            'height' => 120,
            'background' => ['type' => 'solid', 'color' => '#ffffff'],
            'elements' => [[
                'id' => 'svg-check',
                'type' => 'svg',
                'x' => 20,
                'y' => 20,
                'width' => 80,
                'height' => 80,
                'opacity' => 1,
                'rotation' => 0,
                'hidden' => false,
                '_svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="#14b8a6" d="M2 2h20v20H2z"/></svg>',
            ]],
        ], $destination, 'png', 90);

        self::assertNotInstanceOf(WP_Error::class, $result);
        self::assertFileExists($destination);
        $output = new \Imagick($destination);
        $center = $output->getImagePixelColor(60, 60)->getColor();
        $output->clear();
        self::assertLessThan(60, $center['r']);
        self::assertGreaterThan(140, $center['g']);
        self::assertGreaterThan(120, $center['b']);

        if ($rasterizer->capabilities()['limited']) {
            self::assertContains($rasterizer->capabilities()['notice'], $renderer->warnings());
        } else {
            self::assertSame([], $renderer->warnings());
        }
    }

    public function testImagickRoundedImageMaskKeepsTheInteriorAndClipsTheCorners(): void
    {
        if (! extension_loaded('imagick')) {
            self::markTestSkipped('Imagick is not installed.');
        }

        $sourcePath = $this->temporaryPath('png');
        $source = new \Imagick();
        $source->newImage(80, 80, new \ImagickPixel('#ff9900'), 'png');
        $source->setImageAlphaChannel(\Imagick::ALPHACHANNEL_SET);
        $pixels = $source->getPixelIterator();

        foreach ($pixels as $row) {
            for ($x = 30; $x < 50; $x++) {
                $row[$x]->setColor('rgba(255, 153, 0, 0)');
            }

            $row[29]->setColor('rgba(255, 153, 0, 0.5)');
            $row[50]->setColor('rgba(255, 153, 0, 0.5)');
            $pixels->syncIterator();
        }

        $source->writeImage($sourcePath);
        $source->clear();

        $destination = $this->temporaryPath('png');
        $renderer = ImagineRenderer::create('imagick');
        $result = $renderer->render([
            'width' => 120,
            'height' => 120,
            'background' => ['type' => 'solid', 'color' => '#ffffff'],
            'elements' => [[
                'id' => 'rounded-image',
                'type' => 'image',
                'x' => 20,
                'y' => 20,
                'width' => 80,
                'height' => 80,
                'opacity' => 1,
                'rotation' => 0,
                'hidden' => false,
                '_path' => $sourcePath,
                'fit' => 'fill',
                'radius' => 16,
            ]],
        ], $destination, 'png', 90);

        self::assertNotInstanceOf(WP_Error::class, $result);
        $output = new \Imagick($destination);
        $interior = $output->getImagePixelColor(40, 60)->getColor();
        $transparentBand = $output->getImagePixelColor(60, 60)->getColor();
        $softEdge = $output->getImagePixelColor(49, 60)->getColor();
        $corner = $output->getImagePixelColor(20, 20)->getColor();
        $output->clear();

        self::assertGreaterThan(240, $interior['r']);
        self::assertGreaterThan(120, $interior['g']);
        self::assertLessThan(40, $interior['b']);
        self::assertGreaterThan(240, $transparentBand['r']);
        self::assertGreaterThan(240, $transparentBand['g']);
        self::assertGreaterThan(240, $transparentBand['b']);
        self::assertGreaterThan(180, $softEdge['g']);
        self::assertLessThan(225, $softEdge['g']);
        self::assertGreaterThan(100, $softEdge['b']);
        self::assertLessThan(155, $softEdge['b']);
        self::assertGreaterThan(240, $corner['r']);
        self::assertGreaterThan(240, $corner['g']);
        self::assertGreaterThan(240, $corner['b']);
    }

    public function testImagickLayerOpacityIsNotInverted(): void
    {
        if (! extension_loaded('imagick')) {
            self::markTestSkipped('Imagick is not installed.');
        }

        $destination = $this->temporaryPath('png');
        $renderer = ImagineRenderer::create('imagick');
        $result = $renderer->render([
            'width' => 40,
            'height' => 40,
            'background' => ['type' => 'solid', 'color' => '#ffffff'],
            'elements' => [[
                'id' => 'opacity-check',
                'type' => 'shape',
                'shape' => 'rectangle',
                'x' => 0,
                'y' => 0,
                'width' => 40,
                'height' => 40,
                'opacity' => 0.8,
                'rotation' => 0,
                'hidden' => false,
                'background' => ['type' => 'solid', 'color' => '#000000'],
                'strokeColor' => '#000000',
                'strokeWidth' => 0,
                'radius' => 0,
            ]],
        ], $destination, 'png', 90);

        self::assertNotInstanceOf(WP_Error::class, $result);
        $output = new \Imagick($destination);
        $center = $output->getImagePixelColor(20, 20)->getColor();
        $output->clear();

        self::assertLessThan(80, $center['r']);
        self::assertLessThan(80, $center['g']);
        self::assertLessThan(80, $center['b']);
    }

    public function testImagickRendersShapesAndImageMasks(): void
    {
        if (! extension_loaded('imagick')) {
            self::markTestSkipped('Imagick is not installed.');
        }

        $sourcePath = $this->temporaryPath('png');
        $source = new \Imagick();
        $source->newImage(80, 80, new \ImagickPixel('#ef4444'), 'png');
        $source->writeImage($sourcePath);
        $source->clear();

        $destination = $this->temporaryPath('png');
        $renderer = ImagineRenderer::create('imagick');
        $result = $renderer->render([
            'width' => 260,
            'height' => 110,
            'background' => ['type' => 'solid', 'color' => '#ffffff'],
            'elements' => [
                [
                    'id' => 'diamond',
                    'type' => 'shape',
                    'shape' => 'diamond',
                    'x' => 10,
                    'y' => 10,
                    'width' => 80,
                    'height' => 80,
                    'opacity' => 1,
                    'rotation' => 0,
                    'hidden' => false,
                    'background' => ['type' => 'solid', 'color' => '#22c55e'],
                    'strokeColor' => '#000000',
                    'strokeWidth' => 0,
                ],
                [
                    'id' => 'masked-photo',
                    'type' => 'image',
                    'x' => 100,
                    'y' => 10,
                    'width' => 80,
                    'height' => 80,
                    'opacity' => 1,
                    'rotation' => 0,
                    'hidden' => false,
                    '_path' => $sourcePath,
                    'fit' => 'fill',
                    'radius' => 0,
                    'mask' => 'ellipse',
                ],
            ],
        ], $destination, 'png', 90);

        self::assertNotInstanceOf(WP_Error::class, $result);
        $output = new \Imagick($destination);
        $diamondCorner = $output->getImagePixelColor(10, 10)->getColor();
        $diamondCenter = $output->getImagePixelColor(50, 50)->getColor();
        $imageCorner = $output->getImagePixelColor(100, 10)->getColor();
        $imageCenter = $output->getImagePixelColor(140, 50)->getColor();
        $output->clear();

        self::assertGreaterThan(240, $diamondCorner['r']);
        self::assertGreaterThan(150, $diamondCenter['g']);
        self::assertGreaterThan(240, $imageCorner['g']);
        self::assertGreaterThan(200, $imageCenter['r']);
    }

    public function testUnknownOutputFallsBackToPng(): void
    {
        if (! extension_loaded('gd')) {
            self::markTestSkipped('GD is not installed.');
        }

        $renderer = ImagineRenderer::create('gd');
        $result = $renderer->render([
            'width' => 10,
            'height' => 10,
            'background' => ['type' => 'solid', 'color' => '#000000'],
            'elements' => [],
        ], $this->temporaryPath('png'), 'tiff');

        self::assertNotInstanceOf(WP_Error::class, $result);
        self::assertFileExists($result['path']);
    }

    private function sourceImage(): string
    {
        $path = $this->temporaryPath('png');
        $image = imagecreatetruecolor(240, 160);
        self::assertInstanceOf(GdImage::class, $image);
        imagefill($image, 0, 0, imagecolorallocate($image, 245, 158, 11));
        imagefilledellipse($image, 120, 80, 130, 130, imagecolorallocate($image, 79, 70, 229));
        imagepng($image, $path);

        return $path;
    }

    private function renderDocument(string $driver, array $document): string
    {
        $destination = $this->temporaryPath('png');
        $result = ImagineRenderer::create($driver)->render($document, $destination, 'png', 90);

        self::assertNotInstanceOf(WP_Error::class, $result);
        self::assertFileExists($destination);

        return $destination;
    }

    /**
     * @return array{red: int, green: int, blue: int}
     */
    private function pixelAt(string $path, int $x, int $y): array
    {
        $image = imagecreatefrompng($path);
        self::assertInstanceOf(GdImage::class, $image);
        $color = imagecolorat($image, $x, $y);
        $components = imagecolorsforindex($image, $color);
        unset($image);

        return [
            'red' => $components['red'],
            'green' => $components['green'],
            'blue' => $components['blue'],
        ];
    }

    /**
     * @return array{left: int, top: int, right: int, bottom: int}
     */
    private function visibleBounds(string $path): array
    {
        $image = imagecreatefrompng($path);
        self::assertInstanceOf(GdImage::class, $image);
        $width = imagesx($image);
        $height = imagesy($image);
        $bounds = ['left' => $width, 'top' => $height, 'right' => -1, 'bottom' => -1];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);
                $components = imagecolorsforindex($image, $color);
                $red = $components['red'];
                $green = $components['green'];
                $blue = $components['blue'];

                if ($red >= 245 && $green >= 245 && $blue >= 245) {
                    continue;
                }

                $bounds['left'] = min($bounds['left'], $x);
                $bounds['top'] = min($bounds['top'], $y);
                $bounds['right'] = max($bounds['right'], $x);
                $bounds['bottom'] = max($bounds['bottom'], $y);
            }
        }

        unset($image);
        self::assertGreaterThanOrEqual(0, $bounds['right']);
        self::assertGreaterThanOrEqual(0, $bounds['bottom']);

        return $bounds;
    }

    /**
     * @return array<string, array{string}>
     */
    public function compatibleDriverProvider(): array
    {
        return [
            'Imagick' => ['imagick'],
            'GD' => ['gd'],
        ];
    }

    private function temporaryPath(string $extension): string
    {
        $path = sys_get_temp_dir() . '/social-image-imagine-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function availableFont(): string
    {
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
        ] as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return '';
    }
}
