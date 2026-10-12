<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('general');
if(!acceso_es_coord_general() && !acceso_es_super())
{
	header('Location: '.acceso_destino_login());
	exit();
}
include __DIR__.'/datos-tareas.php';
tareas_iniciar_contexto();
$menu_activo='solicitudes_alta';
$msg='';
if(isset($_SESSION['msg_solicitud']))
{
	$msg=$_SESSION['msg_solicitud'];
	unset($_SESSION['msg_solicitud']);
}

$ver=isset($_GET['ver']) ? $_GET['ver'] : 'pendiente';
if(!in_array($ver, array('pendiente','aceptada','rechazada','todas'), true))
{
	$ver='pendiente';
}
$id_ver=isset($_GET['id']) ? intval($_GET['id']) : 0;

if(isset($_POST['aceptar_solicitud']))
{
	$id=isset($_POST['id_solicitud']) ? intval($_POST['id_solicitud']) : 0;
	$tipo=isset($_POST['tipo_asignado']) ? $_POST['tipo_asignado'] : 'emprendedor';
	$nota=isset($_POST['nota_revision']) ? $_POST['nota_revision'] : '';
	$err=crm_solicitud_aceptar($id, $tipo, $nota);
	$_SESSION['msg_solicitud']=($err==='') ? 'Solicitud aceptada. Cliente creado.' : $err;
	header('Location: solicitudes-alta.php?id='.$id);
	exit();
}
if(isset($_POST['rechazar_solicitud']))
{
	$id=isset($_POST['id_solicitud']) ? intval($_POST['id_solicitud']) : 0;
	$nota=isset($_POST['nota_revision']) ? $_POST['nota_revision'] : '';
	$err=crm_solicitud_rechazar($id, $nota);
	$_SESSION['msg_solicitud']=($err==='') ? 'Solicitud rechazada.' : $err;
	header('Location: solicitudes-alta.php?id='.$id);
	exit();
}
if(isset($_POST['cambiar_tipo_cliente']))
{
	$id_cli=isset($_POST['id_cliente']) ? intval($_POST['id_cliente']) : 0;
	$tipo=isset($_POST['tipo_nuevo']) ? $_POST['tipo_nuevo'] : '';
	$err=cliente_cambiar_tipo($id_cli, $tipo);
	$_SESSION['msg_solicitud']=($err==='') ? 'Tipo de cliente actualizado.' : $err;
	$back=isset($_POST['id_solicitud']) ? intval($_POST['id_solicitud']) : 0;
	header('Location: solicitudes-alta.php?'.($back>0 ? 'id='.$back : 'ver=aceptada'));
	exit();
}

$lista=crm_solicitud_listar($ver==='todas' ? '' : $ver, 150);
$detalle=$id_ver>0 ? crm_solicitud_traer($id_ver) : null;
$n_pend=crm_solicitud_contar_pendientes();
$sectores=crm_solicitud_sectores();
$anios_ops=crm_solicitud_anios_opciones();
$pers_ops=crm_solicitud_personas_opciones();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | Solicitudes de alta</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<?php if(function_exists('crm_ui_css')) { crm_ui_css(); } ?>
<style>
.crm-sol-layout {
	display: flex;
	flex-wrap: wrap;
	gap: 16px;
	align-items: flex-start;
	width: 100%;
	max-width: 100%;
	box-sizing: border-box;
}
.crm-sol-lista,
.crm-sol-detalle {
	min-width: 0;
	box-sizing: border-box;
}
.crm-sol-lista {
	flex: 1 1 <?php echo $detalle ? '340px' : '100%'; ?>;
	max-width: 100%;
}
.crm-sol-detalle {
	flex: 1 1 420px;
	max-width: 100%;
}
.crm-sol-lista .grid.simple,
.crm-sol-detalle .grid.simple {
	margin-bottom: 0;
	width: 100%;
	max-width: 100%;
}
.crm-sol-scroll {
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
	max-width: 100%;
}
.crm-sol-table {
	width: 100%;
	margin: 0;
	table-layout: auto;
}
.crm-sol-table td,
.crm-sol-table th {
	vertical-align: middle !important;
}
.crm-sol-table .crm-sol-mail {
	display: block;
	font-size: 12px;
	color: #64748b;
	word-break: break-all;
}
.crm-sol-detalle .grid-title {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
	flex-wrap: wrap;
}
.crm-sol-detalle .grid-title h4 {
	margin: 0;
}
.crm-sol-detalle .grid-body p {
	margin: 0 0 10px;
	line-height: 1.45;
	word-wrap: break-word;
	overflow-wrap: break-word;
}
.crm-sol-acciones {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	margin-top: 8px;
}
.crm-sol-filtros {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin: 10px 0 16px;
}
</style>
</head>
<body class="">
<?php crm_header_staff(); ?>
<div class="page-container row">
  <?php crm_menu_staff(); ?>
  <div class="clearfix"></div>
