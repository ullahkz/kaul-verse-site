<?php
/** Reusable cards and their groups. */
defined('ABSPATH') || exit;

/** Resolve portable page paths; keep old ID-based cards readable until saved/migrated. */
function kaulverse_card_destination($meta, $index)
{
    $key = 'page_path_' . $index;
    if (array_key_exists($key, $meta)) {
        return $meta[$key] !== '' ? get_page_by_path($meta[$key], OBJECT, 'page') : null;
    }
    $page = !empty($meta['page_' . $index]) ? get_post($meta['page_' . $index]) : null;
    return $page && $page->post_type === 'page' ? $page : null;
}

/** Use the featured image, falling back only for cards not yet migrated. */
function kaulverse_card_image_id($post_id, $meta)
{
    if (metadata_exists('post', $post_id, '_thumbnail_id')) {
        return absint(get_post_thumbnail_id($post_id));
    }
    return !empty($meta['thumbnail']) ? absint($meta['thumbnail']) : 0;
}

add_action('init', function () {
    register_post_type('kaulverse_card', array(
        'labels' => array('name' => 'Cards', 'singular_name' => 'Card', 'add_new_item' => 'Add New Card', 'edit_item' => 'Edit Card'),
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-index-card',
        'supports' => array('title', 'editor', 'thumbnail', 'page-attributes', 'revisions'),
    ));
    register_taxonomy('kaulverse_card_group', 'kaulverse_card', array(
        'labels' => array('name' => 'Card Groups', 'singular_name' => 'Card Group', 'add_new_item' => 'Add New Card Group', 'add_new' => 'Add New Card Group', 'parent_item' => 'Parent Card Group', 'all_items' => 'All Card Groups'),
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'hierarchical' => true,
    ));
});

// Cards use a straightforward form instead of the theme-styled block editor.
add_filter('use_block_editor_for_post_type', function ($use_block_editor, $post_type) {
    return $post_type === 'kaulverse_card' ? false : $use_block_editor;
}, 10, 2);

add_filter('enter_title_here', function ($placeholder, $post) {
    return $post->post_type === 'kaulverse_card' ? 'Card title — for example, Product One' : $placeholder;
}, 10, 2);

add_action('edit_form_after_title', function ($post) {
    if ($post->post_type === 'kaulverse_card') {
        echo '<p><strong>Supporting text</strong> — write the text to display on this card in the editor below.</p>';
    }
});

function kaulverse_card_usage($post)
{
    echo '<ol><li>Enter the <strong>card title</strong> at the top and supporting text below it.</li><li>Select or add a group in <strong>Card Groups</strong>.</li><li>Choose an optional <strong>Main card image</strong> in Card Details and fill in Card Details.</li><li>Click <strong>Publish</strong> or <strong>Update</strong> to save.</li></ol>';
    echo '<p>To display cards, edit a normal page, add a <strong>Shortcode</strong> block, and paste one of the codes below.</p>';
    $groups = wp_get_post_terms($post->ID, 'kaulverse_card_group');
    if (!is_wp_error($groups) && $groups) {
        foreach ($groups as $group) {
            echo '<p><label>' . esc_html($group->name) . '<input type="text" class="widefat" readonly onclick="this.select()" value="' . esc_attr('[kaulverse_cards group="' . $group->slug . '" columns="3"]') . '"></label></p>';
        }
    } else {
        echo '<p><strong>Select a group and save this card</strong> to see its group shortcode here.</p>';
    }
    echo '<p><label>All published cards<input type="text" class="widefat" readonly onclick="this.select()" value="' . esc_attr('[kaulverse_cards]') . '"></label></p>';
    echo '<p>Click a shortcode to select it, then copy it. Group shortcodes show all published cards assigned to that group.</p>';
}

// Enable card images even when the active theme does not declare thumbnail support.
add_action('after_setup_theme', function () {
    if (!current_theme_supports('post-thumbnails')) {
        add_theme_support('post-thumbnails', array('kaulverse_card'));
    } else {
        $support = get_theme_support('post-thumbnails');
        if (isset($support[0]) && is_array($support[0])) {
            add_theme_support('post-thumbnails', array_unique(array_merge($support[0], array('kaulverse_card'))));
        }
    }
}, 100);

add_action('add_meta_boxes_kaulverse_card', function () {
    remove_meta_box('postimagediv', 'kaulverse_card', 'side');
    add_meta_box('kaulverse-card-usage', 'How to use this card', 'kaulverse_card_usage', 'kaulverse_card', 'side', 'high');
    add_meta_box('kaulverse-card-details', 'Card Details', 'kaulverse_card_fields', 'kaulverse_card', 'normal');
});

