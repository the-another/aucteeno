<?php
/**
 * Aucteeno Salebill Notes Block - Server-Side Render
 *
 * @package Aucteeno
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use The_Another\Plugin\Aucteeno\Blocks\Salebill_Panels;

echo Salebill_Panels::render_notes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the renderer.
