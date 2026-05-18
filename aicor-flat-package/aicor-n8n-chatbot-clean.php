<?php
/*
Plugin Name: Aicor n8n Chatbot Clean
Description: Chatbot flotante para WordPress conectado a n8n, con panel de conversaciones y leads.
Version: 1.0.1
Author: Aicor
Text Domain: aicor-n8n-chatbot-clean
*/

if (!defined('ABSPATH')) {
    exit;
}

define('AICOR_CBN8N_VERSION', '1.0.0');
define('AICOR_CBN8N_FILE', __FILE__);
define('AICOR_CBN8N_PATH', plugin_dir_path(__FILE__));
define('AICOR_CBN8N_URL', plugin_dir_url(__FILE__));
define('AICOR_CBN8N_OPTION', 'aicor_cbn8n_options');
define('AICOR_CBN8N_REST', 'aicor-chatbot-n8n/v1');

register_activation_hook(__FILE__, 'aicor_cbn8n_activate');

add_action('admin_menu', 'aicor_cbn8n_admin_menu');
add_action('admin_init', 'aicor_cbn8n_register_settings');
add_action('admin_init', 'aicor_cbn8n_install_tables');
add_action('wp_enqueue_scripts', 'aicor_cbn8n_enqueue');
add_action('rest_api_init', 'aicor_cbn8n_rest_routes');

function aicor_cbn8n_defaults()
{
    return array(
        'enabled' => '1',
        'chat_webhook_url' => '',
        'scraper_webhook_url' => '',
        'client_id' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('client_', true),
        'company_name' => get_bloginfo('name'),
        'auth_type' => 'none',
        'auth_token' => '',
        'auth_header_name' => 'X-Chatbot-Token',
        'local_storage_enabled' => '1',
        'title' => 'Asistente virtual',
        'subtitle' => 'Normalmente responde en unos segundos',
        'initial_message' => 'Hola, soy el asistente virtual. En que puedo ayudarte?',
        'placeholder' => 'Escribe tu mensaje...',
        'launcher_label' => 'Chat',
        'position' => 'right',
        'offset_bottom' => '24',
        'offset_side' => '24',
        'panel_width' => '380',
        'panel_height' => '560',
        'primary_color' => '#2563eb',
        'accent_color' => '#16a34a',
        'text_color' => '#111827',
        'surface_color' => '#ffffff'
    );
}

function aicor_cbn8n_get_options()
{
    return wp_parse_args(get_option(AICOR_CBN8N_OPTION, array()), aicor_cbn8n_defaults());
}

function aicor_cbn8n_activate()
{
    if (!get_option(AICOR_CBN8N_OPTION)) {
        add_option(AICOR_CBN8N_OPTION, aicor_cbn8n_defaults());
    }
    update_option('aicor_cbn8n_tables_pending', '1');
}

function aicor_cbn8n_install_tables()
{
    global $wpdb;

    $charset = $wpdb->get_charset_collate();
    $sessions = $wpdb->prefix . 'aicor_cbn8n_sessions';
    $messages = $wpdb->prefix . 'aicor_cbn8n_messages';
    $leads = $wpdb->prefix . 'aicor_cbn8n_leads';

    $wpdb->query("CREATE TABLE IF NOT EXISTS $sessions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(191) NOT NULL,
        client_id VARCHAR(191) NOT NULL DEFAULT '',
        company_name VARCHAR(191) NOT NULL DEFAULT '',
        ip_hash VARCHAR(64) NOT NULL DEFAULT '',
        user_agent VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY session_id (session_id),
        KEY client_id (client_id)
    ) $charset");

    $wpdb->query("CREATE TABLE IF NOT EXISTS $messages (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(191) NOT NULL,
        role VARCHAR(20) NOT NULL,
        content LONGTEXT NOT NULL,
        raw_payload LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY session_id (session_id)
    ) $charset");

    $wpdb->query("CREATE TABLE IF NOT EXISTS $leads (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(191) NOT NULL,
        client_id VARCHAR(191) NOT NULL DEFAULT '',
        company_name VARCHAR(191) NOT NULL DEFAULT '',
        nombre VARCHAR(191) NOT NULL DEFAULT '',
        email VARCHAR(191) NOT NULL DEFAULT '',
        telefono VARCHAR(80) NOT NULL DEFAULT '',
        producto_interes VARCHAR(255) NOT NULL DEFAULT '',
        via_contacto VARCHAR(80) NOT NULL DEFAULT '',
        contexto LONGTEXT NULL,
        raw_payload LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY client_id (client_id)
    ) $charset");

    delete_option('aicor_cbn8n_tables_pending');
}

