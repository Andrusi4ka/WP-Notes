<?php

if (! defined('ABSPATH')) {
	exit;
}

class WP_Notes_Context {
	public static function current_admin_url() {
		$request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/wp-admin/';
		$scheme      = is_ssl() ? 'https://' : 'http://';
		$host        = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : wp_parse_url(admin_url(), PHP_URL_HOST);

		return esc_url_raw($scheme . $host . $request_uri);
	}

	public static function current_page_title() {
		$object_id = self::current_object_id();
		if ($object_id > 0) {
			$post = get_post($object_id);
			if ($post && '' !== trim($post->post_title)) {
				return wp_strip_all_tags($post->post_title);
			}
		}

		if (function_exists('get_current_screen')) {
			$screen = get_current_screen();
			if ($screen && ! empty($screen->title)) {
				return wp_strip_all_tags($screen->title);
			}
		}

		return get_bloginfo('name');
	}

	public static function current_screen_id() {
		if (! function_exists('get_current_screen')) {
			return '';
		}

		$screen = get_current_screen();
		if (! $screen) {
			return '';
		}

		$screen_id = (string) $screen->id;
		$object_id = self::current_object_id();
		if ($object_id > 0) {
			return $screen_id . ':' . $object_id;
		}

		return $screen_id;
	}

	public static function current_object_id() {
		$candidates = array('post', 'post_ID', 'tag_ID', 'user_id', 'comment_ID');

		foreach ($candidates as $key) {
			if (! isset($_GET[$key])) {
				continue;
			}

			$value = absint(wp_unslash($_GET[$key]));
			if ($value > 0) {
				return $value;
			}
		}

		return 0;
	}
}
