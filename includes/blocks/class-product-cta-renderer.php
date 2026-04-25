<?php
/**
 * Product CTA button renderer — single source of truth for button HTML.
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Product_Cta_Renderer {

    public static function render_button( array $button ): string {
        $button = self::normalize( $button );

        $classes = array_merge( array( 'globalag-cta-button' ), $button['classes'] );
        if ( 'none' !== $button['icon'] ) {
            $classes[] = 'has-icon-' . $button['icon'];
        }

        $button_html = sprintf(
            '<button class="%s"%s><span class="button-text">%s</span></button>',
            esc_attr( implode( ' ', array_map( 'esc_attr', $classes ) ) ),
            self::format_attrs( $button['attrs'] ),
            esc_html( $button['text'] )
        );

        if ( 'form' !== $button['wrapper'] ) {
            return $button_html;
        }

        $form           = $button['form'];
        $form_classes   = array_merge( array( 'globalag-cta-form' ), $form['classes'] ?? array() );
        $hidden_fields  = $form['hidden_fields'] ?? array();
        $hidden_html    = '';
        foreach ( $hidden_fields as $name => $value ) {
            $hidden_html .= sprintf(
                '<input type="hidden" name="%s" value="%s">',
                esc_attr( (string) $name ),
                esc_attr( (string) $value )
            );
        }

        return sprintf(
            '<form class="%s" action="%s" method="%s" target="%s" rel="%s">%s%s</form>',
            esc_attr( implode( ' ', array_map( 'esc_attr', $form_classes ) ) ),
            esc_url( $form['action'] ?? '' ),
            esc_attr( $form['method'] ?? 'get' ),
            esc_attr( $form['target'] ?? '' ),
            esc_attr( $form['rel'] ?? '' ),
            $hidden_html,
            $button_html
        );
    }

    private static function normalize( array $button ): array {
        return array_merge(
            array(
                'id'       => '',
                'wrapper'  => 'button',
                'text'     => '',
                'classes'  => array(),
                'icon'     => 'none',
                'attrs'    => array(),
                'form'     => array(),
                'priority' => 20,
            ),
            $button
        );
    }

    private static function format_attrs( array $attrs ): string {
        $out = '';
        foreach ( $attrs as $key => $value ) {
            if ( ! is_string( $key ) || ! preg_match( '/^[a-z][a-z0-9-]*$/', $key ) ) {
                continue;
            }
            if ( 'style' === $key || 0 === stripos( $key, 'on' ) ) {
                continue;
            }
            $out .= ' ' . esc_attr( $key ) . '="' . esc_attr( (string) $value ) . '"';
        }
        return $out;
    }

    public static function render_collection( array $buttons ): string {
        $valid = array();
        foreach ( $buttons as $button ) {
            if ( ! is_array( $button ) || empty( $button['id'] ) || ! is_string( $button['id'] ) ) {
                continue;
            }
            $valid[ $button['id'] ] = self::normalize( $button );
        }

        if ( empty( $valid ) ) {
            return '';
        }

        uasort(
            $valid,
            static fn( array $a, array $b ): int => $a['priority'] <=> $b['priority']
        );

        $out = '';
        foreach ( $valid as $button ) {
            $out .= self::render_button( $button );
        }
        return $out;
    }
}
