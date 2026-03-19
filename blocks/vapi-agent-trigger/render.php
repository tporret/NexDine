<?php
/**
 * Server-side rendering for the Vapi Agent Trigger block.
 *
 * @package NexDine
 */

if (!defined('ABSPATH')) {
	exit;
}

$settings = get_option('nexdine_vapi_settings', array());
$settings = is_array($settings) ? $settings : array();

$configured_public_key = isset($settings['public_key']) ? sanitize_text_field($settings['public_key']) : '';
$configured_assistant_id = isset($settings['assistant_id']) ? sanitize_text_field($settings['assistant_id']) : '';
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

$defaults = array(
	'assistantId' => '',
	'buttonText' => __('Talk To Host', 'nexdine'),
	'designVariation' => 'default',
	'horizontalAlign' => 'left',
);

$attributes = wp_parse_args((array) $attributes, $defaults);

$assistant_id = sanitize_text_field($attributes['assistantId']);

if ($assistant_id === '') {
	$assistant_id = $configured_assistant_id;

	if ($assistant_id === '' && !empty($configured_assistant_ids)) {
		$assistant_id = $configured_assistant_ids[0];
	}
}

$api_key = $configured_public_key;
$button_text = sanitize_text_field($attributes['buttonText']);
$primary_color = '#0d9488';
$mode = 'voice';
$allowed_variations = array('default', 'elevation-high', 'tonal', 'outlined', 'glassmorphism', 'minimalist', 'neumorphic');
$design_variation = sanitize_key((string) $attributes['designVariation']);

if (!in_array($design_variation, $allowed_variations, true)) {
	$design_variation = 'default';
}

$design_class = 'is-design-' . $design_variation;

$allowed_alignments = array('left', 'center', 'right');
$horizontal_align = sanitize_key((string) $attributes['horizontalAlign']);

if (!in_array($horizontal_align, $allowed_alignments, true)) {
	$horizontal_align = 'left';
}

$alignment_class = 'is-horizontal-' . $horizontal_align;

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'nexdine-vapi-agent-trigger ' . $design_class . ' ' . $alignment_class,
		'style' => sprintf('--nexdine-primary:%s;', esc_attr($primary_color)),
	)
);

$button_attrs = array(
	'type' => 'button',
	'class' => 'nexdine-vapi-agent-trigger__button is-online',
	'data-assistant-id' => $assistant_id,
	'data-api-key' => $api_key,
	'data-mode' => $mode,
	'data-default-text' => $button_text,
	'data-offline-text' => __('Agent Offline', 'nexdine'),
	'data-live-text' => __('Call Live', 'nexdine'),
	'aria-label' => $button_text,
);

$end_button_attrs = array(
	'type' => 'button',
	'class' => 'nexdine-vapi-agent-trigger__end-button is-hidden',
	'data-end-text' => __('End Call', 'nexdine'),
	'aria-label' => __('End Call', 'nexdine'),
);

$button_html_attributes = '';

foreach ($button_attrs as $key => $value) {
	$button_html_attributes .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
}

$end_button_html_attributes = '';

foreach ($end_button_attrs as $key => $value) {
	$end_button_html_attributes .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
}

echo sprintf(
	'<div %1$s><div class="nexdine-vapi-agent-trigger__controls"><button%2$s>%3$s</button><button%4$s>%5$s</button></div></div>',
	$wrapper_attributes,
	$button_html_attributes,
	esc_html($button_text),
	$end_button_html_attributes,
	esc_html__('End Call', 'nexdine')
);
