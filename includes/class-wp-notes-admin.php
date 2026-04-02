<?php

if (! defined('ABSPATH')) {
	exit;
}

class WP_Notes_Admin {
	private $repository;
	private $i18n;
	private $permissions;

	const DEFAULT_NOTE_THEME = 'default';

	public function __construct($repository, $i18n, $permissions) {
		$this->repository  = $repository;
		$this->i18n        = $i18n;
		$this->permissions = $permissions;
	}

	public function register() {
		add_action('admin_menu', array($this, 'register_menu'));
		add_action('admin_bar_menu', array($this, 'register_admin_bar'), 100);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('admin_footer', array($this, 'render_modal_root'));
		add_action('admin_post_wp_notes_save_note', array($this, 'handle_save_note'));
		add_action('admin_post_wp_notes_delete_note', array($this, 'handle_delete_note'));
		add_action('wp_ajax_wp_notes_get_note_form', array($this, 'handle_get_note_form'));
		add_action('wp_ajax_wp_notes_save_note', array($this, 'handle_save_note_ajax'));
		add_action('wp_ajax_wp_notes_delete_note', array($this, 'handle_delete_note_ajax'));
		add_action('wp_ajax_wp_notes_upload_image', array($this, 'handle_upload_image'));
	}

	public static function allowed_html() {
		$allowed = wp_kses_allowed_html('post');
		$allowed['a'] = array('href' => true, 'rel' => true, 'rev' => true, 'name' => true, 'target' => true, 'download' => true, 'class' => true);
		$allowed['p'] = array('class' => true, 'style' => true);
		$allowed['h1'] = array('class' => true, 'style' => true);
		$allowed['h2'] = array('class' => true, 'style' => true);
		$allowed['h3'] = array('class' => true, 'style' => true);
		$allowed['blockquote'] = array('class' => true, 'style' => true);
		$allowed['pre'] = array('class' => true);
		$allowed['code'] = array('class' => true);
		$allowed['span'] = array('class' => true, 'style' => true);
		$allowed['ol'] = array('class' => true, 'style' => true);
		$allowed['ul'] = array('class' => true, 'style' => true);
		$allowed['li'] = array('class' => true, 'style' => true);
		$allowed['img'] = array('src' => true, 'alt' => true, 'class' => true, 'width' => true, 'height' => true, 'style' => true);
		return $allowed;
	}

	public static function sanitize_note_content($content) {
		add_filter('safe_style_css', array(__CLASS__, 'allow_note_css_properties'));
		$sanitized = wp_kses($content, self::allowed_html());
		remove_filter('safe_style_css', array(__CLASS__, 'allow_note_css_properties'));

		return $sanitized;
	}

	public static function allow_note_css_properties($properties) {
		$required = array(
			'color',
			'background',
			'background-color',
			'text-align',
		);

		foreach ($required as $property) {
			if (! in_array($property, $properties, true)) {
				$properties[] = $property;
			}
		}

		return $properties;
	}

	public static function normalize_note_theme($theme) {
		$allowed_themes = array(
			'default',
			'sky',
			'mint',
			'peach',
			'rose',
			'lavender',
			'lemon',
			'ice',
			'sage',
			'sand',
		);

		$theme = sanitize_key((string) $theme);

		return in_array($theme, $allowed_themes, true) ? $theme : self::DEFAULT_NOTE_THEME;
	}

	public static function get_note_theme_class($note) {
		$theme = '';

		if (is_array($note) && isset($note['note_theme'])) {
			$theme = $note['note_theme'];
		} elseif (is_string($note)) {
			$theme = $note;
		}

		return 'wp-notes-theme--' . self::normalize_note_theme($theme);
	}

	private function can_create_screen_note_for_current_screen() {
		if (! function_exists('get_current_screen')) {
			return false;
		}

		$screen = get_current_screen();
		if (! $screen) {
			return false;
		}

		return 'post' === $screen->base && 'page' === $screen->post_type;
	}

	private function can_create_screen_note_for_request($screen_id, $page_url = '') {
		$screen_id = (string) $screen_id;
		$page_url  = (string) $page_url;

		if ($this->can_create_screen_note_for_current_screen()) {
			$current_screen_id = WP_Notes_Context::current_screen_id();
			$current_page_url  = WP_Notes_Context::current_admin_url();

			if ($screen_id === $current_screen_id || ('' !== $page_url && $page_url === $current_page_url)) {
				return true;
			}
		}

		if ('' !== $screen_id && 0 === strpos($screen_id, 'page')) {
			return true;
		}

		if ('' !== $page_url) {
			$query = wp_parse_url($page_url, PHP_URL_QUERY);
			if (is_string($query)) {
				parse_str($query, $params);
				$post_id = 0;

				foreach (array('post', 'post_ID') as $key) {
					if (! empty($params[$key])) {
						$post_id = absint($params[$key]);
						if ($post_id > 0) {
							break;
						}
					}
				}

				if ($post_id > 0) {
					return 'page' === get_post_type($post_id);
				}
			}
		}

		return false;
	}

