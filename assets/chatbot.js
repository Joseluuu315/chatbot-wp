(function () {
  const config = window.CWP_N8N_CHATBOT;

  if (!config || !config.restUrl) {
    return;
  }

  const settings = config.settings || {};
  const storageKey = "cwp_n8n_session_id";
  function makeSessionId() {
    if (window.crypto && typeof window.crypto.randomUUID === "function") {
      return window.crypto.randomUUID();
    }

    return `wp-${Date.now()}-${Math.random().toString(16).slice(2)}`;
  }

  const state = {
    open: false,
    busy: false,
    sessionId: window.localStorage.getItem(storageKey) || makeSessionId(),
  };

  window.localStorage.setItem(storageKey, state.sessionId);

  const icons = {
    message:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8.5 8.5 0 0 1-8.5 8.5 9.5 9.5 0 0 1-4.4-1.1L3 21l1.6-4.6A8.2 8.2 0 0 1 3.5 12a8.5 8.5 0 0 1 17 0Z"/><path d="M8 10.5h8M8 14h5"/></svg>',
    spark:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z"/><path d="m19 15 .7 2.3L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.7L19 15Z"/></svg>',
    help:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 18h.01"/><path d="M9.1 9a3 3 0 1 1 5.4 1.8c-.8.8-1.7 1.3-2.1 2.4-.1.3-.1.6-.1.8"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>',
  };

  function createElement(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
  }

  function addMessage(role, text) {
    const message = createElement("div", `cwp-chatbot-message cwp-chatbot-message--${role}`);
    message.textContent = text;
    messages.appendChild(message);
    messages.scrollTop = messages.scrollHeight;
  }

  function setBusy(isBusy) {
    state.busy = isBusy;
    form.classList.toggle("is-busy", isBusy);
    input.disabled = isBusy;
    submit.disabled = isBusy;
  }

  async function sendMessage(text) {
    setBusy(true);
    typing.hidden = false;

    try {
      const response = await fetch(config.restUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": config.nonce,
        },
        body: JSON.stringify({
          message: text,
          session_id: state.sessionId,
          page_url: window.location.href,
        }),
      });

      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.message || "No se pudo contactar con el chatbot.");
      }

      if (data.session_id) {
        state.sessionId = data.session_id;
        window.localStorage.setItem(storageKey, state.sessionId);
      }

      addMessage("assistant", data.reply || "Solicitud recibida.");
    } catch (error) {
      addMessage("assistant", error.message || "Ahora mismo no puedo responder. Inténtalo de nuevo en un momento.");
    } finally {
      typing.hidden = true;
      setBusy(false);
      input.focus();
    }
  }

  function toggleChat(forceOpen) {
    state.open = typeof forceOpen === "boolean" ? forceOpen : !state.open;
    root.classList.toggle("is-open", state.open);
    launcher.setAttribute("aria-expanded", String(state.open));
    if (state.open) {
      window.setTimeout(() => input.focus(), 80);
    }
  }

  const root = createElement("div", "cwp-chatbot");
  root.classList.add(settings.position === "left" ? "cwp-chatbot--left" : "cwp-chatbot--right");
  root.style.setProperty("--cwp-primary", settings.primaryColor || "#2563eb");
  root.style.setProperty("--cwp-accent", settings.accentColor || "#16a34a");
  root.style.setProperty("--cwp-text", settings.textColor || "#111827");
  root.style.setProperty("--cwp-surface", settings.surfaceColor || "#ffffff");
  root.style.setProperty("--cwp-bottom", `${settings.offsetBottom || 24}px`);
  root.style.setProperty("--cwp-side", `${settings.offsetSide || 24}px`);
  root.style.setProperty("--cwp-width", `${settings.panelWidth || 380}px`);
  root.style.setProperty("--cwp-height", `${settings.panelHeight || 560}px`);

  const panel = createElement("section", "cwp-chatbot-panel");
  panel.setAttribute("aria-label", settings.title || "Chatbot");

  const header = createElement("header", "cwp-chatbot-header");
  const headingWrap = createElement("div");
  const title = createElement("h2", "", settings.title || "Asistente virtual");
  const subtitle = createElement("p", "", settings.subtitle || "");
  headingWrap.append(title, subtitle);

  const close = createElement("button", "cwp-chatbot-icon-button");
  close.type = "button";
  close.setAttribute("aria-label", "Cerrar chat");
  close.innerHTML =
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>';
  close.addEventListener("click", () => toggleChat(false));
  header.append(headingWrap, close);

  const messages = createElement("div", "cwp-chatbot-messages");
  if (settings.initialMessage) {
    addMessageAfterMount("assistant", settings.initialMessage);
  }

  const typing = createElement("div", "cwp-chatbot-typing", "Escribiendo...");
  typing.hidden = true;

  const form = createElement("form", "cwp-chatbot-form");
  const input = createElement("textarea", "cwp-chatbot-input");
  input.rows = 1;
  input.maxLength = 1200;
  input.placeholder = settings.placeholder || "Escribe tu mensaje...";
  const submit = createElement("button", "cwp-chatbot-send");
  submit.type = "submit";
  submit.setAttribute("aria-label", "Enviar mensaje");
  submit.innerHTML =
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/></svg>';
  form.append(input, submit);

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const text = input.value.trim();
    if (!text || state.busy) return;
    input.value = "";
    addMessage("user", text);
    sendMessage(text);
  });

  input.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      form.requestSubmit();
    }
  });

  const footer = createElement("div", "cwp-chatbot-footer", settings.showBranding ? "Chatbot WP n8n" : "");
  panel.append(header, messages, typing, form, footer);

  const launcher = createElement("button", "cwp-chatbot-launcher");
  launcher.type = "button";
  launcher.setAttribute("aria-label", settings.launcherLabel || "Abrir chat");
  launcher.setAttribute("aria-expanded", "false");
  launcher.innerHTML = `${icons[settings.launcherIcon] || icons.message}<span>${settings.launcherLabel || "Chat"}</span>`;
  launcher.addEventListener("click", () => toggleChat());

  root.append(panel, launcher);
  document.body.appendChild(root);

  function addMessageAfterMount(role, text) {
    window.requestAnimationFrame(() => addMessage(role, text));
  }
})();
