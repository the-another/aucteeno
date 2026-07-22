<?php
/**
 * Aucteeno Salebill Directions Block - Server-Side Render
 *
 * @package Aucteeno
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use The_Another\Plugin\Aucteeno\Blocks\Salebill_Panels;

echo Salebill_Panels::render_directions(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the renderer.
