<?php
/**
 * Admin interface and secure PDF replacement handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maximum accepted replacement PDF size (25 MiB).
 *
 * @return int
 */
function qfr_max_upload_bytes() {
	return (int) apply_filters( 'qfr_max_upload_bytes', 25 * MB_IN_BYTES );
}

/**
 * Register the plugin menu.
 */
function qfr_admin_menu() {
	add_menu_page(
		'Quasar File Replacer',
		'Quasar File Replacer',
		'manage_options',
		'quasar-file-replacer',
		'qfr_admin_page',
		'dashicons-media-document',
		80
	);
}
add_action( 'admin_menu', 'qfr_admin_menu' );

/**
 * Render admin notices stored by the last save operation.
 */
function qfr_admin_notices() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$key     = 'qfr_notice_' . get_current_user_id();
	$notices = get_transient( $key );

	if ( empty( $notices ) || ! is_array( $notices ) ) {
		return;
	}

	delete_transient( $key );

	foreach ( $notices as $notice ) {
		$type    = ( isset( $notice['type'] ) && 'success' === $notice['type'] ) ? 'success' : 'warning';
		$message = isset( $notice['message'] ) ? $notice['message'] : '';

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}
add_action( 'admin_notices', 'qfr_admin_notices' );

/**
 * Render the plugin page.
 */
