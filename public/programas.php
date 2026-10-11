<?php
require_once __DIR__.'/_init.php';
crm_proteger_cliente();
$disponibles=crm_cliente_programas_disponibles();
$mios=crm_cliente_mis_programas();
$ids_mios=array();
foreach($mios as $m)
{
	$ids_mios[intval($m['id_programa'])]=true;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | Programas</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
</head>
<body class="">
<?php crm_header_cliente();?>
<div class="page-container row-fluid">
	<?php crm_menu_cliente();?>
	<div class="clearfix"></div>
  </div>
  </div>
  <div class="page-content">
    <div class="content">
		<ul class="breadcrumb"><li><p>ESTÁS AQUÍ</p></li><li><a href="dashboard.php">Inicio</a></li><li><a href="#" class="active">Programas</a></li></ul>
		<div class="page-title"><h3>Programas</h3></div>

		<div class="grid simple">
			<div class="grid-title"><h4>En los que estás</h4></div>
			<div class="grid-body">
				<?php if(count($mios)==0) { ?>
				<p class="muted">Todavía no estás anotado en un programa.</p>
				<?php } else { foreach($mios as $m) { ?>
				<p><strong><?php echo htmlspecialchars($m['nombre']); ?></strong>
					<span class="muted"> · desde <?php echo htmlspecialchars(crm_portal_fecha($m['fecha_desde'])); ?></span></p>
				<?php } } ?>
			</div>
		</div>

		<div class="grid simple">
			<div class="grid-title"><h4>Disponibles</h4></div>
			<div class="grid-body">
				<?php if(count($disponibles)==0) { ?>
				<p class="muted">No hay programas publicados.</p>
				<?php } else { foreach($disponibles as $p) {
					$en=isset($ids_mios[intval($p['id_programa'])]);
				?>
				<div style="margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid #eee;">
					<strong><?php echo htmlspecialchars($p['nombre']); ?></strong>
					<?php if($en) { ?><span class="label label-success">Ya estás</span><?php } ?>
					<?php if(trim(isset($p['descripcion']) ? $p['descripcion'] : '')!='') { ?>
					<p class="muted" style="margin:6px 0 0;"><?php echo htmlspecialchars($p['descripcion']); ?></p>
					<?php } ?>
				</div>
				<?php } } ?>
				<p class="muted" style="margin-top:10px;">La inscripción a un programa la gestiona el equipo ADEMI.</p>
			</div>
		</div>
    </div>
  </div>
</div>
</div>
<script src="assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="assets/js/core.js?v=20260926" type="text/javascript"></script>
</body>
</html>
