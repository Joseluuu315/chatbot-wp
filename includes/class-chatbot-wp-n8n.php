<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Chatbot_WP_N8N_Standalone', false)) {
    return;
}

final class Chatbot_WP_N8N_Standalone
{
    const OPTION_NAME = 'cwp_n8n_options';
    const REST_NAMESPACE = 'chatbot-wp/v1';

    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate() {
        if (!get_option(self::OPTION_NAME)) {
            add_option(self::OPTION_NAME, self::defaults());
        }

        update_option('cwp_n8n_needs_tables', '1', false);
    }

    private function __construct()
    {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'maybe_create_tables'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_shortcode('chatbot_wp_n8n', array($this, 'render_shortcode'));
    }

    private static function defaults() {
        return array(
            'enabled' => '1',
            'chat_webhook_url' => '',
            'scraper_webhook_url' => '',
            'client_id' => wp_generate_uuid4(),
            'company_name' => get_bloginfo('name'),
            'auth_type' => 'none',
            'auth_token' => '',
            'auth_header_name' => 'X-Chatbot-Token',
            'local_storage_enabled' => '1',
            'retention_days' => '90',
            'title' => 'Asistente virtual',
            'subtitle' => 'Normalmente responde en unos segundos',
            'initial_message' => 'Hola, soy el asistente virtual. Â¿En quÃ© puedo ayudarte?',
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
            'surface_color' => '#ffffff',
            'launcher_icon' => 'message',
            'show_branding' => '0',
        );
    }

