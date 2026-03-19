<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('nexdine_version');
delete_option('nexdine_vapi_settings');
