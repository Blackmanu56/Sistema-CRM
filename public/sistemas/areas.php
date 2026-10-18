<?php
require_once __DIR__.'/../_init.php';
require_once BASE_PATH.'/includes/areas_gestion.php';
crm_proteger_staff('sistemas');
acceso_exigir_gestion('sistemas');
$menu_activo='areas';

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
	$cap_clientes = isset($_POST['puede_clientes']) ? (intval($_POST['puede_clientes']) ? 1 : 0) : 0;
	$cap_deriv = isset($_POST['recibe_derivaciones']) ? (intval($_POST['recibe_derivaciones']) ? 1 : 0) : 0;
	$cap_turnos = isset($_POST['recibe_turnos']) ? (intval($_POST['recibe_turnos']) ? 1 : 0) : 0;
	$asignable = isset($_POST['asignable_empleado']) ? (intval($_POST['asignable_empleado']) ? 1 : 0) : 1;
	// Legacy: solo como resumen para llamadas viejas que no mandan las capacidades.
	$atiende = ($cap_clientes || $cap_deriv || $cap_turnos) ? 1 : 0;

	$res = crm_area_crear($nombre, $clave, $id_coord, $conf_mov, $atiende, $asignable, $cap_clientes, $cap_deriv, $cap_turnos);
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
// EDITAR NOMBRE Y CLAVE DE ÁREA
// -------------------------------------------------------------
if(isset($_POST['editar_area']))
{
	$id_area = isset($_POST['id_area']) ? intval($_POST['id_area']) : 0;
	$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
	$clave = isset($_POST['clave']) ? trim($_POST['clave']) : '';

	$res = crm_area_editar($id_area, $nombre, $clave);
	$_SESSION['msgsis'] = $res['ok'] ? $res['msg'] : $res['error'];
	echo "<script>window.location.href='areas.php'</script>";
	exit();
}

// -------------------------------------------------------------
// TOGGLE ACTIVIDAD DEL ÁREA
// -------------------------------------------------------------
if(isset($_POST['toggle_area']))
{
	$id = isset($_POST['id_area']) ? intval($_POST['id_area']) : 0;
	$act = isset($_POST['activo']) ? intval($_POST['activo']) : 0;
	if($id > 0)
	{
		acceso_ejecutar("UPDATE area SET activo=? WHERE id_area=?", 'ii', array($act ? 0 : 1, $id));
		$_SESSION['msgsis'] = 'Estado del área actualizado.';
	}
	echo "<script>window.location.href='areas.php'</script>";
	exit();
}

if(isset($_POST['toggle_flag_area']))
{
	$id = isset($_POST['id_area']) ? intval($_POST['id_area']) : 0;
	$flag = isset($_POST['flag']) ? trim($_POST['flag']) : '';
	$val = isset($_POST['valor']) ? intval($_POST['valor']) : 0;
	if($id > 0 && in_array($flag, array('puede_clientes', 'recibe_derivaciones', 'recibe_turnos', 'asignable_empleado'), true))
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
			'asignable_empleado' => 'Asignable en el alta'
		);
		$_SESSION['msgsis'] = (isset($etq_flag[$flag]) ? $etq_flag[$flag] : 'Capacidad') . ($val ? ' habilitada.' : ' deshabilitada.');
	}
	echo "<script>window.location.href='areas.php'</script>";
	exit();
}

