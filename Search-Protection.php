<?php
/**
 * Plugin Name: Search Protection
 * Plugin URI: https://github.com/hilfans/search-protection-wordpress
 * Description: Lindungi form pencarian dari spam dan karakter berbahaya dengan daftar hitam dan reCAPTCHA v3.
 * Version: 1.4.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: <a href="https://msp.web.id" target="_blank">Hilfan</a>
 * Author URI:  https://msp.web.id/
 * License: GPLv2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: search-protection
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class SPH_Search_Protection {
    private $option_name = 'sph_settings';
    private $log_table;
    private $cron_hook_name = 'sph_daily_log_cleanup';
    private $plugin_version;

    public function __construct() {
        global $wpdb;
        $this->log_table = $wpdb->prefix . 'sph_logs';
        
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        $plugin_data = get_plugin_data( __FILE__ );
        $this->plugin_version = $plugin_data['Version'];

        // Main Hooks
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('pre_get_posts', [$this, 'intercept_search_query']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_recaptcha_scripts']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_settings_link']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('admin_init', [$this, 'process_settings_actions']);

        // Activation, Deactivation, and Cron Hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        add_action($this->cron_hook_name, [$this, 'do_daily_log_cleanup']);
    }

    public function activate() {
        $this->create_log_table();
        wp_clear_scheduled_hook($this->cron_hook_name);
        if (!wp_next_scheduled($this->cron_hook_name)) {
            wp_schedule_event(time(), 'daily', $this->cron_hook_name);
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook($this->cron_hook_name);
    }

    public function plugin_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=search-protection-settings">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function add_settings_page() {
        add_options_page(
            'Search Protection Settings',
            'Search Protection',
            'manage_options',
            'search-protection-settings',
            [$this, 'settings_page_html']
        );
    }

    public function register_settings() {
        register_setting(
            'sph_settings_group',
            $this->option_name,
            [$this, 'sanitize_settings']
        );
    }
    
    public function sanitize_settings($input) {
        $new_input = [];
        $defaults = $this->get_default_settings();
    
        $new_input['enable_recaptcha'] = isset($input['enable_recaptcha']) ? '1' : '0';
        $new_input['site_key'] = sanitize_text_field($input['site_key'] ?? '');
        $new_input['secret_key'] = sanitize_text_field($input['secret_key'] ?? '');
        $new_input['blacklist'] = isset($input['blacklist']) ? wp_kses_post(trim($input['blacklist'])) : '';
        $new_input['msg_recaptcha_fail'] = sanitize_text_field($input['msg_recaptcha_fail'] ?? $defaults['msg_recaptcha_fail']);
        $new_input['msg_badword'] = sanitize_text_field($input['msg_badword'] ?? $defaults['msg_badword']);
        $new_input['msg_regex'] = sanitize_text_field($input['msg_regex'] ?? $defaults['msg_regex']);
        $new_input['block_page_url'] = isset($input['block_page_url']) ? esc_url_raw($input['block_page_url']) : '';
        $new_input['delete_on_uninstall'] = isset($input['delete_on_uninstall']) ? '1' : '0';
        $new_input['enable_auto_log_cleanup'] = isset($input['enable_auto_log_cleanup']) ? '1' : '0';
    
        return $new_input;
    }

    private function get_default_settings() {
        return [
            'enable_recaptcha' => '0',
            'site_key' => '',
            'secret_key' => '',
            'blacklist' => '',
            'msg_recaptcha_fail' => 'reCAPTCHA tidak terdeteksi. Coba ulangi pencarian Anda.',
            'msg_badword' => 'Pencarian diblokir karena mengandung kata yang tidak diizinkan.',
            'msg_regex' => 'Pencarian diblokir karena mengandung pola karakter yang tidak diizinkan.',
            'block_page_url' => '',
            'delete_on_uninstall' => '0',
            'enable_auto_log_cleanup' => '1', // Default to ON to maintain behavior for existing users
        ];
    }

    public function settings_page_html() {
        if (!current_user_can('manage_options')) return;

        $defaults = $this->get_default_settings();
        $saved_options = get_option($this->option_name);
        $options = wp_parse_args($saved_options, $defaults);

        $cache_key = 'sph_recent_keywords';
        $recent_keywords = wp_cache_get($cache_key, 'search_protection');

        if (false === $recent_keywords) {
            global $wpdb;
            $one_day_ago = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
            
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
            $recent_keywords = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT search_term, COUNT(*) as count FROM {$this->log_table} WHERE created_at >= %s GROUP BY search_term ORDER BY count DESC LIMIT 20",
                    $one_day_ago
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            wp_cache_set($cache_key, $recent_keywords, 'search_protection', 15 * MINUTE_IN_SECONDS);
        }

        ?>
        <div class="wrap">
            <h1>Search Protection Settings</h1>
            <?php settings_errors('sph_notices'); ?>
            <p>Plugin ini membantu melindungi form pencarian Anda dari kata-kata yang tidak diinginkan dan spam menggunakan reCAPTCHA v3.</p>

            <h2>Informasi Kata Kunci Terblokir (24 Jam Terakhir)</h2>
            <p>Berikut adalah kata kunci yang paling sering diblokir dalam 24 jam terakhir.</p>
            
            <?php if (!empty($recent_keywords)): ?>
                <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                    <?php
                    $keywords_to_copy = implode(', ', array_map(function($item) {
                        return $item->search_term;
                    }, $recent_keywords));
                    ?>
                    <label for="recent_keywords_textarea" style="font-weight: bold; display: block; margin-bottom: 5px;">Salin Kata Kunci:</label>
                    <textarea id="recent_keywords_textarea" readonly rows="3" class="large-text" onclick="this.select();"><?php echo esc_textarea($keywords_to_copy); ?></textarea>
                    <p class="description">Klik di dalam area teks di atas, lalu salin (Ctrl+C atau Cmd+C) dan tempel ke daftar terlarang di bawah.</p>
                    
                    <h3 style="margin-top: 20px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Rincian:</h3>
                    <ul style="margin-left: 20px; list-style-type: disc;">
                        <?php foreach ($recent_keywords as $keyword): ?>
                            <li><strong><?php echo esc_html($keyword->search_term); ?></strong> (diblokir <?php echo esc_html($keyword->count); ?> kali)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                    <p>Tidak ada aktivitas pemblokiran kata kunci yang tercatat dalam 24 jam terakhir.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('sph_settings_group'); ?>

                <h2>Pengaturan reCAPTCHA v3</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Aktifkan reCAPTCHA</th>
                        <td>
                            <input type="checkbox" id="enable_recaptcha" name="<?php echo esc_attr($this->option_name); ?>[enable_recaptcha]" value="1" <?php checked('1', $options['enable_recaptcha']); ?>>
                            <label for="enable_recaptcha">Aktifkan verifikasi reCAPTCHA pada form pencarian.</label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="site_key">Site Key</label></th>
                        <td><input type="text" id="site_key" name="<?php echo esc_attr($this->option_name); ?>[site_key]" value="<?php echo esc_attr($options['site_key']); ?>" class="regular-text"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="secret_key">Secret Key</label></th>
                        <td><input type="text" id="secret_key" name="<?php echo esc_attr($this->option_name); ?>[secret_key]" value="<?php echo esc_attr($options['secret_key']); ?>" class="regular-text"></td>
                    </tr>
                </table>

                <h2>Pengaturan Pemblokiran Kata</h2>
                <table class="form-table">
                     <tr valign="top">
                        <th scope="row"><label for="blacklist">Daftar Kata/Pola Terlarang</label></th>
                        <td>
                            <textarea id="blacklist" name="<?php echo esc_attr($this->option_name); ?>[blacklist]" rows="5" class="large-text"><?php echo esc_textarea($options['blacklist']); ?></textarea>
                            <p class="description">
                                Pisahkan setiap kata atau pola dengan koma. <br>
                                - Untuk kata biasa: <code>spam, judi, test</code><br>
                                - Untuk ekspresi reguler (regex), apit dengan garis miring: <code>/^[a-z]+$/</code>, <code>/[^\x20-\x7E]/</code>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2>Pengaturan Pesan & Pengalihan</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="msg_recaptcha_fail">Pesan Gagal reCAPTCHA</label></th>
                        <td><input type="text" id="msg_recaptcha_fail" name="<?php echo esc_attr($this->option_name); ?>[msg_recaptcha_fail]" value="<?php echo esc_attr($options['msg_recaptcha_fail']); ?>" class="regular-text"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="msg_badword">Pesan Kata Terlarang</label></th>
                        <td><input type="text" id="msg_badword" name="<?php echo esc_attr($this->option_name); ?>[msg_badword]" value="<?php echo esc_attr($options['msg_badword']); ?>" class="regular-text"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="msg_regex">Pesan Pola Terlarang (Regex)</label></th>
                        <td><input type="text" id="msg_regex" name="<?php echo esc_attr($this->option_name); ?>[msg_regex]" value="<?php echo esc_attr($options['msg_regex']); ?>" class="regular-text"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="block_page_url">URL Halaman Blokir Kustom</label></th>
                        <td>
                            <input type="url" id="block_page_url" name="<?php echo esc_attr($this->option_name); ?>[block_page_url]" value="<?php echo esc_attr($options['block_page_url']); ?>" class="regular-text" placeholder="https://domain.com/blocked">
                            <p class="description">Jika diisi, pengguna akan dialihkan ke URL ini saat pencarian diblokir. Jika kosong, pesan akan ditampilkan.</p>
                        </td>
                    </tr>
                </table>

                <h2>Manajemen Data</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Hapus Log Otomatis</th>
                        <td>
                            <input type="checkbox" id="enable_auto_log_cleanup" name="<?php echo esc_attr($this->option_name); ?>[enable_auto_log_cleanup]" value="1" <?php checked('1', $options['enable_auto_log_cleanup']); ?>>
                            <label for="enable_auto_log_cleanup">Centang untuk menghapus log pencarian yang diblokir secara otomatis setiap 24 jam.</label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Hapus Data Saat Uninstall</th>
                        <td>
                            <input type="checkbox" id="delete_on_uninstall" name="<?php echo esc_attr($this->option_name); ?>[delete_on_uninstall]" value="1" <?php checked('1', $options['delete_on_uninstall']); ?>>
                            <label for="delete_on_uninstall">Centang untuk menghapus semua pengaturan dan log 'Search Protection' saat plugin dihapus.</label>
                            <p class="description"><strong>Peringatan:</strong> Tindakan ini tidak dapat diurungkan.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Simpan Semua Perubahan'); ?>
            </form>

            <hr>

            <h2>Cadangkan & Pulihkan Pengaturan</h2>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <p>Simpan semua konfigurasi plugin di atas ke dalam sebuah file .json.</p>
                    <form method="post">
                        <input type="hidden" name="sph_action" value="export_settings" />
                        <?php wp_nonce_field('sph_export_nonce', 'sph_export_nonce_field'); ?>
                        <?php submit_button('Cadangkan Pengaturan', 'secondary', 'submit', false); ?>
                    </form>
                </div>
                <div style="flex: 1; min-width: 300px; border-left: 1px solid #ddd; padding-left: 20px;">
                    <p>Pulihkan konfigurasi dari file cadangan. Pengaturan saat ini akan ditimpa.</p>
                    <form method="post" enctype="multipart/form-data">
                        <p>
                            <label for="import_file">Pilih File Cadangan (.json):</label><br>
                            <input type="file" id="import_file" name="import_file" accept=".json" required>
                        </p>
                        <input type="hidden" name="sph_action" value="import_settings" />
                        <?php wp_nonce_field('sph_import_nonce', 'sph_import_nonce_field'); ?>
                        <?php submit_button('Impor & Pulihkan Pengaturan', 'primary', 'submit', false); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function process_settings_actions() {
        if (empty($_POST['sph_action'])) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['sph_action']));
        
        if ($action === 'export_settings') {
            $nonce = isset($_POST['sph_export_nonce_field']) ? sanitize_text_field(wp_unslash($_POST['sph_export_nonce_field'])) : '';
            if (!wp_verify_nonce($nonce, 'sph_export_nonce')) {
                wp_die('Pemeriksaan keamanan gagal!');
            }
            $this->export_settings();
        }

        if ($action === 'import_settings') {
            $nonce = isset($_POST['sph_import_nonce_field']) ? sanitize_text_field(wp_unslash($_POST['sph_import_nonce_field'])) : '';
            if (!wp_verify_nonce($nonce, 'sph_import_nonce')) {
                wp_die('Pemeriksaan keamanan gagal!');
            }
            $this->import_settings();
        }
    }

    private function export_settings() {
        if (!current_user_can('manage_options')) return;

        $settings = get_option($this->option_name);
        if (empty($settings)) $settings = $this->get_default_settings();

        $filename = 'search-protection-settings-backup-' . gmdate('Y-m-d') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        ob_clean();
        flush();
        
        echo json_encode($settings, JSON_PRETTY_PRINT);
        exit;
    }

    private function import_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Anda tidak memiliki izin untuk melakukan tindakan ini.');
        }

        $nonce = isset($_POST['sph_import_nonce_field']) ? sanitize_text_field(wp_unslash($_POST['sph_import_nonce_field'])) : '';
        if (!wp_verify_nonce($nonce, 'sph_import_nonce')) {
            add_settings_error('sph_notices', 'import_error', 'Pemeriksaan keamanan gagal.', 'error');
            return;
        }
        
        if (empty($_FILES['import_file']) || empty($_FILES['import_file']['tmp_name'])) {
            add_settings_error('sph_notices', 'import_error', 'Tidak ada file yang dipilih untuk diimpor.', 'error');
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $file = $_FILES['import_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            add_settings_error('sph_notices', 'import_error', 'Terjadi kesalahan saat mengunggah file.', 'error');
            return;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if ($extension !== 'json') {
            add_settings_error('sph_notices', 'import_error', 'File tidak valid. Harap unggah file cadangan .json yang benar.', 'error');
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $content = file_get_contents($file['tmp_name']);
        $imported_settings = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error('sph_notices', 'import_error', 'Gagal membaca file cadangan. File JSON tidak valid.', 'error');
            return;
        }
        
        $sanitized_settings = $this->sanitize_settings($imported_settings);
        update_option($this->option_name, $sanitized_settings);

        add_settings_error('sph_notices', 'import_success', 'Pengaturan berhasil diimpor dan disimpan.', 'success');
    }

    public function create_log_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
        $sql = "CREATE TABLE IF NOT EXISTS {$this->log_table} (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            search_term TEXT NOT NULL,
            blocked_reason VARCHAR(255) NOT NULL,
            user_ip VARCHAR(100) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function do_daily_log_cleanup() {
        $options = get_option($this->option_name);

        if ( ! empty( $options['enable_auto_log_cleanup'] ) && '1' === $options['enable_auto_log_cleanup'] ) {
            global $wpdb;

            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$this->log_table} WHERE created_at < (NOW() - INTERVAL %d DAY)",
                    1
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            wp_cache_delete('sph_recent_keywords', 'search_protection');
        }
    }

    public function intercept_search_query($query) {
        if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search_query = trim($query->get('s'));
        if (empty($search_query)) {
            return;
        }

        $options = wp_parse_args(get_option($this->option_name), $this->get_default_settings());
        
        $blacklist_raw = $options['blacklist'] ?? '';
        if (!empty($blacklist_raw)) {
            $blacklist = array_map('trim', explode(',', $blacklist_raw));
            foreach ($blacklist as $term) {
                if (empty($term)) continue;
                if (substr($term, 0, 1) == '/' && substr($term, -1) == '/') {
                    if (@preg_match($term, $search_query)) {
                        $this->block_request($options['msg_regex'], 'Regex Block');
                    }
                } else {
                    if (stripos($search_query, $term) !== false) {
                        $this->block_request($options['msg_badword'], 'Badword Block');
                    }
                }
            }
        }

        if (!empty($options['enable_recaptcha']) && $options['enable_recaptcha'] === '1') {
            $secret_key = $options['secret_key'] ?? '';
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
            
            if (empty($secret_key)) return;
            if (empty($token)) {
                $this->block_request($options['msg_recaptcha_fail'], 'No reCAPTCHA Token');
            }
            
            $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
            $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
                'body' => ['secret' => $secret_key, 'response' => $token, 'remoteip' => $remote_ip]
            ]);

            if (is_wp_error($response)) {
                 $this->block_request('Gagal menghubungi server reCAPTCHA.', 'reCAPTCHA API Error');
            }

            $result = json_decode(wp_remote_retrieve_body($response), true);
            if (empty($result['success']) || $result['score'] < 0.5) {
                $this->block_request($options['msg_recaptcha_fail'], 'reCAPTCHA Failed');
            }
        }
    }
    
    public function enqueue_recaptcha_scripts() {
        $options = wp_parse_args(get_option($this->option_name), $this->get_default_settings());

        if (empty($options['enable_recaptcha']) || $options['enable_recaptcha'] !== '1' || empty($options['site_key'])) {
            return;
        }
        $site_key = $options['site_key'];

        wp_register_script(
            'google-recaptcha',
            "https://www.google.com/recaptcha/api.js?render={$site_key}",
            [],
            $this->plugin_version,
            true
        );

        $inline_script = "
            document.addEventListener('submit', function(e) {
                const form = e.target.closest('form[role=\"search\"], form.search-form, form[action*=\"/?s=\"]');
                if (!form) {
                    return;
                }
                if (form.dataset.recaptchaAttempted) {
                    return;
                }
                e.preventDefault();
                form.dataset.recaptchaAttempted = 'true';
                if (typeof grecaptcha === 'undefined' || typeof grecaptcha.execute === 'undefined') {
                    console.error('Search Protection: reCAPTCHA script not loaded correctly.');
                    form.submit();
                    return;
                }
                grecaptcha.ready(() => {
                    grecaptcha.execute('" . esc_js($site_key) . "', { action: 'search' }).then(token => {
                        const existingToken = form.querySelector('input[name=\"token\"]');
                        if (existingToken) {
                            existingToken.remove();
                        }
                        const tokenInput = document.createElement('input');
                        tokenInput.type = 'hidden';
                        tokenInput.name = 'token';
                        tokenInput.value = token;
                        form.appendChild(tokenInput);
                        form.submit();
                    }).catch(error => {
                        console.error('Search Protection: reCAPTCHA execution error.', error);
                        form.submit();
                    });
                });
            });
        ";

        wp_enqueue_script('google-recaptcha');
        wp_add_inline_script('google-recaptcha', $inline_script);
    }

    private function block_request($message, $reason) {
        global $wpdb;
        $search_query = get_search_query();
        $user_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'UNKNOWN';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert($this->log_table, [
            'search_term'    => $search_query,
            'blocked_reason' => sanitize_text_field($reason),
            'user_ip'        => $user_ip
        ]);

        wp_cache_delete('sph_recent_keywords', 'search_protection');

        $options = wp_parse_args(get_option($this->option_name), $this->get_default_settings());
        $block_page_url = $options['block_page_url'] ?? '';
        
        if (!empty($block_page_url) && filter_var($block_page_url, FILTER_VALIDATE_URL)) {
            wp_redirect(esc_url_raw($block_page_url));
            exit;
        }
        
        $homepage_url = home_url('/');
        $full_message = sprintf(
            '%s<p><a href="%s">&laquo; Kembali ke Beranda</a></p>',
            esc_html($message),
            esc_url($homepage_url)
        );

        wp_die(wp_kses_post($full_message), 'Pencarian Diblokir', ['response' => 403]);
    }

    public function admin_notices() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'settings_page_search-protection-settings') {
            echo '<div class="notice notice-info is-dismissible"><p>Pastikan Anda telah mendaftarkan domain Anda di <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA (v3)</a> untuk mendapatkan Site Key dan Secret Key.</p></div>';
            
            $options = wp_parse_args(get_option($this->option_name), $this->get_default_settings());
            if (!empty($options['enable_recaptcha']) && (empty($options['site_key']) || empty($options['secret_key']))) {
                 echo '<div class="notice notice-warning is-dismissible"><p><strong>Peringatan:</strong> reCAPTCHA diaktifkan, tetapi Site Key atau Secret Key belum diisi. Fitur reCAPTCHA tidak akan berfungsi.</p></div>';
            }
        }
    }
}

new SPH_Search_Protection();
