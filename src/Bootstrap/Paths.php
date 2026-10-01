<?php

declare (strict_types=1);
namespace JooosiSocialImage\Bootstrap;

/**
 * Runtime filesystem paths.
 *
 * @since 0.1.0
 */
final class Paths
{
    public function __construct(public string $pluginFile, public string $rootDir, public string $srcDir)
    {
    }
    public static function fromPluginFile(string $pluginFile): self
    {
        $rootDir = dirname($pluginFile);
        return new self(pluginFile: $pluginFile, rootDir: $rootDir, srcDir: $rootDir . '/src');
    }
}
