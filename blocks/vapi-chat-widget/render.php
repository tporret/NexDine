<?php
/**
 * Server-side rendering for the Vapi Chat Widget block.
 *
 * @package NexDine
 */

if (!defined('ABSPATH')) {
	exit;
}

$settings = get_option('nexdine_vapi_settings', array());
$settings = is_array($settings) ? $settings : array();

$default_public_key = isset($settings['public_key']) ? sanitize_text_field($settings['public_key']) : '';
$default_assistant_id = isset($settings['assistant_id']) ? sanitize_text_field($settings['assistant_id']) : '';
$configured_assistant_ids = array();

if (!empty($settings['assistant_ids'])) {
	if (is_array($settings['assistant_ids'])) {
		$raw_assistant_ids = $settings['assistant_ids'];
	} else {
		$raw_assistant_ids = preg_split('/[\r\n,]+/', (string) $settings['assistant_ids']);
	}

	foreach ((array) $raw_assistant_ids as $raw_assistant_id) {
		$clean_id = sanitize_text_field(trim((string) $raw_assistant_id));

		if ($clean_id !== '') {
			$configured_assistant_ids[] = $clean_id;
		}
	}
}

$attributes = wp_parse_args(
	(array) $attributes,
	array(
		'assistantId' => '',
	)
);

$assistant_id = sanitize_text_field($attributes['assistantId']);

$public_key = $default_public_key;

if ($assistant_id === '') {
	$assistant_id = $default_assistant_id;

	if ($assistant_id === '' && !empty($configured_assistant_ids)) {
		$assistant_id = $configured_assistant_ids[0];
	}
}

if ($public_key === '' || $assistant_id === '') {
	return '';
}

wp_enqueue_script(
	'nexdine-vapi-widget-sdk',
	'https://unpkg.com/@vapi-ai/client-sdk-react/dist/embed/widget.umd.js',
	array(),
	null,
	true
);

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'nexdine-vapi-chat-widget',
	)
);

echo sprintf(
	'<div %1$s><vapi-widget public-key="%2$s" assistant-id="%3$s" mode="hybrid" theme="light"></vapi-widget></div>',
	$wrapper_attributes,
	esc_attr($public_key),
	esc_attr($assistant_id)
);