function aicor_cbn8n_admin_menu()
{
    add_menu_page('Aicor Chatbot n8n', 'Aicor Chatbot', 'manage_options', 'aicor-chatbot-n8n', 'aicor_cbn8n_settings_page', 'dashicons-format-chat', 56);
    add_submenu_page('aicor-chatbot-n8n', 'Ajustes', 'Ajustes', 'manage_options', 'aicor-chatbot-n8n', 'aicor_cbn8n_settings_page');
    add_submenu_page('aicor-chatbot-n8n', 'Conversaciones', 'Conversaciones', 'manage_options', 'aicor-chatbot-n8n-conversations', 'aicor_cbn8n_conversations_page');
    add_submenu_page('aicor-chatbot-n8n', 'Leads', 'Leads', 'manage_options', 'aicor-chatbot-n8n-leads', 'aicor_cbn8n_leads_page');
}

function aicor_cbn8n_register_settings()
{
    register_setting('aicor_cbn8n_settings', AICOR_CBN8N_OPTION, 'aicor_cbn8n_sanitize_options');
}

function aicor_cbn8n_sanitize_options($input)
{
    $defaults = aicor_cbn8n_defaults();
    $input = is_array($input) ? $input : array();
    $out = array();

    $out['enabled'] = empty($input['enabled']) ? '0' : '1';
    $out['chat_webhook_url'] = esc_url_raw(aicor_cbn8n_value($input, 'chat_webhook_url', ''));
    $out['scraper_webhook_url'] = esc_url_raw(aicor_cbn8n_value($input, 'scraper_webhook_url', ''));
    $out['client_id'] = sanitize_text_field(aicor_cbn8n_value($input, 'client_id', $defaults['client_id']));
    $out['company_name'] = sanitize_text_field(aicor_cbn8n_value($input, 'company_name', $defaults['company_name']));
    $out['auth_type'] = in_array(aicor_cbn8n_value($input, 'auth_type', 'none'), array('none', 'bearer', 'header'), true) ? $input['auth_type'] : 'none';
    $out['auth_token'] = sanitize_text_field(aicor_cbn8n_value($input, 'auth_token', ''));
    $out['auth_header_name'] = sanitize_text_field(aicor_cbn8n_value($input, 'auth_header_name', 'X-Chatbot-Token'));
    $out['local_storage_enabled'] = empty($input['local_storage_enabled']) ? '0' : '1';
    $out['title'] = sanitize_text_field(aicor_cbn8n_value($input, 'title', $defaults['title']));
    $out['subtitle'] = sanitize_text_field(aicor_cbn8n_value($input, 'subtitle', $defaults['subtitle']));
    $out['initial_message'] = sanitize_textarea_field(aicor_cbn8n_value($input, 'initial_message', $defaults['initial_message']));
    $out['placeholder'] = sanitize_text_field(aicor_cbn8n_value($input, 'placeholder', $defaults['placeholder']));
    $out['launcher_label'] = sanitize_text_field(aicor_cbn8n_value($input, 'launcher_label', $defaults['launcher_label']));
    $out['position'] = aicor_cbn8n_value($input, 'position', 'right') === 'left' ? 'left' : 'right';
    $out['offset_bottom'] = (string) max(0, min(240, absint(aicor_cbn8n_value($input, 'offset_bottom', '24'))));
    $out['offset_side'] = (string) max(0, min(240, absint(aicor_cbn8n_value($input, 'offset_side', '24'))));
    $out['panel_width'] = (string) max(300, min(720, absint(aicor_cbn8n_value($input, 'panel_width', '380'))));
    $out['panel_height'] = (string) max(360, min(820, absint(aicor_cbn8n_value($input, 'panel_height', '560'))));
    $out['primary_color'] = sanitize_hex_color(aicor_cbn8n_value($input, 'primary_color', $defaults['primary_color']));
    $out['accent_color'] = sanitize_hex_color(aicor_cbn8n_value($input, 'accent_color', $defaults['accent_color']));
    $out['text_color'] = sanitize_hex_color(aicor_cbn8n_value($input, 'text_color', $defaults['text_color']));
    $out['surface_color'] = sanitize_hex_color(aicor_cbn8n_value($input, 'surface_color', $defaults['surface_color']));

    return wp_parse_args($out, $defaults);
}

