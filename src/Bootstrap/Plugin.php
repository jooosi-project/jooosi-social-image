<?php

declare (strict_types=1);
namespace JooosiSocialImage\Bootstrap;

use JooosiSocialImage\Rendering\ImageGenerator;
/**
 * Main plugin entrypoint.
 *
 * @since 0.1.0
 */
final class Plugin
{
    private static ?self $instance = null;
    private ?\JooosiSocialImage\Bootstrap\Kernel $kernel = null;
    private function __construct(private \JooosiSocialImage\Bootstrap\Paths $paths)
    {
    }
    public static function boot(string $pluginFile): self
    {
        if (self::$instance instanceof self) {
            self::$instance->getKernel()->boot();
            return self::$instance;
        }
        self::$instance = new self(\JooosiSocialImage\Bootstrap\Paths::fromPluginFile($pluginFile));
        self::$instance->registerLifecycleHooks();
        self::$instance->getKernel()->boot();
        return self::$instance;
    }
    public static function instance(): self
    {
        if (!self::$instance instanceof self) {
            self::boot(\JOOOSI_SOCIAL_IMAGE_PLUGIN_FILE);
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
    private function getKernel(): \JooosiSocialImage\Bootstrap\Kernel
    {
        if (!$this->kernel instanceof \JooosiSocialImage\Bootstrap\Kernel) {
            $this->kernel = new \JooosiSocialImage\Bootstrap\Kernel($this->paths);
        }
        return $this->kernel;
    }
}
