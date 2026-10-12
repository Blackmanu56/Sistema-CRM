<?php
require_once __DIR__.'/../_init.php';
crm_proteger_staff();
require_once BASE_PATH.'/includes/informes.php';

// Solo Coordinación general (y super) arma; Presidencia solo ve publicados.
$puede_armar=function_exists('acceso_puede_armar_informes') ? acceso_puede_armar_informes() : ((acceso_es_coord_general() || acceso_es_super()) && !acceso_es_presidencia());
$puede_ver=acceso_puede_ver_informes() || $puede_armar;
if(!$puede_ver)
{
	header('Location: '.acceso_destino_login());
	exit();
}

$menu_activo='informes_presidencia';
$plantillas=informes_plantillas();

if($puede_armar && isset($_POST['publicar_informe']))
{
	$filtros=array(
		'desde'=>isset($_POST['desde']) ? $_POST['desde'] : '',
		'hasta'=>isset($_POST['hasta']) ? $_POST['hasta'] : '',
		'localidad'=>isset($_POST['localidad']) ? $_POST['localidad'] : '',
		'tipo_cliente'=>isset($_POST['tipo_cliente']) ? $_POST['tipo_cliente'] : '',
		'tipo_actividad'=>isset($_POST['tipo_actividad']) ? $_POST['tipo_actividad'] : ''
	);
	$msg=informes_publicar(
		isset($_POST['id_plantilla']) ? intval($_POST['id_plantilla']) : 0,
		isset($_POST['titulo']) ? $_POST['titulo'] : '',
		$filtros
	);
	$_SESSION['msginforme']=($msg=='') ? 'Informe publicado para Presidencia.' : $msg;
	header('Location: informes-presidencia.php');
	exit();
}

