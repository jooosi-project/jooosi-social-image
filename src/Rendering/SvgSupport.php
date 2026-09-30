<?php

declare(strict_types=1);

namespace JooosiSocialImage\Rendering;

use JooosiSocialImage\Integration\JooosiIcon;

defined('ABSPATH') || exit;

final class SvgSupport
{
    public function __construct(
        private JooosiIcon $icons,
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
        $capabilities['jooosi_icon'] = $this->icons->available();
        $capabilities['available'] = $capabilities['available'] && $capabilities['jooosi_icon'];

        if (! $capabilities['jooosi_icon']) {
            $capabilities['reason'] = __('Install and activate Jooosi Icon to use SVG elements.', 'jooosi-social-image');
            $capabilities['notice'] = '';
        }

        return $capabilities;
    }
}