    private static function options() {
        return wp_parse_args(get_option(self::OPTION_NAME, array()), self::defaults());
    }

    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $sessions = $wpdb->prefix . 'cwp_chat_sessions';
        $messages = $wpdb->prefix . 'cwp_chat_messages';
        $leads = $wpdb->prefix . 'cwp_chat_leads';

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$sessions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(191) NOT NULL,
            client_id VARCHAR(191) NOT NULL DEFAULT '',
            company_name VARCHAR(191) NOT NULL DEFAULT '',
            ip_hash VARCHAR(64) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_id (session_id),
            KEY client_id (client_id)
        ) {$charset_collate};");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$messages} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(191) NOT NULL,
            role VARCHAR(20) NOT NULL,
            content LONGTEXT NOT NULL,
            raw_payload LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY role (role)
        ) {$charset_collate};");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$leads} (
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
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY client_id (client_id),
            KEY email (email)
        ) {$charset_collate};");
    }

    public function maybe_create_tables()
    {
        if (get_option('cwp_n8n_needs_tables') !== '1') {
            return;
        }

        self::create_tables();
        delete_option('cwp_n8n_needs_tables');
    }

    public function register_admin_menu() {
        add_menu_page(
            'Chatbot WP n8n',
            'Chatbot n8n',
            'manage_options',
            'chatbot-wp-n8n',
            array($this, 'render_settings_page'),
            'dashicons-format-chat',
            56
        );

        add_submenu_page(
            'chatbot-wp-n8n',
            'Ajustes',
            'Ajustes',
            'manage_options',
            'chatbot-wp-n8n',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'chatbot-wp-n8n',
            'Conversaciones',
            'Conversaciones',
            'manage_options',
            'chatbot-wp-n8n-conversations',
            array($this, 'render_conversations_page')
        );

        add_submenu_page(
            'chatbot-wp-n8n',
            'Leads',
            'Leads',
            'manage_options',
            'chatbot-wp-n8n-leads',
            array($this, 'render_leads_page')
        );
    }

    public function register_settings() {
        register_setting('cwp_n8n_settings', self::OPTION_NAME, array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_options'),
            'default' => self::defaults(),
        ));
    }

    public function sanitize_options($input) {
        $defaults = self::defaults();
        $input = is_array($input) ? $input : array();

        $clean = array();
        $clean['enabled'] = empty($input['enabled']) ? '0' : '1';
        $clean['chat_webhook_url'] = esc_url_raw($input['chat_webhook_url'] ?? '');
        $clean['scraper_webhook_url'] = esc_url_raw($input['scraper_webhook_url'] ?? '');
        $clean['client_id'] = sanitize_text_field($input['client_id'] ?? $defaults['client_id']);
        $clean['company_name'] = sanitize_text_field($input['company_name'] ?? $defaults['company_name']);
        $clean['auth_type'] = in_array(($input['auth_type'] ?? 'none'), array('none', 'bearer', 'header'), true) ? $input['auth_type'] : 'none';
        $clean['auth_token'] = sanitize_text_field($input['auth_token'] ?? '');
        $clean['auth_header_name'] = sanitize_key(str_replace('-', '_', $input['auth_header_name'] ?? $defaults['auth_header_name']));
        $clean['auth_header_name'] = str_replace('_', '-', $clean['auth_header_name']) ?: $defaults['auth_header_name'];
        $clean['local_storage_enabled'] = empty($input['local_storage_enabled']) ? '0' : '1';
        $clean['retention_days'] = (string) max(1, min(3650, absint($input['retention_days'] ?? $defaults['retention_days'])));
        $clean['title'] = sanitize_text_field($input['title'] ?? $defaults['title']);
        $clean['subtitle'] = sanitize_text_field($input['subtitle'] ?? $defaults['subtitle']);
        $clean['initial_message'] = sanitize_textarea_field($input['initial_message'] ?? $defaults['initial_message']);
        $clean['placeholder'] = sanitize_text_field($input['placeholder'] ?? $defaults['placeholder']);
        $clean['launcher_label'] = sanitize_text_field($input['launcher_label'] ?? $defaults['launcher_label']);
        $clean['position'] = in_array(($input['position'] ?? 'right'), array('right', 'left'), true) ? $input['position'] : 'right';
        $clean['offset_bottom'] = (string) max(0, min(240, absint($input['offset_bottom'] ?? $defaults['offset_bottom'])));
        $clean['offset_side'] = (string) max(0, min(240, absint($input['offset_side'] ?? $defaults['offset_side'])));
        $clean['panel_width'] = (string) max(300, min(720, absint($input['panel_width'] ?? $defaults['panel_width'])));
        $clean['panel_height'] = (string) max(360, min(820, absint($input['panel_height'] ?? $defaults['panel_height'])));
        $clean['primary_color'] = sanitize_hex_color($input['primary_color'] ?? $defaults['primary_color']) ?: $defaults['primary_color'];
        $clean['accent_color'] = sanitize_hex_color($input['accent_color'] ?? $defaults['accent_color']) ?: $defaults['accent_color'];
        $clean['text_color'] = sanitize_hex_color($input['text_color'] ?? $defaults['text_color']) ?: $defaults['text_color'];
        $clean['surface_color'] = sanitize_hex_color($input['surface_color'] ?? $defaults['surface_color']) ?: $defaults['surface_color'];
        $clean['launcher_icon'] = in_array(($input['launcher_icon'] ?? 'message'), array('message', 'spark', 'help'), true) ? $input['launcher_icon'] : 'message';
        $clean['show_branding'] = empty($input['show_branding']) ? '0' : '1';

        return $clean;
    }

    public function enqueue_frontend() {
        $options = self::options();
        if ($options['enabled'] !== '1' || empty($options['chat_webhook_url'])) {
            return;
        }

        wp_enqueue_style('cwp-n8n-chatbot', CWP_N8N_STANDALONE_URL . 'assets/chatbot.css', array(), CWP_N8N_STANDALONE_VERSION);
        wp_enqueue_script('cwp-n8n-chatbot', CWP_N8N_STANDALONE_URL . 'assets/chatbot.js', array(), CWP_N8N_STANDALONE_VERSION, true);

        wp_localize_script('cwp-n8n-chatbot', 'CWP_N8N_CHATBOT', array(
            'restUrl' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/chat')),
            'nonce' => wp_create_nonce('wp_rest'),
            'clientId' => $options['client_id'],
            'companyName' => $options['company_name'],
            'settings' => array(
                'title' => $options['title'],
                'subtitle' => $options['subtitle'],
                'initialMessage' => $options['initial_message'],
                'placeholder' => $options['placeholder'],
                'launcherLabel' => $options['launcher_label'],
                'position' => $options['position'],
                'offsetBottom' => absint($options['offset_bottom']),
                'offsetSide' => absint($options['offset_side']),
                'panelWidth' => absint($options['panel_width']),
                'panelHeight' => absint($options['panel_height']),
                'primaryColor' => $options['primary_color'],
                'accentColor' => $options['accent_color'],
                'textColor' => $options['text_color'],
                'surfaceColor' => $options['surface_color'],
                'launcherIcon' => $options['launcher_icon'],
                'showBranding' => $options['show_branding'] === '1',
            ),
        ));
    }

    public function register_rest_routes() {
        register_rest_route(self::REST_NAMESPACE, '/chat', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_chat'),
            'permission_callback' => '__return_true',
            'args' => array(
                'message' => array('required' => true, 'type' => 'string'),
                'session_id' => array('required' => false, 'type' => 'string'),
            ),
        ));

        register_rest_route(self::REST_NAMESPACE, '/scrape', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_scrape'),
            'permission_callback' => array($this, 'can_manage_options'),
        ));
    }

    public function can_manage_options()
    {
        return current_user_can('manage_options');
    }

    public function handle_chat($request)
    {
        $options = self::options();
        if ($options['enabled'] !== '1' || empty($options['chat_webhook_url'])) {
            return new WP_Error('cwp_not_configured', 'El chatbot no estÃ¡ configurado.', array('status' => 400));
        }

        $message = trim(wp_strip_all_tags((string) $request->get_param('message')));
        if ($message === '') {
            return new WP_Error('cwp_empty_message', 'El mensaje estÃ¡ vacÃ­o.', array('status' => 400));
        }

        if (strlen($message) > 1200) {
            return new WP_Error('cwp_message_too_long', 'El mensaje es demasiado largo.', array('status' => 413));
        }

        $session_id = sanitize_text_field((string) $request->get_param('session_id'));
        if ($session_id === '') {
            $session_id = wp_generate_uuid4();
        }

        $payload = array(
            'chatInput' => $message,
            'message' => $message,
            'sessionId' => $session_id,
            'client_id' => $options['client_id'],
            'clientId' => $options['client_id'],
            'company_name' => $options['company_name'],
            'companyName' => $options['company_name'],
            'metadata' => array(
                'source' => 'wordpress',
                'site_url' => home_url('/'),
                'page_url' => esc_url_raw((string) $request->get_param('page_url')),
            ),
        );

        $this->store_session($session_id, $options);
        $this->store_message($session_id, 'user', $message, $payload);

        $response = wp_remote_post($options['chat_webhook_url'], array(
            'timeout' => 45,
            'headers' => $this->build_headers($options),
            'body' => wp_json_encode($payload),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('cwp_n8n_error', $response->get_error_message(), array('status' => 502));
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        $reply = $this->extract_reply($decoded, $body);

        if ($status < 200 || $status >= 300) {
            return new WP_Error('cwp_n8n_bad_status', 'n8n devolviÃ³ una respuesta no vÃ¡lida.', array(
                'status' => 502,
                'n8n_status' => $status,
                'body' => $body,
            ));
        }

        $this->store_message($session_id, 'assistant', $reply, is_array($decoded) ? $decoded : array('raw' => $body));
        $this->store_lead_from_response($session_id, is_array($decoded) ? $decoded : array());

        return rest_ensure_response(array(
            'ok' => true,
            'session_id' => $session_id,
            'reply' => $reply,
            'raw' => $decoded,
        ));
    }

    public function handle_scrape($request)
    {
        $options = self::options();
        if (empty($options['scraper_webhook_url'])) {
            return new WP_Error('cwp_scraper_not_configured', 'El webhook de scrapeo no estÃ¡ configurado.', array('status' => 400));
        }

        $urls = $request->get_param('urls');
        if (is_string($urls)) {
            $urls = preg_split('/[\r\n,]+/', $urls);
        }

        $urls = array_values(array_filter(array_map('esc_url_raw', (array) $urls)));
        if (empty($urls)) {
            return new WP_Error('cwp_scraper_empty_urls', 'Indica al menos una URL.', array('status' => 400));
        }

        $payload = array(
            'urls' => $urls,
            'client_id' => $options['client_id'],
            'company_name' => $options['company_name'],
        );

        $response = wp_remote_post($options['scraper_webhook_url'], array(
            'timeout' => 90,
            'headers' => $this->build_headers($options),
            'body' => wp_json_encode($payload),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('cwp_scraper_error', $response->get_error_message(), array('status' => 502));
        }

        $body = wp_remote_retrieve_body($response);

        return rest_ensure_response(array(
            'ok' => wp_remote_retrieve_response_code($response) >= 200 && wp_remote_retrieve_response_code($response) < 300,
            'status' => wp_remote_retrieve_response_code($response),
            'body' => json_decode($body, true) ?: $body,
        ));
    }

    private function build_headers($options) {
        $headers = array('Content-Type' => 'application/json');

        if (!empty($options['auth_token']) && $options['auth_type'] === 'bearer') {
            $headers['Authorization'] = 'Bearer ' . $options['auth_token'];
        }

        if (!empty($options['auth_token']) && $options['auth_type'] === 'header') {
            $headers[$options['auth_header_name']] = $options['auth_token'];
        }

        return $headers;
    }

    private function extract_reply($decoded, $raw) {
        if (is_array($decoded)) {
            foreach (array('output', 'reply', 'message', 'text') as $key) {
                if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                    return wp_strip_all_tags($decoded[$key]);
                }
            }

            if (isset($decoded[0]) && is_array($decoded[0])) {
                return $this->extract_reply($decoded[0], $raw);
            }
        }

        $raw = trim(wp_strip_all_tags($raw));

        return $raw !== '' ? $raw : 'Solicitud recibida. Â¿En quÃ© mÃ¡s puedo ayudarte?';
    }

    private function store_session($session_id, $options) {
        if ($options['local_storage_enabled'] !== '1') {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cwp_chat_sessions';
        $now = current_time('mysql');
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        $ua = substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (session_id, client_id, company_name, ip_hash, user_agent, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at), company_name = VALUES(company_name)",
            $session_id,
            $options['client_id'],
            $options['company_name'],
            hash('sha256', $ip . wp_salt('auth')),
            $ua,
            $now,
            $now
        ));
    }

    private function store_message($session_id, $role, $content, $payload) {
        $options = self::options();
        if ($options['local_storage_enabled'] !== '1') {
            return;
        }

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'cwp_chat_messages',
            array(
                'session_id' => $session_id,
                'role' => $role,
                'content' => $content,
                'raw_payload' => wp_json_encode($payload),
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }

    private function store_lead_from_response($session_id, $response) {
        $options = self::options();
        if ($options['local_storage_enabled'] !== '1') {
            return;
        }

        $lead = $this->find_lead_payload($response);
        if (empty($lead) || !is_array($lead)) {
            return;
        }

        $email = sanitize_email($lead['email'] ?? '');
        $telefono = sanitize_text_field($lead['telefono'] ?? $lead['phone'] ?? '');
        if ($email === '' && $telefono === '') {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cwp_chat_leads';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE session_id = %s AND (email = %s OR telefono = %s) LIMIT 1",
            $session_id,
            $email,
            $telefono
        ));

        $data = array(
            'session_id' => $session_id,
            'client_id' => $options['client_id'],
            'company_name' => $options['company_name'],
            'nombre' => sanitize_text_field($lead['nombre'] ?? $lead['name'] ?? ''),
            'email' => $email,
            'telefono' => $telefono,
            'producto_interes' => sanitize_text_field($lead['producto_interes'] ?? $lead['subject'] ?? ''),
            'via_contacto' => sanitize_text_field($lead['via_contacto'] ?? ''),
            'contexto' => sanitize_textarea_field($lead['contexto'] ?? ''),
            'raw_payload' => wp_json_encode($lead),
            'created_at' => current_time('mysql'),
        );

        if ($existing) {
            $wpdb->update($table, $data, array('id' => absint($existing)));
            return;
        }

        $wpdb->insert($table, $data);
    }

    private function find_lead_payload($payload)
    {
        if (!empty($payload['lead']) && is_array($payload['lead'])) {
            return $payload['lead'];
        }

        if (isset($payload[0]) && is_array($payload[0])) {
            return $this->find_lead_payload($payload[0]);
        }

        if (!empty($payload['json']) && is_array($payload['json'])) {
            return $this->find_lead_payload($payload['json']);
        }

        return null;
    }

    public function render_shortcode() {
        return '<div data-cwp-n8n-shortcode></div>';
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = self::options();
        ?>
        <div class="wrap">
            <h1>Chatbot WP n8n</h1>
            <form method="post" action="options.php">
                <?php settings_fields('cwp_n8n_settings'); ?>
                <h2>ConexiÃ³n con n8n</h2>
                <table class="form-table" role="presentation">
                    <?php $this->field_checkbox('enabled', 'Activar widget', $options); ?>
                    <?php $this->field_url('chat_webhook_url', 'Webhook del chat', $options, 'URL production/test del Chat Trigger de n8n.'); ?>
                    <?php $this->field_url('scraper_webhook_url', 'Webhook de scrapeo', $options, 'Ejemplo: https://tu-n8n/webhook/scraper-run'); ?>
                    <?php $this->field_text('client_id', 'Client ID', $options, 'Debe coincidir con el cliente usado en Supabase/vector store.'); ?>
                    <?php $this->field_text('company_name', 'Nombre de empresa', $options); ?>
                    <tr>
                        <th scope="row"><label for="cwp_auth_type">AutenticaciÃ³n webhook</label></th>
                        <td>
                            <select id="cwp_auth_type" name="<?php echo esc_attr(self::OPTION_NAME); ?>[auth_type]">
                                <option value="none" <?php selected($options['auth_type'], 'none'); ?>>Sin autenticaciÃ³n</option>
                                <option value="bearer" <?php selected($options['auth_type'], 'bearer'); ?>>Bearer token</option>
                                <option value="header" <?php selected($options['auth_type'], 'header'); ?>>Cabecera personalizada</option>
                            </select>
                        </td>
                    </tr>
                    <?php $this->field_text('auth_header_name', 'Nombre de cabecera', $options); ?>
                    <?php $this->field_password('auth_token', 'Token secreto', $options); ?>
                </table>

                <h2>Base de datos local</h2>
                <table class="form-table" role="presentation">
                    <?php $this->field_checkbox('local_storage_enabled', 'Guardar copia en WordPress', $options); ?>
                    <?php $this->field_number('retention_days', 'RetenciÃ³n de mensajes (dÃ­as)', $options); ?>
                </table>

                <h2>Contenido y estilos</h2>
                <table class="form-table" role="presentation">
                    <?php $this->field_text('title', 'TÃ­tulo', $options); ?>
                    <?php $this->field_text('subtitle', 'SubtÃ­tulo', $options); ?>
                    <?php $this->field_textarea('initial_message', 'Primer mensaje', $options); ?>
                    <?php $this->field_text('placeholder', 'Placeholder', $options); ?>
                    <?php $this->field_text('launcher_label', 'Texto del botÃ³n', $options); ?>
                    <tr>
                        <th scope="row"><label for="cwp_position">PosiciÃ³n</label></th>
                        <td>
                            <select id="cwp_position" name="<?php echo esc_attr(self::OPTION_NAME); ?>[position]">
                                <option value="right" <?php selected($options['position'], 'right'); ?>>Abajo derecha</option>
                                <option value="left" <?php selected($options['position'], 'left'); ?>>Abajo izquierda</option>
                            </select>
                        </td>
                    </tr>
                    <?php $this->field_number('offset_bottom', 'SeparaciÃ³n inferior (px)', $options); ?>
                    <?php $this->field_number('offset_side', 'SeparaciÃ³n lateral (px)', $options); ?>
                    <?php $this->field_number('panel_width', 'Ancho panel (px)', $options); ?>
                    <?php $this->field_number('panel_height', 'Alto panel (px)', $options); ?>
                    <?php $this->field_color('primary_color', 'Color principal', $options); ?>
                    <?php $this->field_color('accent_color', 'Color acento', $options); ?>
                    <?php $this->field_color('text_color', 'Color texto', $options); ?>
                    <?php $this->field_color('surface_color', 'Color superficie', $options); ?>
                    <?php $this->field_checkbox('show_branding', 'Mostrar branding del plugin', $options); ?>
                </table>
                <?php submit_button(); ?>
            </form>
            <hr>
            <h2>Scrapeo de conocimiento</h2>
            <p>EnvÃ­a URLs al workflow <code>scraper-run</code> usando el <code>client_id</code> y nombre de empresa configurados arriba.</p>
            <textarea id="cwp-scrape-urls" class="large-text" rows="4" placeholder="https://empresa.com&#10;https://empresa.com/servicios"></textarea>
            <p>
                <button type="button" class="button button-secondary" id="cwp-run-scrape">Lanzar scrapeo</button>
                <span id="cwp-scrape-result" style="margin-left:8px;"></span>
            </p>
            <script>
                (function () {
                    const button = document.getElementById('cwp-run-scrape');
                    const urls = document.getElementById('cwp-scrape-urls');
                    const result = document.getElementById('cwp-scrape-result');
                    if (!button || !urls || !result) return;

                    button.addEventListener('click', async function () {
                        button.disabled = true;
                        result.textContent = 'Enviando...';

                        try {
                            const response = await fetch(<?php echo wp_json_encode(rest_url(self::REST_NAMESPACE . '/scrape')); ?>, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>
                                },
                                body: JSON.stringify({ urls: urls.value })
                            });
                            const data = await response.json();
                            if (!response.ok) throw new Error(data.message || 'Error al lanzar el scrapeo.');
                            result.textContent = data.ok ? 'Scrapeo lanzado correctamente.' : 'n8n respondiÃ³ con estado ' + data.status + '.';
                        } catch (error) {
                            result.textContent = error.message || 'No se pudo lanzar el scrapeo.';
                        } finally {
                            button.disabled = false;
                        }
                    });
                })();
            </script>
        </div>
        <?php
    }

    public function render_conversations_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $sessions_table = $wpdb->prefix . 'cwp_chat_sessions';
        $messages_table = $wpdb->prefix . 'cwp_chat_messages';
        $selected_session = sanitize_text_field(wp_unslash($_GET['session_id'] ?? ''));

        $sessions = $wpdb->get_results(
            "SELECT s.*, COUNT(m.id) AS message_count
            FROM {$sessions_table} s
            LEFT JOIN {$messages_table} m ON m.session_id = s.session_id
            GROUP BY s.id
            ORDER BY s.updated_at DESC
            LIMIT 100"
        );

        ?>
        <div class="wrap">
            <h1>Conversaciones</h1>
            <p>Historial local guardado por el plugin. n8n/Supabase pueden tener su propio historial adicional.</p>
            <div style="display:grid;grid-template-columns:minmax(280px,360px) 1fr;gap:20px;align-items:start;">
                <table class="widefat striped">
                    <thead><tr><th>SesiÃ³n</th><th>Mensajes</th><th>Ãšltima actividad</th></tr></thead>
                    <tbody>
                    <?php if (empty($sessions)) : ?>
                        <tr><td colspan="3">TodavÃ­a no hay conversaciones guardadas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($sessions as $session) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-wp-n8n-conversations&session_id=' . rawurlencode($session->session_id))); ?>">
                                    <?php echo esc_html($this->short_session($session->session_id)); ?>
                                </a>
                                <br><small><?php echo esc_html($session->company_name); ?></small>
                            </td>
                            <td><?php echo esc_html((string) $session->message_count); ?></td>
                            <td><?php echo esc_html($session->updated_at); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="postbox" style="padding:16px;margin:0;">
                    <?php if ($selected_session === '') : ?>
                        <h2>Selecciona una conversaciÃ³n</h2>
                        <p>Abre una sesiÃ³n de la lista para ver el transcript completo.</p>
                    <?php else : ?>
                        <h2>Transcript: <?php echo esc_html($this->short_session($selected_session)); ?></h2>
                        <?php
                        $messages = $wpdb->get_results($wpdb->prepare(
                            "SELECT role, content, created_at FROM {$messages_table} WHERE session_id = %s ORDER BY created_at ASC, id ASC",
                            $selected_session
                        ));
                        ?>
                        <?php if (empty($messages)) : ?>
                            <p>No hay mensajes para esta sesiÃ³n.</p>
                        <?php endif; ?>
                        <?php foreach ($messages as $message) : ?>
                            <div style="margin:0 0 12px;padding:12px;border-left:4px solid <?php echo $message->role === 'user' ? '#2563eb' : '#16a34a'; ?>;background:#fff;border-radius:4px;">
                                <strong><?php echo esc_html($message->role === 'user' ? 'Usuario' : 'Asistente'); ?></strong>
                                <small style="color:#646970;margin-left:8px;"><?php echo esc_html($message->created_at); ?></small>
                                <div style="margin-top:8px;white-space:pre-wrap;"><?php echo esc_html($message->content); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_leads_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cwp_chat_leads';
        $leads = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200");

        ?>
        <div class="wrap">
            <h1>Leads</h1>
            <p>Leads detectados en las respuestas de n8n y guardados localmente en WordPress.</p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>TelÃ©fono</th>
                        <th>InterÃ©s</th>
                        <th>VÃ­a</th>
                        <th>ConversaciÃ³n</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($leads)) : ?>
                    <tr><td colspan="7">TodavÃ­a no hay leads guardados.</td></tr>
                <?php endif; ?>
                <?php foreach ($leads as $lead) : ?>
                    <tr>
                        <td><?php echo esc_html($lead->created_at); ?></td>
                        <td><?php echo esc_html($lead->nombre); ?></td>
                        <td><a href="mailto:<?php echo esc_attr($lead->email); ?>"><?php echo esc_html($lead->email); ?></a></td>
                        <td><?php echo esc_html($lead->telefono); ?></td>
                        <td><?php echo esc_html($lead->producto_interes); ?></td>
                        <td><?php echo esc_html($lead->via_contacto); ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-wp-n8n-conversations&session_id=' . rawurlencode($lead->session_id))); ?>">
                                Ver chat
                            </a>
                        </td>
                    </tr>
                    <?php if (!empty($lead->contexto)) : ?>
                        <tr>
                            <td></td>
                            <td colspan="6"><small><?php echo esc_html($lead->contexto); ?></small></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function short_session($session_id) {
        return strlen($session_id) > 18 ? substr($session_id, 0, 18) . '...' : $session_id;
    }

    private function option_name($key) {
        return self::OPTION_NAME . '[' . $key . ']';
    }

    private function field_text($key, $label, $options, $help = '') {
        printf(
            '<tr><th scope="row"><label for="cwp_%1$s">%2$s</label></th><td><input class="regular-text" id="cwp_%1$s" name="%3$s" type="text" value="%4$s">%5$s</td></tr>',
            esc_attr($key),
            esc_html($label),
            esc_attr($this->option_name($key)),
            esc_attr($options[$key] ?? ''),
            $help ? '<p class="description">' . esc_html($help) . '</p>' : ''
        );
    }

    private function field_password($key, $label, $options) {
        printf(
            '<tr><th scope="row"><label for="cwp_%1$s">%2$s</label></th><td><input class="regular-text" id="cwp_%1$s" name="%3$s" type="password" value="%4$s" autocomplete="new-password"></td></tr>',
            esc_attr($key),
            esc_html($label),
            esc_attr($this->option_name($key)),
            esc_attr($options[$key] ?? '')
        );
    }

    private function field_url($key, $label, $options, $help = '') {
        printf(
            '<tr><th scope="row"><label for="cwp_%1$s">%2$s</label></th><td><input class="large-text" id="cwp_%1$s" name="%3$s" type="url" value="%4$s">%5$s</td></tr>',
            esc_attr($key),
            esc_html($label),
            esc_attr($this->option_name($key)),
            esc_attr($options[$key] ?? ''),
            $help ? '<p class="description">' . esc_html($help) . '</p>' : ''
        );
    }

    private function field_textarea($key, $label, $options) {
        printf(
            '<tr><th scope="row"><label for="cwp_%1$s">%2$s</label></th><td><textarea class="large-text" rows="3" id="cwp_%1$s" name="%3$s">%4$s</textarea></td></tr>',
            esc_attr($key),
            esc_html($label),
            esc_attr($this->option_name($key)),
            esc_textarea($options[$key] ?? '')
        );
    }

    private function field_number($key, $label, $options) {
        printf(
            '<tr><th scope="row"><label for="cwp_%1$s">%2$s</label></th><td><input id="cwp_%1$s" name="%3$s" type="number" min="0" value="%4$s"></td></tr>',
            esc_attr($key),
            esc_html($label),
            esc_attr($this->option_name($key)),
            esc_attr($options[$key] ?? '')
        );
    }

    private function field_color($key, $label, $options) {
        printf(
            '<tr><th scope="row"><label for="cwp_%1$s">%2$s</label></th><td><input id="cwp_%1$s" name="%3$s" type="color" value="%4$s"></td></tr>',
            esc_attr($key),
            esc_html($label),
            esc_attr($this->option_name($key)),
            esc_attr($options[$key] ?? '')
        );
    }

    private function field_checkbox($key, $label, $options) {
        printf(
            '<tr><th scope="row">%2$s</th><td><label><input name="%3$s" type="checkbox" value="1" %4$s> SÃ­</label></td></tr>',
            esc_attr($key),
            esc_html($label),
            esc_attr($this->option_name($key)),
            checked(($options[$key] ?? '0'), '1', false)
        );
    }
}