function aicor_cbn8n_value($array, $key, $default)
{
    return isset($array[$key]) ? $array[$key] : $default;
}

function aicor_cbn8n_enqueue()
{
    $o = aicor_cbn8n_get_options();
    if ($o['enabled'] !== '1' || empty($o['chat_webhook_url'])) {
        return;
    }

    wp_enqueue_style('aicor-cbn8n', AICOR_CBN8N_URL . 'assets/chatbot.css', array(), AICOR_CBN8N_VERSION);
    wp_enqueue_script('aicor-cbn8n', AICOR_CBN8N_URL . 'assets/chatbot.js', array(), AICOR_CBN8N_VERSION, true);

    wp_localize_script('aicor-cbn8n', 'AICOR_CBN8N', array(
        'restUrl' => esc_url_raw(rest_url(AICOR_CBN8N_REST . '/chat')),
        'nonce' => wp_create_nonce('wp_rest'),
        'settings' => array(
            'title' => $o['title'],
            'subtitle' => $o['subtitle'],
            'initialMessage' => $o['initial_message'],
            'placeholder' => $o['placeholder'],
            'launcherLabel' => $o['launcher_label'],
            'position' => $o['position'],
            'offsetBottom' => absint($o['offset_bottom']),
            'offsetSide' => absint($o['offset_side']),
            'panelWidth' => absint($o['panel_width']),
            'panelHeight' => absint($o['panel_height']),
            'primaryColor' => $o['primary_color'],
            'accentColor' => $o['accent_color'],
            'textColor' => $o['text_color'],
            'surfaceColor' => $o['surface_color']
        )
    ));
}

function aicor_cbn8n_rest_routes()
{
    register_rest_route(AICOR_CBN8N_REST, '/chat', array(
        'methods' => 'POST',
        'callback' => 'aicor_cbn8n_handle_chat',
        'permission_callback' => '__return_true'
    ));

    register_rest_route(AICOR_CBN8N_REST, '/scrape', array(
        'methods' => 'POST',
        'callback' => 'aicor_cbn8n_handle_scrape',
        'permission_callback' => 'aicor_cbn8n_can_manage'
    ));
}

function aicor_cbn8n_can_manage()
{
    return current_user_can('manage_options');
}

