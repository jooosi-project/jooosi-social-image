<?php

declare(strict_types=1);

namespace JooosiSocialImage\Rendering;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Downloads public external images through WordPress's SSRF-safe HTTP client.
 */
final class RemoteImageFetcher
{
	private const MAX_BYTES = 10485760;
	private const CACHE_TTL = 21600;

	/**
	 * @return array{path: string, hash: string}|WP_Error
	 */
	public function fetch(string $url): array|WP_Error {
		$url = esc_url_raw( trim( $url ), array( 'http', 'https' ) );

		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'social_image_remote_image_url', __( 'The external image URL is not a safe public HTTP or HTTPS address.', 'jooosi-social-image' ) );
		}

		$directory = $this->cacheDirectory();
		if ( $directory instanceof WP_Error ) {
			return $directory;
		}

		$key = hash( 'sha256', $url );
		$cached = $this->cachedFile( $directory, $key );
		if ( '' !== $cached && filemtime( $cached ) >= time() - self::CACHE_TTL ) {
			return array( 'path' => $cached, 'hash' => hash_file( 'sha256', $cached ) ?: $key );
		}

		$response = wp_safe_remote_get( $url, array(
			'timeout'             => 10,
			'redirection'         => 3,
			'reject_unsafe_urls'  => true,
			'limit_response_size' => self::MAX_BYTES,
			'user-agent'          => 'Jooosi-Social-Image/' . JOOOSI_SOCIAL_IMAGE_VERSION . '; ' . home_url( '/' ),
		) );

		if ( is_wp_error( $response ) ) {
			return '' !== $cached
				? array( 'path' => $cached, 'hash' => hash_file( 'sha256', $cached ) ?: $key )
				: new WP_Error( 'social_image_remote_image_request', $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $status < 200 || $status >= 300 || ! is_string( $body ) || '' === $body ) {
			return new WP_Error( 'social_image_remote_image_response', __( 'The external image server did not return a usable image.', 'jooosi-social-image' ) );
		}

		if ( strlen( $body ) > self::MAX_BYTES ) {
			return new WP_Error( 'social_image_remote_image_size', __( 'The external image is larger than the 10 MB limit.', 'jooosi-social-image' ) );
		}

		$info = @getimagesizefromstring( $body );
		$extensions = array(
			IMAGETYPE_JPEG => 'jpg',
			IMAGETYPE_PNG  => 'png',
			IMAGETYPE_GIF  => 'gif',
			IMAGETYPE_WEBP => 'webp',
		);
		$type = is_array( $info ) ? (int) ( $info[2] ?? 0 ) : 0;

		if ( ! isset( $extensions[ $type ] ) ) {
			return new WP_Error( 'social_image_remote_image_type', __( 'The external URL must return a JPEG, PNG, GIF, or WebP image.', 'jooosi-social-image' ) );
		}

		$image_width = (int) ( $info[0] ?? 0 );
		$image_height = (int) ( $info[1] ?? 0 );
		if ( $image_width < 1 || $image_height < 1 || $image_width > 12000 || $image_height > 12000 || $image_width * $image_height > 100000000 ) {
			return new WP_Error( 'social_image_remote_image_dimensions', __( 'The external image dimensions exceed the safe rendering limit.', 'jooosi-social-image' ) );
		}

		$temporary = TemporaryFile::create( $directory );
		if ( null === $temporary || false === file_put_contents( $temporary, $body, LOCK_EX ) ) {
			return new WP_Error( 'social_image_remote_image_write', __( 'The external image cache could not be written.', 'jooosi-social-image' ) );
		}

		$destination = trailingslashit( $directory ) . $key . '.' . $extensions[ $type ];
		if ( ! copy( $temporary, $destination ) ) {
			wp_delete_file( $temporary );

			return new WP_Error( 'social_image_remote_image_move', __( 'The external image could not be moved into the cache.', 'jooosi-social-image' ) );
		}
		wp_delete_file( $temporary );

		foreach ( (array) glob( trailingslashit( $directory ) . $key . '.*' ) as $file ) {
			if ( $file !== $destination && is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}

		$this->prune( $directory );

		return array( 'path' => $destination, 'hash' => hash( 'sha256', $body ) );
	}

	private function cacheDirectory(): string|WP_Error {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'social_image_remote_image_uploads', (string) $uploads['error'] );
		}

		$directory = trailingslashit( (string) $uploads['basedir'] ) . 'social-image/remote-images';

		return wp_mkdir_p( $directory ) && wp_is_writable( $directory )
			? $directory
			: new WP_Error( 'social_image_remote_image_directory', __( 'The external image cache is not writable.', 'jooosi-social-image' ) );
	}

	private function cachedFile(string $directory, string $key): string {
		foreach ( (array) glob( trailingslashit( $directory ) . $key . '.*' ) as $file ) {
			if ( is_file( $file ) && is_readable( $file ) && filesize( $file ) > 0 ) {
				return $file;
			}
		}

		return '';
	}

	private function prune(string $directory): void {
		$cutoff = time() - 7 * DAY_IN_SECONDS;
		foreach ( (array) glob( trailingslashit( $directory ) . '*' ) as $file ) {
			if ( is_file( $file ) && filemtime( $file ) < $cutoff ) {
				wp_delete_file( $file );
			}
		}
	}
}
