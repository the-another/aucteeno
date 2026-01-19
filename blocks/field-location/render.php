<?php
/**
 * Aucteeno Field Location Block - Server-Side Render
 *
 * @package Aucteeno
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TheAnother\Plugin\Aucteeno\Helpers\Location_Helper;
use TheAnother\Plugin\Aucteeno\Helpers\Block_Data_Helper;

// Get item data from context or current post.
$item_data = $block->context['aucteeno/item'] ?? null;
if ( ! $item_data ) {
	$item_data = Block_Data_Helper::get_item_data();
}

if ( ! $item_data ) {
	return '';
}

$show_icon   = $attributes['showIcon'] ?? true;
$show_links  = $attributes['showLinks'] ?? false;
$format      = $attributes['format'] ?? 'smart';
$city        = $item_data['location_city'] ?? '';
$subdivision = $item_data['location_subdivision'] ?? '';
$country     = $item_data['location_country'] ?? '';

/**
 * Get location term ID by code meta.
 *
 * @param string $code Meta code to search for.
 * @param int    $parent Parent term ID (0 for countries, country term ID for subdivisions).
 * @return int Term ID or 0 if not found.
 */
$get_term_by_code = function ( $code, $parent = 0 ) {
	if ( empty( $code ) ) {
		return 0;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'aucteeno-location',
			'hide_empty' => false,
			'parent'     => $parent,
			'meta_query' => array(
				array(
					'key'   => 'code',
					'value' => $code,
				),
			),
		)
	);

	if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		return (int) $terms[0]->term_id;
	}

	return 0;
};

// Extract subdivision code from "COUNTRY:SUBDIVISION" format if needed.
$subdivision_code = $subdivision;
if ( ! empty( $subdivision ) && strpos( $subdivision, ':' ) !== false ) {
	$sub_parts        = explode( ':', $subdivision, 2 );
	$subdivision_code = isset( $sub_parts[1] ) ? $sub_parts[1] : '';
}

// Get human-readable names.
$country_name     = Location_Helper::get_country_name( $country );
$subdivision_name = Location_Helper::get_subdivision_name( $country, $subdivision_code );

// Build location parts array with text and optional term link.
// Each part is ['text' => string, 'term_id' => int|null].
$location_parts = array();

switch ( $format ) {
	case 'smart':
		if ( $city ) {
			$location_parts[] = array(
				'text'    => $city,
				'term_id' => null,
			);
		}
		if ( $subdivision_name ) {
			// Get term IDs for linking.
			$country_term_id = 0;
			if ( $show_links && $country ) {
				$country_term_id = $get_term_by_code( $country, 0 );
			}

			$subdivision_term_id = 0;
			if ( $show_links && $country && $subdivision_code ) {
				$subdivision_term_id = $get_term_by_code( $country . ':' . $subdivision_code, $country_term_id );
			}

			$location_parts[] = array(
				'text'    => $subdivision_name,
				'term_id' => $subdivision_term_id > 0 ? $subdivision_term_id : null,
			);
			$location_parts[] = array(
				'text'    => $country,
				'term_id' => $country_term_id > 0 ? $country_term_id : null,
			);
		} elseif ( $country_name ) {
			$country_term_id = 0;
			if ( $show_links && $country ) {
				$country_term_id = $get_term_by_code( $country, 0 );
			}

			$location_parts[] = array(
				'text'    => $country_name,
				'term_id' => $country_term_id > 0 ? $country_term_id : null,
			);
		}
		break;

	case 'city_only':
		if ( $city ) {
			$location_parts[] = array(
				'text'    => $city,
				'term_id' => null,
			);
		}
		break;

	case 'country_only':
		if ( $country_name ) {
			$country_term_id = 0;
			if ( $show_links && $country ) {
				$country_term_id = $get_term_by_code( $country, 0 );
			}

			$location_parts[] = array(
				'text'    => $country_name,
				'term_id' => $country_term_id > 0 ? $country_term_id : null,
			);
		}
		break;

	case 'city_subdivision':
		if ( $city ) {
			$location_parts[] = array(
				'text'    => $city,
				'term_id' => null,
			);
		}
		if ( $subdivision_name ) {
			$country_term_id = 0;
			if ( $show_links && $country ) {
				$country_term_id = $get_term_by_code( $country, 0 );
			}

			$subdivision_term_id = 0;
			if ( $show_links && $country && $subdivision_code ) {
				$subdivision_term_id = $get_term_by_code( $country . ':' . $subdivision_code, $country_term_id );
			}

			$location_parts[] = array(
				'text'    => $subdivision_name,
				'term_id' => $subdivision_term_id > 0 ? $subdivision_term_id : null,
			);
		}
		break;

	case 'city_country':
		if ( $city ) {
			$location_parts[] = array(
				'text'    => $city,
				'term_id' => null,
			);
		}
		if ( $country ) {
			$country_term_id = 0;
			if ( $show_links && $country ) {
				$country_term_id = $get_term_by_code( $country, 0 );
			}

			$location_parts[] = array(
				'text'    => $country,
				'term_id' => $country_term_id > 0 ? $country_term_id : null,
			);
		}
		break;

	default:
		// Fallback to smart format.
		if ( $city ) {
			$location_parts[] = array(
				'text'    => $city,
				'term_id' => null,
			);
		}
		if ( $subdivision_name ) {
			$country_term_id = 0;
			if ( $show_links && $country ) {
				$country_term_id = $get_term_by_code( $country, 0 );
			}

			$subdivision_term_id = 0;
			if ( $show_links && $country && $subdivision_code ) {
				$subdivision_term_id = $get_term_by_code( $country . ':' . $subdivision_code, $country_term_id );
			}

			$location_parts[] = array(
				'text'    => $subdivision_name,
				'term_id' => $subdivision_term_id > 0 ? $subdivision_term_id : null,
			);
			$location_parts[] = array(
				'text'    => $country,
				'term_id' => $country_term_id > 0 ? $country_term_id : null,
			);
		} elseif ( $country_name ) {
			$country_term_id = 0;
			if ( $show_links && $country ) {
				$country_term_id = $get_term_by_code( $country, 0 );
			}

			$location_parts[] = array(
				'text'    => $country_name,
				'term_id' => $country_term_id > 0 ? $country_term_id : null,
			);
		}
		break;
}

if ( empty( $location_parts ) ) {
	return '';
}

$wrapper_classes    = 'aucteeno-field-location';
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $show_icon ) : ?>
		<span class="aucteeno-field-location__icon" aria-hidden="true">📍</span>
	<?php endif; ?>
	<span class="aucteeno-field-location__text">
		<?php
		foreach ( $location_parts as $index => $part ) {
			// Add comma separator between parts.
			if ( $index > 0 ) {
				echo ', ';
			}

			// Render part with optional link.
			if ( $show_links && ! empty( $part['term_id'] ) ) {
				$term_link = get_term_link( $part['term_id'], 'aucteeno-location' );
				if ( ! is_wp_error( $term_link ) ) {
					echo '<a href="' . esc_url( $term_link ) . '">' . esc_html( $part['text'] ) . '</a>';
				} else {
					echo esc_html( $part['text'] );
				}
			} else {
				echo esc_html( $part['text'] );
			}
		}
		?>
	</span>
</div>
<?php
echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is already escaped above.
