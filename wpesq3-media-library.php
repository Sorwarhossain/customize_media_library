<?php


if ( ! defined( 'ABSPATH' ) )
	exit;



global $wp_version,
       $wpesq3_ml_dir,
       $wpesq3_ml_path;





if ( ! function_exists( 'wpesq3_ml_media_shortcodes' ) ) {

    function wpesq3_ml_media_shortcodes() {

        $wpesq3_ml_lib_options = get_option( 'wpesq3_ml_lib_options', array() );

        $enhance_media_shortcodes = isset( $wpesq3_ml_lib_options['enhance_media_shortcodes'] ) ? (bool) $wpesq3_ml_lib_options['enhance_media_shortcodes'] : false;

        return $enhance_media_shortcodes;
    }
}



/**
 *  Free functionality
 */

include_once( 'core/mime-types.php' );
include_once( 'core/taxonomies.php' );
include_once( 'core/compatibility.php' );

if ( wpesq3_ml_media_shortcodes() ) {
    include_once( 'core/medialist.php' );
}

if ( is_admin() ) {
    include_once( 'core/options-pages.php' );
}




add_action( 'init', 'wpesq3_eml_on_init', 12 );

if ( ! function_exists( 'wpesq3_eml_on_init' ) ) {

    function wpesq3_eml_on_init() {

        $wpesq3_ml_taxonomies = get_option( 'wpesq3_ml_taxonomies', array() );

        // register eml taxonomies
        foreach ( (array) $wpesq3_ml_taxonomies as $taxonomy => $params ) {

            if ( $params['eml_media'] && ! empty( $params['labels']['singular_name'] ) && ! empty( $params['labels']['name'] ) ) {

                $labels = array_map( 'sanitize_text_field', $params['labels'] );

                register_taxonomy(
                    $taxonomy,
                    'attachment',
                    array(
                        'labels' => $labels,
                        'public' => true,
                        'show_admin_column' => (bool) $params['show_admin_column'],
                        'show_in_nav_menus' => (bool) $params['show_in_nav_menus'],
                        'hierarchical' => (bool) $params['hierarchical'],
                        'update_count_callback' => '_eml_update_attachment_term_count',
                        'sort' => (bool) $params['sort'],
                        'show_in_rest' => (bool) $params['show_in_rest'],
                        'query_var' => sanitize_key( $taxonomy ),
                        'rewrite' => array(
                            'slug' => wpesq3_ml_sanitize_slug( $params['rewrite']['slug'] ),
                            'with_front' => (bool) $params['rewrite']['with_front']
                        )
                    )
                );
            }
        } // endforeach
    }
}



/**
 *  wpesq3_ml_on_wp_loaded
 *
 *  @since    1.0
 *  @created  03/11/13
 */

add_action( 'wp_loaded', 'wpesq3_ml_on_wp_loaded' );

if ( ! function_exists( 'wpesq3_ml_on_wp_loaded' ) ) {

    function wpesq3_ml_on_wp_loaded() {

        global $wp_taxonomies,  
                $wpesq3_ml_dir,
                $wpesq3_ml_path;



        $wpesq3_ml_dir = get_stylesheet_directory_uri() . '/libs/media-library/';
        $wpesq3_ml_path = trailingslashit( dirname( __FILE__ ) );



        $wpesq3_ml_taxonomies = get_option( 'wpesq3_ml_taxonomies', array() );
        $taxonomies = get_taxonomies( array(), 'object' );


        // discover 'foreign' taxonomies
        foreach ( $taxonomies as $taxonomy => $params ) {

            if ( ! empty( $params->object_type ) && ! array_key_exists( $taxonomy, $wpesq3_ml_taxonomies ) &&
                 ! in_array( 'revision', $params->object_type ) &&
                 ! in_array( 'nav_menu_item', $params->object_type ) &&
                 $taxonomy !== 'post_format' &&
                 $taxonomy !== 'link_category' ) {

                $wpesq3_ml_taxonomies[$taxonomy] = array(
                    'eml_media' => 0,
                    'admin_filter' => 1, // since 2.7
                    'media_uploader_filter' => 1, // since 2.7
                    'media_popup_taxonomy_edit' => 0,
                    'taxonomy_auto_assign' => 0
                );

                if ( in_array('attachment',$params->object_type) )
                    $wpesq3_ml_taxonomies[$taxonomy]['assigned'] = 1;
                else
                    $wpesq3_ml_taxonomies[$taxonomy]['assigned'] = 0;
            }
        }

        // assign/unassign taxonomies to atachment
        foreach ( $wpesq3_ml_taxonomies as $taxonomy => $params ) {

            $taxonomy = sanitize_key($taxonomy);

            if ( (bool) $params['assigned'] )
                register_taxonomy_for_object_type( $taxonomy, 'attachment' );

            if ( ! (bool) $params['assigned'] )
                unregister_taxonomy_for_object_type( $taxonomy, 'attachment' );
        }


        /**
         *  Clean up update_count_callback
         *  Set custom update_count_callback for post type
         *
         *  @since 2.3
         */
        foreach ( $taxonomies as $taxonomy => $params ) {

            if ( in_array( 'attachment', $params->object_type ) &&
                 isset( $wp_taxonomies[$taxonomy]->update_count_callback ) &&
                 '_update_generic_term_count' === $wp_taxonomies[$taxonomy]->update_count_callback ) {

                unset( $wp_taxonomies[$taxonomy]->update_count_callback );
            }

            if ( in_array( 'post', $params->object_type ) ) {

                if ( in_array( 'attachment', $params->object_type ) )
                    $wp_taxonomies[$taxonomy]->update_count_callback = '_eml_update_post_term_count';
                else
                    unset( $wp_taxonomies[$taxonomy]->update_count_callback );
            }
        }

        update_option( 'wpesq3_ml_taxonomies', $wpesq3_ml_taxonomies );
    }
}