$id_ver=isset($_GET['id']) ? intval($_GET['id']) : 0;
$detalle=$id_ver>0 ? informes_publicado_traer($id_ver) : null;
$resumen=null;
if($detalle && isset($detalle['resumen_json']))
{
	$resumen=json_decode($detalle['resumen_json'], true);
	if(!is_array($resumen)) { $resumen=array(); }
}
$lista=informes_publicados();
$localidades=acceso_consulta("SELECT nombre FROM localidad ORDER BY nombre LIMIT 400");
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Informes Presidencia</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/animate.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/jquery-scrollbar/jquery.scrollbar.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
</head>
<body class="">
<?php crm_header_staff();?>
<div class="page-container row">
      <?php crm_menu_staff();?>
      <div class="clearfix"></div>
    </div>
  </div>
  <div class="page-content">
    <div class="clearfix"></div>
    <div class="content">
      <ul class="breadcrumb"><li><p>ESTÁS AQUÍ</p></li><li><a href="#" class="active">Informes para Presidencia</a></li></ul>
      <div class="page-title"><h3>Informes para Presidencia</h3></div>
      <?php if(isset($_SESSION['msginforme']) && $_SESSION['msginforme']!='') { ?>
      <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['msginforme']); $_SESSION['msginforme']=''; ?></div>
      <?php } ?>

      <?php if($detalle) { ?>
      <div class="grid simple">
        <div class="grid-title no-border">
          <h4><?php echo htmlspecialchars($detalle['titulo']); ?></h4>
        </div>
        <div class="grid-body no-border">
          <p class="muted">Plantilla: <?php echo htmlspecialchars(isset($detalle['plantilla_nombre']) ? $detalle['plantilla_nombre'] : ''); ?>
            · Publicado por <?php echo htmlspecialchars(isset($detalle['autor']) ? $detalle['autor'] : ''); ?>
            · <?php echo htmlspecialchars(isset($detalle['creado_en']) ? $detalle['creado_en'] : ''); ?></p>
          <p>Total ítems: <strong><?php echo intval(isset($resumen['total']) ? $resumen['total'] : 0); ?></strong></p>
          <table class="table table-hover">
            <thead><tr><th>Concepto</th><th>Detalle</th><th>Cantidad</th></tr></thead>
            <tbody>
            <?php
            $filas=(isset($resumen['filas']) && is_array($resumen['filas'])) ? $resumen['filas'] : array();
            if(count($filas)==0) { ?>
              <tr><td colspan="3" class="muted">Sin filas en este informe.</td></tr>
            <?php } else {
              foreach($filas as $f) { ?>
              <tr>
                <td><?php echo htmlspecialchars(isset($f['concepto']) ? $f['concepto'] : ''); ?></td>
                <td><?php echo htmlspecialchars(isset($f['detalle']) ? $f['detalle'] : ''); ?></td>
                <td><?php echo intval(isset($f['cantidad']) ? $f['cantidad'] : 0); ?></td>
              </tr>
            <?php } } ?>
            </tbody>
          </table>
          <p><a class="btn btn-white" href="informes-presidencia.php">Volver</a></p>
        </div>
      </div>
      <?php } else { ?>

      <?php if($puede_armar) { ?>
      <div class="grid simple">
        <div class="grid-title no-border"><h4>Armar y publicar informe</h4></div>
        <div class="grid-body no-border">
          <?php if(count($plantillas)==0) { ?>
          <div class="alert alert-warning">No hay plantillas de informe cargadas en la base.</div>
          <?php } else { ?>
          <form method="post">
            <div class="form-group">
              <label>Plantilla</label>
              <select name="id_plantilla" class="form-control" required>
                <?php foreach($plantillas as $p) { ?>
                <option value="<?php echo intval($p['id_plantilla']); ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="form-group">
              <label>Título (opcional)</label>
              <input type="text" name="titulo" class="form-control" placeholder="Se usa el nombre de la plantilla si lo dejás vacío">
            </div>
            <div class="row">
              <div class="col-md-3"><div class="form-group"><label>Desde</label><input type="date" name="desde" class="form-control"></div></div>
              <div class="col-md-3"><div class="form-group"><label>Hasta</label><input type="date" name="hasta" class="form-control"></div></div>
              <div class="col-md-3"><div class="form-group"><label>Tipo de cliente</label>
                <select name="tipo_cliente" class="form-control">
                  <option value="">Todos</option>
                  <?php foreach(cliente_tipo_etiquetas() as $k=>$t) { ?>
                  <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($t); ?></option>
                  <?php } ?>
                </select>
              </div></div>
              <div class="col-md-3"><div class="form-group"><label>Localidad</label>
                <select name="localidad" class="form-control">
                  <option value="">Todas</option>
                  <?php foreach($localidades as $l) { ?>
                  <option value="<?php echo htmlspecialchars($l['nombre']); ?>"><?php echo htmlspecialchars($l['nombre']); ?></option>
                  <?php } ?>
                </select>
              </div></div>
            </div>
            <div class="form-group">
              <label>Tipo de actividad (opcional)</label>
              <input type="text" name="tipo_actividad" class="form-control" placeholder="Ej. capacitacion, ronda, reunion">
            </div>
            <button type="submit" name="publicar_informe" value="1" class="btn btn-primary">Publicar para Presidencia</button>
          </form>
          <?php } ?>
        </div>
      </div>
      <?php } ?>

      <div class="grid simple">
        <div class="grid-title no-border"><h4>Informes publicados</h4></div>
        <div class="grid-body no-border">
          <?php if(count($lista)==0) { ?>
          <p class="muted">Todavía no hay informes publicados.</p>
          <?php } else { ?>
          <table class="table table-hover">
            <thead><tr><th>Título</th><th>Plantilla</th><th>Autor</th><th>Fecha</th><th></th></tr></thead>
            <tbody>
            <?php foreach($lista as $inf) { ?>
              <tr>
                <td><?php echo htmlspecialchars($inf['titulo']); ?></td>
                <td><?php echo htmlspecialchars($inf['plantilla_nombre']); ?></td>
                <td><?php echo htmlspecialchars($inf['autor']); ?></td>
                <td><?php echo htmlspecialchars($inf['creado_en']); ?></td>
                <td><a class="btn btn-white btn-xs" href="informes-presidencia.php?id=<?php echo intval($inf['id_informe']); ?>">Ver</a></td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
          <?php } ?>
        </div>
      </div>
      <?php } ?>
    </div>
  </div>
</div>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-ui/jquery-ui-1.10.1.custom.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/plugins/breakpoints.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-unveil/jquery.unveil.min.js" type="text/javascript"></script>
<script src="../assets/plugins/pace/pace.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-scrollbar/jquery.scrollbar.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-block-ui/jqueryblockui.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
</body>
</html>
