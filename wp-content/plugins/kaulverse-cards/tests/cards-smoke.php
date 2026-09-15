<?php
/** Run with: ddev wp eval-file wp-content/plugins/kaulverse-cards/tests/cards-smoke.php */
if (!defined('WP_CLI') || !WP_CLI) { exit; }
$ids = array();
$terms = array();
$check = function ($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
};
try {
    $check(post_type_exists('kaulverse_card'), 'Card type registered');
    $suffix = wp_generate_uuid4();
    foreach (array('first', 'second') as $name) {
        $term = wp_insert_term('Smoke ' . $name . ' ' . $suffix, 'kaulverse_card_group');
        $check(!is_wp_error($term), 'Create group');
        $terms[] = $term['term_id'];
    }
    $page = wp_insert_post(array('post_type' => 'page', 'post_title' => 'Smoke destination', 'post_status' => 'publish'));
    $ids[] = $page;
    foreach (array('Alpha', 'Beta', 'Draft', 'Other', 'Protected') as $index => $title) {
        $id = wp_insert_post(array('post_type' => 'kaulverse_card', 'post_title' => $title, 'post_content' => 'Supporting text', 'post_status' => $title === 'Draft' ? 'draft' : 'publish', 'post_password' => $title === 'Protected' ? 'secret' : '', 'menu_order' => $title === 'Beta' ? -1 : 0));
        $ids[] = $id;
        wp_set_object_terms($id, array($terms[$title === 'Other' ? 1 : 0]), 'kaulverse_card_group');
    }
    update_post_meta($ids[1], '_kaulverse_card', array('subheading' => '<script>alert(1)</script>', 'label_1' => 'Visit', 'page_1' => $page, 'share' => true));
    $admins = get_users(array('role' => 'administrator', 'number' => 1));
    $check(!empty($admins), 'Administrator available for save checks');
    $previous_user = get_current_user_id();
    wp_set_current_user($admins[0]->ID);
    $_POST['kv_card'] = array('subheading' => '<b>Clean text</b>', 'page_1' => $page, 'label_1' => 'Visit', 'share' => '1');
    do_action('save_post_kaulverse_card', $ids[1]);
    $check(get_post_meta($ids[1], '_kaulverse_card', true)['subheading'] === '<script>alert(1)</script>', 'Reject save without nonce');
    $_POST['kaulverse_card_nonce'] = wp_create_nonce('kaulverse_card_save');
    do_action('save_post_kaulverse_card', $ids[1]);
    $check(get_post_meta($ids[1], '_kaulverse_card', true)['subheading'] === 'Clean text', 'Sanitize saved text');
    unset($_POST['kv_card'], $_POST['kaulverse_card_nonce']);
    wp_set_current_user($previous_user);
    $slug = get_term($terms[0])->slug;
    $html = do_shortcode('[kaulverse_cards group="' . $slug . '"]');
    $check(substr_count($html, '<article ') === 2, 'Exclude drafts, protected cards, other groups');
    $check(strpos($html, 'Beta') < strpos($html, 'Alpha'), 'Honor card order');
    $check(strpos($html, '<script>') === false, 'Escape metadata');
    $check(strpos($html, '>Visit</a>') !== false && strpos($html, 'data-url=') !== false, 'Render destination and share');
    $check(substr_count(do_shortcode('[kaulverse_cards group="' . $slug . '" limit="1"]'), '<article ') === 1, 'Honor limit');
    $check(do_shortcode('[kaulverse_cards group="missing-' . $suffix . '"]') === '', 'Unknown group empty');
    wp_update_post(array('ID' => $page, 'post_status' => 'draft'));
    $html = do_shortcode('[kaulverse_cards group="' . $slug . '"]');
    $check(strpos($html, '>Visit</a>') === false && strpos($html, 'data-url=') === false, 'Hide unpublished destinations');
    $fake_image = function () { return '<img class="kv-card__media" alt="Test image">'; };
    add_filter('post_thumbnail_html', $fake_image);
    $html = do_shortcode('[kaulverse_cards group="' . $slug . '" limit="1"]');
    $check(strpos($html, 'kv-card__media') < strpos($html, 'kv-card__body'), 'Default image above text');
    update_post_meta($ids[2], '_kaulverse_card', array('image_position' => 'bottom'));
    $html = do_shortcode('[kaulverse_cards group="' . $slug . '" limit="1"]');
    $check(strpos($html, 'kv-card__media') > strpos($html, 'Supporting text'), 'Bottom image below text');
    $check(substr_count($html, 'kv-card__media') === 1, 'Image rendered once');
    remove_filter('post_thumbnail_html', $fake_image);
    WP_CLI::success('Card registration, grouping, ordering, visibility, escaping, buttons, sharing, and limits passed.');
} finally {
    foreach ($ids as $id) { wp_delete_post($id, true); }
    foreach ($terms as $id) { wp_delete_term($id, 'kaulverse_card_group'); }
}
