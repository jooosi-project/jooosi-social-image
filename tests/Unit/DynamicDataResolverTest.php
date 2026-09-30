<?php

declare(strict_types=1);

namespace JooosiSocialImage\Tests\Unit;

use JooosiSocialImage\Content\DynamicDataResolver;
use PHPUnit\Framework\TestCase;

final class DynamicDataResolverTest extends TestCase
{
    public function testNestedPlaceholdersAndArraysAreRenderedPredictably(): void
    {
        $resolver = new DynamicDataResolver();
        $data = [
            'post' => ['title' => 'Typed WordPress', 'id' => 42],
            'taxonomy' => ['category' => ['Design', 'Engineering']],
        ];

        self::assertSame(
            'Typed WordPress · Design, Engineering · ',
            $resolver->replace('{{post.title}} · {{taxonomy.category}} · {{missing.value}}', $data),
        );
        self::assertSame(42, $resolver->rawValue('{{post.id}}', $data));
        self::assertSame('Article 42', $resolver->rawValue('Article {{post.id}}', $data));
    }
}