/**
 *  wpesq3_ml_admin_enqueue_scripts
 *
 *  @since    1.1.1
 *  @created  07/04/14
 */

add_action( 'admin_enqueue_scripts', 'wpesq3_ml_admin_enqueue_scripts' );

if ( ! function_exists( 'wpesq3_ml_admin_enqueue_scripts' ) ) {

    function wpesq3_ml_admin_enqueue_scripts() {

        global $wpesq3_ml_dir,
               $current_screen;


        $media_library_mode = get_user_option( 'media_library_mode', get_current_user_id() ) ? get_user_option( 'media_library_mode', get_current_user_id() ) : 'grid';

        $wpesq3_ml_lib_options = get_option( 'wpesq3_ml_lib_options' );


        // admin styles
        wp_enqueue_style(
            'wpuxss-eml-admin-custom-style',
            $wpesq3_ml_dir . 'css/eml-admin.css',
            false,
            '',
            'all'
        );
        wp_style_add_data( 'wpuxss-eml-admin-custom-style', 'rtl', 'replace' );

        // media styles
        wp_enqueue_style(
            'wpuxss-eml-admin-media-style',
            $wpesq3_ml_dir . 'css/eml-admin-media.css',
            false,
            '',
            'all'
        );
        wp_style_add_data( 'wpuxss-eml-admin-media-style', 'rtl', 'replace' );


        wp_enqueue_style ( 'wp-jquery-ui-dialog' );


        // admin scripts
        wp_enqueue_script(
            'wpuxss-eml-admin-script',
            $wpesq3_ml_dir . 'js/eml-admin.js',
            array( 'jquery', 'jquery-ui-dialog' ),
            '',
            true
        );


        // scripts for list view :: /wp-admin/upload.php
        if ( isset( $current_screen ) && 'upload' === $current_screen->base && 'list' === $media_library_mode ) {

            wp_enqueue_script(
                'wpuxss-eml-media-list-script',
                $wpesq3_ml_dir . 'js/eml-media-list.js',
                array('jquery'),
                '',
                true
            );

            $media_list_l10n = array(
                '$_GET'             => wp_json_encode($_GET),
                'uncategorized'     => __( 'All Uncategorized', 'textdomain' ),
                'reset_all_filters' => __( 'Reset All Filters', 'textdomain' ),
                'filters_to_show'   => $wpesq3_ml_lib_options ? array_map( 'sanitize_key', $wpesq3_ml_lib_options['filters_to_show'] ) : array(
                    'types',
                    'dates',
                    'taxonomies'
                )
            );

            wp_localize_script(
                'wpuxss-eml-media-list-script',
                'wpesq3_ml_media_list_l10n',
                $media_list_l10n
            );
        }


        // scripts for grid view :: /wp-admin/upload.php
        if ( isset( $current_screen ) && 'upload' === $current_screen->base && 'grid' === $media_library_mode ) {

            wp_dequeue_script( 'media' );
            wp_enqueue_script(
                'wpuxss-eml-media-grid-script',
                $wpesq3_ml_dir . 'js/eml-media-grid.js',
                array( 'media-grid', 'wpuxss-eml-media-models-script', 'wpuxss-eml-media-views-script' ),
                '',
                true
            );

            $media_grid_l10n = array(
                'grid_show_caption' => (int) $wpesq3_ml_lib_options['grid_show_caption'],
                'grid_caption_type' => isset( $wpesq3_ml_lib_options['grid_caption_type'] ) ? sanitize_key( $wpesq3_ml_lib_options['grid_caption_type'] ) : 'title',
                'more_details' => __( 'More Details', 'textdomain' ),
                'less_details' => __( 'Less Details', 'textdomain' )
            );

            wp_localize_script(
                'wpuxss-eml-media-grid-script',
                'wpesq3_ml_media_grid_l10n',
                $media_grid_l10n
            );
        }
    }
}



