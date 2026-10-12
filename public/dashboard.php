<?php
require_once __DIR__.'/_init.php';
crm_proteger_cliente();

$cta=acceso_cliente_cuenta();
$msg='';
$err='';

if(isset($_POST['inscribir_actividad']))
{
	$err=crm_cliente_inscribir_actividad(intval($_POST['inscribir_actividad']));
	if($err==='')
	{
		$msg='Listo, quedaste inscripto.';
	}
}

if(isset($_POST['cerrar_aviso']))
{
	crm_cliente_aviso_marcar_leido(intval($_POST['cerrar_aviso']));
}

$turnos=crm_cliente_mis_turnos();
$proximas=array_slice(crm_cliente_actividades_proximas(), 0, 5);
$recomendadas=crm_cliente_recomendaciones();
$mis_prog=crm_cliente_mis_programas();
$avisos=function_exists('crm_cliente_avisos_pendientes') ? crm_cliente_avisos_pendientes() : array();
$nombre=isset($cta['nombre']) ? $cta['nombre'] : '';
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Panel principal</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="assets/plugins/boostrapv3/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css"/>
<link href="assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/animate.min.css" rel="stylesheet" type="text/css"/>
<link href="assets/plugins/jquery-scrollbar/jquery.scrollbar.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<style>
.crm-cli-card { background:#fff; border:1px solid #e8eef2; border-radius:6px; padding:16px 18px; margin-bottom:14px; }
.crm-cli-card h4 { margin:0 0 8px; font-size:15px; }
.crm-cli-muted { color:#7a8694; font-size:12px; }
.crm-cli-reco { border-left:3px solid #3a7a90; background:#f7fafc; }
.crm-cli-actions { margin-top:10px; }
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
    <div class="clearfix"></div>
    <div class="content">
		<ul class="breadcrumb"><li><p>ESTÁS AQUÍ</p></li><li><a href="#" class="active">Inicio</a></li></ul>
		<div class="page-title">
			<h3>Tu resumen</h3>
			<?php if(isset($cta['tipo']) && $cta['tipo']=='empresa') { ?>
			<p class="crm-cli-muted">Estás gestionando la cuenta de la empresa <?php echo htmlspecialchars($nombre); ?></p>
			<?php } ?>
        </div>
		<?php if($msg!='') { ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php } ?>
		<?php if($err!='') { ?><div class="alert alert-warning"><?php echo htmlspecialchars($err); ?></div><?php } ?>

		<?php foreach($avisos as $av) { ?>
		<div class="alert alert-success" style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px;">
			<div>
				<strong><?php echo htmlspecialchars($av['titulo']); ?></strong>
				<?php if(trim(isset($av['detalle']) ? $av['detalle'] : '')!='') { ?>
				<br><span><?php echo htmlspecialchars($av['detalle']); ?></span>
				<?php } ?>
			</div>
			<form method="post" action="" style="margin:0; flex-shrink:0;">
				<button type="submit" name="cerrar_aviso" value="<?php echo intval($av['id_aviso']); ?>" class="btn btn-xs btn-white">Entendido</button>
			</form>
		</div>
		<?php } ?>

		<?php if(count($recomendadas)>0) { ?>
		<div class="crm-cli-card crm-cli-reco">
			<h4><i class="fa fa-lightbulb-o"></i> Recomendadas para vos</h4>
			<?php foreach(array_slice($recomendadas, 0, 3) as $r) { ?>
			<form method="post" action="" style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px 0; border-top:1px solid #e4ebf0; margin:0;">
				<span>
					<strong><?php echo htmlspecialchars($r['titulo']); ?></strong>
					<span class="crm-cli-muted"> · <?php echo htmlspecialchars(crm_portal_fecha($r['fecha'])); ?> · <?php echo htmlspecialchars($r['area_nombre']); ?></span>
				</span>
				<button type="submit" name="inscribir_actividad" value="<?php echo intval($r['id_evento']); ?>" class="btn btn-primary btn-xs">Inscribirme</button>
			</form>
			<?php } ?>
			<a href="<?php echo url_crm('actividades.php'); ?>" class="crm-cli-muted">Ver todas las actividades</a>
		</div>
		<?php } ?>

		<div class="row">
			<div class="col-md-6">
				<div class="crm-cli-card">
					<h4><i class="fa fa-calendar"></i> Próximas actividades</h4>
					<?php if(count($proximas)==0) { ?>
					<p class="crm-cli-muted">No hay próximas por ahora.</p>
					<?php } foreach($proximas as $p) { ?>
					<p style="margin:0 0 8px;">
						<strong><?php echo htmlspecialchars($p['titulo']); ?></strong><br>
						<span class="crm-cli-muted"><?php echo htmlspecialchars(crm_portal_fecha($p['fecha'])); ?>
						<?php if(!empty($p['mi_estado'])) { ?> · Inscripto<?php } ?></span>
					</p>
					<?php } ?>
					<a href="<?php echo url_crm('actividades.php'); ?>">Ver todas</a>
				</div>
			</div>
			<div class="col-md-6">
				<div class="crm-cli-card">
					<h4><i class="fa fa-clock-o"></i> Tus turnos</h4>
					<?php
					$n_turnos=0;
					foreach($turnos as $t)
					{
						if($n_turnos>=4) { break; }
						$n_turnos++;
					?>
					<p style="margin:0 0 8px;">
						<strong><?php echo htmlspecialchars(function_exists('crm_motivo_texto') ? crm_motivo_texto($t['motivo']) : $t['motivo']); ?></strong>
						· <?php echo htmlspecialchars(isset($t['area_nombre']) ? $t['area_nombre'] : ''); ?><br>
						<span class="crm-cli-muted"><?php echo htmlspecialchars(crm_portal_fecha(isset($t['fecha_turno']) && $t['fecha_turno']!='' ? $t['fecha_turno'] : $t['fecha'])); ?>
						· <?php echo htmlspecialchars(function_exists('crm_estado_texto') ? crm_estado_texto($t['estado']) : $t['estado']); ?></span>
					</p>
					<?php } if($n_turnos==0) { ?>
					<p class="crm-cli-muted">Todavía no tenés turnos.</p>
					<?php } ?>
					<a href="<?php echo url_crm('mis-turnos.php'); ?>">Ver turnos</a>
				</div>
				<div class="crm-cli-card">
					<h4><i class="fa fa-bookmark"></i> Programas</h4>
					<?php if(count($mis_prog)==0) { ?>
					<p class="crm-cli-muted">No estás en un programa todavía.</p>
					<?php } else { foreach($mis_prog as $mp) { ?>
					<p style="margin:0 0 6px;"><?php echo htmlspecialchars($mp['nombre']); ?></p>
					<?php } } ?>
					<a href="<?php echo url_crm('programas.php'); ?>">Ver programas disponibles</a>
				</div>
			</div>
		</div>
    </div>
  </div>
</div>
</div>
<script src="assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="assets/plugins/jquery-ui/jquery-ui-1.10.1.custom.min.js" type="text/javascript"></script>
<script src="assets/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
<script src="assets/plugins/breakpoints.js" type="text/javascript"></script>
<script src="assets/plugins/jquery-unveil/jquery.unveil.min.js" type="text/javascript"></script>
<script src="assets/plugins/jquery-block-ui/jqueryblockui.js" type="text/javascript"></script>
<script src="assets/plugins/jquery-scrollbar/jquery.scrollbar.min.js" type="text/javascript"></script>
<script src="assets/plugins/pace/pace.min.js" type="text/javascript"></script>
<script src="assets/js/core.js?v=20260926" type="text/javascript"></script>
<script src="assets/js/chat.js" type="text/javascript"></script>
<script src="assets/js/demo.js" type="text/javascript"></script>
</body>
</html>
