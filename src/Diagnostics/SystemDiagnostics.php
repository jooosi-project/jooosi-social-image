<?php

declare (strict_types=1);
namespace JooosiSocialImage\Diagnostics;

use JooosiSocialImage\Integration\JooosiFon;
use JooosiSocialImage\Rendering\FontLocator;
use JooosiSocialImage\Rendering\ImageGenerator;
use JooosiSocialImage\Rendering\RendererInterface;
use JooosiSocialImage\Rendering\SvgSupport;
defined('ABSPATH') || exit;
/**
 * Builds one sanitized diagnostics payload for REST, Site Health, and notices.
 */
final class SystemDiagnostics
{
    private ?array $cached = null;
    public function __construct(private RendererInterface $renderer, private SvgSupport $svgSupport, private JooosiFon $fon, private FontLocator $fonts)
    {
    }
    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        if (is_array($this->cached)) {
            return $this->cached;
        }
        $renderer = $this->renderer->capabilities();
        return $this->cached = ['version' => \JOOOSI_SOCIAL_IMAGE_VERSION, 'renderer' => $renderer, 'svg' => $this->svgSupport->capabilities(), 'webfont' => $this->fon->capabilities(), 'fonts' => $this->fonts->capabilities(), 'filesystem' => $this->filesystem(), 'cron' => $this->cron()];
    }
    /**
     * @return array{ready: bool, uploads_available: bool, directory_exists: bool, writable: bool, reason: string}
     */
    private function filesystem(): array
    {
        $uploads = wp_upload_dir();
        $uploadsAvailable = empty($uploads['error']) && !empty($uploads['basedir']);
        $directory = $uploadsAvailable ? trailingslashit((string) $uploads['basedir']) . 'social-image' : '';
        $directoryExists = $directory !== '' && is_dir($directory);
        $writable = $uploadsAvailable && ($directoryExists && wp_is_writable($directory) || !$directoryExists && wp_is_writable((string) $uploads['basedir']));
        if (!$uploadsAvailable) {
            $reason = !empty($uploads['error']) ? sanitize_text_field((string) $uploads['error']) : __('WordPress did not provide an uploads directory.', 'jooosi-social-image');
        } elseif (!$writable) {
            $reason = $directoryExists ? __('The wp-content/uploads/social-image directory is not writable by PHP. Check filesystem ownership and permissions.', 'jooosi-social-image') : __('The WordPress uploads directory is not writable, so Social Image cannot create its cache directory. Check filesystem ownership and permissions.', 'jooosi-social-image');
        } else {
            $reason = '';
        }
        return ['ready' => $uploadsAvailable && $writable, 'uploads_available' => $uploadsAvailable, 'directory_exists' => $directoryExists, 'writable' => $writable, 'reason' => $reason];
    }
    /**
     * @return array{ready: bool, disabled: bool, alternate: bool, last_error: array<string, mixed>|null, reason: string}
     */
    private function cron(): array
    {
        $disabled = defined('JooosiSocialImageDeps\DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $alternate = defined('JooosiSocialImageDeps\ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON;
        $lastError = get_option(ImageGenerator::OPTION_CRON_ERROR, null);
        $lastError = is_array($lastError) && !empty($lastError['message']) ? $lastError : null;
        if ($disabled) {
            $reason = __('Automatic WP-Cron spawning is disabled. Confirm that the host calls wp-cron.php from a real system cron, otherwise queued images will not be generated.', 'jooosi-social-image');
        } elseif (is_array($lastError)) {
            /* translators: %s: Last WordPress cron scheduling error message. */
            $reason = sprintf(__('The last image-generation event could not be scheduled: %s', 'jooosi-social-image'), sanitize_text_field((string) $lastError['message']));
        } elseif ($alternate) {
            $reason = __('ALTERNATE_WP_CRON is enabled. Verify redirects and loopback requests on this host if queued generation stalls.', 'jooosi-social-image');
        } else {
            $reason = '';
        }
        return ['ready' => !$disabled && !is_array($lastError), 'disabled' => $disabled, 'alternate' => $alternate, 'last_error' => $lastError, 'reason' => $reason];
    }
}
