<?php

declare(strict_types=1);

namespace JooosiEgami\Rendering;

use JooosiEgami\Integration\OmniIcon;

defined('ABSPATH') || exit;

final class SvgSupport
{
    public function __construct(
        private OmniIcon $icons,
        private SvgRasterizer $rasterizer,
    ) {
    }

    public function available(): bool
    {
        return $this->icons->available() && $this->rasterizer->available();
    }

    public function capabilities(): array
    {
        $capabilities = $this->rasterizer->capabilities();
        $capabilities['omni_icon'] = $this->icons->available();
        $capabilities['available'] = $capabilities['available'] && $capabilities['omni_icon'];

        if (! $capabilities['omni_icon']) {
            $capabilities['reason'] = __('Install and activate Omni Icon to use SVG elements.', 'jooosi-egami');
            $capabilities['notice'] = '';
        }

        return $capabilities;
    }
}