/**
 *  wpesq3_ml_enqueue_media
 *
 *  @since    2.0
 *  @created  04/09/14
 */

add_action( 'wp_enqueue_media', 'wpesq3_ml_enqueue_media' );

if ( ! function_exists( 'wpesq3_ml_enqueue_media' ) ) {

    function wpesq3_ml_enqueue_media() {

        global $wpesq3_ml_dir,
               $wp_version,
               $current_screen;


        if ( ! is_admin() ) {
            return;
        }


        $media_library_mode = get_user_option( 'media_library_mode', get_current_user_id() ) ? get_user_option( 'media_library_mode', get_current_user_id() ) : 'grid';

        $wpesq3_ml_lib_options = get_option( 'wpesq3_ml_lib_options' );
        $wpesq3_ml_taxonomies = get_option( 'wpesq3_ml_taxonomies', array() );
        $media_taxonomies = get_object_taxonomies( 'attachment','object' );
        $media_taxonomy_names = array_keys( $media_taxonomies );

        $media_taxonomies_ready_for_script = array();
        $filter_taxonomy_names_ready_for_script = array();
        $compat_taxonomies_to_hide = array();


        $terms = get_terms( $media_taxonomy_names, array('fields'=>'all','get'=>'all') );
        $terms_id_tt_id_ready_for_script = wpesq3_ml_get_media_term_pairs( $terms, 'id=>tt_id' );


        $users_ready_for_script = array();

        if( current_user_can( 'manage_options' ) && $wpesq3_ml_lib_options ) {

            if ( in_array( 'authors', $wpesq3_ml_lib_options['filters_to_show'] ) ) {

                foreach( get_users( array( 'who' => 'authors' ) ) as $user ) {
                    $users_ready_for_script[] = array(
                        'user_id' => $user->ID,
                        'user_name' => $user->data->display_name
                    );
                }
            }
        }


        if ( function_exists( 'wp_terms_checklist' ) ) {

            foreach ( $media_taxonomies as $taxonomy ) {

                $taxonomy_terms = array();


                ob_start();

                    wp_terms_checklist( 0, array( 'taxonomy' => $taxonomy->name, 'checked_ontop' => false, 'walker' => new Walker_Media_Taxonomy_Uploader_Filter() ) );

                    $html = '';
                    if ( ob_get_contents() != false ) {
                        $html = ob_get_contents();
                    }

                ob_end_clean();


                $html = str_replace( '}{', '},{', $html );
                $html = '[' . $html . ']';
                $taxonomy_terms = json_decode( $html, true );

                $media_taxonomies_ready_for_script[$taxonomy->name] = array(
                    'singular_name' => $taxonomy->labels->singular_name,
                    'plural_name'   => $taxonomy->labels->name,
                    'term_list'     => $taxonomy_terms,
                );


                if ( (bool) $wpesq3_ml_taxonomies[$taxonomy->name]['media_uploader_filter'] ) {
                    $filter_taxonomy_names_ready_for_script[] = $taxonomy->name;
                }

                if ( ! (bool) $wpesq3_ml_taxonomies[$taxonomy->name]['media_popup_taxonomy_edit'] ) {
                    $compat_taxonomies_to_hide[] = $taxonomy->name;
                }
            } // foreach
        }


        // generic scripts

        wp_enqueue_script(
            'wpuxss-eml-media-models-script',
            $wpesq3_ml_dir . 'js/eml-media-models.js',
            array('media-models'),
            '',
            true
        );

        wp_enqueue_script(
            'wpuxss-eml-media-views-script',
            $wpesq3_ml_dir . 'js/eml-media-views.js',
            array('media-views'),
            '',
            true
        );


// TODO:
//        wp_enqueue_script(
//            'wpuxss-eml-tags-box-script',
//            '/wp-admin/js/tags-box.js',
//            array(),
//            EML_VERSION,
//            true
//        );


        $media_models_l10n = array(
            'media_orderby'   => $wpesq3_ml_lib_options ? sanitize_text_field( $wpesq3_ml_lib_options['media_orderby'] ) : 'date',
            'media_order'     => $wpesq3_ml_lib_options ? strtoupper( sanitize_text_field( $wpesq3_ml_lib_options['media_order'] ) ) : 'DESC',
            'bulk_edit_nonce' => wp_create_nonce( 'eml-bulk-edit-nonce' ),
            'natural_sort'    => (bool) $wpesq3_ml_lib_options['natural_sort']
        );

        wp_localize_script(
            'wpuxss-eml-media-models-script',
            'wpesq3_ml_media_models_l10n',
            $media_models_l10n
        );


        $media_views_l10n = array(
            'terms'                     => $terms_id_tt_id_ready_for_script,
            'taxonomies'                => $media_taxonomies_ready_for_script,
            'filter_taxonomies'         => $filter_taxonomy_names_ready_for_script,
            'compat_taxonomies'         => $media_taxonomy_names,
            'compat_taxonomies_to_hide' => $compat_taxonomies_to_hide,
            'is_tax_compat'             => count( $media_taxonomy_names ) - count( $compat_taxonomies_to_hide ) > 0 ? 1 : 0,
            'force_filters'             => (bool) $wpesq3_ml_lib_options['force_filters'],
            'filters_to_show'           => $wpesq3_ml_lib_options ? array_map( 'sanitize_key', $wpesq3_ml_lib_options['filters_to_show'] ) : array(
                'types',
                'dates',
                'taxonomies'
            ),
            'users'                     => $users_ready_for_script,
            'wp_version'                => $wp_version,
            'uncategorized'             => __( 'All Uncategorized', 'textdomain' ),
            'filter_by'                 => __( 'Filter by', 'textdomain' ),
            'in'                        => __( 'All', 'textdomain' ),
            'not_in'                    => __( 'Not in a', 'textdomain' ),
            'reset_filters'             => __( 'Reset All Filters', 'textdomain' ),
            'author'                    => __( 'author', 'textdomain' ),
            'authors'                   => __( 'authors', 'textdomain' ),
            'current_screen'            => isset( $current_screen ) ? $current_screen->id : '',

            'saveButton_success'        => __( 'Saved.', 'textdomain' ),
            'saveButton_failure'        => __( 'Something went wrong.', 'textdomain' ),
            'saveButton_text'           => __( 'Save Changes', 'textdomain' ),

            'select_all'                => __( 'Select All', 'textdomain' )
        );

        wp_localize_script(
            'wpuxss-eml-media-views-script',
            'wpesq3_ml_media_views_l10n',
            $media_views_l10n
        );


        if ( wpesq3_ml_media_shortcodes() ) {

            wp_enqueue_script(
                'wpuxss-eml-enhanced-medialist-script',
                $wpesq3_ml_dir . 'js/eml-enhanced-medialist.js',
                array('media-views'),
                '',
                true
            );

            wp_enqueue_script(
                'wpuxss-eml-media-editor-script',
                $wpesq3_ml_dir . 'js/eml-media-editor.js',
                array('media-editor','media-views', 'wpuxss-eml-enhanced-medialist-script'),
                '',
                true
            );

            $enhanced_medialist_l10n = array(
                'uploaded_to' => __( 'Uploaded to post #', 'textdomain' ),
                'based_on' => __( 'Based On', 'textdomain' )
            );

            wp_localize_script(
                'wpuxss-eml-enhanced-medialist-script',
                'wpesq3_ml_enhanced_medialist_l10n',
                $enhanced_medialist_l10n
            );
        }
    }
}




