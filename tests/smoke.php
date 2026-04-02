<?php

$root = dirname(__DIR__);

$required_files = array(
	$root . '/wp-notes.php',
	$root . '/uninstall.php',
	$root . '/includes/class-wp-notes-plugin.php',
	$root . '/includes/class-wp-notes-repository.php',
	$root . '/includes/class-wp-notes-admin.php',
);

foreach ($required_files as $file) {
	if (! file_exists($file)) {
		fwrite(STDERR, "Missing required file: {$file}\n");
		exit(1);
	}
}

$plugin_bootstrap = file_get_contents($root . '/wp-notes.php');
if (false === $plugin_bootstrap || false === strpos($plugin_bootstrap, "define('WP_NOTES_VERSION'")) {
	fwrite(STDERR, "Plugin bootstrap is missing WP_NOTES_VERSION.\n");
	exit(1);
}

$repository_source = file_get_contents($root . '/includes/class-wp-notes-repository.php');
if (false === $repository_source || false === strpos($repository_source, 'UNIQUE KEY note_key')) {
	fwrite(STDERR, "Repository schema is missing the note_key unique constraint.\n");
	exit(1);
}

$plugin_source = file_get_contents($root . '/includes/class-wp-notes-plugin.php');
if (false === $plugin_source || false === strpos($plugin_source, 'WP_Filesystem')) {
	fwrite(STDERR, "Storage bootstrap is not using WP_Filesystem.\n");
	exit(1);
}

fwrite(STDOUT, "Smoke checks passed.\n");
