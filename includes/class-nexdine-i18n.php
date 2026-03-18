<?php

if (!defined('ABSPATH')) {
    exit;
}

class NexDine_i18n {
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'nexdine',
            false,
            dirname(plugin_basename(NEXDINE_PLUGIN_FILE)) . '/languages/'
        );
    }
}
