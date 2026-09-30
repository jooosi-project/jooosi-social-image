<?php

declare(strict_types=1);

namespace JooosiSocialImage\Tests\Unit;

use JooosiSocialImage\Template\TemplateSchema;
use PHPUnit\Framework\TestCase;

final class TemplateSchemaTest extends TestCase
{
    public function testDocumentNormalizationAllowListsAndBoundsInput(): void
    {
        $document = TemplateSchema::normalizeDocument([
            'version' => 99,
            'width' => 9000,
            'height' => 10,
            'background' => [
                'type' => 'script',
                'color' => 'not-a-color',
                'direction' => 'diagonal',
                'stops' => [
                    ['color' => '#00ff00', 'position' => 75],
                    ['color' => '#ff0000', 'position' => -20],
                    ['color' => 'not-a-color', 'position' => 140],
                ],
            ],
            'elements' => [[
                'id' => 'Title With Spaces',
                'type' => 'unknown',
                'x' => -99999,
                'y' => 99999,
                'width' => 0,
                'height' => 99999,
                'opacity' => 8,
                'content' => '<b>{{post.title}}</b>',
                'fontSize' => 999,
            ]],
        ]);

        self::assertSame(TemplateSchema::DOCUMENT_VERSION, $document['version']);
        self::assertSame(2400, $document['width']);
        self::assertSame(200, $document['height']);
        self::assertSame('solid', $document['background']['type']);
        self::assertSame('#111827', $document['background']['color']);
        self::assertSame('horizontal', $document['background']['direction']);
        self::assertSame([
            ['color' => '#ff0000', 'position' => 0.0],
            ['color' => '#00ff00', 'position' => 75.0],
            ['color' => '#111827', 'position' => 100.0],
        ], $document['background']['stops']);
        self::assertSame('titlewithspaces', $document['elements'][0]['id']);
        self::assertSame('text', $document['elements'][0]['type']);
        self::assertSame(-2400.0, $document['elements'][0]['x']);
        self::assertSame(1.0, $document['elements'][0]['width']);
        self::assertSame(1.0, $document['elements'][0]['opacity']);
        self::assertSame('{{post.title}}', $document['elements'][0]['content']);
        self::assertSame(400.0, $document['elements'][0]['fontSize']);
    }

    public function testMissingGradientStopsUseTheCurrentDocumentDefaults(): void
    {
        $document = TemplateSchema::normalizeDocument([
            'background' => [
                'type' => 'gradient',
                'color' => '#123456',
                'direction' => 'vertical',
            ],
        ]);

        self::assertSame([
            ['color' => '#123456', 'position' => 0.0],
            ['color' => '#312e81', 'position' => 100.0],
        ], $document['background']['stops']);
    }

    public function testImageSourceAcceptsHttpsUrlsAndSanitizesDynamicValues(): void
    {
        $document = TemplateSchema::normalizeDocument([
            'elements' => [
                ['id' => 'external', 'type' => 'image', 'source' => 'https://images.example.test/photo.jpg?size=large'],
                ['id' => 'dynamic', 'type' => 'image', 'source' => '<b>{{post.featured_image}}</b>'],
            ],
        ]);

        self::assertSame('https://images.example.test/photo.jpg?size=large', $document['elements'][0]['source']);
        self::assertSame('none', $document['elements'][0]['mask']);
        self::assertSame('{{post.featured_image}}', $document['elements'][1]['source']);
    }

    public function testShapesAndImageMasksAreNormalizedSafely(): void
    {
        $document = TemplateSchema::normalizeDocument([
            'elements' => [
                [
                    'id' => 'accent',
                    'type' => 'shape',
                    'shape' => 'star',
                    'strokeColor' => '#14b8a6',
                    'strokeWidth' => 12,
                    'background' => ['type' => 'solid', 'color' => '#f97316'],
                ],
                [
                    'id' => 'accent',
                    'type' => 'image',
                    'mask' => 'hexagon',
                ],
            ],
        ]);

        self::assertSame('star', $document['elements'][0]['shape']);
        self::assertSame(0.0, $document['elements'][0]['radius']);
        self::assertSame('#14b8a6', $document['elements'][0]['strokeColor']);
        self::assertSame(12.0, $document['elements'][0]['strokeWidth']);
        self::assertSame('hexagon', $document['elements'][1]['mask']);
        self::assertSame('accent-2', $document['elements'][1]['id']);
    }

