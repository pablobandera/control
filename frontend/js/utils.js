const Utils = (() => {
  function money(n) {
    const v = Math.round(Number(n) || 0);
    return '$' + v.toLocaleString('es-AR');
  }

  function pct(n) {
    return (Number(n) || 0).toFixed(1) + '%';
  }

  function esc(str) {
    const div = document.createElement('div');
    div.textContent = str === null || str === undefined ? '' : String(str);
    return div.innerHTML;
  }

  function fechaHora(iso) {
    if (!iso) return '--';
    const d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  }

  function hora(iso) {
    if (!iso) return '--:--';
    const d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d.getTime())) return '--:--';
    return d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
  }

  function fecha(iso) {
    if (!iso) return '--';
    const d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleDateString('es-AR', { weekday: 'long', day: '2-digit', month: 'long' });
  }

  function horasEntre(desdeIso, hastaIso) {
    const desde = new Date(desdeIso.replace(' ', 'T'));
    const hasta = hastaIso ? new Date(hastaIso.replace(' ', 'T')) : new Date();
    const horas = (hasta.getTime() - desde.getTime()) / 3600000;
    return Math.max(0, horas).toFixed(1);
  }

  function toast(message, type = 'info') {
    const wrap = document.getElementById('toast-wrap');
    if (!wrap) return;
    const el = document.createElement('div');
    el.className = 'toast' + (type === 'error' ? ' error' : type === 'success' ? ' success' : '');
    el.textContent = message;
    wrap.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => {
      el.classList.remove('show');
      setTimeout(() => el.remove(), 250);
    }, 3200);
  }

  function showLoading() {
    document.getElementById('loading-overlay')?.classList.add('active');
  }

  function hideLoading() {
    document.getElementById('loading-overlay')?.classList.remove('active');
  }

  function openModal(id) {
    document.getElementById(id)?.classList.add('active');
  }

  function closeModal(id) {
    document.getElementById(id)?.classList.remove('active');
  }

  function confirmDialog(title, onConfirm) {
    const modal = document.getElementById('confirm-modal');
    document.getElementById('confirm-modal-title').textContent = title;
    const yesBtn = document.getElementById('btn-confirm-modal-yes');
    const noBtn = document.getElementById('btn-confirm-modal-no');
    const cleanup = () => {
      yesBtn.removeEventListener('click', onYes);
      noBtn.removeEventListener('click', onNo);
      closeModal('confirm-modal');
    };
    const onYes = () => { cleanup(); onConfirm(); };
    const onNo = () => cleanup();
    yesBtn.addEventListener('click', onYes);
    noBtn.addEventListener('click', onNo);
    openModal(modal.id);
  }

  function fileToDataUrl(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = reject;
      reader.readAsDataURL(file);
    });
  }

  return { money, pct, esc, fechaHora, hora, fecha, horasEntre, toast, showLoading, hideLoading, openModal, closeModal, confirmDialog, fileToDataUrl };
})();
