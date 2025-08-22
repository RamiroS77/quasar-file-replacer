<?php
/**
 * Plugin Name: Quasar File Replacer
 * Description: Backend-only plugin to select an existing Media Library file and replace it with an uploaded file. Stores multiple targets and optional notes.
 * Version: 1.0.0
 * Author: Ramiro Suarez (QuasarCR)
 * Author URI: https://www.quasarcr.com/
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'qfr_PLUGIN_FILE', __FILE__ );
define( 'qfr_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'qfr_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once qfr_PLUGIN_DIR . 'admin-ui.php';

/**
 * Enqueue admin assets & media frame
 */
 
function qfr_admin_enqueue( $hook ) {
    if ( $hook !== 'toplevel_page_quasar-file-replacer' ) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_script(
        'qfr-admin',
        qfr_PLUGIN_URL . 'js/admin.js',
        array( 'jquery' ),
        '1.1.0',
        true
    );
    wp_localize_script( 'qfr-admin', 'EFR', array(
        'ajax'  => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'qfr_nonce' ),
    ));
    wp_enqueue_style(
        'qfr-admin',
        qfr_PLUGIN_URL . 'css/admin.css',
        array(),
        '1.1.0'
    );
}
add_action( 'admin_enqueue_scripts', 'qfr_admin_enqueue' );

/**
 * Handle save (admin-post)
 */
 
add_action( 'admin_post_qfr_save', 'qfr_save_file_data' );

/**
 * AJAX: add a fresh row
 */

function qfr_ajax_add_row() {
    check_ajax_referer( 'qfr_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
    }
    $index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : 0;
    $html  = qfr_generate_row( $index, array( 'file_id' => '', 'file_url' => '', 'note' => '' ) );
    wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_qfr_add_row', 'qfr_ajax_add_row' );
