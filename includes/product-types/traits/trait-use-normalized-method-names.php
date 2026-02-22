<?php
/**
 * Use Normalized Method Names Trait
 *
 * Provides backward compatibility for aucteeno-prefixed getter and setter methods.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Product_Types\Traits;

use BadMethodCallException;

trait Use_Normalized_Method_Names {
	/**
	 * Backward compatibility.
	 *
	 * @param string $method Method name being called.
	 * @param array  $args   Arguments passed to the method.
	 * @return mixed
	 * @throws BadMethodCallException If the method does not exist.
	 */
	public function __call( $method, $args ): mixed {
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found, Squiz.Commenting.InlineComment.InvalidEndChar
		// Map get_aucteeno_field() -> get_field()
		if ( str_starts_with( $method, 'get_aucteeno_' ) ) {
			$mapped = 'get_' . substr( $method, strlen( 'get_aucteeno_' ) );

			if ( method_exists( $this, $mapped ) ) {
				return $this->$mapped( ...$args );
			}
		}

		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found, Squiz.Commenting.InlineComment.InvalidEndChar
		// Optional: map set_aucteeno_field($v) -> set_field($v)
		if ( str_starts_with( $method, 'set_aucteeno_' ) ) {
			$mapped = 'set_' . substr( $method, strlen( 'set_aucteeno_' ) );

			if ( method_exists( $this, $mapped ) ) {
				return $this->$mapped( ...$args );
			}
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is not rendered as HTML.
		throw new BadMethodCallException(
			sprintf(
				'Call to undefined method %s::%s()',
				static::class,
				$method
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}
}
