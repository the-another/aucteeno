<?php
/**
 * Eager Loader
 *
 * Batch-primes WordPress object caches to eliminate N+1 queries
 * in query_for_listing() transform loops.
 *
 * @package Aucteeno
 * @since 2.1.0
 */

namespace The_Another\Plugin\Aucteeno\Database;

/**
 * Class Eager_Loader
 *
 * Provides static helpers for batch-loading WordPress object caches
 * before iterating over query result sets.
 */
class Eager_Loader {

	/**
	 * Batch-prime post meta cache for the given post IDs.
	 *
	 * After calling, get_post_meta() for any of these IDs serves from the
	 * WP in-memory object cache — no further DB queries.
	 *
	 * Uses _prime_post_caches() when available (WordPress internal, 6.0+).
	 * Falls back to get_post_meta($id) per-ID when the function is absent.
	 *
	 * @since 2.1.0
	 * @param array<int> $ids Post IDs to prime.
	 * @return void
	 */
	public static function prime_post_meta( array $ids ): void {
		if ( empty( $ids ) ) {
			return;
		}

		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, false, true );
			return;
		}

		// Fallback: load all meta per ID individually (higher query cost but safe).
		// absint() guards against non-integer elements that callers may pass.
		foreach ( $ids as $id ) {
			get_post_meta( absint( $id ) );
		}
	}

	/**
	 * Prime attachment meta caches for the thumbnails of the given post IDs.
	 *
	 * Must be called AFTER prime_post_meta() on the same IDs — reads
	 * _thumbnail_id from the already-primed meta cache.
	 *
	 * @since 2.1.0
	 * @param array<int> $ids Post IDs whose thumbnails should be primed.
	 * @return array<int,int> Map of post_id => attachment_id (0 if no thumbnail).
	 */
	public static function prime_images( array $ids ): array {
		$map            = array();
		$attachment_ids = array();

		foreach ( $ids as $id ) {
			$id         = absint( $id );
			$image_id   = (int) get_post_meta( $id, '_thumbnail_id', true );
			$map[ $id ] = $image_id;
			if ( $image_id > 0 ) {
				$attachment_ids[] = $image_id;
			}
		}

		if ( ! empty( $attachment_ids ) ) {
			$unique_ids = array_values( array_unique( $attachment_ids ) );
			if ( function_exists( '_prime_post_caches' ) ) {
				_prime_post_caches( $unique_ids, false, true );
			}
		}

		return $map;
	}
}
