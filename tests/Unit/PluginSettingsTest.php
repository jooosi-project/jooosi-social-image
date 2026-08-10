<?php

declare(strict_types=1);

namespace JooosiEgami\Tests\Unit;

use JooosiEgami\Settings\PluginSettings;
use PHPUnit\Framework\TestCase;

final class PluginSettingsTest extends TestCase
{
    public function testSettingsAreBoundedAndStringBooleansAreParsed(): void
    {
        $settings = PluginSettings::sanitize([
            'format' => 'invalid',
            'quality' => 900,
            'replace_featured' => 'false',
            'delete_on_uninstall' => 'yes',
        ]);

        self::assertSame('png', $settings['format']);
        self::assertSame(100, $settings['quality']);
        self::assertArrayNotHasKey('processing', $settings);
        self::assertFalse($settings['replace_featured']);
        self::assertTrue($settings['delete_on_uninstall']);
    }
}
