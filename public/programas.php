<?php
require_once __DIR__.'/_init.php';
crm_proteger_cliente();
$disponibles=crm_cliente_programas_disponibles();
$mios=crm_cliente_mis_programas();
$ids_mios=array();
$mios_por_id=array();
foreach($mios as $m)
{
	$pid=intval($m['id_programa']);
	$ids_mios[$pid]=true;
	$mios_por_id[$pid]=$m;
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
<style>
.crm-prog { margin-bottom:16px; padding:14px 16px; border:1px solid #e8eef2; border-radius:6px; background:#fff; }
.crm-prog.en-curso { border-left:3px solid #5cb85c; }
.crm-prog h4 { margin:0 0 6px; font-size:16px; }
.crm-prog-meta { color:#7a8694; font-size:12px; margin-bottom:8px; }
.crm-prog-items { margin:8px 0 0; padding-left:18px; color:#445; font-size:13px; }
.crm-prog-items li { margin-bottom:4px; }
</style>
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
		<div class="page-title crm-compact-heading"><h3>Programas</h3></div>

		<div class="grid simple">
			<div class="grid-title"><h4>En los que estás</h4></div>
			<div class="grid-body">
				<?php if(count($mios)==0) { ?>
				<p class="muted">Todavía no estás en ningún programa.</p>
				<?php } else { foreach($mios as $m) {
					$items=function_exists('crm_programa_items') ? crm_programa_items($m['id_programa']) : array();
				?>
				<div class="crm-prog en-curso">
					<h4><?php echo htmlspecialchars($m['nombre']); ?> <span class="label label-success">En curso</span></h4>
					<div class="crm-prog-meta">Desde <?php echo htmlspecialchars(crm_portal_fecha($m['fecha_desde'])); ?></div>
					<?php if(trim(isset($m['descripcion']) ? $m['descripcion'] : '')!='') { ?>
					<p style="margin:0 0 6px;"><?php echo nl2br(htmlspecialchars($m['descripcion'])); ?></p>
					<?php } ?>
					<?php if(count($items)>0) { ?>
					<ul class="crm-prog-items">
						<?php foreach($items as $it) {
							$lab=trim($it['titulo']);
							if($lab==='') { $lab=isset($it['tipo_nombre']) ? $it['tipo_nombre'] : 'Actividad'; }
							$extra=array();
							if(!empty($it['area_nombre'])) { $extra[]=$it['area_nombre']; }
							if(!empty($it['eje_nombre'])) { $extra[]=$it['eje_nombre']; }
						?>
						<li>
							<?php echo htmlspecialchars($lab); ?>
							<?php if(count($extra)>0) { ?><span class="muted"> · <?php echo htmlspecialchars(implode(' · ', $extra)); ?></span><?php } ?>
						</li>
						<?php } ?>
					</ul>
					<?php } ?>
				</div>
				<?php } } ?>
			</div>
		</div>

		<div class="grid simple">
			<div class="grid-title"><h4>Todos los programas</h4></div>
			<div class="grid-body">
				<?php if(count($disponibles)==0) { ?>
				<p class="muted">No hay programas publicados.</p>
				<?php } else { foreach($disponibles as $p) {
					$pid=intval($p['id_programa']);
					$en=isset($ids_mios[$pid]);
					$items=function_exists('crm_programa_items') ? crm_programa_items($pid) : array();
					$desde=$en && isset($mios_por_id[$pid]['fecha_desde']) ? $mios_por_id[$pid]['fecha_desde'] : '';
				?>
				<div class="crm-prog<?php echo $en ? ' en-curso' : ''; ?>">
					<h4>
						<?php echo htmlspecialchars($p['nombre']); ?>
						<?php if($en) { ?><span class="label label-success">Ya estás</span><?php } ?>
					</h4>
					<?php if($en && $desde!='') { ?>
					<div class="crm-prog-meta">Inscripto desde <?php echo htmlspecialchars(crm_portal_fecha($desde)); ?></div>
					<?php } ?>
					<?php if(trim(isset($p['descripcion']) ? $p['descripcion'] : '')!='') { ?>
					<p style="margin:0 0 6px;"><?php echo nl2br(htmlspecialchars($p['descripcion'])); ?></p>
					<?php } else { ?>
					<p class="muted" style="margin:0 0 6px;">Sin descripción publicada.</p>
					<?php } ?>
					<?php if(count($items)>0) { ?>
					<p style="margin:0 0 4px; font-size:12px; color:#7a8694;">Incluye:</p>
					<ul class="crm-prog-items">
						<?php foreach($items as $it) {
							$lab=trim($it['titulo']);
							if($lab==='') { $lab=isset($it['tipo_nombre']) ? $it['tipo_nombre'] : 'Actividad'; }
							$extra=array();
							if(!empty($it['area_nombre'])) { $extra[]=$it['area_nombre']; }
							if(!empty($it['eje_nombre'])) { $extra[]=$it['eje_nombre']; }
						?>
						<li>
							<?php echo htmlspecialchars($lab); ?>
							<?php if(count($extra)>0) { ?><span class="muted"> · <?php echo htmlspecialchars(implode(' · ', $extra)); ?></span><?php } ?>
						</li>
						<?php } ?>
					</ul>
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
