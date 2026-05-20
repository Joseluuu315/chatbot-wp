<?php
/*
Plugin Name: Dgtalit Chatbot n8n
Description: Chatbot flotante para WordPress conectado a n8n. Panel de conversaciones, leads y configuración completa del bot desde el admin.
Version: 2.0.0
Author: Dgtalit
Author URI: https://dgtalit.com
Text Domain: dgtalit-chatbot-n8n
*/

if (!defined('ABSPATH')) exit;

define('DGTALIT_VERSION',     '2.0.0');
define('DGTALIT_FILE',        __FILE__);
define('DGTALIT_PATH',        plugin_dir_path(__FILE__));
define('DGTALIT_URL',         plugin_dir_url(__FILE__));
define('DGTALIT_OPT',         'dgtalit_cbn8n_options');
define('DGTALIT_BOT_OPT',     'dgtalit_cbn8n_bot_options');
define('DGTALIT_REST',        'dgtalit-chatbot-n8n/v1');

register_activation_hook(__FILE__, 'dgtalit_activate');
register_deactivation_hook(__FILE__, 'dgtalit_deactivate');

add_action('admin_menu',         'dgtalit_menu');
add_action('admin_init',         'dgtalit_register_settings');
add_action('admin_init',         'dgtalit_install_tables');
add_action('admin_enqueue_scripts', 'dgtalit_admin_assets');
add_action('wp_enqueue_scripts', 'dgtalit_frontend_assets');
add_action('wp_footer',          'dgtalit_render_widget');
add_action('rest_api_init',      'dgtalit_rest_routes');
add_action('wp_ajax_dgtalit_test_webhook', 'dgtalit_ajax_test_webhook');
add_action('wp_ajax_dgtalit_export_leads', 'dgtalit_ajax_export_leads');

// ─── DEFAULTS ────────────────────────────────────────────────────────────────

function dgtalit_defaults() {
    return [
        'enabled'              => '1',
        'chat_webhook_url'     => '',
        'scraper_webhook_url'  => '',
        'client_id'            => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('client_', true),
        'company_name'         => get_bloginfo('name'),
        'auth_type'            => 'none',
        'auth_token'           => '',
        'auth_header_name'     => 'X-Chatbot-Token',
        'local_storage_enabled'=> '1',
        'title'                => 'Asistente virtual',
        'subtitle'             => 'Responde en segundos',
        'placeholder'          => 'Escribe tu mensaje...',
        'launcher_label'       => 'Chat',
        'position'             => 'right',
        'offset_bottom'        => '24',
        'offset_side'          => '24',
        'panel_width'          => '380',
        'panel_height'         => '580',
        'primary_color'        => '#1a56db',
        'accent_color'         => '#16a34a',
        'text_color'           => '#111827',
        'surface_color'        => '#ffffff',
    ];
}

function dgtalit_bot_defaults() {
    return [
        'bot_name'             => 'Asistente',
        'tone'                 => 'natural',
        'language'             => 'es',
        'system_prompt'        => "Eres el asistente virtual de {company_name}.\nHabla de forma natural y directa, sin florituras.\nResponde solo con información de la base de conocimiento de {company_name}.\nNunca inventes servicios, precios ni compromisos.",
        'welcome_message'      => '¡Hola! Soy el asistente de {company_name}. ¿Cómo te llamas?',
        'flow_enabled'         => '1',
        'step1_label'          => 'Nombre',
        'step1_prompt'         => 'Cuando dé su nombre, úsalo y pregunta en qué le puedes ayudar.',
        'step2_label'          => 'Necesidad',
        'step2_prompt'         => 'Cuando el usuario diga lo que busca, conecta con su situación y haz UNA pregunta concreta.',
        'step3_label'          => 'Cualificación',
        'step3_prompt'         => 'Cuando ya sepas qué quiere, haz UNA pregunta relevante para conocerle mejor.',
        'step4_label'          => 'Oferta de contacto',
        'step4_prompt'         => "Ofrece contacto:\na) Información por email\nb) Información por WhatsApp\nc) Llamada de un comercial",
        'step5_label'          => 'Recogida de datos',
        'step5_prompt'         => 'Recoge siempre teléfono Y email. Un dato por mensaje.',
        'max_response_lines'   => '4',
        'one_question_per_msg' => '1',
        'no_repeat_openers'    => '1',
        'no_personal_before_step4' => '1',
        'lead_capture_enabled' => '1',
        'lead_trigger_word'    => 'LEAD_CAPTURED',
        'lead_confirm_msg'     => 'Perfecto, {nombre}. Ya lo tenemos apuntado, te contactamos en breve. ¡Hasta pronto!',
        'block_off_topic'      => '1',
        'off_topic_reply'      => 'Eso está fuera de lo que puedo ayudarte aquí. ¿Tienes alguna pregunta sobre {company_name}?',
        'unknown_info_reply'   => 'Eso te lo confirma el equipo directamente. ¿Quieres que lo gestionemos?',
        'filter_spam'          => '1',
        'filter_insults'       => '1',
        'filter_long_msg'      => '1',
        'max_msg_length'       => '800',
        'spam_reply'           => 'No puedo procesar mensajes con ese tipo de contenido.',
        'insult_reply'         => 'Por favor, mantén un tono respetuoso para que pueda ayudarte.',
        'long_msg_reply'       => 'El mensaje es demasiado largo. ¿Puedes resumirlo en pocas frases?',
        'ai_model'             => 'meta-llama/llama-3.3-70b-instruct',
        'ai_temperature'       => '0.7',
        'ai_max_tokens'        => '1000',
    ];
}

function dgtalit_get_opts()    { return wp_parse_args(get_option(DGTALIT_OPT, []),     dgtalit_defaults()); }
function dgtalit_get_bot_opts(){ return wp_parse_args(get_option(DGTALIT_BOT_OPT, []), dgtalit_bot_defaults()); }

// ─── ACTIVATE / DEACTIVATE ───────────────────────────────────────────────────

function dgtalit_activate() {
    if (!get_option(DGTALIT_OPT))     add_option(DGTALIT_OPT,     dgtalit_defaults());
    if (!get_option(DGTALIT_BOT_OPT)) add_option(DGTALIT_BOT_OPT, dgtalit_bot_defaults());
    dgtalit_install_tables();
}
function dgtalit_deactivate() {}

function dgtalit_install_tables() {
    global $wpdb;
    $c = $wpdb->get_charset_collate();
    $s = $wpdb->prefix . 'dgtalit_sessions';
    $m = $wpdb->prefix . 'dgtalit_messages';
    $l = $wpdb->prefix . 'dgtalit_leads';
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE IF NOT EXISTS $s (
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
    ) $c");
    dbDelta("CREATE TABLE IF NOT EXISTS $m (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(191) NOT NULL,
        role VARCHAR(20) NOT NULL,
        content LONGTEXT NOT NULL,
        raw_payload LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY session_id (session_id)
    ) $c");
    dbDelta("CREATE TABLE IF NOT EXISTS $l (
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
        KEY email (email)
    ) $c");
}

// ─── ADMIN MENU ──────────────────────────────────────────────────────────────

