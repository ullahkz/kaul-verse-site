<?php

/**
 * Plugin Name: Kaul Verse Site
 * Description: Core site plugin for kaulverse.com, managed through the WP Orchestrator.
 * Version: 1.1.0
 */

defined('ABSPATH') || exit;

add_action('admin_menu', 'kaulverse_site_add_admin_page');

function kaulverse_site_add_admin_page()
{
    add_menu_page(
        'Kaul Verse',
        'Kaul Verse',
        'manage_options',
        'kaulverse-site',
        'kaulverse_site_render_admin_page',
        'dashicons-edit'
    );
}

add_action('admin_init', 'kaulverse_site_register_settings');

function kaulverse_site_register_settings()
{
    register_setting(
        'kaulverse_site_settings',
        'kaulverse_site_slogan',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        )
    );
}

function kaulverse_site_render_admin_page()
{
    if (! current_user_can('manage_options')) {
        return;
    }
?>
    <div class="wrap">
        <h1>Kaul Verse</h1>
        <?php settings_errors(); ?>
        <form action="options.php" method="post">
            <?php settings_fields('kaulverse_site_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="kaulverse-site-slogan">Website slogan</label></th>
                    <td>
                        <input type="text" id="kaulverse-site-slogan" name="kaulverse_site_slogan" value="<?php echo esc_attr(get_option('kaulverse_site_slogan', '')); ?>" class="regular-text" aria-describedby="kaulverse-site-slogan-description">
                        <p class="description" id="kaulverse-site-slogan-description">Use <code>[kaulverse_slogan]</code> in a Shortcode block to display this text.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Slogan'); ?>
        </form>
    </div>
<?php
}

add_shortcode('kaulverse_slogan', 'kaulverse_site_render_slogan');

function kaulverse_site_render_slogan()
{
    return esc_html(get_option('kaulverse_site_slogan', ''));
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'kaulverse_site_action_links');

function kaulverse_site_action_links($links)
{
    array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=kaulverse-site')) . '">Settings</a>');

    return $links;
}
