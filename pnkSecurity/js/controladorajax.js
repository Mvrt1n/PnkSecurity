// Controlador AJAX del carrito.
// Seguridad: envía el token CSRF en la cabecera X-CSRF-Token, codifica parámetros con
// $.param (sin concatenación de strings) y lee los ids desde atributos data-*.
(function ($) {
  'use strict';

  var token = $('meta[name="csrf-token"]').attr('content') || '';

  function enviar(datos, alTerminar) {
    $.ajax({
      type: 'POST',
      url: 'carrito.php',
      data: $.param(datos),
      dataType: 'json',
      headers: { 'X-CSRF-Token': token }
    }).done(alTerminar).fail(function (xhr) {
      var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'No fue posible procesar la solicitud.';
      $('#modalMensaje').text(msg);           // text(): nunca interpreta HTML
      if ($('#myModal').length) { $('#myModal').modal('show'); } else { window.alert(msg); }
    });
  }

  $(function () {
    $('.button[data-item]').on('click', function (ev) {
      ev.preventDefault();
      var id = String($(this).data('item'));
      if (!/^[0-9]+$/.test(id)) { return; }
      enviar({ op: '1', iditems: id }, function () {
        $('#modalMensaje').text('El producto seleccionado ha sido agregado.');
        $('#myModal').modal('show');
      });
    });

    $('.elim[data-pos]').on('click', function (ev) {
      ev.preventDefault();
      enviar({ op: '2', pos: String($(this).data('pos')) }, function () { window.location.reload(); });
    });

    $('.limpiar').on('click', function (ev) {
      ev.preventDefault();
      enviar({ op: '3' }, function () { window.location.reload(); });
    });
  });
})(jQuery);
