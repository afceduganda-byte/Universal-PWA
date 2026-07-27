<?php
/**
 * Plugin Name: Universal-PWA Translator
 * Plugin URI: https://github.com/afceduganda-byte/Universal-PWA
 * Description: Drop-in, multi-language translation widget backed by a free Supabase project. Configure once in Settings, no theme edits required.
 * Version: 1.0.0
 * Author: Universal-PWA
 * License: GPL-2.0-or-later
 * Text Domain: universal-pwa-translator
 */

if (!defined('ABSPATH')) {
    exit; // No direct access.
}

define('UPWA_TRANSLATOR_OPTION', 'upwa_translator_settings');
define('UPWA_TRANSLATOR_VERSION', '1.0.0');

/**
 * Default widget script location. Point this at your own fork/CDN copy of
 * widget/translator.js + translator.css if you don't want to depend on
 * jsDelivr's GitHub mirror.
 */
function upwa_translator_default_base_url() {
    return 'https://cdn.jsdelivr.net/gh/afceduganda-byte/Universal-PWA@main/widget';
}

function upwa_translator_get_settings() {
    $defaults = array(
        'supabase_url' => '',
        'supabase_anon_key' => '',
        'project_slug' => '',
        'position' => 'bottom-right',
        'auto_scan' => true,
        'widget_base_url' => upwa_translator_default_base_url(),
    );
    $saved = get_option(UPWA_TRANSLATOR_OPTION, array());
    return wp_parse_args($saved, $defaults);
}

/**
 * Settings page under Settings -> Universal-PWA Translator.
 */
function upwa_translator_register_settings() {
    register_setting('upwa_translator', UPWA_TRANSLATOR_OPTION);

    add_settings_section(
        'upwa_translator_main',
        'Supabase connection',
        function () {
            echo '<p>Values come from your Supabase project (Settings &rarr; API) and the admin dashboard\'s Embed page.</p>';
        },
        'upwa_translator'
    );

    $fields = array(
        'supabase_url' => 'Supabase URL',
        'supabase_anon_key' => 'Supabase anon key',
        'project_slug' => 'Project slug',
        'widget_base_url' => 'Widget script base URL',
    );

    foreach ($fields as $key => $label) {
        add_settings_field(
            $key,
            $label,
            function () use ($key) {
                $settings = upwa_translator_get_settings();
                printf(
                    '<input type="text" style="width:420px" name="%s[%s]" value="%s" />',
                    esc_attr(UPWA_TRANSLATOR_OPTION),
                    esc_attr($key),
                    esc_attr($settings[$key])
                );
            },
            'upwa_translator',
            'upwa_translator_main'
        );
    }

    add_settings_field(
        'position',
        'Switcher position',
        function () {
            $settings = upwa_translator_get_settings();
            $options = array('bottom-right', 'bottom-left', 'top-right', 'top-left');
            echo '<select name="' . esc_attr(UPWA_TRANSLATOR_OPTION) . '[position]">';
            foreach ($options as $opt) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($opt),
                    selected($settings['position'], $opt, false),
                    esc_html($opt)
                );
            }
            echo '</select>';
        },
        'upwa_translator',
        'upwa_translator_main'
    );

    add_settings_field(
        'auto_scan',
        'Auto-translate visible text (no template edits needed)',
        function () {
            $settings = upwa_translator_get_settings();
            printf(
                '<input type="checkbox" name="%s[auto_scan]" value="1" %s />',
                esc_attr(UPWA_TRANSLATOR_OPTION),
                checked(!empty($settings['auto_scan']), true, false)
            );
        },
        'upwa_translator',
        'upwa_translator_main'
    );
}
add_action('admin_init', 'upwa_translator_register_settings');

function upwa_translator_settings_page() {
    ?>
    <div class="wrap">
        <h1>Universal-PWA Translator</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('upwa_translator');
            do_settings_sections('upwa_translator');
            submit_button();
            ?>
        </form>
        <p>
            Manage languages and translation strings in the
            <strong>Universal-PWA admin dashboard</strong> (deployed separately, see
            <code>docs/SETUP.md</code> in the plugin's GitHub repo) — this settings page only wires
            your site up to it.
        </p>
    </div>
    <?php
}

function upwa_translator_add_menu() {
    add_options_page(
        'Universal-PWA Translator',
        'Translator',
        'manage_options',
        'upwa_translator',
        'upwa_translator_settings_page'
    );
}
add_action('admin_menu', 'upwa_translator_add_menu');

/**
 * Front-end: enqueue the shared widget script/stylesheet with this site's
 * configuration, on every public page.
 */
function upwa_translator_enqueue_widget() {
    if (is_admin()) {
        return;
    }

    $settings = upwa_translator_get_settings();
    if (empty($settings['supabase_url']) || empty($settings['supabase_anon_key']) || empty($settings['project_slug'])) {
        return; // Not configured yet.
    }

    $base = untrailingslashit($settings['widget_base_url']);

    wp_enqueue_style('upwa-translator', $base . '/translator.css', array(), UPWA_TRANSLATOR_VERSION);
    wp_enqueue_script('upwa-translator', $base . '/translator.js', array(), UPWA_TRANSLATOR_VERSION, true);

    wp_script_add_data('upwa-translator', 'data-supabase-url', esc_url($settings['supabase_url']));

    // wp_script_add_data only covers a handful of known attributes, so hook
    // into the enqueued <script> tag directly to add the rest.
    add_filter('script_loader_tag', 'upwa_translator_add_script_attributes', 10, 3);
}
add_action('wp_enqueue_scripts', 'upwa_translator_enqueue_widget');

function upwa_translator_add_script_attributes($tag, $handle, $src) {
    if ($handle !== 'upwa-translator') {
        return $tag;
    }
    $settings = upwa_translator_get_settings();
    $functions_url = untrailingslashit($settings['supabase_url']) . '/functions/v1';

    $attrs = sprintf(
        ' data-supabase-url="%s" data-supabase-anon-key="%s" data-project-slug="%s" data-position="%s" data-auto-scan="%s" data-log-endpoint="%s"',
        esc_url($settings['supabase_url']),
        esc_attr($settings['supabase_anon_key']),
        esc_attr($settings['project_slug']),
        esc_attr($settings['position']),
        !empty($settings['auto_scan']) ? 'true' : 'false',
        esc_url($functions_url . '/log-language-stat')
    );

    return str_replace(' src=', $attrs . ' src=', $tag);
}
