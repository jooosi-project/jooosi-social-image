<?php

declare (strict_types=1);
namespace JooosiEgami\Integration\CustomFields;

use JooosiEgami\Content\PlaceholderProviderInterface;
defined('ABSPATH') || exit;
/**
 * JetEngine post fields are stored as native WordPress post metadata.
 */
final class JetEnginePlaceholderProvider implements PlaceholderProviderInterface
{
    public function namespace(): string
    {
        return 'jetengine';
    }
    public function definitions(int $postId): array
    {
        $fields = [];
        foreach (array_keys($this->metadata($postId)) as $key) {
            $fields[$key] = ['label' => ucwords(str_replace(['_', '-'], ' ', $key)), 'group' => __('JetEngine', 'jooosi-egami')];
        }
        return $fields;
    }
    public function values(int $postId): array
    {
        return $this->metadata($postId);
    }
    private function metadata(int $postId): array
    {
        if ($postId < 1 || !defined('JooosiEgamiDeps\JET_ENGINE_VERSION') && !function_exists('JooosiEgamiDeps\jet_engine')) {
            return [];
        }
        $result = [];
        foreach ((array) get_post_meta($postId) as $key => $values) {
            if (is_protected_meta((string) $key, 'post')) {
                continue;
            }
            $values = array_map('maybe_unserialize', (array) $values);
            $result[(string) $key] = count($values) === 1 ? reset($values) : $values;
        }
        return $result;
    }
}