function dgtalit_menu() {
    add_menu_page('Dgtalit Chatbot', 'Dgtalit Chatbot', 'manage_options',
        'dgtalit-chatbot', 'dgtalit_page_settings',
        'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'),
        58
    );
    add_submenu_page('dgtalit-chatbot', 'Conexión & Widget', 'Conexión & Widget', 'manage_options', 'dgtalit-chatbot',           'dgtalit_page_settings');
    add_submenu_page('dgtalit-chatbot', 'Configurar Bot',    'Configurar Bot',    'manage_options', 'dgtalit-chatbot-bot',        'dgtalit_page_bot');
    add_submenu_page('dgtalit-chatbot', 'Conversaciones',    'Conversaciones',    'manage_options', 'dgtalit-chatbot-convs',      'dgtalit_page_convs');
    add_submenu_page('dgtalit-chatbot', 'Leads',             'Leads',             'manage_options', 'dgtalit-chatbot-leads',      'dgtalit_page_leads');
}

// ─── SETTINGS ────────────────────────────────────────────────────────────────

function dgtalit_register_settings() {
    register_setting('dgtalit_settings',     DGTALIT_OPT,     'dgtalit_sanitize');
    register_setting('dgtalit_bot_settings', DGTALIT_BOT_OPT, 'dgtalit_bot_sanitize');
}

function dgtalit_val($a, $k, $d = '') { return isset($a[$k]) ? $a[$k] : $d; }

function dgtalit_sanitize($in) {
    $d = dgtalit_defaults(); $in = is_array($in) ? $in : [];
    return wp_parse_args([
        'enabled'              => empty($in['enabled']) ? '0' : '1',
        'chat_webhook_url'     => esc_url_raw(dgtalit_val($in,'chat_webhook_url')),
        'scraper_webhook_url'  => esc_url_raw(dgtalit_val($in,'scraper_webhook_url')),
        'client_id'            => sanitize_text_field(dgtalit_val($in,'client_id',$d['client_id'])),
        'company_name'         => sanitize_text_field(dgtalit_val($in,'company_name',$d['company_name'])),
        'auth_type'            => in_array(dgtalit_val($in,'auth_type','none'),['none','bearer','header'],true) ? $in['auth_type'] : 'none',
        'auth_token'           => sanitize_text_field(dgtalit_val($in,'auth_token')),
        'auth_header_name'     => sanitize_text_field(dgtalit_val($in,'auth_header_name','X-Chatbot-Token')),
        'local_storage_enabled'=> empty($in['local_storage_enabled']) ? '0' : '1',
        'title'                => sanitize_text_field(dgtalit_val($in,'title',$d['title'])),
        'subtitle'             => sanitize_text_field(dgtalit_val($in,'subtitle',$d['subtitle'])),
        'placeholder'          => sanitize_text_field(dgtalit_val($in,'placeholder',$d['placeholder'])),
        'launcher_label'       => sanitize_text_field(dgtalit_val($in,'launcher_label',$d['launcher_label'])),
        'position'             => dgtalit_val($in,'position','right') === 'left' ? 'left' : 'right',
        'offset_bottom'        => (string)max(0,min(240,absint(dgtalit_val($in,'offset_bottom','24')))),
        'offset_side'          => (string)max(0,min(240,absint(dgtalit_val($in,'offset_side','24')))),
        'panel_width'          => (string)max(300,min(720,absint(dgtalit_val($in,'panel_width','380')))),
        'panel_height'         => (string)max(360,min(820,absint(dgtalit_val($in,'panel_height','580')))),
        'primary_color'        => sanitize_hex_color(dgtalit_val($in,'primary_color',$d['primary_color'])) ?: $d['primary_color'],
        'accent_color'         => sanitize_hex_color(dgtalit_val($in,'accent_color',$d['accent_color'])) ?: $d['accent_color'],
        'text_color'           => sanitize_hex_color(dgtalit_val($in,'text_color',$d['text_color'])) ?: $d['text_color'],
        'surface_color'        => sanitize_hex_color(dgtalit_val($in,'surface_color',$d['surface_color'])) ?: $d['surface_color'],
    ], $d);
}

function dgtalit_bot_sanitize($in) {
    $d = dgtalit_bot_defaults(); $in = is_array($in) ? $in : [];
    return wp_parse_args([
        'bot_name'             => sanitize_text_field(dgtalit_val($in,'bot_name',$d['bot_name'])),
        'tone'                 => in_array(dgtalit_val($in,'tone','natural'),['natural','formal','friendly','technical'],true) ? $in['tone'] : 'natural',
        'language'             => in_array(dgtalit_val($in,'language','es'),['es','en','auto'],true) ? $in['language'] : 'es',
        'system_prompt'        => sanitize_textarea_field(dgtalit_val($in,'system_prompt',$d['system_prompt'])),
        'welcome_message'      => sanitize_textarea_field(dgtalit_val($in,'welcome_message',$d['welcome_message'])),
        'flow_enabled'         => empty($in['flow_enabled']) ? '0' : '1',
        'step1_label'          => sanitize_text_field(dgtalit_val($in,'step1_label',$d['step1_label'])),
        'step1_prompt'         => sanitize_textarea_field(dgtalit_val($in,'step1_prompt',$d['step1_prompt'])),
        'step2_label'          => sanitize_text_field(dgtalit_val($in,'step2_label',$d['step2_label'])),
        'step2_prompt'         => sanitize_textarea_field(dgtalit_val($in,'step2_prompt',$d['step2_prompt'])),
        'step3_label'          => sanitize_text_field(dgtalit_val($in,'step3_label',$d['step3_label'])),
        'step3_prompt'         => sanitize_textarea_field(dgtalit_val($in,'step3_prompt',$d['step3_prompt'])),
        'step4_label'          => sanitize_text_field(dgtalit_val($in,'step4_label',$d['step4_label'])),
        'step4_prompt'         => sanitize_textarea_field(dgtalit_val($in,'step4_prompt',$d['step4_prompt'])),
        'step5_label'          => sanitize_text_field(dgtalit_val($in,'step5_label',$d['step5_label'])),
        'step5_prompt'         => sanitize_textarea_field(dgtalit_val($in,'step5_prompt',$d['step5_prompt'])),
        'max_response_lines'   => (string)max(1,min(20,absint(dgtalit_val($in,'max_response_lines','4')))),
        'one_question_per_msg' => empty($in['one_question_per_msg']) ? '0' : '1',
        'no_repeat_openers'    => empty($in['no_repeat_openers']) ? '0' : '1',
        'no_personal_before_step4' => empty($in['no_personal_before_step4']) ? '0' : '1',
        'lead_capture_enabled' => empty($in['lead_capture_enabled']) ? '0' : '1',
        'lead_trigger_word'    => sanitize_text_field(dgtalit_val($in,'lead_trigger_word','LEAD_CAPTURED')),
        'lead_confirm_msg'     => sanitize_textarea_field(dgtalit_val($in,'lead_confirm_msg',$d['lead_confirm_msg'])),
        'block_off_topic'      => empty($in['block_off_topic']) ? '0' : '1',
        'off_topic_reply'      => sanitize_textarea_field(dgtalit_val($in,'off_topic_reply',$d['off_topic_reply'])),
        'unknown_info_reply'   => sanitize_textarea_field(dgtalit_val($in,'unknown_info_reply',$d['unknown_info_reply'])),
        'filter_spam'          => empty($in['filter_spam']) ? '0' : '1',
        'filter_insults'       => empty($in['filter_insults']) ? '0' : '1',
        'filter_long_msg'      => empty($in['filter_long_msg']) ? '0' : '1',
        'max_msg_length'       => (string)max(100,min(5000,absint(dgtalit_val($in,'max_msg_length','800')))),
        'spam_reply'           => sanitize_textarea_field(dgtalit_val($in,'spam_reply',$d['spam_reply'])),
        'insult_reply'         => sanitize_textarea_field(dgtalit_val($in,'insult_reply',$d['insult_reply'])),
        'long_msg_reply'       => sanitize_textarea_field(dgtalit_val($in,'long_msg_reply',$d['long_msg_reply'])),
        'ai_model'             => sanitize_text_field(dgtalit_val($in,'ai_model',$d['ai_model'])),
        'ai_temperature'       => number_format(max(0,min(2,(float)dgtalit_val($in,'ai_temperature','0.7'))),1),
        'ai_max_tokens'        => (string)max(100,min(4000,absint(dgtalit_val($in,'ai_max_tokens','1000')))),
    ], $d);
}

