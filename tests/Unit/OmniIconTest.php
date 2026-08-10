<?php

declare(strict_types=1);

namespace JooosiEgami\Tests\Unit;

use JooosiEgami\Integration\OmniIcon;
use PHPUnit\Framework\TestCase;

final class OmniIconTest extends TestCase
{
    /**
     * @dataProvider iconNameProvider
     */
    public function testIconNameValidationDoesNotAcceptMarkupOrAmbiguousNames(string $name, bool $valid): void
    {
        self::assertSame($valid, OmniIcon::validName($name));
    }

    /** @return array<string, array{string, bool}> */
    public function iconNameProvider(): array
    {
        return [
            'iconify name' => ['mdi:home', true],
            'hyphenated name' => ['lucide:circle-check-big', true],
            'uppercase is normalized' => ['Lucide:Circle-Check', true],
            'missing prefix' => ['home', false],
            'extra separator' => ['mdi:home:filled', false],
            'markup' => ['mdi:home\" onload=\"alert(1)', false],
            'path traversal' => ['local:../secret', false],
        ];
    }
}
