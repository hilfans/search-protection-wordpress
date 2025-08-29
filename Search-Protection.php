<?php
/**
 * Plugin Name: Search Protection
 * Plugin URI: https://github.com/hilfans/search-protection-wordpress
 * Description: Lindungi form pencarian dari spam dan karakter berbahaya dengan daftar hitam dan reCAPTCHA v3.
 * Version: 1.5.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: <a href="https://msp.web.id" target="_blank">Hilfan</a>
 * Author URI:  https://msp.web.id/
 * License: GPLv2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: search-protection
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Ebmsp_SProtect_Protection {
	private $option_name      = 'ebmsp_sprotect_settings';
	private $log_table;
	private $cron_hook_name   = 'ebmsp_sprotect_daily_log_cleanup';
	private $plugin_version   = '1.5.0';

	public function __construct() {
		global $wpdb;
		$this->log_table = $wpdb->prefix . 'ebmsp_sprotect_logs';

		add_action( 'init', [ $this, 'setup_plugin' ] );
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'pre_get_posts', [ $this, 'intercept_search_query' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_recaptcha_scripts' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_settings_link' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'admin_init', [ $this, 'process_settings_actions' ] );

		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
		add_action( $this->cron_hook_name, [ $this, 'do_daily_log_cleanup' ] );
	}

	public function setup_plugin() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data          = get_plugin_data( __FILE__ );
		$this->plugin_version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : $this->plugin_version;
	}

	public function activate() {
		$this->create_log_table();
		wp_clear_scheduled_hook( $this->cron_hook_name );
		if ( ! wp_next_scheduled( $this->cron_hook_name ) ) {
			wp_schedule_event( time(), 'daily', $this->cron_hook_name );
		}
	}

	public function deactivate() {
		wp_clear_scheduled_hook( $this->cron_hook_name );
	}

	public function plugin_settings_link( $links ) {
		$settings_url  = esc_url( admin_url( 'options-general.php?page=ebmsp-sprotect-settings' ) );
		$settings_link = '<a href="' . $settings_url . '">' . esc_html__( 'Settings', 'search-protection' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function add_settings_page() {
		add_options_page(
			' Search Protection Settings',
			esc_html__( 'Search Protection', 'search-protection' ),
			'manage_options',
			'ebmsp-sprotect-settings',
			[ $this, 'settings_page_html' ]
		);
	}

	public function register_settings() {
		register_setting(
			'ebmsp_sprotect_settings_group',
			$this->option_name,
			[ $this, 'sanitize_settings' ]
		);
	}

	public function sanitize_settings( $input ) {
		$new_input = [];
		$defaults  = $this->get_default_settings();

		$new_input['enable_recaptcha']        = isset( $input['enable_recaptcha'] ) ? '1' : '0';
		$new_input['site_key']                = isset( $input['site_key'] ) ? sanitize_text_field( $input['site_key'] ) : '';
		$new_input['secret_key']              = isset( $input['secret_key'] ) ? sanitize_text_field( $input['secret_key'] ) : '';
		$new_input['blacklist']               = isset( $input['blacklist'] ) ? wp_kses_post( trim( (string) $input['blacklist'] ) ) : '';
		$new_input['msg_recaptcha_fail']      = sanitize_text_field( $input['msg_recaptcha_fail'] ?? $defaults['msg_recaptcha_fail'] );
		$new_input['msg_badword']             = sanitize_text_field( $input['msg_badword'] ?? $defaults['msg_badword'] );
		$new_input['msg_regex']               = sanitize_text_field( $input['msg_regex'] ?? $defaults['msg_regex'] );
		$new_input['block_page_url']          = ! empty( $input['block_page_url'] ) ? esc_url_raw( $input['block_page_url'] ) : '';
		$new_input['delete_on_uninstall']     = isset( $input['delete_on_uninstall'] ) ? '1' : '0';
		$new_input['enable_auto_log_cleanup'] = isset( $input['enable_auto_log_cleanup'] ) ? '1' : '0';

		return $new_input;
	}

	private function get_default_settings() {
		return [
			'enable_recaptcha'        => '0',
			'site_key'                => '',
			'secret_key'              => '',
			'blacklist'               => '',
			'msg_recaptcha_fail'      => 'reCAPTCHA tidak terdeteksi. Coba ulangi pencarian Anda.',
			'msg_badword'             => 'Pencarian diblokir karena mengandung kata yang tidak diizinkan.',
			'msg_regex'               => 'Pencarian diblokir karena mengandung pola karakter yang tidak diizinkan.',
			'block_page_url'          => '',
			'delete_on_uninstall'     => '0',
			'enable_auto_log_cleanup' => '0',
		];
	}

	public function settings_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$defaults      = $this->get_default_settings();
		$saved_options = get_option( $this->option_name );
		$options       = wp_parse_args( $saved_options, $defaults );

		$cache_key       = 'ebmsp_sprotect_recent_keywords';
		$recent_keywords = wp_cache_get( $cache_key, 'search_protection' );

		if ( false === $recent_keywords ) {
			global $wpdb;
			$one_day_ago = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

			$table = esc_sql( $this->log_table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$recent_keywords = $wpdb->get_results( $wpdb->prepare( "SELECT search_term, COUNT(*) AS count FROM {$table} WHERE created_at >= %s GROUP BY search_term ORDER BY count DESC LIMIT 20", $one_day_ago ) );

			wp_cache_set( $cache_key, $recent_keywords, 'search_protection', 15 * MINUTE_IN_SECONDS );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Search Protection Settings', 'search-protection' ); ?></h1>
			<?php settings_errors( 'ebmsp_sprotect_notices' ); ?>
			<p><?php echo esc_html__( 'Plugin ini membantu melindungi form pencarian Anda dari kata-kata yang tidak diinginkan dan spam menggunakan reCAPTCHA v3.', 'search-protection' ); ?></p>

			<h2><?php echo esc_html__( 'Informasi Kata Kunci Terblokir (24 Jam Terakhir)', 'search-protection' ); ?></h2>
			<p><?php echo esc_html__( 'Berikut adalah kata kunci yang paling sering diblokir dalam 24 jam terakhir.', 'search-protection' ); ?></p>

			<?php if ( ! empty( $recent_keywords ) ) : ?>
				<div style="background:#fff;border:1px solid #ccd0d4;padding:15px;margin-bottom:20px;border-radius:4px;">
					<?php
					$keywords_to_copy = implode( ', ', array_map( static function( $item ) {
						return (string) $item->search_term;
					}, $recent_keywords ) );
					?>
					<label for="recent_keywords_textarea" style="font-weight:bold;display:block;margin-bottom:5px;"><?php echo esc_html__( 'Salin Kata Kunci:', 'search-protection' ); ?></label>
					<textarea id="recent_keywords_textarea" readonly rows="3" class="large-text" onclick="this.select();"><?php echo esc_textarea( $keywords_to_copy ); ?></textarea>
					<p class="description"><?php echo esc_html__( 'Klik di dalam area teks di atas, lalu salin (Ctrl+C atau Cmd+C) dan tempel ke daftar terlarang di bawah.', 'search-protection' ); ?></p>

					<h3 style="margin-top:20px;margin-bottom:10px;border-bottom:1px solid #eee;padding-bottom:5px;"><?php echo esc_html__( 'Rincian:', 'search-protection' ); ?></h3>
					<ul style="margin-left:20px;list-style-type:disc;">
						<?php foreach ( $recent_keywords as $keyword ) : ?>
							<li><strong><?php echo esc_html( (string) $keyword->search_term ); ?></strong> (<?php echo esc_html__( 'diblokir', 'search-protection' ); ?> <?php echo esc_html( (string) $keyword->count ); ?> <?php echo esc_html__( 'kali', 'search-protection' ); ?>)</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php else : ?>
				<div style="background:#fff;border:1px solid #ccd0d4;padding:15px;margin-bottom:20px;border-radius:4px;">
					<p><?php echo esc_html__( 'Tidak ada aktivitas pemblokiran kata kunci yang tercatat dalam 24 jam terakhir.', 'search-protection' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'ebmsp_sprotect_settings_group' ); ?>

				<h2><?php echo esc_html__( 'Pengaturan reCAPTCHA v3', 'search-protection' ); ?></h2>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"></th>
						<td>
							<label for="enable_recaptcha">
								<input type="checkbox" id="enable_recaptcha" name="<?php echo esc_attr( $this->option_name ); ?>[enable_recaptcha]" value="1" <?php checked( '1', $options['enable_recaptcha'] ); ?>>
								<?php echo esc_html__( 'Aktifkan verifikasi reCAPTCHA pada form pencarian.', 'search-protection' ); ?>
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="site_key"><?php echo esc_html__( 'Site Key', 'search-protection' ); ?></label></th>
						<td><input type="text" id="site_key" name="<?php echo esc_attr( $this->option_name ); ?>[site_key]" value="<?php echo esc_attr( $options['site_key'] ); ?>" class="regular-text"></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="secret_key"><?php echo esc_html__( 'Secret Key', 'search-protection' ); ?></label></th>
						<td><input type="text" id="secret_key" name="<?php echo esc_attr( $this->option_name ); ?>[secret_key]" value="<?php echo esc_attr( $options['secret_key'] ); ?>" class="regular-text"></td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Pengaturan Pemblokiran Kata', 'search-protection' ); ?></h2>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="blacklist"><?php echo esc_html__( 'Daftar Kata/Pola Terlarang', 'search-protection' ); ?></label></th>
						<td>
							<textarea id="blacklist" name="<?php echo esc_attr( $this->option_name ); ?>[blacklist]" rows="5" class="large-text"><?php echo esc_textarea( $options['blacklist'] ); ?></textarea>
							<p class="description">
								<?php echo wp_kses_post( __( 'Pisahkan setiap kata atau pola dengan koma.<br> - Untuk kata biasa: <code>spam, judi, test</code><br> - Untuk ekspresi reguler (regex), apit dengan garis miring: <code>/^[a-z]+$/</code>, <code>/[^\x20-\x7E]/</code>', 'search-protection' ) ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Pengaturan Pesan & Pengalihan', 'search-protection' ); ?></h2>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="msg_recaptcha_fail"><?php echo esc_html__( 'Pesan Gagal reCAPTCHA', 'search-protection' ); ?></label></th>
						<td><input type="text" id="msg_recaptcha_fail" name="<?php echo esc_attr( $this->option_name ); ?>[msg_recaptcha_fail]" value="<?php echo esc_attr( $options['msg_recaptcha_fail'] ); ?>" class="regular-text"></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="msg_badword"><?php echo esc_html__( 'Pesan Kata Terlarang', 'search-protection' ); ?></label></th>
						<td><input type="text" id="msg_badword" name="<?php echo esc_attr( $this->option_name ); ?>[msg_badword]" value="<?php echo esc_attr( $options['msg_badword'] ); ?>" class="regular-text"></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="msg_regex"><?php echo esc_html__( 'Pesan Pola Terlarang (Regex)', 'search-protection' ); ?></label></th>
						<td><input type="text" id="msg_regex" name="<?php echo esc_attr( $this->option_name ); ?>[msg_regex]" value="<?php echo esc_attr( $options['msg_regex'] ); ?>" class="regular-text"></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="block_page_url"><?php echo esc_html__( 'URL Halaman Blokir Kustom', 'search-protection' ); ?></label></th>
						<td>
							<input type="url" id="block_page_url" name="<?php echo esc_attr( $this->option_name ); ?>[block_page_url]" value="<?php echo esc_attr( $options['block_page_url'] ); ?>" class="regular-text" placeholder="https://domain.com/blocked">
							<p class="description"><?php echo esc_html__( 'Jika diisi, pengguna akan dialihkan ke URL ini saat pencarian diblokir. Jika kosong, pesan akan ditampilkan.', 'search-protection' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Manajemen Data', 'search-protection' ); ?></h2>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Hapus Log Otomatis</th>
						<td>
							<label for="enable_auto_log_cleanup">
								<input type="checkbox" id="enable_auto_log_cleanup" name="<?php echo esc_attr( $this->option_name ); ?>[enable_auto_log_cleanup]" value="1" <?php checked( '1', $options['enable_auto_log_cleanup'] ); ?>>
								<?php echo esc_html__( 'Centang untuk menghapus log pencarian yang diblokir secara otomatis setiap 24 jam.', 'search-protection' ); ?>
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Hapus Data Saat Uninstall</th>
						<td>
							<label for="delete_on_uninstall">
								<input type="checkbox" id="delete_on_uninstall" name="<?php echo esc_attr( $this->option_name ); ?>[delete_on_uninstall]" value="1" <?php checked( '1', $options['delete_on_uninstall'] ); ?>>
								<?php echo esc_html__( "Centang untuk menghapus semua pengaturan dan log 'Search Protection' saat plugin dihapus.", 'search-protection' ); ?>
							</label>
							<p class="description"><strong><?php echo esc_html__( 'Peringatan:', 'search-protection' ); ?></strong> <?php echo esc_html__( 'Tindakan ini tidak dapat diurungkan.', 'search-protection' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( esc_html__( 'Simpan Semua Perubahan', 'search-protection' ) ); ?>
			</form>

			<hr>

			<h2><?php echo esc_html__( 'Cadangkan & Pulihkan Pengaturan', 'search-protection' ); ?></h2>
			<div style="display:flex;gap:20px;flex-wrap:wrap;">
				<div style="flex:1;min-width:300px;">
					<p><?php echo esc_html__( 'Simpan semua konfigurasi plugin di atas ke dalam sebuah file .json.', 'search-protection' ); ?></p>
					<form method="post">
						<input type="hidden" name="ebmsp_sprotect_action" value="export_settings" />
						<?php wp_nonce_field( 'ebmsp_sprotect_export', 'ebmsp_sprotect_export_nonce' ); ?>
						<?php submit_button( esc_html__( 'Cadangkan Pengaturan', 'search-protection' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
				<div style="flex:1;min-width:300px;border-left:1px solid #ddd;padding-left:20px;">
					<p><?php echo esc_html__( 'Pulihkan konfigurasi dari file cadangan. Pengaturan saat ini akan ditimpa.', 'search-protection' ); ?></p>
					<form method="post" enctype="multipart/form-data">
						<p>
							<label for="import_file"><?php echo esc_html__( 'Pilih File Cadangan (.json):', 'search-protection' ); ?></label><br>
							<input type="file" id="import_file" name="import_file" accept=".json,application/json,text/plain" required>
						</p>
						<input type="hidden" name="ebmsp_sprotect_action" value="import_settings" />
						<?php wp_nonce_field( 'ebmsp_sprotect_import', 'ebmsp_sprotect_import_nonce' ); ?>
						<?php submit_button( esc_html__( 'Impor & Pulihkan Pengaturan', 'search-protection' ), 'primary', 'submit', false ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public function process_settings_actions() {
		$request_method = (string) ( filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		if ( 'POST' !== $request_method ) {
			return;
		}

		// Stop early unless action exists, nonce verified, and user capable.
		$action = (string) ( filter_input( INPUT_POST, 'ebmsp_sprotect_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		if ( '' === $action ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( 'export_settings' === $action ) {
			check_admin_referer( 'ebmsp_sprotect_export', 'ebmsp_sprotect_export_nonce' );
			$this->export_settings();
			return;
		}

		if ( 'import_settings' === $action ) {
			check_admin_referer( 'ebmsp_sprotect_import', 'ebmsp_sprotect_import_nonce' );
			$this->import_settings();
			return;
		}
	}

	private function export_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = get_option( $this->option_name );
		if ( empty( $settings ) ) {
			$settings = $this->get_default_settings();
		}

		$filename = 'search-protection-settings-backup-' . gmdate( 'Y-m-d' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $filename ) );

		wp_send_json( $settings, 200 );
	}

	private function import_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk melakukan tindakan ini.', 'search-protection' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( empty( $_FILES['import_file'] ) || empty( $_FILES['import_file']['tmp_name'] ) ) {
			add_settings_error( 'ebmsp_sprotect_notices', 'import_error', esc_html__( 'Tidak ada file yang dipilih atau file tidak valid.', 'search-protection' ), 'error' );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file      = $_FILES['import_file'];
		$tmp_name  = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		$error     = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_OK;
		$size      = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$orig_name = isset( $file['name'] ) ? (string) $file['name'] : '';

		if ( UPLOAD_ERR_OK !== $error ) {
			add_settings_error( 'ebmsp_sprotect_notices', 'import_error', esc_html__( 'Terjadi kesalahan saat mengunggah file.', 'search-protection' ), 'error' );
			return;
		}

		if ( ! is_uploaded_file( $tmp_name ) ) {
			add_settings_error( 'ebmsp_sprotect_notices', 'import_error', esc_html__( 'File upload tidak valid.', 'search-protection' ), 'error' );
			return;
		}

		if ( $size > 1024 * 1024 ) { // 1MB
			add_settings_error( 'ebmsp_sprotect_notices', 'import_error', esc_html__( 'File terlalu besar. Maksimum 1MB.', 'search-protection' ), 'error' );
			return;
		}

		$sanitized_name = sanitize_file_name( $orig_name );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$ft = wp_check_filetype_and_ext( $tmp_name, $sanitized_name, [ 'json' => 'application/json' ] );

		$allowed_mimes = [ 'application/json', 'text/plain', 'application/octet-stream' ];
		$ext_ok        = ( isset( $ft['ext'] ) && 'json' === $ft['ext'] );
		$mime_ok       = ( isset( $ft['type'] ) && in_array( $ft['type'], $allowed_mimes, true ) );

		if ( ! $ext_ok || ! $mime_ok ) {
			add_settings_error( 'ebmsp_sprotect_notices', 'import_error', esc_html__( 'File tidak valid. Unggah file cadangan .json yang benar.', 'search-protection' ), 'error' );
			return;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}

		$content = '';
		if ( $wp_filesystem && is_object( $wp_filesystem ) ) {
			$content = $wp_filesystem->get_contents( $tmp_name );
		}
		if ( '' === $content || false === $content || null === $content ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$content = file_get_contents( $tmp_name );
		}
		if ( '' === $content || false === $content || null === $content ) {
			add_settings_error( 'ebmsp_sprotect_notices', 'import_error', esc_html__( 'Gagal membaca file cadangan.', 'search-protection' ), 'error' );
			return;
		}

		$imported_settings = json_decode( $content, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $imported_settings ) ) {
			add_settings_error( 'ebmsp_sprotect_notices', 'import_error', esc_html__( 'File JSON tidak valid.', 'search-protection' ), 'error' );
			return;
		}

		$sanitized_settings = $this->sanitize_settings( $imported_settings );
		update_option( $this->option_name, $sanitized_settings );

		add_settings_error( 'ebmsp_sprotect_notices', 'import_success', esc_html__( 'Pengaturan berhasil diimpor dan disimpan.', 'search-protection' ), 'success' );
	}

	public function create_log_table() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table           = esc_sql( $this->log_table );
		$sql             = "CREATE TABLE IF NOT EXISTS {$table} (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			search_term TEXT NOT NULL,
			blocked_reason VARCHAR(255) NOT NULL,
			user_ip VARCHAR(100) NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public function do_daily_log_cleanup() {
		$options = get_option( $this->option_name );

		if ( ! empty( $options['enable_auto_log_cleanup'] ) && '1' === $options['enable_auto_log_cleanup'] ) {
			global $wpdb;
			$table = esc_sql( $this->log_table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < (NOW() - INTERVAL %d DAY)", 1 ) );
			wp_cache_delete( 'ebmsp_sprotect_recent_keywords', 'search_protection' );
		}
	}

	public function intercept_search_query( $wp_query ) {
		if ( is_admin() || ! $wp_query->is_main_query() || ! $wp_query->is_search() ) {
			return;
		}

		$search_query = trim( (string) $wp_query->get( 's' ) );
		if ( '' === $search_query ) {
			return;
		}

		$options       = wp_parse_args( get_option( $this->option_name ), $this->get_default_settings() );
		$blacklist_raw = isset( $options['blacklist'] ) ? (string) $options['blacklist'] : '';

		if ( '' !== $blacklist_raw ) {
			$blacklist = array_map( 'trim', explode( ',', $blacklist_raw ) );
			foreach ( $blacklist as $term ) {
				if ( '' === $term ) {
					continue;
				}
				if ( '/' === substr( $term, 0, 1 ) && '/' === substr( $term, -1 ) ) {
					$is_valid = @preg_match( $term, '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					if ( false === $is_valid ) {
						continue;
					}
					$match = @preg_match( $term, $search_query ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					if ( 1 === (int) $match ) {
						$this->block_request( $options['msg_regex'], 'Regex Block' );
					}
				} else {
					if ( false !== stripos( $search_query, $term ) ) {
						$this->block_request( $options['msg_badword'], 'Badword Block' );
					}
				}
			}
		}

		if ( ! empty( $options['enable_recaptcha'] ) && '1' === $options['enable_recaptcha'] ) {
			$secret_key = isset( $options['secret_key'] ) ? (string) $options['secret_key'] : '';

			$nonce = (string) ( filter_input( INPUT_POST, 'ebmsp_sprotect_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'ebmsp_sprotect_search' ) ) {
				return; // nonce invalid -> jangan proses token
			}

			$token = (string) ( filter_input( INPUT_POST, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );

			if ( '' === $secret_key ) {
				return;
			}
			if ( '' === $token ) {
				$this->block_request( $options['msg_recaptcha_fail'], 'No reCAPTCHA Token' );
			}

			$remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			$response  = wp_remote_post(
				'https://www.google.com/recaptcha/api/siteverify',
				[
					'body' => [
						'secret'   => $secret_key,
						'response' => $token,
						'remoteip' => $remote_ip,
					],
				]
			);

			if ( is_wp_error( $response ) ) {
				$this->block_request( esc_html__( 'Gagal menghubungi server reCAPTCHA.', 'search-protection' ), 'reCAPTCHA API Error' );
			}

			$result = json_decode( wp_remote_retrieve_body( $response ), true );
			$score  = isset( $result['score'] ) ? (float) $result['score'] : 0.0;
			if ( empty( $result['success'] ) || $score < 0.5 ) {
				$this->block_request( $options['msg_recaptcha_fail'], 'reCAPTCHA Failed' );
			}
		}
	}

	public function enqueue_recaptcha_scripts() {
		$options = wp_parse_args( get_option( $this->option_name ), $this->get_default_settings() );

		if ( empty( $options['enable_recaptcha'] ) || '1' !== $options['enable_recaptcha'] || empty( $options['site_key'] ) ) {
			return;
		}
		$site_key = $options['site_key'];

		wp_register_script(
			'google-recaptcha',
			"https://www.google.com/recaptcha/api.js?render=" . rawurlencode( $site_key ),
			[],
			$this->plugin_version,
			true
		);

		$nonce = wp_create_nonce( 'ebmsp_sprotect_search' );

		$inline_script = "
			document.addEventListener('submit', function(e) {
				const form = e.target && e.target.closest('form[role=\"search\"], form.search-form, form[action*=\"/?s=\"]');
				if (!form) { return; }
				if (form.dataset.recaptchaAttempted) { return; }
				e.preventDefault();
				form.dataset.recaptchaAttempted = 'true';
				if (typeof grecaptcha === 'undefined' || typeof grecaptcha.execute === 'undefined') {
					console.error('Search Protection: reCAPTCHA script not loaded correctly.');
					form.submit();
					return;
				}
				var nonceInput = form.querySelector('input[name=\"ebmsp_sprotect_nonce\"]');
				if (!nonceInput) {
					nonceInput = document.createElement('input');
					nonceInput.type = 'hidden';
					nonceInput.name = 'ebmsp_sprotect_nonce';
					nonceInput.value = '" . esc_js( $nonce ) . "';
					form.appendChild(nonceInput);
				}
				grecaptcha.ready(function() {
					grecaptcha.execute('" . esc_js( $site_key ) . "', { action: 'search' }).then(function(token) {
						var existingToken = form.querySelector('input[name=\"token\"]');
						if (existingToken) { existingToken.remove(); }
						var tokenInput = document.createElement('input');
						tokenInput.type = 'hidden';
						tokenInput.name = 'token';
						tokenInput.value = token;
						form.appendChild(tokenInput);
						form.submit();
					}).catch(function(error){
						console.error('Search Protection: reCAPTCHA execution error.', error);
						form.submit();
					});
				});
			});
		";

		wp_enqueue_script( 'google-recaptcha' );
		wp_add_inline_script( 'google-recaptcha', $inline_script );
	}

	private function block_request( $message, $reason ) {
		global $wpdb;
		$search_query = get_search_query();
		$user_ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'UNKNOWN';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$this->log_table,
			[
				'search_term'    => $search_query,
				'blocked_reason' => sanitize_text_field( $reason ),
				'user_ip'        => $user_ip,
			]
		);

		wp_cache_delete( 'ebmsp_sprotect_recent_keywords', 'search_protection' );

		$options        = wp_parse_args( get_option( $this->option_name ), $this->get_default_settings() );
		$block_page_url = isset( $options['block_page_url'] ) ? (string) $options['block_page_url'] : '';

		if ( '' !== $block_page_url && filter_var( $block_page_url, FILTER_VALIDATE_URL ) ) {
			wp_redirect( esc_url_raw( $block_page_url ) );
			exit;
		}

		$homepage_url = home_url( '/' );
		$full_message = sprintf(
			'%s<p><a href="%s">&laquo; %s</a></p>',
			esc_html( $message ),
			esc_url( $homepage_url ),
			esc_html__( 'Kembali ke Beranda', 'search-protection' )
		);

		wp_die( wp_kses_post( $full_message ), esc_html__( 'Pencarian Diblokir', 'search-protection' ), [ 'response' => 403 ] );
	}

	public function admin_notices() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! isset( $screen->id ) || 'settings_page_ebmsp-sprotect-settings' !== $screen->id ) {
			return; // hanya tampil di halaman Settings plugin kita
		}

		$recaptcha_url = 'https://www.google.com/recaptcha/admin';
		echo '<div class="notice notice-info is-dismissible"><p>'
			. esc_html__( 'Pastikan Anda telah mendaftarkan domain Anda di', 'search-protection' )
			. ' <a href="' . esc_url( $recaptcha_url ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Google reCAPTCHA (v3)', 'search-protection' )
			. '</a> '
			. esc_html__( 'untuk mendapatkan Site Key dan Secret Key.', 'search-protection' )
			. '</p></div>';

		$options = wp_parse_args( get_option( $this->option_name ), $this->get_default_settings() );
		if ( ! empty( $options['enable_recaptcha'] ) && ( empty( $options['site_key'] ) || empty( $options['secret_key'] ) ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p><strong>'
				. esc_html__( 'Peringatan:', 'search-protection' )
				. '</strong> '
				. esc_html__( 'reCAPTCHA diaktifkan, tetapi Site Key atau Secret Key belum diisi. Fitur reCAPTCHA tidak akan berfungsi.', 'search-protection' )
				. '</p></div>';
		}
	}
}

new Ebmsp_SProtect_Protection();
