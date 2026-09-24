const DuenoScreen = (() => {
  let periodoActual = 'dia';
  let mesSeleccionado = null; // 'YYYY-MM', solo aplica cuando periodoActual === 'mes'
  let eventsBound = false;
  let editandoChoferId = null;
  let choferFormFotoFile = null;

  const MESES_ES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

  function el(id) { return document.getElementById(id); }

  function poblarSelectorMeses() {
    const sel = el('sel-reportes-month');
    if (!sel || sel.options.length) return;
    const now = new Date();
    let html = '';
    for (let i = 0; i < 12; i++) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
      const nombreMes = MESES_ES[d.getMonth()];
      const label = `${nombreMes.charAt(0).toUpperCase()}${nombreMes.slice(1)} ${d.getFullYear()}`;
      html += `<option value="${key}">${label}</option>`;
    }
    sel.innerHTML = html;
    mesSeleccionado = sel.value;
  }

  function reportesParams() {
    const params = { periodo: periodoActual };
    if (periodoActual === 'mes' && mesSeleccionado) {
      params.fecha = `${mesSeleccionado}-15`;
    }
    return params;
  }

  async function init() {
    bindEventsOnce();
    showView(document.getElementById('screen-dueno'), 'dueno-view-choferes', 'dueno-nav');
    el('dueno-titulo').textContent = 'Choferes';
    el('btn-export-reportes').style.display = 'none';
    await renderChoferes();
  }

  function iniciales(nombre) {
    return (nombre || '?').split(/[\s,]+/).filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('');
  }

  async function renderChoferes() {
    Utils.showLoading();
    try {
      const data = await Api.get('/choferes');
      const items = data.choferes;

      const totales = items.reduce((acc, it) => {
        acc.taxi += it.liquidacion_hoy.taxi_total;
        acc.uber += it.liquidacion_hoy.uber_total;
        acc.viajes += it.liquidacion_hoy.viajes_count;
        acc.activos += it.en_servicio ? 1 : 0;
        return acc;
      }, { taxi: 0, uber: 0, viajes: 0, activos: 0 });

      el('choferes-stats-row').innerHTML = `
        <div class="stat-box taxi"><p class="lbl">Taxi hoy</p><p class="val">${Utils.money(totales.taxi)}</p></div>
        <div class="stat-box uber"><p class="lbl">Uber hoy</p><p class="val">${Utils.money(totales.uber)}</p></div>
        <div class="stat-box turno"><p class="lbl">Activos</p><div class="pill-outline-box"><p class="val">${totales.activos}/${items.length}</p></div></div>
        <div class="stat-box viajes"><p class="lbl">Viajes hoy</p><div class="pill-outline-box"><p class="val">${totales.viajes}</p></div></div>`;

      const hoy = new Date().toLocaleDateString('es-AR', { weekday: 'long', day: '2-digit', month: 'long' });
      el('choferes-subtitle').textContent = 'Hoy, ' + hoy;

      const stack = el('choferes-stack');
      if (!items.length) {
        stack.innerHTML = '<div class="empty-state">Todavía no agregaste choferes. Hacelo desde la pestaña Perfil.</div>';
        return;
      }

      stack.innerHTML = items.map((it) => {
        const c = it.chofer;
        const l = it.liquidacion_hoy;
        const sumaTaxiUber = l.taxi_total + l.uber_total;
        const pctTaxi = sumaTaxiUber > 0 ? (l.taxi_total / sumaTaxiUber * 100) : 50;
        const pctUber = 100 - pctTaxi;
        const foto = c.foto_path ? Api.fileUrl(c.foto_path) : '';

        return `
        <div class="card" data-chofer-id="${c.id}">
          <div class="card-top">
            <div class="card-person">
              ${foto
                ? `<img class="avatar" style="width:52px;height:52px;border-radius:50%;object-fit:cover;flex-shrink:0;" src="${foto}" alt="">`
                : `<div class="avatar icon taxi">${Utils.esc(iniciales(c.nombre))}</div>`}
              <div><p class="p-name" style="font-size:16px;">${Utils.esc(c.nombre)}</p><p class="p-role">${it.movil_actual ? 'Móvil #' + Utils.esc(it.movil_actual) : 'Sin turno activo'}</p></div>
            </div>
            <span class="status-pill ${it.en_servicio ? 'en-servicio' : 'fuera-servicio'}"><span class="dot"></span>${it.en_servicio ? 'En servicio' : 'Fuera de servicio'}</span>
          </div>
          <div class="card-summary">
            <div class="stadium-wrap">
              <div class="stadium-labels">
                <span style="flex:0 0 40%; color:#b8960b;">Taxi ${Utils.money(l.taxi_total)}</span>
                <span style="flex:0 0 40%; color:#1c1c1c;">Uber ${Utils.money(l.uber_total)}</span>
                <span style="color:#7a7a76;">${l.viajes_count} viajes</span>
              </div>
              <div class="stadium-bar">
                <div class="stadium-seg taxi" style="width:${pctTaxi}%;"></div>
                <div class="stadium-seg uber" style="width:${pctUber}%;"></div>
              </div>
            </div>
          </div>
          <div class="card-detail">
            <div class="seg-block" style="margin-top:6px;">
              <div class="seg-labels-row">
                <span class="seg-lbl" style="color:#b8960b;">Efvo ${Utils.money(l.ingreso_efvo)}</span>
                <span class="seg-lbl" style="color:#1c1c1c;">Transf ${Utils.money(l.ingreso_transf)}</span>
              </div>
            </div>
            <div class="fuel-row" style="padding:0 2px 10px;"><span class="lbl">Combustible</span><span class="val">${Utils.money(l.gastos_combustible)}</span></div>
            <div class="fuel-row" style="padding:0 2px 10px;"><span class="lbl">Descuentos</span><span class="val" style="color:#c9483d;">-${Utils.money(l.descuentos_total)}</span></div>
            <div class="fuel-row" style="padding:0 2px 10px;"><span class="lbl">Gastos</span><span class="val" style="color:#c9483d;">-${Utils.money(l.gastos_total)}</span></div>
            <div class="fuel-row" style="padding:0 2px 10px;"><span class="lbl">Cuenta corriente</span><span class="val" style="color:#4d64d9;">${Utils.money(l.cc_total)}${l.cc_pendiente > 0 ? ' (' + Utils.money(l.cc_pendiente) + ' sin cobrar)' : ''}</span></div>
            <div class="rendir-badge"><span class="lbl">A rendir (después de ${l.comision_pct}%)</span><span class="val">${Utils.money(l.a_rendir)}</span></div>
          </div>
        </div>`;
      }).join('');
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  async function renderReportes() {
    Utils.showLoading();
    try {
      const data = await Api.get('/reportes', reportesParams());
      const sumaTaxiUber = data.taxi_total + data.uber_total;
      const pctTaxi = sumaTaxiUber > 0 ? (data.taxi_total / sumaTaxiUber * 100) : 50;
      const tituloPeriodo = periodoActual === 'dia' ? 'Hoy' : periodoActual === 'semana' ? 'Esta semana' : 'Este mes';

      el('reportes-content').innerHTML = `
        <div class="chart-card liquidacion-flota-card only-desktop">
          <p class="chart-title">Liquidación de la flota <span class="chart-sub">${tituloPeriodo}</span></p>
          <div class="liquidacion-stats">
            <div class="liquidacion-stat"><p class="lbl">Total bruto</p><p class="val">${Utils.money(data.total_bruto)}</p></div>
            <div class="liquidacion-stat"><p class="lbl">Comisiones choferes (${data.comision_pct}%)</p><p class="val" style="color:#2fa85a;">-${Utils.money(data.comision_chofer)}</p></div>
            <div class="liquidacion-stat"><p class="lbl">Gastos de la flota</p><p class="val" style="color:#e2544c;">-${Utils.money(data.gastos_total)}</p></div>
            <div class="liquidacion-stat destacado"><p class="lbl">A rendir total</p><p class="val">${Utils.money(data.a_rendir)}</p></div>
          </div>
        </div>

        <div class="report-kpis">
          <div class="report-kpi"><p class="lbl">Total bruto</p><p class="val">${Utils.money(data.total_bruto)}</p></div>
          <div class="report-kpi"><p class="lbl">A rendir</p><p class="val">${Utils.money(data.a_rendir)}</p></div>
          <div class="report-kpi"><p class="lbl">Comisiones</p><p class="val">${Utils.money(data.comision_chofer)}</p></div>
          <div class="report-kpi"><p class="lbl">Gastos</p><p class="val">${Utils.money(data.gastos_total)}</p></div>
          <div class="report-kpi"><p class="lbl">Cta cte pendiente</p><p class="val">${Utils.money(data.cc_pendiente)}</p></div>
          <div class="report-kpi"><p class="lbl">Choferes activos</p><p class="val">${data.choferes_activos}/${data.choferes_total}</p></div>
        </div>

        <div class="chart-card">
          <p class="chart-title">Taxi vs Uber</p>
          <div class="simple-seg-bar"><div class="seg-a" style="width:${pctTaxi}%;"></div><div class="seg-b" style="width:${100 - pctTaxi}%;"></div></div>
          <div class="simple-seg-legend"><span>Taxi <b>${Utils.money(data.taxi_total)}</b></span><span>Uber <b>${Utils.money(data.uber_total)}</b></span></div>
        </div>

        <div class="chart-card">
          <p class="chart-title">Efectivo vs Transferencia</p>
          <div class="simple-seg-bar"><div class="seg-a" style="width:${data.total_bruto > 0 ? (100 - data.transferencia_pct) : 50}%;"></div><div class="seg-b" style="width:${data.transferencia_pct}%;"></div></div>
          <div class="simple-seg-legend"><span>Efectivo <b>${Utils.money(data.ingreso_efvo)}</b></span><span>Transferencia <b>${Utils.money(data.ingreso_transf)}</b></span></div>
        </div>

        <div class="report-kpis">
          <div class="report-kpi"><p class="lbl">Combustible</p><p class="val">${Utils.money(data.gastos_combustible)}</p><p class="lbl" style="margin-top:4px;">${Utils.pct(data.combustible_pct)}</p></div>
          <div class="report-kpi"><p class="lbl">Descuentos</p><p class="val">${Utils.money(data.descuentos_total)}</p><p class="lbl" style="margin-top:4px;">${Utils.pct(data.descuentos_pct)}</p></div>
          <div class="report-kpi"><p class="lbl">Cuenta corriente</p><p class="val">${Utils.money(data.cc_total)}</p><p class="lbl" style="margin-top:4px;">${Utils.pct(data.cc_pct)}</p></div>
        </div>

        <div class="chart-card clickable" id="card-ranking">
          <p class="chart-title">Facturación por chofer</p>
          ${data.ranking_choferes.length ? data.ranking_choferes.map((r) => `
            <div class="ranking-row">
              <div><p class="ranking-name">${Utils.esc(r.nombre)}</p><p class="ranking-sub">${r.viajes_count} viajes</p></div>
              <p class="ranking-val">${Utils.money(r.total_bruto)}</p>
            </div>`).join('') : '<div class="empty-state">Sin datos en este período.</div>'}
        </div>

        <div class="chart-card clickable" id="card-comisiones">
          <p class="chart-title">Comisiones por chofer <i class="ti ti-chevron-right"></i></p>
          <p class="subtitle" style="margin:0;">Tocá para ver el detalle ordenado por comisión.</p>
        </div>

        <div class="chart-card clickable" id="card-service">
          <p class="chart-title">Service por móvil <i class="ti ti-chevron-right"></i></p>
          <p class="subtitle" style="margin:0;">Tocá para ver el historial de mantenimiento.</p>
        </div>
      `;

      el('card-comisiones').addEventListener('click', abrirComisiones);
      el('card-service').addEventListener('click', abrirService);

      el('btn-export-reportes').style.display = 'flex';
      const exportParams = new URLSearchParams(reportesParams()).toString();
      el('btn-export-reportes').href = CONFIG.API_BASE + '/reportes/export?' + exportParams;
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  async function abrirComisiones() {
    Utils.showLoading();
    try {
      const data = await Api.get('/reportes/comisiones', reportesParams());
      el('comisiones-modal-body').innerHTML = data.comisiones.length
        ? data.comisiones.map((r) => `
          <div class="modal-list-row">
            <div class="row-info"><p class="row-name">${Utils.esc(r.nombre)}</p><p class="row-sub">${r.viajes_count} viajes · a rendir ${Utils.money(r.a_rendir)}</p></div>
            <p class="row-val">${Utils.money(r.comision_chofer)}</p>
          </div>`).join('')
        : '<div class="empty-state">Sin datos en este período.</div>';
      Utils.openModal('comisiones-modal');
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  async function abrirService() {
    Utils.showLoading();
    try {
      const data = await Api.get('/reportes/services');
      const movilesNumeros = Object.keys(data.services_por_movil);
      el('service-modal-body').innerHTML = movilesNumeros.length
        ? movilesNumeros.map((numero) => `
          <p class="modal-field-lbl" style="margin-top:14px;">Móvil #${Utils.esc(numero)}</p>
          ${data.services_por_movil[numero].map((s) => `
            <div class="service-entry">
              <p class="service-entry-date">${Utils.fechaHora(s.fecha)} · ${Utils.esc(s.chofer_nombre)}</p>
              <p class="service-entry-text">${Utils.esc(s.descripcion)}</p>
            </div>`).join('')}
        `).join('')
        : '<div class="empty-state">Todavía no hay entradas de service.</div>';
      Utils.openModal('service-modal');
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  async function renderAsistente() {
    Utils.showLoading();
    try {
      const data = await Api.get('/asistente/informe');
      const bloque = (titulo, icono, clase, items) => `
        <div class="report-card ${clase}">
          <div class="report-head"><div class="report-icon"><i class="ti ${icono}"></i></div><p class="report-head-title">${titulo}</p></div>
          ${items.map((txt) => `<div class="report-item"><div class="report-bullet">${clase === 'good' ? '✓' : clase === 'bad' ? '!' : 'i'}</div><p>${Utils.esc(txt)}</p></div>`).join('')}
        </div>`;
      el('asistente-content').innerHTML =
        bloque('Lo que va bien', 'ti-mood-smile', 'good', data.bien) +
        bloque('Necesita atención', 'ti-alert-triangle', 'bad', data.atencion) +
        bloque('Consejos', 'ti-bulb', 'tips', data.consejos);
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  async function renderPerfil() {
    Utils.showLoading();
    try {
      const [empresaData, choferesData] = await Promise.all([Api.get('/empresa'), Api.get('/choferes')]);
      const emp = empresaData.empresa;
      el('empresa-perfil-card').innerHTML = `
        <div class="profile-card">
          <div class="login-badge" style="margin:0 auto 14px;"><i class="ti ti-building-store"></i></div>
          <p class="profile-name">${Utils.esc(emp.nombre)}</p>
          <p class="profile-role">Plan ${Utils.esc(emp.plan)}</p>
          <div class="profile-grid">
            <div class="profile-item"><span class="lbl">CUIT</span><p class="val">${Utils.esc(emp.cuit || '-')}</p></div>
            <div class="profile-item"><span class="lbl">Ciudad</span><p class="val">${Utils.esc(emp.ciudad || '-')}</p></div>
            <div class="profile-item"><span class="lbl">Teléfono</span><p class="val">${Utils.esc(emp.telefono || '-')}</p></div>
            <div class="profile-item"><span class="lbl">Comisión chofer</span><p class="val">${emp.comision_chofer_pct}%</p></div>
            <div class="profile-item"><span class="lbl">Choferes</span><p class="val">${emp.choferes_count}</p></div>
            <div class="profile-item"><span class="lbl">Móviles</span><p class="val">${emp.moviles_count}</p></div>
          </div>
          <button class="profile-link-btn" id="btn-editar-empresa">Editar datos de la flota</button>
        </div>`;

      el('btn-editar-empresa').addEventListener('click', async () => {
        const nombre = prompt('Nombre de la flota', emp.nombre) ?? emp.nombre;
        const cuit = prompt('CUIT', emp.cuit || '') ?? emp.cuit;
        const ciudad = prompt('Ciudad', emp.ciudad || '') ?? emp.ciudad;
        const telefono = prompt('Teléfono', emp.telefono || '') ?? emp.telefono;
        const comisionStr = prompt('Comisión del chofer (%)', emp.comision_chofer_pct) ?? String(emp.comision_chofer_pct);
        Utils.showLoading();
        try {
          await Api.put('/empresa', { nombre, cuit, ciudad, telefono, comision_chofer_pct: comisionStr });
          Utils.toast('Datos actualizados', 'success');
          renderPerfil();
        } catch (err) {
          Utils.toast(err.message, 'error');
        } finally {
          Utils.hideLoading();
        }
      });

      const items = choferesData.choferes;
      el('flota-list').innerHTML = items.length
        ? items.map((it) => `
          <div class="flota-row" data-action="editar-chofer" data-id="${it.chofer.id}">
            ${it.chofer.foto_path
              ? `<img class="flota-row-avatar" src="${Api.fileUrl(it.chofer.foto_path)}" alt="">`
              : `<div class="flota-row-avatar" style="display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#6b6b68;">${Utils.esc(iniciales(it.chofer.nombre))}</div>`}
            <div class="flota-row-info">
              <p class="flota-row-name">${Utils.esc(it.chofer.nombre)}</p>
              <p class="flota-row-phone">${Utils.esc(it.chofer.telefono)} · @${Utils.esc(it.chofer.username)}</p>
            </div>
            <i class="ti ti-chevron-right flota-row-chev"></i>
          </div>`).join('')
        : '<div class="empty-state">Todavía no agregaste choferes.</div>';
    } catch (e) {
      Utils.toast(e.message, 'error');
    } finally {
      Utils.hideLoading();
    }
  }

  function abrirFormChofer(chofer) {
    editandoChoferId = chofer ? chofer.id : null;
    choferFormFotoFile = null;
    el('chofer-form-title').textContent = chofer ? 'Editar chofer' : 'Nuevo chofer';
    el('chofer-form-nombre').value = chofer ? chofer.nombre : '';
    el('chofer-form-telefono').value = chofer ? chofer.telefono : '';
    el('chofer-form-vehiculo').value = chofer ? (chofer.vehiculo || '') : '';
    el('chofer-form-comision').value = chofer && chofer.comision_pct !== null ? chofer.comision_pct : '';
    el('chofer-form-username').value = chofer ? chofer.username : '';
    el('chofer-form-username').disabled = !!chofer;
    el('chofer-form-password').value = '';
    el('chofer-form-password').placeholder = chofer ? 'Dejar vacío para no cambiarla' : 'Contraseña';
    el('chofer-form-password-lbl').textContent = chofer ? 'Nueva contraseña (opcional)' : 'Contraseña';
    el('chofer-form-error').classList.remove('show');
    const preview = el('chofer-form-avatar-preview');
    if (chofer && chofer.foto_path) {
      preview.src = Api.fileUrl(chofer.foto_path);
      preview.style.display = 'block';
    } else {
      preview.style.display = 'none';
    }
    el('btn-eliminar-chofer-form').style.display = chofer ? 'block' : 'none';
    Utils.openModal('chofer-form-modal');
  }

  function eliminarChoferActual() {
    if (!editandoChoferId) return;
    Utils.confirmDialog('¿Eliminar este chofer? No va a poder ingresar más, pero su historial se conserva.', async () => {
      Utils.showLoading();
      try {
        await Api.del(`/choferes/${editandoChoferId}`);
        Utils.closeModal('chofer-form-modal');
        Utils.toast('Chofer eliminado', 'success');
        renderPerfil();
        if (document.getElementById('dueno-view-choferes').classList.contains('active')) renderChoferes();
      } catch (e) {
        Utils.toast(e.message, 'error');
      } finally {
        Utils.hideLoading();
      }
    });
  }

  async function guardarChoferForm() {
    const nombre = el('chofer-form-nombre').value.trim();
    const telefono = el('chofer-form-telefono').value.trim();
    const vehiculo = el('chofer-form-vehiculo').value.trim();
    const comision = el('chofer-form-comision').value.trim();
    const username = el('chofer-form-username').value.trim();
    const password = el('chofer-form-password').value;

    if (!nombre || !telefono || !username) {
      el('chofer-form-error').textContent = 'Completá nombre, teléfono y usuario.';
      el('chofer-form-error').classList.add('show');
      return;
    }
    if (!editandoChoferId && !password) {
      el('chofer-form-error').textContent = 'Ingresá una contraseña para el nuevo chofer.';
      el('chofer-form-error').classList.add('show');
      return;
    }

    Utils.showLoading();
    try {
      let choferId = editandoChoferId;
      if (editandoChoferId) {
        await Api.put(`/choferes/${editandoChoferId}`, { nombre, telefono, vehiculo, comision_pct: comision, password: password || undefined });
      } else {
        const data = await Api.post('/choferes', { nombre, telefono, vehiculo, comision_pct: comision, username, password });
        choferId = data.chofer.id;
      }
      if (choferFormFotoFile && choferId) {
        const fd = new FormData();
        fd.append('foto', choferFormFotoFile);
        await Api.postForm(`/choferes/${choferId}/foto`, fd);
      }
      Utils.closeModal('chofer-form-modal');
      Utils.toast('Chofer guardado', 'success');
      renderPerfil();
      if (document.getElementById('dueno-view-choferes').classList.contains('active')) renderChoferes();
    } catch (e) {
      el('chofer-form-error').textContent = e.message;
      el('chofer-form-error').classList.add('show');
    } finally {
      Utils.hideLoading();
    }
  }

  function bindEventsOnce() {
    if (eventsBound) return;
    eventsBound = true;

    el('dueno-logout').addEventListener('click', () => Auth.logout());

    document.querySelectorAll('#dueno-nav .nav-item').forEach((item) => {
      item.addEventListener('click', () => {
        const view = item.dataset.view;
        showView(document.getElementById('screen-dueno'), view, 'dueno-nav');
        const titles = { 'dueno-view-choferes': 'Choferes', 'dueno-view-reportes': 'Reportes', 'dueno-view-asistente': 'Asistente', 'dueno-view-perfil': 'Perfil' };
        el('dueno-titulo').textContent = titles[view] || '';
        el('btn-export-reportes').style.display = view === 'dueno-view-reportes' ? 'flex' : 'none';
        if (view === 'dueno-view-choferes') renderChoferes();
        if (view === 'dueno-view-reportes') renderReportes();
        if (view === 'dueno-view-asistente') renderAsistente();
        if (view === 'dueno-view-perfil') renderPerfil();
      });
    });

    el('choferes-stack').addEventListener('click', (ev) => {
      const card = ev.target.closest('.card');
      if (!card) return;
      card.classList.toggle('selected');
      card.querySelector('.card-detail')?.classList.toggle('open');
    });

    poblarSelectorMeses();
    el('sel-reportes-month').addEventListener('change', (ev) => {
      mesSeleccionado = ev.target.value;
      renderReportes();
    });

    document.getElementById('reportes-filter-row').addEventListener('click', (ev) => {
      const btn = ev.target.closest('button[data-period]');
      if (!btn) return;
      periodoActual = btn.dataset.period;
      document.querySelectorAll('#reportes-filter-row button').forEach((b) => b.classList.toggle('active', b === btn));
      el('reportes-month-picker').classList.toggle('open', periodoActual === 'mes');
      renderReportes();
    });

    el('btn-cerrar-comisiones').addEventListener('click', () => Utils.closeModal('comisiones-modal'));
    el('btn-cerrar-service').addEventListener('click', () => Utils.closeModal('service-modal'));
    el('btn-cerrar-ver-turno').addEventListener('click', () => Utils.closeModal('ver-turno-modal'));
    el('btn-cerrar-foto-full').addEventListener('click', () => Utils.closeModal('foto-full-modal'));

    el('btn-agregar-chofer').addEventListener('click', () => abrirFormChofer(null));
    el('flota-list').addEventListener('click', (ev) => {
      const row = ev.target.closest('[data-action="editar-chofer"]');
      if (!row) return;
      Api.get('/choferes').then((data) => {
        const it = data.choferes.find((c) => String(c.chofer.id) === row.dataset.id);
        if (it) abrirFormChofer(it.chofer);
      });
    });
    el('btn-cancelar-chofer-form').addEventListener('click', () => Utils.closeModal('chofer-form-modal'));
    el('btn-guardar-chofer-form').addEventListener('click', guardarChoferForm);
    el('btn-eliminar-chofer-form').addEventListener('click', eliminarChoferActual);
    el('chofer-form-avatar-preview').addEventListener('click', () => {
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/*';
      input.onchange = () => {
        const file = input.files[0];
        if (!file) return;
        choferFormFotoFile = file;
        el('chofer-form-avatar-preview').src = URL.createObjectURL(file);
        el('chofer-form-avatar-preview').style.display = 'block';
      };
      input.click();
    });
  }

  return { init };
})();
