<?php
/**
 * Plugin Name: Chatbot WP n8n
 * Description: Integra un chatbot flotante conectado a workflows de n8n, con proxy REST, ajustes visuales y almacenamiento local opcional.
 * Version: 1.0.2
 * Author: Aicor
 * Text Domain: chatbot-wp-n8n
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('CWP_N8N_STANDALONE_VERSION')) {
    define('CWP_N8N_STANDALONE_VERSION', '1.0.2');
}

if (!defined('CWP_N8N_STANDALONE_FILE')) {
    define('CWP_N8N_STANDALONE_FILE', __FILE__);
}

if (!defined('CWP_N8N_STANDALONE_PATH')) {
    define('CWP_N8N_STANDALONE_PATH', plugin_dir_path(__FILE__));
}

if (!defined('CWP_N8N_STANDALONE_URL')) {
    define('CWP_N8N_STANDALONE_URL', plugin_dir_url(__FILE__));
}

require_once CWP_N8N_STANDALONE_PATH . 'includes/class-chatbot-wp-n8n.php';

if (class_exists('Chatbot_WP_N8N_Standalone')) {
    register_activation_hook(__FILE__, array('Chatbot_WP_N8N_Standalone', 'activate'));
}

if (!function_exists('cwp_n8n_standalone_bootstrap')) {
    function cwp_n8n_standalone_bootstrap()
    {
        if (class_exists('Chatbot_WP_N8N_Standalone')) {
            Chatbot_WP_N8N_Standalone::instance();
        }
    }
}

add_action('plugins_loaded', 'cwp_n8n_standalone_bootstrap');
