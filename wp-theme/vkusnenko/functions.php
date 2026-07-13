<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'after_setup_theme', function () {
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
} );

add_filter( 'show_admin_bar', '__return_false' );
add_filter( 'redirect_canonical', '__return_false' );

add_action( 'init', function () {
    register_taxonomy( 'menu_category', array( 'menu_item' ), array(
        'labels'            => array(
            'name'          => 'Категории меню',
            'singular_name' => 'Категория меню',
            'menu_name'     => 'Категории',
        ),
        'public'            => false,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'hierarchical'      => true,
        'rewrite'           => false,
    ) );

    register_post_type( 'menu_item', array(
        'labels'       => array(
            'name'          => 'Блюда',
            'singular_name' => 'Блюдо',
            'add_new_item'  => 'Добавить блюдо',
            'edit_item'     => 'Редактировать блюдо',
            'menu_name'     => 'Меню кафе',
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'menu_icon'    => 'dashicons-food',
        'supports'     => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
        'taxonomies'   => array( 'menu_category' ),
        'rewrite'      => false,
    ) );
} );

add_action( 'add_meta_boxes', function () {
    add_meta_box( 'vkusnenko_menu_details', 'Параметры блюда', 'vkusnenko_menu_details_box', 'menu_item', 'normal', 'high' );
} );

function vkusnenko_menu_details_box( $post ) {
    wp_nonce_field( 'vkusnenko_menu_details', 'vkusnenko_menu_details_nonce' );

    $fields = array(
        '_vkusnenko_price'    => 'Цена',
        '_vkusnenko_weight'   => 'Вес / порция',
        '_vkusnenko_badge'    => 'Бейдж',
        '_vkusnenko_fallback' => 'Fallback-картинка',
    );

    foreach ( $fields as $key => $label ) {
        $value = get_post_meta( $post->ID, $key, true );
        echo '<p><label style="display:block;font-weight:600;margin-bottom:6px" for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
        echo '<input style="width:100%" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '"></p>';
    }
}

add_action( 'save_post_menu_item', function ( $post_id ) {
    if ( ! isset( $_POST['vkusnenko_menu_details_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vkusnenko_menu_details_nonce'] ) ), 'vkusnenko_menu_details' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    foreach ( array( '_vkusnenko_price', '_vkusnenko_weight', '_vkusnenko_badge', '_vkusnenko_fallback' ) as $key ) {
        $value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
        update_post_meta( $post_id, $key, $value );
    }
} );

add_action( 'rest_api_init', function () {
    register_rest_route( 'vkusnenko/v1', '/menu', array(
        'methods'             => 'GET',
        'callback'            => 'vkusnenko_rest_menu',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'vkusnenko/v1', '/gallery', array(
        'methods'             => 'GET',
        'callback'            => 'vkusnenko_rest_gallery',
        'permission_callback' => '__return_true',
    ) );
} );

function vkusnenko_rest_menu() {
    $posts = get_posts( array(
        'post_type'      => 'menu_item',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
    ) );

    return array_map( function ( $post ) {
        $terms    = get_the_terms( $post, 'menu_category' );
        $category = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
        $image    = get_the_post_thumbnail_url( $post, 'large' );

        return array(
            'id'          => $post->ID,
            'category'    => $category,
            'title'       => get_the_title( $post ),
            'price'       => get_post_meta( $post->ID, '_vkusnenko_price', true ),
            'weight'      => get_post_meta( $post->ID, '_vkusnenko_weight', true ),
            'description' => wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ) ),
            'image'       => $image ? $image : '',
            'fallback'    => get_post_meta( $post->ID, '_vkusnenko_fallback', true ),
            'badge'       => get_post_meta( $post->ID, '_vkusnenko_badge', true ),
        );
    }, $posts );
}

function vkusnenko_rest_gallery() {
    $attachments = get_posts( array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'meta_key'       => '_vkusnenko_gallery',
        'meta_value'     => '1',
    ) );

    return array(
        'items' => array_map( function ( $post ) {
            return array(
                'title'    => get_the_title( $post ),
                'caption'  => $post->post_excerpt,
                'src'      => wp_get_attachment_image_url( $post->ID, 'large' ),
                'fallback' => get_post_meta( $post->ID, '_vkusnenko_fallback', true ),
            );
        }, $attachments ),
    );
}
