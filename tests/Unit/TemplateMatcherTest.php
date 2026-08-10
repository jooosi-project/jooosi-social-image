<?php

declare(strict_types=1);

namespace JooosiEgami\Tests\Unit;

use JooosiEgami\Assignment\TemplateMatcher;
use JooosiEgami\Template\TemplateRepository;
use PHPUnit\Framework\TestCase;
use WP_Post;

final class TemplateMatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['egami_test_post_meta'] = [];
        $GLOBALS['egami_test_terms'] = [];
        $GLOBALS['egami_test_page_templates'] = [];
    }

    public function testAllConditionsMustMatchForAndQueries(): void
    {
        $post = $this->post();
        $GLOBALS['egami_test_post_meta'][42]['price'] = ['29.00'];
        $GLOBALS['egami_test_terms'][42]['category'] = ['news', '7'];

        self::assertTrue($this->matcher()->matches([
            'query' => [
                'type' => 'group',
                'relation' => 'and',
                'children' => [
                    ['type' => 'condition', 'field' => 'post_type', 'operator' => 'in', 'key' => '', 'values' => ['post']],
                    ['type' => 'condition', 'field' => 'taxonomy', 'operator' => 'has_any', 'key' => 'category', 'values' => ['news']],
                    ['type' => 'condition', 'field' => 'meta', 'operator' => 'greater', 'key' => 'price', 'values' => ['20']],
                    ['type' => 'condition', 'field' => 'post_title', 'operator' => 'contains', 'key' => '', 'values' => ['launch']],
                ],
            ],
        ], $post));
    }

    public function testAnyConditionCanMatchForOrQueries(): void
    {
        self::assertTrue($this->matcher()->matches([
            'query' => [
                'type' => 'group',
                'relation' => 'or',
                'children' => [
                    ['type' => 'condition', 'field' => 'post_type', 'operator' => 'in', 'key' => '', 'values' => ['page']],
                    ['type' => 'condition', 'field' => 'post_slug', 'operator' => 'ends_with', 'key' => '', 'values' => ['launch']],
                ],
            ],
        ], $this->post()));
    }

    public function testNoConditionsMatchAllContentRegardlessOfRelation(): void
    {
        self::assertTrue($this->matcher()->matches(['query' => ['type' => 'group', 'relation' => 'and', 'children' => []]], $this->post()));
        self::assertTrue($this->matcher()->matches(['query' => ['type' => 'group', 'relation' => 'or', 'children' => []]], $this->post()));
    }

    public function testNestedGroupsComposeIndependentBooleanRelations(): void
    {
        $GLOBALS['egami_test_terms'][42]['category'] = ['news'];

        self::assertTrue($this->matcher()->matches([
            'query' => [
                'type' => 'group',
                'relation' => 'and',
                'children' => [
                    [
                        'type' => 'group',
                        'relation' => 'or',
                        'children' => [
                            ['type' => 'condition', 'field' => 'post_type', 'operator' => 'in', 'values' => ['page']],
                            ['type' => 'condition', 'field' => 'post_type', 'operator' => 'in', 'values' => ['post']],
                        ],
                    ],
                    [
                        'type' => 'group',
                        'relation' => 'or',
                        'children' => [
                            ['type' => 'condition', 'field' => 'taxonomy', 'operator' => 'has_any', 'key' => 'category', 'values' => ['news']],
                            ['type' => 'condition', 'field' => 'post_status', 'operator' => 'in', 'values' => ['draft']],
                        ],
                    ],
                ],
            ],
        ], $this->post()));
    }

    private function matcher(): TemplateMatcher
    {
        return new TemplateMatcher(new TemplateRepository());
    }

    private function post(): WP_Post
    {
        return new WP_Post([
            'ID' => 42,
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_author' => 5,
            'post_title' => 'Product Launch Notes',
            'post_name' => 'product-launch',
            'post_excerpt' => 'Shipping this week',
            'post_date' => '2026-08-10 09:00:00',
            'post_modified' => '2026-08-10 10:00:00',
        ]);
    }
}