    public function testRuleNormalizationProducesDeterministicSafeValues(): void
    {
        $rules = TemplateSchema::normalizeRules([
            'outputs' => ['featured', 'script', 'og', 'og'],
            'query' => [
                'id' => 'root',
                'type' => 'group',
                'relation' => 'and',
                'children' => [
                    ['id' => 'post-type', 'type' => 'condition', 'field' => 'post_type', 'operator' => 'in', 'values' => ['Post', 'product!']],
                    ['id' => 'post-status', 'type' => 'condition', 'field' => 'post_status', 'operator' => 'in', 'values' => ['publish', 'invalid status']],
                    ['id' => 'post-include', 'type' => 'condition', 'field' => 'post_id', 'operator' => 'in', 'values' => [-8, '8', 0]],
                    ['id' => 'post-exclude', 'type' => 'condition', 'field' => 'post_id', 'operator' => 'not_in', 'values' => ['12', '12']],
                ],
            ],
            'priority' => -9999,
            'replaceFeatured' => true,
        ]);

        self::assertSame(['og', 'twitter', 'featured'], $rules['outputs']);
        self::assertSame('and', $rules['query']['relation']);
        self::assertSame([
            ['id' => 'post-type', 'type' => 'condition', 'field' => 'post_type', 'operator' => 'in', 'key' => '', 'values' => ['post', 'product']],
            ['id' => 'post-status', 'type' => 'condition', 'field' => 'post_status', 'operator' => 'in', 'key' => '', 'values' => ['publish', 'invalidstatus']],
            ['id' => 'post-include', 'type' => 'condition', 'field' => 'post_id', 'operator' => 'in', 'key' => '', 'values' => ['8']],
            ['id' => 'post-exclude', 'type' => 'condition', 'field' => 'post_id', 'operator' => 'not_in', 'key' => '', 'values' => ['12']],
        ], $rules['query']['children']);
        self::assertSame(-1000, $rules['priority']);
        self::assertTrue($rules['replaceFeatured']);
    }

    public function testSocialOutputsAreAlwaysNormalizedAsAPair(): void
    {
        self::assertSame(['og', 'twitter'], TemplateSchema::normalizeRules(['outputs' => ['og']])['outputs']);
        self::assertSame(['og', 'twitter'], TemplateSchema::normalizeRules(['outputs' => ['twitter']])['outputs']);
        self::assertSame(['featured'], TemplateSchema::normalizeRules(['outputs' => ['social', 'featured']])['outputs']);
    }

    public function testRuleConditionIdsAreMadeUnique(): void
    {
        $rules = TemplateSchema::normalizeRules([
            'query' => [
                'id' => 'root',
                'type' => 'group',
                'relation' => 'and',
                'children' => [
                    ['id' => 'target', 'type' => 'condition', 'field' => 'post_type', 'operator' => 'in', 'values' => ['post']],
                    ['id' => 'target', 'type' => 'condition', 'field' => 'post_status', 'operator' => 'in', 'values' => ['publish']],
                ],
            ],
        ]);

        self::assertSame(['target', 'target-2'], array_column($rules['query']['children'], 'id'));
    }

    public function testNestedBooleanGroupsAreNormalized(): void
    {
        $rules = TemplateSchema::normalizeRules([
            'query' => [
                'id' => 'root',
                'type' => 'group',
                'relation' => 'and',
                'children' => [[
                    'id' => 'content-kind',
                    'type' => 'group',
                    'relation' => 'or',
                    'children' => [
                        ['id' => 'post', 'type' => 'condition', 'field' => 'post_type', 'operator' => 'in', 'values' => ['post']],
                        ['id' => 'page', 'type' => 'condition', 'field' => 'post_type', 'operator' => 'in', 'values' => ['page']],
                    ],
                ]],
            ],
        ]);

        self::assertSame('group', $rules['query']['type']);
        self::assertSame('or', $rules['query']['children'][0]['relation']);
        self::assertSame(['post', 'page'], array_column($rules['query']['children'][0]['children'], 'id'));
    }

    public function testSvgElementsKeepOnlyAValidIconNameAndColor(): void
    {
        $document = TemplateSchema::normalizeDocument([
            'elements' => [
                [
                    'id' => 'brand-icon',
                    'type' => 'svg',
                    'icon' => 'Lucide:Circle-Check',
                    'color' => '#14b8a6',
                ],
                [
                    'id' => 'unsafe-icon',
                    'type' => 'svg',
                    'icon' => 'mdi:home\" onload=\"alert(1)',
                    'color' => 'javascript:alert(1)',
                ],
            ],
        ]);

        self::assertSame('lucide:circle-check', $document['elements'][0]['icon']);
        self::assertSame('#14b8a6', $document['elements'][0]['color']);
        self::assertSame('', $document['elements'][1]['icon']);
        self::assertSame('#ffffff', $document['elements'][1]['color']);
    }
}
