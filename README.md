<p align="center">
  <img src="./jooosi-egami.svg" alt="Jooosi Egami logo" width="100" />
</p>

<h1 align="center">Jooosi Egami</h1>

<p align="center">
  <i>Design dynamic Open Graph and featured images visually in WordPress, then keep them in sync with your content automatically.</i>
</p>

<p align="center">
  <a href="https://github.com/jooosi-project/jooosi-egami/releases">
    <picture>
      <img src="https://img.shields.io/github/v/release/jooosi-project/jooosi-egami.svg?logo=github" alt="GitHub Release" />
    </picture>
  </a>
  <a href="https://github.com/jooosi-project/jooosi-egami"><img src="https://img.shields.io/github/license/jooosi-project/jooosi-egami.svg" alt="GPL-3.0-or-later license" /></a>
  <a href="https://github.com/jooosi-project/jooosi-egami/actions"><img src="https://img.shields.io/github/actions/workflow/status/jooosi-project/jooosi-egami/ci.yaml?branch=main" alt="Build status" /></a>
  <br />
  <a aria-label="GitHub Sponsors" href="https://github.com/sponsors/suasgn">
    <picture>
      <img alt="GitHub Sponsors button" src="https://img.shields.io/github/sponsors/suasgn?logo=github" />
    </picture>
  </a>
  <a aria-label="Support me on Ko-fi" href="https://ko-fi.com/Q5Q75XSF7">
    <picture>
      <img alt="Ko-fi button" src="https://img.shields.io/badge/Buy_me_a_Coffee-ff5e5b?logo=ko-fi&label=Ko-fi" />
    </picture>
  </a>
  <a aria-label="Join our Facebook community" href="https://wind.press/go/facebook">
    <picture>
      <img alt="Facebook community button" src="https://img.shields.io/badge/Join_us-0866ff?logo=facebook&label=Community" />
    </picture>
  </a>
</p>

> [!NOTE]
>
> Jooosi Egami is an open-source WordPress plugin from [Jooosi](https://jooo.si).

## Overview

Jooosi Egami is a visual image design and generation plugin for WordPress. Create branded Open Graph, X/Twitter, and featured images directly in WP Admin, connect each layout to live WordPress content, and choose exactly where it applies. Egami generates static, content-hashed files in the background and refreshes only the images affected by a content or design change.

### Features

- 🎨 **Visual image editor:** Drag, resize, rotate, reorder, lock, and style text, image, SVG, and shape layers in the browser.
- 🧩 **Content-driven layouts:** Preview and insert post, author, taxonomy, metadata, ACF, JetEngine, Meta Box, and Toolset values.
- 🗂️ **Reusable templates:** Start from 65 bundled templates or add validated public template repositories.
- 🎯 **Flexible locations:** Target content with nested all/any rules across post properties, taxonomies, metadata, text, and dates.
- 🖼️ **Social and featured output:** Generate Open Graph, X/Twitter, and optional Media Library featured images from one design.
- 🔄 **Automatic regeneration:** Render through WP-Cron with content-derived cache keys and targeted invalidation.
- ⚙️ **Rendering choices:** Use Imagick when available or GD with FreeType as a fallback.
- 🧰 **Developer APIs:** Render through the namespaced PHP API, shortcode, filters, REST endpoints, or WP-CLI.
- 🩺 **Host diagnostics:** Inspect renderer, SVG, local-font, WP-Cron, and uploads failures in Egami settings or WordPress Site Health.

## Requirements

- WordPress 7.0 or later
- PHP 8.0 or later
- Imagick, or GD with FreeType support
- Optional SVG layers: [Omni Icon](https://wordpress.org/plugins/omni-icon/) and PHP Imagick
- Optional managed fonts: [Yabe Webfont](https://wordpress.org/plugins/yabe-webfont/) with local TTF/OTF files

## Installation

Install a release archive from the WordPress Plugins screen, activate Jooosi Egami, then open **WP Admin → Egami**.

## Development

The admin application uses React 19, Tailwind CSS 4, and Vite. PHP code is namespaced under `JooosiEgami\\` and release builds scope third-party dependencies under `JooosiEgamiDeps\\` to avoid conflicts with other WordPress plugins.

```bash
composer install
pnpm install
pnpm run dev
pnpm run typecheck
pnpm run check:presets
composer test
pnpm run test:e2e
```

Prepare the first release with `pnpm run release -- 1.0.0`, review the generated commit and tag, then push both to trigger the release workflow.

## Sponsors

Jooosi Egami is proudly supported by:

<p>
  <a href="https://jooo.si"><img src="./resources/icons/jooosi.svg" width="48" height="48" alt="Jooosi" /></a>
  &nbsp;
  <a href="https://livecanvas.com"><img src="./resources/icons/livecanvas.svg" width="57" height="48" alt="LiveCanvas" /></a>
</p>

- [Jooosi](https://jooo.si) — open-source tools and products for WordPress.
- [LiveCanvas](https://livecanvas.com) — a visual site builder for WordPress.

## External content

Some bundled Template previews use images hosted by Unsplash; six showcase Templates also retain an editable Unsplash URL when applied. Egami only retrieves a remote image when the admin views the relevant Template or the site renders a Design that contains that URL. See the [Unsplash License](https://unsplash.com/license) and [Privacy Policy](https://unsplash.com/privacy).

Administrators may add public Template repository URLs and image URLs from other hosts. Those requests are explicit, validated, and governed by the selected host's terms and privacy policy.
