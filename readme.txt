=== Jooosi Egami ===
Contributors: suasgn, suabahasa
Donate link: https://ko-fi.com/Q5Q75XSF7
Tags: open graph, featured image, social image, image generator, automation
Requires at least: 7.0
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Design dynamic Open Graph and featured images visually in WordPress and keep them in sync with your content automatically.

== Description ==

Jooosi Egami is a visual image design and generation plugin for WordPress. Create branded Open Graph, X/Twitter, and featured images directly in WP Admin with a familiar drag-and-drop editor.

Design once, connect the layout to live WordPress content, and choose exactly where it applies. Egami generates static, content-hashed files in the background and refreshes only the images affected by a content or design change.

### Features

* **Visual image editor:** Drag, resize, rotate, reorder, lock, and style text, image, SVG, and Shape layers.
* **Dynamic WordPress data:** Preview and insert post, author, taxonomy, metadata, ACF, JetEngine, Meta Box, and Toolset values.
* **Template library:** Start from 65 bundled Templates or add validated public Template repositories.
* **Flexible locations:** Build nested all/any rules over post properties, taxonomies, metadata, text, and dates.
* **Social and featured images:** Generate Open Graph, X/Twitter, and optional Media Library featured images from one Design.
* **Automatic regeneration:** Queue rendering through WP-Cron and invalidate only affected cache files.
* **Imagick or GD:** Prefer Imagick while retaining a GD with FreeType fallback.
* **Developer tools:** Use REST endpoints, WP-CLI, filters, a shortcode, or the namespaced PHP API.
* **Actionable diagnostics:** Check renderer, SVG, local-font, WP-Cron, and uploads failures in Egami settings or WordPress Site Health.

### Optional integrations

Install Omni Icon to add sanitized SVG icon layers. SVG rendering also requires PHP Imagick with SVG support. Install Yabe Webfont to expose its enabled local TTF/OTF font families inside Egami.

### External services

Egami includes Template previews that reference images hosted by Unsplash. Six showcase Templates retain an editable Unsplash image URL when applied. The browser requests an image when an administrator views the relevant Template; the server requests it when rendering a Design that still contains that URL. The request sends standard connection information such as the site server's IP address, browser headers for an admin preview, and the requested image URL. No WordPress post content or account credentials are sent to Unsplash.

Unsplash terms: https://unsplash.com/terms
Unsplash privacy policy: https://unsplash.com/privacy
Unsplash license: https://unsplash.com/license

Administrators may explicitly add a public Template repository URL. Egami requests that host to download its JSON manifest and sends a user-agent containing the plugin version and the site's public home URL. Administrators may also place a public HTTP(S) image URL in a Design; Egami requests that host when it needs to render the image. Data handling for those administrator-selected services is governed by each host's terms and privacy policy.

Egami does not send data to Omni Icon or Yabe Webfont. Those integrations read data from the corresponding WordPress plugins installed on the same site.

### Sponsors

Jooosi Egami is proudly supported by:

* [Jooosi](https://jooo.si) — open-source tools and products for WordPress.
* [LiveCanvas](https://livecanvas.com) — a visual site builder for WordPress.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/jooosi-egami`, or install the plugin through the WordPress Plugins screen.
2. Activate Jooosi Egami.
3. Open **Egami** in WordPress Admin.
4. Edit the starter Design or create one from a Template, choose its locations, and publish it.

== Frequently Asked Questions ==

= Which image library does Egami require? =

Egami uses Imagick when available. It can use GD instead when GD has FreeType support. SVG layers specifically require Imagick with SVG support.

= Does image rendering slow down frontend page loads? =

No. Egami renders through scheduled background events and stores static files. A frontend request can schedule a missing image for repair, but it does not wait for rendering.

= Which SEO plugins are supported? =

Egami integrates with Yoast SEO, Rank Math, All in One SEO, and SEOPress. When none is active, Egami can print its own Open Graph and X/Twitter image tags.

= Can developers register custom data? =

Yes. Filters register discoverable placeholder definitions and nested render-time values. Egami also provides a namespaced PHP API, REST endpoints, WP-CLI commands, and a shortcode.

= What happens when the plugin is uninstalled? =

Data is preserved by default. Enable **Delete plugin data on uninstall** in Egami settings before uninstalling to remove Designs, generated attachments, metadata, settings, caches, and generated files.

= How do I troubleshoot missing or delayed images? =

Open a Design, choose **More actions → Rendering settings**, and review System status. Administrators can also open **Tools → Site Health → Status** for separate renderer, SVG, font, filesystem, and background-generation checks. Egami records the last WP-Cron scheduling error and the last per-post generation error so host-specific failures are not silent.

== Changelog ==

= 1.0.0 - 2026-08-11 =

* 🐣 Initial release

[See changelog for all versions.](https://github.com/jooosi-project/jooosi-egami/blob/main/CHANGELOG.md)
