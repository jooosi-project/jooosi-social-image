<?php

declare(strict_types=1);

namespace JooosiEgami\Rendering;

use WP_Error;

defined('ABSPATH') || exit;

/**
 * Driver-neutral boundary for server-side image rendering.
 *
 * Dependency-specific objects must not cross this interface. This keeps the
 * plugin API stable when bundled dependencies are prefixed for distribution.
 *
 * @since 0.1.0
 */
interface RendererInterface
{
    public function available(): bool;

    public function driver(): string;

    /**
     * Returns an identity suitable for deterministic cache keys.
     */
    public function identity(): string;

    /**
     * @return array{
     *     available: bool,
     *     active_driver: string,
     *     library: string,
     *     library_version: string,
     *     driver_version: string,
     *     engine_version: string,
     *     formats: list<string>,
     *     extensions: array{imagick: bool, gd: bool, gmagick: bool},
     *     diagnostics: list<string>,
     *     svg: array<string, bool|string>|null
     * }
     */
    public function capabilities(): array;

    /**
     * @return list<string>
     */
    public function warnings(): array;

    /**
     * @return array{path: string, width: int, height: int, warnings: list<string>}|WP_Error
     */
    public function render(array $document, string $destination, string $format = 'png', int $quality = 90): array|WP_Error;
}