if(function_exists('crm_asegurar_area_flags')) { crm_asegurar_area_flags(); }
$areas = acceso_consulta("SELECT id_area, clave, nombre, activo, atiende_clientes, puede_clientes, recibe_derivaciones, recibe_turnos, asignable_empleado, creado_en FROM area ORDER BY nombre");
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
    <div class="content">
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
            <input type="hidden" name="nombre" value="<?php echo htmlspecialchars($confirmacion_pendiente['nombre']); ?>">
            <input type="hidden" name="clave" value="<?php echo htmlspecialchars($confirmacion_pendiente['clave']); ?>">
            <input type="hidden" name="id_coordinador" value="<?php echo intval($confirmacion_pendiente['id_coordinador']); ?>">
            <input type="hidden" name="puede_clientes" value="<?php echo intval(isset($confirmacion_pendiente['puede_clientes']) ? $confirmacion_pendiente['puede_clientes'] : 0); ?>">
            <input type="hidden" name="recibe_derivaciones" value="<?php echo intval(isset($confirmacion_pendiente['recibe_derivaciones']) ? $confirmacion_pendiente['recibe_derivaciones'] : 0); ?>">
            <input type="hidden" name="recibe_turnos" value="<?php echo intval(isset($confirmacion_pendiente['recibe_turnos']) ? $confirmacion_pendiente['recibe_turnos'] : 0); ?>">
            <input type="hidden" name="asignable_empleado" value="<?php echo intval(isset($confirmacion_pendiente['asignable_empleado']) ? $confirmacion_pendiente['asignable_empleado'] : 1); ?>">
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
            <div class="form-group">
              <label>Nombre</label>
              <input type="text" name="nombre" class="form-control" required placeholder="Ej. Coordinación de turismo">
            </div>
            <div class="form-group">
              <label>Clave (carpeta / código corto)</label>
              <input type="text" name="clave" class="form-control" placeholder="Ej. turismo (opcional, se genera sola)">
              <p class="muted">Solo minúsculas, números y guión bajo. Debe coincidir con la carpeta pública si la van a usar.</p>
            </div>
            <div class="form-group">
              <label><strong>Capacidades del área</strong></label>
              <label class="checkbox"><input type="checkbox" name="puede_clientes" value="1" checked> Habilitar gestión de clientes</label>
              <label class="checkbox"><input type="checkbox" name="recibe_derivaciones" value="1" checked> Permitir recibir derivaciones</label>
              <label class="checkbox"><input type="checkbox" name="recibe_turnos" value="1" checked> Permitir recibir turnos</label>
              <label class="checkbox"><input type="checkbox" name="asignable_empleado" value="1" checked> Se puede asignar a colaboradores en el alta</label>
              <p class="muted">Son independientes: habilitar clientes NO activa por sí solo las derivaciones ni los turnos.</p>
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
        </div>
      </div>

      <div class="grid simple">
        <div class="grid-title no-border"><h4>Áreas existentes</h4></div>
        <div class="grid-body no-border">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Clave</th>
                <th>Nombre</th>
                <th>Coordinador actual</th>
                <th>Clientes</th>
                <th>Derivaciones</th>
                <th>Turnos</th>
                <th>Asignable</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($areas as $a) { 
              $coord = crm_area_coordinador_activo($a['id_area']);
              $cli_a = isset($a['puede_clientes']) ? intval($a['puede_clientes']) : 0;
              $der_a = isset($a['recibe_derivaciones']) ? intval($a['recibe_derivaciones']) : 0;
              $tur_a = isset($a['recibe_turnos']) ? intval($a['recibe_turnos']) : 0;
              $asig_a = isset($a['asignable_empleado']) ? intval($a['asignable_empleado']) : 1;
              $caps_a = array(
                array('puede_clientes', $cli_a, 'Habilitar gestión de clientes'),
                array('recibe_derivaciones', $der_a, 'Permitir recibir derivaciones'),
                array('recibe_turnos', $tur_a, 'Permitir recibir turnos')
              );
            ?>
              <tr>
                <td><code><?php echo htmlspecialchars($a['clave']); ?></code></td>
                <td><strong><?php echo htmlspecialchars($a['nombre']); ?></strong></td>
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
                  <?php foreach($caps_a as $capc) { ?>
                  <form method="post" style="display:inline; margin-right:4px;">
                    <input type="hidden" name="id_area" value="<?php echo intval($a['id_area']); ?>">
                    <input type="hidden" name="flag" value="<?php echo $capc[0]; ?>">
                    <input type="hidden" name="valor" value="<?php echo intval($capc[1]); ?>">
                    <button type="submit" name="toggle_flag_area" value="1" class="btn btn-xs <?php echo $capc[1] ? 'btn-success' : 'btn-white'; ?>" title="<?php echo htmlspecialchars($capc[2]); ?>">
                      <?php echo $capc[1] ? 'Sí' : 'No'; ?>
                    </button>
                  </form>
                  <?php } ?>
                </td>
                <td>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="id_area" value="<?php echo intval($a['id_area']); ?>">
                    <input type="hidden" name="flag" value="asignable_empleado">
                    <input type="hidden" name="valor" value="<?php echo $asig_a; ?>">
                    <button type="submit" name="toggle_flag_area" value="1" class="btn btn-xs <?php echo $asig_a ? 'btn-success' : 'btn-white'; ?>" title="Visible al dar de alta colaboradores">
                      <?php echo $asig_a ? 'Sí' : 'No'; ?>
                    </button>
                  </form>
                </td>
                <td>
                  <?php if(intval($a['activo'])) { ?>
                    <span class="label label-success">Activa</span>
                  <?php } else { ?>
                    <span class="label label-important">Inactiva</span>
                  <?php } ?>
                </td>
                <td>
                  <button type="button" class="btn btn-white btn-xs btn-mini" style="margin-right:4px;" 
                          onclick="abrirModalEditar(<?php echo intval($a['id_area']); ?>, '<?php echo addslashes($a['nombre']); ?>', '<?php echo addslashes($a['clave']); ?>')">
                    <i class="fa fa-pencil"></i> Editar
                  </button>

                  <button type="button" class="btn btn-info btn-xs btn-mini" 
                          onclick="abrirModalAsignar(<?php echo intval($a['id_area']); ?>, '<?php echo addslashes($a['nombre']); ?>', <?php echo $coord ? intval($coord['id_empleado']) : 0; ?>)">
                    <i class="fa fa-user"></i> <?php echo $coord ? 'Cambiar' : 'Asignar'; ?>
                  </button>

                  <?php if($coord) { ?>
                  <form method="post" style="display:inline; margin-left:4px;">
                    <input type="hidden" name="id_area" value="<?php echo intval($a['id_area']); ?>">
                    <button type="submit" name="quitar_coordinador" value="1" class="btn btn-danger btn-xs btn-mini" title="Quitar coordinador"><i class="fa fa-times"></i> Quitar</button>
                  </form>
                  <?php } ?>

                  <form method="post" style="display:inline; margin-left:4px;">
                    <input type="hidden" name="id_area" value="<?php echo intval($a['id_area']); ?>">
                    <input type="hidden" name="activo" value="<?php echo intval($a['activo']); ?>">
                    <button type="submit" name="toggle_area" value="1" class="btn btn-white btn-xs btn-mini"><?php echo intval($a['activo']) ? 'Desactivar' : 'Activar'; ?></button>
                  </form>
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

<!-- MODAL EDITAR ÁREA -->
<div class="modal fade" id="modalEditarArea" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title">Editar área: <span id="modal_edit_area_titulo" style="font-weight:bold;"></span></h4>
      </div>
      <form method="post" id="form_modal_editar_area">
        <div class="modal-body">
          <input type="hidden" name="id_area" id="modal_edit_id_area" value="0">
          
          <div class="form-group">
            <label>Nombre del área</label>
            <input type="text" name="nombre" id="modal_edit_nombre" class="form-control" required placeholder="Ej. Comercio Exterior">
          </div>

          <div class="form-group">
            <label>Clave del área (código corto / carpeta)</label>
            <input type="text" name="clave" id="modal_edit_clave" class="form-control" required placeholder="Ej. comercio">
            <p class="muted" style="margin-top:5px; font-size:12px;">Solo letras minúsculas, números y guión bajo. Si cambiás la clave, se actualiza automáticamente la carpeta del módulo.</p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
          <button type="submit" name="editar_area" value="1" class="btn btn-primary">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL ASIGNAR / CAMBIAR COORDINADOR -->
<div class="modal fade" id="modalAsignarCoord" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title">Asignar coordinador a <span id="modal_area_nombre" style="font-weight:bold;"></span></h4>
      </div>
      <form method="post" id="form_modal_asignar">
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
    $('#modalAsignarCoord').modal('show');
  };

  window.abrirModalEditar = function(idArea, nombreArea, claveArea) {
    document.getElementById('modal_edit_id_area').value = idArea;
    document.getElementById('modal_edit_area_titulo').textContent = nombreArea;
    document.getElementById('modal_edit_nombre').value = nombreArea;
    document.getElementById('modal_edit_clave').value = claveArea;
    $('#modalEditarArea').modal('show');
  };
})();
</script>
</body>
</html>
