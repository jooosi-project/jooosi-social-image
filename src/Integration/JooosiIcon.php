<?php

declare(strict_types=1);

namespace JooosiSocialImage\Integration;

use Throwable;

defined('ABSPATH') || exit;

/**
 * Optional boundary around Jooosi Icon's public service.
 *
 * No Jooosi Icon object crosses this class, which keeps Social Image loadable when the
 * integration plugin is absent and keeps PHP-Scoper release builds stable.
 */
final class JooosiIcon
{
    private bool $resolved = false;

    private ?object $service = null;

    public function available(): bool
    {
        return $this->service() instanceof \JooosiIcon\Services\IconService;
    }

    public function get(string $name, array $attributes = []): ?string
    {
        if (! self::validName($name)) {
            return null;
        }

        foreach ($attributes as $key => $value) {
            if (is_bool($value)) {
                $attributes[$key] = $value ? 'true' : 'false';
            } elseif (is_int($value) || is_float($value)) {
                $attributes[$key] = (string) $value;
            } elseif (! is_string($value)) {
                unset($attributes[$key]);
            }
        }

        $service = $this->service();

        if (! $service instanceof \JooosiIcon\Services\IconService) {
            return null;
        }

        try {
            $svg = $service->get_icon($name, $attributes);

            return is_string($svg) && $svg !== '' ? $svg : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{name: string, prefix: string, iconName: string}>
     */
    public function search(string $query, int $limit = 128): array
    {
        $service = $this->service();

        if (! $service instanceof \JooosiIcon\Services\IconService) {
            return [];
        }

        try {
            $response = $service->search_icons(trim($query));
        } catch (Throwable) {
            return [];
        }

        $results = [];

        foreach ((array) ($response['results'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $prefix = strtolower((string) ($item['prefix'] ?? ''));
            $iconName = strtolower((string) ($item['name'] ?? ''));
            $name = $prefix . ':' . $iconName;

            if (! self::validName($name)) {
                continue;
            }

            $results[] = compact('name', 'prefix', 'iconName');

            if (count($results) >= max(1, min(256, $limit))) {
                break;
            }
        }

        return $results;
    }

    public static function validName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*:[a-z0-9]+(?:-[a-z0-9]+)*$/', strtolower(trim($name)));
    }

    private function service(): ?object
    {
        if ($this->resolved) {
            return $this->service;
        }

        $this->resolved = true;

        if (! class_exists(\JooosiIcon\Plugin::class) || ! class_exists(\JooosiIcon\Services\IconService::class)) {
            return null;
        }

        try {
            $service = \JooosiIcon\Plugin::get_instance()
                ->container()
                ->get(\JooosiIcon\Services\IconService::class);

            if ($service instanceof \JooosiIcon\Services\IconService) {
                $this->service = $service;
            }
        } catch (Throwable) {
            $this->service = null;
        }

        return $this->service;
    }
}
