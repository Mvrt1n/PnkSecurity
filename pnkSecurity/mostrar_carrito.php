<?php
/**
 * Detalle del carrito.
 * Corrige: SQLi (PNK-02), XSS reflejado en keyid/id (PNK-12), ruta de imagen errónea (../imagenes),
 * variable inexistente $_GET['id'], iteración sobre carrito no inicializado.
 * ASVS v5.0.0: 1.2.1, 1.2.4, 2.2.1
 */
declare(strict_types=1);

require __DIR__ . '/setup/setup.php';

iniciar_sesion_segura();
cabeceras_seguridad();

$key = entero_positivo($_GET['keyid'] ?? null) ?? entero_positivo($_SESSION['restaurante_id'] ?? null);

$datos_restorant = $key === null ? false : consulta(
    'SELECT restautantes.nombre, restautantes.id, restautantes.foto
       FROM restautantes WHERE restautantes.id = :id AND restautantes.eliminado IS NULL',
    [':id' => $key]
)->fetch();

if (!$datos_restorant) {
    http_response_code(404);
    exit('Restaurante no encontrado');
}

$carrito = is_array($_SESSION['carrito'] ?? null) ? $_SESSION['carrito'] : [];
$fotoResto = archivo_seguro($datos_restorant['foto']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($datos_restorant['nombre']) ?></title>
  <link rel="stylesheet" href="vendors/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="vendors/themify-icons/themify-icons.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<section>
  <div class="container contendor">
    <div class="row">
      <div class="col-lg-4"><div style="text-align: center;">
        <div class="media"><img class="logosintituciones" src="img/logo.png" width="170" alt="PNK"></div><br><br>
      </div></div>
      <div class="col-lg-4"><div style="text-align: center;">
        <?php if ($fotoResto): ?>
          <img class="logosintituciones" src="<?= e('imagenes/cod' . $key . '/' . $fotoResto) ?>" width="170" alt="">
        <?php else: ?>
          <img class="logosintituciones" src="img/logo_empresa_comodin.png" width="170" alt="">
        <?php endif; ?>
      </div></div>
      <div class="col-lg-4"><div class="carro"><div class="media float-right">
        <a class="volver" href="index.php?id=<?= $key ?>">Volver a la Carta</a>&nbsp;&nbsp;
        <a class="limpiar" href="#">Eliminar Todo</a>
      </div></div></div>
    </div>
  </div>
</section>
<section>
  <div class="container contendor"><div class="row"><div class="col-lg-12">
    <h4 class="intro-title" style="text-align: center">Productos Agregados para Solicitar</h4>
  </div></div></div>
</section>
<section>
  <div class="container contendor"><div class="row"><div class="col-lg-12">
    <table class="table table-hover table-lg">
      <thead><tr><th scope="col">Código</th><th scope="col">Producto</th><th scope="col">Precios</th><th scope="col">Acción</th></tr></thead>
      <tbody>
      <?php foreach ($carrito as $value): ?>
        <tr>
          <th scope="row"><?= (int) $value['id'] ?></th>
          <td><?= e($value['nombre']) ?></td>
          <td><?= e(moneda_chilena($value['precio'])) ?></td>
          <td><a class="elim text-danger" data-pos="<?= e($value['posicion']) ?>" href="#" aria-label="Eliminar">
            <i class="ti-trash" style="font-size:1.6em"></i></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$carrito): ?>
        <tr><td colspan="4" class="text-center">No hay productos seleccionados.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    <div><hr><br>
      <h3 class="price-tag text-center">¡Si estás listo con tu pedido, llama al garzón para solicitarlo!</h3>
    </div>
  </div></div></div>
</section>

<footer class="container contendor"><br><hr>
  <div class="container"><div class="footer-bottom row align-items-center text-center">
    <p class="footer-text m-0 col-md-12">
      Copyright 2020, Todos los Derechos reservados PNK · <a href="privacidad.php">Aviso de privacidad</a><br>
      Sitio desarrollado para el Laboratorio de Programación Segura I<br>
      <b>Área Tecnologías de Información y Ciberseguridad - Inacap Sede La Serena</b><br><br>
    </p>
  </div></div>
</footer>

<script src="vendors/jquery/jquery-3.7.1.min.js"></script>
<script src="vendors/bootstrap/bootstrap.bundle.min.js"></script>
<script src="js/controladorajax.js"></script>
</body>
</html>
