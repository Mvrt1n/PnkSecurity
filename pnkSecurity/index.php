<?php
/**
 * Carta digital del restaurante.
 * Corrige: SQLi en id (PNK-02), XSS reflejado y almacenado (PNK-12/PNK-05), CSRF en formularios (PNK-07),
 * error off-by-one en pestañas, utf8_encode obsoleto (PHP 8.2+).
 * ASVS v5.0.0: 1.2.1, 1.2.2, 1.2.4, 2.2.1, 3.4.x, 3.5.1
 */
declare(strict_types=1);

require __DIR__ . '/setup/setup.php';

iniciar_sesion_segura();
cabeceras_seguridad();

$key = entero_positivo($_GET['id'] ?? null);
$datos_restorant = false;

if ($key !== null) {
    $datos_restorant = consulta(
        'SELECT direcciones.calle, direcciones.numero, direcciones.comuna, direcciones.region,
                restautantes.nombre, restautantes.id, restautantes.fono, restautantes.email, restautantes.foto
           FROM restautantes
           INNER JOIN direcciones ON restautantes.direcciones_id = direcciones.id
          WHERE restautantes.id = :id AND restautantes.eliminado IS NULL',
        [':id' => $key]
    )->fetch();
}

if (!$datos_restorant) {
    if (isset($_GET['id'])) {
        log_seguridad('validacion_fallida', ['param' => 'id']);
    }
    http_response_code(404);
    ?><!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Restaurante no encontrado</title><link rel="stylesheet" href="vendors/bootstrap/bootstrap.min.css"></head>
<body><div class="container text-center" style="padding:4rem 1rem"><h4>Restaurante no encontrado</h4>
<p>Escanee nuevamente el código QR de su mesa.</p></div></body></html>
<?php
    exit;
}

$_SESSION['restaurante_id'] = $key;
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$carpeta = 'imagenes/cod' . $key . '/';
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
  <link rel="stylesheet" href="vendors/owl-carousel/owl.theme.default.min.css">
  <link rel="stylesheet" href="vendors/owl-carousel/owl.carousel.min.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<nav class="navbar navbar-light bg-light">
  <?php if (!usuario_autenticado()): ?>
    <form class="form-inline" action="setup/procesalogin.php" method="post" autocomplete="on">
      <?= csrf_campo() ?>
      <div class="form-group">
        <div class="input-group-prepend"><span class="input-group-text" id="basic-addon1">@</span></div>
        <input type="email" class="form-control" name="frmusuario" placeholder="Correo" maxlength="255"
               autocomplete="username" required aria-label="Correo">
      </div>
      <div class="form-group">
        <input type="password" class="form-control" name="frmpassword" placeholder="Contraseña" maxlength="1024"
               autocomplete="current-password" required aria-label="Contraseña">
      </div>
      <div class="form-group">
        <input type="text" class="form-control" name="frmtotp" placeholder="Código 2FA (si aplica)" maxlength="10"
               inputmode="numeric" autocomplete="one-time-code" aria-label="Código de verificación">
      </div>
      <button type="submit" class="btn btn-outline-primary my-2 my-sm-0">Ingresar</button>
    </form>
    <?php if ($flash): ?><span class="text-danger ml-2" role="alert"><?= e($flash) ?></span><?php endif; ?>
  <?php else: ?>
    <form class="form-inline" action="setup/cerrar_sesion.php" method="post">
      <?= csrf_campo() ?>
      <span class="mr-2">Bienvenido: <?= e($_SESSION['nombre']) ?></span>
      <button type="submit" class="btn btn-link p-0">Cerrar sesión</button>
    </form>
  <?php endif; ?>
</nav>

<section>
  <div class="container contendor">
    <div class="row">
      <div class="col-lg-4">
        <div style="text-align: center;"><div class="media">
          <img class="logosintituciones" src="img/logo.png" width="190" alt="PNK">
        </div></div>
      </div>
      <div class="col-lg-4">
        <div style="text-align: center;"><div class="media">
          <?php if ($fotoResto): ?>
            <img class="logosintituciones" src="<?= e($carpeta . $fotoResto) ?>" width="170" alt="">
          <?php else: ?>
            <img class="logosintituciones" src="img/logo_empresa_comodin.png" width="170" alt="">
          <?php endif; ?>
        </div></div>
      </div>
      <div class="col-lg-4">
        <div class="carro"><div class="media float-right">
          <a class="button_carrito" href="mostrar_carrito.php?keyid=<?= $key ?>">
            <i class="ti-shopping-cart"></i> Productos Seleccionados
          </a>
        </div></div>
      </div>
    </div>
    <div class="row">
      <div class="col-lg-12"><div class="text-center">
        <h4><?= e($datos_restorant['nombre']) ?></h4>
        <h5><i class="ti-location-pin"></i>
          <?= e($datos_restorant['calle'] . ' #' . $datos_restorant['numero'] . ', ' . $datos_restorant['comuna']) ?>
        </h5>
        <h5>
          <?php if ((string) $datos_restorant['fono'] !== ''): ?>
            <i class="ti-mobile"></i> <?= e($datos_restorant['fono']) ?>
          <?php endif; ?>
          <?php if ((string) $datos_restorant['email'] !== ''): ?>
            <i class="ti-email"></i> <?= e($datos_restorant['email']) ?>
          <?php endif; ?>
        </h5>
      </div></div>
    </div>
  </div>