	public function register_menu() {
		$capability = 'edit_pages';
		add_menu_page($this->i18n->t('menu_root'), $this->i18n->t('menu_root'), $capability, 'wp-notes', array($this, 'render_all_notes_page'), 'dashicons-welcome-write-blog', 58);
		add_submenu_page('wp-notes', $this->i18n->t('page_title_all'), $this->i18n->t('menu_all_notes'), $capability, 'wp-notes', array($this, 'render_all_notes_page'));
		add_submenu_page('wp-notes', $this->i18n->t('page_title_settings'), $this->i18n->t('menu_settings'), 'manage_options', 'wp-notes-settings', array($this, 'render_settings_page'));
		add_submenu_page(null, $this->i18n->t('page_title_add'), $this->i18n->t('page_title_add'), $capability, 'wp-notes-add', array($this, 'render_note_form_page'));
		add_submenu_page(null, $this->i18n->t('page_title_edit'), $this->i18n->t('page_title_edit'), $capability, 'wp-notes-edit', array($this, 'render_note_form_page'));
	}

	public function enqueue_assets($hook) {
		wp_enqueue_style('quill-snow', WP_NOTES_URL . 'assets/vendor/quill/quill.snow.css', array(), '1.3.7');
		wp_enqueue_style('highlightjs-theme', WP_NOTES_URL . 'assets/vendor/highlightjs/github.min.css', array(), '11.11.1');
		wp_enqueue_style('wp-notes-admin', WP_NOTES_URL . 'assets/css/admin.css', array(), WP_NOTES_VERSION);
		wp_enqueue_script('quill', WP_NOTES_URL . 'assets/vendor/quill/quill.min.js', array(), '1.3.7', true);
		wp_enqueue_script('highlightjs', WP_NOTES_URL . 'assets/vendor/highlightjs/highlight.min.js', array(), '11.11.1', true);
		wp_enqueue_script('quill-image-resize', WP_NOTES_URL . 'assets/vendor/quill-image-resize/image-resize.min.js', array('quill'), '3.0.0', true);
		wp_enqueue_script('wp-notes-admin-core', WP_NOTES_URL . 'assets/js/admin-core.js', array('jquery'), WP_NOTES_VERSION, true);
		wp_enqueue_script('wp-notes-admin-code', WP_NOTES_URL . 'assets/js/admin-code.js', array('wp-notes-admin-core', 'highlightjs'), WP_NOTES_VERSION, true);
		wp_enqueue_script('wp-notes-admin-editor', WP_NOTES_URL . 'assets/js/admin-editor.js', array('wp-notes-admin-core', 'wp-notes-admin-code', 'quill-image-resize'), WP_NOTES_VERSION, true);
		wp_enqueue_script('wp-notes-admin-modal', WP_NOTES_URL . 'assets/js/admin-modal.js', array('wp-notes-admin-editor'), WP_NOTES_VERSION, true);
		wp_enqueue_script('wp-notes-admin-notes', WP_NOTES_URL . 'assets/js/admin-notes.js', array('wp-notes-admin-modal'), WP_NOTES_VERSION, true);
		wp_enqueue_script('wp-notes-admin', WP_NOTES_URL . 'assets/js/admin.js', array('wp-notes-admin-notes'), WP_NOTES_VERSION, true);
		wp_localize_script('wp-notes-admin', 'wpNotesAdmin', array(
			'ajaxUrl'         => admin_url('admin-ajax.php'),
			'formNonce'       => wp_create_nonce('wp_notes_get_note_form'),
			'saveNonce'       => wp_create_nonce('wp_notes_save_note'),
			'deleteNonce'     => wp_create_nonce('wp_notes_delete_note'),
			'uploadNonce'     => wp_create_nonce('wp_notes_upload_image'),
			'uploadingText'   => $this->i18n->t('uploading'),
			'uploadError'     => $this->i18n->t('upload_error'),
			'uploadLabel'     => $this->i18n->t('upload_image'),
			'modalTitleAdd'   => $this->i18n->t('page_title_add'),
			'modalTitleEdit'  => $this->i18n->t('page_title_edit'),
			'loadingText'     => $this->i18n->t('loading'),
			'savingText'      => $this->i18n->t('saving'),
			'genericError'    => $this->i18n->t('generic_error'),
			'deleteError'     => $this->i18n->t('delete_error'),
			'deleteConfirm'   => $this->i18n->t('delete_confirm'),
			'emptyNotes'      => $this->i18n->t('no_notes'),
			'copyCode'        => $this->i18n->t('copy_code'),
			'copiedText'      => $this->i18n->t('copied'),
			'copyIconUrl'     => WP_NOTES_URL . 'assets/img/copy.svg',
			'closeIconUrl'    => WP_NOTES_URL . 'assets/img/close.svg',
			'currentScreenId' => WP_Notes_Context::current_screen_id(),
		));
	}

