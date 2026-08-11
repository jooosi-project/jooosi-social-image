<?php

declare (strict_types=1);
namespace JooosiEgami\Rendering;

defined('ABSPATH') || exit;
/**
 * Creates Egami's renderer without exposing dependency-specific objects.
 *
 * @since 0.1.0
 */
final class RendererFactory
{
    public static function create(?\JooosiEgami\Rendering\SvgRasterizer $svgRasterizer = null): \JooosiEgami\Rendering\RendererInterface
    {
        $preferredDriver = apply_filters('jooosi-egami/rendering:driver', 'auto');
        $renderer = \JooosiEgami\Rendering\ImagineRenderer::create(is_string($preferredDriver) ? $preferredDriver : 'auto', $svgRasterizer);
        $filtered = apply_filters('jooosi-egami/rendering:renderer', $renderer, $preferredDriver);
        return $filtered instanceof \JooosiEgami\Rendering\RendererInterface ? $filtered : $renderer;
    }
}