</section>

<!-- DESTACADOS -->
<?php
$destacados = consulta(
    'SELECT items.id, items.tiempo, items.puntuacion, items.precio, items.descripcion, items.observaciones,
            items.nombre, items.foto
       FROM categorias
       INNER JOIN items  ON items.categorias_id = categorias.id
       INNER JOIN cartas ON categorias.cartas_id = cartas.id
      WHERE items.destacado = 1 AND items.eliminado IS NULL AND items.visible = 1
        AND cartas.restautantes_id = :id AND cartas.eliminada IS NULL
        AND categorias.visible = 1 AND categorias.eliminado IS NULL',
    [':id' => $key]
)->fetchAll();

if ($destacados): ?>
<section class="destacados">
  <div class="container contendor">
    <div class="section-intro"><h4 class="intro-title">Menús Destacados</h4></div>
    <div class="owl-carousel owl-theme featured-carousel">
      <?php foreach ($destacados as $d): $foto = archivo_seguro($d['foto']); ?>
      <div class="featured-item">
        <img class="card-img rounded-0" width="350" height="235" alt=""
             src="<?= $foto ? e($carpeta . $foto) : 'img/carta_comodin.png' ?>">
        <div class="item-body">
          <h3><?= e($d['nombre']) ?></h3>
          <p><?= e($d['descripcion']) ?><br><?= e($d['observaciones']) ?><br>
            <?php if ((string) $d['tiempo'] !== ''): ?><i class="ti-alarm-clock"></i> <?= e($d['tiempo']) ?><?php endif; ?>
          </p>
          <div class="d-flex justify-content-between">
            <ul class="rating-star">
              <?php for ($i = 1; $i <= min(5, (int) $d['puntuacion']); $i++): ?><li><i class="ti-star"></i></li><?php endfor; ?>
            </ul>
            <h3 class="price-tag"><?= e(moneda_chilena($d['precio'])) ?></h3>
          </div>
          <a class="button" href="#" data-item="<?= (int) $d['id'] ?>" style="margin:0%">Seleccionar</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section>
  <div class="container contendor"><div class="container"><div class="row"><div class="col-lg-12">
  <?php
  $cartas = consulta(
      'SELECT id, nombre FROM cartas WHERE restautantes_id = :id AND visible = 1 AND eliminada IS NULL ORDER BY orden ASC',
      [':id' => $key]
  )->fetchAll();
  ?>
    <nav>
      <div class="nav nav-tabs nav-fill" id="nav-tab" role="tablist">
        <?php foreach ($cartas as $n => $c): ?>
          <a class="nav-item nav-link<?= $n === 0 ? ' active' : '' ?>" id="nav-carta-<?= (int) $c['id'] ?>-tab"
             data-toggle="tab" href="#carta-<?= (int) $c['id'] ?>" role="tab"
             aria-controls="carta-<?= (int) $c['id'] ?>" aria-selected="<?= $n === 0 ? 'true' : 'false' ?>"><?= e($c['nombre']) ?></a>
        <?php endforeach; ?>
      </div>
    </nav>
    <div class="tab-content py-3 px-3 px-sm-0" id="nav-tabContent">
      <?php foreach ($cartas as $n => $c): ?>
      <div class="tab-pane fade<?= $n === 0 ? ' show active' : '' ?>" id="carta-<?= (int) $c['id'] ?>" role="tabpanel"
           aria-labelledby="nav-carta-<?= (int) $c['id'] ?>-tab">
        <?php
        $categorias = consulta(
            'SELECT id, nombre FROM categorias WHERE visible = 1 AND cartas_id = :c AND eliminado IS NULL ORDER BY orden ASC',
            [':c' => (int) $c['id']]
        )->fetchAll();
        if (!$categorias): ?>
          <div class="row"><div class="col-lg-12"><div class="text-center"><h4>No hay información para mostrar</h4></div></div></div>
        <?php endif;
        foreach ($categorias as $cat): ?>
          <div class="section-intro mb-20px"><h4 class="intro-title"><?= e($cat['nombre']) ?></h4></div>
          <div class="row">
          <?php
          $items = consulta(
              'SELECT id, nombre, descripcion, observaciones, tiempo, puntuacion, precio, foto
                 FROM items WHERE categorias_id = :cat AND visible = 1 AND eliminado IS NULL ORDER BY orden ASC',
              [':cat' => (int) $cat['id']]
          )->fetchAll();
          if (!$items): ?>
            <div class="col-lg-12"><div class="text-center"><h4>No hay información para mostrar</h4></div></div>
          <?php endif;
          foreach ($items as $it): $foto = archivo_seguro($it['foto']); ?>
            <div class="<?= count($items) > 1 ? 'col-lg-6' : 'col-lg-12' ?>">
              <div class="media align-items-center food-card">
                <img class="mr-3 mr-sm-4" width="120" alt="" src="<?= $foto ? e($carpeta . $foto) : 'img/carta_comodin.png' ?>">
                <div class="media-body">
                  <div class="d-flex justify-content-between food-card-title">
                    <h4><?= e($it['nombre']) ?></h4>
                    <h3 class="price-tag"><?= e(moneda_chilena($it['precio'])) ?></h3>
                  </div>
                  <p><?= e($it['descripcion']) ?><br><?= e($it['observaciones']) ?></p>
                  <?php if ((string) $it['tiempo'] !== ''): ?><p><i class="ti-alarm-clock"></i> <?= e($it['tiempo']) ?></p><?php endif; ?>
                  <ul class="rating-star">
                    <?php for ($k = 1; $k <= min(5, (int) $it['puntuacion']); $k++): ?><li><i class="ti-star"></i></li><?php endfor; ?>
                  </ul>
                  <a class="button float-right" href="#" data-item="<?= (int) $it['id'] ?>">Seleccionar</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div></div></div></div>
