<?php

if ( ! defined( 'ABSPATH' ) )
    exit;



add_filter( 'shortcode_atts_gallery', 'wpesq3_ml_shortcode_atts', 10, 3 );
add_filter( 'shortcode_atts_playlist', 'wpesq3_ml_shortcode_atts', 10, 3 );
add_filter( 'shortcode_atts_slideshow', 'wpesq3_ml_shortcode_atts', 10, 3 );



if ( ! function_exists( 'wpesq3_ml_shortcode_atts' ) ) {

    function wpesq3_ml_shortcode_atts( $output, $defaults, $atts ) {

        $wpuxss_eml_lib_options = get_option( 'wpuxss_eml_lib_options', array() );

        $custom_query = false;
        $id = isset( $atts['id'] ) ? intval( $atts['id'] ) : 0;
        unset( $atts['id'] );
        unset( $defaults['id'] );

        $atts = array_merge( $defaults, $atts );


        if ( ! empty( $atts['monthnum'] ) && ! empty( $atts['year'] ) ) {
            $custom_query = true;
        }


        $tax_query = array();

        foreach ( get_taxonomies_for_attachments( 'names' ) as $taxonomy ) {

            if ( ! empty( $atts[$taxonomy] ) ) {

                $terms = explode( ',', $atts[$taxonomy] );

                $field = ctype_digit( implode( '', $terms ) ) ? 'term_id' : 'slug';

                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field' => $field,
                    'terms' => $terms,
                    'operator' => 'IN',
                    'include_children' => (bool) $wpuxss_eml_lib_options['include_children']
                );

                unset( $atts[$taxonomy] );
                $custom_query = true;
            }
        }


        if ( empty( $atts['ids'] ) || $custom_query ) {

            if ( empty( $atts['orderby'] ) || 'post__in' === $atts['orderby'] ) {
                $output['orderby'] = $atts['orderby'] = ( 'menuOrder' === $wpuxss_eml_lib_options['media_orderby'] ) ? 'menu_order' : esc_attr( $wpuxss_eml_lib_options['media_orderby'] );
            }

            if ( empty( $atts['order'] ) ) {
                $output['order'] = $atts['order'] = esc_attr( $wpuxss_eml_lib_options['media_order'] );
            }
        }


        if ( ! $custom_query ) {
            return $output;
        }


        $mime_type_valuemap = array(
            'pdf' => 'application/pdf',
            'image' => 'image',
            'audio' => 'audio',
            'video' => 'video'
        );

        if ( ! empty( $atts['type'] ) ) {
            $mime_type = $atts['type'];
        }

        else {
            $mime_type = 'image';
        }

        $mime_type = isset( $mime_type_valuemap[$mime_type] ) ? $mime_type_valuemap[$mime_type] : 'image';

        $posts_per_page = isset( $atts['limit'] ) ? intval( $atts['limit'] ) : -1;
        unset( $atts['limit'] );

        $query = array(
            'post_status' => 'inherit',
            'post_type' => 'attachment',
            'post_mime_type' => $mime_type,
            'order' => $atts['order'],
            'orderby' => $atts['orderby'],
            'posts_per_page' => $posts_per_page, // @TODO: add pagination
        );


        if ( ! empty( $atts['monthnum'] ) && ! empty( $atts['year'] ) ) {

            $query['monthnum'] = $atts['monthnum'];
            $query['year'] = $atts['year'];

            unset( $atts['monthnum'] );
            unset( $atts['year'] );
        }


        if ( ! empty( $tax_query ) ) {

            $tax_query['relation'] = 'AND';
            $query['tax_query'] = $tax_query;
        }

        if ( $id ) {
            $query['post_parent'] = $id;
        }


        $get_posts = new WP_Query;
        $attachments = $get_posts->query($query);


        $ids = array();

        foreach ( $attachments as $attachment ) {
            $ids[] = $attachment->ID;
        }


        $output = $atts;
        $output['id'] = $id; // shortcodes require it!


        if ( $ids ) {
            $output['ids'] = $output['include'] = implode( ',', $ids );
            $output['orderby'] = ( 'title' === $output['orderby'] && (bool) $wpuxss_eml_lib_options['natural_sort'] ) ? 'post__in' : $output['orderby'];
        }

        return $output;
    }
}

?>