function aicor_cbn8n_handle_chat($request)
{
    $o = aicor_cbn8n_get_options();
    if ($o['enabled'] !== '1' || empty($o['chat_webhook_url'])) {
        return new WP_Error('not_configured', 'El chatbot no esta configurado.', array('status' => 400));
    }

    $message = trim(wp_strip_all_tags((string) $request->get_param('message')));
    if ($message === '') {
        return new WP_Error('empty_message', 'El mensaje esta vacio.', array('status' => 400));
    }

    $session_id = sanitize_text_field((string) $request->get_param('session_id'));
    if ($session_id === '') {
        $session_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('session_', true);
    }

    $payload = array(
        'chatInput' => $message,
        'message' => $message,
        'sessionId' => $session_id,
        'client_id' => $o['client_id'],
        'clientId' => $o['client_id'],
        'company_name' => $o['company_name'],
        'companyName' => $o['company_name'],
        'metadata' => array('source' => 'wordpress', 'site_url' => home_url('/'), 'page_url' => esc_url_raw((string) $request->get_param('page_url')))
    );

    aicor_cbn8n_store_session($session_id, $o);
    aicor_cbn8n_store_message($session_id, 'user', $message, $payload);

    $response = wp_remote_post($o['chat_webhook_url'], array(
        'timeout' => 45,
        'headers' => aicor_cbn8n_headers($o),
        'body' => wp_json_encode($payload)
    ));

    if (is_wp_error($response)) {
        return new WP_Error('n8n_error', $response->get_error_message(), array('status' => 502));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $reply = aicor_cbn8n_reply($data, $body);

    aicor_cbn8n_store_message($session_id, 'assistant', $reply, is_array($data) ? $data : array('raw' => $body));
    aicor_cbn8n_store_lead($session_id, is_array($data) ? $data : array(), $o);

    return rest_ensure_response(array('ok' => true, 'session_id' => $session_id, 'reply' => $reply, 'raw' => $data));
}

function aicor_cbn8n_handle_scrape($request)
{
    $o = aicor_cbn8n_get_options();
    if (empty($o['scraper_webhook_url'])) {
        return new WP_Error('scraper_not_configured', 'El webhook de scrapeo no esta configurado.', array('status' => 400));
    }

    $urls = $request->get_param('urls');
    if (is_string($urls)) {
        $urls = preg_split('/[\r\n,]+/', $urls);
    }
    $urls = array_values(array_filter(array_map('esc_url_raw', (array) $urls)));
    if (empty($urls)) {
        return new WP_Error('empty_urls', 'Indica al menos una URL.', array('status' => 400));
    }

    $response = wp_remote_post($o['scraper_webhook_url'], array(
        'timeout' => 90,
        'headers' => aicor_cbn8n_headers($o),
        'body' => wp_json_encode(array('urls' => $urls, 'client_id' => $o['client_id'], 'company_name' => $o['company_name']))
    ));

    if (is_wp_error($response)) {
        return new WP_Error('scraper_error', $response->get_error_message(), array('status' => 502));
    }

    $body = wp_remote_retrieve_body($response);
    return rest_ensure_response(array('ok' => true, 'status' => wp_remote_retrieve_response_code($response), 'body' => json_decode($body, true)));
}

function aicor_cbn8n_headers($o)
{
    $headers = array('Content-Type' => 'application/json');
    if (!empty($o['auth_token']) && $o['auth_type'] === 'bearer') {
        $headers['Authorization'] = 'Bearer ' . $o['auth_token'];
    }
    if (!empty($o['auth_token']) && $o['auth_type'] === 'header') {
        $headers[$o['auth_header_name']] = $o['auth_token'];
    }
    return $headers;
}

function aicor_cbn8n_reply($data, $raw)
{
    if (is_array($data)) {
        foreach (array('output', 'reply', 'message', 'text') as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                return wp_strip_all_tags($data[$key]);
            }
        }
        if (isset($data[0]) && is_array($data[0])) {
            return aicor_cbn8n_reply($data[0], $raw);
        }
    }
    $raw = trim(wp_strip_all_tags($raw));
    return $raw === '' ? 'Solicitud recibida. En que mas puedo ayudarte?' : $raw;
}

