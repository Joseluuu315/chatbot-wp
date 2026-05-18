<?php
/**
 * Uninstall cleanup for Chatbot WP n8n.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('cwp_n8n_options');

global $wpdb;

$tables = array(
    $wpdb->prefix . 'cwp_chat_sessions',
    $wpdb->prefix . 'cwp_chat_messages',
    $wpdb->prefix . 'cwp_chat_leads',
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}
