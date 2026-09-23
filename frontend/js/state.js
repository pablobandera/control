const Store = {
  session: null, // { rol, username, chofer, empresa_id }
  turno: null,   // ticket completo del turno activo del chofer (o null)

  isChofer() {
    return this.session && this.session.rol === 'chofer';
  },
  isDueno() {
    return this.session && this.session.rol === 'dueno';
  },
};

function showScreen(id) {
  document.querySelectorAll('.screen').forEach((el) => el.classList.remove('active'));
  document.getElementById(id)?.classList.add('active');
}

function showView(container, viewId, navId) {
  container.querySelectorAll('.chofer-view, .dueno-view').forEach((el) => el.classList.remove('active'));
  document.getElementById(viewId)?.classList.add('active');
  if (navId) {
    document.querySelectorAll(`#${navId} .nav-item`).forEach((el) => {
      el.classList.toggle('active', el.dataset.view === viewId);
    });
  }
}
