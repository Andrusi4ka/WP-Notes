<?php

if (! defined('ABSPATH')) {
	exit;
}

class WP_Notes_Plugin {
	private static $instance = null;
	private $repository;
	private $i18n;
	private $permissions;
	private $admin;
	private $renderer;

	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate() {
		$repository = new WP_Notes_Repository();
		$repository->create_table();

		self::ensure_storage();
	}

	private function __construct() {
		$this->repository  = new WP_Notes_Repository();
		$this->i18n        = new WP_Notes_I18n();
		$this->permissions = new WP_Notes_Permissions();
		$this->admin       = new WP_Notes_Admin($this->repository, $this->i18n, $this->permissions);
		$this->renderer    = new WP_Notes_Renderer($this->repository, $this->i18n, $this->permissions);

		add_action('init', array($this, 'bootstrap'));
	}

	public function bootstrap() {
		self::ensure_storage();
		$this->repository->migrate_schema();

		if (is_admin()) {
			$this->admin->register();
			$this->renderer->register();
		}
	}

	public static function storage_path() {
		return trailingslashit(WP_NOTES_PATH . 'storage/uploads');
	}

	public static function storage_url() {
		return trailingslashit(WP_NOTES_URL . 'storage/uploads');
	}

	public static function ensure_storage() {
		$base_path = WP_NOTES_PATH . 'storage';
		$path      = self::storage_path();

		if (! file_exists($base_path)) {
			wp_mkdir_p($base_path);
		}

		if (! file_exists($path)) {
			wp_mkdir_p($path);
		}

		$index_file = trailingslashit($base_path) . 'index.php';
		if (! file_exists($index_file)) {
			self::write_index_file($index_file);
		}

		$uploads_index = trailingslashit($path) . 'index.php';
		if (! file_exists($uploads_index)) {
			self::write_index_file($uploads_index);
		}
	}

	private static function write_index_file($path) {
		global $wp_filesystem;

		if (! function_exists('WP_Filesystem')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if (! WP_Filesystem()) {
			return false;
		}

		return (bool) $wp_filesystem->put_contents($path, "<?php\n", FS_CHMOD_FILE);
	}
}
