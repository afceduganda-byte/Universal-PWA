<?php
/**
 * IP Manager – blocks/whitelists individual IPs and CIDR ranges.
 * Also provides the shared event-logging method used by all modules.
 */
defined( 'ABSPATH' ) || exit;

class WPSP_IP_Manager {

    private static $instance = null;
    private $client_ip       = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /* ── Public entry point ──────────────────────────────────────────── */

    /** Called very early (plugins_loaded priority 1). */
    public function run() {
        $ip = $this->get_ip();
        if ( $this->is_whitelisted( $ip ) ) {
            return;
        }
        if ( $this->is_blocked( $ip ) ) {
            $this->log( $ip, 'blocked_ip', 'IP is on the blocklist' );
            $this->die_403( 'Your IP address has been blocked.' );
        }
    }

    /* ── IP helpers ──────────────────────────────────────────────────── */

    public function get_ip() {
        if ( $this->client_ip !== null ) {
            return $this->client_ip;
        }
        $headers = [
            'HTTP_CF_CONNECTING_IP',   // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];
        foreach ( $headers as $h ) {
            if ( ! empty( $_SERVER[ $h ] ) ) {
                $parts = explode( ',', $_SERVER[ $h ] );
                $ip    = trim( $parts[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    $this->client_ip = $ip;
                    return $ip;
                }
            }
        }
        $this->client_ip = '0.0.0.0';
        return $this->client_ip;
    }

    public function is_blocked( $ip ) {
        $list = get_option( 'wpsp_blocked_ips', [] );
        foreach ( $list as $entry ) {
            $entry_ip = is_array( $entry ) ? $entry['ip'] : $entry;
            if ( strpos( $entry_ip, '/' ) !== false ) {
                if ( $this->cidr_match( $ip, $entry_ip ) ) {
                    return true;
                }
            } elseif ( $entry_ip === $ip ) {
                return true;
            }
        }
        return false;
    }

    public function is_whitelisted( $ip ) {
        $list = get_option( 'wpsp_whitelisted_ips', [] );
        foreach ( $list as $w ) {
            if ( $w === $ip ) {
                return true;
            }
            if ( strpos( $w, '/' ) !== false && $this->cidr_match( $ip, $w ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add an IP (or CIDR) to the blocklist.
     *
     * @param string $ip
     * @param string $reason
     * @return bool  false if already blocked or whitelisted.
     */
    public function block( $ip, $reason = 'Security violation' ) {
        if ( $this->is_whitelisted( $ip ) ) {
            return false;
        }
        $list = get_option( 'wpsp_blocked_ips', [] );
        foreach ( $list as $e ) {
            if ( ( is_array( $e ) ? $e['ip'] : $e ) === $ip ) {
                return false;
            }
        }
        $list[] = [
            'ip'     => $ip,
            'reason' => sanitize_text_field( $reason ),
            'added'  => gmdate( 'Y-m-d H:i:s' ),
        ];
        update_option( 'wpsp_blocked_ips', $list );
        $this->log( $ip, 'ip_added_to_blocklist', $reason );
        return true;
    }

    public function unblock( $ip ) {
        $list = get_option( 'wpsp_blocked_ips', [] );
        $list = array_values( array_filter( $list, function ( $e ) use ( $ip ) {
            return ( is_array( $e ) ? $e['ip'] : $e ) !== $ip;
        } ) );
        update_option( 'wpsp_blocked_ips', $list );
    }

    public function add_to_whitelist( $ip ) {
        $list = get_option( 'wpsp_whitelisted_ips', [] );
        if ( ! in_array( $ip, $list, true ) ) {
            $list[] = $ip;
            update_option( 'wpsp_whitelisted_ips', $list );
        }
    }

    public function remove_from_whitelist( $ip ) {
        $list = array_values( array_filter(
            get_option( 'wpsp_whitelisted_ips', [] ),
            function ( $w ) use ( $ip ) { return $w !== $ip; }
        ) );
        update_option( 'wpsp_whitelisted_ips', $list );
    }

    /* ── Logging ─────────────────────────────────────────────────────── */

    /**
     * Write a security event to the DB log.
     * Called by every module.
     */
    public function log( $ip, $event_type, $details = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpsp_logs';

        // Silently skip if table not yet created (e.g. during fresh activation).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $table !== $exists ) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->insert(
            $table,
            [
                'log_time'   => current_time( 'mysql' ),
                'ip_address' => substr( (string) $ip, 0, 45 ),
                'event_type' => substr( sanitize_key( $event_type ), 0, 60 ),
                'details'    => sanitize_textarea_field( (string) $details ),
                'req_uri'    => isset( $_SERVER['REQUEST_URI'] )
                    ? substr( esc_url_raw( $_SERVER['REQUEST_URI'] ), 0, 500 )
                    : '',
                'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] )
                    ? substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ), 0, 500 )
                    : '',
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    /* ── Termination helper ──────────────────────────────────────────── */

    public function die_403( $message = 'Access denied.' ) {
        status_header( 403 );
        nocache_headers();
        wp_die(
            '<h1 style="font-family:sans-serif">403 – Access Denied</h1>'
            . '<p style="font-family:sans-serif">' . esc_html( $message ) . '</p>',
            'Access Denied – WP Shield Pro',
            [ 'response' => 403 ]
        );
    }

    /* ── CIDR helper ─────────────────────────────────────────────────── */

    private function cidr_match( $ip, $cidr ) {
        if ( strpos( $ip, ':' ) !== false ) {
            return false; // IPv6 CIDR not supported yet
        }
        [ $subnet, $mask ] = explode( '/', $cidr );
        $ip_long     = ip2long( $ip );
        $subnet_long = ip2long( $subnet );
        if ( false === $ip_long || false === $subnet_long ) {
            return false;
        }
        $mask_long    = -1 << ( 32 - (int) $mask );
        $subnet_long &= $mask_long;
        return ( $ip_long & $mask_long ) === $subnet_long;
    }
}
