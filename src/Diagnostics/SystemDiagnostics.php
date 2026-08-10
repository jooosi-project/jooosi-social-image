<?php

declare(strict_types=1);

namespace JooosiEgami\Diagnostics;

use JooosiEgami\Integration\YabeWebfont;
use JooosiEgami\Rendering\FontLocator;
use JooosiEgami\Rendering\ImageGenerator;
use JooosiEgami\Rendering\RendererInterface;
use JooosiEgami\Rendering\SvgSupport;

defined('ABSPATH') || exit;

/**
 * Builds one sanitized diagnostics payload for REST, Site Health, and notices.
 */
final class SystemDiagnostics
{
    private ?array $cached = null;

    public function __construct(
        private RendererInterface $renderer,
        private SvgSupport $svgSupport,
        private YabeWebfont $webfonts,
        private FontLocator $fonts,
    ) {
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

        return $this->cached = [
            'version' => JOOOSI_EGAMI_VERSION,
            'renderer' => $renderer,
            'svg' => $this->svgSupport->capabilities(),
            'webfont' => $this->webfonts->capabilities(),
            'fonts' => $this->fonts->capabilities(),
            'filesystem' => $this->filesystem(),
            'cron' => $this->cron(),
        ];
    }

    /**
     * @return array{ready: bool, uploads_available: bool, directory_exists: bool, writable: bool, reason: string}
     */
    private function filesystem(): array
    {
        $uploads = wp_upload_dir();
        $uploadsAvailable = empty($uploads['error']) && ! empty($uploads['basedir']);
        $directory = $uploadsAvailable ? trailingslashit((string) $uploads['basedir']) . 'egami' : '';
        $directoryExists = $directory !== '' && is_dir($directory);
        $writable = $uploadsAvailable && (
            ($directoryExists && wp_is_writable($directory))
            || (! $directoryExists && wp_is_writable((string) $uploads['basedir']))
        );

        if (! $uploadsAvailable) {
            $reason = ! empty($uploads['error'])
                ? sanitize_text_field((string) $uploads['error'])
                : __('WordPress did not provide an uploads directory.', 'jooosi-egami');
        } elseif (! $writable) {
            $reason = $directoryExists
                ? __('The wp-content/uploads/egami directory is not writable by PHP. Check filesystem ownership and permissions.', 'jooosi-egami')
                : __('The WordPress uploads directory is not writable, so Egami cannot create its cache directory. Check filesystem ownership and permissions.', 'jooosi-egami');
        } else {
            $reason = '';
        }

        return [
            'ready' => $uploadsAvailable && $writable,
            'uploads_available' => $uploadsAvailable,
            'directory_exists' => $directoryExists,
            'writable' => $writable,
            'reason' => $reason,
        ];
    }

    /**
     * @return array{ready: bool, disabled: bool, alternate: bool, last_error: array<string, mixed>|null, reason: string}
     */
    private function cron(): array
    {
        $disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $alternate = defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON;
        $lastError = get_option(ImageGenerator::OPTION_CRON_ERROR, null);
        $lastError = is_array($lastError) && ! empty($lastError['message']) ? $lastError : null;

        if ($disabled) {
            $reason = __('Automatic WP-Cron spawning is disabled. Confirm that the host calls wp-cron.php from a real system cron, otherwise queued images will not be generated.', 'jooosi-egami');
        } elseif (is_array($lastError)) {
            /* translators: %s: Last WordPress cron scheduling error message. */
            $reason = sprintf(__('The last image-generation event could not be scheduled: %s', 'jooosi-egami'), sanitize_text_field((string) $lastError['message']));
        } elseif ($alternate) {
            $reason = __('ALTERNATE_WP_CRON is enabled. Verify redirects and loopback requests on this host if queued generation stalls.', 'jooosi-egami');
        } else {
            $reason = '';
        }

        return [
            'ready' => ! $disabled && ! is_array($lastError),
            'disabled' => $disabled,
            'alternate' => $alternate,
            'last_error' => $lastError,
            'reason' => $reason,
        ];
    }
}
