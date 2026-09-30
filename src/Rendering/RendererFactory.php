<?php

declare(strict_types=1);

namespace JooosiSocialImage\Rendering;

defined('ABSPATH') || exit;

/**
 * Creates Social Image's renderer without exposing dependency-specific objects.
 *
 * @since 0.1.0
 */
final class RendererFactory
{
    public static function create(?SvgRasterizer $svgRasterizer = null): RendererInterface
    {
        $preferredDriver = apply_filters('jooosi-social-image/rendering:driver', 'auto');
        $renderer = ImagineRenderer::create(is_string($preferredDriver) ? $preferredDriver : 'auto', $svgRasterizer);
        $filtered = apply_filters('jooosi-social-image/rendering:renderer', $renderer, $preferredDriver);

        return $filtered instanceof RendererInterface ? $filtered : $renderer;
    }
}