	public function register_admin_bar($admin_bar) {
		if (! is_admin() || ! is_admin_bar_showing() || ! $this->permissions->can_create()) {
			return;
		}

		$screen = get_current_screen();
		if (! $screen) {
			return;
		}

		$current_url   = WP_Notes_Context::current_admin_url();
		$page_title    = WP_Notes_Context::current_page_title();
		$screen_key    = WP_Notes_Context::current_screen_id();
		$page_exists   = $this->repository->exists('screen', $screen_key, $current_url);
		$global_exists = $this->repository->exists('global');
		$page_allowed  = $this->can_create_screen_note_for_current_screen();

		$admin_bar->add_node(array('id' => 'wp-notes-root', 'title' => $this->i18n->t('admin_bar_root')));

		$page_href = add_query_arg(array(
			'page'       => 'wp-notes-add',
			'scope'      => 'screen',
			'screen_id'  => rawurlencode($screen_key),
			'return_url' => rawurlencode($current_url),
			'page_title' => rawurlencode($page_title),
		), admin_url('admin.php'));

		$page_args = array(
			'id'     => 'wp-notes-page',
			'parent' => 'wp-notes-root',
			'title'  => $page_allowed ? ($page_exists ? $this->i18n->t('note_exists') : $this->i18n->t('admin_bar_page')) : $this->i18n->t('admin_bar_page_unavailable'),
			'meta'   => array(
				'class'      => (! $page_allowed || $page_exists) ? 'wp-notes-admin-bar__item is-disabled' : 'wp-notes-admin-bar__item',
				'data-modal' => (! $page_allowed || $page_exists) ? '0' : '1',
			),
		);
		if ($page_allowed && ! $page_exists) {
			$page_args['href'] = $page_href;
		}
		$admin_bar->add_node($page_args);

		$global_href = add_query_arg(array(
			'page'       => 'wp-notes-add',
			'scope'      => 'global',
			'return_url' => rawurlencode($current_url),
			'page_title' => rawurlencode(get_bloginfo('name')),
		), admin_url('admin.php'));

		$global_args = array(
			'id'     => 'wp-notes-global',
			'parent' => 'wp-notes-root',
			'title'  => $global_exists ? $this->i18n->t('note_exists') : $this->i18n->t('admin_bar_global'),
			'meta'   => array(
				'class'      => $global_exists ? 'wp-notes-admin-bar__item is-disabled' : 'wp-notes-admin-bar__item',
				'data-modal' => $global_exists ? '0' : '1',
			),
		);
		if (! $global_exists) {
			$global_args['href'] = $global_href;
		}
		$admin_bar->add_node($global_args);
	}

