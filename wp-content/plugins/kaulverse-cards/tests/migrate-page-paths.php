<?php
/** Run before exporting cards: wp eval-file wp-content/plugins/kaulverse-cards/tests/migrate-page-paths.php */
if (!defined('WP_CLI') || !WP_CLI) { exit; }
$count = 0;
$offset = 0;
do {
    $ids = get_posts(array('post_type' => 'kaulverse_card', 'post_status' => array_keys(get_post_stati()), 'posts_per_page' => 100, 'offset' => $offset, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids'));
    foreach ($ids as $id) {
        $meta = get_post_meta($id, '_kaulverse_card', true);
        if (!is_array($meta)) { continue; }
        $updated = $meta;
        for ($i = 1; $i <= 2; $i++) {
            if (array_key_exists('page_path_' . $i, $meta)) { continue; }
            $page = kaulverse_card_destination($meta, $i);
            if (!$page && !empty($meta['page_' . $i])) {
                WP_CLI::warning('Card ' . $id . ': missing destination for button ' . $i . '; legacy value retained.');
                continue;
            }
            $updated['page_path_' . $i] = $page ? get_page_uri($page) : '';
            unset($updated['page_' . $i]);
        }
        if ($updated !== $meta) {
            // Keep the original metadata for a manual rollback without using it for rendering.
            if (!metadata_exists('post', $id, '_kaulverse_card_before_page_paths')) {
                add_post_meta($id, '_kaulverse_card_before_page_paths', $meta, true);
            }
            if (!update_post_meta($id, '_kaulverse_card', $updated)) {
                WP_CLI::error('Could not migrate card ' . $id);
            }
            $count++;
        }
    }
    $offset += 100;
} while (count($ids) === 100);
WP_CLI::success('Converted ' . $count . ' cards to page paths.');
