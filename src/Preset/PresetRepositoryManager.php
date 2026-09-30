<?php

declare(strict_types=1);

namespace JooosiSocialImage\Preset;

use JsonException;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Loads the bundled preset manifest and manages last-known-good remote copies.
 */
final class PresetRepositoryManager
{
    public const OPTION_REPOSITORIES = 'social_image_preset_repositories';

    public const OPTION_CACHE = 'social_image_preset_repository_cache';

    public const OPTION_BUNDLED_ENABLED = 'social_image_bundled_preset_repository_enabled';

    private const MAX_RESPONSE_BYTES = 2_097_152;

    private ?array $bundled = null;

    public function __construct(
        private string $bundledFile,
        private string $schemaBaseUrl,
    ) {
        $this->schemaBaseUrl = trailingslashit($schemaBaseUrl);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function catalog(): array|WP_Error
    {
        $bundled = $this->bundled();

        if (is_wp_error($bundled)) {
            return $bundled;
        }

        $bundledEnabled = get_option(self::OPTION_BUNDLED_ENABLED, '1') !== '0';
        $repositories = [$this->summary($bundled, '', true, $bundledEnabled, '', '')];
        $presets = $bundledEnabled ? $this->decorate($bundled, '', true) : [];
        $configured = $this->configured();
        $cache = $this->cache();

        foreach ($configured as $id => $configuration) {
            $stored = $cache[$id]['repository'] ?? null;
            $lastError = (string) ($configuration['lastError'] ?? '');

            if (! is_array($stored)) {
                $repositories[] = $this->missingSummary($configuration, $lastError ?: __('The repository has no cached manifest.', 'jooosi-social-image'));
                continue;
            }

            $clean = PresetSchema::repository($stored);

            if (is_wp_error($clean)) {
                $repositories[] = $this->missingSummary($configuration, $clean->get_error_message());
                continue;
            }

            $enabled = ! empty($configuration['enabled']);
            $syncedAt = (string) ($cache[$id]['syncedAt'] ?? '');
            $repositories[] = $this->summary($clean, (string) $configuration['url'], false, $enabled, $syncedAt, $lastError);

            if ($enabled) {
                $presets = array_merge($presets, $this->decorate($clean, (string) $configuration['url'], false));
            }
        }

        return [
            'schemaVersion' => PresetSchema::VERSION,
            'presets' => $presets,
            'repositories' => $repositories,
            'schemas' => [
                'preset' => $this->schemaBaseUrl . 'preset.schema.json',
                'repository' => $this->schemaBaseUrl . 'repository.schema.json',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function add(string $url): array|WP_Error
    {
        $url = $this->validatedUrl($url);

        if (is_wp_error($url)) {
            return $url;
        }

        foreach ($this->configured() as $configuration) {
            if ($configuration['url'] === $url) {
                return new WP_Error('social_image_repository_url_exists', __('That preset repository URL is already configured.', 'jooosi-social-image'), ['status' => 409]);
            }
        }

        $fetched = $this->fetch($url);

        if (is_wp_error($fetched)) {
            return $fetched;
        }

        $repository = $fetched['repository'];
        $id = $repository['id'];
        $bundled = $this->bundled();
        $configured = $this->configured();

        if ((! is_wp_error($bundled) && $bundled['id'] === $id) || isset($configured[$id])) {
            return new WP_Error('social_image_repository_id_exists', __('A preset repository with that id is already configured.', 'jooosi-social-image'), ['status' => 409]);
        }

        $configured[$id] = [
            'id' => $id,
            'url' => $url,
            'enabled' => true,
            'addedAt' => gmdate('c'),
            'lastError' => '',
        ];
        $cache = $this->cache();
        $cache[$id] = $fetched;
        $this->save($configured, $cache);

        return $this->catalog();
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function refresh(string $id): array|WP_Error
    {
        $configured = $this->configured();

        if (! isset($configured[$id])) {
            return $this->notFound();
        }

        $cache = $this->cache();
        $fetched = $this->fetch((string) $configured[$id]['url'], is_array($cache[$id] ?? null) ? $cache[$id] : []);

        if (is_wp_error($fetched)) {
            $configured[$id]['lastError'] = $fetched->get_error_message();
            update_option(self::OPTION_REPOSITORIES, $configured, false);

            return $fetched;
        }

        if ($fetched['repository']['id'] !== $id) {
            $error = __('The refreshed manifest changed its repository id. Remove it and add it again to accept that identity change.', 'jooosi-social-image');
            $configured[$id]['lastError'] = $error;
            update_option(self::OPTION_REPOSITORIES, $configured, false);

            return new WP_Error('social_image_repository_identity_changed', $error, ['status' => 409]);
        }

        $configured[$id]['lastError'] = '';
        $cache[$id] = $fetched;
        $this->save($configured, $cache);

        return $this->catalog();
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function setEnabled(string $id, bool $enabled): array|WP_Error
    {
        $bundled = $this->bundled();

        if (! is_wp_error($bundled) && $bundled['id'] === $id) {
            update_option(self::OPTION_BUNDLED_ENABLED, $enabled ? '1' : '0', false);

            return $this->catalog();
        }

        $configured = $this->configured();

        if (! isset($configured[$id])) {
            return $this->notFound();
        }

        $configured[$id]['enabled'] = $enabled;
        update_option(self::OPTION_REPOSITORIES, $configured, false);

        return $this->catalog();
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function remove(string $id): array|WP_Error
    {
        $bundled = $this->bundled();

        if (! is_wp_error($bundled) && $bundled['id'] === $id) {
            return new WP_Error(
                'social_image_bundled_repository_protected',
                __('The bundled preset repository cannot be deleted. Disable it to hide its templates.', 'jooosi-social-image'),
                ['status' => 409],
            );
        }

        $configured = $this->configured();

        if (! isset($configured[$id])) {
            return $this->notFound();
        }

        $cache = $this->cache();
        unset($configured[$id], $cache[$id]);
        $this->save($configured, $cache);

        return $this->catalog();
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function bundled(): array|WP_Error
    {
        if ($this->bundled !== null) {
            return $this->bundled;
        }

        if (! is_readable($this->bundledFile)) {
            return new WP_Error('social_image_bundled_repository_missing', __('The bundled preset repository is missing or unreadable.', 'jooosi-social-image'));
        }

        $decoded = $this->decode((string) file_get_contents($this->bundledFile));

        if (is_wp_error($decoded)) {
            return $decoded;
        }

        $clean = PresetSchema::repository($decoded);

        if (! is_wp_error($clean)) {
            $this->bundled = $clean;
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $previous
     * @return array<string, mixed>|WP_Error
     */
    private function fetch(string $url, array $previous = []): array|WP_Error
    {
        $headers = ['Accept' => 'application/json'];

        if (! empty($previous['etag'])) {
            $headers['If-None-Match'] = (string) $previous['etag'];
        }
        if (! empty($previous['lastModified'])) {
            $headers['If-Modified-Since'] = (string) $previous['lastModified'];
        }

        $arguments = [
            'timeout' => 12,
            'redirection' => 3,
            'limit_response_size' => self::MAX_RESPONSE_BYTES,
            'headers' => $headers,
            'user-agent' => 'Jooosi-Social-Image/' . JOOOSI_SOCIAL_IMAGE_VERSION . '; ' . home_url('/'),
        ];
        $isLocalHttp = wp_parse_url($url, PHP_URL_SCHEME) === 'http' && $this->isTrustedLocalUrl($url);

        if ($isLocalHttp) {
            // Local-only HTTP bypasses WordPress's public-address check, so never follow redirects.
            $arguments['redirection'] = 0;
        }

        $response = $isLocalHttp
            ? wp_remote_get($url, $arguments)
            : wp_safe_remote_get($url, $arguments);

        if (is_wp_error($response)) {
            return new WP_Error('social_image_repository_fetch_failed', $response->get_error_message(), ['status' => 502]);
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 304 && isset($previous['repository']) && is_array($previous['repository'])) {
            $previous['syncedAt'] = gmdate('c');

            return $previous;
        }

        if ($code !== 200) {
            return new WP_Error(
                'social_image_repository_http_error',
                /* translators: %d: HTTP response status code. */
                sprintf(__('The preset repository returned HTTP %d.', 'jooosi-social-image'), $code),
                ['status' => 502],
            );
        }

        $body = wp_remote_retrieve_body($response);

        if (strlen($body) > self::MAX_RESPONSE_BYTES) {
            return new WP_Error('social_image_repository_too_large', __('The preset repository exceeds the 2 MB response limit.', 'jooosi-social-image'), ['status' => 413]);
        }

        $decoded = $this->decode($body);

        if (is_wp_error($decoded)) {
            return $decoded;
        }

        $repository = PresetSchema::repository($decoded);

        if (is_wp_error($repository)) {
            return $repository;
        }

        return [
            'repository' => $repository,
            'syncedAt' => gmdate('c'),
            'etag' => sanitize_text_field((string) wp_remote_retrieve_header($response, 'etag')),
            'lastModified' => sanitize_text_field((string) wp_remote_retrieve_header($response, 'last-modified')),
        ];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function decode(string $body): array|WP_Error
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            /* translators: %s: JSON parser error message. */
            return new WP_Error('social_image_repository_invalid_json', sprintf(__('The preset repository is not valid JSON: %s', 'jooosi-social-image'), $exception->getMessage()), ['status' => 422]);
        }

        return is_array($decoded)
            ? $decoded
            : new WP_Error('social_image_repository_invalid_json', __('The preset repository JSON root must be an object.', 'jooosi-social-image'), ['status' => 422]);
    }

    /**
     * @return string|WP_Error
     */
    private function validatedUrl(string $url): string|WP_Error
    {
        $url = esc_url_raw(trim($url), ['http', 'https']);
        $parts = $url === '' ? false : wp_parse_url($url);

        if (! is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return new WP_Error('social_image_repository_url_invalid', __('Enter a valid preset repository URL.', 'jooosi-social-image'), ['status' => 422]);
        }

        if ($parts['scheme'] !== 'https' && ! $this->isTrustedLocalUrl($url)) {
            return new WP_Error('social_image_repository_https_required', __('Preset repositories on external hosts must use HTTPS.', 'jooosi-social-image'), ['status' => 422]);
        }

        return $url;
    }

    private function isTrustedLocalUrl(string $url): bool
    {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $homeHost = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));

        if ($host !== '' && $host === $homeHost) {
            return true;
        }

        return in_array(wp_get_environment_type(), ['local', 'development'], true)
            && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function configured(): array
    {
        $value = get_option(self::OPTION_REPOSITORIES, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function cache(): array
    {
        $value = get_option(self::OPTION_CACHE, []);

        return is_array($value) ? $value : [];
    }

    private function save(array $configured, array $cache): void
    {
        update_option(self::OPTION_REPOSITORIES, $configured, false);
        update_option(self::OPTION_CACHE, $cache, false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decorate(array $repository, string $url, bool $bundled): array
    {
        return array_map(
            static function (array $preset) use ($repository, $url, $bundled): array {
                $preset['key'] = $repository['id'] . '/' . $preset['id'];
                $preset['source'] = [
                    'repositoryId' => $repository['id'],
                    'repositoryTitle' => $repository['title'],
                    'repositoryUrl' => $url,
                    'bundled' => $bundled,
                ];

                return $preset;
            },
            $repository['presets'],
        );
    }

    private function summary(array $repository, string $url, bool $bundled, bool $enabled, string $syncedAt, string $error): array
    {
        return [
            'id' => $repository['id'],
            'version' => $repository['version'],
            'title' => $repository['title'],
            'description' => $repository['description'],
            'homepage' => $repository['homepage'],
            'url' => $url,
            'bundled' => $bundled,
            'enabled' => $enabled,
            'presetCount' => count($repository['presets']),
            'updatedAt' => $repository['updatedAt'],
            'syncedAt' => $syncedAt,
            'status' => $error === '' ? 'ready' : 'stale',
            'error' => $error,
            'capabilities' => [
                'toggle' => true,
                'refresh' => ! $bundled,
                'delete' => ! $bundled,
            ],
        ];
    }

    private function missingSummary(array $configuration, string $error): array
    {
        return [
            'id' => (string) ($configuration['id'] ?? ''),
            'version' => '',
            'title' => (string) ($configuration['id'] ?? __('External repository', 'jooosi-social-image')),
            'description' => '',
            'homepage' => '',
            'url' => (string) ($configuration['url'] ?? ''),
            'bundled' => false,
            'enabled' => ! empty($configuration['enabled']),
            'presetCount' => 0,
            'updatedAt' => '',
            'syncedAt' => '',
            'status' => 'error',
            'error' => $error,
            'capabilities' => [
                'toggle' => true,
                'refresh' => true,
                'delete' => true,
            ],
        ];
    }

    private function notFound(): WP_Error
    {
        return new WP_Error('social_image_repository_not_found', __('Preset repository not found.', 'jooosi-social-image'), ['status' => 404]);
    }
}
