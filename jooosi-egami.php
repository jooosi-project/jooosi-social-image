<?php

/**
 * @wordpress-plugin
 * Plugin Name:         Jooosi Egami
 * Plugin URI:          https://github.com/jooosi-project/jooosi-egami
 * Description:         Design dynamic Open Graph and featured images visually in WordPress and keep them in sync automatically.
 * Text Domain:         jooosi-egami
 * Version:             1.0.0
 * Requires at least:   7.0
 * Requires PHP:        8.0
 * Author:              Jooosi
 * Author URI:          https://jooo.si
 * License:             GPL-3.0-or-later
 *
 * @package             JooosiEgami
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    if (file_exists(__DIR__ . '/vendor/scoper-autoload.php')) {
        require_once __DIR__ . '/vendor/scoper-autoload.php';
    } else {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    require_once __DIR__ . '/constant.php';

    JooosiEgami\Bootstrap\Plugin::boot(__FILE__);
}
