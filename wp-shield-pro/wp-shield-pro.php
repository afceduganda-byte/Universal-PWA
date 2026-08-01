<?php
/**
 * Plugin Name:       WP Shield Pro
 * Description:       Master security shield – blocks bots, spam comments, hackers, brute-force attacks and malicious crawlers site-wide.
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            WP Shield Security
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-shield-pro
 */

defined( 'ABSPATH' ) || exit;

define( 'WPSP_VER',  '1.0.0' );
define( 'WPSP_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WPSP_URL',  plugin_dir_url( __FILE__ ) );
define( 'WPSP_FILE', __FILE__ );

/* ── Autoload modules ───────────────────────────────────────────────── */
foreach ( [ 'ip-manager', 'bot-firewall', 'comment-protection', 'login-protection', 'hardening' ] as $m ) {
    require_once WPSP_DIR . "includes/class-{$m}.php";
}
if ( is_admin() ) {
    require_once WPSP_DIR . 'admin/class-admin.php';
}

/* ══════════════════════════════════════════════════════════════════════
   Core bootstrap
   ══════════════════════════════════════════════════════════════════════ */
final class WP_Shield_Pro {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook( WPSP_FILE,   [ $this, 'activate' ] );
        register_deactivation_hook( WPSP_FILE, [ $this, 'deactivate' ] );

        // Priority 1: run IP + bot checks before anything else.
        add_action( 'plugins_loaded', [ $this, 'early_check' ], 1 );
        // Priority 10: boot the rest of the modules.
        add_action( 'plugins_loaded', [ $this, 'boot' ], 10 );
    }

    /** IP block + bot/WAF check – runs before most of WordPress loads. */
    public function early_check() {
        WPSP_IP_Manager::instance()->run();
        WPSP_Bot_Firewall::instance()->run();
    }

    /** Boot feature modules based on saved settings. */
    public function boot() {
        $s = get_option( 'wpsp_settings', [] );

        if ( ! empty( $s['enable_comment_protection'] ) ) {
            WPSP_Comment_Protection::instance()->init();
        }
        if ( ! empty( $s['enable_login_protection'] ) ) {
            WPSP_Login_Protection::instance()->init();
        }
        if ( ! empty( $s['enable_hardening'] ) ) {
            WPSP_Hardening::instance()->init();
        }
        if ( is_admin() ) {
            WPSP_Admin::instance()->init();
        }
    }

    /* ── Activation ──────────────────────────────────────────────────── */
    public function activate() {
        $defaults = [
            'enable_bot_protection'     => 1,
            'enable_comment_protection' => 1,
            'enable_login_protection'   => 1,
            'enable_hardening'          => 1,
            // Login protection
            'login_max_attempts'        => 5,
            'login_lockout_minutes'     => 30,
            // Comment protection
            'comment_min_time'          => 5,
            'comment_max_links'         => 2,
            // Bot / WAF
            'block_xmlrpc'              => 1,
            'block_author_scan'         => 1,
            'block_bad_useragents'      => 1,
            'enable_waf'                => 1,
            'rate_limit_per_minute'     => 60,
            // Hardening
            'remove_wp_version'         => 1,
            'disable_file_edit'         => 1,
            'disable_pingbacks'         => 1,
            // Auto response
            'auto_block_on_attack'      => 1,
            'enable_notifications'      => 1,
            'notify_admin_email'        => get_option( 'admin_email' ),
        ];

        if ( false === get_option( 'wpsp_settings' ) ) {
            add_option( 'wpsp_settings', $defaults );
        }
        foreach ( [ 'wpsp_blocked_ips', 'wpsp_whitelisted_ips' ] as $k ) {
            if ( false === get_option( $k ) ) {
                add_option( $k, [] );
            }
        }

        $this->create_tables();
        flush_rewrite_rules();
    }

    private function create_tables() {
        global $wpdb;
        $t   = $wpdb->prefix . 'wpsp_logs';
        $col = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$t} (
            id         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            log_time   datetime            NOT NULL,
            ip_address varchar(45)         NOT NULL DEFAULT '',
            event_type varchar(60)         NOT NULL DEFAULT '',
            details    text,
            req_uri    varchar(500),
            user_agent varchar(500),
            PRIMARY KEY (id),
            KEY ip_address (ip_address),
            KEY event_type (event_type),
            KEY log_time   (log_time)
        ) {$col};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public function deactivate() {
        wp_clear_scheduled_hook( 'wpsp_cleanup_logs' );
        flush_rewrite_rules();
    }
}

WP_Shield_Pro::instance();