function aicor_cbn8n_store_session($session_id, $o)
{
    if ($o['local_storage_enabled'] !== '1') {
        return;
    }
    aicor_cbn8n_install_tables();
    global $wpdb;
    $table = $wpdb->prefix . 'aicor_cbn8n_sessions';
    $now = current_time('mysql');
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255) : '';
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE session_id = %s LIMIT 1", $session_id));
    $data = array('session_id' => $session_id, 'client_id' => $o['client_id'], 'company_name' => $o['company_name'], 'ip_hash' => hash('sha256', $ip . wp_salt('auth')), 'user_agent' => $ua, 'updated_at' => $now);
    if ($existing) {
        $wpdb->update($table, $data, array('id' => absint($existing)));
    } else {
        $data['created_at'] = $now;
        $wpdb->insert($table, $data);
    }
}

function aicor_cbn8n_store_message($session_id, $role, $content, $payload)
{
    $o = aicor_cbn8n_get_options();
    if ($o['local_storage_enabled'] !== '1') {
        return;
    }
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'aicor_cbn8n_messages', array('session_id' => $session_id, 'role' => $role, 'content' => $content, 'raw_payload' => wp_json_encode($payload), 'created_at' => current_time('mysql')));
}

function aicor_cbn8n_store_lead($session_id, $response, $o)
{
    if ($o['local_storage_enabled'] !== '1') {
        return;
    }
    $lead = aicor_cbn8n_find_lead($response);
    if (!$lead || !is_array($lead)) {
        return;
    }
    $email = sanitize_email(aicor_cbn8n_value($lead, 'email', ''));
    $phone = sanitize_text_field(aicor_cbn8n_value($lead, 'telefono', aicor_cbn8n_value($lead, 'phone', '')));
    if ($email === '' && $phone === '') {
        return;
    }
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'aicor_cbn8n_leads', array(
        'session_id' => $session_id,
        'client_id' => $o['client_id'],
        'company_name' => $o['company_name'],
        'nombre' => sanitize_text_field(aicor_cbn8n_value($lead, 'nombre', aicor_cbn8n_value($lead, 'name', ''))),
        'email' => $email,
        'telefono' => $phone,
        'producto_interes' => sanitize_text_field(aicor_cbn8n_value($lead, 'producto_interes', '')),
        'via_contacto' => sanitize_text_field(aicor_cbn8n_value($lead, 'via_contacto', '')),
        'contexto' => sanitize_textarea_field(aicor_cbn8n_value($lead, 'contexto', '')),
        'raw_payload' => wp_json_encode($lead),
        'created_at' => current_time('mysql')
    ));
}

function aicor_cbn8n_find_lead($payload)
{
    if (!is_array($payload)) {
        return null;
    }
    if (isset($payload['lead']) && is_array($payload['lead'])) {
        return $payload['lead'];
    }
    if (isset($payload[0]) && is_array($payload[0])) {
        return aicor_cbn8n_find_lead($payload[0]);
    }
    if (isset($payload['json']) && is_array($payload['json'])) {
        return aicor_cbn8n_find_lead($payload['json']);
    }
    return null;
}

