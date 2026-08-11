<?php

declare (strict_types=1);
namespace JooosiEgamiDeps;

\defined('ABSPATH') || exit;
\define('JOOOSI_EGAMI_VERSION', '1.0.0');
\define('JOOOSI_EGAMI_PLUGIN_FILE', __DIR__ . '/jooosi-egami.php');
\define('JOOOSI_EGAMI_PLUGIN_BASENAME', \plugin_basename(\JOOOSI_EGAMI_PLUGIN_FILE));
\define('JOOOSI_EGAMI_PLUGIN_DIR', __DIR__);
\define('JOOOSI_EGAMI_PLUGIN_URL', \plugin_dir_url(\JOOOSI_EGAMI_PLUGIN_FILE));
