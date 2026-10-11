<?php
require_once __DIR__.'/_init.php';
crm_proteger_cliente();

if(isset($_POST['cancelar_mi_turno']) && isset($_POST['id_atencion']))
{
	$msg=crm_cliente_cancelar_turno($_POST['id_atencion'], isset($_POST['motivo_cancel']) ? $_POST['motivo_cancel'] : '');
	$_SESSION['msgturnos']=($msg==='') ? 'Turno cancelado.' : $msg;
	echo "<script>window.location.href='mis-turnos.php'</script>";
	exit();
}
if(isset($_POST['solicitar_turno']))
{
	$msg=crm_cliente_solicitar_turno(
		isset($_POST['motivo']) ? $_POST['motivo'] : 'consulta',
		isset($_POST['detalle']) ? $_POST['detalle'] : '',
		isset($_POST['fecha_pref']) ? $_POST['fecha_pref'] : ''
	);
	$_SESSION['msgturnos']=($msg==='') ? 'Solicitud enviada. Recepción la revisará y te confirmará el turno.' : $msg;
	echo "<script>window.location.href='mis-turnos.php'</script>";
	exit();
}

$turnos=crm_cliente_mis_turnos();
$msg=isset($_SESSION['msgturnos']) ? $_SESSION['msgturnos'] : '';
$_SESSION['msgturnos']='';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | Mis turnos</title>
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
		<ul class="breadcrumb"><li><p>ESTÁS AQUÍ</p></li><li><a href="dashboard.php">Inicio</a></li><li><a href="#" class="active">Mis turnos</a></li></ul>
		<div class="page-title"><h3>Mis turnos</h3></div>
		<?php if($msg!='') { ?>
		<div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
		<?php } ?>
		<div class="grid simple">
			<div class="grid-title no-border"><h4>Solicitar turno</h4></div>
			<div class="grid-body">
				<p class="muted">El turno no se agenda solo: Recepción recibe tu pedido y lo confirma si hay lugar.</p>
				<form method="post" action="" class="form-inline">
					<div class="form-group" style="margin-right:8px;">
						<label>Motivo</label>
						<select name="motivo" class="form-control">
							<option value="consulta">Consulta</option>
							<option value="documentacion">Documentación</option>
							<option value="seguimiento">Seguimiento</option>
							<option value="tramite">Trámite</option>
							<option value="otro">Otro</option>
						</select>
					</div>
					<div class="form-group" style="margin-right:8px;">
						<label>Fecha preferida (opcional)</label>
						<input type="datetime-local" name="fecha_pref" class="form-control">
					</div>
					<div class="form-group" style="margin-right:8px;">
						<label>Detalle</label>
						<input type="text" name="detalle" class="form-control" placeholder="Breve motivo" style="min-width:220px;">
					</div>
					<button type="submit" name="solicitar_turno" value="1" class="btn btn-primary" style="margin-top:18px;">Enviar solicitud</button>
				</form>
			</div>
		</div>
		<div class="grid simple">
			<div class="grid-body">
				<?php if(count($turnos)==0) { ?>
				<p>No tenés turnos registrados.</p>
				<?php } else { ?>
				<table class="table table-hover">
					<thead>
						<tr>
							<th>Fecha</th>
							<th>Área</th>
							<th>Motivo</th>
							<th>Estado</th>
							<th>Detalle</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach($turnos as $t) {
						$puede_cancel=(isset($t['estado']) && in_array($t['estado'], array('abierta','pendiente'), true));
					?>
						<tr>
							<td><?php echo htmlspecialchars(crm_portal_fecha(isset($t['fecha_turno']) && $t['fecha_turno']!='' ? $t['fecha_turno'] : $t['fecha'])); ?></td>
							<td><?php echo htmlspecialchars(isset($t['area_nombre']) ? $t['area_nombre'] : ''); ?></td>
							<td><?php echo htmlspecialchars(function_exists('crm_motivo_texto') ? crm_motivo_texto($t['motivo']) : $t['motivo']); ?></td>
							<td><?php echo htmlspecialchars(function_exists('crm_estado_texto') ? crm_estado_texto($t['estado']) : $t['estado']); ?></td>
							<td><?php echo htmlspecialchars(isset($t['detalle']) ? $t['detalle'] : ''); ?></td>
							<td>
							<?php if($puede_cancel) { ?>
								<form method="post" action="" style="margin:0; min-width:220px;" onsubmit="return confirm('¿Cancelar este turno?');">
									<input type="hidden" name="id_atencion" value="<?php echo intval($t['id_atencion']); ?>">
									<input type="text" name="motivo_cancel" class="form-control input-sm" placeholder="Motivo de cancelación" required style="margin-bottom:6px;">
									<button type="submit" name="cancelar_mi_turno" value="1" class="btn btn-danger btn-xs">Cancelar</button>
								</form>
							<?php } else { ?>
								<span class="muted">—</span>
							<?php } ?>
							</td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
				<?php } ?>
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
