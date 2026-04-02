<?php

if (! defined('ABSPATH')) {
	exit;
}

class WP_Notes_Repository {
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'wp_notes';
	}

	public function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$this->table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scope varchar(20) NOT NULL,
			note_key varchar(191) NOT NULL,
			note_theme varchar(32) NOT NULL DEFAULT 'default',
			screen_id varchar(191) DEFAULT '',
			page_url text NOT NULL,
			page_title varchar(255) DEFAULT '',
			content longtext NOT NULL,
			author_id bigint(20) unsigned NOT NULL,
			edit_mode varchar(20) NOT NULL DEFAULT 'author',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY note_key (note_key),
			KEY scope (scope),
			KEY screen_id (screen_id)
		) {$charset_collate};";

		dbDelta($sql);
		$this->migrate_schema();
	}

	public function migrate_schema() {
		$this->ensure_note_keys();
		$this->ensure_note_theme_column();
		$this->migrate_legacy_columns();
	}

	public function migrate_legacy_columns() {
		global $wpdb;

		$columns = $wpdb->get_col("SHOW COLUMNS FROM {$this->table_name}", 0);
		if (empty($columns)) {
			return;
		}

		$drop_columns = array();
		foreach (array('view_mode', 'view_users', 'note_title', 'edit_users') as $column) {
			if (in_array($column, $columns, true)) {
				$drop_columns[] = "DROP COLUMN {$column}";
			}
		}

		if (empty($drop_columns)) {
			return;
		}

		$wpdb->query("ALTER TABLE {$this->table_name} " . implode(', ', $drop_columns));
	}

	public function get_all() {
		global $wpdb;
		$query = "SELECT * FROM {$this->table_name} ORDER BY updated_at DESC";
		return array_map(array($this, 'normalize_note'), $wpdb->get_results($query, ARRAY_A));
	}

	public function get($id) {
		global $wpdb;
		$note = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id), ARRAY_A);
		return $note ? $this->normalize_note($note) : null;
	}

	public function get_global() {
		global $wpdb;
		$note = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE note_key = %s LIMIT 1", $this->build_note_key('global')), ARRAY_A);
		return $note ? $this->normalize_note($note) : null;
	}

	public function get_for_screen($screen_id, $page_url = '') {
		global $wpdb;
		$note_key = $this->build_note_key('screen', $screen_id, $page_url);

		if ('' !== $note_key) {
			$note = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE note_key = %s LIMIT 1", $note_key), ARRAY_A);
			if ($note) {
				return $this->normalize_note($note);
			}
		}

		if ('' !== $page_url) {
			$note = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->table_name}
					WHERE scope = %s
					  AND (screen_id = %s OR page_url = %s)
					ORDER BY CASE WHEN screen_id = %s THEN 0 ELSE 1 END, id DESC
					LIMIT 1",
					'screen',
					$screen_id,
					$page_url,
					$screen_id
				),
				ARRAY_A
			);
		} else {
			$note = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE scope = %s AND screen_id = %s ORDER BY id DESC LIMIT 1", 'screen', $screen_id), ARRAY_A);
		}

		return $note ? $this->normalize_note($note) : null;
	}

	public function exists($scope, $screen_id = '', $page_url = '') {
		global $wpdb;
		$note_key = $this->build_note_key($scope, $screen_id, $page_url);

		if ('' !== $note_key) {
			$count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE note_key = %s", $note_key));
			return $count > 0;
		}

		if ('global' === $scope) {
			$count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE scope = %s", 'global'));
			return $count > 0;
		}

		if ('' !== $page_url) {
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table_name}
					WHERE scope = %s
					  AND (screen_id = %s OR page_url = %s)",
					'screen',
					$screen_id,
					$page_url
				)
			);
		} else {
			$count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE scope = %s AND screen_id = %s", 'screen', $screen_id));
		}

		return $count > 0;
	}

	public function save($data) {
		global $wpdb;

		$defaults = array(
			'id'         => 0,
			'scope'      => 'screen',
			'screen_id'  => '',
			'page_url'   => '',
			'page_title' => '',
			'content'    => '',
			'author_id'  => get_current_user_id(),
			'edit_mode'  => 'author',
			'note_theme' => 'default',
		);

		$data = wp_parse_args($data, $defaults);
		$now  = current_time('mysql');

		$row = array(
			'scope'      => $data['scope'],
			'note_key'   => $this->build_note_key($data['scope'], $data['screen_id'], $data['page_url']),
			'note_theme' => $data['note_theme'],
			'screen_id'  => $data['screen_id'],
			'page_url'   => $data['page_url'],
			'page_title' => $data['page_title'],
			'content'    => $data['content'],
			'author_id'  => (int) $data['author_id'],
			'edit_mode'  => $data['edit_mode'],
			'updated_at' => $now,
		);

		$formats = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s');

		if (! empty($data['id'])) {
			$wpdb->update($this->table_name, $row, array('id' => (int) $data['id']), $formats, array('%d'));
			return (int) $data['id'];
		}

		$row['created_at'] = $now;
		$formats[]         = '%s';
		$wpdb->insert($this->table_name, $row, $formats);
		return (int) $wpdb->insert_id;
	}

	public function delete($id) {
		global $wpdb;
		return (bool) $wpdb->delete($this->table_name, array('id' => (int) $id), array('%d'));
	}

	public function has_content_reference_excluding_note($needle, $excluded_note_id) {
		global $wpdb;

		if ('' === $needle) {
			return false;
		}

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE id != %d AND content LIKE %s",
				(int) $excluded_note_id,
				'%' . $wpdb->esc_like($needle) . '%'
			)
		);

		return $count > 0;
	}

	private function normalize_note($note) {
		$note['id']         = (int) $note['id'];
		$note['author_id']  = (int) $note['author_id'];
		$note['note_theme'] = ! empty($note['note_theme']) ? (string) $note['note_theme'] : 'default';
		return $note;
	}

	private function ensure_note_theme_column() {
		global $wpdb;

		$columns = $wpdb->get_col("SHOW COLUMNS FROM {$this->table_name}", 0);
		if (empty($columns)) {
			return;
		}

		if (! in_array('note_theme', $columns, true)) {
			$wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN note_theme varchar(32) NOT NULL DEFAULT 'default' AFTER note_key");
		}
	}

	private function ensure_note_keys() {
		global $wpdb;

		$columns = $wpdb->get_col("SHOW COLUMNS FROM {$this->table_name}", 0);
		if (empty($columns)) {
			return;
		}

		if (! in_array('note_key', $columns, true)) {
			$wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN note_key varchar(191) NOT NULL DEFAULT '' AFTER scope");
		}

		$notes = $wpdb->get_results("SELECT id, scope, screen_id, page_url, updated_at FROM {$this->table_name} ORDER BY updated_at DESC, id DESC", ARRAY_A);
		if (empty($notes)) {
			$this->ensure_note_key_index();
			return;
		}

		$seen_keys = array();
		foreach ($notes as $note) {
			$note_key = $this->build_note_key($note['scope'], $note['screen_id'], $note['page_url']);

			if ('' === $note_key) {
				continue;
			}

			if (isset($seen_keys[$note_key])) {
				$wpdb->delete($this->table_name, array('id' => (int) $note['id']), array('%d'));
				continue;
			}

			$seen_keys[$note_key] = true;
			$wpdb->update(
				$this->table_name,
				array('note_key' => $note_key),
				array('id' => (int) $note['id']),
				array('%s'),
				array('%d')
			);
		}

		$this->ensure_note_key_index();
	}

	private function ensure_note_key_index() {
		global $wpdb;

		$indexes = $wpdb->get_results("SHOW INDEX FROM {$this->table_name} WHERE Key_name = 'note_key'", ARRAY_A);
		if (empty($indexes)) {
			$wpdb->query("ALTER TABLE {$this->table_name} ADD UNIQUE KEY note_key (note_key)");
			return;
		}

		foreach ($indexes as $index) {
			if (! empty($index['Non_unique'])) {
				$wpdb->query("ALTER TABLE {$this->table_name} DROP INDEX note_key, ADD UNIQUE KEY note_key (note_key)");
				return;
			}
		}
	}

	private function build_note_key($scope, $screen_id = '', $page_url = '') {
		$scope = (string) $scope;

		if ('global' === $scope) {
			return 'global';
		}

		$screen_id = (string) $screen_id;
		if ('' !== $screen_id) {
			return 'screen:' . md5($screen_id);
		}

		$page_url = (string) $page_url;
		if ('' !== $page_url) {
			return 'screen-url:' . md5($page_url);
		}

		return '';
	}
}