</section>

<div id="myModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="modalTitulo" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title text-lg-center" id="modalTitulo">Selección de Producto</h5>
      <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
    </div>
    <div class="modal-body" id="modalMensaje">El producto seleccionado ha sido agregado.</div>
  </div></div>
</div>

<section>
  <div class="container contendor"><div class="container"><div class="row"><div class="col-lg-12">
    <div class="section-intro">
      <h4 class="intro-title">Comentarios</h4>
      <?php if (usuario_autenticado()): ?>
        <form action="grcomentarios.php" method="post">
          <?= csrf_campo() ?>
          <input type="hidden" name="restaurante" value="<?= $key ?>">
          <p class="small text-muted">Su comentario se publicará con su nombre (<?= e($_SESSION['nombre']) ?>).
            Puede eliminarlo cuando quiera. <a href="privacidad.php">Aviso de privacidad</a>.</p>
          <div class="form-group">
            <label for="comentario">Comentario:</label>
            <textarea class="form-control" rows="5" id="comentario" name="comentario" maxlength="1000" required></textarea>
          </div>
          <button type="submit" class="btn btn-success">Comentar</button>
        </form>
      <?php endif; ?>
    </div>
    <br>
    <?php
    $comentarios = consulta(
        'SELECT Id, usuario, usuario_id, comentario FROM comentarios WHERE id_restaurante = :id ORDER BY Id DESC LIMIT 100',
        [':id' => $key]
    )->fetchAll();
    foreach ($comentarios as $com): ?>
      <div class="card bg-light"><div class="card-body">
        <b><?= e($com['usuario']) ?></b><br>
        <?= nl2br(e($com['comentario'])) ?>
        <?php if (usuario_autenticado() && (int) $com['usuario_id'] === $_SESSION['usuario_id']): ?>
          <form action="grcomentarios.php" method="post" class="mt-2">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="restaurante" value="<?= $key ?>">
            <input type="hidden" name="comentario_id" value="<?= (int) $com['Id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar mi comentario</button>
          </form>
        <?php endif; ?>
      </div></div><br>
    <?php endforeach; ?>
    <hr>
  </div></div></div></div>
</section>

<footer class="footer-area section-gap"><br>
  <div class="container"><div class="footer-bottom row align-items-center text-center">
    <p class="footer-text m-0 col-md-12">
      Copyright 2020, Todos los Derechos reservados PNK · <a href="privacidad.php">Aviso de privacidad</a><br>
      Sitio desarrollado para el Laboratorio de Programación Segura I<br>
      <b>Área Tecnologías de Información y Ciberseguridad - Inacap Sede La Serena</b>
    </p>
  </div></div>
</footer>

<script src="vendors/jquery/jquery-3.7.1.min.js"></script>
<script src="vendors/bootstrap/bootstrap.bundle.min.js"></script>
<script src="vendors/owl-carousel/owl.carousel.min.js"></script>
<script src="js/main.js"></script>
<script src="js/controladorajax.js"></script>
</body>
</html>
