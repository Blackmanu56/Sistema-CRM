<?php
require_once __DIR__.'/_init.php';
crm_proteger_cliente();

$msg='';
$err='';
if(isset($_POST['inscribir_actividad']))
{
	$err=crm_cliente_inscribir_actividad(intval($_POST['inscribir_actividad']));
	if($err=='')
	{
		$msg='Listo, quedaste inscripto.';
	}
}

$tab=isset($_GET['tab']) ? $_GET['tab'] : 'proximas';
if(!in_array($tab, array('proximas','asistidas'), true))
{
	$tab='proximas';
}
$proximas=crm_cliente_actividades_proximas();
$asistidas=crm_cliente_actividades_asistidas();
$sugeridas=crm_cliente_recomendaciones();
$tipo=crm_portal_tipo_cliente();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | Actividades</title>
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
		<ul class="breadcrumb"><li><p>ESTÁS AQUÍ</p></li><li><a href="dashboard.php">Inicio</a></li><li><a href="#" class="active">Actividades</a></li></ul>
		<div class="page-title"><h3>Actividades</h3></div>
		<?php if($msg!='') { ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php } ?>
		<?php if($err!='') { ?><div class="alert alert-warning"><?php echo htmlspecialchars($err); ?></div><?php } ?>

		<?php
		$recomendadas=$sugeridas;
		if($tab=='proximas' && count($recomendadas)>0) {
		?>
		<div class="grid simple" style="border-left:4px solid #f0ad4e;">
			<div class="grid-body">
				<p style="margin:0 0 8px;"><i class="fa fa-lightbulb-o" style="color:#f0ad4e;"></i> <strong>Recomendadas para vos</strong></p>
				<?php foreach(array_slice($recomendadas, 0, 3) as $r) { ?>
				<form method="post" action="" style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:6px 0; border-top:1px solid #f1f1f1;">
					<span>
						<strong><?php echo htmlspecialchars($r['titulo']); ?></strong>
						<span class="muted"> · <?php echo htmlspecialchars(crm_portal_fecha($r['fecha'])); ?> · <?php echo htmlspecialchars(crm_portal_tipo_actividad(isset($r['tipo']) ? $r['tipo'] : '')); ?></span>
					</span>
					<button type="submit" name="inscribir_actividad" value="<?php echo intval($r['id_evento']); ?>" class="btn btn-primary btn-small">Inscribirme</button>
				</form>
				<?php } ?>
			</div>
		</div>
		<?php } ?>

		<ul class="nav nav-tabs" style="margin-bottom:16px;">
			<li class="<?php echo $tab=='proximas'?'active':''; ?>"><a href="?tab=proximas"><i class="fa fa-calendar"></i> Agenda de actividades</a></li>
			<li class="<?php echo $tab=='asistidas'?'active':''; ?>"><a href="?tab=asistidas"><i class="fa fa-check-circle"></i> Mi participación</a></li>
		</ul>

		<?php
		$lista=($tab=='asistidas') ? $asistidas : $proximas;
		if(count($lista)==0) {
		?>
		<div class="grid simple"><div class="grid-body">
			<p><?php
			if($tab=='asistidas') { echo 'Todavía no registrás participación en actividades.'; }
			else { echo 'No hay actividades programadas por ahora.'; }
			?></p>
		</div></div>
		<?php } foreach($lista as $ev) {
			$inscripto=!empty($ev['mi_estado']);
			$puede=(!$inscripto && intval(isset($ev['autoinscripcion']) ? $ev['autoinscripcion'] : 0)==1 && crm_evento_publico_ok(isset($ev['publico']) ? $ev['publico'] : 'todos', $tipo) && $tab!='asistidas');
		?>
		<div class="grid simple">
			<div class="grid-title">
				<h4><?php echo htmlspecialchars($ev['titulo']); ?></h4>
			</div>
			<div class="grid-body">
				<p>
					<span class="label label-info"><?php echo htmlspecialchars(crm_portal_tipo_actividad(isset($ev['tipo']) ? $ev['tipo'] : '')); ?></span>
					<strong><?php echo htmlspecialchars(crm_portal_fecha($ev['fecha'])); ?></strong>
					· <?php echo htmlspecialchars(isset($ev['area_nombre']) ? $ev['area_nombre'] : ''); ?>
					<?php if($inscripto) { ?>
					· <span class="label label-success"><?php echo $ev['mi_estado']=='asistio' ? 'Asististe' : 'Inscripto'; ?></span>
					<?php } ?>
				</p>
				<?php if(trim(isset($ev['descripcion']) ? $ev['descripcion'] : '')!='') { ?>
				<p><?php echo nl2br(htmlspecialchars($ev['descripcion'])); ?></p>
				<?php } ?>
				<?php if($puede) { ?>
				<form method="post" action="">
					<button type="submit" name="inscribir_actividad" value="<?php echo intval($ev['id_evento']); ?>" class="btn btn-primary btn-cons">Inscribirme</button>
				</form>
				<?php } ?>
			</div>
		</div>
		<?php } ?>
    </div>
  </div>
</div>
</div>
<script src="assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="assets/js/core.js?v=20260926" type="text/javascript"></script>
</body>
</html>
