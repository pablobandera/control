const Api = (() => {
  let csrfToken = null;
  let onUnauthorized = null;

  function setCsrfToken(token) {
    csrfToken = token || null;
  }

  // Permite que auth.js reaccione (limpiar sesión, volver al login) apenas
  // el backend responde 401, por ejemplo si el dueño desactivó a este chofer
  // mientras tenía la sesión abierta en el navegador.
  function setUnauthorizedHandler(fn) {
    onUnauthorized = fn;
  }

  async function handle(res) {
    let body = null;
    try {
      body = await res.json();
    } catch (e) {
      body = null;
    }
    if (!res.ok) {
      const message = (body && body.error) ? body.error : `Error ${res.status}`;
      const err = new Error(message);
      err.status = res.status;
      err.body = body;
      if (res.status === 401 && onUnauthorized) {
        onUnauthorized(message);
      }
      throw err;
    }
    return body;
  }

  function headers(extra) {
    const h = { ...extra };
    if (csrfToken) {
      h['X-CSRF-Token'] = csrfToken;
    }
    return h;
  }

  async function get(path, params) {
    let url = CONFIG.API_BASE + path;
    if (params) {
      const qs = new URLSearchParams(params).toString();
      if (qs) url += (url.includes('?') ? '&' : '?') + qs;
    }
    const res = await fetch(url, { method: 'GET', credentials: 'include', headers: headers() });
    return handle(res);
  }

  async function send(method, path, data) {
    const res = await fetch(CONFIG.API_BASE + path, {
      method,
      credentials: 'include',
      headers: headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(data || {}),
    });
    return handle(res);
  }

  function post(path, data) { return send('POST', path, data); }
  function put(path, data) { return send('PUT', path, data); }
  function del(path, data) { return send('DELETE', path, data); }

  async function postForm(path, formData) {
    const res = await fetch(CONFIG.API_BASE + path, {
      method: 'POST',
      credentials: 'include',
      headers: headers(),
      body: formData,
    });
    return handle(res);
  }

  function fileUrl(path) {
    if (!path) return '';
    if (/^https?:\/\//.test(path)) return path;
    // Los archivos subidos (uploads/...) los sirve directo el mismo document root
    // del backend (backend/public), que es a donde apunta CONFIG.API_BASE.
    return CONFIG.API_BASE.replace(/\/+$/, '') + '/' + path.replace(/^\/+/, '');
  }

  return { setCsrfToken, setUnauthorizedHandler, get, post, put, del, postForm, fileUrl };
})();
