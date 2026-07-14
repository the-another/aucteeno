<?php
/**
 * Search Block Service Class
 *
 * Parses the "view all" page's post_content to extract the first aucteeno/query-loop
 * block's perPage and orderBy settings, caches the result, and invalidates caches on edits.
 *
 * @package Aucteeno
 * @since 2.0.0
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno\Services;

use The_Another\Plugin\Aucteeno\Hook_Manager;

/**
 * Class Search_Block_Service
 *
 * Parses configured "view all" page block settings and manages cache invalidation.
 */
class Search_Block_Service {
	/**
	 * Valid orderBy values.
	 *
	 * @var string[]
	 */
	private const VALID_ORDER_BY = array( 'ending_soon', 'status_ending_soon', 'newest', 'lot_number' );

	/**
	 * Fallback page options.
	 *
	 * @var array{perPage:int,orderBy:string,pageUrl:string}
	 */
	private const FALLBACK = array(
		'perPage' => 25,
		'orderBy' => 'ending_soon',
		'pageUrl' => '',
	);

	/**
	 * Transient TTL for cached page options.
	 *
	 * @var int
	 */
	private const PAGE_TRANSIENT_TTL = DAY_IN_SECONDS;

	/**
	 * Hook manager instance.
	 *
	 * @var Hook_Manager
	 */
	private Hook_Manager $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param Hook_Manager $hook_manager Hook manager instance.
	 */
	public function __construct( Hook_Manager $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Initialize service — register all hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->hook_manager->register_action( 'save_post_page', array( $this, 'on_page_save' ), 10, 1 );
		// The search count transient is deliberately NOT flushed on item/auction
		// saves: mass imports save items in bulk, which kept the cache
		// permanently cold and made visitors pay the count query on render.
		// Its TTL (Search_Count_Provider) handles freshness instead.
	}

	/**
	 * Invalidate page options transient when a page is saved.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_page_save( int $post_id ): void {
		delete_transient( $this->page_transient_key( $post_id ) );
	}

	/**
	 * Get page options (perPage, orderBy, pageUrl) for the given page ID.
	 *
	 * Parses the page's post_content to find the first aucteeno/query-loop block
	 * and extracts its attributes. Result is cached in a per-page transient.
	 *
	 * @param int $page_id WordPress page post ID.
	 * @return array{perPage:int,orderBy:string,pageUrl:string}
	 */
	public function get_page_options( int $page_id ): array {
		if ( $page_id <= 0 ) {
			return self::FALLBACK;
		}

		$post = get_post( $page_id );
		if ( null === $post || 'publish' !== $post->post_status ) {
			return self::FALLBACK;
		}

		$cache_key = $this->page_transient_key( $page_id );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$opts            = self::FALLBACK;
		$opts['pageUrl'] = (string) get_permalink( $page_id );

		$blocks = parse_blocks( $post->post_content );
		$found  = $this->find_query_loop( $blocks );
		if ( null !== $found ) {
			$attrs = $found['attrs'] ?? array();
			if ( isset( $attrs['perPage'] ) && is_numeric( $attrs['perPage'] ) ) {
				$opts['perPage'] = max( 1, min( 100, (int) $attrs['perPage'] ) );
			}
			if ( isset( $attrs['orderBy'] ) && in_array( $attrs['orderBy'], self::VALID_ORDER_BY, true ) ) {
				$opts['orderBy'] = (string) $attrs['orderBy'];
			}
		}

		set_transient( $cache_key, $opts, self::PAGE_TRANSIENT_TTL );
		return $opts;
	}

	/**
	 * Recursively walks block tree, returns the first aucteeno/query-loop block or null.
	 *
	 * @param array<int,array<string,mixed>> $blocks Parsed block tree.
	 * @return array<string,mixed>|null
	 */
	private function find_query_loop( array $blocks ): ?array {
		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? '' ) === 'aucteeno/query-loop' ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$hit = $this->find_query_loop( $block['innerBlocks'] );
				if ( null !== $hit ) {
					return $hit;
				}
			}
		}
		return null;
	}

	/**
	 * Build the transient key for a page's options.
	 *
	 * @param int $page_id WordPress page post ID.
	 * @return string
	 */
	private function page_transient_key( int $page_id ): string {
		return 'aucteeno_search_pageopts_' . $page_id;
	}
}
