<?php
/**
 * Bot Firewall & WAF
 * – Blocks known hacker/scraper user-agents
 * – Web Application Firewall: SQLi, XSS, path-traversal detection
 * – Rate limiting per IP
 * – Blocks XML-RPC abuse and author-enumeration scans
 */
defined( 'ABSPATH' ) || exit;

class WPSP_Bot_Firewall {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /* ── Entry point ─────────────────────────────────────────────────── */

    public function run() {
        $s = get_option( 'wpsp_settings', [] );

        if ( ! empty( $s['enable_bot_protection'] ) ) {
            if ( ! empty( $s['block_bad_useragents'] ) ) {
                $this->check_user_agent();
            }
            if ( ! empty( $s['enable_waf'] ) ) {
                $this->check_waf();
            }
            $this->check_rate_limit( (int) ( $s['rate_limit_per_minute'] ?? 60 ) );
        }

        if ( ! empty( $s['block_xmlrpc'] ) ) {
            $this->block_xmlrpc();
        }
        if ( ! empty( $s['block_author_scan'] ) ) {
            add_action( 'init', [ $this, 'block_author_enumeration' ], 2 );
        }
    }

    /* ── User-Agent check ────────────────────────────────────────────── */

    private function check_user_agent() {
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( $_SERVER['HTTP_USER_AGENT'] ) : '';

        // Empty / missing UA is almost always a bot.
        if ( '' === trim( $ua ) ) {
            $this->block_request( 'Empty user-agent' );
        }

        foreach ( $this->bad_uas() as $pattern ) {
            if ( strpos( $ua, strtolower( $pattern ) ) !== false ) {
                $this->block_request( "Bad user-agent matched: {$pattern}" );
            }
        }
    }

    /** Definitive list of user-agent signatures to block. */
    private function bad_uas() {
        return [
            // ── Attack tools ──
            'nikto', 'sqlmap', 'nmap', 'masscan', 'zmeu', 'morfeus',
            'w3af', 'skipfish', 'havij', 'acunetix', 'nessus', 'openvas',
            'zgrab', 'stretchoid', 'wfuzz', 'dirb', 'gobuster', 'wpscan',
            'hydra', 'medusa', 'burpsuite', 'appscan', 'webinspect',
            'netsparker', 'paros', 'webscarab', 'webshag', 'xsser',
            'commix', 'dirsearch', 'nuclei', 'metasploit', 'msfpayload',
            'sqlninja', 'jbrofuzz', 'peach fuzzer', 'vega', 'owasp',
            // ── Bad script libs ──
            'libwww-perl', 'lwp-trivial', 'lwp-request', 'python-urllib',
            'python-httpx', 'go-http-client', 'okhttp', 'java/',
            // ── Scrapers / rippers ──
            'httrack', 'webcopier', 'webzip', 'webstripper', 'sitegrabber',
            'wget/', 'getright', 'webwhacker', 'teleport pro',
            'webdevil', 'websauger', 'net vampire', 'webmirror',
            'offline explorer', 'black widow',
            // ── Vulnerability scanners ──
            'burp', 'dirbuster', 'fimap', 'grabber', 'iron wasp',
            'n-stealth', 'pangolin', 'proxystrike', 'saint',
            // ── Email harvesters ──
            'emailsiphon', 'emailwolf', 'emailcollector', 'email harvester',
            'emailharvest', 'extract', 'extractor',
            // ── Other recognisable malware UAs ──
            'muieblackcat', 'indy library', 'morfeus fucking scanner',
            'jorgee', 'lmao', 'internet explorer 5',
        ];
    }

    /* ── WAF ─────────────────────────────────────────────────────────── */

    private function check_waf() {
        // WordPress GET params that legitimately contain URLs – never flag these.
        $safe_get_keys = [ 'redirect_to', '_wp_http_referer', 'action', 'reauth', 'loggedout' ];
        $get_data = $_GET;
        foreach ( $safe_get_keys as $k ) {
            unset( $get_data[ $k ] );
        }

        // Build scan string from sanitised GET, full POST, and cookies.
        // Use only the path (no query string) from REQUEST_URI to avoid
        // catching legitimate redirect_to= values that include the site URL.
        $data = '';
        foreach ( [ $get_data, $_POST, $_COOKIE ] as $input ) {
            $data .= ' ' . $this->flatten( $input );
        }
        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $path_only = strtok( $_SERVER['REQUEST_URI'], '?' );
            $data .= ' ' . rawurldecode( $path_only );
        }

