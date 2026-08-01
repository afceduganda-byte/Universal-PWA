<?php
/**
 * Comment Spam Protection
 * – Honeypot field (bots fill it, humans don't)
 * – Submission time check (< 5 s = bot)
 * – Excessive-link check
 * – Suspicious email/URL pattern detection (catches e.g. 888tarz_amon@…)
 * – IP-based rate limiting
 * – Keyword/regex spam filter
 */
defined( 'ABSPATH' ) || exit;

class WPSP_Comment_Protection {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        // Add honeypot + timing fields to the form.
        add_action( 'comment_form_after_fields',   [ $this, 'inject_honeypot' ] );
        add_action( 'comment_form_logged_in_after', [ $this, 'inject_honeypot' ] );

        // Validate before WP processes the comment.
        add_filter( 'preprocess_comment', [ $this, 'validate_comment' ] );

        // Auto-mark as spam if it slips through to pre_comment_approved.
        add_filter( 'pre_comment_approved', [ $this, 'auto_spam_filter' ], 10, 2 );
    }

    /* ── Honeypot + timing injection ─────────────────────────────────── */

    public function inject_honeypot() {
        $token = wp_create_nonce( 'wpsp_comment' );
        $time  = time();
        echo '<p style="display:none!important;visibility:hidden;position:absolute;left:-9999px;" aria-hidden="true">'
           . '<label for="wpsp_hp">Leave this field empty</label>'
           . '<input type="text" name="wpsp_hp" id="wpsp_hp" value="" tabindex="-1" autocomplete="off">'
           . '</p>'
           . '<input type="hidden" name="wpsp_ts" value="' . esc_attr( $time ) . '">'
           . '<input type="hidden" name="wpsp_nonce" value="' . esc_attr( $token ) . '">';
    }

    /* ── Main validation filter ──────────────────────────────────────── */

    public function validate_comment( $comment_data ) {
        // Skip checks for logged-in editors / admins.
        if ( current_user_can( 'moderate_comments' ) ) {
            return $comment_data;
        }

        $ip  = WPSP_IP_Manager::instance()->get_ip();
        $s   = get_option( 'wpsp_settings', [] );

        // 1. Honeypot filled → bot.
        if ( isset( $_POST['wpsp_hp'] ) && '' !== $_POST['wpsp_hp'] ) {
            $this->block_comment( $ip, 'Honeypot triggered' );
        }

        // 2. Timing check.
        $min_time = max( 1, (int) ( $s['comment_min_time'] ?? 5 ) );
        if ( isset( $_POST['wpsp_ts'] ) ) {
            $elapsed = time() - (int) $_POST['wpsp_ts'];
            if ( $elapsed < $min_time ) {
                $this->block_comment( $ip, "Comment submitted too fast ({$elapsed}s)" );
            }
        }

        // 3. Rate limit per IP.
        $this->check_comment_rate( $ip );

        // 4. Suspicious email.
        $email = $comment_data['comment_author_email'] ?? '';
        if ( $email && $this->is_suspicious_email( $email ) ) {
            $this->block_comment( $ip, "Suspicious email: {$email}" );
        }

        // 5. Suspicious author URL.
        $url = $comment_data['comment_author_url'] ?? '';
        if ( $url && $this->is_suspicious_url( $url ) ) {
            $this->block_comment( $ip, "Suspicious URL: {$url}" );
        }

        // 6. Too many links in comment body.
        $body      = $comment_data['comment_content'] ?? '';
        $max_links = max( 0, (int) ( $s['comment_max_links'] ?? 2 ) );
        $link_cnt  = substr_count( strtolower( $body ), 'http' );
        if ( $link_cnt > $max_links ) {
            $this->block_comment( $ip, "Too many links ({$link_cnt})" );
        }

        // 7. Spam keyword patterns.
        if ( $this->matches_spam_pattern( $body ) ) {
            $this->block_comment( $ip, 'Spam keyword pattern matched' );
        }

        return $comment_data;
    }

    /* ── Auto-mark suspicious comments as spam ───────────────────────── */

    public function auto_spam_filter( $approved, $comment_data ) {
        if ( 'spam' === $approved ) {
            return $approved;
        }
        $body  = $comment_data['comment_content'] ?? '';
        $email = $comment_data['comment_author_email'] ?? '';
        $url   = $comment_data['comment_author_url'] ?? '';

        if ( $this->is_suspicious_email( $email )
            || $this->is_suspicious_url( $url )
            || $this->matches_spam_pattern( $body )
        ) {
            return 'spam';
        }
        return $approved;
    }

    /* ── Detection helpers ───────────────────────────────────────────── */

    /**
     * Detect bot-generated emails like 888tarz_amon@random.site
     */
    private function is_suspicious_email( $email ) {
        $local = strtolower( substr( $email, 0, strpos( $email, '@' ) ) );
        $domain = strtolower( substr( $email, strpos( $email, '@' ) + 1 ) );

        // Starts with numbers
        if ( preg_match( '/^\d+/', $local ) ) {
            return true;
        }
        // Random-looking local: long alphanumeric + special chars
        if ( preg_match( '/^[a-z0-9]{6,}[_\-\.][a-z0-9]{4,}$/i', $local ) && strlen( $local ) > 12 ) {
            return true;
        }
        // Disposable / throwaway domains
        $throwaway = [
            'mailinator.com', 'guerrillamail.com', 'trashmail.com', 'yopmail.com',
            'sharklasers.com', 'guerrillamailblock.com', 'grr.la', 'guerrillamail.info',
            'spam4.me', 'tempr.email', 'dispostable.com', 'maildrop.cc',
            'mailnull.com', 'spamgourmet.com', 'discard.email', 'fakeinbox.com',
            'tempmail.com', 'throwam.com', 'spambox.us', 'getairmail.com',
            'mailnesia.com', 'throwaway.email', 'nospam.ze.tc',
        ];
        foreach ( $throwaway as $d ) {
            if ( $domain === $d || str_ends_with( $domain, '.' . $d ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flag URLs with randomised subdomains, known spam TLDs, or suspicious paths.
     */
    private function is_suspicious_url( $url ) {
        $host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
        if ( ! $host ) {
            return false;
        }

        // Subdomain looks randomly generated (10+ random-looking chars)
        $parts = explode( '.', $host );
        if ( count( $parts ) >= 3 ) {
            $sub = $parts[0];
            if ( preg_match( '/^[a-z0-9]{10,}$/i', $sub ) ) {
                return true;
            }
        }

        // URL contains a path that looks like a spam keyword
        $path = strtolower( (string) parse_url( $url, PHP_URL_PATH ) );
        $spam_keywords = [
            'casino', 'poker', 'viagra', 'cialis', 'porn', 'sex', 'xxx',
            'loan', 'payday', 'crypto', 'bitcoin', 'forex', 'gambling',
            'cheap-', 'buy-', 'discount', 'free-', 'win-', 'prize',
        ];
        foreach ( $spam_keywords as $kw ) {
            if ( strpos( $path, $kw ) !== false || strpos( $host, $kw ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keyword / regex patterns commonly found in spam.
     */
    private function matches_spam_pattern( $text ) {
        $patterns = [
            '/\b(viagra|cialis|levitra|tramadol|valium)\b/i',
            '/\b(casino|poker|gambling|roulette|blackjack|slot\s*machine)\b/i',
            '/\b(payday\s*loan|unsecured\s*loan|cheap\s*loan)\b/i',
            '/\b(buy\s+followers|buy\s+likes|cheap\s+seo|link\s+building)\b/i',
            '/\b(weight\s*loss|diet\s*pill|fat\s*burn|lose\s+\d+\s*lbs)\b/i',
            '/\[url=[^\]]+\]/i',                 // BBCode URLs
            '/(http[s]?:\/\/){3,}/i',            // Three or more URLs
            '/(.)\1{10,}/',                      // Character repetition (aaaaaaaaaa)
            '/\$\d+[\.,]\d+/i',                  // Money amounts used in spam
        ];
        foreach ( $patterns as $p ) {
            if ( preg_match( $p, $text ) ) {
                return true;
            }
        }
        return false;
    }

    /* ── Rate limit ──────────────────────────────────────────────────── */

    private function check_comment_rate( $ip ) {
        $key   = 'wpsp_cr_' . md5( $ip );
        $count = (int) get_transient( $key );
        if ( $count >= 5 ) {
            $this->block_comment( $ip, 'Comment rate limit exceeded' );
        }
        set_transient( $key, $count + 1, 300 ); // 5 comments per 5 min
    }

    /* ── Block helper ────────────────────────────────────────────────── */

    private function block_comment( $ip, $reason ) {
        WPSP_IP_Manager::instance()->log( $ip, 'comment_blocked', $reason );

        $s = get_option( 'wpsp_settings', [] );
        if ( ! empty( $s['auto_block_on_attack'] ) ) {
            WPSP_IP_Manager::instance()->block( $ip, "Comment spam: {$reason}" );
        }

        wp_die(
            '<h1 style="font-family:sans-serif">Comment Blocked</h1>'
            . '<p style="font-family:sans-serif">Your comment was rejected by the spam filter. '
            . 'Please go back and try again.</p>',
            'Comment Blocked',
            [ 'response' => 403, 'back_link' => true ]
        );
    }
}