function aicor_cbn8n_settings_page()
{
    $o = aicor_cbn8n_get_options();
    ?>
    <div class="wrap">
        <h1>Aicor Chatbot n8n</h1>
        <form method="post" action="options.php">
            <?php settings_fields('aicor_cbn8n_settings'); ?>
            <h2>Conexion</h2>
            <table class="form-table" role="presentation">
                <?php aicor_cbn8n_field_checkbox('enabled', 'Activar widget', $o); ?>
                <?php aicor_cbn8n_field_url('chat_webhook_url', 'Webhook del chat', $o); ?>
                <?php aicor_cbn8n_field_url('scraper_webhook_url', 'Webhook de scrapeo', $o); ?>
                <?php aicor_cbn8n_field_text('client_id', 'Client ID', $o); ?>
                <?php aicor_cbn8n_field_text('company_name', 'Nombre de empresa', $o); ?>
                <?php aicor_cbn8n_field_text('auth_type', 'Auth type: none, bearer o header', $o); ?>
                <?php aicor_cbn8n_field_text('auth_header_name', 'Cabecera personalizada', $o); ?>
                <?php aicor_cbn8n_field_password('auth_token', 'Token', $o); ?>
                <?php aicor_cbn8n_field_checkbox('local_storage_enabled', 'Guardar conversaciones y leads', $o); ?>
            </table>
            <h2>Widget</h2>
            <table class="form-table" role="presentation">
                <?php aicor_cbn8n_field_text('title', 'Titulo', $o); ?>
                <?php aicor_cbn8n_field_text('subtitle', 'Subtitulo', $o); ?>
                <?php aicor_cbn8n_field_textarea('initial_message', 'Primer mensaje', $o); ?>
                <?php aicor_cbn8n_field_text('placeholder', 'Placeholder', $o); ?>
                <?php aicor_cbn8n_field_text('launcher_label', 'Texto boton', $o); ?>
                <?php aicor_cbn8n_field_text('position', 'Posicion: right o left', $o); ?>
                <?php aicor_cbn8n_field_number('offset_bottom', 'Separacion inferior', $o); ?>
                <?php aicor_cbn8n_field_number('offset_side', 'Separacion lateral', $o); ?>
                <?php aicor_cbn8n_field_number('panel_width', 'Ancho panel', $o); ?>
                <?php aicor_cbn8n_field_number('panel_height', 'Alto panel', $o); ?>
                <?php aicor_cbn8n_field_color('primary_color', 'Color principal', $o); ?>
                <?php aicor_cbn8n_field_color('accent_color', 'Color acento', $o); ?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function aicor_cbn8n_conversations_page()
{
    aicor_cbn8n_install_tables();
    global $wpdb;
    $selected = isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '';
    $sessions = $wpdb->get_results("SELECT s.*, COUNT(m.id) AS message_count FROM {$wpdb->prefix}aicor_cbn8n_sessions s LEFT JOIN {$wpdb->prefix}aicor_cbn8n_messages m ON m.session_id = s.session_id GROUP BY s.id ORDER BY s.updated_at DESC LIMIT 100");
    ?>
    <div class="wrap">
        <h1>Conversaciones</h1>
        <div style="display:grid;grid-template-columns:minmax(280px,360px) 1fr;gap:20px;">
            <table class="widefat striped"><thead><tr><th>Sesion</th><th>Mensajes</th><th>Fecha</th></tr></thead><tbody>
            <?php foreach ($sessions as $s) { ?>
                <tr><td><a href="<?php echo esc_url(admin_url('admin.php?page=aicor-chatbot-n8n-conversations&session_id=' . rawurlencode($s->session_id))); ?>"><?php echo esc_html(aicor_cbn8n_short($s->session_id)); ?></a></td><td><?php echo esc_html($s->message_count); ?></td><td><?php echo esc_html($s->updated_at); ?></td></tr>
            <?php } ?>
            <?php if (!$sessions) { ?><tr><td colspan="3">Sin conversaciones.</td></tr><?php } ?>
            </tbody></table>
            <div class="postbox" style="padding:16px;margin:0;">
            <?php
            if ($selected === '') {
                echo '<p>Selecciona una conversacion.</p>';
            } else {
                $messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aicor_cbn8n_messages WHERE session_id = %s ORDER BY created_at ASC, id ASC", $selected));
                foreach ($messages as $m) {
                    echo '<div style="margin:0 0 12px;padding:12px;border-left:4px solid ' . ($m->role === 'user' ? '#2563eb' : '#16a34a') . ';background:#fff;"><strong>' . esc_html($m->role) . '</strong> <small>' . esc_html($m->created_at) . '</small><div style="white-space:pre-wrap;margin-top:8px;">' . esc_html($m->content) . '</div></div>';
                }
            }
            ?>
            </div>
        </div>
    </div>
    <?php
}

function aicor_cbn8n_leads_page()
{
    aicor_cbn8n_install_tables();
    global $wpdb;
    $leads = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aicor_cbn8n_leads ORDER BY created_at DESC LIMIT 200");
    ?>
    <div class="wrap">
        <h1>Leads</h1>
        <table class="widefat striped">
            <thead><tr><th>Fecha</th><th>Nombre</th><th>Email</th><th>Telefono</th><th>Interes</th><th>Conversacion</th></tr></thead>
            <tbody>
            <?php foreach ($leads as $l) { ?>
                <tr>
                    <td><?php echo esc_html($l->created_at); ?></td>
                    <td><?php echo esc_html($l->nombre); ?></td>
                    <td><?php echo esc_html($l->email); ?></td>
                    <td><?php echo esc_html($l->telefono); ?></td>
                    <td><?php echo esc_html($l->producto_interes); ?></td>
                    <td><a href="<?php echo esc_url(admin_url('admin.php?page=aicor-chatbot-n8n-conversations&session_id=' . rawurlencode($l->session_id))); ?>">Ver chat</a></td>
                </tr>
            <?php } ?>
            <?php if (!$leads) { ?><tr><td colspan="6">Sin leads.</td></tr><?php } ?>
            </tbody>
        </table>
    </div>
    <?php
}

function aicor_cbn8n_short($value)
{
    return strlen($value) > 18 ? substr($value, 0, 18) . '...' : $value;
}

function aicor_cbn8n_name($key)
{
    return AICOR_CBN8N_OPTION . '[' . $key . ']';
}

function aicor_cbn8n_field_text($key, $label, $o)
{
    echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input class="regular-text" id="' . esc_attr($key) . '" name="' . esc_attr(aicor_cbn8n_name($key)) . '" value="' . esc_attr(aicor_cbn8n_value($o, $key, '')) . '"></td></tr>';
}

function aicor_cbn8n_field_password($key, $label, $o)
{
    echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input class="regular-text" type="password" id="' . esc_attr($key) . '" name="' . esc_attr(aicor_cbn8n_name($key)) . '" value="' . esc_attr(aicor_cbn8n_value($o, $key, '')) . '"></td></tr>';
}

function aicor_cbn8n_field_url($key, $label, $o)
{
    echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input class="large-text" type="url" id="' . esc_attr($key) . '" name="' . esc_attr(aicor_cbn8n_name($key)) . '" value="' . esc_attr(aicor_cbn8n_value($o, $key, '')) . '"></td></tr>';
}

function aicor_cbn8n_field_textarea($key, $label, $o)
{
    echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><textarea class="large-text" rows="3" id="' . esc_attr($key) . '" name="' . esc_attr(aicor_cbn8n_name($key)) . '">' . esc_textarea(aicor_cbn8n_value($o, $key, '')) . '</textarea></td></tr>';
}

function aicor_cbn8n_field_number($key, $label, $o)
{
    echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input type="number" id="' . esc_attr($key) . '" name="' . esc_attr(aicor_cbn8n_name($key)) . '" value="' . esc_attr(aicor_cbn8n_value($o, $key, '')) . '"></td></tr>';
}

function aicor_cbn8n_field_color($key, $label, $o)
{
    echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input type="color" id="' . esc_attr($key) . '" name="' . esc_attr(aicor_cbn8n_name($key)) . '" value="' . esc_attr(aicor_cbn8n_value($o, $key, '#000000')) . '"></td></tr>';
}

function aicor_cbn8n_field_checkbox($key, $label, $o)
{
    echo '<tr><th>' . esc_html($label) . '</th><td><label><input type="checkbox" name="' . esc_attr(aicor_cbn8n_name($key)) . '" value="1" ' . checked(aicor_cbn8n_value($o, $key, '0'), '1', false) . '> Si</label></td></tr>';
}
