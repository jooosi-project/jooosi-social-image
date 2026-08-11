<?php

declare (strict_types=1);
namespace JooosiEgami\Bootstrap;

use JooosiEgami\Rendering\ImageGenerator;
/**
 * Main plugin entrypoint.
 *
 * @since 0.1.0
 */
final class Plugin
{
    private static ?self $instance = null;
    private ?\JooosiEgami\Bootstrap\Kernel $kernel = null;
    private function __construct(private \JooosiEgami\Bootstrap\Paths $paths)
    {
    }
    public static function boot(string $pluginFile): self
    {
        if (self::$instance instanceof self) {
            self::$instance->getKernel()->boot();
            return self::$instance;
        }
        self::$instance = new self(\JooosiEgami\Bootstrap\Paths::fromPluginFile($pluginFile));
        self::$instance->registerLifecycleHooks();
        self::$instance->getKernel()->boot();
        return self::$instance;
    }
    public static function instance(): self
    {
        if (!self::$instance instanceof self) {
            self::boot(\JOOOSI_EGAMI_PLUGIN_FILE);
        }
        return self::$instance;
    }
    public function registerLifecycleHooks(): void
    {
        register_activation_hook($this->paths->pluginFile, [$this, 'activate']);
        register_deactivation_hook($this->paths->pluginFile, [$this, 'deactivate']);
    }
    public function activate(): void
    {
        $this->getKernel()->activate();
    }
    public function deactivate(): void
    {
        $this->getKernel()->deactivate();
    }
    public function imageGenerator(): ImageGenerator
    {
        return $this->getKernel()->imageGenerator();
    }
    private function getKernel(): \JooosiEgami\Bootstrap\Kernel
    {
        if (!$this->kernel instanceof \JooosiEgami\Bootstrap\Kernel) {
            $this->kernel = new \JooosiEgami\Bootstrap\Kernel($this->paths);
        }
        return $this->kernel;
    }
}
