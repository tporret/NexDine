<?php

if (!defined('ABSPATH')) {
    exit;
}

class NexDine_Activator {
    public static function activate() {
        if (!get_option('nexdine_version')) {
            add_option('nexdine_version', NEXDINE_VERSION);
        } else {
            update_option('nexdine_version', NEXDINE_VERSION);
        }
    }
}
