<?php

if (!defined('ABSPATH')) {
    exit;
}

class NexDine_Admin {
    private $plugin_name;
    private $version;
    private $vapi_option_name;
    private $vapi_settings_group;
    private $vapi_page_slug;
    private $agents_page_slug;
    private $agents_cache_key;
    private $agents_cache_ttl;
    private $encryption_prefix;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->vapi_option_name = 'nexdine_vapi_settings';
        $this->vapi_settings_group = 'nexdine_vapi_settings_group';
        $this->vapi_page_slug = 'nexdine-vapi-settings';
        $this->agents_page_slug = 'nexdine-agents';
        $this->agents_cache_key = 'nexdine_vapi_agents_cache';
        $this->agents_cache_ttl = HOUR_IN_SECONDS;
        $this->encryption_prefix = 'nexdine_enc_v1:';
    }

    public function enqueue_styles($hook_suffix) {
        if (!$this->is_nexdine_admin_page($hook_suffix)) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name . '-admin',
            NEXDINE_PLUGIN_URL . 'admin/css/nexdine-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    public function enqueue_scripts($hook_suffix) {
        if (!$this->is_vapi_settings_page($hook_suffix)) {
            return;
        }

        $handle = $this->plugin_name . '-admin';

        wp_enqueue_script(
            $handle,
            NEXDINE_PLUGIN_URL . 'admin/js/nexdine-admin.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script(
            $handle,
            'nexdineAdmin',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nexdine_test_vapi_connection'),
                'action' => 'nexdine_test_vapi_connection',
                'labels' => array(
                    'testing' => __('Testing connection...', 'nexdine'),
                    'successPrefix' => __('Connected to Vapi successfully. HTTP status:', 'nexdine'),
                    'errorPrefix' => __('Connection failed:', 'nexdine'),
                ),
            )
        );
    }

    public function add_plugin_admin_menu() {
        add_menu_page(
            __('NexDine', 'nexdine'),
            __('NexDine', 'nexdine'),
            'manage_options',
            $this->agents_page_slug,
            array($this, 'render_agents_page'),
            'dashicons-microphone',
            58
        );

        add_submenu_page(
            $this->agents_page_slug,
            __('Agents', 'nexdine'),
            __('Agents', 'nexdine'),
            'manage_options',
            $this->agents_page_slug,
            array($this, 'render_agents_page')
        );

        add_submenu_page(
            $this->agents_page_slug,
            __('Vapi Settings', 'nexdine'),
            __('Vapi Settings', 'nexdine'),
            'manage_options',
            $this->vapi_page_slug,
            array($this, 'render_vapi_settings_page')
        );
    }

    public function render_agents_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_vapi_settings();
        $encrypted_private_key = isset($settings['private_key']) ? $settings['private_key'] : '';
        $private_key = trim($this->decrypt_secret($encrypted_private_key));
        $settings_url = admin_url('admin.php?page=' . $this->vapi_page_slug);
        $refresh_url = wp_nonce_url(
            add_query_arg(
                array(
                    'page' => $this->agents_page_slug,
                    'nexdine_refresh_agents' => '1',
                ),
                admin_url('admin.php')
            ),
            'nexdine_refresh_agents'
        );
        $agents = array();
        $api_error = '';
        $force_refresh = isset($_GET['nexdine_refresh_agents']) && wp_verify_nonce(
            isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '',
            'nexdine_refresh_agents'
        );
        $cache_used = false;

        if ($private_key === '') {
            $api_error = __('No Vapi private key is saved yet.', 'nexdine');
        } else {
            if (!$force_refresh) {
                $cached = get_transient($this->agents_cache_key);

                if (is_array($cached)) {
                    $agents = isset($cached['agents']) && is_array($cached['agents']) ? $cached['agents'] : array();
                    $api_error = isset($cached['api_error']) && is_string($cached['api_error']) ? $cached['api_error'] : '';
                    $cache_used = true;
                }
            }

            if (!$cache_used || $force_refresh) {
                $response_data = $this->fetch_vapi_agents($private_key);
                $agents = isset($response_data['agents']) ? $response_data['agents'] : array();
                $api_error = isset($response_data['api_error']) ? $response_data['api_error'] : '';

                set_transient(
                    $this->agents_cache_key,
                    array(
                        'agents' => $agents,
                        'api_error' => $api_error,
                    ),
                    $this->agents_cache_ttl
                );
            }
        }
        ?>
        <div class="wrap nexdine-settings-wrap">
            <h1><?php echo esc_html__('NexDine Agents', 'nexdine'); ?></h1>
            <p><?php echo esc_html__('This page shows live Vapi assistants available to your saved account key.', 'nexdine'); ?></p>

            <p>
                <a href="<?php echo esc_url($refresh_url); ?>" class="button button-secondary">
                    <?php echo esc_html__('Refresh Agents', 'nexdine'); ?>
                </a>
                <span class="description" style="margin-left:8px;">
                    <?php echo esc_html__('Agent results are cached for 1 hour.', 'nexdine'); ?>
                </span>
            </p>

            <?php if ($force_refresh && $private_key !== '') : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php echo esc_html__('Agents list refreshed from Vapi.', 'nexdine'); ?>
                </p></div>
            <?php endif; ?>

            <?php if ($private_key === '') : ?>
                <div class="notice notice-warning"><p>
                    <?php
                    printf(
                        esc_html__('To view agents, add your Vapi Private Key in %s.', 'nexdine'),
                        '<a href="' . esc_url($settings_url) . '">' . esc_html__('Vapi Settings', 'nexdine') . '</a>'
                    );
                    ?>
                </p></div>
            <?php elseif ($api_error !== '') : ?>
                <div class="notice notice-error"><p>
                    <?php
                    echo esc_html__('We could not load agents from Vapi.', 'nexdine') . ' ' . esc_html($api_error);
                    ?>
                </p></div>
            <?php endif; ?>

            <?php if (!empty($agents)) : ?>
                <table class="widefat fixed striped" style="max-width: 1100px;">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo esc_html__('Name', 'nexdine'); ?></th>
                            <th scope="col"><?php echo esc_html__('Assistant ID', 'nexdine'); ?></th>
                            <th scope="col"><?php echo esc_html__('Created', 'nexdine'); ?></th>
                            <th scope="col"><?php echo esc_html__('Updated', 'nexdine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agents as $agent) : ?>
                            <tr>
                                <td><?php echo esc_html($this->agent_display_value($agent, 'name')); ?></td>
                                <td><code><?php echo esc_html($this->agent_display_value($agent, 'id')); ?></code></td>
                                <td><?php echo esc_html($this->format_agent_datetime($this->agent_display_value($agent, 'createdAt'))); ?></td>
                                <td><?php echo esc_html($this->format_agent_datetime($this->agent_display_value($agent, 'updatedAt'))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="notice notice-info"><p>
                    <?php
                    if ($private_key === '') {
                        echo esc_html__('No agents to display yet. Save your Vapi credentials first, then reload this page.', 'nexdine');
                    } elseif ($api_error !== '') {
                        echo esc_html__('No agents could be displayed due to an API error. Check your key and try again.', 'nexdine');
                    } else {
                        echo esc_html__('No agents were returned by Vapi. Create an assistant in your Vapi dashboard, then refresh this page.', 'nexdine');
                    }
                    ?>
                </p></div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function fetch_vapi_agents($private_key) {
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
                'api_error' => $response->get_error_message(),
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'agents' => $this->normalize_agents_response($decoded),
                'api_error' => '',
            );
        }

        return array(
            'agents' => array(),
            'api_error' => $this->extract_vapi_error_message($decoded),
        );
    }

    public function register_vapi_settings() {
        register_setting(
            $this->vapi_settings_group,
            $this->vapi_option_name,
            array($this, 'sanitize_vapi_settings')
        );

        add_settings_section(
            'nexdine_vapi_account_section',
            __('Vapi Account Connection', 'nexdine'),
            array($this, 'render_vapi_section_intro'),
            $this->vapi_page_slug
        );

        add_settings_field(
            'public_key',
            __('Public Key', 'nexdine'),
            array($this, 'render_text_field'),
            $this->vapi_page_slug,
            'nexdine_vapi_account_section',
            array(
                'field_key' => 'public_key',
                'placeholder' => __('pk_xxxxxxxxxxxxxxxxx', 'nexdine'),
            )
        );

        add_settings_field(
            'private_key',
            __('Private Key', 'nexdine'),
            array($this, 'render_password_field'),
            $this->vapi_page_slug,
            'nexdine_vapi_account_section',
            array(
                'field_key' => 'private_key',
                'placeholder' => __('sk_xxxxxxxxxxxxxxxxx', 'nexdine'),
            )
        );

        add_settings_field(
            'assistant_id',
            __('Default Assistant ID', 'nexdine'),
            array($this, 'render_text_field'),
            $this->vapi_page_slug,
            'nexdine_vapi_account_section',
            array(
                'field_key' => 'assistant_id',
                'placeholder' => __('asst_xxxxxxxxxxxxxxxxx', 'nexdine'),
            )
        );

        add_settings_field(
            'assistant_ids',
            __('Additional Assistant IDs', 'nexdine'),
            array($this, 'render_textarea_field'),
            $this->vapi_page_slug,
            'nexdine_vapi_account_section',
            array(
                'field_key' => 'assistant_ids',
                'placeholder' => "asst_xxxxxxxxxxxxxxxxx\nasst_yyyyyyyyyyyyyyyyy",
                'description' => __('Add one assistant ID per line. These IDs are merged into the block selector.', 'nexdine'),
            )
        );

        add_settings_field(
            'phone_number_id',
            __('Phone Number ID', 'nexdine'),
            array($this, 'render_text_field'),
            $this->vapi_page_slug,
            'nexdine_vapi_account_section',
            array(
                'field_key' => 'phone_number_id',
                'placeholder' => __('pn_xxxxxxxxxxxxxxxxx', 'nexdine'),
            )
        );

        add_settings_field(
            'webhook_secret',
            __('Webhook Secret', 'nexdine'),
            array($this, 'render_password_field'),
            $this->vapi_page_slug,
            'nexdine_vapi_account_section',
            array(
                'field_key' => 'webhook_secret',
                'placeholder' => __('Optional webhook signing secret', 'nexdine'),
            )
        );
    }

    public function sanitize_vapi_settings($input) {
        $current = $this->get_vapi_settings();
        $sanitized = array();

        $sanitized['public_key'] = isset($input['public_key']) ? sanitize_text_field(wp_unslash($input['public_key'])) : '';
        $sanitized['assistant_id'] = isset($input['assistant_id']) ? sanitize_text_field(wp_unslash($input['assistant_id'])) : '';
        $sanitized['phone_number_id'] = isset($input['phone_number_id']) ? sanitize_text_field(wp_unslash($input['phone_number_id'])) : '';
        $sanitized['assistant_ids'] = $this->sanitize_assistant_ids(isset($input['assistant_ids']) ? wp_unslash($input['assistant_ids']) : '');

        $private_key = isset($input['private_key']) ? sanitize_text_field(wp_unslash($input['private_key'])) : '';
        $webhook_secret = isset($input['webhook_secret']) ? sanitize_text_field(wp_unslash($input['webhook_secret'])) : '';

        if ($private_key !== '') {
            $sanitized['private_key'] = $this->encrypt_secret($private_key);
        } else {
            $existing_private_key = isset($current['private_key']) ? $current['private_key'] : '';
            if ($existing_private_key !== '' && !$this->is_encrypted_value($existing_private_key)) {
                $sanitized['private_key'] = $this->encrypt_secret($existing_private_key);
            } else {
                $sanitized['private_key'] = $existing_private_key;
            }
        }

        $sanitized['webhook_secret'] = $webhook_secret !== '' ? $webhook_secret : (isset($current['webhook_secret']) ? $current['webhook_secret'] : '');

        add_settings_error(
            $this->vapi_option_name,
            'nexdine_vapi_saved',
            __('Vapi account settings saved.', 'nexdine'),
            'updated'
        );

        return $sanitized;
    }

    public function render_vapi_section_intro() {
        echo '<p>' . esc_html__('Add your Vapi credentials to enable NexDine AI voice answering. Private fields keep existing saved values when left blank.', 'nexdine') . '</p>';
    }

    public function render_text_field($args) {
        $settings = $this->get_vapi_settings();
        $field_key = $args['field_key'];
        $value = isset($settings[$field_key]) ? $settings[$field_key] : '';
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';

        printf(
            '<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s" autocomplete="off" />',
            esc_attr($this->vapi_option_name),
            esc_attr($field_key),
            esc_attr($value),
            esc_attr($placeholder)
        );
    }

    public function render_password_field($args) {
        $settings = $this->get_vapi_settings();
        $field_key = $args['field_key'];
        $has_saved_value = !empty($settings[$field_key]);
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';

        printf(
            '<input type="password" class="regular-text" name="%1$s[%2$s]" value="" placeholder="%3$s" autocomplete="new-password" />',
            esc_attr($this->vapi_option_name),
            esc_attr($field_key),
            esc_attr($placeholder)
        );

        if ($has_saved_value) {
            echo '<p class="description">' . esc_html__('A value is already saved. Leave blank to keep the current value.', 'nexdine') . '</p>';
        }
    }

    public function render_textarea_field($args) {
        $settings = $this->get_vapi_settings();
        $field_key = $args['field_key'];
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        $description = isset($args['description']) ? $args['description'] : '';
        $value = '';

        if (isset($settings[$field_key])) {
            if (is_array($settings[$field_key])) {
                $value = implode("\n", $settings[$field_key]);
            } elseif (is_string($settings[$field_key])) {
                $value = $settings[$field_key];
            }
        }

        printf(
            '<textarea class="large-text code" rows="6" name="%1$s[%2$s]" placeholder="%3$s">%4$s</textarea>',
            esc_attr($this->vapi_option_name),
            esc_attr($field_key),
            esc_attr($placeholder),
            esc_textarea($value)
        );

        if ($description !== '') {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    private function sanitize_assistant_ids($value) {
        if (is_array($value)) {
            $raw = $value;
        } else {
            $raw = preg_split('/[\r\n,]+/', (string) $value);
        }

        $ids = array();

        foreach ((array) $raw as $item) {
            $clean = sanitize_text_field(trim((string) $item));

            if ($clean !== '') {
                $ids[] = $clean;
            }
        }

        return array_values(array_unique($ids));
    }

    public function render_vapi_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_vapi_settings();
        $is_connected = !empty($settings['public_key']) && !empty($settings['private_key']);
        ?>
        <div class="wrap nexdine-settings-wrap">
            <h1><?php echo esc_html__('NexDine AI Voice Settings', 'nexdine'); ?></h1>
            <p class="nexdine-status <?php echo $is_connected ? 'is-connected' : 'is-not-connected'; ?>">
                <?php echo $is_connected
                    ? esc_html__('Vapi account credentials are configured.', 'nexdine')
                    : esc_html__('Vapi account is not configured yet.', 'nexdine'); ?>
            </p>

            <?php settings_errors($this->vapi_option_name); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields($this->vapi_settings_group);
                do_settings_sections($this->vapi_page_slug);
                submit_button(__('Save Vapi Settings', 'nexdine'));
                ?>
            </form>

            <hr />

            <h2><?php echo esc_html__('Connection Check', 'nexdine'); ?></h2>
            <p><?php echo esc_html__('Use this to verify your saved Vapi private key can access the API right now.', 'nexdine'); ?></p>
            <button id="nexdine-test-vapi-connection" class="button button-secondary" type="button">
                <?php echo esc_html__('Test Connection', 'nexdine'); ?>
            </button>
            <p id="nexdine-test-vapi-result" class="description" aria-live="polite"></p>
        </div>
        <?php
    }

    public function test_vapi_connection() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You are not allowed to perform this action.', 'nexdine')), 403);
        }

        check_ajax_referer('nexdine_test_vapi_connection', 'nonce');

        $settings = $this->get_vapi_settings();
        $encrypted_private_key = isset($settings['private_key']) ? $settings['private_key'] : '';
        $private_key = trim($this->decrypt_secret($encrypted_private_key));

        if ($private_key === '') {
            wp_send_json_error(array('message' => __('No Vapi private key is saved yet.', 'nexdine')), 400);
        }

        $response = wp_remote_get(
            'https://api.vapi.ai/assistant?limit=1',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $private_key,
                    'Accept' => 'application/json',
                ),
            )
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()), 502);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status_code >= 200 && $status_code < 300) {
            wp_send_json_success(
                array(
                    'message' => __('Connected to Vapi successfully.', 'nexdine'),
                    'status_code' => $status_code,
                ),
                200
            );
        }

        $error_message = __('Unexpected response from Vapi.', 'nexdine');

        if (is_array($decoded)) {
            if (!empty($decoded['message']) && is_string($decoded['message'])) {
                $error_message = $decoded['message'];
            } elseif (!empty($decoded['error']) && is_string($decoded['error'])) {
                $error_message = $decoded['error'];
            }
        }

        wp_send_json_error(
            array(
                'message' => $error_message,
                'status_code' => $status_code,
            ),
            $status_code > 0 ? $status_code : 500
        );
    }

    private function get_vapi_settings() {
        $settings = get_option($this->vapi_option_name, array());
        return is_array($settings) ? $settings : array();
    }

    private function normalize_agents_response($decoded) {
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

    private function agent_display_value($agent, $key) {
        if (is_array($agent) && !empty($agent[$key])) {
            return (string) $agent[$key];
        }

        return __('N/A', 'nexdine');
    }

    private function format_agent_datetime($datetime) {
        if (!is_string($datetime) || $datetime === '' || $datetime === __('N/A', 'nexdine')) {
            return __('N/A', 'nexdine');
        }

        $timestamp = strtotime($datetime);

        if ($timestamp === false) {
            return $datetime;
        }

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    private function is_nexdine_admin_page($hook_suffix) {
        return in_array(
            $hook_suffix,
            array(
                'toplevel_page_' . $this->agents_page_slug,
                'nexdine_page_' . $this->vapi_page_slug,
            ),
            true
        );
    }

    private function is_vapi_settings_page($hook_suffix) {
        return $hook_suffix === 'nexdine_page_' . $this->vapi_page_slug;
    }

    private function encrypt_secret($plain_text) {
        if ($plain_text === '') {
            return '';
        }

        if ($this->is_encrypted_value($plain_text)) {
            return $plain_text;
        }

        if (!function_exists('openssl_encrypt')) {
            return $plain_text;
        }

        $key = $this->get_encryption_key();
        $cipher = 'aes-256-cbc';
        $iv_length = openssl_cipher_iv_length($cipher);

        if ($iv_length < 1) {
            return $plain_text;
        }

        $iv = random_bytes($iv_length);
        $ciphertext = openssl_encrypt($plain_text, $cipher, $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            return $plain_text;
        }

        $mac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
        return $this->encryption_prefix . base64_encode($iv . $mac . $ciphertext);
    }

    private function decrypt_secret($stored_value) {
        if ($stored_value === '') {
            return '';
        }

        if (!$this->is_encrypted_value($stored_value)) {
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

        $key = $this->get_encryption_key();
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

    private function is_encrypted_value($value) {
        return is_string($value) && strpos($value, $this->encryption_prefix) === 0;
    }

    private function get_encryption_key() {
        return hash('sha256', wp_salt('auth') . '|' . wp_salt('secure_auth'), true);
    }
}
