<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once NEXDINE_PLUGIN_PATH . 'includes/class-nexdine-loader.php';
require_once NEXDINE_PLUGIN_PATH . 'includes/class-nexdine-i18n.php';
require_once NEXDINE_PLUGIN_PATH . 'admin/class-nexdine-admin.php';
require_once NEXDINE_PLUGIN_PATH . 'public/class-nexdine-public.php';

class NexDine {
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $vapi_option_name;
    protected $agents_cache_key;
    protected $agents_cache_ttl;
    protected $encryption_prefix;

    public function __construct() {
        $this->plugin_name = 'nexdine';
        $this->version = defined('NEXDINE_VERSION') ? NEXDINE_VERSION : '0.1.0';
        $this->vapi_option_name = 'nexdine_vapi_settings';
        $this->agents_cache_key = 'nexdine_vapi_agents_cache';
        $this->agents_cache_ttl = HOUR_IN_SECONDS;
        $this->encryption_prefix = 'nexdine_enc_v1:';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_block_hooks();
        $this->define_data_hooks();
    }

    private function define_block_hooks() {
        $this->loader->add_action('init', $this, 'register_blocks');
        $this->loader->add_action('rest_api_init', $this, 'register_rest_routes');
        $this->loader->add_action('enqueue_block_editor_assets', $this, 'enqueue_block_editor_data');
    }

    private function define_data_hooks() {
        $this->loader->add_action('init', $this, 'register_vapi_reservation_post_type');
        $this->loader->add_action('init', $this, 'register_vapi_reservation_meta');
    }

    public function register_vapi_reservation_post_type() {
        $labels = array(
            'name' => __('Vapi Reservations', 'nexdine'),
            'singular_name' => __('Vapi Reservation', 'nexdine'),
            'menu_name' => __('Vapi Reservations', 'nexdine'),
            'add_new' => __('Add New', 'nexdine'),
            'add_new_item' => __('Add New Reservation', 'nexdine'),
            'edit_item' => __('Edit Reservation', 'nexdine'),
            'new_item' => __('New Reservation', 'nexdine'),
            'view_item' => __('View Reservation', 'nexdine'),
            'search_items' => __('Search Reservations', 'nexdine'),
            'not_found' => __('No reservations found.', 'nexdine'),
            'not_found_in_trash' => __('No reservations found in Trash.', 'nexdine'),
        );

        register_post_type(
            'vapi_reservation',
            array(
                'labels' => $labels,
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => true,
                'show_in_rest' => true,
                'rest_base' => 'vapi_reservation',
                'rest_controller_class' => 'WP_REST_Posts_Controller',
                'supports' => array('title', 'custom-fields'),
                'capability_type' => 'post',
                'map_meta_cap' => true,
                'menu_icon' => 'dashicons-calendar-alt',
            )
        );
    }

