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
		foreach ( $ids as $id ) {
			get_post_meta( absint( $id ) );
		}
	}
}
