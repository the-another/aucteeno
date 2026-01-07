<?php
/**
 * Blocks Registrar Class
 *
 * Registers all Gutenberg blocks from the blocks/ directory.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno;

/**
 * Class Blocks
 *
 * Registers Gutenberg blocks by scanning the blocks/ directory.
 */
class Blocks {

	/**
	 * Block category slug.
	 *
	 * @var string
	 */
	public const CATEGORY_SLUG = 'aucteeno';

	/**
	 * Register all blocks from the blocks/ directory.
	 *
	 * Scans the blocks/ directory for subdirectories containing block.json
	 * and registers each block using register_block_type().
	 *
	 * @return void
	 */
	public static function register(): void {
		// Register custom block category.
		add_filter( 'block_categories_all', array( __CLASS__, 'register_block_category' ), 10, 2 );

		$base = AUCTEENO_PLUGIN_DIR . 'blocks';

		if ( ! is_dir( $base ) ) {
			return;
		}

		$dirs = scandir( $base );
		if ( false === $dirs ) {
			return;
		}

		foreach ( $dirs as $dir ) {
			if ( '.' === $dir || '..' === $dir ) {
				continue;
			}

			$block_dir = $base . '/' . $dir;

			if ( is_dir( $block_dir ) && file_exists( $block_dir . '/block.json' ) ) {
				register_block_type( $block_dir );
			}
		}
	}

	/**
	 * Register Aucteeno block category.
	 *
	 * @param array                    $categories Array of block categories.
	 * @param \WP_Block_Editor_Context $context    Block editor context.
	 * @return array Modified array of block categories.
	 */
	public static function register_block_category( array $categories, $context ): array {
		// Add Aucteeno category at the beginning.
		array_unshift(
			$categories,
			array(
				'slug'  => self::CATEGORY_SLUG,
				'title' => __( 'Aucteeno', 'aucteeno' ),
				'icon'  => 'hammer',
			)
		);

		return $categories;
	}
}
