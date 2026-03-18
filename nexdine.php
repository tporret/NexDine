<?php
/**
 * Plugin Name: NexDine
 * Plugin URI: https://github.com/tporret/NexDine
 * Description: Core plugin bootstrap for NexDine.
 * Version: 0.1.0
 * Author: NexDine
 * Author URI: https://github.com/tporret
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nexdine
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NEXDINE_VERSION', '0.1.0');
define('NEXDINE_PLUGIN_FILE', __FILE__);
define('NEXDINE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('NEXDINE_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once NEXDINE_PLUGIN_PATH . 'includes/class-nexdine-activator.php';
require_once NEXDINE_PLUGIN_PATH . 'includes/class-nexdine-deactivator.php';

function activate_nexdine() {
    NexDine_Activator::activate();
}

function deactivate_nexdine() {
    NexDine_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_nexdine');
register_deactivation_hook(__FILE__, 'deactivate_nexdine');

require_once NEXDINE_PLUGIN_PATH . 'includes/class-nexdine.php';

function run_nexdine() {
    $plugin = new NexDine();
    $plugin->run();
}

run_nexdine();
