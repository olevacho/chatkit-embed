(async () => {
  // 1) Ask your WP REST endpoint to mint a short-lived client secret
  async function getClientSecret(existing) {
    // existing is provided by ChatKit when refreshing tokens
    const res = await fetch(MYCHATKIT.tokenEndpoint, { method: 'POST' });
    if (!res.ok) throw new Error('Failed to fetch client secret');
    const data = await res.json(); // { client_secret, expires_at, ... }
    return data.client_secret;     // ChatKit expects a string here
  }

  // 2) Create the element and pass options
  const el = document.createElement('openai-chatkit');
  el.setOptions({
    api: { getClientSecret },   // ← the supported Hosted API config
    // optional UI config, theme, header, etc.
    // theme: 'light',
    // header: { title: 'Support' }
  });

  // 3) Mount it wherever you want
  const mount = document.getElementById('chatkit-root') || document.body;
  mount.appendChild(el);
})();
