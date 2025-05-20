<?php

if ( ! defined( 'WP_DEBUG' ) ) {
    die( 'Direct access forbidden.' );
}

// Laad de stijl van het thema
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
});

// Include de dealer locator functionaliteit
require_once get_stylesheet_directory() . '/dealer-locator.php';

// Include de admin pagina voor dealers (CRUD functionaliteit)
require_once get_stylesheet_directory() . '/dealer-admin.php';

?>