<?php

declare(strict_types=1);

namespace JooosiEgami\Integration;

use JooosiEgami\Api\Image;
use JooosiEgami\Rendering\ImageGenerator;

/**
 * Connects rendered images to WordPress output and popular SEO plugins.
 *
 * @since 0.1.0
 */
final class SocialImageIntegration
{
    public function registerHooks(): void
    {
        add_shortcode('egami', [$this, 'shortcode']);
        add_action('wp_head', [$this, 'renderStandaloneMeta'], 4);

        add_filter('wpseo_opengraph_image', [$this, 'filterOgUrl'], 99);
        add_filter('wpseo_opengraph_image_secure_url', [$this, 'filterOgUrl'], 99);
        add_filter('wpseo_twitter_image', [$this, 'filterTwitterUrl'], 99);
        add_filter('wpseo_opengraph_image_width', [$this, 'filterOgWidth'], 99);
        add_filter('wpseo_opengraph_image_height', [$this, 'filterOgHeight'], 99);

        add_filter('rank_math/opengraph/facebook/image', [$this, 'filterOgUrl'], 99);
        add_filter('rank_math/opengraph/twitter/image', [$this, 'filterTwitterUrl'], 99);

        add_filter('aioseo_facebook_tags', [$this, 'filterAioseoFacebook'], 99);
        add_filter('aioseo_twitter_tags', [$this, 'filterAioseoTwitter'], 99);

        add_filter('seopress_social_og_thumb', [$this, 'filterOgUrl'], 99);
        add_filter('seopress_social_twitter_card_thumb', [$this, 'filterTwitterUrl'], 99);
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function shortcode(array|string $attributes): string
    {
        $attributes = shortcode_atts([
            'id' => 0,
            'post_id' => 0,
            'key' => '',
            'data' => '',
            'class' => 'egami-image',
            'alt' => '',
        ], is_array($attributes) ? $attributes : [], 'egami');

        $templateId = absint($attributes['id']);

        if ($templateId === 0) {
            return '';
        }

        $data = [];

        if ((string) $attributes['data'] !== '') {
            $decoded = json_decode(html_entity_decode((string) $attributes['data'], ENT_QUOTES, 'UTF-8'), true);
            $data = is_array($decoded) ? $decoded : [];
        }

        return Image::html($templateId, absint($attributes['post_id']), [
            'key' => (string) $attributes['key'],
            'data' => $data,
            'class' => (string) $attributes['class'],
            'alt' => (string) $attributes['alt'],
        ]);
    }

    public function filterOgUrl(mixed $url): string
    {
        return $this->currentUrl('og') ?: (string) $url;
    }

    public function filterTwitterUrl(mixed $url): string
    {
        return $this->currentUrl('twitter') ?: ($this->currentUrl('og') ?: (string) $url);
    }

    public function filterOgWidth(mixed $width): mixed
    {
        $dimensions = $this->currentDimensions('og');

        return $dimensions === null ? $width : $dimensions[0];
    }

    public function filterOgHeight(mixed $height): mixed
    {
        $dimensions = $this->currentDimensions('og');

        return $dimensions === null ? $height : $dimensions[1];
    }

    /**
     * @param array<string|int, mixed> $tags
     * @return array<string|int, mixed>
     */
    public function filterAioseoFacebook(array $tags): array
    {
        return $this->replaceTag($tags, 'og:image', $this->currentUrl('og'));
    }

    /**
     * @param array<string|int, mixed> $tags
     * @return array<string|int, mixed>
     */
    public function filterAioseoTwitter(array $tags): array
    {
        return $this->replaceTag($tags, 'twitter:image', $this->currentUrl('twitter') ?: $this->currentUrl('og'));
    }

    public function renderStandaloneMeta(): void
    {
        if (! is_singular() || $this->supportedSeoProviderIsActive()) {
            return;
        }

        $ogUrl = $this->currentUrl('og');
        $twitterUrl = $this->currentUrl('twitter') ?: $ogUrl;

        if ($ogUrl !== '') {
            $dimensions = $this->currentDimensions('og');
            echo '<meta property="og:image" content="' . esc_url($ogUrl) . '" />' . "\n";

            if ($dimensions !== null) {
                echo '<meta property="og:image:width" content="' . esc_attr((string) $dimensions[0]) . '" />' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr((string) $dimensions[1]) . '" />' . "\n";
            }
        }

        if ($twitterUrl !== '') {
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url($twitterUrl) . '" />' . "\n";
        }
    }

    private function currentUrl(string $output): string
    {
        if (! is_singular()) {
            return '';
        }

        $metaKey = $output === 'twitter' ? ImageGenerator::META_TWITTER : ImageGenerator::META_OG;

        return esc_url_raw((string) get_post_meta(get_queried_object_id(), $metaKey, true));
    }

    /**
     * @return array{int, int}|null
     */
    private function currentDimensions(string $output): ?array
    {
        if (! is_singular()) {
            return null;
        }

        $map = get_post_meta(get_queried_object_id(), ImageGenerator::META_MAP, true);

        if (
            ! is_array($map)
            || ! isset($map[$output])
            || ! is_array($map[$output])
            || empty($map[$output]['width'])
            || empty($map[$output]['height'])
        ) {
            return null;
        }

        return [(int) $map[$output]['width'], (int) $map[$output]['height']];
    }

    /**
     * @param array<string|int, mixed> $tags
     * @return array<string|int, mixed>
     */
    private function replaceTag(array $tags, string $name, string $url): array
    {
        if ($url === '') {
            return $tags;
        }

        $found = false;

        if (array_key_exists($name, $tags)) {
            $tags[$name] = $url;
            $found = true;
        }

        foreach ($tags as &$tag) {
            if (! is_array($tag)) {
                continue;
            }

            $key = $tag['property'] ?? ($tag['name'] ?? '');

            if ($key === $name) {
                $tag['content'] = $url;
                $found = true;
            }
        }

        unset($tag);

        if (! $found) {
            $tags[$name] = $url;
        }

        return $tags;
    }

    private function supportedSeoProviderIsActive(): bool
    {
        return defined('WPSEO_VERSION')
            || defined('RANK_MATH_VERSION')
            || defined('AIOSEO_VERSION')
            || defined('SEOPRESS_VERSION');
    }
}
