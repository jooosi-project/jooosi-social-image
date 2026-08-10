<?php

declare(strict_types=1);

namespace JooosiEgami\Assignment;

use JooosiEgami\Template\TemplateRepository;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class TemplateMatcher
{

	public function __construct(private TemplateRepository $repository) {
	}

	public function forPost(int $post_id): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$templates = array_filter(
			$this->repository->all( true ),
			function ( $template ) use ( $post ) {
				return 'publish' === $template['status'] && $this->matches( $template['rules'], $post );
			}
		);

		usort(
			$templates,
			static function ( $a, $b ) {
				$priority = (int) $a['rules']['priority'] <=> (int) $b['rules']['priority'];
				return 0 !== $priority ? $priority : (int) $a['id'] <=> (int) $b['id'];
			}
		);

		$matched = array();
		foreach ( $templates as $template ) {
			foreach ( $template['rules']['outputs'] as $output ) {
				if ( ! isset( $matched[ $output ] ) ) {
					$matched[ $output ] = $template;
				}
			}
		}
		return $matched;
	}

	public function matches(array $rules, WP_Post $post): bool {
		$query = is_array( $rules['query'] ?? null ) ? $rules['query'] : array();
		$matched = $this->matchesGroup( $query, $post, true );

		return (bool) apply_filters( 'jooosi-egami/template:match', $matched, $rules, $post );
	}

	private function matchesGroup(array $group, WP_Post $post, bool $root = false): bool {
		$children = is_array( $group['children'] ?? null ) ? $group['children'] : array();
		if ( array() === $children ) {
			return $root;
		}

		$results = array_map(
			function ( mixed $node ) use ( $post ): bool {
				if ( ! is_array( $node ) ) {
					return false;
				}
				if ( 'group' === ( $node['type'] ?? '' ) || array_key_exists( 'children', $node ) ) {
					return $this->matchesGroup( $node, $post );
				}
				return $this->matchesCondition( $node, $post );
			},
			$children
		);

		return 'or' === ( $group['relation'] ?? 'and' )
			? in_array( true, $results, true )
			: ! in_array( false, $results, true );
	}

	private function matchesCondition(array $condition, WP_Post $post): bool {
		$field = (string) ( $condition['field'] ?? '' );
		$operator = (string) ( $condition['operator'] ?? '' );
		$key = (string) ( $condition['key'] ?? '' );
		$values = array_map( 'strval', (array) ( $condition['values'] ?? array() ) );

		if ( 'post_type' === $field ) {
			return $this->matchesList( (string) $post->post_type, $operator, $values );
		}
		if ( 'post_status' === $field ) {
			return $this->matchesList( (string) $post->post_status, $operator, $values );
		}
		if ( 'post_id' === $field ) {
			return $this->matchesList( (string) $post->ID, $operator, $values );
		}
		if ( 'author' === $field ) {
			return $this->matchesList( (string) $post->post_author, $operator, $values );
		}
		if ( 'parent' === $field ) {
			return $this->matchesList( (string) $post->post_parent, $operator, $values );
		}
		if ( 'page_template' === $field ) {
			$template = get_page_template_slug( $post );
			return $this->matchesList( $template ?: 'default', $operator, $values );
		}
		if ( 'taxonomy' === $field ) {
			if ( 'has_all' === $operator ) {
				return ! in_array( false, array_map( static fn ( string $term ): bool => has_term( $term, $key, $post ), $values ), true );
			}
			$has_any = has_term( $values, $key, $post );
			return 'has_none' === $operator ? ! $has_any : $has_any;
		}
		if ( 'meta' === $field ) {
			$exists = metadata_exists( 'post', (int) $post->ID, $key );
			if ( 'exists' === $operator || 'not_exists' === $operator ) {
				return 'exists' === $operator ? $exists : ! $exists;
			}
			$actual = array_map( 'maybe_unserialize', (array) get_post_meta( (int) $post->ID, $key, false ) );
			return $this->compareValues( $actual, $operator, $values );
		}
		if ( in_array( $field, array( 'post_title', 'post_slug', 'post_excerpt' ), true ) ) {
			$property = array( 'post_title' => 'post_title', 'post_slug' => 'post_name', 'post_excerpt' => 'post_excerpt' )[ $field ];
			return $this->compareValues( array( (string) $post->{$property} ), $operator, $values );
		}
		if ( 'date' === $field ) {
			$date = 'modified' === $key ? ( $post->post_modified ?: $post->post_modified_gmt ) : ( $post->post_date ?: $post->post_date_gmt );
			return $this->matchesDate( (string) $date, $operator, $values );
		}

		return false;
	}

	private function matchesList(string $actual, string $operator, array $values): bool {
		$matched = in_array( $actual, $values, true );
		return 'not_in' === $operator ? ! $matched : $matched;
	}

	private function compareValues(array $actual_values, string $operator, array $expected_values): bool {
		$actual = array();
		array_walk_recursive(
			$actual_values,
			static function ( mixed $value ) use ( &$actual ): void {
				if ( is_scalar( $value ) || null === $value ) {
					$actual[] = (string) $value;
				}
			}
		);
		$expected = (string) ( $expected_values[0] ?? '' );
		$equals = static fn ( string $value ): bool => 0 === strcasecmp( $value, $expected );
		$contains = static fn ( string $value ): bool => false !== stripos( $value, $expected );

		return match ( $operator ) {
			'equals'           => in_array( true, array_map( $equals, $actual ), true ),
			'not_equals'       => ! in_array( true, array_map( $equals, $actual ), true ),
			'contains'         => in_array( true, array_map( $contains, $actual ), true ),
			'not_contains'     => ! in_array( true, array_map( $contains, $actual ), true ),
			'starts_with'      => in_array( true, array_map( static fn ( string $value ): bool => 0 === stripos( $value, $expected ), $actual ), true ),
			'ends_with'        => in_array( true, array_map( static fn ( string $value ): bool => '' !== $expected && str_ends_with( strtolower( $value ), strtolower( $expected ) ), $actual ), true ),
			'in'               => array() !== array_intersect( array_map( 'strtolower', $actual ), array_map( 'strtolower', $expected_values ) ),
			'not_in'           => array() === array_intersect( array_map( 'strtolower', $actual ), array_map( 'strtolower', $expected_values ) ),
			'greater'          => is_numeric( $expected ) && in_array( true, array_map( static fn ( string $value ): bool => is_numeric( $value ) && (float) $value > (float) $expected, $actual ), true ),
			'greater_or_equal' => is_numeric( $expected ) && in_array( true, array_map( static fn ( string $value ): bool => is_numeric( $value ) && (float) $value >= (float) $expected, $actual ), true ),
			'less'             => is_numeric( $expected ) && in_array( true, array_map( static fn ( string $value ): bool => is_numeric( $value ) && (float) $value < (float) $expected, $actual ), true ),
			'less_or_equal'    => is_numeric( $expected ) && in_array( true, array_map( static fn ( string $value ): bool => is_numeric( $value ) && (float) $value <= (float) $expected, $actual ), true ),
			default            => false,
		};
	}

	private function matchesDate(string $actual, string $operator, array $values): bool {
		$timestamp = strtotime( $actual );
		$first = isset( $values[0] ) ? strtotime( $values[0] . ' 00:00:00' ) : false;
		$last = isset( $values[1] ) ? strtotime( $values[1] . ' 23:59:59' ) : false;

		if ( false === $timestamp || false === $first ) {
			return false;
		}

		return match ( $operator ) {
			'before'  => $timestamp < $first,
			'after'   => $timestamp > strtotime( $values[0] . ' 23:59:59' ),
			'on'      => $timestamp >= $first && $timestamp <= strtotime( $values[0] . ' 23:59:59' ),
			'between' => false !== $last && $timestamp >= $first && $timestamp <= $last,
			default   => false,
		};
	}
}
