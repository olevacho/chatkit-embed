(async () => {
  const safeJSON = (s, f = null) => {
    try {
      return s ? JSON.parse(s) : f;
    } catch {
      return f;
    }
  };

  // You technically don’t need deepMerge anymore, but we can leave it
  const deepMerge = (a, b) => {
    if (!b) return a || {};
    const o = { ...(a || {}) };
    for (const k of Object.keys(b)) {
      o[k] =
        b[k] && typeof b[k] === 'object' && !Array.isArray(b[k])
          ? deepMerge(o[k], b[k])
          : b[k];
    }
    return o;
  };

  function buildContext() {
    return {
      page_url: location.href,
      referrer: document.referrer || null,
      title: document.title || null,
    };
  }

  async function getClientSecret(workflowId) {
    const res = await fetch(CHATKIT_WIDGET.tokenEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        workflow_id: workflowId || null,
        context: buildContext(),
      }),
    });
    if (!res.ok) throw new Error('Token endpoint failed: ' + res.status);
    const j = await res.json();
    const payload = j?.client_secret ? j : (j?.data || {});
    if (!payload.client_secret) throw new Error('No client_secret in response');
    return payload.client_secret;
  }

  function normalizeOptions(opts = {}) {
    const out = { ...opts };

    // Allow header.title as a simple string
    if (out.header && typeof out.header.title === 'string') {
      out.header.title = { text: out.header.title };
    }

    // Legacy "subtitle" → startScreen.greeting
    if (out.header && out.header.subtitle && !out.startScreen?.greeting) {
      out.startScreen = {
        ...(out.startScreen || {}),
        greeting: out.header.subtitle,
      };
      delete out.header.subtitle;
    }

    // Strip view/meta keys that are not ChatKitOptions
    delete out.layout;
    delete out.personas;

    // Very important: ignore any user-provided api block from Playground export
    delete out.api;

    return out;
  }

  // We can ignore CHATKIT_WIDGET.defaults now, everything comes from admin JSON
  // const defaults = safeJSON(CHATKIT_WIDGET.defaults, {});

  document.querySelectorAll('[data-chatkit-options]').forEach((mount) => {
    const attr = safeJSON(mount.getAttribute('data-chatkit-options'), {});

    const workflowId = (attr.workflow || '').trim() || null;

    // 🔹 Directly parse options from admin JSON (no merging)
    const rawOptions = safeJSON(attr.options, {});
    const options = normalizeOptions(rawOptions);

    console.debug('[chatkit] rawOptions from admin', rawOptions);
    console.debug('[chatkit] normalized options', options);

    const mode = (attr.mode || 'inline').toLowerCase();  // inline | bubble | modal
    const position = (attr.position || 'br').toLowerCase(); // tl|tr|bl|br
    const panelW = attr.panelWidth || '380px';
    const panelH = attr.panelHeight || '560px';
    const label  = attr.label || 'Chat';
    const icon   = attr.icon || '';
    const launcherBg     = attr.launcherBg || null;
    const launcherColor  = attr.launcherColor || null;
    const launcherShadow = attr.launcherShadow ?? null;
    const hideOnMobile   =
    attr.hideOnMobile === '1' || attr.hideOnMobile === 1 || attr.hideOnMobile === true;
    const startOpen =
    attr.startOpen === '1' || attr.startOpen === 1 || attr.startOpen === true;
    const zIndex = attr.zIndex != null ? parseInt(attr.zIndex, 10) : null;


    let created = false;

    const createWidget = (host) => {
      if (created) return;
      created = true;

      const el = document.createElement('openai-chatkit');

      // If you still want to support style.minHeight / style.rounded from options
      if (options?.style?.minHeight) el.style.minHeight = options.style.minHeight;
      if (options?.style?.rounded) {
        el.style.borderRadius = '14px';
        el.style.overflow = 'hidden';
        el.style.display = 'block';
      }

      const finalOptions = { ...options };

      // Ensure tools have icons – fallback if missing
      if (finalOptions.composer && Array.isArray(finalOptions.composer.tools)) {
        finalOptions.composer.tools = finalOptions.composer.tools.map((tool) => {
          if (!tool.icon) {
            return { ...tool, icon: 'sparkles' };
          }
          return tool;
        });
      }

      console.debug('[chatkit] final options to setOptions', finalOptions);

      el.setOptions({
        ...finalOptions,                           // theme, composer, startScreen, etc. from admin
        api: { getClientSecret: () => getClientSecret(workflowId) }, // our own api config
        // onClientTool: ...  (optional in the future)
      });

      el.addEventListener('error', (e) =>
        console.error('[chatkit:error]', e.detail)
      );
      host.appendChild(el);
    };

    if (mode === 'inline') {
      mount.style.minHeight = options?.style?.minHeight || '560px';
      createWidget(mount);
      return;
    }

    if (mode === 'bubble') {
	  const btn = document.createElement('button');
	  btn.type = 'button';
	  btn.className = `ckw-launcher ${position}`;
	  btn.setAttribute('aria-haspopup', 'dialog');
	  btn.setAttribute('aria-expanded', 'false');
	  if (icon) {
	    const img = document.createElement('img');
	    img.src = icon;
	    img.alt = '';
	    btn.appendChild(img);
	  }
	  btn.append(document.createTextNode(label));

	  // 🔹 NEW: launcher style overrides
	  if (launcherBg) {
	    btn.style.background = launcherBg;
	  }
	  if (launcherColor) {
	    btn.style.color = launcherColor;
	  }
	  if (launcherShadow === '0' || launcherShadow === 0 || launcherShadow === false) {
	    btn.style.boxShadow = 'none';
	  }
	  if (hideOnMobile) {
	    btn.classList.add('ckw-hide-mobile');
	  }
	  if (!isNaN(zIndex) && zIndex !== null) {
	    btn.style.zIndex = String(zIndex);
	  }

	  document.body.appendChild(btn);

	  const panel = document.createElement('div');
	  panel.className = 'ckw-panel hidden';
	  panel.style.width = panelW;
	  panel.style.height = panelH;

	  // 🔹 NEW: hide + z-index for panel
	  if (hideOnMobile) {
	    panel.classList.add('ckw-hide-mobile');
	  }
	  if (!isNaN(zIndex) && zIndex !== null) {
	    panel.style.zIndex = String(zIndex + 1);
	  }

	  document.body.appendChild(panel);

	  const [vert, horiz] = [position[0], position[1]];
	  panel.style[vert === 't' ? 'top' : 'bottom'] = '80px';
	  panel.style[horiz === 'l' ? 'left' : 'right'] = '20px';

	  const close = document.createElement('button');
	  close.className = 'ckw-close';
	  close.setAttribute('aria-label', 'Close chat');
	  close.textContent = '×';
	  close.style.zIndex = '10001';
	  panel.appendChild(close);

	  function openPanel() {
	    if (panel.classList.contains('hidden')) {
	      panel.classList.remove('hidden');
	      btn.setAttribute('aria-expanded', 'true');
	      createWidget(panel);
	      setTimeout(() => close.focus(), 0);
	    }
	  }
	  function closePanel() {
	    panel.classList.add('hidden');
	    btn.setAttribute('aria-expanded', 'false');
	  }

	  btn.addEventListener('click', openPanel);
	  close.addEventListener('click', closePanel);
	  window.addEventListener('keydown', (e) => {
	    if (e.key === 'Escape') closePanel();
	  });

	  // 🔹 NEW: auto-open support
	  if (startOpen) {
	    openPanel();
	  }

	  return;
	}


    if (mode === 'modal') {
	  const overlay = document.createElement('div');
	  overlay.className = 'ckw-overlay';
	  const dialog = document.createElement('div');
	  dialog.className = 'ckw-modal';
	  overlay.appendChild(dialog);
	  document.body.appendChild(overlay);

	  // 🔹 NEW: z-index + hide-on-mobile for overlay/dialog
	  if (hideOnMobile) {
	    overlay.classList.add('ckw-hide-mobile');
	  }
	  if (!isNaN(zIndex) && zIndex !== null) {
	    overlay.style.zIndex = String(zIndex);
	    dialog.style.zIndex = String(zIndex + 1);
	  }

	  const close = document.createElement('button');
	  close.className = 'ckw-close';
	  close.setAttribute('aria-label', 'Close chat');
	  close.textContent = '×';
	  close.style.zIndex = '10001';
	  dialog.appendChild(close);

	  const btn = document.createElement('button');
	  btn.type = 'button';
	  btn.className = 'ckw-launcher br';
	  btn.setAttribute('aria-haspopup', 'dialog');
	  btn.setAttribute('aria-expanded', 'false');
	  if (icon) {
	    const img = document.createElement('img');
	    img.src = icon;
	    img.alt = '';
	    btn.appendChild(img);
	  }
	  btn.append(document.createTextNode(label));

	  // 🔹 NEW: launcher style overrides
	  if (launcherBg) {
	    btn.style.background = launcherBg;
	  }
	  if (launcherColor) {
	    btn.style.color = launcherColor;
	  }
	  if (launcherShadow === '0' || launcherShadow === 0 || launcherShadow === false) {
	    btn.style.boxShadow = 'none';
	  }
	  if (hideOnMobile) {
	    btn.classList.add('ckw-hide-mobile');
	  }
	  if (!isNaN(zIndex) && zIndex !== null) {
	    // keep launcher above overlay or on the same layer
	    btn.style.zIndex = String(zIndex + 2);
	  }

	  document.body.appendChild(btn);

	  function openModal() {
	    overlay.classList.add('active');
	    btn.setAttribute('aria-expanded', 'true');
	    if (!created) createWidget(dialog);
	    setTimeout(() => close.focus(), 0);
	  }
	  function closeModal() {
	    overlay.classList.remove('active');
	    btn.setAttribute('aria-expanded', 'false');
	  }

	  btn.addEventListener('click', openModal);
	  close.addEventListener('click', closeModal);
	  overlay.addEventListener('click', (e) => {
	    if (e.target === overlay) closeModal();
	  });
	  window.addEventListener('keydown', (e) => {
	    if (e.key === 'Escape') closeModal();
	  });

	  // 🔹 NEW: auto-open
	  if (startOpen) {
	    openModal();
	  }

	  return;
	}

  });
})();

