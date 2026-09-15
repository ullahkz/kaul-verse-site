<?php
defined('ABSPATH') || exit;

return function ($id) {
    $meta = get_post_meta($id, '_kaulverse_card', true);
    if (!is_array($meta)) { return; }
    $updated = $meta;
    for ($i = 1; $i <= 2; $i++) {
        if (!array_key_exists('page_path_' . $i, $meta)) {
            $page = kaulverse_card_destination($meta, $i);
            if (!$page && !empty($meta['page_' . $i])) {
                kaulverse_cards_migration_notice('Card ' . $id . ': missing destination for button ' . $i . '; legacy value retained.', 'warning');
                continue;
            }
            $updated['page_path_' . $i] = $page ? get_page_uri($page) : '';
        }
        unset($updated['page_' . $i]);
    }
    kaulverse_cards_migration_save($id, $meta, $updated, '_kaulverse_card_before_page_paths');
};
