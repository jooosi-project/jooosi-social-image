<?php

declare(strict_types=1);

namespace JooosiEgami\Content;

defined('ABSPATH') || exit;

/**
 * Supplies a namespaced set of dynamic placeholders.
 *
 * Integrations remain optional: providers must return an empty array when the
 * plugin they adapt is unavailable.
 */
interface PlaceholderProviderInterface
{
    public function namespace(): string;

    /**
     * @return array<string, array{label?: string, group?: string, type?: string, description?: string}>
     */
    public function definitions(int $postId): array;

    /**
     * @return array<string, mixed>
     */
    public function values(int $postId): array;
}
