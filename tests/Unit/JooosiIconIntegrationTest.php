<?php

declare(strict_types=1);

namespace JooosiIcon\Services {
    final class IconService
    {
        /** @var list<array<string, string>> */
        public array $calls = [];

        public function get_icon(string $name, array $attributes = []): ?string
        {
            foreach ($attributes as $value) {
                if (! is_string($value)) {
                    throw new \TypeError('Presentation attributes must be strings.');
                }
            }

            $this->calls[] = $attributes;

            $htmlAttributes = '';

            foreach ($attributes as $name => $value) {
                $htmlAttributes .= sprintf(' %s="%s"', $name, htmlspecialchars($value, ENT_QUOTES));
            }

            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"' . $htmlAttributes . '><path fill="none" stroke="currentColor" d="M2 12h20"/></svg>';
        }

        public function search_icons(string $query): array
        {
            return ['results' => [['prefix' => 'lucide', 'name' => 'star']], 'total' => 1];
        }
    }
}

namespace JooosiIcon {
    final class TestContainer
    {
        public function __construct(private Services\IconService $service)
        {
        }

        public function get(string $id): object
        {
            return $this->service;
        }
    }

    final class Plugin
    {
        private static ?self $instance = null;

        private Services\IconService $service;

        private function __construct()
        {
            $this->service = new Services\IconService();
        }

        public static function get_instance(): self
        {
            return self::$instance ??= new self();
        }

        public function container(): TestContainer
        {
            return new TestContainer($this->service);
        }

        public function service(): Services\IconService
        {
            return $this->service;
        }
    }
}

namespace JooosiSocialImage\Tests\Unit {
    use JooosiSocialImage\Integration\JooosiIcon;
    use PHPUnit\Framework\TestCase;

    final class JooosiIconIntegrationTest extends TestCase
    {
        public function testPublicServiceResultIsAlreadyHtmlAndPresentationIsNormalized(): void
        {
            $integration = new JooosiIcon();
            $svg = $integration->get('lucide:star', [
                'width' => 96,
                'height' => 64,
                'color' => '#FF0000',
            ]);

            self::assertIsString($svg);
            self::assertStringContainsString('width="96"', $svg);
            self::assertStringContainsString('height="64"', $svg);
            self::assertStringContainsString('color="#FF0000"', $svg);
            self::assertStringContainsString('stroke="currentColor"', $svg);
            self::assertSame([
                'width' => '96',
                'height' => '64',
                'color' => '#FF0000',
            ], \JooosiIcon\Plugin::get_instance()->service()->calls[0]);
        }
    }
}
