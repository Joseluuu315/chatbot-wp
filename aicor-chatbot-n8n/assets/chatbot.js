(function () {
  var config = window.AICOR_CBN8N;
  if (!config || !config.restUrl) return;

  var settings = config.settings || {};
  var storageKey = "aicor_cbn8n_session_id";
  var sessionId = window.localStorage.getItem(storageKey) || "wp-" + Date.now() + "-" + Math.random().toString(16).slice(2);
  window.localStorage.setItem(storageKey, sessionId);

  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (typeof text !== "undefined") node.textContent = text;
    return node;
  }

  function addMessage(role, text) {
    var msg = el("div", "aicor-cbn8n-message aicor-cbn8n-message--" + role, text);
    messages.appendChild(msg);
    messages.scrollTop = messages.scrollHeight;
  }

  var root = el("div", "aicor-cbn8n");
  root.className += settings.position === "left" ? " aicor-cbn8n--left" : " aicor-cbn8n--right";
  root.style.setProperty("--primary", settings.primaryColor || "#2563eb");
  root.style.setProperty("--accent", settings.accentColor || "#16a34a");
  root.style.setProperty("--text", settings.textColor || "#111827");
  root.style.setProperty("--surface", settings.surfaceColor || "#ffffff");
  root.style.setProperty("--bottom", (settings.offsetBottom || 24) + "px");
  root.style.setProperty("--side", (settings.offsetSide || 24) + "px");
  root.style.setProperty("--width", (settings.panelWidth || 380) + "px");
  root.style.setProperty("--height", (settings.panelHeight || 560) + "px");

  var panel = el("section", "aicor-cbn8n-panel");
  var header = el("header", "aicor-cbn8n-header");
  var titleWrap = el("div");
  titleWrap.appendChild(el("h2", "", settings.title || "Asistente virtual"));
  titleWrap.appendChild(el("p", "", settings.subtitle || ""));
  var close = el("button", "aicor-cbn8n-close", "x");
  close.type = "button";
  header.appendChild(titleWrap);
  header.appendChild(close);

  var messages = el("div", "aicor-cbn8n-messages");
  var typing = el("div", "aicor-cbn8n-typing", "Escribiendo...");
  typing.hidden = true;

  var form = el("form", "aicor-cbn8n-form");
  var input = el("textarea", "aicor-cbn8n-input");
  input.rows = 1;
  input.maxLength = 1200;
  input.placeholder = settings.placeholder || "Escribe tu mensaje...";
  var submit = el("button", "aicor-cbn8n-send", "Enviar");
  submit.type = "submit";
  form.appendChild(input);
  form.appendChild(submit);

  var launcher = el("button", "aicor-cbn8n-launcher", settings.launcherLabel || "Chat");
  launcher.type = "button";

  panel.appendChild(header);
  panel.appendChild(messages);
  panel.appendChild(typing);
  panel.appendChild(form);
  root.appendChild(panel);
  root.appendChild(launcher);
  document.body.appendChild(root);

  if (settings.initialMessage) addMessage("assistant", settings.initialMessage);

  function openChat(open) {
    root.classList.toggle("is-open", open);
    if (open) setTimeout(function () { input.focus(); }, 80);
  }

  launcher.addEventListener("click", function () { openChat(!root.classList.contains("is-open")); });
  close.addEventListener("click", function () { openChat(false); });

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    var text = input.value.trim();
    if (!text) return;
    input.value = "";
    addMessage("user", text);
    typing.hidden = false;
    submit.disabled = true;
    input.disabled = true;

    fetch(config.restUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-WP-Nonce": config.nonce },
      body: JSON.stringify({ message: text, session_id: sessionId, page_url: window.location.href })
    })
      .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
      .then(function (res) {
        if (!res.ok || !res.json.ok) throw new Error(res.json.message || "No se pudo contactar con el chatbot.");
        if (res.json.session_id) {
          sessionId = res.json.session_id;
          window.localStorage.setItem(storageKey, sessionId);
        }
        addMessage("assistant", res.json.reply || "Solicitud recibida.");
      })
      .catch(function (error) {
        addMessage("assistant", error.message || "Ahora mismo no puedo responder.");
      })
      .then(function () {
        typing.hidden = true;
        submit.disabled = false;
        input.disabled = false;
        input.focus();
      });
  });
})();
