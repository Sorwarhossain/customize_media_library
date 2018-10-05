<?php

if ( ! defined( 'ABSPATH' ) )
	exit;


if ( ! function_exists( 'wpesq3_ml_mimes_validate' ) ) {

    function wpesq3_ml_mimes_validate( $input ) {

        if ( ! $input ) $input = array();


        if ( isset( $_POST['eml-restore-mime-types-settings'] ) ) {

            $input = get_site_option( 'wpesq3_ml_mimes_backup', array() );

            add_settings_error(
                'mime-types',
                'eml_mime_types_restored',
                __('MIME Types settings restored.', 'textdomain'),
                'updated'
            );
        }
        else {

            add_settings_error(
                'mime-types',
                'eml_mime_types_saved',
                __('MIME Types settings saved.', 'textdomain'),
                'updated'
            );
        }


        foreach ( $input as $type => $mime ) {

            $sanitized_type = wpesq3_ml_sanitize_extension( $type );

            if ( $sanitized_type !== $type ) {

                $input[$sanitized_type] = $input[$type];
                unset($input[$type]);
                $type = $sanitized_type;
            }

            $input[$type]['filter'] = isset( $mime['filter'] ) && !! $mime['filter'] ? 1 : 0;
            $input[$type]['upload'] = isset( $mime['upload'] ) && !! $mime['upload'] ? 1 : 0;

            $input[$type]['mime'] = sanitize_mime_type($mime['mime']);
            $input[$type]['singular'] = sanitize_text_field($mime['singular']);
            $input[$type]['plural'] = sanitize_text_field($mime['plural']);
        }

        return $input;
    }
}




if ( ! function_exists( 'wpesq3_ml_sanitize_extension' ) ) {

    function wpesq3_ml_sanitize_extension( $key ) {

        $key = strtolower( $key );
        $key = preg_replace( '/[^a-z0-9|]/', '', $key );
        return $key;
    }
}



add_filter( 'post_mime_types', 'wpesq3_ml_post_mime_types' );

if ( ! function_exists( 'wpesq3_ml_post_mime_types' ) ) {

    function wpesq3_ml_post_mime_types( $post_mime_types ) {

        $wpesq3_ml_mimes = get_option('wpesq3_ml_mimes');

        if ( ! empty( $wpesq3_ml_mimes ) ) {

            foreach ( $wpesq3_ml_mimes as $extension => $mime ) {

                if ( (bool) $mime['filter'] ) {

                    $mime_type = sanitize_mime_type( $mime['mime'] );

                    $post_mime_types[$mime_type] = array(
                        esc_html( $mime['plural'] ),
                        'Manage ' . esc_html( $mime['plural'] ),
                        _n_noop( esc_html( $mime['singular'] ) . ' <span class="count">(%s)</span>', esc_html( $mime['plural'] ) . ' <span class="count">(%s)</span>' )
                    );
                }
            }
        }

        return $post_mime_types;
    }
}



add_filter('upload_mimes', 'wpesq3_ml_upload_mimes');

if ( ! function_exists( 'wpesq3_ml_upload_mimes' ) ) {

    function wpesq3_ml_upload_mimes( $existing_mimes = array() ) {

        $wpesq3_ml_mimes = get_option('wpesq3_ml_mimes');

        if ( ! empty( $wpesq3_ml_mimes ) ) {

            foreach ( $wpesq3_ml_mimes as $extension => $mime ) {

                $extension = wpesq3_ml_sanitize_extension( $extension );


                if ( (bool) $mime['upload'] ) {
                    $existing_mimes[$extension] = sanitize_mime_type( $mime['mime'] );
                }
                else {
                    unset( $existing_mimes[$extension] );
                }
            }
        }

        return $existing_mimes;
    }
}



add_filter( 'mime_types', 'wpesq3_ml_mime_types' );

if ( ! function_exists( 'wpesq3_ml_mime_types' ) ) {

    function wpesq3_ml_mime_types( $default_mimes ) {

        $new_mimes = array();
        $wpesq3_ml_mimes = get_option( 'wpesq3_ml_mimes' );

        if ( false !== $wpesq3_ml_mimes ) {

            foreach ( $wpesq3_ml_mimes as $extension => $mime ) {

                $extension = wpesq3_ml_sanitize_extension( $extension );
                $new_mimes[$extension] = sanitize_mime_type( $mime['mime'] );
            }

            return $new_mimes;
        }

        return $default_mimes;
    }
}

?>
