<?php
/**
 * Aviso de privacidad (transparencia / información al titular).
 * Ley 19.628 (vigente al análisis) y adecuación a Ley 21.719 (vigencia 01-12-2026).
 * IMPORTANTE: los campos [ENTRE CORCHETES] deben completarlos el responsable del tratamiento
 * y validarse jurídicamente antes de publicar. No constituye asesoría legal.
 */
declare(strict_types=1);

require __DIR__ . '/setup/setup.php';

iniciar_sesion_segura();
cabeceras_seguridad(false);
$volver = entero_positivo($_SESSION['restaurante_id'] ?? null);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aviso de privacidad</title>
  <link rel="stylesheet" href="vendors/bootstrap/bootstrap.min.css">
</head>
<body>
<div class="container" style="max-width:760px;padding:2rem 1rem">
  <h3>Aviso de privacidad</h3>
  <p><b>Responsable del tratamiento:</b> [Razón social, RUT, domicilio y correo de contacto].</p>

  <h5>¿Qué datos tratamos y para qué?</h5>
  <ul>
    <li><b>Cuenta de usuario</b> (nombre, correo, contraseña protegida con hash): permitir iniciar sesión y publicar comentarios.</li>
    <li><b>Comentarios</b> (texto y nombre visible): mostrar opiniones sobre el restaurante. El nombre se publica junto al comentario.</li>
    <li><b>Registros de seguridad</b> (IP, fecha/hora, identificador seudonimizado del correo): prevenir accesos no autorizados y ataques de fuerza bruta.</li>
    <li><b>Carrito</b>: se guarda sólo en la sesión del navegador mientras dura la visita; no se asocia a su identidad.</li>
  </ul>

  <h5>Fundamento</h5>
  <p>[Indicar la base de licitud de cada tratamiento, p. ej. consentimiento del titular al crear la cuenta o el
    fundamento legal aplicable. Validar contra el texto vigente de la Ley 19.628 y, desde el 01-12-2026, el texto
    modificado por la Ley 21.719.]</p>

  <h5>Conservación</h5>
  <ul>
    <li>Intentos de inicio de sesión: [30 días].</li>
    <li>Registros de seguridad: [180 días].</li>
    <li>Cuenta y comentarios: mientras la cuenta esté activa o hasta que solicite su eliminación.</li>
  </ul>

  <h5>Terceros y transferencias</h5>
  <p>El sitio carga tipografías desde Google Fonts, lo que implica que su navegador envía su dirección IP a Google
    (servidores posiblemente fuera de Chile). [Evaluar: alojar las fuentes localmente para eliminar esta transferencia.]</p>

  <h5>Sus derechos</h5>
  <p>Puede solicitar acceso, rectificación, cancelación/supresión u oposición respecto de sus datos escribiendo a
    [correo de contacto]. Puede eliminar directamente sus comentarios desde la carta usando “Eliminar mi comentario”.</p>

  <p class="text-muted small">Última actualización: [fecha].</p>
  <?php if ($volver): ?><p><a href="index.php?id=<?= $volver ?>">Volver a la carta</a></p><?php endif; ?>
</div>
</body>
</html>