// ─── ADMIN ASSETS ────────────────────────────────────────────────────────────

function dgtalit_admin_assets($hook) {
    if (strpos($hook, 'dgtalit-chatbot') === false) return;
    wp_enqueue_style('dgtalit-admin', DGTALIT_URL . 'assets/admin.css', [], DGTALIT_VERSION);
    wp_enqueue_script('dgtalit-admin', DGTALIT_URL . 'assets/admin.js', ['jquery'], DGTALIT_VERSION, true);
    wp_localize_script('dgtalit-admin', 'DGTALIT_ADMIN', [
        'ajax_url'  => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('dgtalit_admin'),
        'rest_url'  => rest_url(DGTALIT_REST),
        'client_id' => dgtalit_get_opts()['client_id'],
    ]);
    // Color picker
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
}

// ─── FRONTEND ASSETS + WIDGET ────────────────────────────────────────────────

function dgtalit_frontend_assets() {
    $o  = dgtalit_get_opts();
    $bo = dgtalit_get_bot_opts();
    if ($o['enabled'] !== '1' || empty($o['chat_webhook_url'])) return;
    wp_enqueue_style('dgtalit-chat',  DGTALIT_URL . 'assets/chatbot.css',  [], DGTALIT_VERSION);
    wp_enqueue_script('dgtalit-chat', DGTALIT_URL . 'assets/chatbot.js',   [], DGTALIT_VERSION, true);
    wp_localize_script('dgtalit-chat', 'DGTALIT', [
        'rest'     => esc_url_raw(rest_url(DGTALIT_REST . '/chat')),
        'nonce'    => wp_create_nonce('wp_rest'),
        'cfg'      => [
            'title'         => $o['title'],
            'subtitle'      => $o['subtitle'],
            'welcome'       => str_replace('{company_name}', $o['company_name'], $bo['welcome_message']),
            'placeholder'   => $o['placeholder'],
            'launcher'      => $o['launcher_label'],
            'position'      => $o['position'],
            'offsetBottom'  => absint($o['offset_bottom']),
            'offsetSide'    => absint($o['offset_side']),
            'panelW'        => absint($o['panel_width']),
            'panelH'        => absint($o['panel_height']),
            'primary'       => $o['primary_color'],
            'accent'        => $o['accent_color'],
            'textColor'     => $o['text_color'],
            'surface'       => $o['surface_color'],
        ],
    ]);
}

function dgtalit_render_widget() {
    $o = dgtalit_get_opts();
    if ($o['enabled'] !== '1' || empty($o['chat_webhook_url'])) return;
    echo '<div id="dgtalit-chat-root"></div>';
}

// ─── REST API ─────────────────────────────────────────────────────────────────

