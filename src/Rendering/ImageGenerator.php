<?php

declare(strict_types=1);

namespace JooosiSocialImage\Rendering;

use FilesystemIterator;
use JooosiSocialImage\Assignment\TemplateMatcher;
use JooosiSocialImage\Content\DynamicDataResolver;
use JooosiSocialImage\Integration\JooosiIcon;
use JooosiSocialImage\Settings\PluginSettings;
use JooosiSocialImage\Template\TemplateRepository;
use JooosiSocialImage\Template\TemplateSchema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class ImageGenerator
{
	public const META_OG = '_social_image_og_image_url';
	public const META_TWITTER = '_social_image_twitter_image_url';
	public const META_FEATURED = '_social_image_featured_image_url';
	public const META_MAP = '_social_image_render_map';
	public const META_ERROR = '_social_image_last_error';
	public const OPTION_CRON_ERROR = 'social_image_last_cron_error';

	private bool $rendering = false;

	public function __construct(
		private TemplateRepository $repository,
		private TemplateMatcher $matcher,
		private DynamicDataResolver $resolver,
		private RendererInterface $renderer,
		private FontLocator $fonts,
		private RemoteImageFetcher $remoteImages,
		private ?JooosiIcon $icons = null,
	) {
	}

	public function registerHooks(): void {
		add_action( 'jooosi-social-image/rendering:render_post', array( $this, 'runScheduled' ), 10, 2 );
	}

	public function schedulePost(int $post_id, bool $force = false, int $delay = 0): bool|WP_Error {
		$args = array( absint( $post_id ), (bool) $force );
		if ( wp_next_scheduled( 'jooosi-social-image/rendering:render_post', $args ) ) {
			delete_option( self::OPTION_CRON_ERROR );
			return true;
		}

		$result = wp_schedule_single_event( time() + max( 0, (int) $delay ), 'jooosi-social-image/rendering:render_post', $args, true );

		if ( false === $result ) {
			$result = new WP_Error( 'social_image_cron_schedule_failed', __( 'WordPress did not accept the background image-generation event.', 'jooosi-social-image' ) );
		}

		if ( is_wp_error( $result ) ) {
			update_option(
				self::OPTION_CRON_ERROR,
				array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'time' => time() ),
				false,
			);

			return $result;
		}

		delete_option( self::OPTION_CRON_ERROR );

		return true;
	}

	public function runScheduled(int $post_id, bool $force = false): void {
		$result = $this->generateForPost( absint( $post_id ), (bool) $force );
		if ( is_wp_error( $result ) ) {
			update_post_meta(
				$post_id,
				self::META_ERROR,
				array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'time' => time() )
			);
		} else {
			delete_post_meta( $post_id, self::META_ERROR );
		}
	}

	public function generateForPost(int $post_id, bool $force = false): array|WP_Error {
		$matches = $this->matcher->forPost( $post_id );
		if ( ! $matches ) {
			return new WP_Error( 'social_image_no_match', __( 'No published design matches this post.', 'jooosi-social-image' ) );
		}
		$results = array();
		foreach ( $this->generationPlan( $matches ) as $target ) {
			$result = $this->generate( $target['template']['id'], $post_id, $target['output'], array(), $force );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$results[ $target['output'] ] = $result;

			foreach ( $target['aliases'] as $alias => $template ) {
				$alias_result = array_merge( $result, array( 'output' => $alias ) );
				$updated = $this->persistResult( $post_id, $template, $alias_result );
				if ( is_wp_error( $updated ) ) {
					return $updated;
				}
				$results[ $alias ] = array_merge( $alias_result, $updated );
			}
		}
		return $results;
	}

	public function generate(int $template_id, int $post_id = 0, string $output = 'manual', array $overrides = [], bool $force = false, string $key = ''): array|WP_Error {
		if ( ! in_array( $output, array( 'og', 'twitter', 'featured', 'manual' ), true ) ) {
			$output = 'manual';
		}
		$template = $this->repository->get( $template_id );
		if ( is_wp_error( $template ) ) {
			return $template;
		}
		if ( $post_id && ! get_post( $post_id ) ) {
			return new WP_Error( 'social_image_post_not_found', __( 'The render post was not found.', 'jooosi-social-image' ) );
		}

		$data     = $this->resolver->resolve( $post_id, $overrides );
		$document = $this->hydrateDocument( $template['document'], $data );
		$format   = (string) PluginSettings::get( 'format', 'png' );
		$quality  = (int) PluginSettings::get( 'quality', 90 );
		$artifact_output = in_array( $output, array( 'og', 'twitter' ), true ) ? 'social' : $output;
		$hash     = hash(
			'sha256',
			wp_json_encode(
				array(
					'document' => $document,
					'data'     => $data,
					'revision' => $template['revision'],
					'output'   => $artifact_output,
					'key'      => sanitize_key( $key ),
					'format'   => $format,
					'quality'  => $quality,
					'renderer' => $this->renderer->identity(),
				)
			)
		);
		$locations = $this->locations();
		if ( is_wp_error( $locations ) ) {
			return $locations;
		}
		$extension = 'jpeg' === $format ? 'jpg' : $format;
		$name = sprintf( '%d-%d-%s-%s.%s', $template['id'], (int) $post_id, sanitize_key( $artifact_output . '-' . $key ), substr( $hash, 0, 16 ), $extension );
		$path = trailingslashit( $locations['path'] ) . $name;
		$url  = trailingslashit( $locations['url'] ) . $name;
		$cached = ! $force && is_file( $path ) && filesize( $path ) > 0;

		if ( ! $cached ) {
			$tmp = TemporaryFile::create( $locations['path'] );
			if ( ! $tmp ) {
				return new WP_Error( 'social_image_temp_failed', __( 'A temporary render file could not be created.', 'jooosi-social-image' ) );
			}
			$result = $this->renderer->render( $document, $tmp, $format, $quality );
			if ( is_wp_error( $result ) ) {
				wp_delete_file( $tmp );
				return $result;
			}
			if ( ! copy( $tmp, $path ) ) {
				wp_delete_file( $tmp );
				return new WP_Error( 'social_image_move_failed', __( 'The completed render could not be moved into uploads.', 'jooosi-social-image' ) );
			}
			wp_delete_file( $tmp );
		}

		$result = array(
			'template_id' => (int) $template_id,
			'post_id'     => (int) $post_id,
			'output'      => $output,
			'hash'        => $hash,
			'path'        => $path,
			'url'         => $url,
			'width'       => (int) $document['width'],
			'height'      => (int) $document['height'],
			'format'      => $format,
			'cached'      => $cached,
			'warnings'    => $cached ? array() : $this->renderer->warnings(),
		);

		if ( $post_id && in_array( $output, array( 'og', 'twitter', 'featured' ), true ) ) {
			$updated = $this->persistResult( $post_id, $template, $result );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
			$result = array_merge( $result, $updated );
		}
		do_action( 'jooosi-social-image/rendering:rendered', $result, $template, $data );
		return $result;
	}

	public function preview(array $document, int $post_id = 0): array|WP_Error {
		$document = TemplateSchema::normalizeDocument( $document );
		$data = $this->resolver->resolve( $post_id );
		$document = $this->hydrateDocument( $document, $data );
		$locations = $this->locations( 'previews' );
		if ( is_wp_error( $locations ) ) {
			return $locations;
		}
		$hash = substr( hash( 'sha256', wp_json_encode( array( $document, $data, $this->renderer->identity() ) ) ), 0, 20 );
		$name = sprintf( '%d-%s.png', get_current_user_id(), $hash );
		$path = trailingslashit( $locations['path'] ) . $name;
		$url = trailingslashit( $locations['url'] ) . $name;
		$this->prunePreviewCache( $locations['path'] );
		$cached = is_file( $path );
		if ( ! $cached ) {
			$result = $this->renderer->render( $document, $path, 'png', 90 );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return array( 'url' => add_query_arg( 'v', $hash, $url ), 'path' => $path, 'width' => $document['width'], 'height' => $document['height'], 'warnings' => $cached ? array() : $this->renderer->warnings() );
	}

	public function warm(int $template_id = 0, string $post_type = '', bool $force = true): array {
		$args = array(
			'post_type'      => $post_type ? sanitize_key( $post_type ) : get_post_types( array( 'public' => true ), 'names' ),
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);
		$ids = get_posts( $args );
		$scheduled = 0;
		foreach ( $ids as $index => $post_id ) {
			$post_id = (int) $post_id;
			$matches = $this->matcher->forPost( $post_id );
			if ( $template_id && ! in_array( (int) $template_id, array_map( static function ( $item ) { return (int) $item['id']; }, $matches ), true ) ) {
				continue;
			}
			if ( true === $this->schedulePost( $post_id, $force, 1 + min( 300, $index ) ) ) {
				$scheduled++;
			}
		}
		return array( 'scheduled' => $scheduled );
	}

	public function flushCache(int $template_id = 0): array|WP_Error {
		$locations = $this->locations();
		if ( is_wp_error( $locations ) ) {
			return $locations;
		}
		$deleted = 0;
		if ( $template_id > 0 ) {
			$pattern = trailingslashit( $locations['path'] ) . absint( $template_id ) . '-*';
			foreach ( (array) glob( $pattern ) as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
					$deleted++;
				}
			}
		} else {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $locations['path'], FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $item ) {
				if ( $item->isFile() ) {
					wp_delete_file( $item->getPathname() );
					$deleted++;
				}
			}
		}
		return array( 'deleted' => $deleted );
	}

	private function prunePreviewCache(string $directory): void {
		$cutoff = time() - 24 * 60 * 60;
		foreach ( (array) glob( trailingslashit( $directory ) . '*' ) as $file ) {
			if ( is_file( $file ) && filemtime( $file ) < $cutoff ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Collapse the paired social outputs into one physical render target.
	 *
	 * @param array<string, array<string, mixed>> $matches
	 * @return list<array{output: string, template: array<string, mixed>, aliases: array<string, array<string, mixed>>}>
	 */
	private function generationPlan(array $matches): array {
		$plan = array();

		if (
			isset( $matches['og'], $matches['twitter'] )
			&& (int) $matches['og']['id'] === (int) $matches['twitter']['id']
		) {
			$plan[] = array(
				'output'   => 'og',
				'template' => $matches['og'],
				'aliases'  => array( 'twitter' => $matches['twitter'] ),
			);
			unset( $matches['og'], $matches['twitter'] );
		}

		foreach ( $matches as $output => $template ) {
			$plan[] = array(
				'output'   => $output,
				'template' => $template,
				'aliases'  => array(),
			);
		}

		return $plan;
	}

	private function hydrateDocument(array $document, array $data): array {
		$document = TemplateSchema::normalizeDocument( $document );
		foreach ( $document['elements'] as &$element ) {
			if ( 'text' === $element['type'] ) {
				$element['_text'] = $this->resolver->replace( $element['content'], $data );
				$element['_font_path'] = $this->fonts->locateForElement( $element );
			} elseif ( 'image' === $element['type'] ) {
				$value = $element['attachmentId'] ? (int) $element['attachmentId'] : $this->resolver->rawValue( $element['source'], $data );
				$element['_path'] = $this->localMediaPath( $value );
				if ( '' === $element['_path'] && is_string( $value ) && preg_match( '#^https?://#i', $value ) ) {
					$remote = $this->remoteImages->fetch( $value );
					if ( is_wp_error( $remote ) ) {
						$element['_source_error'] = $remote->get_error_message();
					} else {
						$element['_path'] = $remote['path'];
						$element['_source_hash'] = $remote['hash'];
					}
				}
			} elseif ( 'svg' === $element['type'] ) {
				$element['_svg'] = $this->icons?->get(
					$element['icon'],
					array(
						'width'  => min( 8192, max( 1, (int) round( $element['width'] * 2 ) ) ),
						'height' => min( 8192, max( 1, (int) round( $element['height'] * 2 ) ) ),
						'color'  => $element['color'],
					)
				) ?? '';
			}
		}
		unset( $element );
		return $document;
	}

	private function localMediaPath(mixed $value): string {
		if ( is_numeric( $value ) ) {
			$path = get_attached_file( (int) $value );
			return is_string( $path ) && is_readable( $path ) ? $path : '';
		}
		$url = esc_url_raw( (string) $value );
		if ( ! $url ) {
			return '';
		}
		$attachment_id = attachment_url_to_postid( $url );
		if ( $attachment_id ) {
			$path = get_attached_file( $attachment_id );
			return is_string( $path ) && is_readable( $path ) ? $path : '';
		}
		$uploads = wp_upload_dir();
		if ( 0 === strpos( $url, $uploads['baseurl'] ) ) {
			$relative = ltrim( substr( $url, strlen( $uploads['baseurl'] ) ), '/' );
			$path = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . $relative );
			$base = trailingslashit( wp_normalize_path( $uploads['basedir'] ) );
			return 0 === strpos( $path, $base ) && is_readable( $path ) ? $path : '';
		}
		return '';
	}

	private function persistResult(int $post_id, array $template, array $result): array|WP_Error {
		$meta_keys = array( 'og' => self::META_OG, 'twitter' => self::META_TWITTER, 'featured' => self::META_FEATURED );
		update_post_meta( $post_id, $meta_keys[ $result['output'] ], esc_url_raw( $result['url'] ) );
		$map = get_post_meta( $post_id, self::META_MAP, true );
		$map = is_array( $map ) ? $map : array();
		$previous = isset( $map[ $result['output'] ] ) && is_array( $map[ $result['output'] ] ) ? $map[ $result['output'] ] : array();
		$entry = array(
			'template_id' => (int) $template['id'],
			'hash'        => $result['hash'],
			'file'        => $result['path'],
			'url'         => $result['url'],
			'width'       => (int) $result['width'],
			'height'      => (int) $result['height'],
			'generated'   => time(),
		);

		$extra = array();
		if ( 'featured' === $result['output'] ) {
			$attachment_id = $this->mediaAttachment( $post_id, $template, $result, (int) ( $previous['attachment_id'] ?? 0 ) );
			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}
			$entry['attachment_id'] = $attachment_id;
			$replace = ! empty( $template['rules']['replaceFeatured'] ) || PluginSettings::get( 'replace_featured', false );
			if ( ! has_post_thumbnail( $post_id ) || $replace || (int) get_post_thumbnail_id( $post_id ) === (int) ( $previous['attachment_id'] ?? 0 ) ) {
				$this->rendering = true;
				set_post_thumbnail( $post_id, $attachment_id );
				$this->rendering = false;
				$extra['attachment_id'] = $attachment_id;
			}
		}

		$map[ $result['output'] ] = $entry;
		update_post_meta( $post_id, self::META_MAP, $map );
		$this->prunePrevious( $previous, $result['path'] );
		return $extra;
	}

	private function mediaAttachment(int $post_id, array $template, array $result, int $existing_id = 0): int|WP_Error {
		$mime = array( 'png' => 'image/png', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp' );
		if ( $existing_id && '1' === (string) get_post_meta( $existing_id, '_social_image_generated', true ) ) {
			$attachment_id = $existing_id;
			update_attached_file( $attachment_id, $result['path'] );
			wp_update_post( array( 'ID' => $attachment_id, 'post_mime_type' => $mime[ $result['format'] ], 'post_title' => sprintf( '%s — %s', $template['title'], get_the_title( $post_id ) ) ) );
		} else {
			$attachment_id = wp_insert_attachment(
				array(
					'post_mime_type' => $mime[ $result['format'] ],
					'post_title'     => sprintf( '%s — %s', $template['title'], get_the_title( $post_id ) ),
					'post_status'    => 'inherit',
				),
				$result['path'],
				$post_id,
				true
			);
			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $result['path'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		update_post_meta( $attachment_id, '_social_image_generated', 1 );
		update_post_meta( $attachment_id, '_social_image_source_post', $post_id );
		update_post_meta( $attachment_id, '_social_image_template_id', $template['id'] );
		update_post_meta( $attachment_id, '_social_image_hash', $result['hash'] );
		return $attachment_id;
	}

	private function prunePrevious(array $previous, string $current_path): void {
		if ( empty( $previous['file'] ) || $previous['file'] === $current_path ) {
			return;
		}
		$uploads = wp_upload_dir();
		$base = trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . 'social-image/';
		$old = wp_normalize_path( $previous['file'] );
		if ( 0 === strpos( $old, $base ) && is_file( $old ) ) {
			wp_delete_file( $old );
		}
	}

	private function locations(string $suffix = ''): array|WP_Error {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'social_image_upload_error', $uploads['error'] );
		}
		$path = trailingslashit( $uploads['basedir'] ) . 'social-image';
		$url  = trailingslashit( $uploads['baseurl'] ) . 'social-image';
		if ( $suffix ) {
			$path .= '/' . sanitize_key( $suffix );
			$url  .= '/' . sanitize_key( $suffix );
		}
		if ( ! wp_mkdir_p( $path ) || ! wp_is_writable( $path ) ) {
			return new WP_Error( 'social_image_upload_unwritable', __( 'The Social Image uploads directory is not writable.', 'jooosi-social-image' ) );
		}
		return array( 'path' => $path, 'url' => $url );
	}
}
