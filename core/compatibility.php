<?php

if ( ! defined( 'ABSPATH' ) )
    exit;



/**
 *  wpuxss_eml_elementor_scripts
 *  @TODO: temporary solution
 *
 *  @since    2.5
 *  @created  28/01/18
 */

add_action( 'elementor/editor/after_enqueue_scripts', 'wpesq3_ml_elementor_scripts' );

if ( ! function_exists( 'wpesq3_ml_elementor_scripts' ) ) {

    function wpesq3_ml_elementor_scripts() {

        global $wpesq3_ml_dir;


        wp_enqueue_style( 'common' );
        wp_enqueue_style(
            'wpuxss-eml-elementor-media-style',
            $wpesq3_ml_dir . 'css/eml-admin-media.css'
        );
    }
}
