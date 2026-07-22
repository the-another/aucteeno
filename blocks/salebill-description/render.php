<?php
/**
 * Aucteeno Salebill Description Block - Server-Side Render
 *
 * @package Aucteeno
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use The_Another\Plugin\Aucteeno\Blocks\Salebill_Panels;

echo Salebill_Panels::render_description(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped/filtered inside the renderer.
