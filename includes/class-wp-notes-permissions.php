<?php

if (! defined('ABSPATH')) {
	exit;
}

class WP_Notes_Permissions {
	public function can_manage_plugin() {
		return current_user_can('edit_pages');
	}

	public function can_create() {
		return $this->can_manage_plugin();
	}

	public function can_view($note, $user_id = 0) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if (! $user_id || empty($note)) {
			return false;
		}

		return user_can($user_id, 'edit_pages');
	}

	public function can_edit($note, $user_id = 0) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if (! $user_id || empty($note)) {
			return false;
		}

		if (user_can($user_id, 'manage_options')) {
			return true;
		}

		if ('anyone' === $note['edit_mode']) {
			return user_can($user_id, 'edit_pages');
		}

		return (int) $note['author_id'] === $user_id;
	}
}
