<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'wp_notes';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

$storage_path = trailingslashit(plugin_dir_path(__FILE__)) . 'storage';

if (is_dir($storage_path)) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($storage_path, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($iterator as $item) {
		if ($item->isDir()) {
			rmdir($item->getPathname());
		} else {
			unlink($item->getPathname());
		}
	}

	rmdir($storage_path);
}
