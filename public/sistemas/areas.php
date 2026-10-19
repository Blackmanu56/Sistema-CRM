<?php
require_once __DIR__.'/../_init.php';
require_once BASE_PATH.'/includes/areas_gestion.php';
require_once BASE_PATH.'/includes/informes-area-config.php';
crm_proteger_staff('sistemas');
acceso_exigir_gestion('sistemas');
if(function_exists('crm_asegurar_area_flags')) { crm_asegurar_area_flags(); }
$campo_puede_atenciones=crm_columna_existe('area','puede_atenciones') ? 'puede_atenciones' : '1 AS puede_atenciones';
$campo_informes_habilitados=crm_columna_existe('area','informes_habilitados') ? 'informes_habilitados' : '1 AS informes_habilitados';
$menu_activo='areas';
$csrf_modulo_token=function_exists('crm_tarea_extra_csrf_token') ? crm_tarea_extra_csrf_token() : '';
if($_SERVER['REQUEST_METHOD']==='POST')
{
	$csrf_post=isset($_POST['csrf_tarea_extra']) && is_string($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : '';
	if(!function_exists('crm_tarea_extra_csrf_valido') || !crm_tarea_extra_csrf_valido($csrf_post))
	{
		http_response_code(403);
		exit('La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.');
	}
}

$confirmacion_pendiente = null;

// -------------------------------------------------------------
// ALTA DE ÁREA
// -------------------------------------------------------------
if(isset($_POST['alta_area']))
{
	$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
	$clave = isset($_POST['clave']) ? trim($_POST['clave']) : '';
	$id_coord = isset($_POST['id_coordinador']) ? intval($_POST['id_coordinador']) : 0;
	$conf_mov = (isset($_POST['confirmar_movimiento']) && $_POST['confirmar_movimiento']==='1');
	$capacidades_alta=crm_area_capacidades_validar($_POST);
	if($capacidades_alta===false)
	{
		$_SESSION['msgsis']='Las capacidades recibidas no son válidas.';
		echo "<script>window.location.href='areas.php'</script>";
		exit();
	}
	$cap_clientes=$capacidades_alta['puede_clientes'];
	$cap_deriv=$capacidades_alta['recibe_derivaciones'];
	$cap_turnos=$capacidades_alta['recibe_turnos'];
	$cap_atenciones=$capacidades_alta['puede_atenciones'];
	$asignable=$capacidades_alta['asignable_empleado'];
	$informes_seleccionados = crm_informes_seleccionados_validar_estricto(isset($_POST['informes_disponibles']) ? $_POST['informes_disponibles'] : array());
	$informes_habilitados = is_array($informes_seleccionados) && count($informes_seleccionados)>0 ? 1 : 0;
	// Legacy: solo como resumen para llamadas viejas que no mandan las capacidades.
	$atiende = ($cap_clientes || $cap_deriv || $cap_turnos) ? 1 : 0;

	if($informes_seleccionados===false)
	{
		$_SESSION['msgsis']='La configuración del módulo de Informes no es válida.';
		echo "<script>window.location.href='areas.php'</script>";
		exit();
	}
	$res = crm_area_crear($nombre, $clave, $id_coord, $conf_mov, $atiende, $asignable, $cap_clientes, $cap_deriv, $cap_turnos, $cap_atenciones, $informes_habilitados);
	if(!$res['ok'] && isset($res['requiere_confirmacion']) && $res['requiere_confirmacion'])
	{
		$confirmacion_pendiente = $res['datos_confirmacion'];
		$confirmacion_pendiente['informes_disponibles'] = $informes_seleccionados;
	}
	else
	{
		if($res['ok'] && (!crm_informes_area_guardar(intval($res['id_area']), $informes_seleccionados) || !crm_informes_area_modulo_guardar(intval($res['id_area']), $informes_habilitados)))
		{
			$_SESSION['msgsis'] = 'Área creada, pero no se pudo guardar la configuración de informes. Revisá que la migración esté aplicada.';
		}
		else
		{
			$_SESSION['msgsis'] = $res['ok'] ? $res['msg'] : $res['error'];
		}
		echo "<script>window.location.href='areas.php'</script>";
		exit();
	}
}

// -------------------------------------------------------------
// ASIGNACIÓN / CAMBIO DE COORDINADOR EN ÁREA EXISTENTE
// -------------------------------------------------------------
if(isset($_POST['asignar_coordinador']))
{
	$id_area = isset($_POST['id_area']) ? intval($_POST['id_area']) : 0;
	$id_coord = isset($_POST['id_coordinador']) ? intval($_POST['id_coordinador']) : 0;
	$conf_mov = (isset($_POST['confirmar_movimiento']) && $_POST['confirmar_movimiento']==='1');
	$conf_reemp = (isset($_POST['confirmar_reemplazo']) && $_POST['confirmar_reemplazo']==='1');

	$res = crm_area_validar_y_asignar_coordinador($id_area, $id_coord, $conf_mov, $conf_reemp);
	if(!$res['ok'] && isset($res['requiere_confirmacion']) && $res['requiere_confirmacion'])
	{
		$confirmacion_pendiente = $res['datos_confirmacion'];
	}
	else
	{
		$_SESSION['msgsis'] = $res['ok'] ? $res['msg'] : $res['error'];
		echo "<script>window.location.href='areas.php'</script>";
		exit();
	}
}

// -------------------------------------------------------------
// QUITAR COORDINADOR DE UN ÁREA EXISTENTE
// -------------------------------------------------------------
if(isset($_POST['quitar_coordinador']))
{
	$id_area = isset($_POST['id_area']) ? intval($_POST['id_area']) : 0;
	$conf = (isset($_POST['confirmar_quitar']) && $_POST['confirmar_quitar']==='1');

	$res = crm_area_quitar_coordinador($id_area, $conf);
	if(!$res['ok'] && isset($res['requiere_confirmacion']) && $res['requiere_confirmacion'])
	{
		$confirmacion_pendiente = $res['datos_confirmacion'];
	}
	else
	{
		$_SESSION['msgsis'] = $res['ok'] ? $res['msg'] : $res['error'];
		echo "<script>window.location.href='areas.php'</script>";
		exit();
	}
}

// -------------------------------------------------------------
// GUARDAR CONFIGURACIÓN DEL ÁREA (la clave y el estado interno no son editables aquí)
// -------------------------------------------------------------
if(isset($_POST['guardar_configuracion_area']) || isset($_POST['editar_area']))
{
	$id_area = isset($_POST['id_area']) ? intval($_POST['id_area']) : 0;
	$modo_configuracion = isset($_POST['modo_configuracion']) && is_string($_POST['modo_configuracion']) ? $_POST['modo_configuracion'] : 'completo';
		$area_actual = $id_area > 0 ? acceso_consulta_una("SELECT id_area, clave, {$campo_informes_habilitados} FROM area WHERE id_area=? LIMIT 1", 'i', array($id_area)) : null;
	$mensaje = '';
	if(!$area_actual)
	{
		$mensaje='El área seleccionada no existe.';
	}
	elseif(!in_array($modo_configuracion, array('capacidades','informes','completo'), true))
	{
		$mensaje='La configuración solicitada no es válida.';
	}
	else
	{
		$capacidades = ($modo_configuracion==='informes') ? null : crm_area_capacidades_validar($_POST);
		$informes_recibidos=isset($_POST['informes_disponibles']) ? $_POST['informes_disponibles'] : array();
		$informes = ($modo_configuracion==='capacidades') ? null : crm_informes_seleccionados_validar_estricto($informes_recibidos);
		$informes_habilitados = ($modo_configuracion==='capacidades') ? (isset($area_actual['informes_habilitados']) ? intval($area_actual['informes_habilitados']) : 1) : (is_array($informes) && count($informes)>0 ? 1 : 0);
		if($capacidades===false)
		{
			$mensaje='Las capacidades recibidas no son válidas.';
		}
		elseif($informes===false)
		{
			$mensaje='Los informes recibidos no son válidos.';
		}
		else
		{
			$transaccion_propia=!crm_transaccion_abierta();
			$transaccion_iniciada=!$transaccion_propia || ($con && mysqli_begin_transaction($con));
			$ok=(bool)$transaccion_iniciada;
			if(!$ok) { $mensaje='No se pudo iniciar el guardado. No se modificó la configuración del área.'; }
			if($ok && $modo_configuracion==='completo')
			{
				$nombre=isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
				$res=crm_area_editar($id_area,$nombre,(string)$area_actual['clave']);
				$ok=!empty($res['ok']);
				if(!$ok) { $mensaje=isset($res['error']) ? $res['error'] : 'No se pudo actualizar el área.'; }
			}
			if($ok && ($modo_configuracion==='capacidades' || $modo_configuracion==='completo'))
			{
				$ok=crm_area_capacidades_guardar($id_area,$capacidades);
				if(!$ok) { $mensaje='No se pudieron guardar las capacidades del área.'; }
			}
			if($ok && ($modo_configuracion==='informes' || $modo_configuracion==='completo'))
			{
				$ok=crm_informes_area_modulo_guardar($id_area,$informes_habilitados);
				if($ok) { $ok=crm_informes_area_guardar($id_area,$informes); }
				if(!$ok) { $mensaje='No se guardaron los cambios de Informes. Revisá que el esquema esté actualizado y volvé a intentar.'; }
			}
			if($ok)
			{
				if($transaccion_propia && !mysqli_commit($con))
				{
					mysqli_rollback($con);
					$ok=false;
					$mensaje='No se pudo confirmar el guardado. Se revirtieron los cambios.';
				}
				else
				{
					$mensaje=$modo_configuracion==='capacidades' ? 'Capacidades actualizadas correctamente.' : ($modo_configuracion==='informes' ? 'Informes actualizados correctamente.' : 'Área, capacidades e Informes actualizados correctamente.');
				}
			}
			elseif($transaccion_propia && $transaccion_iniciada)
			{
				mysqli_rollback($con);
			}
		}
	}
	$_SESSION['msgsis']=$mensaje;
	echo "<script>window.location.href='areas.php'</script>";
	exit();
}

// -------------------------------------------------------------
// TOGGLE ACTIVIDAD DEL ÁREA
// -------------------------------------------------------------
if(isset($_POST['toggle_area']))
{
	$id = isset($_POST['id_area']) ? intval($_POST['id_area']) : 0;
	if($id > 0)
	{
		$area_actual = acceso_consulta_una("SELECT activo FROM area WHERE id_area=? LIMIT 1", 'i', array($id));
		if($area_actual)
		{
			$nuevo_estado = intval($area_actual['activo']) ? 0 : 1;
			$actualizado = acceso_ejecutar("UPDATE area SET activo=? WHERE id_area=?", 'ii', array($nuevo_estado, $id));
			$_SESSION['msgsis'] = $actualizado ? ($nuevo_estado ? 'Área activada.' : 'Área desactivada. Se conservaron sus registros y configuración.') : 'No se pudo actualizar el estado del área.';
		}
		else { $_SESSION['msgsis'] = 'El área seleccionada ya no existe.'; }
	}
	echo "<script>window.location.href='areas.php'</script>";
	exit();
}

if(isset($_POST['toggle_flag_area']))
{
	$id = isset($_POST['id_area']) ? intval($_POST['id_area']) : 0;
	$flag = isset($_POST['flag']) ? trim($_POST['flag']) : '';
	$val = isset($_POST['valor']) ? intval($_POST['valor']) : 0;
	if($id > 0 && in_array($flag, array('puede_clientes', 'recibe_derivaciones', 'recibe_turnos', 'puede_atenciones', 'asignable_empleado'), true))
	{
		if(function_exists('crm_asegurar_area_flags')) { crm_asegurar_area_flags(); }
		acceso_ejecutar("UPDATE area SET {$flag}=? WHERE id_area=?", 'ii', array($val ? 0 : 1, $id));
		// atiende_clientes queda como resumen legacy: se recalcula con las tres capacidades.
		acceso_ejecutar(
			"UPDATE area SET atiende_clientes = IF(puede_clientes=1 OR recibe_derivaciones=1 OR recibe_turnos=1, 1, 0) WHERE id_area=?",
			'i',
			array($id)
		);
		$etq_flag = array(
			'puede_clientes' => 'Gestión de clientes',
			'recibe_derivaciones' => 'Recepción de derivaciones',
			'recibe_turnos' => 'Recepción de turnos',
			'puede_atenciones' => 'Módulo de Atenciones',
			'asignable_empleado' => 'Asignable en el alta'
		);
		$_SESSION['msgsis'] = (isset($etq_flag[$flag]) ? $etq_flag[$flag] : 'Capacidad') . ($val ? ' habilitada.' : ' deshabilitada.');
	}
	echo "<script>window.location.href='areas.php'</script>";
	exit();
}

if(function_exists('crm_asegurar_area_flags')) { crm_asegurar_area_flags(); }
$areas = acceso_consulta("SELECT id_area, clave, nombre, activo, atiende_clientes, puede_clientes, recibe_derivaciones, recibe_turnos, {$campo_puede_atenciones}, {$campo_informes_habilitados}, asignable_empleado, creado_en FROM area ORDER BY nombre");
$configuracion_informes_areas = crm_informes_area_configuraciones_todas();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | Sistemas - Áreas</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<style>
.aviso-movimiento {
  background: #fcf8e3;
  border: 1px solid #faebcc;
  color: #8a6d3b;
  padding: 12px;
  border-radius: 4px;
  margin: 10px 0;
}
.aviso-reemplazo {
  background: #f2dede;
  border: 1px solid #ebccd1;
  color: #a94442;
  padding: 12px;
  border-radius: 4px;
  margin: 10px 0;
}
.aviso-unico {
  background: #fff0f0;
  border: 2px dashed #d9534f;
  color: #c9302c;
  padding: 12px;
  border-radius: 4px;
  margin: 10px 0;
  font-weight: 600;
}
.badge-rol {
  font-size: 11px;
  padding: 3px 6px;
  border-radius: 3px;
  background: #eef0f3;
  color: #333;
}
.crm-areas-page > .breadcrumb {
  margin-bottom: 10px;
}
.crm-areas-page > .page-title.crm-compact-heading {
  margin-top: 8px;
  margin-bottom: 12px;
}
.crm-areas-page > .alert {
  margin-top: 0;
  margin-bottom: 12px;
}
.crm-areas-page > .grid.simple {
  margin-bottom: 16px;
}
.crm-areas-page .crm-areas-table-wrap {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.crm-areas-page .crm-areas-table {
  width: 100%;
  min-width: 1120px;
  margin-bottom: 0;
}
.crm-areas-page .crm-areas-table th:nth-child(n+3),
.crm-areas-page .crm-areas-table td:nth-child(n+3) {
  text-align: center;
  vertical-align: middle;
}
.crm-areas-page .crm-areas-table .crm-areas-actions-heading { text-align:center !important; }
.crm-area-key { display:block; margin-top:3px; color:#748194; font-size:11px; }
.crm-informes-area-options { padding:12px; border:1px solid #dce3eb; border-radius:5px; background:#f8fafc; }
.crm-informes-area-options + .crm-informes-area-options { margin-top:20px; }
.crm-informes-area-options-grid { display:flex; flex-wrap:wrap; gap:6px 18px; }
.crm-informes-area-options-grid .checkbox { margin:0; white-space:nowrap; }
.crm-area-actions { min-width:280px; }
.crm-area-count-button { display:inline-flex; align-items:center; justify-content:center; min-width:42px; padding-left:10px; padding-right:10px; }
.crm-area-action-buttons { display:flex; align-items:center; justify-content:center; flex-wrap:wrap; gap:5px; }
.crm-area-action-buttons > .btn { min-height:30px; white-space:nowrap; }
.crm-area-toggle-form { display:inline-flex; margin:0; }
.crm-area-toggle-form .btn-danger { border-color:#e6b9b9; background:#fff7f7; color:#a94442; }
.crm-area-config-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px 16px; }
.crm-informes-area-options-grid.is-hidden { display:none; }
.crm-area-config-grid .checkbox,
.crm-informes-area-options-grid .checkbox { display:flex; align-items:center; gap:8px; margin:0; padding:7px 9px; border:1px solid #e3e8ef; border-radius:4px; background:#fff; cursor:pointer; }
.crm-area-config-grid .checkbox input[type="checkbox"],
.crm-informes-area-options-grid .checkbox input[type="checkbox"] { display:inline-block !important; position:static !important; width:16px; height:16px; flex:0 0 16px; margin:0 !important; opacity:1 !important; }
.crm-area-config-grid .checkbox.is-checked { border-color:#8db9e8; background:#f1f7fd; }
.crm-area-create-config { padding:12px 0; }
.crm-area-create-config + .crm-area-create-config { border-top:1px solid #e5eaf0; }
.crm-area-create-config-header { margin-bottom:10px; }
.crm-area-create-config-header strong { display:block; margin-bottom:3px; }
.crm-area-create-config-header .muted { margin:0; }
.crm-area-create-config-controls { display:flex; align-items:center; flex-wrap:wrap; gap:8px 12px; }
.crm-area-config-summary { color:#68788a; font-size:13px; }
@media (max-width: 767px) {
  .crm-area-config-grid { grid-template-columns:1fr; }
  .crm-area-create-config-controls { align-items:flex-start; flex-direction:column; gap:6px; }
  .crm-areas-page > .breadcrumb {
    margin-bottom: 8px;
  }
  .crm-areas-page > .page-title.crm-compact-heading {
    margin-top: 6px;
    margin-bottom: 10px;
  }
  .crm-areas-page > .grid.simple {
    margin-bottom: 12px;
  }
}
</style>
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
    <div class="content crm-areas-page">
      <ul class="breadcrumb"><li><p>ESTÁS AQUÍ</p></li><li><a href="#" class="active">Sistemas / Áreas</a></li></ul>
      <?php include('menu-seccion.php'); ?>
      <div class="page-title crm-compact-heading"><h3>Áreas</h3></div>
      <?php if(isset($_SESSION['msgsis']) && $_SESSION['msgsis']!='') { ?>
      <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['msgsis']); $_SESSION['msgsis']=''; ?></div>
      <?php } ?>

      <?php if($confirmacion_pendiente && $confirmacion_pendiente['tipo']==='alta_area') { ?>
      <!-- CONFIRMACIÓN PARA ALTA DE ÁREA -->
      <div class="grid simple">
        <div class="grid-title no-border" style="background:#fcf8e3;"><h4><i class="fa fa-exclamation-triangle"></i> Confirmar asignación de coordinador</h4></div>
        <div class="grid-body no-border">
          <p>Estás creando el área <strong><?php echo htmlspecialchars($confirmacion_pendiente['nombre']); ?></strong> y asignando como coordinador a <strong><?php echo htmlspecialchars($confirmacion_pendiente['emp_nombre']); ?></strong>.</p>
          
          <?php if(!empty($confirmacion_pendiente['es_unico'])) { ?>
          <div class="aviso-unico">
            <i class="fa fa-warning"></i> ¡ATENCIÓN! <strong><?php echo htmlspecialchars($confirmacion_pendiente['emp_nombre']); ?></strong> es el ÚNICO colaborador de <strong><?php echo htmlspecialchars($confirmacion_pendiente['area_origen']); ?></strong>. Si lo movés, esa área quedará completamente vacía (sin colaboradores).
          </div>
          <?php } ?>

          <div class="aviso-movimiento">
            <p><strong><i class="fa fa-info-circle"></i> Cambio de área:</strong> Pasará de <strong><?php echo htmlspecialchars($confirmacion_pendiente['area_origen']); ?></strong> a <strong><?php echo htmlspecialchars($confirmacion_pendiente['nueva_area']); ?></strong>.</p>
            <p>Tiene <strong><?php echo intval($confirmacion_pendiente['tareas_pendientes']); ?> tarea(s) pendiente(s)</strong> asignada(s) en su área anterior. Esas tareas se mantendrán en <strong><?php echo htmlspecialchars($confirmacion_pendiente['area_origen']); ?></strong> sin cambios.</p>
            <?php if(!empty($confirmacion_pendiente['tareas_lista'])) { ?>
            <ul style="margin:5px 0 0 15px; font-size:12px;">
              <?php foreach($confirmacion_pendiente['tareas_lista'] as $tp) { ?>
              <li>Tarea #<?php echo intval($tp['id_tarea']); ?>: <?php echo htmlspecialchars($tp['titulo']); ?> (<?php echo htmlspecialchars($tp['estado']); ?>)</li>
              <?php } ?>
            </ul>
            <?php } ?>
          </div>
          <form method="post">
            <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_modulo_token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="nombre" value="<?php echo htmlspecialchars($confirmacion_pendiente['nombre']); ?>">
            <input type="hidden" name="clave" value="<?php echo htmlspecialchars($confirmacion_pendiente['clave']); ?>">
            <input type="hidden" name="id_coordinador" value="<?php echo intval($confirmacion_pendiente['id_coordinador']); ?>">
            <input type="hidden" name="puede_clientes" value="<?php echo intval(isset($confirmacion_pendiente['puede_clientes']) ? $confirmacion_pendiente['puede_clientes'] : 0); ?>">
            <input type="hidden" name="recibe_derivaciones" value="<?php echo intval(isset($confirmacion_pendiente['recibe_derivaciones']) ? $confirmacion_pendiente['recibe_derivaciones'] : 0); ?>">
            <input type="hidden" name="recibe_turnos" value="<?php echo intval(isset($confirmacion_pendiente['recibe_turnos']) ? $confirmacion_pendiente['recibe_turnos'] : 0); ?>">
            <input type="hidden" name="puede_atenciones" value="<?php echo intval(isset($confirmacion_pendiente['puede_atenciones']) ? $confirmacion_pendiente['puede_atenciones'] : 0); ?>">
            <input type="hidden" name="asignable_empleado" value="<?php echo intval(isset($confirmacion_pendiente['asignable_empleado']) ? $confirmacion_pendiente['asignable_empleado'] : 1); ?>">
            <?php foreach(crm_informes_seleccionados_validar(isset($confirmacion_pendiente['informes_disponibles']) ? $confirmacion_pendiente['informes_disponibles'] : array()) as $codigo_informe_confirmar) { ?>
            <input type="hidden" name="informes_disponibles[]" value="<?php echo htmlspecialchars($codigo_informe_confirmar, ENT_QUOTES, 'UTF-8'); ?>">
            <?php } ?>
            <input type="hidden" name="confirmar_movimiento" value="1">
            <button type="submit" name="alta_area" value="1" class="btn btn-primary"><i class="fa fa-check"></i> Confirmar y crear área</button>
            <a href="areas.php" class="btn btn-white">Cancelar</a>
          </form>
        </div>
      </div>
      <?php } ?>

      <?php if($confirmacion_pendiente && $confirmacion_pendiente['tipo']==='asignar_coord') { ?>
      <!-- CONFIRMACIÓN PARA ASIGNAR EN ÁREA EXISTENTE -->
      <div class="grid simple">
        <div class="grid-title no-border" style="background:#fcf8e3;"><h4><i class="fa fa-exclamation-triangle"></i> Confirmar cambio de coordinador en <?php echo htmlspecialchars($confirmacion_pendiente['area_nombre']); ?></h4></div>
        <div class="grid-body no-border">
          <p>Vas a asignar a <strong><?php echo htmlspecialchars($confirmacion_pendiente['emp_nombre']); ?></strong> como coordinador de <strong><?php echo htmlspecialchars($confirmacion_pendiente['area_nombre']); ?></strong>.</p>
          
          <?php if(!empty($confirmacion_pendiente['es_unico'])) { ?>
          <div class="aviso-unico">
            <i class="fa fa-warning"></i> ¡ATENCIÓN! <strong><?php echo htmlspecialchars($confirmacion_pendiente['emp_nombre']); ?></strong> es el ÚNICO colaborador de <strong><?php echo htmlspecialchars($confirmacion_pendiente['area_origen']); ?></strong>. Si lo movés, esa área quedará completamente vacía (sin colaboradores).
          </div>
          <?php } ?>

          <?php if($confirmacion_pendiente['reemplaza']) { ?>
          <div class="aviso-reemplazo">
            <p><strong><i class="fa fa-user-times"></i> Reemplazo de coordinador:</strong> El área ya tiene como coordinador activo a <strong><?php echo htmlspecialchars($confirmacion_pendiente['coord_anterior']); ?></strong>.</p>
            <p>Al confirmar, <strong><?php echo htmlspecialchars($confirmacion_pendiente['coord_anterior']); ?></strong> pasará a ser colaborador común en esa área, sin borrarlo del sistema.</p>
          </div>
          <?php } ?>

          <?php if($confirmacion_pendiente['cambia_area']) { ?>
          <div class="aviso-movimiento">
            <p><strong><i class="fa fa-exchange"></i> Cambio de área:</strong> Pasará de <strong><?php echo htmlspecialchars($confirmacion_pendiente['area_origen']); ?></strong> a <strong><?php echo htmlspecialchars($confirmacion_pendiente['area_nombre']); ?></strong>.</p>
            <p>Tiene <strong><?php echo intval($confirmacion_pendiente['tareas_pendientes']); ?> tarea(s) pendiente(s)</strong> en su área anterior. Esas tareas se mantendrán en <strong><?php echo htmlspecialchars($confirmacion_pendiente['area_origen']); ?></strong> sin modificaciones.</p>
            <?php if(!empty($confirmacion_pendiente['tareas_lista'])) { ?>
            <ul style="margin:5px 0 0 15px; font-size:12px;">
              <?php foreach($confirmacion_pendiente['tareas_lista'] as $tp) { ?>
              <li>Tarea #<?php echo intval($tp['id_tarea']); ?>: <?php echo htmlspecialchars($tp['titulo']); ?> (<?php echo htmlspecialchars($tp['estado']); ?>)</li>
              <?php } ?>
            </ul>
            <?php } ?>
          </div>
          <?php } ?>

          <form method="post">
            <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_modulo_token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id_area" value="<?php echo intval($confirmacion_pendiente['id_area']); ?>">
            <input type="hidden" name="id_coordinador" value="<?php echo intval($confirmacion_pendiente['id_coordinador']); ?>">
            <input type="hidden" name="confirmar_movimiento" value="1">
            <input type="hidden" name="confirmar_reemplazo" value="1">
            <button type="submit" name="asignar_coordinador" value="1" class="btn btn-primary"><i class="fa fa-check"></i> Confirmar asignación</button>
            <a href="areas.php" class="btn btn-white">Cancelar</a>
          </form>
        </div>
      </div>
      <?php } ?>

      <?php if($confirmacion_pendiente && $confirmacion_pendiente['tipo']==='quitar_coord') { ?>
      <!-- CONFIRMACIÓN PARA QUITAR COORDINADOR -->
      <div class="grid simple">
        <div class="grid-title no-border" style="background:#f2dede;"><h4><i class="fa fa-user-times"></i> Confirmar quitar coordinador de <?php echo htmlspecialchars($confirmacion_pendiente['area_nombre']); ?></h4></div>
        <div class="grid-body no-border">
          <p>¿Estás seguro de que querés quitar a <strong><?php echo htmlspecialchars($confirmacion_pendiente['coord_nombre']); ?></strong> como coordinador de <strong><?php echo htmlspecialchars($confirmacion_pendiente['area_nombre']); ?></strong>?</p>
          <div class="aviso-reemplazo">
            <p><strong><i class="fa fa-info-circle"></i> Resultado:</strong> El área quedará sin coordinador asignado. <strong><?php echo htmlspecialchars($confirmacion_pendiente['coord_nombre']); ?></strong> quedará como colaborador común en <strong><?php echo htmlspecialchars($confirmacion_pendiente['area_nombre']); ?></strong>, sin borrarse del sistema.</p>
          </div>
          <form method="post">
            <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_modulo_token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id_area" value="<?php echo intval($confirmacion_pendiente['id_area']); ?>">
            <input type="hidden" name="confirmar_quitar" value="1">
            <button type="submit" name="quitar_coordinador" value="1" class="btn btn-danger"><i class="fa fa-trash"></i> Confirmar y quitar coordinador</button>
            <a href="areas.php" class="btn btn-white">Cancelar</a>
          </form>
        </div>
      </div>
      <?php } ?>

      <div class="grid simple">
        <div class="grid-title no-border"><h4>Nueva área</h4></div>
        <div class="grid-body no-border">
          <form method="post" id="form_alta_area">
            <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_modulo_token, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="row">
              <div class="col-sm-6 form-group">
                <label>Nombre</label>
                <input type="text" name="nombre" class="form-control" required placeholder="Ej. Coordinación de turismo">
              </div>
              <div class="col-sm-6 form-group">
                <label>Clave (carpeta / código corto)</label>
                <input type="text" name="clave" class="form-control" placeholder="Ej. turismo (opcional, se genera sola)">
                <p class="muted">Solo minúsculas, números y guión bajo.</p>
              </div>
            </div>
            <div class="form-group crm-area-config-actions">
              <label><strong>Configuración del área</strong></label>
              <div class="crm-area-create-config">
                <div class="crm-area-create-config-header">
                  <strong>Capacidades</strong>
                  <p class="muted">Elegí qué funciones estarán disponibles en esta área.</p>
                </div>
                <div class="crm-area-create-config-controls">
                  <button type="button" class="btn btn-white btn-sm" data-toggle="modal" data-target="#modalCapacidadesAlta"><i class="fa fa-sliders"></i> Configurar capacidades</button>
                  <span id="resumen_capacidades_alta" class="crm-area-config-summary">5 capacidades seleccionadas</span>
                </div>
              </div>
              <div class="crm-area-create-config">
                <div class="crm-area-create-config-header">
                  <strong>Informes</strong>
                  <p class="muted">Elegí qué informes estarán disponibles, sin ampliar permisos de consulta.</p>
                </div>
                <div class="crm-area-create-config-controls">
                  <button type="button" class="btn btn-white btn-sm" data-toggle="modal" data-target="#modalInformesAlta"><i class="fa fa-bar-chart-o"></i> Configurar informes</button>
                  <span id="resumen_informes_alta" class="crm-area-config-summary">0 informes seleccionados</span>
                </div>
              </div>
            </div>
            <div class="form-group" style="margin-top:15px;">
              <label><strong>Coordinador de área (opcional)</strong></label>
              <p class="muted">Buscá un colaborador como se buscan los clientes. Dejá vacío para asignar después.</p>
              <?php echo crm_html_picker_empleado(array(
              	'input_id'=>'buscar_coord_alta',
              	'hidden_id'=>'id_coord_alta',
              	'hidden_name'=>'id_coordinador',
              	'valor_id'=>0,
              	'valor_texto'=>'',
              	'placeholder'=>'Nombre, DNI, usuario o correo…'
              )); ?>
              <div id="aviso_coord_alta" style="display:none; margin-top:8px;"></div>
            </div>

            <button type="submit" name="alta_area" id="btn_alta_area" value="1" class="btn btn-primary" style="margin-top:10px;">Crear área</button>
          </form>
          <div class="modal fade" id="modalCapacidadesAlta" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog"><div class="modal-content">
              <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button><h4 class="modal-title">Capacidades del área</h4></div>
              <div class="modal-body">
                <p class="muted">Elegí qué operaciones estarán disponibles para esta área.</p>
                <div class="crm-area-config-grid">
                  <label class="checkbox"><input form="form_alta_area" type="checkbox" name="puede_clientes" value="1" checked> Gestión de clientes</label>
                  <label class="checkbox"><input form="form_alta_area" type="checkbox" name="recibe_derivaciones" value="1" checked> Recibir derivaciones</label>
                  <label class="checkbox"><input form="form_alta_area" type="checkbox" name="recibe_turnos" value="1" checked> Recibir turnos</label>
                  <label class="checkbox"><input form="form_alta_area" type="checkbox" name="puede_atenciones" value="1" checked> Atenciones</label>
                  <label class="checkbox"><input form="form_alta_area" type="checkbox" name="asignable_empleado" value="1" checked> Asignar colaboradores</label>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default crm-area-config-cancel">Cancelar</button>
                <button type="button" class="btn btn-primary crm-area-config-accept">Aceptar</button>
              </div>
            </div></div>
          </div>
          <div class="modal fade" id="modalInformesAlta" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog"><div class="modal-content">
              <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button><h4 class="modal-title">Informes disponibles</h4></div>
              <div class="modal-body">
                <p class="muted">El módulo queda habilitado al seleccionar uno o más informes. Si no seleccionás ninguno, queda deshabilitado. Esto no concede permisos de consulta.</p>
                <div class="crm-area-config-grid">
                  <?php foreach(crm_informes_codigos_configurables() as $codigo_informe=>$nombre_informe) { ?>
                  <label class="checkbox"><input form="form_alta_area" type="checkbox" name="informes_disponibles[]" value="<?php echo htmlspecialchars($codigo_informe, ENT_QUOTES, 'UTF-8'); ?>"> <?php echo htmlspecialchars($nombre_informe, ENT_QUOTES, 'UTF-8'); ?></label>
                  <?php } ?>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default crm-area-config-cancel">Cancelar</button>
                <button type="button" class="btn btn-primary crm-area-config-accept">Aceptar</button>
              </div>
            </div></div>
          </div>
        </div>
      </div>

      <div class="grid simple">
        <div class="grid-title no-border"><h4>Áreas existentes</h4></div>
        <div class="grid-body no-border">
          <div class="crm-areas-table-wrap">
          <table class="table table-hover crm-areas-table">
            <thead>
              <tr>
                <th>Área</th>
                <th>Coordinador</th>
                <th>Capacidades</th>
                <th>Informes</th>
                <th>Estado</th>
                <th class="crm-areas-actions-heading">Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($areas as $a) { 
              $coord = crm_area_coordinador_activo($a['id_area']);
              $cli_a = isset($a['puede_clientes']) ? intval($a['puede_clientes']) : 0;
              $der_a = isset($a['recibe_derivaciones']) ? intval($a['recibe_derivaciones']) : 0;
              $tur_a = isset($a['recibe_turnos']) ? intval($a['recibe_turnos']) : 0;
              $aten_a = isset($a['puede_atenciones']) ? intval($a['puede_atenciones']) : 1;
              $asig_a = isset($a['asignable_empleado']) ? intval($a['asignable_empleado']) : 1;
              $informes_a = isset($configuracion_informes_areas[intval($a['id_area'])]) ? $configuracion_informes_areas[intval($a['id_area'])] : array_fill_keys(array_keys(crm_informes_codigos_configurables()), false);
              $informes_modulo_habilitado_a = isset($a['informes_habilitados']) ? intval($a['informes_habilitados'])===1 : true;
              $capacidades_a_json = array();
              if($cli_a) { $capacidades_a_json[]='puede_clientes'; }
              if($der_a) { $capacidades_a_json[]='recibe_derivaciones'; }
              if($tur_a) { $capacidades_a_json[]='recibe_turnos'; }
              if($aten_a) { $capacidades_a_json[]='puede_atenciones'; }
              if($asig_a) { $capacidades_a_json[]='asignable_empleado'; }
              $cantidad_capacidades_a=count($capacidades_a_json);
              $informes_a_seleccionados=array_keys(array_filter($informes_a));
              $cantidad_informes_a=$informes_modulo_habilitado_a ? count($informes_a_seleccionados) : 0;
            ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($a['nombre']); ?></strong><small class="crm-area-key"><?php echo htmlspecialchars($a['clave']); ?></small></td>
                <td>
                  <?php if($coord) { ?>
                    <i class="fa fa-user" style="color:#0aa699; margin-right:4px;"></i>
                    <strong><?php echo htmlspecialchars($coord['nombre_completo']); ?></strong>
                    <?php if(isset($coord['estado_laboral']) && $coord['estado_laboral']!=='activo') { ?>
                      <span class="label" title="Situación laboral"><?php echo htmlspecialchars($coord['estado_laboral']); ?></span>
                    <?php } ?>
                  <?php } else { ?>
                    <span class="text-muted"><i class="fa fa-user-times"></i> Sin coordinador</span>
                  <?php } ?>
                </td>
                <td>
                  <button type="button" class="btn btn-white btn-xs crm-area-count-button js-area-config" aria-label="Editar capacidades de <?php echo htmlspecialchars($a['nombre'], ENT_QUOTES, 'UTF-8'); ?>" title="Editar capacidades"
                          data-config-focus="capacidades"
                          data-area-id="<?php echo intval($a['id_area']); ?>"
                          data-area-name="<?php echo htmlspecialchars($a['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                          data-area-reports="<?php echo htmlspecialchars(json_encode($informes_a_seleccionados), ENT_QUOTES, 'UTF-8'); ?>"
                          data-area-capabilities="<?php echo htmlspecialchars(json_encode($capacidades_a_json), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $cantidad_capacidades_a; ?></button>
                </td>
                <td>
                  <button type="button" class="btn btn-white btn-xs crm-area-count-button js-area-config" aria-label="Editar informes de <?php echo htmlspecialchars($a['nombre'], ENT_QUOTES, 'UTF-8'); ?>" title="Editar informes"
                          data-config-focus="informes"
                          data-area-id="<?php echo intval($a['id_area']); ?>"
                          data-area-name="<?php echo htmlspecialchars($a['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                          data-area-reports="<?php echo htmlspecialchars(json_encode($informes_a_seleccionados), ENT_QUOTES, 'UTF-8'); ?>"
                          data-area-capabilities="<?php echo htmlspecialchars(json_encode($capacidades_a_json), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $cantidad_informes_a; ?></button>
                </td>
                <td>
                  <?php if(intval($a['activo'])) { ?>
                    <span class="label label-success">Activa</span>
                  <?php } else { ?>
                    <span class="label label-important">Inactiva</span>
                  <?php } ?>
                </td>
                <td class="crm-area-actions">
                  <div class="crm-area-action-buttons">
                  <button type="button" class="btn btn-white btn-xs crm-area-action-edit"
                          data-area-id="<?php echo intval($a['id_area']); ?>"
                          data-area-name="<?php echo htmlspecialchars($a['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                          data-area-reports="<?php echo htmlspecialchars(json_encode($informes_a_seleccionados), ENT_QUOTES, 'UTF-8'); ?>"
                          data-area-capabilities="<?php echo htmlspecialchars(json_encode($capacidades_a_json), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa fa-pencil" aria-hidden="true"></i> Editar
                  </button>
                  <button type="button" class="btn btn-white btn-xs" onclick="abrirModalAsignar(<?php echo intval($a['id_area']); ?>, <?php echo htmlspecialchars(json_encode($a['nombre']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo $coord ? intval($coord['id_empleado']) : 0; ?>)">
                    <i class="fa fa-user" aria-hidden="true"></i> Asignar
                  </button>
                  <form method="post" class="crm-area-toggle-form" onsubmit="return confirm('¿<?php echo intval($a['activo']) ? 'Desactivar' : 'Activar'; ?> esta área?');">
                    <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_modulo_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="id_area" value="<?php echo intval($a['id_area']); ?>">
                    <button type="submit" name="toggle_area" value="1" class="btn <?php echo intval($a['activo']) ? 'btn-danger' : 'btn-success'; ?> btn-xs"><i class="fa fa-power-off" aria-hidden="true"></i> <?php echo intval($a['activo']) ? 'Desactivar' : 'Activar'; ?></button>
                  </form>
                  </div>
                </td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODAL EDITAR ÁREA -->
<div class="modal fade" id="modalEditarArea" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title"><span id="modal_edit_action_title">Editar área:</span> <span id="modal_edit_area_titulo" style="font-weight:bold;"></span></h4>
      </div>
      <form method="post" id="form_modal_editar_area">
        <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_modulo_token, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="modo_configuracion" id="modal_edit_modo_configuracion" value="completo">
        <div class="modal-body">
          <input type="hidden" name="id_area" id="modal_edit_id_area" value="0">
          <div class="form-group" id="modal_edit_nombre_grupo">
            <label>Nombre del área</label>
            <input type="text" name="nombre" id="modal_edit_nombre" class="form-control" required placeholder="Ej. Comercio Exterior">
          </div>

          <p class="text-muted" id="modal_edit_clave_nota">La clave interna se mantiene protegida para conservar las rutas y relaciones del área.</p>

          <div class="form-group crm-informes-area-options" id="modal_edit_capacidades_grupo">
            <label><strong>Configuración de capacidades</strong></label>
            <p class="muted">Estas opciones habilitan funcionalidades del área, sin cambiar los roles de sus empleados.</p>
            <div class="crm-area-config-grid">
              <label class="checkbox"><input type="checkbox" name="puede_clientes" id="edit_puede_clientes" value="1"> Gestión de clientes</label>
              <label class="checkbox"><input type="checkbox" name="recibe_derivaciones" id="edit_recibe_derivaciones" value="1"> Recibir derivaciones</label>
              <label class="checkbox"><input type="checkbox" name="recibe_turnos" id="edit_recibe_turnos" value="1"> Recibir turnos</label>
              <label class="checkbox"><input type="checkbox" name="puede_atenciones" id="edit_puede_atenciones" value="1"> Atenciones</label>
              <label class="checkbox"><input type="checkbox" name="asignable_empleado" id="edit_asignable_empleado" value="1"> Asignar colaboradores</label>
            </div>
          </div>

          <div class="form-group crm-informes-area-options" id="modal_edit_informes_grupo">
            <label><strong>Configuración de informes</strong></label>
            <p class="muted">El módulo queda habilitado al seleccionar uno o más informes. Sin selecciones, queda deshabilitado. Esto no concede permisos de consulta ni cambia el alcance de los datos.</p>
            <div class="crm-informes-area-options-grid" id="edit_informes_options">
              <?php foreach(crm_informes_codigos_configurables() as $codigo_informe=>$nombre_informe) { ?>
              <label class="checkbox"><input type="checkbox" name="informes_disponibles[]" value="<?php echo htmlspecialchars($codigo_informe, ENT_QUOTES, 'UTF-8'); ?>"> <?php echo htmlspecialchars($nombre_informe, ENT_QUOTES, 'UTF-8'); ?></label>
              <?php } ?>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
          <button type="submit" name="guardar_configuracion_area" value="1" class="btn btn-primary">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODALES DE CONFIGURACIÓN PARA EL ALTA -->
<!-- (Los controles usan el atributo form para enviar sus valores con el formulario de alta.) -->

<!-- MODAL ASIGNAR / CAMBIAR COORDINADOR -->
<div class="modal fade" id="modalAsignarCoord" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title">Asignar coordinador a <span id="modal_area_nombre" style="font-weight:bold;"></span></h4>
      </div>
      <form method="post" id="form_modal_asignar">
        <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_modulo_token, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="modal-body">
          <input type="hidden" name="id_area" id="modal_id_area" value="0">
          
          <div class="form-group">
            <label>Seleccionar colaborador existente</label>
            <?php echo crm_html_picker_empleado(array(
            	'input_id'=>'buscar_coord_modal',
            	'hidden_id'=>'modal_id_coordinador',
            	'hidden_name'=>'id_coordinador',
            	'valor_id'=>0,
            	'valor_texto'=>'',
            	'placeholder'=>'Nombre, DNI, usuario o correo…'
            )); ?>
            <div id="modal_aviso" style="display:none; margin-top:10px;"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
          <button type="submit" name="quitar_coordinador" id="btn_modal_quitar_coordinador" value="1" class="btn btn-danger" style="display:none;" onclick="return confirm('¿Quitar el coordinador actual de esta área?');"><i class="fa fa-times" aria-hidden="true"></i> Quitar coordinador</button>
          <button type="submit" name="asignar_coordinador" id="btn_modal_confirmar" value="1" class="btn btn-primary">Asignar coordinador</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../assets/plugins/jquery-1.8.3.min.js"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js"></script>
<script src="../assets/js/core.js?v=20260926"></script>
<script>
(function(){
  var avAlta = document.getElementById('aviso_coord_alta');
  var btnAlta = document.getElementById('btn_alta_area');
  var avMod = document.getElementById('modal_aviso');
  var btnMod = document.getElementById('btn_modal_confirmar');
  var hidArea = document.getElementById('modal_id_area');

  function avisoCoord(emp, destino) {
    var areaNom = emp.area_nombre || emp.area || '';
    if(emp.rol === 'coordinador') {
      return { bloqueo:true, html:'<i class="fa fa-ban"></i> <strong>Bloqueo:</strong> Es coordinador de <strong>'+areaNom+'</strong>; primero reasigná esa área.' };
    }
    if(destino === 'alta' || (hidArea && String(emp.area||'') !== '')) {
      return { bloqueo:false, html:'<i class="fa fa-info-circle"></i> <strong>Aviso:</strong> Pasará desde <strong>'+areaNom+'</strong>. El cambio se verá cuando vuelva a iniciar sesión.' };
    }
    return { bloqueo:false, html:'' };
  }

  window.crmEmpleadoElegido = function(emp, hiddenId) {
    if(hiddenId === 'id_coord_alta' && avAlta) {
      if(!emp || !emp.id) { avAlta.style.display='none'; if(btnAlta) btnAlta.disabled=false; return; }
      var a = avisoCoord(emp, 'alta');
      if(a.html) {
        avAlta.className = a.bloqueo ? 'aviso-reemplazo' : 'aviso-movimiento';
        avAlta.innerHTML = a.html;
        avAlta.style.display = 'block';
      } else { avAlta.style.display='none'; }
      if(btnAlta) btnAlta.disabled = !!a.bloqueo;
    }
    if(hiddenId === 'modal_id_coordinador' && avMod) {
      if(!emp || !emp.id) { avMod.style.display='none'; if(btnMod) btnMod.disabled=false; return; }
      var b = avisoCoord(emp, 'modal');
      if(emp.rol === 'coordinador') {
        avMod.className = 'aviso-reemplazo';
        avMod.innerHTML = b.html;
        avMod.style.display = 'block';
        if(btnMod) btnMod.disabled = true;
      } else {
        avMod.className = 'aviso-movimiento';
        avMod.innerHTML = b.html || '';
        avMod.style.display = b.html ? 'block' : 'none';
        if(btnMod) btnMod.disabled = false;
      }
    }
  };

  var formModal = document.getElementById('form_modal_asignar');
  if(formModal) {
    formModal.onsubmit = function() {
      var id = parseInt(document.getElementById('modal_id_coordinador').value || '0', 10);
      if(!id) { window.alert('Elegí un colaborador de la búsqueda.'); return false; }
      return true;
    };
  }

  window.abrirModalAsignar = function(idArea, nombreArea, idCoordActual) {
    document.getElementById('modal_id_area').value = idArea;
    document.getElementById('modal_area_nombre').textContent = nombreArea;
    document.getElementById('modal_id_coordinador').value = idCoordActual ? String(idCoordActual) : '0';
    document.getElementById('buscar_coord_modal').value = '';
    document.getElementById('modal_aviso').style.display='none';
    document.getElementById('btn_modal_confirmar').disabled=false;
    var btnQuitarCoord=document.getElementById('btn_modal_quitar_coordinador');
    if(btnQuitarCoord) btnQuitarCoord.style.display=idCoordActual ? 'inline-block' : 'none';
    $('#modalAsignarCoord').modal('show');
  };

  window.abrirModalEditar = function(idArea, nombreArea, informesSeleccionados, capacidadesSeleccionadas, focusSection) {
    document.getElementById('modal_edit_id_area').value = idArea;
    document.getElementById('modal_edit_area_titulo').textContent = nombreArea;
    document.getElementById('modal_edit_nombre').value = nombreArea;
    document.getElementById('modal_edit_modo_configuracion').value = 'completo';
    document.getElementById('modal_edit_action_title').textContent = 'Editar área:';
    informesSeleccionados = informesSeleccionados || [];
    capacidadesSeleccionadas = capacidadesSeleccionadas || [];
    var checks = document.querySelectorAll('#form_modal_editar_area input[name="informes_disponibles[]"]');
    Array.prototype.forEach.call(checks, function(check) {
      check.checked = informesSeleccionados.indexOf(check.value) !== -1;
    });
    var checksCapacidades = document.querySelectorAll('#form_modal_editar_area input[name="puede_clientes"], #form_modal_editar_area input[name="recibe_derivaciones"], #form_modal_editar_area input[name="recibe_turnos"], #form_modal_editar_area input[name="puede_atenciones"], #form_modal_editar_area input[name="asignable_empleado"]');
    Array.prototype.forEach.call(checksCapacidades, function(check) {
      check.checked = capacidadesSeleccionadas.indexOf(check.name) !== -1;
    });
    var modal = document.getElementById('modalEditarArea');
    function enfocarSeccion() {
      if(!focusSection || !modal) return;
      var target = document.getElementById(focusSection === 'informes' ? 'modal_edit_informes_grupo' : 'modal_edit_capacidades_grupo');
      var body = modal.querySelector('.modal-body');
      if(target && body) {
        var targetTop = target.getBoundingClientRect().top;
        var bodyTop = body.getBoundingClientRect().top;
        body.scrollTop = Math.max(0, body.scrollTop + targetTop - bodyTop - 12);
      }
    }
    if(window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.modal === 'function') {
      if(focusSection) window.jQuery(modal).one('shown.bs.modal', enfocarSeccion);
      window.jQuery(modal).modal('show');
    } else if(modal) {
      if(!modal.getAttribute('data-fallback-close-bound')) {
        Array.prototype.forEach.call(modal.querySelectorAll('[data-dismiss="modal"]'), function(closeButton) {
          closeButton.addEventListener('click', function(){
            modal.style.display = 'none';
            modal.classList.remove('in');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
          });
        });
        modal.addEventListener('click', function(event){
          if(event.target === modal) {
            modal.style.display = 'none';
            modal.classList.remove('in');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
          }
        });
        modal.setAttribute('data-fallback-close-bound', '1');
      }
      modal.style.display = 'block';
      modal.classList.add('in');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('modal-open');
      enfocarSeccion();
    }
  };

  function abrirConfiguracionDesdeBoton(button) {
    var informes = [];
    var capacidades = [];
    try { informes = JSON.parse(button.getAttribute('data-area-reports') || '[]'); } catch(e) { informes = []; }
    try { capacidades = JSON.parse(button.getAttribute('data-area-capabilities') || '[]'); } catch(e) { capacidades = []; }
    window.abrirModalEditar(
      button.getAttribute('data-area-id'),
      button.getAttribute('data-area-name'),
      informes,
      capacidades,
      button.getAttribute('data-config-focus')
    );
  }
  Array.prototype.forEach.call(document.querySelectorAll('.crm-area-action-edit'), function(button) {
    button.addEventListener('click', function(){ abrirConfiguracionDesdeBoton(button); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('.js-area-config'), function(button) {
    button.addEventListener('click', function(){ abrirConfiguracionDesdeBoton(button); });
  });

  function actualizarResumen(containerId, selector, emptyText, singularText, pluralText) {
    var target = document.getElementById(containerId);
    var selectedInputs = Array.prototype.filter.call(document.querySelectorAll(selector), function(input){ return input.checked; });
    var selected = selectedInputs.length;
    if(target) target.textContent = selected + ' ' + (selected === 1 ? singularText : pluralText);
  }
  function bindResumen(selector, containerId, emptyText, singularText, pluralText) {
    actualizarResumen(containerId, selector, emptyText, singularText, pluralText);
  }
  bindResumen('#modalCapacidadesAlta input[type="checkbox"]', 'resumen_capacidades_alta', 'Ninguna seleccionada', 'capacidad seleccionada', 'capacidades seleccionadas');
  bindResumen('#modalInformesAlta input[name="informes_disponibles[]"]', 'resumen_informes_alta', 'Ninguno seleccionado', 'informe seleccionado', 'informes seleccionados');

  function actualizarEstadoCheckboxes(scope) {
    var labels = scope.querySelectorAll('.crm-area-config-grid .checkbox');
    Array.prototype.forEach.call(labels, function(label){
      var input = label.querySelector('input[type="checkbox"]');
      if(input) label.classList.toggle('is-checked', input.checked);
    });
  }
  function configurarModalSeleccion(idModal, selector, containerId, emptyText, singularText, pluralText) {
    var modal = document.getElementById(idModal);
    if(!modal) return;
    var original = [];
    var aceptado = false;
    var checks = function(){ return Array.prototype.slice.call(modal.querySelectorAll(selector)); };
    var allChecks = function(){ return Array.prototype.slice.call(modal.querySelectorAll('input[type="checkbox"]')); };
    modal.addEventListener('change', function(event){
      if(event.target && event.target.type === 'checkbox') actualizarEstadoCheckboxes(modal);
    });
    if(window.jQuery) {
      $(modal).on('show.bs.modal', function(){
        original = allChecks().map(function(input){ return input.checked; });
        aceptado = false;
        actualizarEstadoCheckboxes(modal);
      });
      $(modal).on('hide.bs.modal', function(){
        if(!aceptado) {
          allChecks().forEach(function(input, index){ input.checked = !!original[index]; });
          actualizarResumen(containerId, selector, emptyText, singularText, pluralText);
          actualizarEstadoCheckboxes(modal);
        }
        aceptado = false;
      });
    }
    var accept = modal.querySelector('.crm-area-config-accept');
    var cancel = modal.querySelector('.crm-area-config-cancel');
    if(accept) accept.addEventListener('click', function(){
      aceptado = true;
      actualizarResumen(containerId, selector, emptyText, singularText, pluralText);
      $('#'+idModal).modal('hide');
    });
    if(cancel) cancel.addEventListener('click', function(){
      aceptado = false;
      $('#'+idModal).modal('hide');
    });
    actualizarEstadoCheckboxes(modal);
  }
  configurarModalSeleccion('modalCapacidadesAlta', 'input[type="checkbox"]', 'resumen_capacidades_alta', 'Ninguna seleccionada', 'capacidad seleccionada', 'capacidades seleccionadas');
  configurarModalSeleccion('modalInformesAlta', 'input[name="informes_disponibles[]"]', 'resumen_informes_alta', 'Ninguno seleccionado', 'informe seleccionado', 'informes seleccionados');
})();
</script>
</body>
</html>
