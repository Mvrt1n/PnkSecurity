'use strict';
// Frontend de la Carta Digital: carrito e IA vía fetch con token CSRF.
(function () {
  const main = document.querySelector('main');
  const csrf = main ? main.getAttribute('data-csrf') : null;
  const restauranteId = main ? main.getAttribute('data-restaurante') : null;

  function toast(msg) {
    let t = document.querySelector('.toast');
    if (!t) { t = document.createElement('div'); t.className = 'toast'; document.body.appendChild(t); }
    t.textContent = msg; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 1800);
  }

  async function postJSON(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'content-type': 'application/json', 'x-csrf-token': csrf },
      body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.mensaje || 'Error');
    return data;
  }

  // Agregar al carrito
  document.querySelectorAll('.add-cart').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        const data = await postJSON('/api/carrito/agregar', { itemId: Number(btn.dataset.item) });
        toast(`Agregado · ${data.carrito.unidades} ítem(s) · $${data.carrito.total}`);
      } catch (e) { toast(e.message); }
    });
  });

  // Asistente de IA
  const iaForm = document.getElementById('ia-form');
  if (iaForm) {
    iaForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const pregunta = document.getElementById('ia-pregunta').value.trim();
      const cont = document.getElementById('ia-respuesta');
      cont.hidden = false; cont.innerHTML = 'Pensando…';
      try {
        const data = await postJSON('/api/ia/recomendar', {
          pregunta, restauranteId: Number(restauranteId),
        });
        const r = data.respuesta;
        cont.innerHTML = `<div>${r.texto.replace(/</g, '&lt;')}</div>` +
          `<div class="tag">Fuente: ${r.fuente}${r.degradado ? ' (IA no disponible, modo local)' : ''}</div>`;
      } catch (e) { cont.innerHTML = e.message; }
    });
  }
})();
