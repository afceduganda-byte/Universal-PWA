<?php
/**
 * Login Brute-Force Protection
 * – Tracks failed login attempts per IP in transients
 * – Locks out the IP after configurable threshold
 * – Emails admin on lockout
 * – Clears counter on successful login
 */
defined( 'ABSPATH' ) || exit;

class WPSP_Login_Protection {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action( 'wp_login_failed',  [ $this, 'on_failed_login' ] );
        add_action( 'wp_login',         [ $this, 'on_success_login' ], 10, 2 );
        add_filter( 'authenticate',     [ $this, 'check_lockout' ], 30, 3 );
    }

    /* ── Hooks ───────────────────────────────────────────────────────── */

    public function on_failed_login( $username ) {
        $ip       = WPSP_IP_Manager::instance()->get_ip();
        $s        = get_option( 'wpsp_settings', [] );
        $max      = max( 1, (int) ( $s['login_max_attempts'] ?? 5 ) );
        $lockout  = max( 1, (int) ( $s['login_lockout_minutes'] ?? 30 ) );

        $key   = $this->attempt_key( $ip );
        $count = (int) get_transient( $key ) + 1;
        set_transient( $key, $count, $lockout * 60 );

        WPSP_IP_Manager::instance()->log(
            $ip,
            'login_failed',
            "Attempt {$count}/{$max} for username: " . sanitize_user( $username )
        );

        if ( $count >= $max ) {
            $lock_key = $this->lockout_key( $ip );
            set_transient( $lock_key, 1, $lockout * 60 );
            WPSP_IP_Manager::instance()->log( $ip, 'login_lockout', "Locked out after {$count} failures" );
            $this->notify_admin( $ip, $username, $count, $lockout );
        }
    }

    public function on_success_login( $username, $user ) {
        $ip = WPSP_IP_Manager::instance()->get_ip();
        delete_transient( $this->attempt_key( $ip ) );
        delete_transient( $this->lockout_key( $ip ) );
        WPSP_IP_Manager::instance()->log( $ip, 'login_success', "User: {$username}" );
    }

    public function check_lockout( $user, $username, $password ) {
        if ( empty( $username ) ) {
            return $user;
        }
        $ip = WPSP_IP_Manager::instance()->get_ip();

        // Whitelisted IPs are never locked out.
        if ( WPSP_IP_Manager::instance()->is_whitelisted( $ip ) ) {
            return $user;
        }

        if ( get_transient( $this->lockout_key( $ip ) ) ) {
            $s       = get_option( 'wpsp_settings', [] );
            $minutes = (int) ( $s['login_lockout_minutes'] ?? 30 );
            return new WP_Error(
                'wpsp_lockout',
                sprintf(
                    /* translators: %d minutes */
                    __( 'Too many failed login attempts. Your IP is locked out for %d minutes.', 'wp-shield-pro' ),
                    $minutes
                )
            );
        }
        return $user;
    }

    /* ── Admin notification ──────────────────────────────────────────── */

    private function notify_admin( $ip, $username, $attempts, $lockout_min ) {
        $s = get_option( 'wpsp_settings', [] );
        if ( empty( $s['enable_notifications'] ) ) {
            return;
        }
        $to      = sanitize_email( $s['notify_admin_email'] ?? get_option( 'admin_email' ) );
        $subject = '[WP Shield Pro] Brute-force login attempt blocked';
        $message = "WP Shield Pro has blocked a brute-force login attempt on " . get_bloginfo( 'name' ) . ".\n\n"
                 . "IP Address : {$ip}\n"
                 . "Username tried : " . sanitize_user( $username ) . "\n"
                 . "Failed attempts : {$attempts}\n"
                 . "Locked out for : {$lockout_min} minutes\n"
                 . "Time : " . current_time( 'mysql' ) . "\n\n"
                 . "To whitelist this IP, visit your WP Shield Pro settings.";
        wp_mail( $to, $subject, $message );
    }

    /* ── Key helpers ─────────────────────────────────────────────────── */

    private function attempt_key( $ip ) {
        return 'wpsp_attempts_' . md5( $ip );
    }

    private function lockout_key( $ip ) {
        return 'wpsp_lockout_' . md5( $ip );
    }
}