function qfr_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'quasar-file-replacer' ), '', array( 'response' => 403 ) );
	}

	$file_data = get_option( 'qfr_file_data', array() );
	if ( ! is_array( $file_data ) ) {
		$file_data = array();
	}
	?>
	<div class="wrap">
		<h1>Quasar File Replacer</h1>
		<p class="description">
			Select an existing PDF attachment, then upload a replacement PDF. The existing filename and URL are preserved.
			Only PDF attachments located inside the WordPress uploads directory can be replaced.
		</p>

		<form id="qfr-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'qfr_save_data', 'qfr_nonce' ); ?>
			<input type="hidden" name="action" value="qfr_save">

			<div id="qfr-container">
				<?php
				if ( ! empty( $file_data ) ) {
					foreach ( $file_data as $index => $data ) {
						echo qfr_generate_row( $index, $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				} else {
					echo qfr_generate_row( 0, array( 'file_id' => 0, 'file_url' => '', 'note' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
			<summary><strong>Notes &amp; Tips</strong></summary>
			<ul class="qfr-notes">
				<li>This version replaces PDF files only. Both the existing target and uploaded replacement must be valid PDFs.</li>
				<li>The original attachment URL and filename are preserved.</li>
				<li>A copy of the original PDF is saved in a protected backup directory before replacement.</li>
				<li>Backups are stored under <code>wp-content/qfr-private-backups</code>. On Nginx servers, configure a deny rule for this directory as .htaccess rules are not read by Nginx.</li>
				<li>The replacement limit is 25 MiB by default. Developers can change it using the <code>qfr_max_upload_bytes</code> filter.</li>
				<li>Use notes to remember or instruct other administrators about each replacement target.</li>
			</ul>
		</details>
	</div>
	<?php
}

/**
 * Render one target row.
 *
 * @param int|string $index Row index.
 * @param array      $data  Row data.
 * @return string
 */
function qfr_generate_row( $index, $data ) {
	$index    = absint( $index );
	$file_id  = isset( $data['file_id'] ) ? absint( $data['file_id'] ) : 0;
	$file_url = $file_id ? wp_get_attachment_url( $file_id ) : '';
	$note     = isset( $data['note'] ) ? sanitize_text_field( $data['note'] ) : '';

	ob_start();
	?>
	<div class="qfr-row">
		<div class="qfr-col">
			<label class="qfr-label">Target PDF</label>
			<input type="hidden" class="qfr-file-id" name="efr[<?php echo esc_attr( $index ); ?>][file_id]" value="<?php echo esc_attr( $file_id ); ?>">
			<button type="button" class="button qfr-select-media">Select PDF to replace</button>
			<div class="qfr-selected-file"><?php echo $file_url ? esc_html( $file_url ) : '<em>No PDF selected</em>'; ?></div>
		</div>

		<div class="qfr-col qfr-upload">
			<label class="qfr-label">Upload replacement PDF</label>
			<input type="file" name="qfr_upload_<?php echo esc_attr( $index ); ?>" class="qfr-file" accept=".pdf,application/pdf">
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
 * Normalize a filesystem path for safe prefix comparisons.
 *
 * @param string $path Path.
 * @return string
 */
function qfr_normalize_path( $path ) {
	return untrailingslashit( wp_normalize_path( $path ) );
}

/**
 * Confirm a path is within a directory, with a separator boundary.
 *
 * @param string $path      Candidate path.
 * @param string $directory Parent directory.
 * @return bool
 */
function qfr_path_is_inside( $path, $directory ) {
	$path      = qfr_normalize_path( $path );
	$directory = qfr_normalize_path( $directory );

	return ( 0 === strpos( $path, $directory . '/' ) );
}

/**
 * Reject symbolic links in the target path between uploads and the file.
 *
 * @param string $uploads_real Canonical uploads directory.
 * @param string $target_path  Original attachment path.
 * @return bool
 */
function qfr_path_has_symlink_component( $uploads_real, $target_path ) {
	$uploads_real = qfr_normalize_path( $uploads_real );
	$target_path  = wp_normalize_path( $target_path );

	if ( ! qfr_path_is_inside( $target_path, $uploads_real ) ) {
		return true;
	}

	$relative = ltrim( substr( $target_path, strlen( $uploads_real ) ), '/' );
	$parts    = $relative ? explode( '/', $relative ) : array();
	$current  = $uploads_real;

	foreach ( $parts as $part ) {
		$current .= '/' . $part;
		if ( is_link( $current ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Validate that an attachment is an existing regular PDF inside uploads.
 *
 * Never accepts a filesystem path from the request.
 *
 * @param int $attachment_id Attachment ID.
 * @return array|WP_Error Validated attachment data or error.
 */
function qfr_get_valid_pdf_target( $attachment_id ) {
	$attachment_id = absint( $attachment_id );

	if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
		return new WP_Error( 'qfr_invalid_attachment', 'The selected target is not a valid Media Library attachment.' );
	}

	if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
		return new WP_Error( 'qfr_forbidden_attachment', 'You do not have permission to replace this attachment.' );
	}

	$mime_type = get_post_mime_type( $attachment_id );
	if ( 'application/pdf' !== $mime_type ) {
		return new WP_Error( 'qfr_target_not_pdf', 'Only existing PDF attachments can be replaced.' );
	}

	$target_path = get_attached_file( $attachment_id );
	if ( ! is_string( $target_path ) || '' === $target_path || is_link( $target_path ) || ! file_exists( $target_path ) || ! is_file( $target_path ) ) {
		return new WP_Error( 'qfr_target_missing', 'The original PDF is missing or is not a regular file.' );
	}

	$uploads     = wp_upload_dir();
	$uploads_dir = isset( $uploads['basedir'] ) ? $uploads['basedir'] : '';
	$uploads_real = $uploads_dir ? realpath( $uploads_dir ) : false;
	$target_real  = realpath( $target_path );

	if ( ! $uploads_real || ! $target_real ) {
		return new WP_Error( 'qfr_path_unavailable', 'The uploads directory or target path could not be resolved.' );
	}

	if ( ! qfr_path_is_inside( $target_real, $uploads_real ) || qfr_path_has_symlink_component( $uploads_real, $target_path ) ) {
		return new WP_Error( 'qfr_path_rejected', 'The target path is outside the uploads directory or contains a symbolic link.' );
	}

	if ( strtolower( pathinfo( $target_real, PATHINFO_EXTENSION ) ) !== 'pdf' ) {
		return new WP_Error( 'qfr_target_extension', 'The existing target filename does not have a PDF extension.' );
	}

	$handle = @fopen( $target_real, 'rb' );
	if ( ! $handle ) {
		return new WP_Error( 'qfr_target_unreadable', 'The original PDF cannot be read.' );
	}
	$header = fread( $handle, 1024 );
	fclose( $handle );

	if ( false === $header || false === strpos( $header, '%PDF-' ) ) {
		return new WP_Error( 'qfr_target_signature', 'The existing target does not contain a valid PDF signature.' );
	}

	return array(
		'id'          => $attachment_id,
		'path'        => $target_real,
		'url'         => wp_get_attachment_url( $attachment_id ),
		'uploads_dir' => $uploads_real,
	);
}

/**
 * Validate the uploaded temporary file as a PDF.
 *
 * @param array $file PHP upload array.
 * @return true|WP_Error
 */
function qfr_validate_uploaded_pdf( $file ) {
	if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
		return new WP_Error( 'qfr_upload_error', 'The replacement upload did not complete successfully.' );
	}

	if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'qfr_upload_invalid', 'The uploaded temporary file is invalid.' );
	}

	if ( empty( $file['name'] ) || strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) ) !== 'pdf' ) {
		return new WP_Error( 'qfr_upload_extension', 'The replacement filename must end in .pdf.' );
	}

	$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
	if ( $size < 1 || $size > qfr_max_upload_bytes() ) {
		return new WP_Error( 'qfr_upload_size', 'The replacement PDF is empty or exceeds the configured 25 MiB upload limit.' );
	}

	$finfo_mime = '';
	if ( function_exists( 'finfo_open' ) ) {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		if ( $finfo ) {
			$finfo_mime = finfo_file( $finfo, $file['tmp_name'] );
			finfo_close( $finfo );
		}
	}

	if ( 'application/pdf' !== $finfo_mime ) {
		return new WP_Error( 'qfr_upload_mime', 'The uploaded file was not identified as application/pdf.' );
	}

	$handle = @fopen( $file['tmp_name'], 'rb' );
	if ( ! $handle ) {
		return new WP_Error( 'qfr_upload_read', 'The uploaded PDF could not be read.' );
	}
	$header = fread( $handle, 1024 );
	fclose( $handle );

	if ( false === $header || false === strpos( $header, '%PDF-' ) ) {
		return new WP_Error( 'qfr_upload_signature', 'The uploaded file does not contain a valid PDF signature.' );
	}

	return true;
}

/**
 * Create a protected backup directory for the original PDF.
 *
 * Backups are outside uploads. Apache protection is written as an additional
 * safeguard; Nginx administrators must add an equivalent deny rule.
 *
 * @return string|WP_Error
 */
function qfr_get_backup_directory() {
	$directory = trailingslashit( WP_CONTENT_DIR ) . 'qfr-private-backups';

	if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
		return new WP_Error( 'qfr_backup_directory', 'Could not create the private backup directory.' );
	}

	if ( is_link( $directory ) ) {
		return new WP_Error( 'qfr_backup_symlink', 'The backup directory must not be a symbolic link.' );
	}

	$index_file = trailingslashit( $directory ) . 'index.php';
	if ( ! file_exists( $index_file ) ) {
		$written = file_put_contents( $index_file, "<?php\n// Silence is golden.\n", LOCK_EX );
		if ( false === $written ) {
			return new WP_Error( 'qfr_backup_index', 'Could not secure the backup directory.' );
		}
	}

	$htaccess_file = trailingslashit( $directory ) . '.htaccess';
	if ( ! file_exists( $htaccess_file ) ) {
		$rules = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n";
		if ( false === file_put_contents( $htaccess_file, $rules, LOCK_EX ) ) {
			return new WP_Error( 'qfr_backup_htaccess', 'Could not write access protection for the backup directory.' );
		}
	}

	@chmod( $directory, 0755 );
	@chmod( $index_file, 0644 );
	@chmod( $htaccess_file, 0644 );

	return $directory;
}

/**
 * Replace the target PDF after validation and backup.
 *
 * @param array $target Validated target information.
 * @param array $file   PHP upload array.
 * @return true|WP_Error
 */
function qfr_replace_pdf( $target, $file ) {
	$valid_upload = qfr_validate_uploaded_pdf( $file );
	if ( is_wp_error( $valid_upload ) ) {
		return $valid_upload;
	}

	$target_path = $target['path'];
	$directory   = dirname( $target_path );

	// Recheck immediately before writing to reduce path-change race exposure.
	if ( is_link( $target_path ) || ! is_file( $target_path ) || ! is_writable( $directory ) || ! is_writable( $target_path ) ) {
		return new WP_Error( 'qfr_target_not_writable', 'The target PDF is no longer a writable regular file.' );
	}

	$backup_dir = qfr_get_backup_directory();
	if ( is_wp_error( $backup_dir ) ) {
		return $backup_dir;
	}

	$backup_name = sprintf(
		'attachment-%d-%s.pdf',
		(int) $target['id'],
		gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 8, false, false )
	);
	$backup_path = trailingslashit( $backup_dir ) . $backup_name;

	if ( ! @copy( $target_path, $backup_path ) ) {
		return new WP_Error( 'qfr_backup_failed', 'The original PDF could not be backed up. No replacement was made.' );
	}
	@chmod( $backup_path, 0600 );

	// Create the replacement temp file in the same directory so rename is atomic on the same filesystem.
	$temp_path = @tempnam( $directory, '.qfr-' );
	if ( ! $temp_path ) {
		return new WP_Error( 'qfr_temp_failed', 'Could not create a temporary replacement file. The original remains unchanged.' );
	}

	$copied = @copy( $file['tmp_name'], $temp_path );
	if ( ! $copied ) {
		@unlink( $temp_path );
		return new WP_Error( 'qfr_copy_failed', 'Could not stage the replacement PDF. The original remains unchanged.' );
	}

	// Verify the staged copy too, rather than relying only on the PHP upload temp file.
	$staged_size = @filesize( $temp_path );
	$staged_handle = @fopen( $temp_path, 'rb' );
	$staged_header = $staged_handle ? fread( $staged_handle, 1024 ) : false;
	if ( $staged_handle ) {
		fclose( $staged_handle );
	}

	if ( ! $staged_size || $staged_size > qfr_max_upload_bytes() || false === $staged_header || false === strpos( $staged_header, '%PDF-' ) ) {
		@unlink( $temp_path );
		return new WP_Error( 'qfr_staged_invalid', 'The staged replacement failed final validation. The original remains unchanged.' );
	}

	$permissions = @fileperms( $target_path );
	$permissions = $permissions ? ( $permissions & 0777 ) : 0644;
	@chmod( $temp_path, $permissions );

	// rename() replaces the target atomically on the same filesystem on supported platforms.
	if ( ! @rename( $temp_path, $target_path ) ) {
		@unlink( $temp_path );
		return new WP_Error( 'qfr_rename_failed', 'The replacement could not be committed. The original file was not intentionally modified.' );
	}

	clearstatcache( true, $target_path );
	$new_size = @filesize( $target_path );
	if ( false !== $new_size ) {
		update_post_meta( $target['id'], 'filesize', (int) $new_size );
	}

	/**
	 * Fires after a PDF has been replaced successfully.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $backup_path   Private backup path.
	 * @param int    $new_size      New file size.
	 */
	do_action( 'qfr_pdf_replaced', (int) $target['id'], $backup_path, (int) $new_size );

	return true;
}

