<?php
/**
 * Admin Interface for WP Shield Pro
 * – Dashboard with live stats
 * – Settings page
 * – IP Manager (block / whitelist / unblock)
 * – Security Log viewer
 */
defined( 'ABSPATH' ) || exit;

class WPSP_Admin {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init',            [ $this, 'handle_form_actions' ] );
        add_action( 'admin_notices',         [ $this, 'show_notices' ] );
    }

    /* ── Menu registration ───────────────────────────────────────────── */

    public function register_menu() {
        add_menu_page(
            'WP Shield Pro',
            'WP Shield',
            'manage_options',
            'wpsp-dashboard',
            [ $this, 'page_dashboard' ],
            'dashicons-shield',
            80
        );
        add_submenu_page( 'wpsp-dashboard', 'Dashboard',     'Dashboard',     'manage_options', 'wpsp-dashboard',   [ $this, 'page_dashboard' ] );
        add_submenu_page( 'wpsp-dashboard', 'Settings',      'Settings',      'manage_options', 'wpsp-settings',    [ $this, 'page_settings' ] );
        add_submenu_page( 'wpsp-dashboard', 'IP Manager',    'IP Manager',    'manage_options', 'wpsp-ip-manager',  [ $this, 'page_ip_manager' ] );
        add_submenu_page( 'wpsp-dashboard', 'Security Logs', 'Security Logs', 'manage_options', 'wpsp-logs',        [ $this, 'page_logs' ] );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'wpsp-' ) === false ) {
            return;
        }
        wp_enqueue_style( 'wpsp-admin', WPSP_URL . 'assets/admin-style.css', [], WPSP_VER );
    }

    /* ══════════════════════════════════════════════════════════════════
       Dashboard
       ══════════════════════════════════════════════════════════════════ */

    public function page_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'wp-shield-pro' ) );
        }
        global $wpdb;
        $table  = $wpdb->prefix . 'wpsp_logs';
        $total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
        $today  = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
            "SELECT COUNT(*) FROM {$table} WHERE DATE(log_time) = %s", gmdate( 'Y-m-d' )
        ) );
        $blocked_ips = count( get_option( 'wpsp_blocked_ips', [] ) );
        $recent = $wpdb->get_results( // phpcs:ignore
            "SELECT * FROM {$table} ORDER BY log_time DESC LIMIT 10"
        );
        $s = get_option( 'wpsp_settings', [] );
        ?>
        <div class="wrap wpsp-wrap">
            <h1 class="wpsp-page-title"><span class="dashicons dashicons-shield"></span> WP Shield Pro</h1>

            <div class="wpsp-stats-row">
                <div class="wpsp-stat-card wpsp-stat-green">
                    <div class="wpsp-stat-icon dashicons dashicons-shield-alt"></div>
                    <div class="wpsp-stat-number"><?php echo esc_html( number_format( $total ) ); ?></div>
                    <div class="wpsp-stat-label">Events Logged</div>
                </div>
                <div class="wpsp-stat-card wpsp-stat-blue">
                    <div class="wpsp-stat-icon dashicons dashicons-calendar-alt"></div>
                    <div class="wpsp-stat-number"><?php echo esc_html( number_format( $today ) ); ?></div>
                    <div class="wpsp-stat-label">Events Today</div>
                </div>
                <div class="wpsp-stat-card wpsp-stat-red">
                    <div class="wpsp-stat-icon dashicons dashicons-dismiss"></div>
                    <div class="wpsp-stat-number"><?php echo esc_html( number_format( $blocked_ips ) ); ?></div>
                    <div class="wpsp-stat-label">Blocked IPs</div>
                </div>
                <div class="wpsp-stat-card wpsp-stat-<?php echo ! empty( $s['enable_bot_protection'] ) ? 'green' : 'grey'; ?>">
                    <div class="wpsp-stat-icon dashicons dashicons-admin-network"></div>
                    <div class="wpsp-stat-number"><?php echo ! empty( $s['enable_bot_protection'] ) ? '✔ ON' : '✘ OFF'; ?></div>
                    <div class="wpsp-stat-label">Bot Firewall</div>
                </div>
            </div>

            <div class="wpsp-panel">
                <h2>Recent Security Events</h2>
                <?php if ( empty( $recent ) ) : ?>
                    <p class="wpsp-empty">No events logged yet. The plugin is actively protecting your site.</p>
                <?php else : ?>
                <table class="wpsp-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>IP Address</th>
                            <th>Event</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $recent as $row ) : ?>
                        <tr>
                            <td class="wpsp-td-time"><?php echo esc_html( $row->log_time ); ?></td>
                            <td><code><?php echo esc_html( $row->ip_address ); ?></code></td>
                            <td><span class="wpsp-badge wpsp-badge-<?php echo esc_attr( $this->badge_class( $row->event_type ) ); ?>"><?php echo esc_html( $row->event_type ); ?></span></td>
                            <td><?php echo esc_html( wp_trim_words( $row->details, 12 ) ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpsp-logs' ) ); ?>" class="button">View all logs →</a></p>
                <?php endif; ?>
            </div>

            <div class="wpsp-module-status">
                <h2>Module Status</h2>
                <div class="wpsp-module-grid">
                    <?php
                    $modules = [
                        'enable_bot_protection'     => [ 'Bot Firewall &amp; WAF', 'dashicons-shield' ],
                        'enable_comment_protection' => [ 'Comment Protection',      'dashicons-format-chat' ],
                        'enable_login_protection'   => [ 'Login Protection',        'dashicons-lock' ],
                        'enable_hardening'          => [ 'WP Hardening',            'dashicons-hammer' ],
                    ];
                    foreach ( $modules as $key => [ $label, $icon ] ) :
                        $active = ! empty( $s[ $key ] );
                    ?>
                    <div class="wpsp-module-card <?php echo $active ? 'active' : 'inactive'; ?>">
                        <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
                        <strong><?php echo wp_kses_post( $label ); ?></strong>
                        <span class="wpsp-status-dot"><?php echo $active ? 'Active' : 'Disabled'; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p style="margin-top:12px"><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpsp-settings' ) ); ?>" class="button button-primary">Manage Settings →</a></p>
            </div>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════════════════════════════
       Settings
       ══════════════════════════════════════════════════════════════════ */

    public function page_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'wp-shield-pro' ) );
        }
        $s = get_option( 'wpsp_settings', [] );
        ?>
        <div class="wrap wpsp-wrap">
            <h1 class="wpsp-page-title"><span class="dashicons dashicons-admin-settings"></span> Settings</h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'wpsp_save_settings', 'wpsp_nonce' ); ?>
                <input type="hidden" name="wpsp_action" value="save_settings">

                <!-- ── Bot Protection ─────────────────────────── -->
                <div class="wpsp-panel">
                    <h2>🤖 Bot Protection &amp; WAF</h2>
                    <table class="form-table">
                        <?php $this->toggle( 'enable_bot_protection', 'Enable Bot Protection &amp; WAF', 'Blocks known attack tools, enables the Web Application Firewall, and enforces rate limiting.', $s ); ?>
                        <?php $this->toggle( 'block_bad_useragents', 'Block Malicious User-Agents', 'Blocks requests from nikto, sqlmap, wpscan, and 40+ known attack tools.', $s ); ?>
                        <?php $this->toggle( 'enable_waf', 'Web Application Firewall (WAF)', 'Inspects every request for SQL injection, XSS, path traversal and shell exploits.', $s ); ?>
                        <?php $this->toggle( 'block_xmlrpc', 'Disable XML-RPC', 'Disables xmlrpc.php — a common brute-force and DDoS amplification target.', $s ); ?>
                        <?php $this->toggle( 'block_author_scan', 'Block Author Enumeration', 'Prevents ?author=1 scans that reveal WordPress usernames.', $s ); ?>
                        <?php $this->toggle( 'auto_block_on_attack', 'Auto-Block Attacking IPs', 'Automatically adds the attacker\'s IP to the blocklist when an attack is detected.', $s ); ?>
                        <tr>
                            <th><label for="rate_limit_per_minute">Rate Limit (requests/min per IP)</label></th>
                            <td>
                                <input type="number" id="rate_limit_per_minute" name="wpsp_settings[rate_limit_per_minute]"
                                    value="<?php echo esc_attr( $s['rate_limit_per_minute'] ?? 60 ); ?>" min="10" max="500" class="small-text">
                                <p class="description">Requests above this threshold trigger a 403. Default: 60.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ── Comment Protection ─────────────────────── -->
                <div class="wpsp-panel">
                    <h2>💬 Comment Spam Protection</h2>
                    <table class="form-table">
                        <?php $this->toggle( 'enable_comment_protection', 'Enable Comment Protection', 'Activates all comment spam defences below.', $s ); ?>
                        <tr>
                            <th><label for="comment_min_time">Minimum Submission Time (seconds)</label></th>
                            <td>
                                <input type="number" id="comment_min_time" name="wpsp_settings[comment_min_time]"
                                    value="<?php echo esc_attr( $s['comment_min_time'] ?? 5 ); ?>" min="1" max="60" class="small-text">
                                <p class="description">Comments submitted faster than this are rejected as bots. Default: 5 s.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="comment_max_links">Max Links Per Comment</label></th>
                            <td>
                                <input type="number" id="comment_max_links" name="wpsp_settings[comment_max_links]"
                                    value="<?php echo esc_attr( $s['comment_max_links'] ?? 2 ); ?>" min="0" max="20" class="small-text">
                                <p class="description">Comments with more links than this are blocked. Default: 2.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ── Login Protection ───────────────────────── -->
                <div class="wpsp-panel">
                    <h2>🔐 Login Brute-Force Protection</h2>
                    <table class="form-table">
                        <?php $this->toggle( 'enable_login_protection', 'Enable Login Protection', 'Locks out IPs that fail login too many times.', $s ); ?>
                        <?php $this->toggle( 'enable_notifications', 'Email Admin on Lockout', 'Sends an alert email when an IP is locked out.', $s ); ?>
                        <tr>
                            <th><label for="login_max_attempts">Max Failed Attempts Before Lockout</label></th>
                            <td>
                                <input type="number" id="login_max_attempts" name="wpsp_settings[login_max_attempts]"
                                    value="<?php echo esc_attr( $s['login_max_attempts'] ?? 5 ); ?>" min="1" max="20" class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="login_lockout_minutes">Lockout Duration (minutes)</label></th>
                            <td>
                                <input type="number" id="login_lockout_minutes" name="wpsp_settings[login_lockout_minutes]"
                                    value="<?php echo esc_attr( $s['login_lockout_minutes'] ?? 30 ); ?>" min="1" max="1440" class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="notify_admin_email">Alert Email Address</label></th>
                            <td>
                                <input type="email" id="notify_admin_email" name="wpsp_settings[notify_admin_email]"
                                    value="<?php echo esc_attr( $s['notify_admin_email'] ?? get_option( 'admin_email' ) ); ?>" class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ── Hardening ──────────────────────────────── -->
                <div class="wpsp-panel">
                    <h2>🛡️ WordPress Hardening</h2>
                    <table class="form-table">
                        <?php $this->toggle( 'enable_hardening', 'Enable Hardening Module', 'Activates all hardening options below.', $s ); ?>
                        <?php $this->toggle( 'remove_wp_version', 'Hide WordPress Version', 'Removes version from HTML, RSS and asset URLs.', $s ); ?>
                        <?php $this->toggle( 'disable_file_edit', 'Disable Theme/Plugin File Editing', 'Removes the editor from Appearance → Editor and Plugins → Editor.', $s ); ?>
                        <?php $this->toggle( 'disable_pingbacks', 'Disable Pingbacks &amp; Trackbacks', 'Stops your site being used as a DDoS amplifier via pingbacks.', $s ); ?>
                    </table>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary button-large">💾 Save Settings</button>
                </p>
            </form>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════════════════════════════
       IP Manager
       ══════════════════════════════════════════════════════════════════ */

    public function page_ip_manager() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'wp-shield-pro' ) );
        }
        $blocked     = get_option( 'wpsp_blocked_ips', [] );
        $whitelisted = get_option( 'wpsp_whitelisted_ips', [] );
        ?>
        <div class="wrap wpsp-wrap">
            <h1 class="wpsp-page-title"><span class="dashicons dashicons-dismiss"></span> IP Manager</h1>

            <div class="wpsp-two-col">
                <!-- Block an IP -->
                <div class="wpsp-panel">
                    <h2>Block an IP / CIDR</h2>
                    <form method="post">
                        <?php wp_nonce_field( 'wpsp_ip_action', 'wpsp_nonce' ); ?>
                        <input type="hidden" name="wpsp_action" value="block_ip">
                        <table class="form-table">
                            <tr>
                                <th><label for="new_ip">IP Address or CIDR</label></th>
                                <td><input type="text" id="new_ip" name="new_ip" placeholder="e.g. 89.124.104.131 or 10.0.0.0/8" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="new_ip_reason">Reason (optional)</label></th>
                                <td><input type="text" id="new_ip_reason" name="new_ip_reason" placeholder="e.g. Spam bot" class="regular-text"></td>
                            </tr>
                        </table>
                        <p><button type="submit" class="button button-primary">🚫 Block IP</button></p>
                    </form>
                </div>

                <!-- Whitelist an IP -->
                <div class="wpsp-panel">
                    <h2>Whitelist an IP</h2>
                    <form method="post">
                        <?php wp_nonce_field( 'wpsp_ip_action', 'wpsp_nonce' ); ?>
                        <input type="hidden" name="wpsp_action" value="whitelist_ip">
                        <table class="form-table">
                            <tr>
                                <th><label for="wl_ip">IP Address or CIDR</label></th>
                                <td><input type="text" id="wl_ip" name="wl_ip" placeholder="e.g. 192.168.1.1" class="regular-text" required></td>
                            </tr>
                        </table>
                        <p><button type="submit" class="button">✅ Whitelist IP</button></p>
                    </form>
                    <p class="description">Your current IP: <code><?php echo esc_html( WPSP_IP_Manager::instance()->get_ip() ); ?></code></p>
                </div>
            </div>

            <!-- Blocked IPs list -->
            <div class="wpsp-panel">
                <h2>Blocked IPs (<?php echo esc_html( count( $blocked ) ); ?>)</h2>
                <?php if ( empty( $blocked ) ) : ?>
                    <p class="wpsp-empty">No IPs are currently blocked.</p>
                <?php else : ?>
                <table class="wpsp-table">
                    <thead><tr><th>IP / CIDR</th><th>Reason</th><th>Date Added</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ( $blocked as $entry ) :
                            $ip     = is_array( $entry ) ? $entry['ip']     : $entry;
                            $reason = is_array( $entry ) ? ( $entry['reason'] ?? '' ) : '';
                            $added  = is_array( $entry ) ? ( $entry['added']  ?? '' ) : '';
                        ?>
                        <tr>
                            <td><code><?php echo esc_html( $ip ); ?></code></td>
                            <td><?php echo esc_html( $reason ); ?></td>
                            <td><?php echo esc_html( $added ); ?></td>
                            <td>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field( 'wpsp_ip_action', 'wpsp_nonce' ); ?>
                                    <input type="hidden" name="wpsp_action" value="unblock_ip">
                                    <input type="hidden" name="unblock_ip" value="<?php echo esc_attr( $ip ); ?>">
                                    <button type="submit" class="button button-small">Unblock</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Whitelisted IPs -->
            <div class="wpsp-panel">
                <h2>Whitelisted IPs (<?php echo esc_html( count( $whitelisted ) ); ?>)</h2>
                <?php if ( empty( $whitelisted ) ) : ?>
                    <p class="wpsp-empty">No IPs are whitelisted.</p>
                <?php else : ?>
                <table class="wpsp-table">
                    <thead><tr><th>IP / CIDR</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ( $whitelisted as $ip ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $ip ); ?></code></td>
                            <td>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field( 'wpsp_ip_action', 'wpsp_nonce' ); ?>
                                    <input type="hidden" name="wpsp_action" value="remove_whitelist">
                                    <input type="hidden" name="remove_wl_ip" value="<?php echo esc_attr( $ip ); ?>">
                                    <button type="submit" class="button button-small">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════════════════════════════
       Security Logs
       ══════════════════════════════════════════════════════════════════ */

    public function page_logs() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'wp-shield-pro' ) );
        }
        global $wpdb;
        $table   = $wpdb->prefix . 'wpsp_logs';
        $page    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $per     = 25;
        $offset  = ( $page - 1 ) * $per;
        $filter  = isset( $_GET['event_filter'] ) ? sanitize_key( $_GET['event_filter'] ) : '';

        $where  = '';
        $params = [];
        if ( $filter ) {
            $where    = 'WHERE event_type = %s';
            $params[] = $filter;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}", ...$params );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY log_time DESC LIMIT %d OFFSET %d",
                ...( $params + [ $per, $offset ] )
            )
        );
        $pages = max( 1, (int) ceil( $total / $per ) );

        // Distinct event types for filter dropdown
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $event_types = $wpdb->get_col( "SELECT DISTINCT event_type FROM {$table} ORDER BY event_type" );
        ?>
        <div class="wrap wpsp-wrap">
            <h1 class="wpsp-page-title"><span class="dashicons dashicons-list-view"></span> Security Logs</h1>

            <div class="wpsp-panel">
                <div class="wpsp-log-toolbar">
                    <form method="get" style="display:inline-flex;gap:8px;align-items:center">
                        <input type="hidden" name="page" value="wpsp-logs">
                        <select name="event_filter">
                            <option value="">All events</option>
                            <?php foreach ( $event_types as $et ) : ?>
                            <option value="<?php echo esc_attr( $et ); ?>" <?php selected( $filter, $et ); ?>>
                                <?php echo esc_html( $et ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button">Filter</button>
                        <?php if ( $filter ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpsp-logs' ) ); ?>" class="button">Clear</a>
                        <?php endif; ?>
                    </form>

                    <form method="post" style="display:inline">
                        <?php wp_nonce_field( 'wpsp_clear_logs', 'wpsp_nonce' ); ?>
                        <input type="hidden" name="wpsp_action" value="clear_logs">
                        <button type="submit" class="button" onclick="return confirm('Delete all log entries?')">🗑 Clear Logs</button>
                    </form>
                </div>

                <p><strong><?php echo esc_html( number_format( $total ) ); ?></strong> events <?php echo $filter ? "· filtered by <code>" . esc_html( $filter ) . '</code>' : ''; ?></p>

                <?php if ( empty( $rows ) ) : ?>
                    <p class="wpsp-empty">No events found.</p>
                <?php else : ?>
                <table class="wpsp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Time</th>
                            <th>IP Address</th>
                            <th>Event</th>
                            <th>Details</th>
                            <th>Request</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row->id ); ?></td>
                            <td class="wpsp-td-time"><?php echo esc_html( $row->log_time ); ?></td>
                            <td>
                                <code><?php echo esc_html( $row->ip_address ); ?></code>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpsp-ip-manager' ) ); ?>" title="Manage IP" style="text-decoration:none;font-size:10px;margin-left:4px">⊕</a>
                            </td>
                            <td><span class="wpsp-badge wpsp-badge-<?php echo esc_attr( $this->badge_class( $row->event_type ) ); ?>"><?php echo esc_html( $row->event_type ); ?></span></td>
                            <td style="max-width:200px;word-break:break-word"><?php echo esc_html( $row->details ); ?></td>
                            <td style="max-width:160px;word-break:break-all;font-size:11px"><?php echo esc_html( $row->req_uri ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ( $pages > 1 ) : ?>
                <div class="wpsp-pagination">
                    <?php for ( $p = 1; $p <= $pages; $p++ ) :
                        $url = add_query_arg( [ 'page' => 'wpsp-logs', 'paged' => $p, 'event_filter' => $filter ], admin_url( 'admin.php' ) );
                    ?>
                        <a href="<?php echo esc_url( $url ); ?>" class="button <?php echo $p === $page ? 'button-primary' : ''; ?>"><?php echo esc_html( $p ); ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════════════════════════════
       Form handlers
       ══════════════════════════════════════════════════════════════════ */

    public function handle_form_actions() {
        if ( ! current_user_can( 'manage_options' ) || empty( $_POST['wpsp_action'] ) ) {
            return;
        }

        $action = sanitize_key( $_POST['wpsp_action'] );

        switch ( $action ) {
            case 'save_settings':
                check_admin_referer( 'wpsp_save_settings', 'wpsp_nonce' );
                $this->save_settings();
                break;

            case 'block_ip':
                check_admin_referer( 'wpsp_ip_action', 'wpsp_nonce' );
                $ip     = sanitize_text_field( $_POST['new_ip'] ?? '' );
                $reason = sanitize_text_field( $_POST['new_ip_reason'] ?? 'Manual block' );
                if ( $ip ) {
                    WPSP_IP_Manager::instance()->block( $ip, $reason );
                    $this->set_notice( "IP {$ip} has been blocked.", 'success' );
                }
                break;

            case 'unblock_ip':
                check_admin_referer( 'wpsp_ip_action', 'wpsp_nonce' );
                $ip = sanitize_text_field( $_POST['unblock_ip'] ?? '' );
                if ( $ip ) {
                    WPSP_IP_Manager::instance()->unblock( $ip );
                    $this->set_notice( "IP {$ip} has been unblocked.", 'success' );
                }
                break;

            case 'whitelist_ip':
                check_admin_referer( 'wpsp_ip_action', 'wpsp_nonce' );
                $ip = sanitize_text_field( $_POST['wl_ip'] ?? '' );
                if ( $ip ) {
                    WPSP_IP_Manager::instance()->add_to_whitelist( $ip );
                    $this->set_notice( "IP {$ip} has been whitelisted.", 'success' );
                }
                break;

            case 'remove_whitelist':
                check_admin_referer( 'wpsp_ip_action', 'wpsp_nonce' );
                $ip = sanitize_text_field( $_POST['remove_wl_ip'] ?? '' );
                if ( $ip ) {
                    WPSP_IP_Manager::instance()->remove_from_whitelist( $ip );
                    $this->set_notice( "IP {$ip} removed from whitelist.", 'success' );
                }
                break;

            case 'clear_logs':
                check_admin_referer( 'wpsp_clear_logs', 'wpsp_nonce' );
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wpsp_logs" );
                $this->set_notice( 'Security logs cleared.', 'success' );
                break;
        }
    }

    private function save_settings() {
        $raw  = $_POST['wpsp_settings'] ?? [];
        $old  = get_option( 'wpsp_settings', [] );

        // Toggle fields (checkboxes – absent = 0)
        $toggles = [
            'enable_bot_protection', 'enable_comment_protection', 'enable_login_protection',
            'enable_hardening', 'block_bad_useragents', 'enable_waf', 'block_xmlrpc',
            'block_author_scan', 'auto_block_on_attack', 'enable_notifications',
            'remove_wp_version', 'disable_file_edit', 'disable_pingbacks',
        ];
        foreach ( $toggles as $key ) {
            $old[ $key ] = ! empty( $raw[ $key ] ) ? 1 : 0;
        }

        // Integer fields
        $ints = [ 'rate_limit_per_minute', 'comment_min_time', 'comment_max_links',
                  'login_max_attempts', 'login_lockout_minutes' ];
        foreach ( $ints as $key ) {
            if ( isset( $raw[ $key ] ) ) {
                $old[ $key ] = max( 0, (int) $raw[ $key ] );
            }
        }

        // Email
        if ( ! empty( $raw['notify_admin_email'] ) ) {
            $old['notify_admin_email'] = sanitize_email( $raw['notify_admin_email'] );
        }

        update_option( 'wpsp_settings', $old );
        $this->set_notice( 'Settings saved successfully.', 'success' );
    }

    /* ── Notices ─────────────────────────────────────────────────────── */

    private function set_notice( $msg, $type = 'success' ) {
        set_transient( 'wpsp_admin_notice', [ 'msg' => $msg, 'type' => $type ], 30 );
        // Redirect to avoid re-submission on reload
        $redirect = add_query_arg( 'wpsp_saved', '1', wp_get_referer() ?: admin_url( 'admin.php?page=wpsp-dashboard' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    public function show_notices() {
        $notice = get_transient( 'wpsp_admin_notice' );
        if ( $notice ) {
            delete_transient( 'wpsp_admin_notice' );
            $class = 'notice notice-' . esc_attr( $notice['type'] ) . ' is-dismissible';
            echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $notice['msg'] ) . '</p></div>';
        }
    }

    /* ── Helpers ─────────────────────────────────────────────────────── */

    private function toggle( $key, $label, $desc, $settings ) {
        $checked = ! empty( $settings[ $key ] ) ? 'checked' : '';
        echo '<tr><th><label for="' . esc_attr( $key ) . '">' . wp_kses_post( $label ) . '</label></th>'
           . '<td><label class="wpsp-switch"><input type="checkbox" id="' . esc_attr( $key ) . '" '
           . 'name="wpsp_settings[' . esc_attr( $key ) . ']" value="1" ' . $checked . '>'
           . '<span class="wpsp-slider"></span></label>'
           . '<p class="description">' . esc_html( $desc ) . '</p></td></tr>';
    }

    private function badge_class( $event_type ) {
        $map = [
            'blocked_ip'           => 'red',
            'bot_blocked'          => 'red',
            'waf_block'            => 'red',
            'comment_blocked'      => 'orange',
            'login_failed'         => 'orange',
            'login_lockout'        => 'red',
            'login_success'        => 'green',
            'xmlrpc_blocked'       => 'orange',
            'author_scan_blocked'  => 'orange',
            'rate_limit'           => 'orange',
            'ip_blocked'           => 'red',
            'ip_added_to_blocklist'=> 'red',
            'sensitive_file_access'=> 'red',
        ];
        return $map[ $event_type ] ?? 'grey';
    }
}
