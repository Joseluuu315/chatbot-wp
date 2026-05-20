<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('aicor_cbn8n_options');
delete_option('aicor_cbn8n_tables_pending');