/**
 * Store a short audit record for successful or failed replacement attempts.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $result        Result label.
 * @param string $detail        Safe detail message.
 */
function qfr_log_operation( $attachment_id, $result, $detail ) {
	$log = get_option( 'qfr_audit_log', array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}

	array_unshift(
		$log,
		array(
			'time'          => current_time( 'mysql', true ),
			'user_id'       => get_current_user_id(),
			'attachment_id' => absint( $attachment_id ),
			'result'        => sanitize_key( $result ),
			'detail'        => sanitize_text_field( $detail ),
		)
	);

	// Keep the option bounded.
	$log = array_slice( $log, 0, 100 );
	update_option( 'qfr_audit_log', $log, false );
}

/**
 * Handle the authenticated save request.
 */
function qfr_save_file_data() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Forbidden.', 'quasar-file-replacer' ), '', array( 'response' => 403 ) );
	}

	if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
		wp_die( esc_html__( 'Invalid request method.', 'quasar-file-replacer' ), '', array( 'response' => 405 ) );
	}

	$nonce = isset( $_POST['qfr_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['qfr_nonce'] ) ) : '';
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'qfr_save_data' ) ) {
		wp_die( esc_html__( 'Invalid security token. Please reload the page and try again.', 'quasar-file-replacer' ), '', array( 'response' => 400 ) );
	}

	$updated_data = array();
	$notices      = array();
	$items        = isset( $_POST['efr'] ) && is_array( $_POST['efr'] ) ? wp_unslash( $_POST['efr'] ) : array();

	if ( count( $items ) > 50 ) {
		wp_die( esc_html__( 'Too many target rows were submitted.', 'quasar-file-replacer' ), '', array( 'response' => 400 ) );
	}

	foreach ( $items as $index => $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$row_index = absint( $index );
		$file_id   = isset( $item['file_id'] ) ? absint( $item['file_id'] ) : 0;
		$note      = isset( $item['note'] ) ? sanitize_text_field( $item['note'] ) : '';

		if ( ! $file_id ) {
			continue;
		}

		$target = qfr_get_valid_pdf_target( $file_id );
		if ( is_wp_error( $target ) ) {
			$message = sprintf( 'Attachment #%1$d was not saved: %2$s', $file_id, $target->get_error_message() );
			$notices[] = array( 'type' => 'warning', 'message' => $message );
			qfr_log_operation( $file_id, 'rejected', $target->get_error_message() );
			continue;
		}

		$field_key = 'qfr_upload_' . $row_index;
		if ( isset( $_FILES[ $field_key ] ) && isset( $_FILES[ $field_key ]['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES[ $field_key ]['error'] ) {
			$result = qfr_replace_pdf( $target, $_FILES[ $field_key ] );

			if ( is_wp_error( $result ) ) {
				$message = sprintf( 'PDF #%1$d was not replaced: %2$s', $file_id, $result->get_error_message() );
				$notices[] = array( 'type' => 'warning', 'message' => $message );
				qfr_log_operation( $file_id, 'failed', $result->get_error_message() );
			} else {
				$notices[] = array( 'type' => 'success', 'message' => sprintf( 'PDF #%d was replaced successfully. Its URL was preserved.', $file_id ) );
				qfr_log_operation( $file_id, 'replaced', 'PDF replaced successfully; original backed up.' );
			}
		}

		$updated_data[ $row_index ] = array(
			'file_id'  => $file_id,
			'file_url' => $target['url'],
			'note'     => $note,
		);
	}

	update_option( 'qfr_file_data', $updated_data, false );

	if ( empty( $notices ) ) {
		$notices[] = array( 'type' => 'success', 'message' => 'Target list saved. No replacement files were uploaded.' );
	}

	set_transient( 'qfr_notice_' . get_current_user_id(), $notices, MINUTE_IN_SECONDS );

	wp_safe_redirect( admin_url( 'admin.php?page=quasar-file-replacer' ) );
	exit;
}
