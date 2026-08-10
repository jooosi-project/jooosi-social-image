<?php

declare(strict_types=1);

namespace JooosiEgami\Integration\CustomFields;

use JooosiEgami\Content\PlaceholderProviderInterface;
use Throwable;

defined('ABSPATH') || exit;

final class ToolsetPlaceholderProvider implements PlaceholderProviderInterface
{
    public function namespace(): string
    {
        return 'toolset';
    }

    public function definitions(int $postId): array
    {
        $result = [];
        foreach ($this->instances($postId) as $instance) {
            $definition = is_object($instance) && method_exists($instance, 'get_definition') ? $instance->get_definition() : null;
            if (! is_object($definition) || ! method_exists($definition, 'get_slug')) {
                continue;
            }
            $slug = sanitize_key((string) $definition->get_slug());
            if ($slug === '') {
                continue;
            }
            $result[$slug] = [
                'label' => method_exists($definition, 'get_name') ? (string) $definition->get_name() : $slug,
                'group' => __('Toolset', 'jooosi-egami'),
            ];
        }
        return $result;
    }

    public function values(int $postId): array
    {
        $result = [];
        foreach ($this->instances($postId) as $instance) {
            $definition = is_object($instance) && method_exists($instance, 'get_definition') ? $instance->get_definition() : null;
            if (! is_object($definition) || ! method_exists($definition, 'get_slug') || ! method_exists($instance, 'get_value')) {
                continue;
            }
            $slug = sanitize_key((string) $definition->get_slug());
            if ($slug !== '') {
                $result[$slug] = $instance->get_value();
            }
        }
        return $result;
    }

    /** @return array<int, object> */
    private function instances(int $postId): array
    {
        if ($postId < 1 || ! function_exists('toolset_get_field_instances')) {
            return [];
        }

        try {
            $instances = toolset_get_field_instances([
                'domain' => 'posts',
                'post_id' => $postId,
                'load_value' => true,
            ]);
            return is_array($instances) ? array_values($instances) : [];
        } catch (Throwable) {
            return [];
        }
    }
}
