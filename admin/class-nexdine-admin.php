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
    private $assistant_dashboard_slug;
    private $assistant_daily_option_name;
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
        $this->assistant_dashboard_slug = 'nexdine-vapi-assistant-dashboard';
        $this->assistant_daily_option_name = 'nexdine_vapi_daily_sync';
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
                'tests' => array(
                    'vapi' => array(
                        'nonce' => wp_create_nonce('nexdine_test_vapi_connection'),
                        'action' => 'nexdine_test_vapi_connection',
                    ),
                    'googleCalendar' => array(
                        'nonce' => wp_create_nonce('nexdine_test_google_calendar_setting'),
                        'action' => 'nexdine_test_google_calendar_setting',
                    ),
                ),
                'labels' => array(
                    'vapiTesting' => __('Testing Vapi connection...', 'nexdine'),
                    'vapiSuccessPrefix' => __('Connected to Vapi successfully. HTTP status:', 'nexdine'),
                    'vapiErrorPrefix' => __('Vapi connection failed:', 'nexdine'),
                    'googleCalendarTesting' => __('Testing Google Calendar settings...', 'nexdine'),
                    'googleCalendarSuccessPrefix' => __('Google Calendar settings look valid. HTTP status:', 'nexdine'),
                    'googleCalendarErrorPrefix' => __('Google Calendar test failed:', 'nexdine'),
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

        add_submenu_page(
            $this->agents_page_slug,
            __('Vapi Assistant Dashboard', 'nexdine'),
            __('Vapi Assistant', 'nexdine'),
            'manage_options',
            $this->assistant_dashboard_slug,
            array($this, 'render_vapi_assistant_dashboard_page')
        );
    }

    public function render_vapi_assistant_dashboard_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'analytics';

        if (!in_array($active_tab, array('analytics', 'sync'), true)) {
            $active_tab = 'analytics';
        }

        $analytics = $this->get_vapi_reservation_analytics_data();
        $daily_settings = $this->get_daily_sync_settings();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Vapi Assistant Dashboard', 'nexdine'); ?></h1>
            <?php $this->render_assistant_dashboard_notices(); ?>

            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->assistant_dashboard_slug . '&tab=analytics')); ?>" class="nav-tab <?php echo $active_tab === 'analytics' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Analytics Dashboard', 'nexdine'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->assistant_dashboard_slug . '&tab=sync')); ?>" class="nav-tab <?php echo $active_tab === 'sync' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('AI Configuration & Menu Sync', 'nexdine'); ?>
                </a>
            </h2>

            <?php if ($active_tab === 'analytics') : ?>
                <?php $this->render_analytics_tab($analytics); ?>
            <?php else : ?>
                <?php $this->render_sync_tab($daily_settings); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_analytics_tab($analytics) {
        ?>
        <h2><?php echo esc_html__('At a Glance', 'nexdine'); ?></h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;max-width:980px;">
            <div class="card">
                <h3><?php echo esc_html__('Total Calls (Today)', 'nexdine'); ?></h3>
                <p><strong><?php echo esc_html((string) $analytics['total_calls_today']); ?></strong></p>
            </div>
            <div class="card">
                <h3><?php echo esc_html__('Confirmed Reservations', 'nexdine'); ?></h3>
                <p><strong><?php echo esc_html((string) $analytics['confirmed_today']); ?></strong></p>
            </div>
            <div class="card">
                <h3><?php echo esc_html__('Missed Calls', 'nexdine'); ?></h3>
                <p><strong><?php echo esc_html((string) $analytics['missed_today']); ?></strong></p>
            </div>
        </div>

        <h2><?php echo esc_html__('Daily Brief (Vapi Metrics)', 'nexdine'); ?></h2>
        <table class="widefat striped" style="max-width:980px;">
            <tbody>
                <tr>
                    <th scope="row" style="width:260px;"><?php echo esc_html__('Total Cost (Today)', 'nexdine'); ?></th>
                    <td><?php echo esc_html('$' . number_format((float) $analytics['cost_today'], 2)); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Top endedReason (Today)', 'nexdine'); ?></th>
                    <td><?php echo esc_html($analytics['top_ended_reason']); ?></td>
                </tr>
            </tbody>
        </table>

        <h2><?php echo esc_html__('Recent Reservation Calls', 'nexdine'); ?></h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Date', 'nexdine'); ?></th>
                    <th scope="col"><?php echo esc_html__('Customer', 'nexdine'); ?></th>
                    <th scope="col"><?php echo esc_html__('Party Size', 'nexdine'); ?></th>
                    <th scope="col"><?php echo esc_html__('Reservation Time', 'nexdine'); ?></th>
                    <th scope="col"><?php echo esc_html__('Call SID', 'nexdine'); ?></th>
                    <th scope="col"><?php echo esc_html__('cost', 'nexdine'); ?></th>
                    <th scope="col"><?php echo esc_html__('endedReason', 'nexdine'); ?></th>
                    <th scope="col"><?php echo esc_html__('Play Recording', 'nexdine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($analytics['recent_rows'])) : ?>
                    <?php foreach ($analytics['recent_rows'] as $row) : ?>
                        <tr>
                            <td><?php echo esc_html($row['date']); ?></td>
                            <td><?php echo esc_html($row['customer_name']); ?></td>
                            <td><?php echo esc_html($row['party_size']); ?></td>
                            <td><?php echo esc_html($row['time']); ?></td>
                            <td><code><?php echo esc_html($row['call_sid']); ?></code></td>
                            <td><?php echo esc_html($row['cost']); ?></td>
                            <td><?php echo esc_html($row['ended_reason']); ?></td>
                            <td>
                                <?php if ($row['recording_url'] !== '') : ?>
                                    <audio controls preload="none" style="max-width:260px;">
                                        <source src="<?php echo esc_url($row['recording_url']); ?>" type="audio/mpeg" />
                                    </audio>
                                <?php else : ?>
                                    <?php echo esc_html__('N/A', 'nexdine'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="8"><?php echo esc_html__('No vapi_reservation entries found.', 'nexdine'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_sync_tab($daily_settings) {
        $settings = $this->get_vapi_settings();
        $assistant_id = isset($settings['assistant_id']) ? sanitize_text_field((string) $settings['assistant_id']) : '';
        ?>
        <p><?php echo esc_html__('Use this tab to update your Vapi assistant prompt instantly from daily restaurant operations.', 'nexdine'); ?></p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('nexdine_sync_vapi_assistant', 'nexdine_sync_nonce'); ?>
            <input type="hidden" name="action" value="nexdine_sync_vapi_assistant" />

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="nexdine_daily_special"><?php echo esc_html__('Daily Special', 'nexdine'); ?></label>
                        </th>
                        <td>
                            <textarea class="large-text" rows="6" id="nexdine_daily_special" name="daily_special" placeholder="<?php echo esc_attr__('Example: Truffle Pasta and grilled branzino tonight.', 'nexdine'); ?>"><?php echo esc_textarea($daily_settings['daily_special']); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nexdine_sold_out_items"><?php echo esc_html__('Sold Out Items', 'nexdine'); ?></label>
                        </th>
                        <td>
                            <input class="regular-text" type="text" id="nexdine_sold_out_items" name="sold_out_items" value="<?php echo esc_attr($daily_settings['sold_out_items']); ?>" placeholder="<?php echo esc_attr__('Example: Lobster Ravioli, Tiramisu', 'nexdine'); ?>" />
                            <p class="description"><?php echo esc_html__('Comma-separated values are recommended.', 'nexdine'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Assistant ID', 'nexdine'); ?></th>
                        <td>
                            <code><?php echo esc_html($assistant_id !== '' ? $assistant_id : __('Not configured in Vapi Settings.', 'nexdine')); ?></code>
                            <p class="description"><?php echo esc_html__('This sync uses the Default Assistant ID from NexDine Vapi Settings.', 'nexdine'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button(__('Sync to AI', 'nexdine'), 'primary', 'submit', false); ?>
        </form>
        <?php
    }

    public function handle_sync_vapi_assistant() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'nexdine'));
        }

        check_admin_referer('nexdine_sync_vapi_assistant', 'nexdine_sync_nonce');

        $daily_special = isset($_POST['daily_special']) ? sanitize_textarea_field(wp_unslash($_POST['daily_special'])) : '';
        $sold_out_items = isset($_POST['sold_out_items']) ? sanitize_text_field(wp_unslash($_POST['sold_out_items'])) : '';

        update_option(
            $this->assistant_daily_option_name,
            array(
                'daily_special' => $daily_special,
                'sold_out_items' => $sold_out_items,
            )
        );

        $settings = $this->get_vapi_settings();
        $assistant_id = isset($settings['assistant_id']) ? sanitize_text_field((string) $settings['assistant_id']) : '';
        $encrypted_private_key = isset($settings['private_key']) ? (string) $settings['private_key'] : '';
        $private_key = trim($this->decrypt_secret($encrypted_private_key));

        if ($assistant_id === '' || $private_key === '') {
            $this->redirect_assistant_dashboard('sync', 'missing_credentials');
        }

        $assistant_response = wp_remote_get(
            'https://api.vapi.ai/assistant/' . rawurlencode($assistant_id),
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $private_key,
                    'Accept' => 'application/json',
                ),
            )
        );

        if (is_wp_error($assistant_response)) {
            $this->redirect_assistant_dashboard('sync', 'sync_failed', $assistant_response->get_error_message());
        }

        $assistant_status = wp_remote_retrieve_response_code($assistant_response);
        $assistant_body = wp_remote_retrieve_body($assistant_response);
        $assistant_data = json_decode($assistant_body, true);

        if ($assistant_status < 200 || $assistant_status >= 300 || !is_array($assistant_data)) {
            $message = is_array($assistant_data) ? $this->extract_vapi_error_message($assistant_data) : __('Unable to read assistant data from Vapi.', 'nexdine');
            $this->redirect_assistant_dashboard('sync', 'sync_failed', $message);
        }

        if (
            !isset($assistant_data['model']) ||
            !is_array($assistant_data['model']) ||
            !isset($assistant_data['model']['messages']) ||
            !is_array($assistant_data['model']['messages']) ||
            empty($assistant_data['model']['messages'][0]) ||
            !is_array($assistant_data['model']['messages'][0])
        ) {
            $this->redirect_assistant_dashboard('sync', 'sync_failed', __('Assistant prompt format is not supported.', 'nexdine'));
        }

        $existing_prompt = isset($assistant_data['model']['messages'][0]['content']) ? (string) $assistant_data['model']['messages'][0]['content'] : '';
        $daily_data_block = $this->build_daily_data_prompt_block($daily_special, $sold_out_items);

        if (strpos($existing_prompt, '{{DAILY_DATA}}') !== false) {
            $new_prompt = str_replace('{{DAILY_DATA}}', $daily_data_block, $existing_prompt);
        } else {
            $new_prompt = trim($daily_data_block . "\n\n" . $existing_prompt);
        }

        $messages = $assistant_data['model']['messages'];
        $messages[0]['content'] = $new_prompt;

        $patch_response = wp_remote_request(
            'https://api.vapi.ai/assistant/' . rawurlencode($assistant_id),
            array(
                'method' => 'PATCH',
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $private_key,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(
                    array(
                        'model' => array(
                            'messages' => $messages,
                        ),
                    )
                ),
            )
        );

        if (is_wp_error($patch_response)) {
            $this->redirect_assistant_dashboard('sync', 'sync_failed', $patch_response->get_error_message());
        }

        $patch_status = wp_remote_retrieve_response_code($patch_response);
        $patch_body = wp_remote_retrieve_body($patch_response);
        $patch_data = json_decode($patch_body, true);

        if ($patch_status < 200 || $patch_status >= 300) {
            $message = is_array($patch_data) ? $this->extract_vapi_error_message($patch_data) : __('Vapi returned an error while updating assistant.', 'nexdine');
            $this->redirect_assistant_dashboard('sync', 'sync_failed', $message);
        }

        $this->redirect_assistant_dashboard('sync', 'sync_success');
    }

    private function get_vapi_reservation_analytics_data() {
        $today_query = new WP_Query(
            array(
                'post_type' => 'vapi_reservation',
                'post_status' => 'any',
                'posts_per_page' => 200,
                'fields' => 'ids',
                'date_query' => array(
                    array(
                        'after' => 'today',
                        'inclusive' => true,
                    ),
                ),
                'no_found_rows' => true,
            )
        );

        $total_calls_today = 0;
        $confirmed_today = 0;
        $missed_today = 0;
        $cost_today = 0.0;
        $ended_reason_counts = array();

        if (!empty($today_query->posts)) {
            foreach ($today_query->posts as $post_id) {
                $total_calls_today++;

                $status_value = strtolower($this->read_first_meta($post_id, array('reservation_status', 'status', 'call_status')));
                $ended_reason = $this->read_first_meta($post_id, array('endedReason', 'ended_reason'));
                $ended_reason_key = strtolower($ended_reason);
                $customer_name = $this->read_first_meta($post_id, array('customer_name'));
                $time_value = $this->read_first_meta($post_id, array('time', 'reservation_time'));
                $party_size = $this->read_first_meta($post_id, array('party_size'));
                $cost_value = (float) $this->read_first_meta($post_id, array('cost', 'call_cost'));

                $cost_today += $cost_value;

                if ($ended_reason_key !== '') {
                    if (!isset($ended_reason_counts[$ended_reason_key])) {
                        $ended_reason_counts[$ended_reason_key] = 0;
                    }

                    $ended_reason_counts[$ended_reason_key]++;
                }

                $is_confirmed = in_array($status_value, array('confirmed', 'booked', 'success', 'completed'), true);

                if (!$is_confirmed) {
                    $is_confirmed = ($customer_name !== '' && $time_value !== '' && $party_size !== '');
                }

                $is_missed = in_array($status_value, array('missed', 'no-answer', 'failed', 'dropped'), true)
                    || in_array($ended_reason_key, array('assistant-error', 'assistant-error-openai-failed', 'no-answer', 'hung-up'), true);

                if ($is_missed) {
                    $missed_today++;
                } elseif ($is_confirmed) {
                    $confirmed_today++;
                }
            }
        }

        $top_ended_reason = __('N/A', 'nexdine');

        if (!empty($ended_reason_counts)) {
            arsort($ended_reason_counts);
            $reason_keys = array_keys($ended_reason_counts);
            $top_ended_reason = isset($reason_keys[0]) ? (string) $reason_keys[0] : __('N/A', 'nexdine');
        }

        $recent_query = new WP_Query(
            array(
                'post_type' => 'vapi_reservation',
                'post_status' => 'any',
                'posts_per_page' => 10,
                'orderby' => 'date',
                'order' => 'DESC',
                'no_found_rows' => true,
            )
        );

        $recent_rows = array();

        if (!empty($recent_query->posts)) {
            foreach ($recent_query->posts as $post) {
                $post_id = (int) $post->ID;
                $cost = (float) $this->read_first_meta($post_id, array('cost', 'call_cost'));
                $cost_display = $cost > 0 ? ('$' . number_format($cost, 2)) : __('N/A', 'nexdine');

                $recent_rows[] = array(
                    'date' => wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime((string) $post->post_date)),
                    'customer_name' => $this->format_dashboard_value($this->read_first_meta($post_id, array('customer_name'))),
                    'party_size' => $this->format_dashboard_value($this->read_first_meta($post_id, array('party_size'))),
                    'time' => $this->format_dashboard_value($this->read_first_meta($post_id, array('time', 'reservation_time'))),
                    'call_sid' => $this->format_dashboard_value($this->read_first_meta($post_id, array('call_sid'))),
                    'cost' => $cost_display,
                    'ended_reason' => $this->format_dashboard_value($this->read_first_meta($post_id, array('endedReason', 'ended_reason'))),
                    'recording_url' => $this->read_first_meta($post_id, array('recordingUrl', 'recording_url')),
                );
            }
        }

        wp_reset_postdata();

        return array(
            'total_calls_today' => $total_calls_today,
            'confirmed_today' => $confirmed_today,
            'missed_today' => $missed_today,
            'cost_today' => $cost_today,
            'top_ended_reason' => $top_ended_reason,
            'recent_rows' => $recent_rows,
        );
    }

    private function read_first_meta($post_id, $meta_keys) {
        foreach ((array) $meta_keys as $meta_key) {
            $value = get_post_meta($post_id, $meta_key, true);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return sanitize_text_field((string) $value);
            }
        }

        return '';
    }

    private function format_dashboard_value($value) {
        $value = trim((string) $value);

        return $value !== '' ? $value : __('N/A', 'nexdine');
    }

    private function get_daily_sync_settings() {
        $saved = get_option($this->assistant_daily_option_name, array());
        $saved = is_array($saved) ? $saved : array();

        return array(
            'daily_special' => isset($saved['daily_special']) ? sanitize_textarea_field((string) $saved['daily_special']) : '',
            'sold_out_items' => isset($saved['sold_out_items']) ? sanitize_text_field((string) $saved['sold_out_items']) : '',
        );
    }

    private function build_daily_data_prompt_block($daily_special, $sold_out_items) {
        $special = $daily_special !== '' ? $daily_special : __('None listed.', 'nexdine');
        $sold_out = $sold_out_items !== '' ? $sold_out_items : __('None listed.', 'nexdine');

        return "DAILY RESTAURANT DATA:\n"
            . "Daily Special: {$special}\n"
            . "Sold Out Items: {$sold_out}";
    }

    private function render_assistant_dashboard_notices() {
        $status = isset($_GET['sync_status']) ? sanitize_key(wp_unslash($_GET['sync_status'])) : '';
        $message = isset($_GET['sync_message']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['sync_message']))) : '';

        if ($status === 'sync_success') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Vapi assistant prompt synced successfully.', 'nexdine') . '</p></div>';
            return;
        }

        if ($status === 'missing_credentials') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Sync failed: assistant ID or private key is missing. Configure NexDine Vapi Settings first.', 'nexdine') . '</p></div>';
            return;
        }

        if ($status === 'sync_failed') {
            $base = __('Sync failed while updating Vapi assistant.', 'nexdine');

            if ($message !== '') {
                $base .= ' ' . $message;
            }

            echo '<div class="notice notice-error"><p>' . esc_html($base) . '</p></div>';
        }
    }

    private function redirect_assistant_dashboard($tab, $status, $message = '') {
        $args = array(
            'page' => $this->assistant_dashboard_slug,
            'tab' => $tab,
            'sync_status' => $status,
        );

        if ($message !== '') {
            $args['sync_message'] = rawurlencode($message);
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
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
            'google_calendar_id',
            __('Google Calendar ID to Sync', 'nexdine'),
            array($this, 'render_text_field'),
            $this->vapi_page_slug,
            'nexdine_vapi_account_section',
            array(
                'field_key' => 'google_calendar_id',
                'placeholder' => __('primary or restaurant@example.com', 'nexdine'),
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
        $sanitized['google_calendar_id'] = isset($input['google_calendar_id']) ? sanitize_text_field(wp_unslash($input['google_calendar_id'])) : '';
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

            <hr />

            <h2><?php echo esc_html__('Google Calendar Check', 'nexdine'); ?></h2>
            <p><?php echo esc_html__('Use this to verify your Google Calendar ID is configured for middleware sync.', 'nexdine'); ?></p>
            <button id="nexdine-test-google-calendar" class="button button-secondary" type="button">
                <?php echo esc_html__('Test Google Calendar', 'nexdine'); ?>
            </button>
            <p id="nexdine-test-google-calendar-result" class="description" aria-live="polite"></p>
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

    public function test_google_calendar_setting() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You are not allowed to perform this action.', 'nexdine')), 403);
        }

        check_ajax_referer('nexdine_test_google_calendar_setting', 'nonce');

        $settings = $this->get_vapi_settings();
        $calendar_id = isset($settings['google_calendar_id']) ? sanitize_text_field((string) $settings['google_calendar_id']) : '';

        if ($calendar_id === '') {
            wp_send_json_error(array('message' => __('No Google Calendar ID is saved yet.', 'nexdine')), 400);
        }

        if ($calendar_id !== 'primary' && !preg_match('/^[a-zA-Z0-9._@-]+$/', $calendar_id)) {
            wp_send_json_error(array('message' => __('Google Calendar ID format appears invalid. Use primary or a valid calendar ID/email.', 'nexdine')), 400);
        }

        wp_send_json_success(
            array(
                'message' => __('Google Calendar ID is configured and ready for middleware sync.', 'nexdine'),
                'status_code' => 200,
                'calendar_id' => $calendar_id,
            ),
            200
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
                'nexdine_page_' . $this->assistant_dashboard_slug,
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
