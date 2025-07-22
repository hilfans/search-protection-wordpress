<?php
/**
 * Plugin Name: Search Protection
 * Plugin URI: https://github.com/hilfans/search-protection-wordpress
 * Description: Lindungi form pencarian dari spam dan karakter berbahaya dengan daftar hitam dan reCAPTCHA v3.
 * Version: 1.3.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: <a href="https://msp.web.id" target="_blank">Hilfan</a>, <a href="https://telkomuniversity.ac.id" target="_blank">Telkom University</a>
 * Author URI:  https://msp.web.id/
 * License: GPLv2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: search-protection
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class TelU_Search_Protection_Full {
    private $option_name = 'telu_search_protection_settings';
    private $log_table;

    public function __construct() {
        global $wpdb;
        $this->log_table = $wpdb->prefix . 'telu_search_protection_logs';

        // Main Hooks
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('pre_get_posts', [$this, 'intercept_search_query']);
        add_action('wp_footer', [$this, 'add_recaptcha_script']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_settings_link']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('admin_init', [$this, 'process_settings_actions']);


        // Activation, Deactivation, and Cron Hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        add_action('telu_daily_log_cleanup_event', [$this, 'do_daily_log_cleanup']);
    }

    public function activate() {
        $this->create_log_table();
        if (!wp_next_scheduled('telu_daily_log_cleanup_event')) {
            wp_schedule_event(time(), 'daily', 'telu_daily_log_cleanup_event');
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook('telu_daily_log_cleanup_event');
    }

    public function plugin_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=telu-search-protection">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function add_settings_page() {
        add_options_page(
            'Search Protection Settings',
            'Search Protection',
            'manage_options',
            'telu-search-protection',
            [$this, 'settings_page_html']
        );
    }

    public function register_settings() {
        register_setting(
            'telu_search_protection_group',
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
            'delete_on_uninstall' => '0'
        ];
    }

    public function settings_page_html() {
        if (!current_user_can('manage_options')) return;

        $defaults = $this->get_default_settings();
        $saved_options = get_option($this->option_name);
        $options = wp_parse_args($saved_options, $defaults);

        global $wpdb;
        $recent_keywords = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT search_term, COUNT(*) as count FROM {$this->log_table} WHERE created_at >= %s GROUP BY search_term ORDER BY count DESC LIMIT 20",
                date('Y-m-d H:i:s', strtotime('-1 day'))
            )
        );

        ?>
        <div class="wrap">
            <h1>Search Protection Settings</h1>
            <?php settings_errors('telu_search_protection_notices'); ?>
            <p>Plugin ini membantu melindungi form pencarian Anda dari kata-kata yang tidak diinginkan dan spam menggunakan reCAPTCHA v3.</p>

            <h2>Informasi Kata Kunci Terblokir (24 Jam Terakhir)</h2>
            <form method="post" action="options.php">
                <?php settings_fields('telu_search_protection_group'); ?>

                <h2>Pengaturan reCAPTCHA v3</h2>
                <h2>Pengaturan Pemblokiran Kata</h2>
                <h2>Pengaturan Pesan & Pengalihan</h2>
                <h2>Manajemen Data</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Hapus Data Saat Uninstall</th>
                        <td>
                            <input type="checkbox" id="delete_on_uninstall" name="<?php echo esc_attr($this->option_name); ?>[delete_on_uninstall]" value="1" <?php checked('1', $options['delete_on_uninstall']); ?>>
                            <label for="delete_on_uninstall">Centang untuk menghapus semua pengaturan dan log 'Search Protection' saat plugin dihapus dari WordPress.</label>
                            <p class="description"><strong>Peringatan:</strong> Tindakan ini tidak dapat diurungkan.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Simpan Semua Perubahan'); ?>
            </form>

            <hr>

            <h2>Cadangkan & Pulihkan Pengaturan</h2>
            <div style="display: flex; gap: 20px;">
                <div style="flex: 1;">
                    <p>Simpan semua konfigurasi plugin di atas ke dalam sebuah file .json.</p>
                    <form method="post">
                        <input type="hidden" name="telu_sp_action" value="export_settings" />
                        <?php wp_nonce_field('telu_sp_export_nonce', 'telu_sp_export_nonce_field'); ?>
                        <?php submit_button('Cadangkan Pengaturan', 'secondary', 'submit', false); ?>
                    </form>
                </div>
                <div style="flex: 1; border-left: 1px solid #ddd; padding-left: 20px;">
                    <p>Pulihkan konfigurasi dari file cadangan. Pengaturan saat ini akan ditimpa.</p>
                    <form method="post" enctype="multipart/form-data">
                        <p>
                            <label for="import_file">Pilih File Cadangan (.json):</label><br>
                            <input type="file" id="import_file" name="import_file" accept=".json" required>
                        </p>
                        <input type="hidden" name="telu_sp_action" value="import_settings" />
                        <?php wp_nonce_field('telu_sp_import_nonce', 'telu_sp_import_nonce_field'); ?>
                        <?php submit_button('Impor & Pulihkan Pengaturan', 'primary', 'submit', false); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function process_settings_actions() {
        if (empty($_POST['telu_sp_action'])) {
            return;
        }

        if ($_POST['telu_sp_action'] == 'export_settings') {
            if (!isset($_POST['telu_sp_export_nonce_field']) || !wp_verify_nonce($_POST['telu_sp_export_nonce_field'], 'telu_sp_export_nonce')) {
                wp_die('Pemeriksaan keamanan gagal!');
            }
            $this->export_settings();
        }

        if ($_POST['telu_sp_action'] == 'import_settings') {
            if (!isset($_POST['telu_sp_import_nonce_field']) || !wp_verify_nonce($_POST['telu_sp_import_nonce_field'], 'telu_sp_import_nonce')) {
                wp_die('Pemeriksaan keamanan gagal!');
            }
            $this->import_settings();
        }
    }

    private function export_settings() {
        $settings = get_option($this->option_name);
        if (empty($settings)) $settings = $this->get_default_settings();

        $filename = 'search-protection-settings-backup-' . date('Y-m-d') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        ob_clean();
        flush();
        
        echo json_encode($settings, JSON_PRETTY_PRINT);
        exit;
    }

    private function import_settings() {
        if (empty($_FILES['import_file']['tmp_name'])) {
            add_settings_error('telu_search_protection_notices', 'import_error', 'Tidak ada file yang dipilih untuk diimpor.', 'error');
            return;
        }

        $file = $_FILES['import_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            add_settings_error('telu_search_protection_notices', 'import_error', 'Terjadi kesalahan saat mengunggah file.', 'error');
            return;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if ($extension !== 'json') {
            add_settings_error('telu_search_protection_notices', 'import_error', 'File tidak valid. Harap unggah file cadangan .json yang benar.', 'error');
            return;
        }

        $content = file_get_contents($file['tmp_name']);
        $imported_settings = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error('telu_search_protection_notices', 'import_error', 'Gagal membaca file cadangan. File JSON tidak valid.', 'error');
            return;
        }
        
        $sanitized_settings = $this->sanitize_settings($imported_settings);
        update_option($this->option_name, $sanitized_settings);

        add_settings_error('telu_search_protection_notices', 'import_success', 'Pengaturan berhasil diimpor dan disimpan.', 'success');
    }

    // ... All other existing methods like create_log_table, intercept_search_query, add_recaptcha_script, etc. remain the same ...
}

new TelU_Search_Protection_Full();