function dgtalit_rest_routes() {
    register_rest_route(DGTALIT_REST, '/chat', [
        'methods'             => 'POST',
        'callback'            => 'dgtalit_rest_chat',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route(DGTALIT_REST, '/scrape', [
        'methods'             => 'POST',
        'callback'            => 'dgtalit_rest_scrape',
        'permission_callback' => fn() => current_user_can('manage_options'),
    ]);
    register_rest_route(DGTALIT_REST, '/bot-config', [
        'methods'             => 'GET',
        'callback'            => 'dgtalit_rest_bot_config',
        'permission_callback' => '__return_true',
    ]);
}

function dgtalit_rest_chat($req) {
    $o = dgtalit_get_opts();
    if ($o['enabled'] !== '1' || empty($o['chat_webhook_url']))
        return new WP_Error('not_configured', 'Chatbot no configurado.', ['status'=>400]);

    $msg = trim(wp_strip_all_tags((string)$req->get_param('message')));
    if (!$msg) return new WP_Error('empty', 'Mensaje vacío.', ['status'=>400]);

    $sid = sanitize_text_field((string)$req->get_param('session_id'));
    if (!$sid) $sid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('s_',true);

    $payload = [
        'chatInput'      => $msg, 'message' => $msg, 'sessionId' => $sid,
        'client_id'      => $o['client_id'], 'clientId' => $o['client_id'],
        'company_name'   => $o['company_name'], 'companyName' => $o['company_name'],
        'bot_config_url' => esc_url_raw(rest_url(DGTALIT_REST.'/bot-config')),
        'metadata'       => ['source'=>'wordpress','site_url'=>home_url('/'),
                             'page_url'=>esc_url_raw((string)$req->get_param('page_url'))],
    ];

    dgtalit_save_session($sid, $o);
    dgtalit_save_msg($sid, 'user', $msg, $payload);

    $res = wp_remote_post($o['chat_webhook_url'], [
        'timeout' => 45,
        'headers' => dgtalit_headers($o),
        'body'    => wp_json_encode($payload),
    ]);
    if (is_wp_error($res)) return new WP_Error('n8n_err', $res->get_error_message(), ['status'=>502]);

    $body  = wp_remote_retrieve_body($res);
    $data  = json_decode($body, true);
    $reply = dgtalit_extract_reply($data, $body);

    dgtalit_save_msg($sid, 'assistant', $reply, is_array($data) ? $data : ['raw'=>$body]);
    dgtalit_save_lead($sid, is_array($data) ? $data : [], $o);

    return rest_ensure_response(['ok'=>true,'session_id'=>$sid,'reply'=>$reply]);
}

function dgtalit_rest_bot_config($req) {
    $o  = dgtalit_get_opts();
    $bo = dgtalit_get_bot_opts();
    $rc = sanitize_text_field((string)$req->get_param('client_id'));
    if ($rc && $rc !== $o['client_id'])
        return new WP_Error('forbidden','client_id incorrecto.',['status'=>403]);

    $vars = ['{company_name}'=>$o['company_name'],'{bot_name}'=>$bo['bot_name'],'{client_id}'=>$o['client_id']];
    $sp   = str_replace(array_keys($vars), array_values($vars), $bo['system_prompt']);
    $wm   = str_replace(array_keys($vars), array_values($vars), $bo['welcome_message']);

    $tones = ['natural'=>'Habla como una persona real: natural, directo, sin florituras.',
              'formal' =>'Usa un tono formal y profesional. Trata de usted.',
              'friendly'=>'Usa un tono cercano y amigable. Tutea al usuario.',
              'technical'=>'Usa un tono técnico y preciso. Sé detallado.'];
    $sp = ($tones[$bo['tone']] ?? '') . "\n\n" . $sp;

    if ($bo['flow_enabled']==='1') {
        $blocks = [];
        foreach (['step1','step2','step3','step4','step5'] as $s) {
            $lbl = dgtalit_val($bo,$s.'_label','');
            $pmt = str_replace(array_keys($vars),array_values($vars),dgtalit_val($bo,$s.'_prompt',''));
            if ($lbl && $pmt) $blocks[] = "─── PASO: {$lbl} ───\n{$pmt}";
        }
        if ($blocks) $sp .= "\n\n".implode("\n\n",$blocks);
    }

    $rules = [];
    if ($bo['max_response_lines'])              $rules[] = "Máximo {$bo['max_response_lines']} líneas por respuesta.";
    if ($bo['one_question_per_msg']==='1')      $rules[] = "Una sola pregunta por mensaje, al final.";
    if ($bo['no_repeat_openers']==='1')         $rules[] = "NUNCA empieces dos respuestas seguidas igual.";
    if ($bo['no_personal_before_step4']==='1')  $rules[] = "No pidas datos personales antes del paso de contacto.";
    if ($bo['block_off_topic']==='1')           $rules[] = "Off-topic: \"{$bo['off_topic_reply']}\"";
    $rules[] = "Sin info: \"{$bo['unknown_info_reply']}\"";
    $sp .= "\n\n─── REGLAS ───\n".implode("\n",$rules);

    if ($bo['lead_capture_enabled']==='1') {
        $lc = str_replace(array_keys($vars),array_values($vars),$bo['lead_confirm_msg']);
        $sp .= "\n\n─── LEAD CAPTURE ───\nTrigger: {$bo['lead_trigger_word']}\nFormato: {$bo['lead_trigger_word']}:{\"nombre\":\"...\",\"email\":\"...\",\"telefono\":\"...\",\"producto_interes\":\"...\",\"via_contacto\":\"...\",\"contexto\":\"...\",\"origen\":\"chatbot\",\"timestamp\":\"ISO8601\"}\nConfirmación: \"{$lc}\"";
    }

    return rest_ensure_response([
        'ok'            => true,
        'client_id'     => $o['client_id'],
        'company_name'  => $o['company_name'],
        'bot_name'      => $bo['bot_name'],
        'tone'          => $bo['tone'],
        'language'      => $bo['language'],
        'system_prompt' => $sp,
        'welcome_message'=> $wm,
        'lead_trigger'  => $bo['lead_trigger_word'],
        'lead_capture_enabled' => $bo['lead_capture_enabled']==='1',
        'filters'       => [
            'spam'          => $bo['filter_spam']==='1',
            'insults'       => $bo['filter_insults']==='1',
            'long_msg'      => $bo['filter_long_msg']==='1',
            'max_msg_length'=> (int)$bo['max_msg_length'],
            'spam_reply'    => $bo['spam_reply'],
            'insult_reply'  => $bo['insult_reply'],
            'long_msg_reply'=> $bo['long_msg_reply'],
        ],
        'ai' => [
            'model'      => $bo['ai_model'],
            'temperature'=> (float)$bo['ai_temperature'],
            'max_tokens' => (int)$bo['ai_max_tokens'],
        ],
    ]);
}

function dgtalit_rest_scrape($req) {
    $o = dgtalit_get_opts();
    if (empty($o['scraper_webhook_url']))
        return new WP_Error('no_scraper','Webhook de scrapeo no configurado.',['status'=>400]);
    $urls = $req->get_param('urls');
    if (is_string($urls)) $urls = preg_split('/[\r\n,]+/',$urls);
    $urls = array_values(array_filter(array_map('esc_url_raw',(array)$urls)));
    if (!$urls) return new WP_Error('no_urls','Indica al menos una URL.',['status'=>400]);
    $res = wp_remote_post($o['scraper_webhook_url'],['timeout'=>90,'headers'=>dgtalit_headers($o),
        'body'=>wp_json_encode(['urls'=>$urls,'client_id'=>$o['client_id'],'company_name'=>$o['company_name']])]);
    if (is_wp_error($res)) return new WP_Error('err',$res->get_error_message(),['status'=>502]);
    return rest_ensure_response(['ok'=>true,'status'=>wp_remote_retrieve_response_code($res),'body'=>json_decode(wp_remote_retrieve_body($res),true)]);
}

// ─── AJAX ─────────────────────────────────────────────────────────────────────

function dgtalit_ajax_test_webhook() {
    check_ajax_referer('dgtalit_admin','nonce');
    if (!current_user_can('manage_options')) wp_die('Forbidden',403);
    $url = esc_url_raw(sanitize_text_field($_POST['url'] ?? ''));
    if (!$url) wp_send_json_error('URL vacía');
    $res = wp_remote_post($url,['timeout'=>10,'headers'=>['Content-Type'=>'application/json'],
        'body'=>wp_json_encode(['test'=>true,'source'=>'dgtalit-chatbot','ping'=>true])]);
    if (is_wp_error($res)) wp_send_json_error($res->get_error_message());
    $code = wp_remote_retrieve_response_code($res);
    if ($code >= 200 && $code < 300) wp_send_json_success("✓ Respuesta {$code} — Webhook activo");
    else wp_send_json_error("Respuesta HTTP {$code}");
}

function dgtalit_ajax_export_leads() {
    check_ajax_referer('dgtalit_admin','nonce');
    if (!current_user_can('manage_options')) wp_die('Forbidden',403);
    global $wpdb;
    $leads = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dgtalit_leads ORDER BY created_at DESC");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output','w');
    fputs($out,"\xEF\xBB\xBF"); // BOM UTF-8
    fputcsv($out,['Fecha','Nombre','Email','Teléfono','Producto','Vía','Contexto','Sesión']);
    foreach ($leads as $l)
        fputcsv($out,[$l->created_at,$l->nombre,$l->email,$l->telefono,$l->producto_interes,$l->via_contacto,$l->contexto,$l->session_id]);
    fclose($out);
    exit;
}

// ─── DB HELPERS ───────────────────────────────────────────────────────────────

function dgtalit_headers($o) {
    $h = ['Content-Type'=>'application/json'];
    if (!empty($o['auth_token'])) {
        if ($o['auth_type']==='bearer') $h['Authorization'] = 'Bearer '.$o['auth_token'];
        if ($o['auth_type']==='header') $h[$o['auth_header_name']] = $o['auth_token'];
    }
    return $h;
}

function dgtalit_extract_reply($data, $raw) {
    if (is_array($data)) {
        foreach (['output','reply','message','text'] as $k)
            if (!empty($data[$k]) && is_string($data[$k])) return wp_strip_all_tags($data[$k]);
        if (isset($data[0]) && is_array($data[0])) return dgtalit_extract_reply($data[0],$raw);
    }
    $r = trim(wp_strip_all_tags($raw));
    return $r ?: '¿En qué más puedo ayudarte?';
}

function dgtalit_save_session($sid, $o) {
    if ($o['local_storage_enabled']!=='1') return;
    global $wpdb; $t = $wpdb->prefix.'dgtalit_sessions'; $now = current_time('mysql');
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])),0,255) : '';
    $ex = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE session_id=%s LIMIT 1",$sid));
    $d  = ['session_id'=>$sid,'client_id'=>$o['client_id'],'company_name'=>$o['company_name'],
           'ip_hash'=>hash('sha256',$ip.wp_salt('auth')),'user_agent'=>$ua,'updated_at'=>$now];
    if ($ex) $wpdb->update($t,$d,['id'=>absint($ex)]);
    else { $d['created_at']=$now; $wpdb->insert($t,$d); }
}

