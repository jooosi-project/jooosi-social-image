<?php

declare(strict_types=1);

namespace JooosiEgami\Tests\Processing;

use FilesystemIterator;
use JooosiEgami\Assignment\TemplateMatcher;
use JooosiEgami\Content\DynamicDataResolver;
use JooosiEgami\Integration\YabeWebfont;
use JooosiEgami\Rendering\FontLocator;
use JooosiEgami\Rendering\ImageGenerator;
use JooosiEgami\Rendering\RemoteImageFetcher;
use JooosiEgami\Rendering\RendererFactory;
use JooosiEgami\Template\TemplateRepository;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ImageGeneratorCacheInvalidationTest extends TestCase
{
    private string $uploads;

    protected function setUp(): void
    {
        $this->uploads = sys_get_temp_dir() . '/jooosi-egami-cache-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->uploads . '/egami/previews', 0777, true));
        $GLOBALS['egami_test_upload_basedir'] = $this->uploads;
    }

    protected function tearDown(): void
    {
        if (is_dir($this->uploads)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->uploads, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }

            rmdir($this->uploads);
        }

        unset($GLOBALS['egami_test_upload_basedir']);
    }

    public function testSelectiveFlushInvalidatesOnlyTheRequestedDesign(): void
    {
        $directory = $this->uploads . '/egami';
        file_put_contents($directory . '/12-4-social-a.png', 'design-12');
        file_put_contents($directory . '/13-4-social-b.png', 'design-13');
        file_put_contents($directory . '/previews/preview.png', 'preview');

        $result = $this->generator()->flushCache(12);

        self::assertSame(['deleted' => 1], $result);
        self::assertFileDoesNotExist($directory . '/12-4-social-a.png');
        self::assertFileExists($directory . '/13-4-social-b.png');
        self::assertFileExists($directory . '/previews/preview.png');
    }

    public function testFullFlushInvalidatesNestedPreviewAndGeneratedFiles(): void
    {
        $directory = $this->uploads . '/egami';
        file_put_contents($directory . '/12-4-social-a.png', 'render');
        file_put_contents($directory . '/previews/preview.png', 'preview');

        $result = $this->generator()->flushCache();

        self::assertSame(['deleted' => 2], $result);
        self::assertFileDoesNotExist($directory . '/12-4-social-a.png');
        self::assertFileDoesNotExist($directory . '/previews/preview.png');
    }

    private function generator(): ImageGenerator
    {
        $repository = new TemplateRepository();

        return new ImageGenerator(
            $repository,
            new TemplateMatcher($repository),
            new DynamicDataResolver(),
            RendererFactory::create(),
            new FontLocator(new YabeWebfont()),
            new RemoteImageFetcher(),
        );
    }
}
