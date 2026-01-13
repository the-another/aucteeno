<?php
namespace TheAnother\Plugin\Aucteeno\Product_Types\Traits;

use BadMethodCallException;

trait Use_Normalized_Method_Names {
	/**
	 * Backward compatibility.
	 *
	 * @param $method
	 * @param $args
	 * @return mixed
	 */
	public function __call( $method, $args ): mixed {
		// Map get_aucteeno_field() -> get_field()
		if ( str_starts_with( $method, 'get_aucteeno_' ) ) {
			$mapped = 'get_' . substr( $method, strlen( 'get_aucteeno_' ) );

			if ( method_exists( $this, $mapped ) ) {
				return $this->$mapped( ...$args );
			}
		}

		// Optional: map set_aucteeno_field($v) -> set_field($v)
		if ( str_starts_with( $method, 'set_aucteeno_' ) ) {
			$mapped = 'set_' . substr( $method, strlen( 'set_aucteeno_' ) );

			if ( method_exists( $this, $mapped ) ) {
				return $this->$mapped( ...$args );
			}
		}

		throw new BadMethodCallException(
			sprintf(
				'Call to undefined method %s::%s()',
				static::class,
				$method
			) 
		);
	}
}