function kaulverse_card_fields($post)
{
    wp_nonce_field('kaulverse_card_save', 'kaulverse_card_nonce');
    $meta = get_post_meta($post->ID, '_kaulverse_card', true);
    $meta = is_array($meta) ? $meta : array();
    echo '<p>Use the title and editor above for your heading and supporting text. Choose the main card image below. Assign Card Groups in the sidebar and use Order to arrange cards (lowest first).</p>';
    $main_image = kaulverse_card_image_id($post->ID, $meta);
    echo '<h3>Main card image</h3><p>This image is separate from the supporting text and fills the card width.</p><div id="kv-main-image-preview">';
    if ($main_image) {
        echo wp_get_attachment_image($main_image, 'medium', false, array('style' => 'max-width:100%;height:auto;'));
    }
    echo '</div><input type="hidden" id="kv-main-image" name="kv_card[main_image]" value="' . esc_attr($main_image) . '">';
    echo '<p><button type="button" class="button" id="kv-select-main-image">Choose main image</button> <button type="button" class="button" id="kv-remove-main-image">Remove main image</button></p>';
    echo '<p><label for="kv-image-position"><strong>Main image position</strong></label><br><select id="kv-image-position" name="kv_card[image_position]">';
    foreach (array('top' => 'Top — above all text', 'bottom' => 'Bottom — below text and buttons') as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected($meta['image_position'] ?? 'top', $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p><p class="description">Choose the main card image above. It fills the card width in a separate section.</p>';
    echo '<p><label for="kv-subheading">Subheading</label><br><input class="widefat" id="kv-subheading" name="kv_card[subheading]" value="' . esc_attr($meta['subheading'] ?? '') . '"></p>';
    echo '<p><label><input type="checkbox" name="kv_card[hide_title]" value="1" ' . checked(!empty($meta['hide_title']), true, false) . '> Hide title on the card</label></p>';
    for ($i = 1; $i <= 2; $i++) {
        echo '<p><label for="kv-label-' . $i . '">Button ' . $i . ' label</label><br><input class="widefat" id="kv-label-' . $i . '" name="kv_card[label_' . $i . ']" value="' . esc_attr($meta['label_' . $i] ?? '') . '"></p>';
        echo '<p><label for="kv-page-' . $i . '">Button ' . $i . ' destination page</label><br>';
        $destination = kaulverse_card_destination($meta, $i);
        wp_dropdown_pages(array('name' => 'kv_card[page_' . $i . ']', 'id' => 'kv-page-' . $i, 'selected' => $destination ? $destination->ID : 0, 'show_option_none' => 'Select a page', 'option_none_value' => '0'));
        echo '</p>';
    }
    echo '<p><label><input type="checkbox" name="kv_card[share]" value="1" ' . checked(!empty($meta['share']), true, false) . '> Show share button (shares the first selected published destination page)</label></p>';
}

add_action('save_post_kaulverse_card', function ($post_id) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    if (!isset($_POST['kaulverse_card_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kaulverse_card_nonce'])), 'kaulverse_card_save')) {
        return;
    }
    $input = isset($_POST['kv_card']) && is_array($_POST['kv_card']) ? wp_unslash($_POST['kv_card']) : array();
    // Retain the native featured-image storage so existing images remain selected.
    if (isset($input['main_image']) && is_scalar($input['main_image'])) {
        $image_id = absint($input['main_image']);
        if (!$image_id) {
            delete_post_thumbnail($post_id);
        } elseif (wp_attachment_is_image($image_id)) {
            set_post_thumbnail($post_id, $image_id);
        }
    }
    if (!isset($input['main_image'])) {
        $legacy = get_post_meta($post_id, '_kaulverse_card', true);
        if (is_array($legacy) && !metadata_exists('post', $post_id, '_thumbnail_id') && !empty($legacy['thumbnail']) && wp_attachment_is_image($legacy['thumbnail'])) {
            set_post_thumbnail($post_id, $legacy['thumbnail']);
        }
    }
    $data = array();
    foreach (array('subheading', 'label_1', 'label_2') as $key) {
        $data[$key] = isset($input[$key]) && is_string($input[$key]) ? sanitize_text_field($input[$key]) : '';
    }
    for ($i = 1; $i <= 2; $i++) {
        $value = $input['page_' . $i] ?? 0;
        $page = is_scalar($value) && absint($value) ? get_post(absint($value)) : null;
        $data['page_path_' . $i] = $page && $page->post_type === 'page' ? get_page_uri($page) : '';
    }
    $data['image_position'] = ($input['image_position'] ?? 'top') === 'bottom' ? 'bottom' : 'top';
    $data['hide_title'] = !empty($input['hide_title']);
    $data['share'] = !empty($input['share']);
    update_post_meta($post_id, '_kaulverse_card', $data);
});

add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if ($screen && $screen->base === 'post' && $screen->post_type === 'kaulverse_card') {
        wp_enqueue_media();
        wp_enqueue_script('kaulverse-card-admin', plugins_url('../assets/cards-admin.js', __FILE__), array('media-editor'), '1.2.0', true);
    }
});