function dgtalit_save_msg($sid, $role, $content, $payload) {
    $o = dgtalit_get_opts();
    if ($o['local_storage_enabled']!=='1') return;
    global $wpdb;
    $wpdb->insert($wpdb->prefix.'dgtalit_messages',
        ['session_id'=>$sid,'role'=>$role,'content'=>$content,
         'raw_payload'=>wp_json_encode($payload),'created_at'=>current_time('mysql')]);
}

function dgtalit_save_lead($sid, $response, $o) {
    if ($o['local_storage_enabled']!=='1') return;
    $lead = null;
    if (isset($response['lead']) && is_array($response['lead'])) $lead = $response['lead'];
    elseif (isset($response[0]['lead']) && is_array($response[0]['lead'])) $lead = $response[0]['lead'];
    if (!$lead) return;
    $email = sanitize_email(dgtalit_val($lead,'email'));
    $phone = sanitize_text_field(dgtalit_val($lead,'telefono',dgtalit_val($lead,'phone')));
    if (!$email && !$phone) return;
    global $wpdb;
    $wpdb->insert($wpdb->prefix.'dgtalit_leads',[
        'session_id'=>$sid,'client_id'=>$o['client_id'],'company_name'=>$o['company_name'],
        'nombre'=>sanitize_text_field(dgtalit_val($lead,'nombre',dgtalit_val($lead,'name'))),
        'email'=>$email,'telefono'=>$phone,
        'producto_interes'=>sanitize_text_field(dgtalit_val($lead,'producto_interes')),
        'via_contacto'=>sanitize_text_field(dgtalit_val($lead,'via_contacto')),
        'contexto'=>sanitize_textarea_field(dgtalit_val($lead,'contexto')),
        'raw_payload'=>wp_json_encode($lead),'created_at'=>current_time('mysql'),
    ]);
}

// ─── ADMIN PAGES ──────────────────────────────────────────────────────────────

