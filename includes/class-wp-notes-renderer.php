<?php

if (! defined('ABSPATH')) {
	exit;
}

class WP_Notes_Renderer {
	private $repository;
	private $i18n;
	private $permissions;

	public function __construct($repository, $i18n, $permissions) {
		$this->repository  = $repository;
		$this->i18n        = $i18n;
		$this->permissions = $permissions;
	}

	public function register() {
		add_action('in_admin_header', array($this, 'render_notes'), 5);
	}

	public function render_notes() {
		if (! is_admin()) {
			return;
		}

		$screen = get_current_screen();
		if (! $screen) {
			return;
		}

		$notes       = array();
		$global_note = $this->repository->get_global();
		$screen_key  = WP_Notes_Context::current_screen_id();
		$current_url = WP_Notes_Context::current_admin_url();
		$screen_note = $this->repository->get_for_screen($screen_key, $current_url);

		if ($global_note && $this->permissions->can_view($global_note)) {
			$notes[] = $global_note;
		}

		if ($screen_note && $this->permissions->can_view($screen_note)) {
			$notes[] = $screen_note;
		}

		echo '<div class="wp-notes-wrap" data-wp-notes-wrap="1">';
		foreach ($notes as $note) {
			echo self::render_card_html($note, $this->i18n, $this->permissions);
		}
		echo '</div>';
	}

	public static function render_card_html($note, $i18n, $permissions) {
		$edit_url    = add_query_arg(array('page' => 'wp-notes-edit', 'note_id' => $note['id']), admin_url('admin.php'));
		$delete_url  = wp_nonce_url(add_query_arg(array('action' => 'wp_notes_delete_note', 'note_id' => $note['id']), admin_url('admin-post.php')), 'wp_notes_delete_' . $note['id']);
		$scope_label = 'global' === $note['scope'] ? $i18n->t('global_note_label') : $i18n->t('screen_note_label');
		$theme_class = WP_Notes_Admin::get_note_theme_class($note);

		ob_start();
		echo '<div class="notice notice-info wp-notes-notice-card is-collapsed ' . esc_attr($theme_class) . '" data-note-id="' . esc_attr($note['id']) . '" data-note-scope="' . esc_attr($note['scope']) . '" data-note-screen-id="' . esc_attr($note['screen_id']) . '">';
		echo '<div class="wp-notes-notice-card__header">';
		echo '<div class="wp-notes-notice-card__meta">';
		echo '<p class="wp-notes-notice-card__eyebrow"><img class="wp-notes-notice-card__eyebrow-logo" src="' . esc_url(WP_NOTES_URL . 'assets/img/R-logo.svg') . '" alt=""><span>' . esc_html($scope_label) . '</span></p>';
		echo '</div>';
		echo '<div class="wp-notes-notice-card__actions">';
		echo '<button type="button" class="wp-notes-icon-action wp-notes-toggle-note" aria-expanded="false" aria-label="' . esc_attr($i18n->t('toggle_note')) . '" title="' . esc_attr($i18n->t('toggle_note')) . '">';
		echo '<img src="' . esc_url(WP_NOTES_URL . 'assets/img/arrow-down.svg') . '" data-icon-collapsed="' . esc_url(WP_NOTES_URL . 'assets/img/arrow-down.svg') . '" data-icon-expanded="' . esc_url(WP_NOTES_URL . 'assets/img/arrow-up.svg') . '" alt="">';
		echo '</button>';
		if ($permissions->can_edit($note)) {
			echo '<a class="wp-notes-icon-action wp-notes-open-modal" href="' . esc_url($edit_url) . '" aria-label="' . esc_attr($i18n->t('edit')) . '" title="' . esc_attr($i18n->t('edit')) . '"><img src="' . esc_url(WP_NOTES_URL . 'assets/img/edit.svg') . '" alt=""></a>';
			echo '<a class="wp-notes-icon-action wp-notes-delete-note" data-confirm="' . esc_attr($i18n->t('delete_confirm')) . '" href="' . esc_url($delete_url) . '" data-note-id="' . esc_attr($note['id']) . '" data-note-scope="' . esc_attr($note['scope']) . '" data-note-screen-id="' . esc_attr($note['screen_id']) . '" aria-label="' . esc_attr($i18n->t('delete')) . '" title="' . esc_attr($i18n->t('delete')) . '"><img src="' . esc_url(WP_NOTES_URL . 'assets/img/close.svg') . '" alt=""></a>';
		}
		echo '</div>';
		echo '</div>';
		echo '<div class="wp-notes-notice-card__body">';
		echo WP_Notes_Admin::sanitize_note_content($note['content']);
		echo '</div>';
		echo '</div>';
		return ob_get_clean();
	}
}
