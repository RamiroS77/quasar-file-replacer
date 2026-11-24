<?php
/**
 * Plugin Name: Quasar File Replacer
 * Description: Backend-only plugin to replace existing PDF attachments while preserving their URLs. Stores multiple targets and optional notes.
 * Version: 1.1.0
 * Author: Ramiro Suarez (QuasarCR)
 * Author URI: https://www.quasarcr.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'QFR_VERSION' ) ) {
	define( 'QFR_VERSION', '1.1.0' );
}

if ( ! defined( 'QFR_PLUGIN_FILE' ) ) {
	define( 'QFR_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'QFR_PLUGIN_DIR' ) ) {
	define( 'QFR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'QFR_PLUGIN_URL' ) ) {
	define( 'QFR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once QFR_PLUGIN_DIR . 'admin-ui.php';

/**
 * Enqueue admin assets only on the plugin page.
 *
 * The existing JavaScript and CSS files are retained and do not need changes.
 *
 * @param string $hook Current admin page hook.
 */
function qfr_admin_enqueue( $hook ) {
	if ( 'toplevel_page_quasar-file-replacer' !== $hook ) {
		return;
	}

	wp_enqueue_media();

	wp_enqueue_script(
		'qfr-admin',
		QFR_PLUGIN_URL . 'js/admin.js',
		array( 'jquery' ),
		QFR_VERSION,
		true
	);

	wp_localize_script(
		'qfr-admin',
		'EFR',
		array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'qfr_nonce' ),
		)
	);

	wp_enqueue_style(
		'qfr-admin',
		QFR_PLUGIN_URL . 'css/admin.css',
		array(),
		QFR_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'qfr_admin_enqueue' );

/**
 * Handle the authenticated admin form submission.
 */
add_action( 'admin_post_qfr_save', 'qfr_save_file_data' );

/**
 * AJAX: add a fresh target row.
 */
function qfr_ajax_add_row() {
	check_ajax_referer( 'qfr_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Forbidden.' ), 403 );
	}

	$index = isset( $_POST['index'] ) ? absint( wp_unslash( $_POST['index'] ) ) : 0;
	$html  = qfr_generate_row(
		$index,
		array(
			'file_id' => 0,
			'file_url' => '',
			'note' => '',
		)
	);

	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_qfr_add_row', 'qfr_ajax_add_row' );
