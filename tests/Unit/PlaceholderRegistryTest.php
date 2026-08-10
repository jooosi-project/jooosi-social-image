<?php

declare(strict_types=1);

namespace JooosiEgami\Tests\Unit;

use JooosiEgami\Content\PlaceholderProviderInterface;
use JooosiEgami\Content\PlaceholderRegistry;
use PHPUnit\Framework\TestCase;

final class PlaceholderRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('jooosi-egami/content:placeholder_definitions');
        remove_all_filters('jooosi-egami/content:placeholder_values');
        parent::tearDown();
    }

    public function testProvidersAreNamespacedAndImageValuesBecomeUrls(): void
    {
        $provider = new class implements PlaceholderProviderInterface {
            public function namespace(): string { return 'commerce'; }
            public function definitions(int $postId): array { return ['hero' => ['label' => 'Hero', 'group' => 'Commerce', 'type' => 'image']]; }
            public function values(int $postId): array { return ['hero' => ['url' => 'https://example.test/hero.jpg']]; }
        };
        $registry = new PlaceholderRegistry([$provider]);

        $definitions = $registry->definitions();
        self::assertContains('commerce.hero', array_column($definitions, 'key'));
        self::assertSame('https://example.test/hero.jpg', $registry->values(12)['commerce']['hero']);
    }

    public function testThirdPartiesCanRegisterDefinitionsAndValuesWithFilters(): void
    {
        add_filter('jooosi-egami/content:placeholder_definitions', static function (array $definitions): array {
            $definitions['company.slogan'] = ['label' => 'Slogan', 'group' => 'Company'];
            return $definitions;
        });
        add_filter('jooosi-egami/content:placeholder_values', static function (array $values): array {
            $values['company']['slogan'] = 'Build something memorable';
            return $values;
        });

        $registry = new PlaceholderRegistry([]);
        self::assertContains('company.slogan', array_column($registry->definitions(), 'key'));
        self::assertSame('Build something memorable', $registry->values(0)['company']['slogan']);
    }
}
