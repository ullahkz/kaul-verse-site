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
    $saved = get_post_meta($ids[1], '_kaulverse_card', true);
    $check($saved['page_path_1'] === get_page_uri($page) && !isset($saved['page_1']), 'Save path instead of ID');
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
    $old_slug = get_post_field('post_name', $page);
    wp_update_post(array('ID' => $page, 'post_name' => $old_slug . '-old'));
    $replacement = wp_insert_post(array('post_type' => 'page', 'post_title' => 'Replacement', 'post_name' => $old_slug, 'post_status' => 'publish'));
    $ids[] = $replacement;
    $check(kaulverse_card_destination($saved, 1)->ID === $replacement, 'Resolve same slug with a different page ID');
    wp_update_post(array('ID' => $replacement, 'post_status' => 'draft'));
    wp_update_post(array('ID' => $page, 'post_status' => 'draft'));
    $html = do_shortcode('[kaulverse_cards group="' . $slug . '"]');
    $check(strpos($html, '>Visit</a>') === false && strpos($html, 'data-url=') === false, 'Hide unpublished destinations');
    $missing = array('page_path_1' => 'missing-' . $suffix, 'label_1' => 'Missing link', 'share' => true);
    update_post_meta($ids[2], '_kaulverse_card', $missing);
    $check(kaulverse_card_destination($missing, 1) === null, 'Missing destination resolves to null');
    $html = do_shortcode('[kaulverse_cards group="' . $slug . '" limit="1"]');
    $check(strpos($html, 'Supporting text') !== false && substr_count($html, '<article ') === 1, 'Card remains visible without destination');
    $check(strpos($html, 'href=') === false && strpos($html, 'data-url=') === false, 'Missing destination emits no link or share URL');
    $attachment = wp_insert_attachment(array('post_title' => 'Smoke image', 'post_mime_type' => 'image/png', 'guid' => home_url('/smoke-image.png')));
    $ids[] = $attachment;
    update_post_meta($attachment, '_wp_attached_file', 'smoke-image.png');
    $legacy = array('thumbnail' => $attachment, 'page_1' => $page);
    update_post_meta($ids[2], '_kaulverse_card', $legacy);
    $check(kaulverse_card_image_id($ids[2], $legacy) === $attachment, 'Legacy image fallback');
    $html = do_shortcode('[kaulverse_cards group="' . $slug . '" limit="1"]');
    $check(substr_count($html, 'kv-card__media') === 1 && strpos($html, 'kv-card__thumbnail') === false, 'Legacy image renders once as main image');
    $migrate_pages = require __DIR__ . '/../migrations/001-page-paths.php';
    $migrate_image = require __DIR__ . '/../migrations/002-featured-image.php';
    $migrate_pages($ids[2]);
    $migrate_image($ids[2]);
    $migrated = get_post_meta($ids[2], '_kaulverse_card', true);
    $check(!array_key_exists('thumbnail', $migrated) && get_post_thumbnail_id($ids[2]) === $attachment, 'Migration moves legacy image to featured image');
    $check(isset($migrated['page_path_1']) && !isset($migrated['page_1']), 'Page migration stores path');
    $migrate_pages($ids[2]);
    $migrate_image($ids[2]);
    $check(get_post_meta($ids[2], '_kaulverse_card', true) === $migrated, 'Migrations are idempotent');
    update_post_meta($ids[2], '_kaulverse_card', array('thumbnail' => 999999999));
    $migrate_image($ids[2]);
    $check(get_post_thumbnail_id($ids[2]) === $attachment, 'Migration preserves existing featured image');
    delete_post_thumbnail($ids[2]);
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