        foreach ( $this->waf_patterns() as $label => $pattern ) {
            if ( preg_match( $pattern, $data ) ) {
                $ip = WPSP_IP_Manager::instance()->get_ip();
                WPSP_IP_Manager::instance()->log( $ip, 'waf_block', "Pattern: {$label}" );

                $s = get_option( 'wpsp_settings', [] );
                if ( ! empty( $s['auto_block_on_attack'] ) ) {
                    WPSP_IP_Manager::instance()->block( $ip, "WAF: {$label}" );
                }

                WPSP_IP_Manager::instance()->die_403( 'Request blocked by security firewall.' );
            }
        }
    }

    private function flatten( $arr, $depth = 3 ) {
        if ( ! is_array( $arr ) || $depth === 0 ) {
            return (string) $arr;
        }
        $out = '';
        foreach ( $arr as $v ) {
            $out .= ' ' . ( is_array( $v ) ? $this->flatten( $v, $depth - 1 ) : (string) $v );
        }
        return $out;
    }

    private function waf_patterns() {
        return [
            // SQL injection
            'sqli_union'     => '/\bunion\b.{0,20}\bselect\b/i',
            'sqli_select'    => '/\bselect\b.{0,30}\bfrom\b.{0,30}\bwhere\b/i',
            'sqli_insert'    => '/\b(insert\s+into|update\s+\w+\s+set|delete\s+from|drop\s+(table|database))\b/i',
            'sqli_exec'      => '/\b(exec|execute|xp_cmdshell|sp_executesql)\s*\(/i',
            'sqli_blind'     => '/\b(sleep|benchmark|waitfor\s+delay)\s*\(/i',
            'sqli_comment'   => '/(--[\s\r\n]|;\s*--|\/\*.*?\*\/)/s',
            'sqli_or_true'   => '/(\'\s*or\s*[\'\d]|"\s*or\s*["\d]|1\s*=\s*1)/i',
            'sqli_cast'      => '/\b(cast|convert|char|nchar|varchar)\s*\(/i',
            // XSS
            'xss_script'     => '/<\s*script[^>]*>/i',
            'xss_javascript' => '/javascript\s*:/i',
            'xss_event'      => '/\bon(?:click|error|load|mouseover|mouseout|focus|blur|submit|change|keypress|keydown|keyup|dragover|drop|copy|paste)\s*=/i',
            'xss_eval'       => '/\beval\s*\(/i',
            'xss_cookie'     => '/document\s*\.\s*(cookie|write|location)/i',
            'xss_embed'      => '/<\s*(iframe|object|embed|applet|link|meta)\s[^>]*(src|href)\s*=/i',
            'xss_expression' => '/\bexpression\s*\(/i',
            'xss_vbscript'   => '/vbscript\s*:/i',
            // Path traversal / LFI / RFI
            'traversal'      => '/(\.\.[\/\\\\]){2,}/i',
            'traversal_enc'  => '/(%2e%2e[%2f%5c]){2,}/i',
            'lfi_etc'        => '/(\/etc\/(passwd|shadow|hosts)|\/proc\/self\/)/i',
            'rfi_proto'      => '/=\s*(https?|ftp|php|data|zip)\:\/\/.{10,}/i',
            // WordPress-specific
            'wp_config'      => '/wp-config\.php/i',
            'php_exec'       => '/<\?php/i',
            'shell_cmd'      => '/\b(passthru|shell_exec|system|popen|proc_open|pcntl_exec)\s*\(/i',
        ];
    }

    /* ── Rate limiting ───────────────────────────────────────────────── */

    private function check_rate_limit( $limit ) {
        if ( $limit <= 0 ) {
            return;
        }
        $ip  = WPSP_IP_Manager::instance()->get_ip();
        $key = 'wpsp_rl_' . md5( $ip );
        $count = (int) get_transient( $key );

        if ( $count >= $limit ) {
            WPSP_IP_Manager::instance()->log( $ip, 'rate_limit', "Exceeded {$limit} req/min" );
            WPSP_IP_Manager::instance()->die_403( 'Too many requests. Please slow down.' );
        }

        if ( $count === 0 ) {
            set_transient( $key, 1, 60 );
        } else {
            set_transient( $key, $count + 1, 60 );
        }
    }

    /* ── XML-RPC block ───────────────────────────────────────────────── */

    private function block_xmlrpc() {
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            $ip = WPSP_IP_Manager::instance()->get_ip();
            WPSP_IP_Manager::instance()->log( $ip, 'xmlrpc_blocked', 'XML-RPC request blocked' );
            WPSP_IP_Manager::instance()->die_403( 'XML-RPC is disabled on this site.' );
        }
        // Also disable via filter (for non-direct requests)
        add_filter( 'xmlrpc_enabled', '__return_false' );
        add_filter( 'xmlrpc_methods', function () { return []; } );
    }

    /* ── Author enumeration block ────────────────────────────────────── */

    public function block_author_enumeration() {
        if ( ! is_admin()
            && isset( $_GET['author'] )
            && ! empty( $_GET['author'] )
            && is_numeric( $_GET['author'] )
        ) {
            $ip = WPSP_IP_Manager::instance()->get_ip();
            WPSP_IP_Manager::instance()->log( $ip, 'author_scan_blocked', 'Author enumeration attempt' );
            wp_redirect( home_url( '/' ), 301 );
            exit;
        }
    }

    /* ── Internal helper ─────────────────────────────────────────────── */

    private function block_request( $reason ) {
        $ip = WPSP_IP_Manager::instance()->get_ip();
        WPSP_IP_Manager::instance()->log( $ip, 'bot_blocked', $reason );

        $s = get_option( 'wpsp_settings', [] );
        if ( ! empty( $s['auto_block_on_attack'] ) ) {
            WPSP_IP_Manager::instance()->block( $ip, $reason );
        }

        WPSP_IP_Manager::instance()->die_403( 'Automated request blocked.' );
    }
}