	public function render_all_notes_page() {
		if (! $this->permissions->can_manage_plugin()) {
			wp_die(esc_html($this->i18n->t('visibility_denied')));
		}

		$notes = array_filter($this->repository->get_all(), array($this, 'filter_visible_note'));
		echo '<div class="wrap wp-notes-admin-page">';
		echo '<h1>' . esc_html($this->i18n->t('page_title_all')) . '</h1>';
		$this->render_notice();
		if (empty($notes)) {
			echo '<p class="wp-notes-empty" data-wp-notes-empty="1">' . esc_html($this->i18n->t('no_notes')) . '</p>';
		} else {
			echo '<table class="widefat striped wp-notes-table" data-wp-notes-table="1"><thead><tr>';
			echo '<th>' . esc_html($this->i18n->t('column_side')) . '</th>';
			echo '<th>' . esc_html($this->i18n->t('column_scope')) . '</th>';
			echo '<th>' . esc_html($this->i18n->t('column_author')) . '</th>';
			echo '<th>' . esc_html($this->i18n->t('column_updated')) . '</th>';
			echo '<th>' . esc_html($this->i18n->t('column_edit')) . '</th>';
			echo '<th>' . esc_html($this->i18n->t('column_actions')) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ($notes as $note) {
				echo $this->get_note_table_row_html($note);
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}

	public function render_settings_page() {
		if (! current_user_can('manage_options')) {
			wp_die(esc_html($this->i18n->t('visibility_denied')));
		}

		if (! function_exists('get_plugin_data')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data = get_plugin_data(WP_NOTES_FILE, false, false);
		echo '<div class="wrap wp-notes-admin-page">';
		echo '<h1>' . esc_html($this->i18n->t('page_title_settings')) . '</h1>';
		$this->render_notice();
		echo '<section class="wp-notes-settings-readme" aria-labelledby="wp-notes-readme-title">';
		echo '<h2 id="wp-notes-readme-title" class="wp-notes-settings-readme__title">' . esc_html($this->i18n->t('settings_about_title')) . '</h2>';
		echo '<p class="wp-notes-settings-readme__intro">' . esc_html($this->i18n->t('settings_about_intro')) . '</p>';
		echo '<h3 class="wp-notes-settings-readme__subtitle">' . esc_html($this->i18n->t('settings_about_features_title')) . '</h3>';
		echo '<ul class="wp-notes-settings-readme__list">';
		echo '<li>' . esc_html($this->i18n->t('settings_about_feature_1')) . '</li>';
		echo '<li>' . esc_html($this->i18n->t('settings_about_feature_2')) . '</li>';
		echo '<li>' . esc_html($this->i18n->t('settings_about_feature_3')) . '</li>';
		echo '<li>' . esc_html($this->i18n->t('settings_about_feature_4')) . '</li>';
		echo '</ul>';
		echo '</section>';
		echo '<div class="wp-notes-settings-footer"><div class="wp-notes-settings-footer__version">' . esc_html($this->i18n->t('version') . ': ' . $plugin_data['Version']) . '</div><div class="wp-notes-settings-footer__promo"><img class="wp-notes-settings-footer__logo" src="' . esc_url(WP_NOTES_URL . 'assets/img/Relevant.svg') . '" alt="Relevant"></div></div>';
		echo '</div>';
	}

	public function render_note_form_page() {
		try {
			$note_data = $this->get_note_form_state($_GET);
		} catch (Exception $exception) {
			wp_die(esc_html($exception->getMessage()));
		}

		echo '<div class="wrap wp-notes-admin-page wp-notes-editor-page"><h1>' . esc_html($this->get_note_form_title($note_data)) . '</h1>';
		echo $this->get_note_form_html($note_data, false);
		echo '</div>';
	}

	public function handle_get_note_form() {
		if (! $this->permissions->can_create()) {
			wp_send_json_error(array('message' => $this->i18n->t('create_denied')), 403);
		}

		check_ajax_referer('wp_notes_get_note_form', 'nonce');

		try {
			$note_data = $this->get_note_form_state($_GET);
		} catch (Exception $exception) {
			wp_send_json_error(array('message' => $exception->getMessage()), 400);
		}

		wp_send_json_success(array(
			'html'       => $this->get_note_form_html($note_data, true),
			'noteId'     => (int) $note_data['id'],
			'isEdit'     => ! empty($note_data['id']),
			'scope'      => $note_data['scope'],
			'screenId'   => $note_data['screen_id'],
			'modalTitle' => $this->get_note_form_title($note_data),
		));
	}

	public function handle_save_note() {
		if (! $this->permissions->can_create()) {
			wp_die(esc_html($this->i18n->t('create_denied')));
		}

		check_admin_referer('wp_notes_save_note');
		$result = $this->save_note_from_request($_POST);
		if (is_wp_error($result)) {
			wp_die(esc_html($result->get_error_message()));
		}

		wp_safe_redirect(add_query_arg(array('page' => 'wp-notes', 'notice' => 'note-saved'), admin_url('admin.php')));
		exit;
	}

	public function handle_save_note_ajax() {
		if (! $this->permissions->can_create()) {
			wp_send_json_error(array('message' => $this->i18n->t('create_denied')), 403);
		}

		check_ajax_referer('wp_notes_save_note', 'nonce');
		$result = $this->save_note_from_request($_POST);
		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()), 400);
		}

		$note = $this->repository->get($result);
		if (! $note) {
			wp_send_json_error(array('message' => $this->i18n->t('generic_error')), 500);
		}

		$response = array(
			'message'   => $this->i18n->t('note_saved'),
			'noteId'    => (int) $note['id'],
			'scope'     => $note['scope'],
			'screenId'  => $note['screen_id'],
			'rowHtml'   => $this->permissions->can_view($note) ? $this->get_note_table_row_html($note) : '',
			'cardHtml'  => $this->permissions->can_view($note) ? $this->get_note_card_html($note) : '',
			'isVisible' => $this->permissions->can_view($note),
		);

		wp_send_json_success($response);
	}

	public function handle_delete_note() {
		$result = $this->delete_note_from_request($_GET);
		if (is_wp_error($result)) {
			wp_die(esc_html($result->get_error_message()));
		}

		wp_safe_redirect(add_query_arg(array('page' => 'wp-notes', 'notice' => 'note-deleted'), admin_url('admin.php')));
		exit;
	}

