<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Menu
 */
 
function qfr_admin_menu() {
    add_menu_page(
        'Quasar File Replacer',
        'Quasar File Replacer',
        'manage_options',
        'quasar-file-replacer',
        'qfr_admin_page',
        'dashicons-media-code',
        80
    );
}
add_action( 'admin_menu', 'qfr_admin_menu' );


/**
 * Admin Page
 */
function qfr_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $file_data = get_option( 'qfr_file_data', array() );
    ?>
    <div class="wrap">
        <h1>Quasar File Replacer</h1>
        <p class="description">Pick a target file from the Media Library, then upload a replacement file to overwrite it on disk. Optionally store a name/notes for reference. Clic Save Changes to apply!</p>

        <form id="qfr-form" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'qfr_save_data', 'qfr_nonce' ); ?>
            <input type="hidden" name="action" value="qfr_save">

            <div id="qfr-container">
                <?php
                if ( ! empty( $file_data ) && is_array( $file_data ) ) {
                    foreach ( $file_data as $index => $data ) {
                        echo qfr_generate_row( $index, $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                } else {
                    echo qfr_generate_row( 0, array( 'file_id' => '', 'file_url' => '', 'note' => '' ) ); // phpcs:ignore
                }
                ?>
            </div>

            <p>
                <button type="button" class="button button-secondary" id="qfr-add-row">+ Add Another Target</button>
            </p>

            <p>
                <input type="submit" class="button button-primary" value="Save Changes">
            </p>
        </form>
        <hr>
        <details>
            <summary><strong>Notes & Tips</strong></summary>
            <ul class="qfr-notes">
				<li>Overwrite with same file extension. Replace .pdf with .pdf (or .PDF) but don't replace a .jpeg with .jpg or a.gif.</li>
                <li>Replacing images will overwrite the original file on disk but will not regenerate thumbnails. If you change dimensions, consider running a thumbnail regeneration plugin.</li>
                <li>Non-image files replace 1:1 on disk.</li>
                <li>Make sure your uploads directory is writable by PHP.</li>
				<li>Use notes to remember or instruct other users on the replacement.</li>
            </ul>
        </details>
    </div>
    <?php
}


/**
 * Render a row
 */
function qfr_generate_row( $index, $data ) {
    $file_id  = isset( $data['file_id'] ) ? intval( $data['file_id'] ) : 0;
    $file_url = $file_id ? wp_get_attachment_url( $file_id ) : ( isset( $data['file_url'] ) ? esc_url_raw( $data['file_url'] ) : '' );
    $note     = isset( $data['note'] ) ? sanitize_text_field( $data['note'] ) : '';

    ob_start();
    ?>
    <div class="qfr-row">
        <div class="qfr-col">
            <label class="qfr-label">Target file</label>
            <input type="hidden" class="qfr-file-id" name="efr[<?php echo esc_attr( $index ); ?>][file_id]" value="<?php echo esc_attr( $file_id ); ?>">
            <button type="button" class="button qfr-select-media">Select file to replace</button>
            <div class="qfr-selected-file"><?php echo $file_url ? esc_html( $file_url ) : '<em>No file selected</em>'; ?></div>
        </div>

        <div class="qfr-col qfr-upload">
            <label class="qfr-label">Upload replacement</label>
            <input type="file" name="qfr_upload_<?php echo esc_attr( $index ); ?>" class="qfr-file">
        </div>

        <div class="qfr-col qfr-note">
            <label class="qfr-label">Name / Note (optional)</label>
            <input type="text" name="efr[<?php echo esc_attr( $index ); ?>][note]" value="<?php echo esc_attr( $note ); ?>" placeholder="e.g., Hi-res July 2025">
        </div>
    </div>
    <?php
    return ob_get_clean();
}


/**
 * Save handler
 */
function qfr_save_file_data() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Forbidden', 403 );
    }
    if ( ! isset( $_POST['qfr_nonce'] ) || ! wp_verify_nonce( $_POST['qfr_nonce'], 'qfr_save_data' ) ) {
        wp_die( 'Invalid nonce', 400 );
    }

    $updated_data = array();

    if ( isset( $_POST['efr'] ) && is_array( $_POST['efr'] ) ) {
        foreach ( $_POST['efr'] as $index => $item ) {
            $file_id = isset( $item['file_id'] ) ? intval( $item['file_id'] ) : 0;
            $note    = isset( $item['note'] ) ? sanitize_text_field( $item['note'] ) : '';

            if ( ! $file_id ) {
                continue;
            }

            $file_url   = wp_get_attachment_url( $file_id );
            $target_path = get_attached_file( $file_id );

            // If a replacement was uploaded for this row, overwrite the target on disk
            $field_key = 'qfr_upload_' . intval( $index );
            if ( isset( $_FILES[ $field_key ] ) && ! empty( $_FILES[ $field_key ]['tmp_name'] ) ) {
                // Use WordPress to handle the upload to a temp location first
                $overrides = array( 'test_form' => false, 'mimes' => null );
                $uploaded  = wp_handle_upload( $_FILES[ $field_key ], $overrides );

                if ( ! isset( $uploaded['error'] ) && isset( $uploaded['file'] ) && $target_path && file_exists( $target_path ) ) {
                    // Overwrite the original file
                    $tmp_path = $uploaded['file'];
                    // Attempt to preserve permissions
                    $perms = fileperms( $target_path );
                    $replaced = copy( $tmp_path, $target_path );
                    if ( $replaced ) {
                        @chmod( $target_path, $perms ? $perms & 0777 : 0644 );
                        // Optionally, update attachment file size meta
                        $filesize = filesize( $target_path );
                        if ( $filesize ) {
                            update_post_meta( $file_id, 'filesize', $filesize );
                        }
                    }
                    // Clean temp file
                    @unlink( $tmp_path );
                }
            }

            $updated_data[ $index ] = array(
                'file_id'  => $file_id,
                'file_url' => $file_url,
                'note'     => $note,
            );
        }
    }

    update_option( 'qfr_file_data', $updated_data );

    wp_safe_redirect( admin_url( 'admin.php?page=quasar-file-replacer&updated=1' ) );
    exit;
}
