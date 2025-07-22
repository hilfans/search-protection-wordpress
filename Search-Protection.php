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
                <?php settings_fields('telu_search_protection_group'); ?>

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
                        <input type="hidden" name="telu_sp_action" value="export_settings" />
                        <?php wp_nonce_field('telu_sp_export_nonce', 'telu_sp_export_nonce_field'); ?>
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