    public function register_vapi_reservation_meta() {
        $slot_meta_keys = array(
            'party_size',
            'date',
            'time',
            'seating_preference',
            'occasion',
            'notes',
            'customer_name',
            'phone',
            'call_sid',
        );

        foreach ($slot_meta_keys as $meta_key) {
            register_meta(
                'post',
                $meta_key,
                array(
                    'object_subtype' => 'vapi_reservation',
                    'type' => 'string',
                    'single' => true,
                    'show_in_rest' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'auth_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                )
            );
        }
    }

    public function register_rest_routes() {
        register_rest_route(
            'nexdine/v1',
            '/vapi-assistants',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'rest_get_vapi_assistants'),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function rest_get_vapi_assistants($request) {
        $settings = $this->get_vapi_settings();
        $encrypted_private_key = isset($settings['private_key']) ? (string) $settings['private_key'] : '';
        $private_key = trim($this->decrypt_secret($encrypted_private_key));

        if ($private_key === '') {
            return new WP_Error(
                'nexdine_missing_private_key',
                __('Missing Vapi private key.', 'nexdine'),
                array('status' => 400)
            );
        }

        $response = wp_remote_get(
            'https://api.vapi.ai/assistant?limit=100',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $private_key,
                    'Accept' => 'application/json',
                ),
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error(
                'nexdine_vapi_request_failed',
                $response->get_error_message(),
                array('status' => 502)
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status_code < 200 || $status_code >= 300) {
            return new WP_Error(
                'nexdine_vapi_response_error',
                $this->extract_vapi_error_message($decoded),
                array('status' => $status_code > 0 ? $status_code : 500)
            );
        }

        return rest_ensure_response(
            array(
                'assistants' => $this->normalize_agents_for_editor($this->extract_agents_from_response($decoded)),
            )
        );
    }

    public function register_blocks() {
        $vapi_block_path = NEXDINE_PLUGIN_PATH . 'blocks/vapi-agent-trigger';
        $vapi_chat_widget_block_path = NEXDINE_PLUGIN_PATH . 'blocks/vapi-chat-widget';

        if (file_exists($vapi_block_path . '/block.json')) {
            register_block_type($vapi_block_path);
        }

        if (file_exists($vapi_chat_widget_block_path . '/block.json')) {
            register_block_type($vapi_chat_widget_block_path);
        }
    }

    public function enqueue_block_editor_data() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $settings = $this->get_vapi_settings();

        $this->enqueue_agent_trigger_editor_data($settings);
        $this->enqueue_chat_widget_editor_data($settings);
    }

    private function enqueue_agent_trigger_editor_data($settings) {

        $editor_script_handle = 'nexdine-vapi-agent-trigger-editor-script';

        if (!wp_script_is($editor_script_handle, 'registered')) {
            return;
        }

        $settings = $this->get_vapi_settings();
        $public_key = isset($settings['public_key']) ? sanitize_text_field($settings['public_key']) : '';
        $default_assistant_id = isset($settings['assistant_id']) ? sanitize_text_field($settings['assistant_id']) : '';
        $configured_assistant_ids = $this->get_configured_assistant_ids($settings);
        $encrypted_private_key = isset($settings['private_key']) ? $settings['private_key'] : '';
        $private_key = trim($this->decrypt_secret($encrypted_private_key));

        $data = array(
            'agents' => array(),
            'defaultAssistantId' => $default_assistant_id,
            'message' => '',
        );

        if ($public_key === '' || $private_key === '') {
            $data['message'] = __('Vapi credentials are missing. Ask an admin to configure NexDine Vapi Settings.', 'nexdine');
            $data['agents'] = $this->normalize_configured_assistant_ids($configured_assistant_ids);
        } else {
            $result = $this->get_editor_agents($private_key);

            $data['agents'] = $this->merge_agents_with_configured_ids($result['agents'], $configured_assistant_ids);
            $data['message'] = $result['error'];
        }

        if ($data['defaultAssistantId'] === '' && !empty($configured_assistant_ids)) {
            $data['defaultAssistantId'] = $configured_assistant_ids[0];
        }

        $json_data = wp_json_encode($data);

        if (!$json_data) {
            return;
        }

        wp_add_inline_script(
            $editor_script_handle,
            'window.nexdineVapiBlockData = ' . $json_data . ';',
            'before'
        );
    }

    private function enqueue_chat_widget_editor_data($settings) {
        $editor_script_handle = 'nexdine-vapi-chat-widget-editor-script';

        if (!wp_script_is($editor_script_handle, 'registered')) {
            return;
        }

        $public_key = isset($settings['public_key']) ? sanitize_text_field($settings['public_key']) : '';
        $default_assistant_id = isset($settings['assistant_id']) ? sanitize_text_field($settings['assistant_id']) : '';

        $data = array(
            'defaultPublicKey' => $public_key,
            'defaultAssistantId' => $default_assistant_id,
            'message' => '',
        );

        if ($public_key === '') {
            $data['message'] = __('Vapi public key is missing. Ask an admin to configure NexDine Vapi Settings.', 'nexdine');
        }

        $json_data = wp_json_encode($data);

        if (!$json_data) {
            return;
        }

        wp_add_inline_script(
            $editor_script_handle,
            'window.nexdineVapiChatWidgetData = ' . $json_data . ';',
            'before'
        );
    }

    private function get_editor_agents($private_key) {
        $cached = get_transient($this->agents_cache_key);

        if (is_array($cached) && isset($cached['agents']) && is_array($cached['agents'])) {
            return array(
                'agents' => $this->normalize_agents_for_editor($cached['agents']),
                'error' => isset($cached['api_error']) ? (string) $cached['api_error'] : '',
            );
        }

        $response = wp_remote_get(
            'https://api.vapi.ai/assistant?limit=100',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $private_key,
                    'Accept' => 'application/json',
                ),
            )
        );

        if (is_wp_error($response)) {
            return array(
                'agents' => array(),
                'error' => $response->get_error_message(),
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status_code >= 200 && $status_code < 300) {
            $agents = $this->extract_agents_from_response($decoded);

            set_transient(
                $this->agents_cache_key,
                array(
                    'agents' => $agents,
                    'api_error' => '',
                ),
                $this->agents_cache_ttl
            );

            return array(
                'agents' => $this->normalize_agents_for_editor($agents),
                'error' => '',
            );
        }

        $error = $this->extract_vapi_error_message($decoded);

        set_transient(
            $this->agents_cache_key,
            array(
                'agents' => array(),
                'api_error' => $error,
            ),
            $this->agents_cache_ttl
        );

        return array(
            'agents' => array(),
            'error' => $error,
        );
    }