add_action("after_switch_theme", "wpesq3_on_activation_function");
function wpesq3_on_activation_function($oldname){

    wpesq3_eml_set_options();

    if ( is_multisite() && is_network_admin() ) {

        // network options
        wpesq3_ml_set_network_options();

        // common (site) options
        do_action( 'wpesq3_ml_set_site_options' );
    }
}


/**
 *  wpesq3_eml_set_options
 *
 *  @since    2.6
 *  @created  02/05/18
 */

if ( ! function_exists( 'wpesq3_eml_set_options' ) ) {

    function wpesq3_eml_set_options() {

        $wpesq3_ml_taxonomies = get_option( 'wpesq3_ml_taxonomies' );
        $wpesq3_ml_lib_options = get_option( 'wpesq3_ml_lib_options', array() );
        $wpesq3_ml_tax_options = get_option( 'wpesq3_ml_tax_options', array() );


        // taxonomies
        if ( false === $wpesq3_ml_taxonomies ) {

            $wpesq3_ml_taxonomies = array(

                'media_category' => array(
                    'assigned' => 1,
                    'eml_media' => 1,

                    'labels' => array(
                        'name' => __( 'Media Categories', 'textdomain' ),
                        'singular_name' => __( 'Media Category', 'textdomain' ),
                        'menu_name' => __( 'Media Categories', 'textdomain' ),
                        'all_items' => __( 'All Media Categories', 'textdomain' ),
                        'edit_item' => __( 'Edit Media Category', 'textdomain' ),
                        'view_item' => __( 'View Media Category', 'textdomain' ),
                        'update_item' => __( 'Update Media Category', 'textdomain' ),
                        'add_new_item' => __( 'Add New Media Category', 'textdomain' ),
                        'new_item_name' => __( 'New Media Category Name', 'textdomain' ),
                        'parent_item' => __( 'Parent Media Category', 'textdomain' ),
                        'parent_item_colon' => __( 'Parent Media Category:', 'textdomain' ),
                        'search_items' => __( 'Search Media Categories', 'textdomain' )
                    ),

                    'hierarchical' => 1,

                    'show_admin_column' => 1,
                    'admin_filter' => 1,          // list view filter
                    'media_uploader_filter' => 1, // grid view filter
                    'media_popup_taxonomy_edit' => 0, // since 2.7

                    'show_in_nav_menus' => 1,
                    'sort' => 0,
                    'show_in_rest' => 0,
                    'rewrite' => array(
                        'slug' => 'media_category',
                        'with_front' => 1
                    )
                )
            );
        }

        // false !== $wpesq3_ml_taxonomies
        else {

            $media_taxonomy_args_defaults = array(
                'assigned' => 1,
                'eml_media' => 1,
                'labels' => array(),

                'hierarchical' => 1,
                'show_admin_column' => 1,
                'admin_filter' => 1,          // list view filter
                'media_uploader_filter' => 1, // grid view filter
                'media_popup_taxonomy_edit' => 0, // since 2.7

                'show_in_nav_menus' => 1,
                'sort' => 0,
                'show_in_rest' => 0,
                'rewrite' => array(
                    'slug' => '',
                    'with_front' => 1
                )
            );

            $non_media_taxonomy_args_defaults = array(
                'assigned' => 0,
                'eml_media' => 0,
                'admin_filter' => 1, // since 2.7
                'media_uploader_filter' => 1, // since 2.7
                'media_popup_taxonomy_edit' => 0,
                'taxonomy_auto_assign' => 0
            );


            foreach( $wpesq3_ml_taxonomies as $taxonomy => $params ) {

                if ( empty( $params['eml_media'] ) ) {
                    $wpesq3_ml_taxonomies[$taxonomy]['eml_media'] = 0;
                }

                $defaults = (bool) $wpesq3_ml_taxonomies[$taxonomy]['eml_media'] ? $media_taxonomy_args_defaults : $non_media_taxonomy_args_defaults;

                $taxonomy_params = array_intersect_key( $params, $defaults );
                $wpesq3_ml_taxonomies[$taxonomy] = array_merge( $defaults, $taxonomy_params );

                if ( (bool) $wpesq3_ml_taxonomies[$taxonomy]['eml_media'] && empty( $params['rewrite']['slug'] ) ) {
                    $wpesq3_ml_taxonomies[$taxonomy]['rewrite']['slug'] = $taxonomy;
                }
            } // foreach
        } // if

        update_option( 'wpesq3_ml_taxonomies', $wpesq3_ml_taxonomies );


        // media library options
        $eml_lib_options_defaults = array(
            'enhance_media_shortcodes' => isset( $wpesq3_ml_tax_options['enhance_media_shortcodes'] ) ? (bool) $wpesq3_ml_tax_options['enhance_media_shortcodes'] : ( isset( $wpesq3_ml_tax_options['enhance_gallery_shortcode'] ) ? (bool) $wpesq3_ml_tax_options['enhance_gallery_shortcode'] : 0 ),
            'media_orderby' => isset( $wpesq3_ml_tax_options['media_orderby'] ) ? sanitize_text_field( $wpesq3_ml_tax_options['media_orderby'] ) : 'date',
            'media_order' => isset( $wpesq3_ml_tax_options['media_order'] ) ? strtoupper( sanitize_text_field( $wpesq3_ml_tax_options['media_order'] ) ) : 'DESC',
            'natural_sort' => 0,
            'force_filters' => isset( $wpesq3_ml_tax_options['force_filters'] ) ? (bool) $wpesq3_ml_tax_options['force_filters'] : 1,
            'filters_to_show' => array(
                'types',
                'dates',
                'taxonomies'
            ),
            'show_count' => isset( $wpesq3_ml_tax_options['show_count'] ) ? (bool) $wpesq3_ml_tax_options['show_count'] : 1,
            'include_children' => 1,
            'grid_show_caption' => 0,
            'grid_caption_type' => 'title',
            'search_in' => array(
                'titles',
                'captions',
                'descriptions'
            )
        );

        $wpesq3_ml_lib_options = array_intersect_key( $wpesq3_ml_lib_options, $eml_lib_options_defaults );
        $wpesq3_ml_lib_options = array_merge( $eml_lib_options_defaults, $wpesq3_ml_lib_options );

        update_option( 'wpesq3_ml_lib_options', $wpesq3_ml_lib_options );


        // taxonomy options
        $eml_tax_options_defaults = array(
            'tax_archives' => 0, // since 2.6
            'edit_all_as_hierarchical' => 0,
            'bulk_edit_save_button' => 0 // since 2.7
        );

        $wpesq3_ml_tax_options = array_intersect_key( $wpesq3_ml_tax_options, $eml_tax_options_defaults );
        $wpesq3_ml_tax_options = array_merge( $eml_tax_options_defaults, $wpesq3_ml_tax_options );

        update_option( 'wpesq3_ml_tax_options', $wpesq3_ml_tax_options );


        // MIME types
        $wpesq3_ml_site_mimes_backup = get_site_option( 'wpesq3_ml_mimes_backup' );

        if ( false === get_option( 'wpesq3_ml_mimes' ) ) {

            $allowed_mimes = get_allowed_mime_types();
            $default_mimes = array();

            foreach ( wp_get_mime_types() as $type => $mime ) {

                $wpesq3_ml_mimes[$type] = $default_mimes[$type] = array(
                    'mime'     => $mime,
                    'singular' => $mime,
                    'plural'   => $mime,
                    'filter'   => 0,
                    'upload'   => isset($allowed_mimes[$type]) ? 1 : 0
                );
            }

            $wpesq3_ml_mimes['pdf']['singular'] = 'PDF';
            $wpesq3_ml_mimes['pdf']['plural'] = 'PDFs';
            $wpesq3_ml_mimes['pdf']['filter'] = 1;

            update_option( 'wpesq3_ml_mimes', $wpesq3_ml_mimes );

            if ( false === $wpesq3_ml_site_mimes_backup ) {
                update_site_option( 'wpesq3_ml_mimes_backup', $default_mimes );
                $wpesq3_ml_site_mimes_backup = $default_mimes;
            }
        }

        if ( is_multisite() ) {

            $wpesq3_ml_mimes_backup = get_option( 'wpesq3_ml_mimes_backup' );
            delete_option( 'wpesq3_ml_mimes_backup' );

            if ( false === $wpesq3_ml_site_mimes_backup ) {
                update_site_option( 'wpesq3_ml_mimes_backup', $wpesq3_ml_mimes_backup );
            }
        }

        do_action( 'wpesq3_eml_set_options' );
    }
}



/**
 *  wpesq3_ml_set_network_options
 *
 *  @since    2.6.3
 *  @created  21/05/18
 */

if ( ! function_exists( 'wpesq3_ml_set_network_options' ) ) {

    function wpesq3_ml_set_network_options() {

        $wpesq3_ml_network_options = get_site_option( 'wpesq3_ml_network_options', array() );

        $wpesq3_ml_network_options_defaults = array(
            'media_settings' => 1,
            'utilities' => 1
        );

        $wpesq3_ml_network_options = array_intersect_key( $wpesq3_ml_network_options, $wpesq3_ml_network_options_defaults );
        $wpesq3_ml_network_options = array_merge( $wpesq3_ml_network_options_defaults, $wpesq3_ml_network_options );

        update_site_option( 'wpesq3_ml_network_options', $wpesq3_ml_network_options );
    }
}

?>
