<?php
defined('ABSPATH') || exit;

return function ($id) {
    $meta = get_post_meta($id, '_kaulverse_card', true);
    if (!is_array($meta) || !array_key_exists('thumbnail', $meta)) { return; }
    if (!metadata_exists('post', $id, '_thumbnail_id') && !empty($meta['thumbnail'])) {
        if (wp_attachment_is_image($meta['thumbnail'])) {
            set_post_thumbnail($id, $meta['thumbnail']);
            if (get_post_thumbnail_id($id) !== absint($meta['thumbnail'])) {
                throw new RuntimeException('Could not set featured image for card ' . $id);
            }
        } else {
            WP_CLI::warning('Card ' . $id . ': legacy image is missing; original value retained in backup metadata.');
        }
    }
    $updated = $meta;
    unset($updated['thumbnail']);
    kaulverse_cards_migration_save($id, $meta, $updated, '_kaulverse_card_before_featured_image');
};
