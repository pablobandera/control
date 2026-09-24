const ChoferScreen = (() => {
  let ticket = null; // { turno, liquidacion, viajes, gastos, cuentas_corrientes, comprobantes }
  let tipoSeleccionado = 'Taxi';
  let pendingFotos = [];
  let editando = null; // { type: 'viaje'|'gasto'|'cc', id }
  let timerId = null;
  let eventsBound = false;
  let comisionesPeriodo = 'semana';

  function el(id) { return document.getElementById(id); }

  async function init() {
    bindEventsOnce();
    await cargarTurnoActivo();
  }

  async function cargarTurnoActivo() {
    Utils.showLoading();
    try {
      const data = await Api.get('/turnos/activo');
      ticket = data.turno ? data : null;
      render();
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  function render() {
    const preturno = el('chofer-preturno');
    const enturno = el('chofer-enturno');
    const finalizarBtn = el('btn-finalizar-turno');
    const nav = el('chofer-nav');

    if (!ticket) {
      preturno.classList.add('active');
      enturno.classList.remove('active');
      finalizarBtn.style.display = 'none';
      nav.style.display = 'none';
      if (timerId) { clearInterval(timerId); timerId = null; }
      const nombre = Store.session.chofer ? Store.session.chofer.nombre : '';
      el('preturno-subtitle').textContent = (nombre ? nombre + '. ' : '') + 'Iniciá el turno para empezar a cargar viajes.';
      return;
    }

    preturno.classList.remove('active');
    enturno.classList.add('active');
    finalizarBtn.style.display = 'flex';
    nav.style.display = 'flex';

    renderStats();
    renderViajes();
    renderGastos();
    renderCc();
    renderServiceHistorial();

    if (!timerId) {
      timerId = setInterval(renderStats, 30000);
    }
  }

  function renderStats() {
    if (!ticket) return;
    const t = ticket.turno;
    const l = ticket.liquidacion;
    el('chofer-subtitle').textContent = `Móvil #${t.movil_numero} · Desde ${Utils.hora(t.fecha_inicio)}`;
    el('stat-taxi-total').textContent = Utils.money(l.taxi_total);
    el('stat-taxi-breakdown').innerHTML = `Efvo ${Utils.money(l.taxi_efvo)}<br>Transf ${Utils.money(l.taxi_transf)}`;
    el('stat-uber-total').textContent = Utils.money(l.uber_total);
    el('stat-uber-breakdown').innerHTML = `Efvo ${Utils.money(l.uber_efvo)}<br>Transf ${Utils.money(l.uber_transf)}`;
    el('stat-turno-time').textContent = Utils.horasEntre(t.fecha_inicio, null) + 'h';
    el('stat-viajes-count').textContent = String(l.viajes_count);
  }

  function renderViajes() {
    const stack = el('viajes-stack');
    if (!ticket.viajes.length) {
      stack.innerHTML = '<div class="empty-state">Todavía no cargaste viajes en este turno.</div>';
      return;
    }
    stack.innerHTML = ticket.viajes.map((v) => {
      const neto = Number(v.monto) - Number(v.descuento);
      return `
        <div class="card simple">
          <div class="card-data">
            <div class="data-col"><span class="lbl">${Utils.esc(v.tipo)} · ${Utils.esc(v.medio)}</span><span class="val">${Utils.money(neto)}</span></div>
            <div class="data-col" style="text-align:right;"><span class="lbl">Hora</span><span class="val">${Utils.hora(v.hora)}</span></div>
          </div>
          ${v.comentario ? `<p style="font-size:11.5px;color:#9a9a96;margin:6px 0 0;">${Utils.esc(v.comentario)}</p>` : ''}
          <div class="card-actions-row">
            <button class="edit-viaje-btn" data-action="editar-viaje" data-id="${v.id}"><i class="ti ti-pencil"></i> Editar</button>
          </div>
        </div>`;
    }).join('');
  }

  function renderGastos() {
    const stack = el('gastos-stack');
    if (!ticket.gastos.length) {
      stack.innerHTML = '<div class="empty-state">Todavía no cargaste gastos en este turno.</div>';
      return;
    }
    stack.innerHTML = ticket.gastos.map((g) => `
      <div class="card simple">
        <div class="card-data">
          <div class="data-col"><span class="lbl">${Utils.esc(g.concepto)}${g.categoria === 'combustible' ? ' · Combustible' : ''}</span><span class="val">${Utils.money(g.monto)}</span></div>
          <div class="data-col" style="text-align:right;"><span class="lbl">Hora</span><span class="val">${Utils.hora(g.hora)}</span></div>
        </div>
        <div class="card-actions-row">
          <button class="edit-viaje-btn" data-action="editar-gasto" data-id="${g.id}"><i class="ti ti-pencil"></i> Editar</button>
        </div>
      </div>`).join('');
  }

  function renderCc() {
    const stack = el('cc-stack');
    const pendientes = ticket.cuentas_corrientes;
    if (!pendientes.length) {
      stack.innerHTML = '<div class="empty-state">No hay viajes a cuenta corriente en este turno.</div>';
      return;
    }
    stack.innerHTML = pendientes.map((c) => {
      const neto = Number(c.monto) - Number(c.descuento);
      return `
        <div class="card simple">
          <div class="card-data">
            <div class="data-col"><span class="lbl">${Utils.esc(c.cliente_nombre)}</span><span class="val">${Utils.money(neto)}</span></div>
            <div class="data-col" style="text-align:right;"><span class="lbl">Hora</span><span class="val">${Utils.hora(c.hora)}</span></div>
          </div>
          <div class="card-actions-row">
            <button class="edit-viaje-btn" data-action="editar-cc" data-id="${c.id}"><i class="ti ti-pencil"></i> Editar</button>
            ${Number(c.cobrado) ? '<span style="font-size:11px;color:#2fa85a;font-weight:700;">Cobrado</span>' : `<button class="cobrar-btn" data-action="cobrar-cc" data-id="${c.id}"><i class="ti ti-check"></i> Marcar cobrado</button>`}
          </div>
        </div>`;
    }).join('');
  }

  async function renderServiceHistorial() {
    const box = el('service-historial-list');
    try {
      const data = await Api.get('/services');
      if (!data.services.length) {
        box.innerHTML = '<div class="empty-state">Todavía no hay service cargado para este móvil.</div>';
        return;
      }
      box.innerHTML = data.services.map((s) => `
        <div class="service-entry">
          <p class="service-entry-date">${Utils.fechaHora(s.fecha)} · ${Utils.esc(s.chofer_nombre)}</p>
          <p class="service-entry-text">${Utils.esc(s.descripcion)}</p>
        </div>`).join('');
    } catch (e) {
      box.innerHTML = '';
    }
  }

  async function renderPerfil() {
    const box = el('chofer-perfil-card');
    Utils.showLoading();
    try {
      const data = await Api.get('/perfil');
      const c = data.chofer;
      const fotoSrc = c.foto_path ? Api.fileUrl(c.foto_path) : '';
      box.innerHTML = `
        <div class="profile-card">
          <div class="profile-avatar-wrap">
            ${fotoSrc ? `<img class="profile-avatar" src="${fotoSrc}" alt="">` : `<div class="profile-avatar" style="display:flex;align-items:center;justify-content:center;"><i class="ti ti-user" style="font-size:32px;color:#9a9a96;"></i></div>`}
            <div class="profile-avatar-edit-btn" id="btn-cambiar-foto-perfil"><i class="ti ti-camera" style="font-size:13px;"></i></div>
            <input type="file" id="input-foto-perfil" accept="image/*" style="display:none;">
          </div>
          <p class="profile-name">${Utils.esc(c.nombre)}</p>
          <p class="profile-role">Chofer · desde ${Utils.fecha(c.created_at)}</p>
          <div class="profile-grid">
            <div class="profile-item"><span class="lbl">Usuario</span><p class="val">${Utils.esc(c.username)}</p></div>
            <div class="profile-item"><span class="lbl">Teléfono</span><p class="val" id="perfil-val-telefono">${Utils.esc(c.telefono)}</p></div>
            <div class="profile-item"><span class="lbl">Vehículo</span><p class="val" id="perfil-val-vehiculo">${Utils.esc(c.vehiculo || '-')}</p></div>
            <div class="profile-item"><span class="lbl">Comisión</span><p class="val">${c.comision_pct !== null ? c.comision_pct + '%' : 'De la flota'}</p></div>
          </div>
          <button class="profile-link-btn" id="btn-editar-perfil">Editar teléfono / vehículo</button>
          <button class="profile-link-btn" id="btn-cambiar-password">Cambiar contraseña</button>
        </div>`;

      el('btn-cambiar-foto-perfil').addEventListener('click', () => el('input-foto-perfil').click());
      el('input-foto-perfil').addEventListener('change', async (ev) => {
        const file = ev.target.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('foto', file);
        Utils.showLoading();
        try {
          await Api.postForm('/perfil/foto', fd);
          Utils.toast('Foto actualizada', 'success');
          renderPerfil();
        } catch (err) {
          Utils.toast(err.message, 'error');
        } finally {
          Utils.hideLoading();
        }
      });

      el('btn-editar-perfil').addEventListener('click', async () => {
        const telefono = prompt('Teléfono', c.telefono) ?? c.telefono;
        const vehiculo = prompt('Vehículo', c.vehiculo || '') ?? c.vehiculo;
        Utils.showLoading();
        try {
          await Api.put('/perfil', { telefono, vehiculo });
          Utils.toast('Perfil actualizado', 'success');
          renderPerfil();
        } catch (err) {
          Utils.toast(err.message, 'error');
        } finally {
          Utils.hideLoading();
        }
      });

      el('btn-cambiar-password').addEventListener('click', async () => {
        const actual = prompt('Contraseña actual');
        if (!actual) return;
        const nueva = prompt('Nueva contraseña (mínimo 6 caracteres)');
        if (!nueva) return;
        Utils.showLoading();
        try {
          await Api.put('/perfil/password', { actual, nueva });
          Utils.toast('Contraseña actualizada', 'success');
        } catch (err) {
          Utils.toast(err.message, 'error');
        } finally {
          Utils.hideLoading();
        }
      });
    } catch (e) {
      box.innerHTML = '<div class="empty-state">No se pudo cargar el perfil.</div>';
    } finally {
      Utils.hideLoading();
    }
  }

  async function renderComisionesChart(periodo) {
    comisionesPeriodo = periodo;
    document.querySelectorAll('#comisiones-filter-row button').forEach((b) => b.classList.toggle('active', b.dataset.period === periodo));
    const cont = el('comisiones-chart-content');
    try {
      const data = await Api.get('/turnos/comisiones', { periodo });
      const max = Math.max(1, ...data.buckets.map((b) => b.comision));
      cont.innerHTML = `
        <div class="bar-chart">
          ${data.buckets.map((b, i) => {
            const heightPct = Math.max(2, Math.round((b.comision / max) * 100));
            const esPico = b.comision === max && max > 0;
            return `
              <div class="bar-col">
                ${b.comision > 0 ? `<span class="bar-val">${Utils.money(b.comision)}</span>` : ''}
                <div class="bar${esPico ? ' peak' : ''}" style="height:${heightPct}%;"></div>
                <span class="bar-lbl">${Utils.esc(b.label)}</span>
              </div>`;
          }).join('')}
        </div>`;
    } catch (e) {
      cont.innerHTML = '<div class="empty-state">No se pudo cargar el gráfico.</div>';
    }
  }

  async function renderHistorial() {
    renderComisionesChart(comisionesPeriodo);
    const box = el('historial-stack');
    Utils.showLoading();
    try {
      const data = await Api.get('/turnos/historial');
      if (!data.turnos.length) {
        box.innerHTML = '<div class="empty-state">Todavía no tenés turnos cerrados.</div>';
        return;
      }
      box.innerHTML = data.turnos.map((t) => `
        <div class="hist-card" data-action="ver-turno" data-id="${t.id}">
          <div>
            <p class="hist-date">${Utils.fecha(t.fecha_inicio)}</p>
            <p class="hist-sub">Móvil #${Utils.esc(t.movil_numero)} · ${Utils.horasEntre(t.fecha_inicio, t.fecha_fin)}h</p>
          </div>
          <div>
            <p class="hist-total">A rendir ${Utils.money(t.a_rendir)}</p>
            <p class="hist-viajes">${t.viajes_count} viajes</p>
          </div>
        </div>`).join('');
    } catch (e) {
      box.innerHTML = '<div class="empty-state">No se pudo cargar el historial.</div>';
    } finally {
      Utils.hideLoading();
    }
  }

  function ticketResumenHtml(t) {
    const l = t.liquidacion;
    return `
      <div class="modal-row"><span>Móvil</span><b>#${Utils.esc(t.turno.movil_numero)}</b></div>
      <div class="modal-row"><span>Inicio</span><b>${Utils.fechaHora(t.turno.fecha_inicio)}</b></div>
      ${t.turno.fecha_fin ? `<div class="modal-row"><span>Fin</span><b>${Utils.fechaHora(t.turno.fecha_fin)}</b></div>` : ''}
      <div class="modal-row"><span>Taxi</span><b>${Utils.money(l.taxi_total)}</b></div>
      <div class="modal-row"><span>Uber</span><b>${Utils.money(l.uber_total)}</b></div>
      <div class="modal-row"><span>Cuenta corriente</span><b>${Utils.money(l.cc_total)}</b></div>
      <div class="modal-row"><span>Descuentos</span><b>-${Utils.money(l.descuentos_total)}</b></div>
      <div class="modal-row"><span>Gastos</span><b>-${Utils.money(l.gastos_total)}</b></div>
      <div class="modal-row"><span>Total bruto</span><b>${Utils.money(l.total_bruto)}</b></div>
      <div class="modal-row"><span>Tu comisión (${l.comision_pct}%)</span><b>${Utils.money(l.comision_chofer)}</b></div>
      <div class="modal-row"><span>A rendir al dueño</span><b>${Utils.money(l.a_rendir)}</b></div>
      <div class="modal-row"><span>Viajes</span><b>${l.viajes_count}</b></div>
      ${t.comprobantes && t.comprobantes.length ? `<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;">${t.comprobantes.map((c) => `<img src="${Api.fileUrl(c.ruta_archivo)}" style="width:56px;height:56px;object-fit:cover;border-radius:8px;cursor:pointer;" data-action="ver-foto" data-src="${Api.fileUrl(c.ruta_archivo)}">`).join('')}</div>` : ''}
    `;
  }

  async function verTurno(id) {
    Utils.showLoading();
    try {
      const data = await Api.get(`/turnos/${id}`);
      el('ver-turno-modal-title').textContent = 'Ticket del turno';
      el('ver-turno-modal-body').innerHTML = ticketResumenHtml(data);
      Utils.openModal('ver-turno-modal');
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  // ---------- acciones ----------

  async function comenzarTurno(movilNumero) {
    Utils.showLoading();
    try {
      await Api.post('/turnos', { movil_numero: movilNumero });
      Utils.closeModal('movil-modal');
      await cargarTurnoActivo();
      showView(el('chofer-enturno'), 'chofer-view-viajes', 'chofer-nav');
    } catch (e) {
      el('movil-modal-error').textContent = e.message;
      el('movil-modal-error').classList.add('show');
    } finally {
      Utils.hideLoading();
    }
  }

  async function agregarViaje() {
    const monto = el('input-monto').value.trim();
    const descuento = el('discount-box').classList.contains('open') ? el('input-descuento').value.trim() : '0';
    if (!monto) {
      Utils.toast('Ingresá el monto del viaje', 'error');
      return;
    }
    Utils.showLoading();
    try {
      await Api.post('/viajes', { tipo: tipoSeleccionado, medio: el('sel-medio').value, monto, descuento: descuento || '0' });
      el('input-monto').value = '';
      el('input-descuento').value = '';
      el('discount-box').classList.remove('open');
      await cargarTurnoActivo();
      Utils.toast('Viaje agregado', 'success');
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  async function agregarGasto() {
    const concepto = el('input-gasto-concepto').value.trim();
    const monto = el('input-gasto-monto').value.trim();
    if (!concepto || !monto) {
      Utils.toast('Completá el concepto y el monto', 'error');
      return;
    }
    Utils.showLoading();
    try {
      await Api.post('/gastos', { concepto, monto, categoria: el('sel-gasto-categoria').value });
      el('input-gasto-concepto').value = '';
      el('input-gasto-monto').value = '';
      await cargarTurnoActivo();
      Utils.toast('Gasto agregado', 'success');
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  async function agregarCc() {
    const cliente = el('input-cc-cliente').value.trim();
    const monto = el('input-cc-monto').value.trim();
    const descuento = el('discount-box-cc').classList.contains('open') ? el('input-cc-descuento').value.trim() : '0';
    if (!cliente || !monto) {
      Utils.toast('Completá el cliente y el monto', 'error');
      return;
    }
    Utils.showLoading();
    try {
      await Api.post('/cuentas-corrientes', { cliente_nombre: cliente, monto, descuento: descuento || '0' });
      el('input-cc-cliente').value = '';
      el('input-cc-monto').value = '';
      el('input-cc-descuento').value = '';
      el('discount-box-cc').classList.remove('open');
      await cargarTurnoActivo();
      Utils.toast('Agregado a cuenta corriente', 'success');
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  async function cobrarCc(id) {
    Utils.showLoading();
    try {
      await Api.post(`/cuentas-corrientes/${id}/cobrar`, {});
      await cargarTurnoActivo();
      Utils.toast('Marcado como cobrado', 'success');
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  function abrirEditar(type, id) {
    editando = { type, id };
    let item = null;
    if (type === 'viaje') item = ticket.viajes.find((v) => String(v.id) === String(id));
    if (type === 'gasto') item = ticket.gastos.find((g) => String(g.id) === String(id));
    if (type === 'cc') item = ticket.cuentas_corrientes.find((c) => String(c.id) === String(id));
    if (!item) return;

    el('editar-monto-title').textContent = type === 'viaje' ? 'Editar viaje' : type === 'gasto' ? 'Editar gasto' : 'Editar cuenta corriente';
    el('input-editar-monto').value = item.monto;
    el('input-editar-descuento').value = item.descuento || '0';
    el('input-editar-descuento').style.display = type === 'gasto' ? 'none' : 'block';
    el('input-editar-comentario').style.display = type === 'viaje' ? 'block' : 'none';
    el('input-editar-comentario').value = item.comentario || '';
    Utils.openModal('editar-monto-modal');
  }

  async function guardarEditar() {
    if (!editando) return;
    const monto = el('input-editar-monto').value.trim();
    const payload = { monto };
    if (editando.type !== 'gasto') {
      payload.descuento = el('input-editar-descuento').value.trim() || '0';
    }
    if (editando.type === 'viaje') {
      payload.comentario = el('input-editar-comentario').value.trim();
    }
    const endpoint = editando.type === 'viaje' ? `/viajes/${editando.id}` : editando.type === 'gasto' ? `/gastos/${editando.id}` : `/cuentas-corrientes/${editando.id}`;
    Utils.showLoading();
    try {
      await Api.put(endpoint, payload);
      Utils.closeModal('editar-monto-modal');
      editando = null;
      await cargarTurnoActivo();
      Utils.toast('Guardado', 'success');
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  async function guardarService() {
    const texto = el('input-service').value.trim();
    if (!texto) return;
    Utils.showLoading();
    try {
      await Api.post('/services', { descripcion: texto });
      el('input-service').value = '';
      const btn = el('btn-guardar-service');
      btn.classList.add('saved');
      el('btn-guardar-service-txt').textContent = 'Guardado';
      setTimeout(() => { btn.classList.remove('saved'); el('btn-guardar-service-txt').textContent = 'Guardar'; }, 1500);
      renderServiceHistorial();
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  function renderFotosGrid() {
    const grid = el('fotos-reloj-grid');
    grid.innerHTML = pendingFotos.map((file, i) => `
      <div class="foto-reloj-item">
        <img src="${URL.createObjectURL(file)}" data-index="${i}" data-action="ver-foto-pendiente">
        <button type="button" class="foto-reloj-remove" data-action="quitar-foto" data-index="${i}" style="position:absolute;top:-6px;right:-6px;width:22px;height:22px;border-radius:50%;background:#c9483d;color:#fff;border:none;font-size:11px;cursor:pointer;">✕</button>
      </div>`).join('');
    el('btn-sacar-foto-reloj-txt').textContent = pendingFotos.length ? `Agregar otra (${pendingFotos.length})` : 'Agregar foto';
    el('btn-sacar-foto-reloj').classList.toggle('tomada', pendingFotos.length > 0);
  }

  function abrirFinalizar() {
    if (!ticket) return;
    el('finalizar-modal-body').innerHTML = ticketResumenHtml(ticket);
    pendingFotos = [];
    renderFotosGrid();
    Utils.openModal('finalizar-modal');
  }

  async function confirmarFinalizar() {
    if (!ticket) return;
    Utils.showLoading();
    try {
      const fd = new FormData();
      pendingFotos.forEach((f) => fd.append('fotos[]', f));
      const data = await Api.postForm(`/turnos/${ticket.turno.id}/cerrar`, fd);
      Utils.closeModal('finalizar-modal');
      ticket = null;
      render();
      el('ver-turno-modal-title').textContent = 'Turno finalizado';
      el('ver-turno-modal-body').innerHTML = ticketResumenHtml(data);
      Utils.openModal('ver-turno-modal');
      Utils.toast('Turno finalizado', 'success');
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  function bindEventsOnce() {
    if (eventsBound) return;
    eventsBound = true;

    el('chofer-logout').addEventListener('click', () => Auth.logout());

    document.querySelectorAll('#chofer-nav .nav-item').forEach((item) => {
      item.addEventListener('click', () => {
        showView(el('chofer-enturno'), item.dataset.view, 'chofer-nav');
        if (item.dataset.view === 'chofer-view-historial') renderHistorial();
        if (item.dataset.view === 'chofer-view-perfil') renderPerfil();
      });
    });

    el('btn-comenzar-turno').addEventListener('click', () => {
      el('input-movil-modal').value = '';
      el('movil-modal-error').classList.remove('show');
      Utils.openModal('movil-modal');
    });
    el('btn-cancelar-movil').addEventListener('click', () => Utils.closeModal('movil-modal'));
    el('btn-confirmar-movil').addEventListener('click', () => {
      const numero = el('input-movil-modal').value.trim();
      if (!numero) {
        el('movil-modal-error').classList.add('show');
        return;
      }
      comenzarTurno(numero);
    });

    el('tipo-toggle').addEventListener('click', (ev) => {
      const btn = ev.target.closest('.tipo-toggle-btn');
      if (!btn) return;
      tipoSeleccionado = btn.dataset.tipo;
      document.querySelectorAll('.tipo-toggle-btn').forEach((b) => b.classList.toggle('active', b === btn));
      el('tipo-toggle-bg').style.transform = tipoSeleccionado === 'Uber' ? 'translateX(100%)' : 'translateX(0)';
    });

    el('discount-toggle').addEventListener('click', () => el('discount-box').classList.toggle('open'));
    el('discount-toggle-cc').addEventListener('click', () => el('discount-box-cc').classList.toggle('open'));

    el('btn-agregar-viaje').addEventListener('click', agregarViaje);
    el('btn-agregar-gasto').addEventListener('click', agregarGasto);
    el('btn-agregar-cc').addEventListener('click', agregarCc);
    el('btn-guardar-service').addEventListener('click', guardarService);

    el('viajes-stack').addEventListener('click', (ev) => {
      const btn = ev.target.closest('[data-action="editar-viaje"]');
      if (btn) abrirEditar('viaje', btn.dataset.id);
    });
    el('gastos-stack').addEventListener('click', (ev) => {
      const btn = ev.target.closest('[data-action="editar-gasto"]');
      if (btn) abrirEditar('gasto', btn.dataset.id);
    });
    el('cc-stack').addEventListener('click', (ev) => {
      const editBtn = ev.target.closest('[data-action="editar-cc"]');
      if (editBtn) { abrirEditar('cc', editBtn.dataset.id); return; }
      const cobrarBtn = ev.target.closest('[data-action="cobrar-cc"]');
      if (cobrarBtn) cobrarCc(cobrarBtn.dataset.id);
    });
    el('historial-stack').addEventListener('click', (ev) => {
      const row = ev.target.closest('[data-action="ver-turno"]');
      if (row) verTurno(row.dataset.id);
    });
    el('comisiones-filter-row').addEventListener('click', (ev) => {
      const btn = ev.target.closest('button[data-period]');
      if (btn) renderComisionesChart(btn.dataset.period);
    });

    el('btn-cancelar-editar-monto').addEventListener('click', () => { Utils.closeModal('editar-monto-modal'); editando = null; });
    el('btn-guardar-editar-monto').addEventListener('click', guardarEditar);

    el('btn-finalizar-turno').addEventListener('click', abrirFinalizar);
    el('btn-cancelar-finalizar').addEventListener('click', () => Utils.closeModal('finalizar-modal'));
    el('btn-confirmar-finalizar').addEventListener('click', confirmarFinalizar);

    el('btn-sacar-foto-reloj').addEventListener('click', () => el('input-foto-reloj').click());
    el('input-foto-reloj').addEventListener('change', (ev) => {
      pendingFotos = pendingFotos.concat(Array.from(ev.target.files));
      renderFotosGrid();
      ev.target.value = '';
    });
    el('fotos-reloj-grid').addEventListener('click', (ev) => {
      const removeBtn = ev.target.closest('[data-action="quitar-foto"]');
      if (removeBtn) {
        pendingFotos.splice(Number(removeBtn.dataset.index), 1);
        renderFotosGrid();
        return;
      }
      const img = ev.target.closest('[data-action="ver-foto-pendiente"]');
      if (img) verFoto(img.src);
    });

    el('ver-turno-modal-body').addEventListener('click', (ev) => {
      const img = ev.target.closest('[data-action="ver-foto"]');
      if (img) verFoto(img.dataset.src);
    });
    el('finalizar-modal-body').addEventListener('click', (ev) => {
      const img = ev.target.closest('[data-action="ver-foto"]');
      if (img) verFoto(img.dataset.src);
    });
    el('btn-cerrar-ver-turno').addEventListener('click', () => Utils.closeModal('ver-turno-modal'));
    el('btn-cerrar-foto-full').addEventListener('click', () => Utils.closeModal('foto-full-modal'));
  }

  function verFoto(src) {
    el('foto-full-img').src = src;
    Utils.openModal('foto-full-modal');
  }

  return { init };
})();
