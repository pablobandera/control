document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;
    const errorEl = document.getElementById('login-error');
    errorEl.classList.remove('show');

    if (!username || !password) {
      errorEl.textContent = 'Ingresá tu usuario y contraseña.';
      errorEl.classList.add('show');
      return;
    }

    const submitBtn = document.getElementById('login-submit');
    submitBtn.disabled = true;
    Utils.showLoading();
    try {
      await Auth.login(username, password);
      document.getElementById('login-password').value = '';
    } catch (e) {
      errorEl.textContent = e.message || 'Usuario o contraseña incorrectos.';
      errorEl.classList.add('show');
    } finally {
      submitBtn.disabled = false;
      Utils.hideLoading();
    }
  });

  Auth.checkSession();
});
