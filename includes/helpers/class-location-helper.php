<?php
/**
 * Location Helper Class
 *
 * Provides utility methods for managing hierarchical location taxonomy terms from WooCommerce country/state codes.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Helpers;

/**
 * Class Location_Helper
 *
 * Helper methods for location operations with hierarchical taxonomy.
 * Countries are top-level terms, states/subdivisions are child terms.
 */
class Location_Helper {

	/**
	 * Taxonomy slug.
	 *
	 * @var string
	 */
	private const TAXONOMY = 'aucteeno-location';

	/**
	 * Get or create country taxonomy term from WooCommerce country code.
	 * Country terms are top-level (parent = 0).
	 *
	 * @param string $country_code Two-letter country code (e.g., "CA").
	 * @param string $country_name Country name (e.g., "Canada").
	 * @return int Term ID, or 0 on failure.
	 */
	public static function get_or_create_country_term( string $country_code, string $country_name ): int {
		if ( empty( $country_code ) || empty( $country_name ) ) {
			return 0;
		}

		// Search for existing term by code meta (top-level only).
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'parent'     => 0,
				// phpcs:ignore WordPress.DB.SlowDBQuery -- Required for taxonomy/meta filtering.
				'meta_query' => array(
					array(
						'key'   => 'code',
						'value' => $country_code,
					),
				),
			)
		);

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			// Term exists, return its ID.
			return (int) $terms[0]->term_id;
		}

		// Term doesn't exist, create it as top-level (parent = 0).
		$term_slug   = sanitize_title( $country_name );
		$term_result = wp_insert_term(
			$country_name,
			self::TAXONOMY,
			array(
				'slug'   => $term_slug,
				'parent' => 0,
			)
		);

		if ( is_wp_error( $term_result ) ) {
			return 0;
		}

		$term_id = isset( $term_result['term_id'] ) ? (int) $term_result['term_id'] : 0;

		if ( $term_id > 0 ) {
			// Add code meta.
			add_term_meta( $term_id, 'code', $country_code, true );
		}

		return $term_id;
	}

	/**
	 * Get or create subdivision taxonomy term from WooCommerce state code.
	 * Subdivision terms are children of country terms.
	 *
	 * @param string $country_code Two-letter country code (e.g., "CA").
	 * @param string $state_code State/province code (1 or more characters, e.g., "MB", "1", "ALA").
	 * @param string $state_name State/province name (e.g., "Manitoba").
	 * @return int Term ID, or 0 on failure.
	 */
	public static function get_or_create_subdivision_term( string $country_code, string $state_code, string $state_name ): int {
		if ( empty( $country_code ) || empty( $state_code ) || empty( $state_name ) ) {
			return 0;
		}

		// Get or create parent country term.
		$countries = WC()->countries->get_countries();
		if ( ! isset( $countries[ $country_code ] ) ) {
			return 0;
		}
		$country_term_id = self::get_or_create_country_term( $country_code, $countries[ $country_code ] );
		if ( $country_term_id <= 0 ) {
			return 0;
		}

		$code_meta = $country_code . ':' . $state_code;

		// Search for existing term by code meta under the parent country.
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'parent'     => $country_term_id,
				// phpcs:ignore WordPress.DB.SlowDBQuery -- Required for taxonomy/meta filtering.
				'meta_query' => array(
					array(
						'key'   => 'code',
						'value' => $code_meta,
					),
				),
			)
		);

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			// Term exists, return its ID.
			return (int) $terms[0]->term_id;
		}

		// Term doesn't exist, create it as child of country term.
		$term_slug   = sanitize_title( $state_name );
		$term_result = wp_insert_term(
			$state_name,
			self::TAXONOMY,
			array(
				'slug'   => $term_slug,
				'parent' => $country_term_id,
			)
		);

		if ( is_wp_error( $term_result ) ) {
			return 0;
		}

		$term_id = isset( $term_result['term_id'] ) ? (int) $term_result['term_id'] : 0;

		if ( $term_id > 0 ) {
			// Add code meta.
			add_term_meta( $term_id, 'code', $code_meta, true );
		}

		return $term_id;
	}

	/**
	 * Get WooCommerce country code from taxonomy term ID.
	 * Works for both country terms (code = country code) and subdivision terms (code = country:state).
	 *
	 * @param int $term_id Taxonomy term ID.
	 * @return string Country code, or empty string if not found.
	 */
	public static function get_country_code_from_term( int $term_id ): string {
		if ( $term_id <= 0 ) {
			return '';
		}

		$code = get_term_meta( $term_id, 'code', true );
		if ( empty( $code ) ) {
			return '';
		}

		// If code contains colon, it's a subdivision term (country:state).
		// Extract country part. Otherwise, it's a country code directly.
		if ( strpos( $code, ':' ) !== false ) {
			$parts = explode( ':', $code, 2 );
			return isset( $parts[0] ) ? $parts[0] : '';
		}

		// Direct country code.
		return $code;
	}

	/**
	 * Get WooCommerce state code from taxonomy term ID.
	 * Only works for subdivision terms (child terms with country:state code).
	 *
	 * @param int $term_id Taxonomy term ID.
	 * @return string State code (without country prefix, can be 1 or more characters), or empty string if not found.
	 */
	public static function get_state_code_from_term( int $term_id ): string {
		if ( $term_id <= 0 ) {
			return '';
		}

		$code = get_term_meta( $term_id, 'code', true );
		if ( empty( $code ) ) {
			return '';
		}

		// Code format is "COUNTRY:STATE" for subdivisions (e.g., "CA:MB", "US:1", "GB:ENG").
		// State code can be 1 or more characters. Using limit 2 ensures we only split on first semicolon.
		if ( strpos( $code, ':' ) !== false ) {
			$parts = explode( ':', $code, 2 );
			return isset( $parts[1] ) ? $parts[1] : '';
		}

		// No colon means it's a country term, not a subdivision.
		return '';
	}

	/**
	 * Get country code from subdivision term ID.
	 * Helper to get country code part from subdivision code meta.
	 * Alias for get_country_code_from_term for clarity.
	 *
	 * @param int $term_id Taxonomy term ID.
	 * @return string Country code, or empty string if not found.
	 */
	public static function get_country_code_from_subdivision_term( int $term_id ): string {
		return self::get_country_code_from_term( $term_id );
	}

	/**
	 * Get formatted location string with smart display logic.
	 *
	 * Display logic:
	 * - City + Subdivision + Country: "Tulsa, Oklahoma, US"
	 * - Subdivision + Country (no city): "Oklahoma, US"
	 * - City + Country (no subdivision): "Leeds, United Kingdom" (full country name)
	 * - Country only: "United Kingdom" (full country name)
	 *
	 * @param string $city         City name.
	 * @param string $subdivision  Subdivision code (e.g., "US:OK" or extracted "OK").
	 * @param string $country_code Two-letter country code.
	 * @return string Formatted location string, or empty string if no data.
	 */
	public static function format_smart_location( string $city = '', string $subdivision = '', string $country_code = '' ): string {
		$parts = array();

		// Get WooCommerce country/state data.
		$countries = WC()->countries->get_countries();

		// Extract subdivision code from "COUNTRY:SUBDIVISION" format if needed.
		$subdivision_code = $subdivision;
		if ( strpos( $subdivision, ':' ) !== false ) {
			$sub_parts        = explode( ':', $subdivision, 2 );
			$subdivision_code = isset( $sub_parts[1] ) ? $sub_parts[1] : '';
		}

		// Get human-readable names.
		$country_name     = isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : $country_code;
		$subdivision_name = '';
		if ( $subdivision_code && $country_code ) {
			$states = WC()->countries->get_states( $country_code );
			if ( $states && isset( $states[ $subdivision_code ] ) ) {
				$subdivision_name = $states[ $subdivision_code ];
			}
		}

		// Build location string based on available data.
		if ( $city ) {
			$parts[] = $city;
		}

		if ( $subdivision_name ) {
			$parts[] = $subdivision_name;
			// Use country code when subdivision is present.
			$parts[] = $country_code;
		} elseif ( $country_name ) {
			// Use full country name when no subdivision.
			$parts[] = $country_name;
		}

		return implode( ', ', $parts );
	}

	/**
	 * Get country name from country code.
	 *
	 * @param string $country_code Two-letter country code.
	 * @return string Country name, or country code if not found.
	 */
	public static function get_country_name( string $country_code ): string {
		if ( empty( $country_code ) ) {
			return '';
		}

		$countries = WC()->countries->get_countries();
		return isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : $country_code;
	}

	/**
	 * Get subdivision name from country code and subdivision code.
	 *
	 * @param string $country_code    Two-letter country code.
	 * @param string $subdivision_code Subdivision code (can be "COUNTRY:CODE" or just "CODE").
	 * @return string Subdivision name, or subdivision code if not found.
	 */
	public static function get_subdivision_name( string $country_code, string $subdivision_code ): string {
		if ( empty( $country_code ) || empty( $subdivision_code ) ) {
			return '';
		}

		// Extract subdivision code from "COUNTRY:SUBDIVISION" format if needed.
		$code = $subdivision_code;
		if ( strpos( $subdivision_code, ':' ) !== false ) {
			$parts = explode( ':', $subdivision_code, 2 );
			$code  = isset( $parts[1] ) ? $parts[1] : '';
		}

		$states = WC()->countries->get_states( $country_code );
		if ( $states && isset( $states[ $code ] ) ) {
			return $states[ $code ];
		}

		return $subdivision_code;
	}
}