function dgtalit_page_settings() {
    $o   = dgtalit_get_opts();
    $saved = isset($_GET['settings-updated']) && $_GET['settings-updated'];
    $bot_config_url = esc_url(rest_url(DGTALIT_REST.'/bot-config?client_id='.$o['client_id']));
    ?>
    <div class="dg-wrap">
    <?php if ($saved): ?><div class="dg-notice dg-notice--success"><span class="dg-notice__icon">✓</span><strong>¡Cambios guardados correctamente!</strong></div><?php endif; ?>
    <div class="dg-header">
        <div class="dg-header__icon">💬</div>
        <div>
            <h1 class="dg-header__title">Conexión & Widget</h1>
            <p class="dg-header__sub">Configura el webhook de n8n y la apariencia del chat</p>
        </div>
    </div>
    <form method="post" action="options.php" id="dg-settings-form">
    <?php settings_fields('dgtalit_settings'); ?>

    <div class="dg-card">
        <div class="dg-card__head"><span class="dg-card__icon">🔗</span><h2>Conexión con n8n</h2></div>
        <div class="dg-card__body">
            <div class="dg-field">
                <label>Estado del widget</label>
                <div class="dg-toggle-wrap">
                    <label class="dg-toggle">
                        <input type="checkbox" name="<?=DGTALIT_OPT?>[enabled]" value="1" <?=checked($o['enabled'],'1',false)?>>
                        <span class="dg-toggle__slider"></span>
                    </label>
                    <span class="dg-toggle__label">Chatbot activado</span>
                </div>
            </div>
            <div class="dg-field">
                <label for="chat_wh">Webhook del chat <span class="dg-badge dg-badge--required">Requerido</span></label>
                <div class="dg-input-action">
                    <input type="url" id="chat_wh" name="<?=DGTALIT_OPT?>[chat_webhook_url]" value="<?=esc_attr($o['chat_webhook_url'])?>" placeholder="https://tu-n8n.com/webhook/...">
                    <button type="button" class="dg-btn dg-btn--outline dg-test-wh" data-field="chat_wh">Probar</button>
                </div>
                <div class="dg-wh-result" id="res_chat_wh"></div>
            </div>
            <div class="dg-field">
                <label for="scr_wh">Webhook de scrapeo <span class="dg-badge">Opcional</span></label>
                <div class="dg-input-action">
                    <input type="url" id="scr_wh" name="<?=DGTALIT_OPT?>[scraper_webhook_url]" value="<?=esc_attr($o['scraper_webhook_url'])?>" placeholder="https://tu-n8n.com/webhook/...">
                    <button type="button" class="dg-btn dg-btn--outline dg-test-wh" data-field="scr_wh">Probar</button>
                </div>
                <div class="dg-wh-result" id="res_scr_wh"></div>
            </div>
            <div class="dg-grid-2">
                <div class="dg-field">
                    <label>Client ID</label>
                    <div class="dg-input-copy">
                        <input type="text" name="<?=DGTALIT_OPT?>[client_id]" value="<?=esc_attr($o['client_id'])?>" readonly>
                        <button type="button" class="dg-copy-btn" data-copy="<?=esc_attr($o['client_id'])?>">Copiar</button>
                    </div>
                </div>
                <div class="dg-field">
                    <label>Nombre de empresa</label>
                    <input type="text" name="<?=DGTALIT_OPT?>[company_name]" value="<?=esc_attr($o['company_name'])?>">
                </div>
            </div>
            <div class="dg-field">
                <label>URL de configuración del bot <span class="dg-badge">Para n8n</span></label>
                <div class="dg-input-copy">
                    <input type="text" value="<?=esc_attr($bot_config_url)?>" readonly>
                    <button type="button" class="dg-copy-btn" data-copy="<?=esc_attr($bot_config_url)?>">Copiar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="dg-card">
        <div class="dg-card__head"><span class="dg-card__icon">🔐</span><h2>Autenticación</h2></div>
        <div class="dg-card__body">
            <div class="dg-grid-2">
                <div class="dg-field">
                    <label>Tipo de autenticación</label>
                    <select name="<?=DGTALIT_OPT?>[auth_type]" id="dg-auth-type">
                        <?php foreach(['none'=>'Sin autenticación','bearer'=>'Bearer Token','header'=>'Header personalizado'] as $v=>$l): ?>
                        <option value="<?=esc_attr($v)?>" <?=selected($o['auth_type'],$v,false)?>><?=esc_html($l)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="dg-field" id="dg-auth-header-name" style="<?=$o['auth_type']==='header'?'':'display:none'?>">
                    <label>Nombre de cabecera</label>
                    <input type="text" name="<?=DGTALIT_OPT?>[auth_header_name]" value="<?=esc_attr($o['auth_header_name'])?>">
                </div>
            </div>
            <div class="dg-field" id="dg-auth-token" style="<?=$o['auth_type']==='none'?'display:none':''?>">
                <label>Token</label>
                <input type="password" name="<?=DGTALIT_OPT?>[auth_token]" value="<?=esc_attr($o['auth_token'])?>" autocomplete="new-password">
            </div>
        </div>
    </div>

    <div class="dg-card">
        <div class="dg-card__head"><span class="dg-card__icon">🎨</span><h2>Apariencia del widget</h2></div>
        <div class="dg-card__body">
            <div class="dg-grid-2">
                <div class="dg-field">
                    <label>Título del panel</label>
                    <input type="text" name="<?=DGTALIT_OPT?>[title]" value="<?=esc_attr($o['title'])?>">
                </div>
                <div class="dg-field">
                    <label>Subtítulo</label>
                    <input type="text" name="<?=DGTALIT_OPT?>[subtitle]" value="<?=esc_attr($o['subtitle'])?>">
                </div>
                <div class="dg-field">
                    <label>Texto placeholder</label>
                    <input type="text" name="<?=DGTALIT_OPT?>[placeholder]" value="<?=esc_attr($o['placeholder'])?>">
                </div>
                <div class="dg-field">
                    <label>Texto botón launcher</label>
                    <input type="text" name="<?=DGTALIT_OPT?>[launcher_label]" value="<?=esc_attr($o['launcher_label'])?>">
                </div>
            </div>
            <div class="dg-grid-4">
                <div class="dg-field">
                    <label>Color principal</label>
                    <input type="text" name="<?=DGTALIT_OPT?>[primary_color]" value="<?=esc_attr($o['primary_color'])?>" class="dg-color-picker">
                </div>
                <div class="dg-field">
                    <label>Color acento</label>
                    <input type="text" name="<?=DGTALIT_OPT?>[accent_color]" value="<?=esc_attr($o['accent_color'])?>" class="dg-color-picker">
                </div>
                <div class="dg-field">
                    <label>Color texto</label>
                    <input type="text" name="<?=DGTALIT_OPT?>[text_color]" value="<?=esc_attr($o['text_color'])?>" class="dg-color-picker">
                </div>
                <div class="dg-field">
                    <label>Fondo panel</label>
                    <input type="text" name="<?=DGTALIT_OPT?>[surface_color]" value="<?=esc_attr($o['surface_color'])?>" class="dg-color-picker">
                </div>
            </div>
            <div class="dg-grid-3">
                <div class="dg-field">
                    <label>Posición</label>
                    <div class="dg-radio-group">
                        <label class="dg-radio <?=$o['position']==='right'?'dg-radio--active':''?>">
                            <input type="radio" name="<?=DGTALIT_OPT?>[position]" value="right" <?=checked($o['position'],'right',false)?>> ↘ Derecha
                        </label>
                        <label class="dg-radio <?=$o['position']==='left'?'dg-radio--active':''?>">
                            <input type="radio" name="<?=DGTALIT_OPT?>[position]" value="left" <?=checked($o['position'],'left',false)?>> ↙ Izquierda
                        </label>
                    </div>
                </div>
                <div class="dg-field">
                    <label>Separación inferior (px)</label>
                    <input type="number" name="<?=DGTALIT_OPT?>[offset_bottom]" value="<?=esc_attr($o['offset_bottom'])?>">
                </div>
                <div class="dg-field">
                    <label>Separación lateral (px)</label>
                    <input type="number" name="<?=DGTALIT_OPT?>[offset_side]" value="<?=esc_attr($o['offset_side'])?>">
                </div>
                <div class="dg-field">
                    <label>Ancho panel (px)</label>
                    <input type="number" name="<?=DGTALIT_OPT?>[panel_width]" value="<?=esc_attr($o['panel_width'])?>">
                </div>
                <div class="dg-field">
                    <label>Alto panel (px)</label>
                    <input type="number" name="<?=DGTALIT_OPT?>[panel_height]" value="<?=esc_attr($o['panel_height'])?>">
                </div>
            </div>
        </div>
    </div>

    <div class="dg-card">
        <div class="dg-card__head"><span class="dg-card__icon">💾</span><h2>Almacenamiento</h2></div>
        <div class="dg-card__body">
            <div class="dg-field">
                <div class="dg-toggle-wrap">
                    <label class="dg-toggle">
                        <input type="checkbox" name="<?=DGTALIT_OPT?>[local_storage_enabled]" value="1" <?=checked($o['local_storage_enabled'],'1',false)?>>
                        <span class="dg-toggle__slider"></span>
                    </label>
                    <span class="dg-toggle__label">Guardar conversaciones y leads en WordPress</span>
                </div>
            </div>
        </div>
    </div>

    <div class="dg-form-footer">
        <button type="submit" class="dg-btn dg-btn--primary dg-btn--lg">
            <span class="dg-btn__icon">💾</span> Guardar cambios
        </button>
    </div>
    </form>
    </div>
    <?php
}