    private function normalize_agents_for_editor($agents) {
        $items = array();

        foreach ((array) $agents as $agent) {
            if (!is_array($agent)) {
                continue;
            }

            $id = isset($agent['id']) ? sanitize_text_field((string) $agent['id']) : '';

            if ($id === '') {
                continue;
            }

            $name = isset($agent['name']) ? sanitize_text_field((string) $agent['name']) : '';

            $items[] = array(
                'id' => $id,
                'name' => $name !== '' ? $name : $id,
            );
        }

        return $items;
    }

    private function normalize_configured_assistant_ids($assistant_ids) {
        $items = array();

        foreach ((array) $assistant_ids as $assistant_id) {
            $id = sanitize_text_field((string) $assistant_id);

            if ($id === '') {
                continue;
            }

            $items[] = array(
                'id' => $id,
                'name' => $id,
            );
        }

        return $items;
    }

    private function merge_agents_with_configured_ids($agents, $configured_assistant_ids) {
        $items = $this->normalize_agents_for_editor($agents);

        foreach ($this->normalize_configured_assistant_ids($configured_assistant_ids) as $configured) {
            $exists = false;

            foreach ($items as $item) {
                if ($item['id'] === $configured['id']) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $items[] = $configured;
            }
        }

        return $items;
    }

    private function get_configured_assistant_ids($settings) {
        $assistant_ids = array();

        if (!empty($settings['assistant_id']) && is_string($settings['assistant_id'])) {
            $assistant_ids[] = sanitize_text_field($settings['assistant_id']);
        }

        if (!empty($settings['assistant_ids'])) {
            if (is_array($settings['assistant_ids'])) {
                $raw_values = $settings['assistant_ids'];
            } else {
                $raw_values = preg_split('/[\r\n,]+/', (string) $settings['assistant_ids']);
            }

            foreach ((array) $raw_values as $raw_value) {
                $clean = sanitize_text_field(trim((string) $raw_value));

                if ($clean !== '') {
                    $assistant_ids[] = $clean;
                }
            }
        }

        return array_values(array_unique($assistant_ids));
    }

    private function extract_agents_from_response($decoded) {
        if (is_array($decoded)) {
            if (isset($decoded['results']) && is_array($decoded['results'])) {
                return $decoded['results'];
            }

            if (isset($decoded['data']) && is_array($decoded['data'])) {
                return $decoded['data'];
            }

            if ($this->is_sequential_array($decoded)) {
                return $decoded;
            }
        }

        return array();
    }

    private function extract_vapi_error_message($decoded) {
        $error_message = __('Unexpected response from Vapi.', 'nexdine');

        if (is_array($decoded)) {
            if (!empty($decoded['message']) && is_string($decoded['message'])) {
                $error_message = $decoded['message'];
            } elseif (!empty($decoded['error']) && is_string($decoded['error'])) {
                $error_message = $decoded['error'];
            }
        }

        return $error_message;
    }

    private function is_sequential_array($value) {
        if (!is_array($value)) {
            return false;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function get_vapi_settings() {
        $settings = get_option($this->vapi_option_name, array());
        return is_array($settings) ? $settings : array();
    }

    private function decrypt_secret($stored_value) {
        if (!is_string($stored_value) || $stored_value === '') {
            return '';
        }

        if (strpos($stored_value, $this->encryption_prefix) !== 0) {
            return $stored_value;
        }

        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        $encoded = substr($stored_value, strlen($this->encryption_prefix));
        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            return '';
        }

        $key = hash('sha256', wp_salt('auth') . '|' . wp_salt('secure_auth'), true);
        $cipher = 'aes-256-cbc';
        $iv_length = openssl_cipher_iv_length($cipher);

        if ($iv_length < 1 || strlen($decoded) <= ($iv_length + 32)) {
            return '';
        }

        $iv = substr($decoded, 0, $iv_length);
        $mac = substr($decoded, $iv_length, 32);
        $ciphertext = substr($decoded, $iv_length + 32);
        $calculated_mac = hash_hmac('sha256', $iv . $ciphertext, $key, true);

        if (!hash_equals($mac, $calculated_mac)) {
            return '';
        }

        $plain_text = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        return $plain_text !== false ? $plain_text : '';
    }

    private function load_dependencies() {
        $this->loader = new NexDine_Loader();
    }

    private function set_locale() {
        $plugin_i18n = new NexDine_i18n();

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks() {
        $plugin_admin = new NexDine_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_vapi_settings');
        $this->loader->add_action('wp_ajax_nexdine_test_vapi_connection', $plugin_admin, 'test_vapi_connection');
        $this->loader->add_action('admin_post_nexdine_sync_vapi_assistant', $plugin_admin, 'handle_sync_vapi_assistant');
    }

    private function define_public_hooks() {
        $plugin_public = new NexDine_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }
}
