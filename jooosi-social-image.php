<?php

/**
 * @wordpress-plugin
 * Plugin Name:         Jooosi Social Image
 * Plugin URI:          https://github.com/jooosi-project/jooosi-social-image
 * Description:         Design dynamic Open Graph and featured images visually in WordPress and keep them in sync automatically.
 * Text Domain:         jooosi-social-image
 * Version:             1.0.1
 * Requires at least:   7.0
 * Requires PHP:        8.0
 * Author:              Jooosi
 * Author URI:          https://jooo.si
 * License:             GPL-3.0-or-later
 *
 * @package             JooosiSocialImage
 */
declare (strict_types=1);
namespace JooosiSocialImageDeps;

\defined('ABSPATH') || exit;
if (\file_exists(__DIR__ . '/vendor/autoload.php')) {
    if (\file_exists(__DIR__ . '/vendor/scoper-autoload.php')) {
        require_once __DIR__ . '/vendor/scoper-autoload.php';
    } else {
        require_once __DIR__ . '/vendor/autoload.php';
    }
    require_once __DIR__ . '/constant.php';
    \JooosiSocialImage\Bootstrap\Plugin::boot(__FILE__);
}
