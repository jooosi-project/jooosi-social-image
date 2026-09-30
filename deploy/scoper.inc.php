<?php

declare(strict_types=1);

$excludesDirectory = __DIR__ . '/php-scoper-wordpress-excludes-master/generated';
$loadExcludes = static function (string $filename) use ($excludesDirectory): array {
    $path = $excludesDirectory . '/' . $filename;

    if (! is_readable($path)) {
        throw new RuntimeException(sprintf(
            'Missing PHP-Scoper WordPress excludes file: %s',
            $path,
        ));
    }

    $contents = file_get_contents($path);

    if (! is_string($contents)) {
        throw new RuntimeException(sprintf('Could not read PHP-Scoper excludes file: %s', $path));
    }

    $symbols = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    return is_array($symbols) ? array_values(array_filter($symbols, 'is_string')) : [];
};

$wpClasses = $loadExcludes('exclude-wordpress-classes.json');
$wpFunctions = $loadExcludes('exclude-wordpress-functions.json');
$wpConstants = $loadExcludes('exclude-wordpress-constants.json');

return [
    'prefix' => 'JooosiSocialImageDeps',

    // Social Image and optional cross-plugin APIs remain stable. Composer dependencies
    // such as Imagine are absent and are therefore scoped.
    'exclude-namespaces' => [
        'JooosiSocialImage',
        'JooosiIcon',
        'JooosiFon',
        'WP_CLI',
    ],

    'exclude-classes' => array_merge($wpClasses, [
        'WP_CLI',
        'WP_CLI_Command',
    ]),

    'exclude-functions' => $wpFunctions,

    'exclude-constants' => array_merge($wpConstants, [
        '/^JOOOSI_SOCIAL_IMAGE_[\p{L}\d_]+$/',
        'ABSPATH',
        'WP_DEBUG',
        'WP_PLUGIN_DIR',
        'WP_PLUGIN_URL',
        'WP_CONTENT_DIR',
        'WP_CONTENT_URL',
    ]),

    'expose-global-constants' => false,
    'expose-global-classes' => false,
    'expose-global-functions' => false,
    'expose-namespaces' => [],
    'expose-classes' => [],
    'expose-functions' => [],
    'expose-constants' => [
        '/^JOOOSI_SOCIAL_IMAGE_[\p{L}\d_]+$/',
    ],
];
