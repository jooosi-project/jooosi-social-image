<?php

declare(strict_types=1);

namespace JooosiSocialImage\Tests\Rendering;

use JooosiSocialImage\Rendering\TemporaryFile;
use PHPUnit\Framework\TestCase;

final class TemporaryFileTest extends TestCase
{
    public function testItCreatesAFileInsideTheRequestedDirectory(): void
    {
        $directory = sys_get_temp_dir();
        $path = TemporaryFile::create($directory);

        self::assertIsString($path);
        self::assertFileExists($path);
        self::assertSame(realpath($directory), realpath(dirname($path)));

        unlink($path);
    }

    public function testItRejectsAnUnavailableDirectory(): void
    {
        $directory = sys_get_temp_dir() . '/social-image-missing-' . bin2hex(random_bytes(8));

        self::assertNull(TemporaryFile::create($directory));
    }
}