	public function handle_delete_note_ajax() {
		check_ajax_referer('wp_notes_delete_note', 'nonce');

		$result = $this->delete_note_from_request($_POST);
		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()), 400);
		}

		wp_send_json_success(array(
			'message'  => $this->i18n->t('note_deleted'),
			'noteId'   => (int) $result['note_id'],
			'scope'    => $result['scope'],
			'screenId' => $result['screen_id'],
		));
	}

	public function handle_upload_image() {
		if (! $this->permissions->can_create()) {
			wp_send_json_error(array('message' => $this->i18n->t('create_denied')), 403);
		}
		check_ajax_referer('wp_notes_upload_image', 'nonce');
		if (empty($_FILES['file']['name'])) {
			wp_send_json_error(array('message' => $this->i18n->t('upload_error')), 400);
		}

		$file = $_FILES['file'];
		$type = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
		if (empty($type['ext']) || 0 !== strpos((string) $type['type'], 'image/')) {
			wp_send_json_error(array('message' => $this->i18n->t('upload_error')), 400);
		}

		WP_Notes_Plugin::ensure_storage();
		$filename = wp_unique_filename(WP_Notes_Plugin::storage_path(), sanitize_file_name($file['name']));
		$target   = WP_Notes_Plugin::storage_path() . $filename;
		if (! @move_uploaded_file($file['tmp_name'], $target)) {
			wp_send_json_error(array('message' => $this->i18n->t('upload_error')), 500);
		}

		wp_send_json_success(array('url' => WP_Notes_Plugin::storage_url() . rawurlencode($filename)));
	}

	public function get_note_table_row_html($note) {
		$edit_url   = add_query_arg(array('page' => 'wp-notes-edit', 'note_id' => $note['id']), admin_url('admin.php'));
		$delete_url = wp_nonce_url(add_query_arg(array('action' => 'wp_notes_delete_note', 'note_id' => $note['id']), admin_url('admin-post.php')), 'wp_notes_delete_' . $note['id']);
		$side_label = $this->resolve_note_side_label($note);
		$theme_class = self::get_note_theme_class($note);

		ob_start();
		echo '<tr class="wp-notes-table__row ' . esc_attr($theme_class) . '" data-note-row-id="' . esc_attr($note['id']) . '" data-note-scope="' . esc_attr($note['scope']) . '" data-note-screen-id="' . esc_attr($note['screen_id']) . '">';
		if ('global' === $note['scope']) {
			echo '<td><span class="wp-notes-theme-swatch ' . esc_attr($theme_class) . '" aria-hidden="true"></span>' . esc_html($this->i18n->t('all_side')) . '</td>';
		} else {
			echo '<td><span class="wp-notes-theme-swatch ' . esc_attr($theme_class) . '" aria-hidden="true"></span><a class="wp-notes-table__title" href="' . esc_url($note['page_url']) . '">' . esc_html($side_label) . '</a></td>';
		}
		echo '<td>' . esc_html('global' === $note['scope'] ? $this->i18n->t('scope_global') : $this->i18n->t('scope_screen')) . '</td>';
		echo '<td>' . esc_html($this->user_label($note['author_id'])) . '</td>';
		echo '<td>' . esc_html(mysql2date('Y-m-d H:i', $note['updated_at'])) . '</td>';
		echo '<td>' . esc_html($this->access_label($note['edit_mode'])) . '</td>';
		echo '<td class="wp-notes-table__actions">';
		if ($this->permissions->can_edit($note)) {
			echo '<a class="button button-small wp-notes-open-modal" href="' . esc_url($edit_url) . '">' . esc_html($this->i18n->t('edit')) . '</a> ';
			echo '<a class="button button-small wp-notes-delete-note" data-confirm="' . esc_attr($this->i18n->t('delete_confirm')) . '" href="' . esc_url($delete_url) . '" data-note-id="' . esc_attr($note['id']) . '" data-note-scope="' . esc_attr($note['scope']) . '" data-note-screen-id="' . esc_attr($note['screen_id']) . '">' . esc_html($this->i18n->t('delete')) . '</a>';
		}
		echo '</td>';
		echo '</tr>';
		return ob_get_clean();
	}

	public function render_modal_root() {
		if (! is_admin() || ! $this->permissions->can_create()) {
			return;
		}

		echo '<div class="wp-notes-modal" data-wp-notes-modal hidden>';
		echo '<div class="wp-notes-modal__backdrop" data-wp-notes-close></div>';
		echo '<div class="wp-notes-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="wp-notes-modal-title">';
		echo '<div class="wp-notes-modal__header"><h2 id="wp-notes-modal-title" class="wp-notes-modal__title"></h2><button type="button" class="wp-notes-modal__close" data-wp-notes-close aria-label="' . esc_attr($this->i18n->t('close')) . '">&times;</button></div>';
		echo '<div class="wp-notes-modal__content" data-wp-notes-modal-content></div>';
		echo '</div>';
		echo '</div>';
	}

	private function get_note_form_html($note_data, $is_modal) {
		ob_start();
		echo '<form class="wp-notes-form' . ($is_modal ? ' is-modal' : '') . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-wp-notes-form="1">';
		wp_nonce_field('wp_notes_save_note');
		echo '<input type="hidden" name="action" value="wp_notes_save_note">';
		echo '<input type="hidden" name="note_id" value="' . esc_attr($note_data['id']) . '">';
		echo '<input type="hidden" name="scope" value="' . esc_attr($note_data['scope']) . '">';
		echo '<input type="hidden" name="screen_id" value="' . esc_attr($note_data['screen_id']) . '">';
		echo '<input type="hidden" name="page_url" value="' . esc_attr($note_data['page_url']) . '">';
		echo '<input type="hidden" name="page_title" value="' . esc_attr($note_data['page_title']) . '">';
		echo '<div class="wp-notes-form__messages" data-wp-notes-form-messages></div>';
		echo '<table class="form-table wp-notes-form__table">';
		if (! $is_modal) {
			echo '<tr><th scope="row">' . esc_html($this->i18n->t('form_scope')) . '</th><td>' . esc_html('global' === $note_data['scope'] ? $this->i18n->t('scope_global') : $this->i18n->t('scope_screen')) . '</td></tr>';
		}
		if (! $is_modal) {
			echo '<tr><th scope="row">' . esc_html($this->i18n->t('form_page_title')) . '</th><td>' . esc_html($note_data['page_title']) . '</td></tr>';
			echo '<tr><th scope="row">' . esc_html($this->i18n->t('form_page_url')) . '</th><td><code>' . esc_html($note_data['page_url']) . '</code></td></tr>';
		}
		echo '<tr><th scope="row"></th><td><div class="wp-notes-editor-tools"><input type="file" class="wp-notes-upload-input" accept="image/*" hidden></div>';
		echo $this->theme_field('note_theme', $note_data['note_theme']);
		echo '<div class="wp-notes-rich-editor" data-quill-root="1">';
		echo '<div class="wp-notes-rich-editor__surface" data-quill-editor="1">' . self::sanitize_note_content($note_data['content']) . '</div>';
		echo '<textarea class="wp-notes-form__textarea" id="wp_notes_content" name="content" rows="16" data-quill-input="1" hidden>' . esc_textarea($note_data['content']) . '</textarea>';
		echo '</div>';
		echo '</td></tr>';
		echo '<tr><th scope="row">' . esc_html($this->i18n->t('form_edit_access')) . '</th><td>' . $this->access_field('edit_mode', $note_data['edit_mode']) . '</td></tr>';
		echo '</table>';
		echo '<div class="wp-notes-form__actions">';
		submit_button($note_data['id'] ? $this->i18n->t('update_note') : $this->i18n->t('save_note'), 'primary', 'submit', false, array('data-wp-notes-submit' => '1', 'data-label' => $note_data['id'] ? $this->i18n->t('update_note') : $this->i18n->t('save_note')));
		if ($is_modal) {
			echo ' <button type="button" class="button wp-notes-form__cancel" data-wp-notes-close>' . esc_html($this->i18n->t('cancel')) . '</button>';
		} else {
			echo ' <a class="button wp-notes-form__cancel" href="' . esc_url(admin_url('admin.php?page=wp-notes')) . '">' . esc_html($this->i18n->t('cancel')) . '</a>';
		}
		echo '</div></form>';
		return ob_get_clean();
	}

	private function access_field($mode_name, $mode_value) {
		ob_start(); ?>
		<fieldset class="wp-notes-access-field">
			<div class="wp-notes-access-field__row">
				<label class="wp-notes-access-field__option"><input type="radio" name="<?php echo esc_attr($mode_name); ?>" value="author" <?php checked($mode_value, 'author'); ?>> <?php echo esc_html($this->i18n->t('access_author')); ?></label>
			</div>
			<div class="wp-notes-access-field__row">
				<label class="wp-notes-access-field__option"><input type="radio" name="<?php echo esc_attr($mode_name); ?>" value="anyone" <?php checked($mode_value, 'anyone'); ?>> <?php echo esc_html($this->i18n->t('access_anyone')); ?></label>
			</div>
		</fieldset>
		<?php
		return ob_get_clean();
	}

	private function theme_field($field_name, $selected_theme) {
		$themes = $this->note_theme_options();

		ob_start(); ?>
		<fieldset class="wp-notes-theme-field">
			<legend class="wp-notes-theme-field__label"><?php echo esc_html($this->i18n->t('form_note_background')); ?></legend>
			<div class="wp-notes-theme-row">
				<?php foreach ($themes as $theme_key => $theme_label) : ?>
					<label class="wp-notes-theme-option" title="<?php echo esc_attr($theme_label); ?>">
						<input type="radio" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($theme_key); ?>" <?php checked(self::normalize_note_theme($selected_theme), $theme_key); ?>>
						<span class="wp-notes-theme-option__swatch <?php echo esc_attr(self::get_note_theme_class($theme_key)); ?>" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php echo esc_html($theme_label); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
		return ob_get_clean();
	}

	private function filter_visible_note($note) {
		return $this->permissions->can_view($note);
	}

	private function access_label($mode) {
		if ('anyone' === $mode) {
			return $this->i18n->t('access_anyone');
		}

		return $this->i18n->t('access_author');
	}

	private function user_label($user_id) {
		$user = get_user_by('id', $user_id);
		return $user ? $user->display_name : '#' . (int) $user_id;
	}

	private function render_notice() {
		$notice  = isset($_GET['notice']) ? sanitize_key(wp_unslash($_GET['notice'])) : '';
		$message = '';
		if ('note-saved' === $notice) {
			$message = $this->i18n->t('note_saved');
		} elseif ('note-deleted' === $notice) {
			$message = $this->i18n->t('note_deleted');
		}
		if ($message) {
			echo '<div class="notice notice-success is-dismissible wp-notes-notice"><p>' . esc_html($message) . '</p></div>';
		}
	}

	private function get_note_form_state($request) {
		$note_id = isset($request['note_id']) ? absint($request['note_id']) : 0;
		$note    = $note_id ? $this->repository->get($note_id) : null;

		if ($note_id && ! $note) {
			throw new Exception($this->i18n->t('visibility_denied'));
		}

		if ($note && ! $this->permissions->can_edit($note)) {
			throw new Exception($this->i18n->t('edit_denied'));
		}

		$scope = $note ? $note['scope'] : (isset($request['scope']) ? sanitize_key(wp_unslash($request['scope'])) : 'screen');
		$scope = in_array($scope, array('screen', 'global'), true) ? $scope : 'screen';

		$screen_id  = $note ? $note['screen_id'] : (isset($request['screen_id']) ? sanitize_text_field(rawurldecode(wp_unslash($request['screen_id']))) : '');
		$page_url   = $note ? $note['page_url'] : (isset($request['return_url']) ? esc_url_raw(rawurldecode(wp_unslash($request['return_url']))) : (isset($request['page_url']) ? esc_url_raw(wp_unslash($request['page_url'])) : WP_Notes_Context::current_admin_url()));
		$page_title = $note ? $note['page_title'] : (isset($request['page_title']) ? sanitize_text_field(rawurldecode(wp_unslash($request['page_title']))) : WP_Notes_Context::current_page_title());

		if ('screen' === $scope && '' === $screen_id) {
			throw new Exception($this->i18n->t('missing_screen'));
		}

		if ('screen' === $scope && ! $note && ! $this->can_create_screen_note_for_request($screen_id, $page_url)) {
			throw new Exception($this->i18n->t('screen_note_page_only'));
		}

		return array(
			'id'         => $note ? $note['id'] : 0,
			'scope'      => $scope,
			'screen_id'  => $screen_id,
			'page_url'   => $page_url,
			'page_title' => $page_title,
			'content'    => $note ? $note['content'] : '',
			'edit_mode'  => ('anyone' === $note['edit_mode']) ? 'anyone' : 'author',
			'note_theme' => $note ? self::normalize_note_theme($note['note_theme']) : self::DEFAULT_NOTE_THEME,
		);
	}

	private function save_note_from_request($request) {
		$scope     = isset($request['scope']) ? sanitize_key(wp_unslash($request['scope'])) : 'screen';
		$scope     = in_array($scope, array('screen', 'global'), true) ? $scope : 'screen';
		$screen_id = isset($request['screen_id']) ? sanitize_text_field(wp_unslash($request['screen_id'])) : '';
		$page_url  = isset($request['page_url']) ? esc_url_raw(wp_unslash($request['page_url'])) : '';
		$note_id   = isset($request['note_id']) ? absint($request['note_id']) : 0;
		$existing  = $note_id ? $this->repository->get($note_id) : null;

		if ('screen' === $scope && '' === $screen_id) {
			return new WP_Error('missing_screen', $this->i18n->t('missing_screen'));
		}

		if ('screen' === $scope && ! $this->can_create_screen_note_for_request($screen_id, $page_url)) {
			return new WP_Error('screen_note_page_only', $this->i18n->t('screen_note_page_only'));
		}

		if (! $existing) {
			$existing = ('global' === $scope) ? $this->repository->get_global() : $this->repository->get_for_screen($screen_id, $page_url);
			if ($existing) {
				$note_id = (int) $existing['id'];
			}
		}

		if ($existing && ! $this->permissions->can_edit($existing)) {
			return new WP_Error('edit_denied', $this->i18n->t('edit_denied'));
		}

		$edit_mode     = isset($request['edit_mode']) ? sanitize_key(wp_unslash($request['edit_mode'])) : 'author';
		$allowed_modes = array('author', 'anyone');
		$edit_mode     = in_array($edit_mode, $allowed_modes, true) ? $edit_mode : 'author';
		$page_title    = isset($request['page_title']) ? sanitize_text_field(wp_unslash($request['page_title'])) : '';
		$content       = isset($request['content']) ? self::sanitize_note_content(wp_unslash($request['content'])) : '';
		$note_theme    = isset($request['note_theme']) ? self::normalize_note_theme(wp_unslash($request['note_theme'])) : self::DEFAULT_NOTE_THEME;
		$previous_note = $existing ? $existing : null;

		$saved_note_id = $this->repository->save(array(
			'id'         => $note_id,
			'scope'      => $scope,
			'screen_id'  => $screen_id,
			'page_url'   => $page_url,
			'page_title' => $page_title,
			'content'    => $content,
			'author_id'  => $existing ? $existing['author_id'] : get_current_user_id(),
			'edit_mode'  => $edit_mode,
			'note_theme' => $note_theme,
		));

		if ($previous_note && $saved_note_id > 0) {
			$this->delete_removed_note_images($previous_note, array(
				'id'      => $saved_note_id,
				'content' => $content,
			));
		}

		return $saved_note_id;
	}

	private function delete_note_from_request($request) {
		$note_id = isset($request['note_id']) ? absint($request['note_id']) : 0;
		$note    = $note_id ? $this->repository->get($note_id) : null;
		if (! $note || ! $this->permissions->can_edit($note)) {
			return new WP_Error('edit_denied', $this->i18n->t('edit_denied'));
		}

		if (isset($request['_wpnonce'])) {
			check_admin_referer('wp_notes_delete_' . $note_id);
		}

		$this->delete_note_images($note);
		$this->repository->delete($note_id);

		return array(
			'note_id'   => $note_id,
			'scope'     => $note['scope'],
			'screen_id' => $note['screen_id'],
		);
	}

	private function get_note_card_html($note) {
		if (! class_exists('WP_Notes_Renderer')) {
			return '';
		}

		return WP_Notes_Renderer::render_card_html($note, $this->i18n, $this->permissions);
	}

	private function resolve_note_side_label($note) {
		$object_id = $this->extract_object_id_from_note($note);
		if ($object_id > 0) {
			$post = get_post($object_id);
			if ($post && '' !== trim($post->post_title)) {
				return wp_strip_all_tags($post->post_title);
			}
		}

		return ! empty($note['page_title']) ? $note['page_title'] : get_bloginfo('name');
	}

	private function get_note_form_title($note_data) {
		$prefix = ! empty($note_data['id']) ? $this->i18n->t('form_title_edit_prefix') : $this->i18n->t('form_title_add_prefix');
		$type   = ('global' === $note_data['scope']) ? $this->i18n->t('whole_site_note_label') : $this->i18n->t('page_note_label');

		return trim($prefix . ' ' . $type);
	}

	private function extract_object_id_from_note($note) {
		if (! empty($note['screen_id']) && false !== strpos($note['screen_id'], ':')) {
			$parts = explode(':', (string) $note['screen_id']);
			$last  = end($parts);
			$id    = absint($last);
			if ($id > 0) {
				return $id;
			}
		}

		if (! empty($note['page_url'])) {
			$query = wp_parse_url($note['page_url'], PHP_URL_QUERY);
			if (is_string($query)) {
				parse_str($query, $params);
				foreach (array('post', 'post_ID') as $key) {
					if (! empty($params[$key])) {
						$id = absint($params[$key]);
						if ($id > 0) {
							return $id;
						}
					}
				}
			}
		}

		return 0;
	}

	private function delete_note_images($note) {
		$image_sources = $this->extract_local_note_image_sources($note);

		foreach ($image_sources as $source) {
			if ($this->repository->has_content_reference_excluding_note($source['src'], $note['id'])) {
				continue;
			}

			if (file_exists($source['path'])) {
				wp_delete_file($source['path']);
			}
		}
	}

	private function delete_removed_note_images($previous_note, $updated_note) {
		$previous_sources = $this->extract_local_note_image_sources($previous_note);
		$current_sources  = $this->extract_local_note_image_sources($updated_note);
		$current_by_src   = array();

		foreach ($current_sources as $source) {
			$current_by_src[$source['src']] = true;
		}

		foreach ($previous_sources as $source) {
			if (isset($current_by_src[$source['src']])) {
				continue;
			}

			if ($this->repository->has_content_reference_excluding_note($source['src'], $updated_note['id'])) {
				continue;
			}

			if (file_exists($source['path'])) {
				wp_delete_file($source['path']);
			}
		}
	}

	private function extract_local_note_image_sources($note) {
		$content = isset($note['content']) ? (string) $note['content'] : '';
		$matches = array();
		$sources = array();
		$storage_url = WP_Notes_Plugin::storage_url();
		$storage_host = (string) wp_parse_url($storage_url, PHP_URL_HOST);
		$storage_path = untrailingslashit((string) wp_parse_url($storage_url, PHP_URL_PATH));

		if ('' === $content) {
			return array();
		}

		preg_match_all('/<img[^>]+src=(["\'])(.*?)\1/i', $content, $matches);
		if (empty($matches[2])) {
			return array();
		}

		foreach ($matches[2] as $src) {
			$src = esc_url_raw(html_entity_decode((string) $src));

			if ('' === $src) {
				continue;
			}

			$src_host = (string) wp_parse_url($src, PHP_URL_HOST);
			$src_path = (string) wp_parse_url($src, PHP_URL_PATH);

			if ('' === $src_path || $src_host !== $storage_host || 0 !== strpos($src_path, $storage_path . '/')) {
				continue;
			}

			$filename = wp_basename(rawurldecode($src_path));
			if ('' === $filename) {
				continue;
			}

			$sources[$src] = array(
				'src'  => $src,
				'path' => WP_Notes_Plugin::storage_path() . $filename,
			);
		}

		return array_values($sources);
	}

	private function note_theme_options() {
		return array(
			'default'  => $this->i18n->t('theme_default'),
			'sky'      => $this->i18n->t('theme_sky'),
			'mint'     => $this->i18n->t('theme_mint'),
			'peach'    => $this->i18n->t('theme_peach'),
			'rose'     => $this->i18n->t('theme_rose'),
			'lavender' => $this->i18n->t('theme_lavender'),
			'lemon'    => $this->i18n->t('theme_lemon'),
			'ice'      => $this->i18n->t('theme_ice'),
			'sage'     => $this->i18n->t('theme_sage'),
			'sand'     => $this->i18n->t('theme_sand'),
		);
	}

}
