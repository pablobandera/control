const Auth = (() => {
  let handlingUnauthorized = false;

  function applySession(payload) {
    Store.session = payload;
    Api.setCsrfToken(payload.csrf_token);
  }

  // Si el backend devuelve 401 en medio del uso (ej: el dueño desactivó a
  // este chofer mientras tenía la sesión abierta), volvemos al login solos.
  Api.setUnauthorizedHandler((message) => {
    if (handlingUnauthorized || !Store.session) return;
    handlingUnauthorized = true;
    Store.session = null;
    Store.turno = null;
    Api.setCsrfToken(null);
    showScreen('screen-login');
    Utils.toast(message || 'Tu sesión terminó, volvé a ingresar.', 'error');
    setTimeout(() => { handlingUnauthorized = false; }, 500);
  });

  async function checkSession() {
    Utils.showLoading();
    try {
      const data = await Api.get('/auth/me');
      if (data.authenticated) {
        applySession(data);
        enterApp();
      } else {
        showScreen('screen-login');
      }
    } catch (e) {
      showScreen('screen-login');
    } finally {
      Utils.hideLoading();
    }
  }

  async function login(username, password) {
    const data = await Api.post('/auth/login', { username, password });
    applySession(data);
    enterApp();
  }

  async function logout() {
    try {
      await Api.post('/auth/logout', {});
    } catch (e) {
      // ignorar: igual limpiamos el estado local
    }
    Store.session = null;
    Store.turno = null;
    Api.setCsrfToken(null);
    showScreen('screen-login');
  }

  function enterApp() {
    if (Store.isChofer()) {
      showScreen('screen-chofer');
      ChoferScreen.init();
    } else if (Store.isDueno()) {
      showScreen('screen-dueno');
      DuenoScreen.init();
    } else {
      showScreen('screen-login');
    }
  }

  return { checkSession, login, logout };
})();
