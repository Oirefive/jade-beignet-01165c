<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$theme_dir = get_template_directory();

function vkusnenko_import_json( $path ) {
    $raw = file_get_contents( $path );
    if ( false === $raw ) {
        WP_CLI::warning( 'Cannot read ' . $path );
        return array();
    }

    $raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
    $data = json_decode( $raw, true );
    if ( ! is_array( $data ) ) {
        WP_CLI::warning( 'Invalid JSON: ' . $path );
        return array();
    }

    return $data;
}

function vkusnenko_import_media( $relative_path, $title = '', $caption = '' ) {
    $clean_relative = preg_replace( '/\?.*$/', '', $relative_path );
    $source         = get_template_directory() . '/' . ltrim( $clean_relative, '/' );

    if ( ! file_exists( $source ) ) {
        return 0;
    }

    $existing = get_posts( array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => 1,
        'meta_key'       => '_vkusnenko_source',
        'meta_value'     => $clean_relative,
        'fields'         => 'ids',
    ) );

    if ( $existing ) {
        $attachment_id = (int) $existing[0];
        wp_update_post( array(
            'ID'           => $attachment_id,
            'post_title'   => $title ? $title : get_the_title( $attachment_id ),
            'post_excerpt' => $caption,
        ) );
        return $attachment_id;
    }

    $file_array = array(
        'name'     => basename( $clean_relative ),
        'tmp_name' => wp_tempnam( basename( $clean_relative ) ),
    );

    copy( $source, $file_array['tmp_name'] );

    $attachment_id = media_handle_sideload( $file_array, 0, $title );
    if ( is_wp_error( $attachment_id ) ) {
        @unlink( $file_array['tmp_name'] );
        WP_CLI::warning( 'Media import failed: ' . $clean_relative . ' - ' . $attachment_id->get_error_message() );
        return 0;
    }

    wp_update_post( array(
        'ID'           => $attachment_id,
        'post_title'   => $title ? $title : get_the_title( $attachment_id ),
        'post_excerpt' => $caption,
    ) );
    update_post_meta( $attachment_id, '_vkusnenko_source', $clean_relative );

    return $attachment_id;
}

$menu   = vkusnenko_import_json( $theme_dir . '/assets/data/menu.json' );
$photos = vkusnenko_import_json( $theme_dir . '/assets/data/photos.json' );

$count = 0;
foreach ( $menu as $index => $item ) {
    $title = isset( $item['title'] ) ? $item['title'] : '';
    if ( '' === $title ) {
        continue;
    }

    $category = isset( $item['category'] ) ? $item['category'] : 'Меню';
    $term     = term_exists( $category, 'menu_category' );
    if ( ! $term ) {
        $term = wp_insert_term( $category, 'menu_category' );
    }

    $existing = get_posts( array(
        'post_type'      => 'menu_item',
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'posts_per_page' => 1,
        'meta_key'       => '_vkusnenko_import_key',
        'meta_value'     => sanitize_title( $title ),
        'fields'         => 'ids',
    ) );

    $post_data = array(
        'post_type'    => 'menu_item',
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_content' => isset( $item['description'] ) ? $item['description'] : '',
        'menu_order'   => $index,
    );

    if ( $existing ) {
        $post_data['ID'] = (int) $existing[0];
        $post_id         = wp_update_post( $post_data );
    } else {
        $post_id = wp_insert_post( $post_data );
    }

    if ( ! $post_id || is_wp_error( $post_id ) ) {
        WP_CLI::warning( 'Cannot import menu item: ' . $title );
        continue;
    }

    wp_set_object_terms( $post_id, array( $category ), 'menu_category' );
    update_post_meta( $post_id, '_vkusnenko_import_key', sanitize_title( $title ) );
    update_post_meta( $post_id, '_vkusnenko_price', isset( $item['price'] ) ? $item['price'] : '' );
    update_post_meta( $post_id, '_vkusnenko_weight', isset( $item['weight'] ) ? $item['weight'] : '' );
    update_post_meta( $post_id, '_vkusnenko_badge', isset( $item['badge'] ) ? $item['badge'] : '' );
    update_post_meta( $post_id, '_vkusnenko_fallback', isset( $item['fallback'] ) ? $item['fallback'] : '' );

    if ( ! empty( $item['image'] ) ) {
        $attachment_id = vkusnenko_import_media( $item['image'], $title );
        if ( $attachment_id ) {
            set_post_thumbnail( $post_id, $attachment_id );
        }
    }

    $count++;
}

$gallery_count = 0;
foreach ( isset( $photos['items'] ) ? $photos['items'] : array() as $index => $item ) {
    if ( empty( $item['src'] ) ) {
        continue;
    }

    $attachment_id = vkusnenko_import_media(
        $item['src'],
        isset( $item['title'] ) ? $item['title'] : '',
        isset( $item['caption'] ) ? $item['caption'] : ''
    );

    if ( $attachment_id ) {
        update_post_meta( $attachment_id, '_vkusnenko_gallery', '1' );
        update_post_meta( $attachment_id, '_vkusnenko_fallback', isset( $item['fallback'] ) ? $item['fallback'] : '' );
        wp_update_post( array(
            'ID'         => $attachment_id,
            'menu_order' => $index,
        ) );
        $gallery_count++;
    }
}

WP_CLI::success( sprintf( 'Imported %d menu items and %d gallery images.', $count, $gallery_count ) );