function dgtalit_page_bot() {
    $bo   = dgtalit_get_bot_opts();
    $saved = isset($_GET['settings-updated']) && $_GET['settings-updated'];
    ?>
    <div class="dg-wrap">
    <?php if ($saved): ?><div class="dg-notice dg-notice--success"><span class="dg-notice__icon">✓</span><strong>¡Configuración del bot guardada correctamente!</strong></div><?php endif; ?>
    <div class="dg-header">
        <div class="dg-header__icon">🤖</div>
        <div>
            <h1 class="dg-header__title">Configurar Bot</h1>
            <p class="dg-header__sub">Prompt, tono, flujo de conversación y captura de leads</p>
        </div>
    </div>
    <form method="post" action="options.php">
    <?php settings_fields('dgtalit_bot_settings'); ?>

    <!-- IDENTIDAD -->
    <div class="dg-card">
        <div class="dg-card__head"><span class="dg-card__icon">👤</span><h2>Identidad</h2></div>
        <div class="dg-card__body">
            <div class="dg-grid-3">
                <div class="dg-field">
                    <label>Nombre del asistente</label>
                    <input type="text" name="<?=DGTALIT_BOT_OPT?>[bot_name]" value="<?=esc_attr($bo['bot_name'])?>">
                </div>
                <div class="dg-field">
                    <label>Tono de comunicación</label>
                    <select name="<?=DGTALIT_BOT_OPT?>[tone]">
                        <?php foreach(['natural'=>'🗣 Natural (recomendado)','formal'=>'👔 Formal','friendly'=>'😊 Cercano / Amigable','technical'=>'⚙️ Técnico'] as $v=>$l): ?>
                        <option value="<?=esc_attr($v)?>" <?=selected($bo['tone'],$v,false)?>><?=esc_html($l)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="dg-field">
                    <label>Idioma</label>
                    <select name="<?=DGTALIT_BOT_OPT?>[language]">
                        <option value="es" <?=selected($bo['language'],'es',false)?>>🇪🇸 Español</option>
                        <option value="en" <?=selected($bo['language'],'en',false)?>>🇬🇧 English</option>
                        <option value="auto" <?=selected($bo['language'],'auto',false)?>>🌐 Auto (detectar)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- PROMPT -->
    <div class="dg-card">
        <div class="dg-card__head"><span class="dg-card__icon">📝</span><h2>Prompt del sistema</h2></div>
        <div class="dg-card__body">
            <div class="dg-info-box">Variables disponibles: <code>{company_name}</code>, <code>{bot_name}</code>, <code>{client_id}</code></div>
            <div class="dg-field">
                <label>System Prompt</label>
                <textarea name="<?=DGTALIT_BOT_OPT?>[system_prompt]" rows="8" class="dg-textarea--code"><?=esc_textarea($bo['system_prompt'])?></textarea>
            </div>
            <div class="dg-field">
                <label>Mensaje de bienvenida</label>
                <textarea name="<?=DGTALIT_BOT_OPT?>[welcome_message]" rows="3"><?=esc_textarea($bo['welcome_message'])?></textarea>
            </div>
        </div>
    </div>

    <!-- FLUJO GUIADO -->
    <div class="dg-card">
        <div class="dg-card__head">
            <span class="dg-card__icon">🗺️</span><h2>Flujo guiado de conversación</h2>
            <div class="dg-toggle-wrap" style="margin-left:auto">
                <label class="dg-toggle">
                    <input type="checkbox" name="<?=DGTALIT_BOT_OPT?>[flow_enabled]" value="1" <?=checked($bo['flow_enabled'],'1',false)?>>
                    <span class="dg-toggle__slider"></span>
                </label>
                <span class="dg-toggle__label">Activado</span>
            </div>
        </div>
        <div class="dg-card__body">
            <?php for($i=1;$i<=5;$i++): $s='step'.$i; ?>
            <div class="dg-step-block">
                <div class="dg-step-num"><?=$i?></div>
                <div class="dg-step-content">
                    <div class="dg-field">
                        <label>Nombre del paso</label>
                        <input type="text" name="<?=DGTALIT_BOT_OPT?>[<?=$s?>_label]" value="<?=esc_attr($bo[$s.'_label'])?>">
                    </div>
                    <div class="dg-field">
                        <label>Instrucción para el agente</label>
                        <textarea name="<?=DGTALIT_BOT_OPT?>[<?=$s?>_prompt]" rows="3"><?=esc_textarea($bo[$s.'_prompt'])?></textarea>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- COMPORTAMIENTO -->
    <div class="dg-card">
        <div class="dg-card__head"><span class="dg-card__icon">⚙️</span><h2>Comportamiento y reglas</h2></div>
        <div class="dg-card__body">
            <div class="dg-grid-2">
                <div class="dg-field">
                    <label>Máx. líneas por respuesta</label>
                    <input type="number" name="<?=DGTALIT_BOT_OPT?>[max_response_lines]" value="<?=esc_attr($bo['max_response_lines'])?>" min="1" max="20">
                </div>
            </div>
            <div class="dg-checks-grid">
                <?php $checks = ['one_question_per_msg'=>'Solo una pregunta por mensaje','no_repeat_openers'=>'No repetir el inicio de respuestas','no_personal_before_step4'=>'No pedir datos personales antes del paso 4','block_off_topic'=>'Bloquear preguntas fuera del ámbito']; ?>
                <?php foreach($checks as $k=>$l): ?>
                <div class="dg-check-item">
                    <label class="dg-toggle-inline">
                        <input type="checkbox" name="<?=DGTALIT_BOT_OPT?>[<?=$k?>]" value="1" <?=checked($bo[$k],'1',false)?>>
                        <span class="dg-toggle__slider"></span>
                        <span><?=esc_html($l)?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="dg-field">
                <label>Respuesta si el tema está fuera de ámbito</label>
                <textarea name="<?=DGTALIT_BOT_OPT?>[off_topic_reply]" rows="2"><?=esc_textarea($bo['off_topic_reply'])?></textarea>
            </div>
            <div class="dg-field">
                <label>Respuesta si no tiene información</label>
                <textarea name="<?=DGTALIT_BOT_OPT?>[unknown_info_reply]" rows="2"><?=esc_textarea($bo['unknown_info_reply'])?></textarea>
            </div>
        </div>
    </div>

    <!-- LEAD CAPTURE -->
    <div class="dg-card">
        <div class="dg-card__head">
            <span class="dg-card__icon">🎯</span><h2>Captura de leads</h2>
            <div class="dg-toggle-wrap" style="margin-left:auto">
                <label class="dg-toggle">
                    <input type="checkbox" name="<?=DGTALIT_BOT_OPT?>[lead_capture_enabled]" value="1" <?=checked($bo['lead_capture_enabled'],'1',false)?>>
                    <span class="dg-toggle__slider"></span>
                </label>
                <span class="dg-toggle__label">Activado</span>
            </div>
        </div>
        <div class="dg-card__body">
            <div class="dg-grid-2">
                <div class="dg-field">
                    <label>Palabra clave de trigger</label>
                    <input type="text" name="<?=DGTALIT_BOT_OPT?>[lead_trigger_word]" value="<?=esc_attr($bo['lead_trigger_word'])?>">
                </div>
            </div>
            <div class="dg-field">
                <label>Mensaje de confirmación al usuario</label>
                <textarea name="<?=DGTALIT_BOT_OPT?>[lead_confirm_msg]" rows="2"><?=esc_textarea($bo['lead_confirm_msg'])?></textarea>
                <p class="dg-hint">Usa <code>{nombre}</code> para el nombre del lead</p>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="dg-card">
        <div class="dg-card__head"><span class="dg-card__icon">🛡️</span><h2>Filtros de seguridad</h2></div>
        <div class="dg-card__body">
            <div class="dg-checks-grid">
                <?php $fchecks = ['filter_spam'=>'Filtrar spam y enlaces maliciosos','filter_insults'=>'Filtrar insultos y contenido inapropiado','filter_long_msg'=>'Filtrar mensajes demasiado largos']; ?>
                <?php foreach($fchecks as $k=>$l): ?>
                <div class="dg-check-item">
                    <label class="dg-toggle-inline">
                        <input type="checkbox" name="<?=DGTALIT_BOT_OPT?>[<?=$k?>]" value="1" <?=checked($bo[$k],'1',false)?>>
                        <span class="dg-toggle__slider"></span>
                        <span><?=esc_html($l)?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="dg-grid-3">
                <div class="dg-field">
                    <label>Longitud máxima (caracteres)</label>
                    <input type="number" name="<?=DGTALIT_BOT_OPT?>[max_msg_length]" value="<?=esc_attr($bo['max_msg_length'])?>">
                </div>
            </div>
            <div class="dg-grid-3">
                <?php foreach(['spam_reply'=>'Respuesta a spam','insult_reply'=>'Respuesta a insultos','long_msg_reply'=>'Respuesta a mensajes largos'] as $k=>$l): ?>
                <div class="dg-field">
                    <label><?=esc_html($l)?></label>
                    <textarea name="<?=DGTALIT_BOT_OPT?>[<?=$k?>]" rows="2"><?=esc_textarea($bo[$k])?></textarea>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- MODELO IA -->
    <div class="dg-card">
        <div class="dg-card__head"><span class="dg-card__icon">🧠</span><h2>Modelo de IA</h2></div>
        <div class="dg-card__body">
            <div class="dg-info-box">n8n leerá estos valores desde el endpoint <code>/bot-config</code> en cada conversación.</div>
            <div class="dg-grid-3">
                <div class="dg-field" style="grid-column:span 1">
                    <label>Modelo (OpenRouter)</label>
                    <input type="text" name="<?=DGTALIT_BOT_OPT?>[ai_model]" value="<?=esc_attr($bo['ai_model'])?>">
                </div>
                <div class="dg-field">
                    <label>Temperatura <span class="dg-hint-inline">(0–2)</span></label>
                    <input type="number" step="0.1" min="0" max="2" name="<?=DGTALIT_BOT_OPT?>[ai_temperature]" value="<?=esc_attr($bo['ai_temperature'])?>">
                </div>
                <div class="dg-field">
                    <label>Máx. tokens respuesta</label>
                    <input type="number" name="<?=DGTALIT_BOT_OPT?>[ai_max_tokens]" value="<?=esc_attr($bo['ai_max_tokens'])?>">
                </div>
            </div>
        </div>
    </div>

    <div class="dg-form-footer">
        <button type="submit" class="dg-btn dg-btn--primary dg-btn--lg">
            <span class="dg-btn__icon">💾</span> Guardar configuración del bot
        </button>
    </div>
    </form>
    </div>
    <?php
}