</div>
</div>
<div class="page-content">
  <div class="clearfix"></div>
  <div class="content">
    <ul class="breadcrumb">
      <li><p>ESTÁS AQUÍ</p></li>
      <li><a href="dashboard.php">Coordinación general</a></li>
      <li><a href="#" class="active">Solicitudes de alta</a></li>
    </ul>
    <?php include __DIR__.'/menu-seccion.php'; ?>
    <div class="page-title"><h3>Solicitudes de alta web <?php if($n_pend>0) { ?><span class="label label-important"><?php echo intval($n_pend); ?> pendientes</span><?php } ?></h3></div>
    <?php if($msg!='') { ?><div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div><?php } ?>

    <div class="crm-sol-filtros">
      <a class="btn btn-sm <?php echo $ver==='pendiente'?'btn-primary':'btn-white'; ?>" href="solicitudes-alta.php?ver=pendiente">Pendientes</a>
      <a class="btn btn-sm <?php echo $ver==='aceptada'?'btn-primary':'btn-white'; ?>" href="solicitudes-alta.php?ver=aceptada">Aceptadas</a>
      <a class="btn btn-sm <?php echo $ver==='rechazada'?'btn-primary':'btn-white'; ?>" href="solicitudes-alta.php?ver=rechazada">Rechazadas</a>
      <a class="btn btn-sm <?php echo $ver==='todas'?'btn-primary':'btn-white'; ?>" href="solicitudes-alta.php?ver=todas">Todas</a>
    </div>

    <div class="crm-sol-layout">
      <div class="crm-sol-lista">
        <div class="grid simple">
          <div class="grid-title"><h4><?php echo $ver==='pendiente'?'Pendientes':($ver==='aceptada'?'Aceptadas':($ver==='rechazada'?'Rechazadas':'Todas')); ?></h4></div>
          <div class="grid-body no-border">
            <?php if(count($lista)==0) { ?>
            <p class="muted">No hay solicitudes en este filtro.</p>
            <?php } else { ?>
            <div class="crm-sol-scroll">
              <table class="table table-hover crm-sol-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Solicitante</th>
                    <th>Negocio</th>
                    <?php if(!$detalle) { ?>
                    <th>Auto</th>
                    <th>Sugerido</th>
                    <?php } ?>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach($lista as $row) { ?>
                  <tr class="<?php echo $id_ver===intval($row['id_solicitud']) ? 'info' : ''; ?>">
                    <td style="white-space:nowrap;"><?php echo $row['fecha_solicitud']!='' ? date('d/m/Y H:i', strtotime($row['fecha_solicitud'])) : ''; ?></td>
                    <td>
                      <?php echo htmlspecialchars($row['nombre']); ?>
                      <span class="crm-sol-mail"><?php echo htmlspecialchars($row['correo']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($row['nombre_negocio']); ?></td>
                    <?php if(!$detalle) { ?>
                    <td><?php echo htmlspecialchars($row['tipo_autodeclarado']); ?></td>
                    <td><?php echo htmlspecialchars(isset($row['sugerencia_tipo']) ? $row['sugerencia_tipo'] : '—'); ?></td>
                    <?php } ?>
                    <td style="white-space:nowrap;">
                      <?php if($id_ver===intval($row['id_solicitud'])) { ?>
                      <a class="btn btn-white btn-xs" href="solicitudes-alta.php?ver=<?php echo urlencode($ver); ?>">Cerrar</a>
                      <?php } else { ?>
                      <a class="btn btn-primary btn-xs" href="solicitudes-alta.php?ver=<?php echo urlencode($ver); ?>&id=<?php echo intval($row['id_solicitud']); ?>">Ver</a>
                      <?php } ?>
                    </td>
                  </tr>
                <?php } ?>
                </tbody>
              </table>
            </div>
            <?php } ?>
          </div>
        </div>
      </div>

      <?php if($detalle) {
      	$sug=isset($detalle['sugerencia_tipo']) ? $detalle['sugerencia_tipo'] : 'emprendedor';
      	$tipo_def=($detalle['estado']==='pendiente') ? $sug : (isset($detalle['tipo_asignado']) && $detalle['tipo_asignado']!='' ? $detalle['tipo_asignado'] : $sug);
      ?>
      <div class="crm-sol-detalle">
        <div class="grid simple">
          <div class="grid-title">
            <h4>Solicitud #<?php echo intval($detalle['id_solicitud']); ?> · <?php echo htmlspecialchars($detalle['estado']); ?></h4>
            <a href="solicitudes-alta.php?ver=<?php echo urlencode($ver); ?>" class="btn btn-white btn-xs">Cerrar</a>
          </div>
          <div class="grid-body no-border">
            <p><strong><?php echo htmlspecialchars($detalle['nombre']); ?></strong><br>
              <?php echo htmlspecialchars($detalle['correo']); ?> · <?php echo htmlspecialchars($detalle['telefono']); ?></p>
            <p><strong>Negocio:</strong> <?php echo htmlspecialchars($detalle['nombre_negocio']); ?></p>
            <p><strong>Localidad:</strong> <?php echo htmlspecialchars(isset($detalle['localidad_nombre']) ? $detalle['localidad_nombre'] : ''); ?><?php if(!empty($detalle['provincia_nombre'])) { echo ' ('.htmlspecialchars($detalle['provincia_nombre']).')'; } ?></p>
            <p><strong>CUIL/CUIT:</strong> <?php echo htmlspecialchars($detalle['cuil_cuit']); ?><?php if(trim($detalle['dni'])!='') { ?> · DNI <?php echo htmlspecialchars($detalle['dni']); ?><?php } ?></p>
            <p><strong>Autodeclarado:</strong> <?php echo htmlspecialchars($detalle['tipo_autodeclarado']); ?>
              · <strong>Sugerencia:</strong> <?php echo htmlspecialchars($sug); ?></p>
            <p><strong>Sector:</strong> <?php echo htmlspecialchars(crm_solicitud_etiqueta($sectores, $detalle['sector'])); ?><?php if($detalle['sector']==='otro' && trim($detalle['sector_otro'])!='') { echo ' — '.htmlspecialchars($detalle['sector_otro']); } ?></p>
            <p><strong>Antigüedad:</strong> <?php echo htmlspecialchars(crm_solicitud_etiqueta($anios_ops, $detalle['anios_funcionamiento'])); ?><br>
              <strong>Personas:</strong> <?php echo htmlspecialchars(crm_solicitud_etiqueta($pers_ops, $detalle['personas_involucradas'])); ?>
              · <strong>Personal formal:</strong> <?php echo intval($detalle['personal_formal'])===1 ? 'Sí' : 'No'; ?></p>
            <?php if(trim($detalle['producto_servicio'])!='') { ?>
            <p><strong>Productos/servicios:</strong><br><?php echo nl2br(htmlspecialchars($detalle['producto_servicio'])); ?></p>
            <?php } ?>

            <?php if($detalle['estado']==='pendiente') { ?>
            <hr>
            <form method="post" action="">
              <input type="hidden" name="id_solicitud" value="<?php echo intval($detalle['id_solicitud']); ?>">
              <div class="form-group">
                <label>Asignar como *</label>
                <select name="tipo_asignado" class="form-control" required>
                  <option value="emprendedor" <?php if($tipo_def==='emprendedor') echo 'selected'; ?>>Emprendedor — área Emprendedores</option>
                  <option value="empresa" <?php if($tipo_def==='empresa') echo 'selected'; ?>>Empresa — área Empresas</option>
                </select>
              </div>
              <div class="form-group">
                <label>Nota (opcional)</label>
                <textarea name="nota_revision" class="form-control" rows="2"></textarea>
              </div>
              <div class="crm-sol-acciones">
                <button type="submit" name="aceptar_solicitud" value="1" class="btn btn-primary"><i class="fa fa-check"></i> Aceptar y crear cliente</button>
                <button type="submit" name="rechazar_solicitud" value="1" class="btn btn-white" onclick="return confirm('¿Rechazar esta solicitud?');"><i class="fa fa-times"></i> Rechazar</button>
              </div>
            </form>
            <?php } else { ?>
            <p class="muted">Resuelta <?php echo $detalle['fecha_resolucion']!='' ? date('d/m/Y H:i', strtotime($detalle['fecha_resolucion'])) : ''; ?>
              <?php if(!empty($detalle['revisa_nombre'])) { ?> por <?php echo htmlspecialchars(trim($detalle['revisa_nombre'])); ?><?php } ?></p>
            <?php if(!empty($detalle['tipo_asignado'])) { ?><p><strong>Tipo asignado:</strong> <?php echo htmlspecialchars($detalle['tipo_asignado']); ?></p><?php } ?>
            <?php if(trim($detalle['nota_revision'])!='') { ?><p><strong>Nota:</strong> <?php echo nl2br(htmlspecialchars($detalle['nota_revision'])); ?></p><?php } ?>
            <?php if(intval($detalle['id_cliente'])>0) {
            	$cli=cliente_traer(intval($detalle['id_cliente']));
            ?>
            <p><strong>Cliente #<?php echo intval($detalle['id_cliente']); ?>:</strong> <?php echo $cli ? htmlspecialchars($cli['nombre']) : ''; ?>
              (<?php echo $cli ? htmlspecialchars(cliente_tipo_normalizar($cli['tipo_cliente'])) : ''; ?>)</p>
            <?php
            $tipo_act=$cli ? cliente_tipo_normalizar($cli['tipo_cliente']) : '';
            if($cli && in_array($tipo_act, array('emprendedor','empresa'), true)) {
            	$tipo_pasar=($tipo_act==='emprendedor') ? 'empresa' : 'emprendedor';
            	$lab_pasar=($tipo_pasar==='empresa') ? 'Empresa' : 'Emprendedor';
            ?>
            <form method="post" action="" style="margin-top:10px;">
              <input type="hidden" name="id_solicitud" value="<?php echo intval($detalle['id_solicitud']); ?>">
              <input type="hidden" name="id_cliente" value="<?php echo intval($detalle['id_cliente']); ?>">
              <input type="hidden" name="tipo_nuevo" value="<?php echo htmlspecialchars($tipo_pasar); ?>">
              <button type="submit" name="cambiar_tipo_cliente" value="1" class="btn btn-white btn-sm" onclick="return confirm('¿Pasar este cliente a <?php echo htmlspecialchars($lab_pasar); ?>?');">Pasar a <?php echo htmlspecialchars($lab_pasar); ?></button>
            </form>
            <?php } ?>
            <?php } ?>
            <?php } ?>
          </div>
        </div>
      </div>
      <?php } ?>
    </div>
  </div>
</div>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
</body>
</html>
