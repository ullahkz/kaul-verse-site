<?php
defined('ABSPATH') || exit;

function kaulverse_cards_migration_save($id, $original, $updated, $backup_key)
{
    if ($original === $updated) { return; }
    if (!metadata_exists('post', $id, $backup_key) && !add_post_meta($id, $backup_key, $original, true)) {
        throw new RuntimeException('Could not back up card ' . $id);
    }
    if (!update_post_meta($id, '_kaulverse_card', $updated)) {
        throw new RuntimeException('Could not migrate card ' . $id);
    }
}

function kaulverse_cards_run_migrations()
{
    $migrations = array(1 => '001-page-paths.php', 2 => '002-featured-image.php');
    $version = (int) get_option('kaulverse_cards_schema_version', 0);
    foreach ($migrations as $number => $file) {
        if ($number <= $version) { continue; }
        $migrate = require __DIR__ . '/../migrations/' . $file;
        $offset = 0;
        do {
            $ids = get_posts(array('post_type' => 'kaulverse_card', 'post_status' => array_keys(get_post_stati()), 'posts_per_page' => 100, 'offset' => $offset, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids'));
            foreach ($ids as $id) {
                $migrate($id);
            }
            $offset += 100;
        } while (count($ids) === 100);
        if (!update_option('kaulverse_cards_schema_version', $number, false)) {
            throw new RuntimeException('Could not record migration ' . $number);
        }
        WP_CLI::log('Applied ' . $file);
    }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('kaulverse-cards migrate', function () {
        try {
            kaulverse_cards_run_migrations();
            WP_CLI::success('Card migrations are up to date.');
        } catch (Throwable $error) {
            WP_CLI::error($error->getMessage());
        }
    });
}
