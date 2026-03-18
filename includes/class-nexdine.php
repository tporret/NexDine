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

    public function __construct() {
        $this->plugin_name = 'nexdine';
        $this->version = defined('NEXDINE_VERSION') ? NEXDINE_VERSION : '0.1.0';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
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
