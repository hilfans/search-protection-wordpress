<?php
/**
 * Plugin Name: Search Protection
 * Plugin URI: https://github.com/hilfans/search-protection-wordpress
 * Description: Lindungi form pencarian dari spam dan karakter berbahaya dengan daftar hitam dan reCAPTCHA v3.
 * Version: 1.1.1
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
        add_action('pre_get_posts', [$this, 'intercept_search_query']); // Changed hook for better timing
        add_action('wp_footer', [$this, 'add_recaptcha_script']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_settings_link']);
        add_action('admin_notices', [$this, 'admin_notices']);

        // Activation, Deactivation, and Cron Hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        add_action('telu_daily_log_cleanup_event', [$this, 'do_daily_log_cleanup']);
    }

    /**
     * Plugin activation tasks.
     */
    public function activate() {
        $this->create_log_table();
        if (!wp_next_scheduled('telu_daily_log_cleanup_event')) {
            wp_schedule_event(time(), 'daily', 'telu_daily_log_cleanup_event');
        }
    }

    /**
     * Plugin deactivation tasks.
     */
    public function deactivate() {
        wp_clear_scheduled_hook('telu_daily_log_cleanup_event');
    }

    /**
     * Add a settings link to the plugins page.
     */
    public function plugin_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=telu-search-protection">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Add the plugin settings page to the admin menu.
     */
    public function add_settings_page() {
        add_options_page(
            'Search Protection Settings',
            'Search Protection',
            'manage_options',
            'telu-search-protection',
            [$this, 'settings_page_html']
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'telu_search_protection_group',
            $this->option_name,
            [$this, 'sanitize_settings']
        );
    }
    
    /**
     * Sanitize settings before saving.
     */
    public function sanitize_settings($input) {
        $new_input = [];
        $new_input['enable_recaptcha'] = isset($input['enable_recaptcha']) ? '1' : '0';
        $new_input['site_key'] = sanitize_text_field($input['site_key'] ?? '');
        $new_input['secret_key'] = sanitize_text_field($input['secret_key'] ?? '');
        $new_input['blacklist'] = wp_kses_post(trim($input['blacklist'] ?? ''));
        $new_input['msg_recaptcha_fail'] = sanitize_text_field($input['msg_recaptcha_fail'] ?? '');
        $new_input['msg_badword'] = sanitize_text_field($input['msg_badword'] ?? '');
        $new_input['msg_regex'] = sanitize_text_field($input['msg_regex'] ?? '');
        $new_input['block_page_url'] = isset($input['block_page_url']) ? esc_url_raw($input['block_page_url']) : '';
        return $new_input;
    }


    /**
     * Render the settings page HTML.
     */
    public function settings_page_html() {
        if (!current_user_can('manage_options')) return;

        $defaults = [
            'enable_recaptcha' => '0',
            'site_key' => '',
            'secret_key' => '',
            'blacklist' => '',
            'msg_recaptcha_fail' => 'reCAPTCHA tidak terdeteksi. Coba ulangi pencarian Anda.',
            'msg_badword' => 'Pencarian diblokir karena mengandung kata yang tidak diizinkan.',
            'msg_regex' => 'Pencarian diblokir karena mengandung pola karakter yang tidak diizinkan.',
            'block_page_url' => ''
        ];
        $options = get_option($this->option_name, $defaults);
        // Ensure all keys from defaults exist in options
        $options = array_merge($defaults, $options);

        ?>
        <div class="wrap">
            <h1>Search Protection Settings</h1>
            <p>Plugin ini membantu melindungi form pencarian Anda dari kata-kata yang tidak diinginkan dan spam menggunakan reCAPTCHA v3.</p>
            
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
                <p><em>Catatan: Log pencarian yang diblokir akan dihapus otomatis setiap 24 jam untuk menjaga performa.</em></p>
                <?php submit_button('Save Changes'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Create the log table on activation.
     */
    public function create_log_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
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

    /**
     * Clean up logs older than 24 hours via daily cron job.
     */
    public function do_daily_log_cleanup() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$this->log_table} WHERE created_at < NOW() - INTERVAL 1 DAY");
    }

    /**
     * Intercept the search query to validate it.
     * Hooked to 'pre_get_posts' to run before the main query.
     */
    public function intercept_search_query($query) {
        // Only run on the frontend for the main search query.
        if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
            return;
        }

        $search_query = trim($query->get('s'));
        if (empty($search_query)) {
            return;
        }

        $options = get_option($this->option_name);
        
        // --- 1. Blacklist Check ---
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

        // --- 2. reCAPTCHA Check ---
        if (!empty($options['enable_recaptcha']) && $options['enable_recaptcha'] === '1') {
            $secret_key = $options['secret_key'] ?? '';
            $token = $_POST['token'] ?? $_GET['token'] ?? '';

            if (empty($secret_key)) return; // Silently fail if not configured
            if (empty($token)) {
                $this->block_request($options['msg_recaptcha_fail'], 'No reCAPTCHA Token');
            }

            $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
                'body' => ['secret' => $secret_key, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR']]
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
    
    /**
     * Add the reCAPTCHA v3 JavaScript to the site footer.
     */
    public function add_recaptcha_script() {
        $options = get_option($this->option_name);
        if (empty($options['enable_recaptcha']) || $options['enable_recaptcha'] !== '1' || empty($options['site_key'])) {
            return;
        }
        $site_key = $options['site_key'];
        ?>
        <script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr($site_key); ?>"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const searchForms = document.querySelectorAll('form[role="search"], form.search-form, form[action*="/?s="]');
                searchForms.forEach(form => {
                    form.addEventListener('submit', e => {
                        e.preventDefault();
                        if (form.querySelector('input[name="token"]')) {
                            form.submit();
                            return;
                        }
                        grecaptcha.ready(() => {
                            grecaptcha.execute('<?php echo esc_js($site_key); ?>', { action: 'search' }).then(token => {
                                const tokenInput = document.createElement('input');
                                tokenInput.type = 'hidden';
                                tokenInput.name = 'token';
                                tokenInput.value = token;
                                form.appendChild(tokenInput);
                                form.submit();
                            });
                        });
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Block the request, log it, and show a message or redirect.
     */
    private function block_request($message, $reason) {
        global $wpdb;
        $search_query = $_GET['s'] ?? '';
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

        $wpdb->insert($this->log_table, [
            'search_term'    => sanitize_text_field($search_query),
            'blocked_reason' => sanitize_text_field($reason),
            'user_ip'        => sanitize_text_field($user_ip)
        ]);

        $options = get_option($this->option_name);
        $block_page_url = $options['block_page_url'] ?? '';
        
        if (!empty($block_page_url) && filter_var($block_page_url, FILTER_VALIDATE_URL)) {
            wp_redirect(esc_url_raw($block_page_url));
            exit;
        }
        
        wp_die(esc_html($message), 'Pencarian Diblokir', ['response' => 403, 'back_link' => true]);
    }

    /**
     * Show admin notices on the settings page.
     */
    public function admin_notices() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'settings_page_telu-search-protection') {
            echo '<div class="notice notice-info"><p>Pastikan Anda telah mendaftarkan domain Anda di <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA (v3)</a> untuk mendapatkan Site Key dan Secret Key.</p></div>';
            
            $options = get_option($this->option_name);
            if (!empty($options['enable_recaptcha']) && (empty($options['site_key']) || empty($options['secret_key']))) {
                 echo '<div class="notice notice-warning is-dismissible"><p><strong>Peringatan:</strong> reCAPTCHA diaktifkan, tetapi Site Key atau Secret Key belum diisi. Fitur reCAPTCHA tidak akan berfungsi.</p></div>';
            }
        }
    }
}

new TelU_Search_Protection_Full();
