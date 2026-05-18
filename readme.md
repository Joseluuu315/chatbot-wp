## Chatbot WP n8n

Plugin de WordPress para insertar un chatbot flotante conectado a los workflows de `flujos-n8n/`.

### Qué incluye

- Widget flotante configurable abajo a la derecha o izquierda.
- Proxy REST en WordPress para no exponer el webhook de n8n en el navegador.
- Ajustes de `client_id`, `company_name`, webhooks, autenticación, textos iniciales, colores y dimensiones.
- Almacenamiento local opcional en tablas de WordPress: sesiones, mensajes y leads.
- Pestañas de administración para revisar conversaciones y leads capturados.
- Endpoint admin para lanzar el workflow de scrapeo si se configura `scraper-run`.
- Flujos n8n actualizados para usar variables de entorno en secretos sensibles.

### Instalación

1. Copia esta carpeta a `wp-content/plugins/chatbot-wp-n8n`.
2. Activa el plugin en WordPress.
3. Ve a `Ajustes > Chatbot n8n`.
4. Configura el webhook del chat de n8n, el webhook de scrapeo, `client_id` y `company_name`.

### Contrato con n8n

El plugin envía al webhook del chat:

```json
{
  "chatInput": "mensaje del usuario",
  "message": "mensaje del usuario",
  "sessionId": "uuid-de-sesion",
  "client_id": "cliente",
  "clientId": "cliente",
  "company_name": "Empresa",
  "companyName": "Empresa",
  "metadata": {
    "source": "wordpress",
    "site_url": "https://web.com/",
    "page_url": "https://web.com/pagina"
  }
}
```

El plugin espera que n8n responda con `output`, `reply`, `message` o `text`.

### Variables de entorno n8n

El flujo `flujos-n8n/chatbot.json` ahora espera estas variables en n8n:

```bash
SUPABASE_URL=https://tu-proyecto.supabase.co
SUPABASE_SERVICE_ROLE_KEY=...
```

### Base de datos

El plugin puede guardar una copia local en WordPress. Los workflows siguen usando Supabase para conocimiento, historial, leads y transcripciones.

Cuando n8n detecta un lead, lo registra en Supabase y lo devuelve al plugin para que aparezca en `Chatbot n8n > Leads`.

El SQL base para Supabase está en `docs/supabase-schema.sql`.
