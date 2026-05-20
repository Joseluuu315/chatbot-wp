(function () {
  /* ── Bootstrap: el PHP inyecta window.DGTALIT = { rest, nonce, cfg } ── */
  var D = window.DGTALIT;
  if (!D || !D.rest) return;

  var cfg      = D.cfg  || {};
  var REST_URL = D.rest;
  var NONCE    = D.nonce || '';

  /* ── Sesión (sessionStorage: se limpia al cerrar pestaña) ── */
  var SK = 'dgtalit_sid';
  var sid;
  try { sid = sessionStorage.getItem(SK); } catch (e) { sid = null; }
  if (!sid) {
    sid = 'wp-' + Date.now() + '-' + Math.random().toString(16).slice(2, 10);
    try { sessionStorage.setItem(SK, sid); } catch (e) {}
  }

  /* ── Helpers ────────────────────────────────────────────── */
  function el(tag, cls) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    return n;
  }
  function svg(path, extra) {
    /* Devuelve un <svg> inline con el path indicado */
    var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    s.setAttribute('viewBox', '0 0 24 24');
    s.setAttribute('fill', 'none');
    s.setAttribute('stroke', 'currentColor');
    s.setAttribute('stroke-width', extra || '2');
    s.setAttribute('stroke-linecap', 'round');
    s.setAttribute('stroke-linejoin', 'round');
    s.innerHTML = path;
    return s;
  }
  function esc(s) {
    return String(s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
  /* Markdown mínimo: **bold**, *italic*, saltos de línea */
  function renderText(s) {
    return esc(s)
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.+?)\*/g,     '<em>$1</em>')
      .replace(/\n/g,            '<br>');
  }

  /* ── Raíz del widget ────────────────────────────────────── */
  var root = el('div', 'dgtalit-chat');
  root.classList.add(cfg.position === 'left' ? 'dgtalit-chat--left' : 'dgtalit-chat--right');

  /* Colores desde cfg (inyectados por PHP) */
  var cv = root.style;
  cv.setProperty('--dg-primary',  cfg.primary   || '#1a56db');
  cv.setProperty('--dg-accent',   cfg.accent    || '#16a34a');
  cv.setProperty('--dg-text',     cfg.textColor || '#0f172a');
  cv.setProperty('--dg-surface',  cfg.surface   || '#ffffff');
  cv.setProperty('--dg-bottom',   (cfg.offsetBottom || 24) + 'px');
  cv.setProperty('--dg-side',     (cfg.offsetSide   || 24) + 'px');
  cv.setProperty('--dg-width',    (cfg.panelW       || 380) + 'px');
  cv.setProperty('--dg-height',   (cfg.panelH       || 580) + 'px');

  /* ── Panel ──────────────────────────────────────────────── */
  var panel = el('section', 'dgtalit-panel');
  panel.setAttribute('aria-label', cfg.title || 'Chat');
  panel.setAttribute('role', 'dialog');

  /* Header */
  var header    = el('header', 'dgtalit-header');

  var avatarWrap = el('div', 'dgtalit-header__avatar');
  var avatarBg   = el('div', 'dgtalit-header__avatar-bg');
  avatarBg.appendChild(svg('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'));
  var onlineDot  = el('span', 'dgtalit-header__online');
  onlineDot.setAttribute('aria-hidden', 'true');
  avatarWrap.appendChild(avatarBg);
  avatarWrap.appendChild(onlineDot);

  var info     = el('div', 'dgtalit-header__info');
  var titleEl  = el('h2', 'dgtalit-header__title');
  titleEl.textContent = cfg.title || 'Asistente virtual';
  var subtitleEl = el('p', 'dgtalit-header__subtitle');
  subtitleEl.textContent = cfg.subtitle || '';
  info.appendChild(titleEl);
  info.appendChild(subtitleEl);

  var closeBtn = el('button', 'dgtalit-close');
  closeBtn.type = 'button';
  closeBtn.setAttribute('aria-label', 'Cerrar chat');
  closeBtn.appendChild(svg('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>', '2.5'));

  header.appendChild(avatarWrap);
  header.appendChild(info);
  header.appendChild(closeBtn);

  /* Área de mensajes */
  var messagesEl = el('div', 'dgtalit-messages');
  messagesEl.setAttribute('role', 'log');
  messagesEl.setAttribute('aria-live', 'polite');
  messagesEl.setAttribute('aria-label', 'Mensajes del chat');

  /* Typing */
  var typingEl = el('div', 'dgtalit-typing');
  typingEl.hidden = true;
  var typingWrap = el('div', 'dgtalit-typing__wrap');
  typingWrap.setAttribute('aria-label', 'El asistente está escribiendo');
  for (var i = 0; i < 3; i++) typingWrap.appendChild(el('span', 'dgtalit-typing__dot'));
  typingEl.appendChild(typingWrap);

  /* Formulario */
  var formEl   = el('form', 'dgtalit-form');
  var textarea = el('textarea', 'dgtalit-input');
  textarea.rows        = 1;
  textarea.maxLength   = parseInt(cfg.maxMsgLength, 10) || 1200;
  textarea.placeholder = cfg.placeholder || 'Escribe tu mensaje…';
  textarea.setAttribute('aria-label', 'Escribe tu mensaje');
  textarea.setAttribute('autocomplete', 'off');

  var sendBtn = el('button', 'dgtalit-send');
  sendBtn.type = 'submit';
  sendBtn.setAttribute('aria-label', 'Enviar');
  sendBtn.appendChild(
    svg('<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>')
  );

  formEl.appendChild(textarea);
  formEl.appendChild(sendBtn);

  panel.appendChild(header);
  panel.appendChild(messagesEl);
  panel.appendChild(typingEl);
  panel.appendChild(formEl);

  /* ── Launcher ───────────────────────────────────────────── */
  var launcher = el('button', 'dgtalit-launcher');
  launcher.type = 'button';
  launcher.setAttribute('aria-expanded', 'false');
  launcher.setAttribute('aria-label', cfg.launcher || 'Abrir chat');

  var lIcon = el('span', 'dgtalit-launcher__icon');
  lIcon.setAttribute('aria-hidden', 'true');
  lIcon.appendChild(
    svg('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>')
  );
  var lLabel = el('span', 'dgtalit-launcher__label');
  lLabel.textContent = cfg.launcher || 'Chat';
  var lBadge = el('span', 'dgtalit-launcher__badge');
  lBadge.setAttribute('aria-hidden', 'true');
  launcher.appendChild(lIcon);
  launcher.appendChild(lLabel);
  launcher.appendChild(lBadge);

  /* ── Montar en el div que genera el PHP ─────────────────── */
  root.appendChild(panel);
  root.appendChild(launcher);

  var mount = document.getElementById('dgtalit-chat-root');
  (mount || document.body).appendChild(root);

  /* ── Mensaje de bienvenida ──────────────────────────────── */
  if (cfg.welcome) addMessage('assistant', cfg.welcome);

  /* ── Funciones de UI ────────────────────────────────────── */
  var isOpen = false;

  function openChat(open) {
    isOpen = open;
    root.classList.toggle('is-open', open);
    launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      setTimeout(function () { textarea.focus(); }, 80);
      /* Scroll al final al abrir */
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }
  }

  function addMessage(role, text) {
    var msgEl   = el('div', 'dgtalit-msg dgtalit-msg--' + role);
    var bubble  = el('div', 'dgtalit-msg__bubble');
    bubble.innerHTML = renderText(text);

    if (role === 'assistant') {
      var avatarMini = el('div', 'dgtalit-msg__avatar');
      avatarMini.setAttribute('aria-hidden', 'true');
      avatarMini.appendChild(
        svg('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>', '1.8')
      );
      msgEl.appendChild(avatarMini);
    }
    msgEl.appendChild(bubble);
    messagesEl.appendChild(msgEl);
    /* Scroll suave al último mensaje */
    requestAnimationFrame(function () {
      messagesEl.scrollTop = messagesEl.scrollHeight;
    });
    return msgEl;
  }

  function setLoading(on) {
    typingEl.hidden   = !on;
    sendBtn.disabled  = on;
    textarea.disabled = on;
    if (!on) {
      textarea.focus();
      textarea.style.height = 'auto';
    }
  }

  /* ── Auto-resize del textarea ───────────────────────────── */
  textarea.addEventListener('input', function () {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
  });

  /* Enter envía; Shift+Enter nueva línea */
  textarea.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      formEl.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
  });

  /* ── Eventos de apertura/cierre ─────────────────────────── */
  launcher.addEventListener('click', function () { openChat(!isOpen); });
  closeBtn.addEventListener('click', function () { openChat(false); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && isOpen) openChat(false);
  });

  /* ── Envío del formulario → REST /chat del plugin ───────── */
  formEl.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = textarea.value.trim();
    if (!text || sendBtn.disabled) return;

    textarea.value = '';
    textarea.style.height = 'auto';
    addMessage('user', text);
    setLoading(true);

    fetch(REST_URL, {
      method:  'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce':   NONCE,
      },
      body: JSON.stringify({
        message:    text,
        session_id: sid,
        page_url:   location.href,
      }),
    })
    .then(function (res) {
      return res.json().then(function (json) { return { ok: res.ok, json: json }; });
    })
    .then(function (r) {
      if (!r.ok || !r.json.ok) {
        throw new Error(r.json.message || 'Error al contactar con el asistente.');
      }
      /* El servidor puede regenerar el session_id */
      if (r.json.session_id) {
        sid = r.json.session_id;
        try { sessionStorage.setItem(SK, sid); } catch (x) {}
      }
      addMessage('assistant', r.json.reply || 'Solicitud recibida.');
    })
    .catch(function (err) {
      addMessage('assistant', err.message || 'Ahora mismo no puedo responder. Inténtalo de nuevo.');
    })
    .finally(function () {
      setLoading(false);
    });
  });
})();