add_filter('manage_edit-kaulverse_card_group_columns', function ($columns) {
    $columns['kaulverse_shortcode'] = 'Shortcode';
    return $columns;
});
add_filter('manage_kaulverse_card_group_custom_column', function ($content, $column, $term_id) {
    if ($column === 'kaulverse_shortcode') {
        $term = get_term($term_id, 'kaulverse_card_group');
        return '<code>' . esc_html('[kaulverse_cards group="' . $term->slug . '"]') . '</code>';
    }
    return $content;
}, 10, 3);

// A small stylesheet also covers shortcodes rendered by templates and widgets.
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('kaulverse-cards', plugins_url('../assets/cards.css', __FILE__), array(), '1.2.0');
});

add_shortcode('kaulverse_cards', 'kaulverse_render_cards');
function kaulverse_render_cards($attributes)
{
    $attributes = shortcode_atts(array('group' => '', 'columns' => 3, 'limit' => 12), $attributes, 'kaulverse_cards');
    $columns = max(1, min(4, absint($attributes['columns'])));
    $args = array('post_type' => 'kaulverse_card', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => max(1, min(100, absint($attributes['limit']))), 'orderby' => array('menu_order' => 'ASC', 'title' => 'ASC', 'ID' => 'ASC'), 'no_found_rows' => true);
    if ($attributes['group'] !== '') {
        $args['tax_query'] = array(array('taxonomy' => 'kaulverse_card_group', 'field' => 'slug', 'terms' => sanitize_title($attributes['group']), 'include_children' => true));
    }
    $cards = new WP_Query($args);
    if (!$cards->have_posts()) {
        return '';
    }
    wp_enqueue_script('kaulverse-cards', plugins_url('../assets/cards.js', __FILE__), array(), '1.2.0', true);
    ob_start();
    echo '<div class="kv-cards kv-cards--' . $columns . '">';
    foreach ($cards->posts as $card) {
        $meta = get_post_meta($card->ID, '_kaulverse_card', true);
        $meta = is_array($meta) ? $meta : array();
        $title = get_the_title($card);
        $buttons = array();
        $share_url = '';
        for ($i = 1; $i <= 2; $i++) {
            $page = kaulverse_card_destination($meta, $i);
            if ($page && $page->post_type === 'page' && $page->post_status === 'publish' && !$page->post_password) {
                $url = get_permalink($page);
                $share_url = $share_url ?: $url;
                if (!empty($meta['label_' . $i])) {
                    $buttons[] = '<a class="kv-card__button" href="' . esc_url($url) . '">' . esc_html($meta['label_' . $i]) . '</a>';
                }
            }
        }
        $image = get_the_post_thumbnail($card, 'large', array('class' => 'kv-card__media'));
        if (!metadata_exists('post', $card->ID, '_thumbnail_id')) {
            $legacy_image = kaulverse_card_image_id($card->ID, $meta);
            if ($legacy_image) {
                $image = wp_get_attachment_image($legacy_image, 'large', false, array('class' => 'kv-card__media'));
            }
        }
        $image_position = ($meta['image_position'] ?? 'top') === 'bottom' ? 'bottom' : 'top';
        echo '<article class="kv-card">';
        if ($image_position === 'top') {
            echo $image;
        }
        echo '<div class="kv-card__body">';
        if ((empty($meta['hide_title']) && $title !== '') || !empty($meta['subheading'])) {
            echo '<header class="kv-card__header"><div>';
            if (empty($meta['hide_title']) && $title !== '') {
                echo '<h3 class="kv-card__title">' . esc_html($title) . '</h3>';
            }
            if (!empty($meta['subheading'])) {
                echo '<p class="kv-card__subheading">' . esc_html($meta['subheading']) . '</p>';
            }
            echo '</div></header>';
        }
        if (trim($card->post_content) !== '') {
            // Render editor blocks and basic formatting without evaluating nested shortcodes.
            echo '<div class="kv-card__text">' . wp_kses_post(wpautop(do_blocks($card->post_content))) . '</div>';
        }
        if ($buttons || (!empty($meta['share']) && $share_url)) {
            echo '<footer class="kv-card__actions">' . implode('', $buttons);
            if (!empty($meta['share']) && $share_url) {
                echo '<button type="button" class="kv-card__share" data-url="' . esc_url($share_url) . '" data-title="' . esc_attr($title) . '" aria-label="' . esc_attr('Share ' . $title) . '"><svg aria-hidden="true" viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M18 16a3 3 0 0 0-2.4 1.2l-6.7-3.9a3.4 3.4 0 0 0 0-2.6l6.7-3.9A3 3 0 1 0 15 5c0 .2 0 .4.1.6L8.4 9.5a3 3 0 1 0 0 5l6.7 3.9c-.1.2-.1.4-.1.6a3 3 0 1 0 3-3Z"/></svg></button><span class="kv-card__status" role="status"></span>';
            }
            echo '</footer>';
        }
        echo '</div>';
        if ($image_position === 'bottom') {
            echo $image;
        }
        echo '</article>';
    }
    echo '</div>';
    return ob_get_clean();
}
