(function () {
  const path = window.location.pathname;
  const base = path.includes('/pages/') ? '../../backend/public/index.php' : '../backend/public/index.php';
  let csrfToken = sessionStorage.getItem('classmanager_csrf') || '';

  async function request(route, options = {}) {
    const method = options.method || 'GET';
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    const config = { method, headers, credentials: 'same-origin' };
    if (options.body !== undefined) {
      headers['Content-Type'] = 'application/json';
      config.body = JSON.stringify(options.body);
    }
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && csrfToken) headers['X-CSRF-Token'] = csrfToken;

    const [apiRoute, query = ''] = route.split('?', 2);
    const url = `${base}?route=${encodeURIComponent(apiRoute)}${query ? `&${query}` : ''}`;
    const response = await fetch(url, config);
    let result;
    try { result = await response.json(); } catch { result = { error: 'O servidor retornou uma resposta inválida.' }; }
    if (!response.ok) {
      const error = new Error(result.error || 'Não foi possível concluir a solicitação.');
      error.status = response.status;
      error.fields = result.fields || {};
      throw error;
    }
    if (result.csrf_token) {
      csrfToken = result.csrf_token;
      sessionStorage.setItem('classmanager_csrf', csrfToken);
    }
    return result;
  }

  function login(email, password) { return request('auth/login', { method: 'POST', body: { email, password } }); }
  async function ensureSignedIn() {
    try { return (await request('auth/me')).data; }
    catch (error) {
      if (error.status === 401) {
        const page = window.location.pathname.includes('/pages/') ? '../login.html' : 'login.html';
        window.location.assign(page);
      }
      throw error;
    }
  }
  async function logout() {
    await request('auth/logout', { method: 'POST', body: {} });
    sessionStorage.removeItem('classmanager_csrf');
    window.location.assign(window.location.pathname.includes('/pages/') ? '../login.html' : 'login.html');
  }

  window.ClassManagerApi = { request, login, ensureSignedIn, logout };
})();
