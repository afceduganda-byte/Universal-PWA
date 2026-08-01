<?php
/**
 * WordPress Hardening
 * – Removes version fingerprints
 * – Disables file editing in the dashboard
 * – Disables pingbacks / trackbacks
 * – Hides sensitive headers
 * – Disables REST API user-listing for non-authenticated requests
 * – Removes unnecessary meta tags
 */
defined( 'ABSPATH' ) || exit;

class WPSP_Hardening {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        $s = get_option( 'wpsp_settings', [] );

        if ( ! empty( $s['remove_wp_version'] ) ) {
            $this->hide_wp_version();
        }
        if ( ! empty( $s['disable_file_edit'] ) ) {
            $this->disable_file_editing();
        }
        if ( ! empty( $s['disable_pingbacks'] ) ) {
            $this->disable_pingbacks();
        }

        // Always apply header hardening and REST-API protection.
        $this->harden_headers();
        $this->protect_rest_api();
        $this->block_sensitive_files();
        $this->remove_meta_clutter();
    }

    /* ── Version hiding ──────────────────────────────────────────────── */

    private function hide_wp_version() {
        // Remove from HTML source
        remove_action( 'wp_head', 'wp_generator' );

        // Remove from all enqueued script/style URLs
        add_filter( 'the_generator',      '__return_empty_string' );
        add_filter( 'style_loader_src',   [ $this, 'strip_ver_query' ], 9999 );
        add_filter( 'script_loader_src',  [ $this, 'strip_ver_query' ], 9999 );

        // Remove from RSS feeds
        add_filter( 'get_the_generator_rss2',    '__return_empty_string' );
        add_filter( 'get_the_generator_comment',  '__return_empty_string' );
    }

    /** Strip ?ver=x.y.z from static resource URLs. */
    public function strip_ver_query( $src ) {
        global $wp_version;
        if ( strpos( $src, "ver={$wp_version}" ) !== false ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }

    /* ── Disable dashboard file editing ──────────────────────────────── */

    private function disable_file_editing() {
        if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
            define( 'DISALLOW_FILE_EDIT', true );
        }
        if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
            // Uncommenting this would also block plugin/theme installs.
            // define( 'DISALLOW_FILE_MODS', true );
        }
    }

    /* ── Pingbacks / trackbacks ──────────────────────────────────────── */

    private function disable_pingbacks() {
        add_filter( 'xmlrpc_methods', function ( $methods ) {
            unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
            return $methods;
        } );
        add_action( 'pre_ping', function ( &$links ) { $links = []; } );
        add_filter( 'wp_headers', function ( $headers ) {
            unset( $headers['X-Pingback'] );
            return $headers;
        } );
        // Turn off pings for the default post type.
        add_filter( 'pings_open', '__return_false' );
    }

    /* ── Security headers ────────────────────────────────────────────── */

    private function harden_headers() {
        add_action( 'send_headers', function () {
            if ( headers_sent() ) {
                return;
            }
            // Prevent clickjacking
            header( 'X-Frame-Options: SAMEORIGIN' );
            // Prevent MIME sniffing
            header( 'X-Content-Type-Options: nosniff' );
            // Basic XSS protection for older browsers
            header( 'X-XSS-Protection: 1; mode=block' );
            // Referrer policy
            header( 'Referrer-Policy: strict-origin-when-cross-origin' );
            // Remove server signature (may not work on all hosts)
            header_remove( 'X-Powered-By' );
            header_remove( 'Server' );
        } );
    }

    /* ── REST API – hide user list from strangers ────────────────────── */

    private function protect_rest_api() {
        // Prevent anonymous access to /wp-json/wp/v2/users
        add_filter( 'rest_endpoints', function ( $endpoints ) {
            if ( ! is_user_logged_in() ) {
                unset( $endpoints['/wp/v2/users'] );
                unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
            }
            return $endpoints;
        } );
    }

    /* ── Protect sensitive paths ─────────────────────────────────────── */

    private function block_sensitive_files() {
        add_action( 'init', function () {
            $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
            $blocked = [
                'wp-config.php',
                'readme.html',
                'license.txt',
                'wp-cron.php',
                'install.php',
            ];
            foreach ( $blocked as $f ) {
                if ( preg_match( '/' . preg_quote( $f, '/' ) . '$/i', $uri ) ) {
                    $ip = WPSP_IP_Manager::instance()->get_ip();
                    WPSP_IP_Manager::instance()->log( $ip, 'sensitive_file_access', "Blocked: {$f}" );
                    WPSP_IP_Manager::instance()->die_403( 'Access to this file is forbidden.' );
                }
            }
        }, 5 );
    }

    /* ── Remove meta clutter ─────────────────────────────────────────── */

    private function remove_meta_clutter() {
        // Remove RSD link (used by old blogging apps)
        remove_action( 'wp_head', 'rsd_link' );
        // Remove Windows Live Writer manifest
        remove_action( 'wp_head', 'wlwmanifest_link' );
        // Remove shortlink
        remove_action( 'wp_head', 'wp_shortlink_wp_head' );
        // Remove adjacent posts links (leaks post IDs)
        remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
        // Remove REST API link (leaks endpoint)
        remove_action( 'wp_head', 'rest_output_link_wp_head' );
        // Remove oEmbed
        remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
    }
}