function dgtalit_page_convs() {
    dgtalit_install_tables();
    global $wpdb;
    $sel  = isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '';
    $page = max(1, absint($_GET['paged'] ?? 1));
    $per  = 20; $offset = ($page-1)*$per;
    $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dgtalit_sessions");
    $sessions = $wpdb->get_results($wpdb->prepare(
        "SELECT s.*, COUNT(m.id) AS mc FROM {$wpdb->prefix}dgtalit_sessions s
         LEFT JOIN {$wpdb->prefix}dgtalit_messages m ON m.session_id=s.session_id
         GROUP BY s.id ORDER BY s.updated_at DESC LIMIT %d OFFSET %d",$per,$offset));
    ?>
    <div class="dg-wrap">
    <div class="dg-header">
        <div class="dg-header__icon">💬</div>
        <div>
            <h1 class="dg-header__title">Conversaciones</h1>
            <p class="dg-header__sub"><?=number_format($total)?> sesiones registradas</p>
        </div>
    </div>
    <div class="dg-convs-layout">
        <div class="dg-convs-list">
            <?php foreach($sessions as $s):
                $isActive = $sel===$s->session_id; ?>
            <a href="<?=esc_url(admin_url('admin.php?page=dgtalit-chatbot-convs&session_id='.rawurlencode($s->session_id).'&paged='.$page))?>"
               class="dg-conv-item <?=$isActive?'dg-conv-item--active':''?>">
                <div class="dg-conv-item__header">
                    <span class="dg-conv-item__id"><?=esc_html(substr($s->session_id,0,16))?>…</span>
                    <span class="dg-conv-item__badge"><?=esc_html($s->mc)?></span>
                </div>
                <div class="dg-conv-item__date"><?=esc_html(date('d M, H:i',strtotime($s->updated_at)))?></div>
            </a>
            <?php endforeach; ?>
            <?php if(!$sessions): ?><div class="dg-empty">Sin conversaciones aún</div><?php endif; ?>
        </div>
        <div class="dg-convs-panel">
        <?php if(!$sel): ?>
            <div class="dg-convs-empty"><span>💬</span><p>Selecciona una conversación</p></div>
        <?php else:
            $msgs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}dgtalit_messages WHERE session_id=%s ORDER BY created_at ASC,id ASC",$sel));
            foreach($msgs as $m):
                $isU = $m->role==='user'; ?>
            <div class="dg-msg <?=$isU?'dg-msg--user':'dg-msg--bot'?>">
                <div class="dg-msg__bubble"><?=nl2br(esc_html($m->content))?></div>
                <div class="dg-msg__meta"><?=$isU?'👤 Usuario':'🤖 Asistente'?> · <?=esc_html(date('H:i',strtotime($m->created_at)))?></div>
            </div>
            <?php endforeach;
            if(!$msgs): ?><div class="dg-empty">Sin mensajes en esta sesión</div><?php endif;
        endif; ?>
        </div>
    </div>
    </div>
    <?php
}

function dgtalit_page_leads() {
    dgtalit_install_tables();
    global $wpdb;
    $page   = max(1,absint($_GET['paged']??1));
    $per    = 25; $offset = ($page-1)*$per;
    $total  = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dgtalit_leads");
    $leads  = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgtalit_leads ORDER BY created_at DESC LIMIT %d OFFSET %d",$per,$offset));
    $pages  = ceil($total/$per);
    ?>
    <div class="dg-wrap">
    <div class="dg-header">
        <div class="dg-header__icon">🎯</div>
        <div>
            <h1 class="dg-header__title">Leads</h1>
            <p class="dg-header__sub"><?=number_format($total)?> leads capturados</p>
        </div>
        <div style="margin-left:auto">
            <button class="dg-btn dg-btn--outline" id="dg-export-leads">⬇ Exportar CSV</button>
        </div>
    </div>
    <div class="dg-card" style="padding:0">
        <table class="dg-table">
            <thead><tr><th>Fecha</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Producto</th><th>Vía</th><th>Chat</th></tr></thead>
            <tbody>
            <?php foreach($leads as $l): ?>
            <tr>
                <td><span class="dg-date"><?=esc_html(date('d M Y',strtotime($l->created_at)))?></span></td>
                <td><strong><?=esc_html($l->nombre)?></strong></td>
                <td><?=esc_html($l->email)?></td>
                <td><?=esc_html($l->telefono)?></td>
                <td><span class="dg-tag"><?=esc_html($l->producto_interes)?></span></td>
                <td><?=esc_html($l->via_contacto)?></td>
                <td><a href="<?=esc_url(admin_url('admin.php?page=dgtalit-chatbot-convs&session_id='.rawurlencode($l->session_id)))?>" class="dg-link">Ver chat →</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$leads): ?><tr><td colspan="7" class="dg-empty">Sin leads aún</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($pages>1): ?>
    <div class="dg-pagination">
        <?php for($i=1;$i<=$pages;$i++): ?>
        <a href="<?=esc_url(admin_url('admin.php?page=dgtalit-chatbot-leads&paged='.$i))?>" class="dg-page <?=$i===$page?'dg-page--active':''?>"><?=$i?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    </div>
    <?php
}