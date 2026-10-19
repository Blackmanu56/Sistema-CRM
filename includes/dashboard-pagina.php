<?php
// Dashboard útil por alcance: empleado / área / todo.
// Resumen del día: turnos, tareas de hoy, vencidas, en proceso.
require_once BASE_PATH.'/includes/informes.php';
require_once BASE_PATH.'/includes/actividades.php';

// Evitar pantalla en blanco: menu-seccion usa tareas_link.
if(!function_exists('tareas_link'))
{
	$crm_datos=dirname(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '').'/datos-tareas.php';
	if(is_file($crm_datos))
	{
		require_once $crm_datos;
		if(function_exists('tareas_iniciar_contexto'))
		{
			tareas_iniciar_contexto();
		}
	}
}
if(!function_exists('tareas_link'))
{
	function tareas_link($pagina, $extra=array())
	{
		$area=function_exists('acceso_area_pantalla') ? acceso_area_pantalla() : 'general';
		if($area=='' || $area=='admin') { $area='general'; }
		$q='';
		if(count($extra)>0) { $q='?'.http_build_query($extra); }
		return url_crm($area.'/'.$pagina).$q;
	}
}

$area_act=acceso_area_pantalla();
if($area_act=='' || $area_act=='admin' || $area_act=='ajax')
{
	$area_act=function_exists('acceso_area_activa') ? acceso_area_activa() : acceso_area();
}

$alcance=crm_dashboard_alcance();
if(function_exists('acceso_es_empleado') && acceso_es_empleado())
{
	$alcance='empleado';
}
elseif(function_exists('acceso_es_coord_general') && (acceso_es_coord_general() || acceso_es_presidencia() || acceso_es_super() || acceso_es_general()))
{
	if($area_act==='general')
	{
		$alcance='todo';
	}
	else
	{
		$alcance='area';
	}
}
else
{
	$alcance='area';
}

$etq=acceso_etiquetas_area();
$areas_operativas=array();
foreach($etq as $k=>$t)
{
	// Estas claves no tienen operación diaria propia: filtrar por ellas deja el dash vacío.
	if(in_array($k, array('general','sistemas','admin','ajax'), true))
	{
		continue;
	}
	$areas_operativas[$k]=$t;
}

if($area_act==='sistemas')
{
	$filtro='sistemas';
	$area_links='sistemas';
}
else
{
	// Si estamos en /general/ o el área de sesión no es operativa, anclar links a una área real.
	$area_links=$area_act;
	if($alcance==='todo' || !isset($areas_operativas[$area_links]))
	{
		$mia=function_exists('acceso_area_activa') ? acceso_area_activa() : acceso_area();
		if(isset($areas_operativas[$mia]))
		{
			$area_links=$mia;
		}
		else
		{
			$area_links=isset($areas_operativas['recepcion']) ? 'recepcion' : (count($areas_operativas) ? array_keys($areas_operativas)[0] : 'recepcion');
		}
	}
}
$titulo_area=isset($etq[$area_act]) ? $etq[$area_act] : $area_act;
$hoy=date('Y-m-d');
$id_yo=function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : 0;

if($area_act==='sistemas')
{
	$filtro='sistemas';
}
else if($alcance=='todo')
{
	$filtro=isset($_GET['area']) ? trim($_GET['area']) : 'todas';
	if($filtro==='' || $filtro==='general' || $filtro==='sistemas' || $filtro==='admin')
	{
		$filtro='todas';
	}
	if($filtro!='todas' && !isset($areas_operativas[$filtro]))
	{
		$filtro='todas';
	}
}
else
{
	$filtro=$area_act;
}

$sin_db=(!isset($GLOBALS['con']) || !$GLOBALS['con']);
$cont=array('No iniciada'=>0,'En proceso'=>0,'Pausada'=>0,'Terminada'=>0,'Vencida'=>0,'total'=>0);
$tareas_hoy=array();
$tareas_vencidas=array();
$tareas_proceso=array();
$turnos_hoy=array();
$avisos=array();
$mias=array();

if(!function_exists('dash_estado_txt'))
{
	function dash_estado_txt($est)
	{
		if(function_exists('informes_estado_texto'))
		{
			return informes_estado_texto($est);
		}
		$map=array('no_iniciada'=>'No iniciada','en_proceso'=>'En proceso','pausada'=>'Pausada','terminada'=>'Terminada','cancelada'=>'Eliminada');
		return isset($map[$est]) ? $map[$est] : $est;
	}
}

if(!function_exists('dash_sql_area_filtro'))
{
	function dash_sql_area_filtro($filtro, &$types, &$vals, $alias_area='a')
	{
		if($filtro=='' || $filtro==='todas' || $filtro==='general')
		{
			return '';
		}
		$types.='s';
		$vals[]=$filtro;
		return ' AND '.$alias_area.'.clave=?';
	}
}

if(!$sin_db && acceso_tabla_existe('tarea'))
{
	if($alcance=='empleado' && $id_yo>0)
	{
		$rows=acceso_consulta(
			"SELECT t.id_tarea, t.titulo, t.estado, t.fecha_cierre, t.avance, a.clave AS area_clave, a.nombre AS area_nombre
			FROM tarea t
			INNER JOIN area a ON a.id_area=t.id_area
			INNER JOIN tarea_responsable tr ON tr.id_tarea=t.id_tarea AND tr.id_empleado=?
			WHERE t.estado<>'cancelada' AND a.clave=?
			ORDER BY FIELD(t.estado,'en_proceso','no_iniciada','pausada','terminada'), t.fecha_cierre ASC",
			'is',
			array($id_yo, $area_act)
		);
		foreach($rows as $r)
		{
			$est=dash_estado_txt($r['estado']);
			$cont['total']++;
			if(isset($cont[$est])) { $cont[$est]++; }
			$vencida=($est!='Terminada' && !empty($r['fecha_cierre']) && strtotime($r['fecha_cierre'])<strtotime($hoy));
			if($vencida) { $cont['Vencida']++; $tareas_vencidas[]=$r; }
			if($r['fecha_cierre']===$hoy && $est!='Terminada') { $tareas_hoy[]=$r; }
			if($r['estado']==='en_proceso') { $tareas_proceso[]=$r; }
			$mias[]=$r;
		}
	}
	else
	{
		$tareas_inf=informes_tareas_por_area($filtro==='todas' ? '' : $filtro);
		$cont=$tareas_inf['total'];
		if($alcance=='area' && isset($tareas_inf['areas'][$area_act]))
		{
			$cont=$tareas_inf['areas'][$area_act];
		}

		$types='';
		$vals=array();
		$extra=dash_sql_area_filtro($filtro, $types, $vals);
		$types2=$types.'s';
		$vals2=$vals;
		$vals2[]=$hoy;
		$tareas_hoy=acceso_consulta(
			"SELECT t.id_tarea, t.titulo, t.estado, t.fecha_cierre, t.avance, a.clave AS area_clave, a.nombre AS area_nombre
			FROM tarea t INNER JOIN area a ON a.id_area=t.id_area
			WHERE t.estado NOT IN ('terminada','cancelada') AND t.fecha_cierre=?".$extra."
			ORDER BY t.prioridad ASC, t.titulo ASC LIMIT 12",
			's'.$types,
			array_merge(array($hoy), $vals)
		);
		$tareas_vencidas=acceso_consulta(
			"SELECT t.id_tarea, t.titulo, t.estado, t.fecha_cierre, t.avance, a.clave AS area_clave, a.nombre AS area_nombre
			FROM tarea t INNER JOIN area a ON a.id_area=t.id_area
			WHERE t.estado NOT IN ('terminada','cancelada') AND t.fecha_cierre < ?".$extra."
			ORDER BY t.fecha_cierre ASC LIMIT 12",
			's'.$types,
			array_merge(array($hoy), $vals)
		);
		$tot_hv=acceso_consulta_una(
			"SELECT SUM(t.fecha_cierre=?) AS hoy, SUM(t.fecha_cierre<?) AS venc
			FROM tarea t INNER JOIN area a ON a.id_area=t.id_area
			WHERE t.estado NOT IN ('terminada','cancelada')".$extra,
			'ss'.$types,
			array_merge(array($hoy, $hoy), $vals)
		);
		$tareas_proceso=acceso_consulta(
			"SELECT t.id_tarea, t.titulo, t.estado, t.fecha_cierre, t.avance, a.clave AS area_clave, a.nombre AS area_nombre
			FROM tarea t INNER JOIN area a ON a.id_area=t.id_area
			WHERE t.estado='en_proceso'".$extra."
			ORDER BY t.fecha_cierre ASC LIMIT 12",
			$types,
			$vals
		);
	}
}

if(!$sin_db && acceso_tabla_existe('atencion'))
{
	if($area_act==='sistemas')
	{
		$turnos_hoy=array();
	}
	else
	{
		$types='';
		$vals=array();
		$types='';
		$vals=array();
		$extra='';
		if($area_act==='recepcion')
		{
			// Recepción ve los turnos registrados para hoy derivados a las distintas áreas.
			// Criterio coherente con el listado: turnos de hoy no cancelados.
			$extra='';
			$filtro_estado="at.estado NOT IN ('cancelada')";
		}
		else if($alcance==='empleado')
		{
			// Empleado: turnos del área donde trabaja
			$extra=dash_sql_area_filtro($area_act, $types, $vals);
			$filtro_estado="at.estado IN ('abierta','en_atencion')";
		}
		else
		{
			$extra=dash_sql_area_filtro($filtro, $types, $vals);
			$filtro_estado="at.estado IN ('abierta','en_atencion')";
		}
		$turnos_hoy=acceso_consulta(
			"SELECT at.id_atencion, at.estado, at.motivo, COALESCE(at.fecha_turno, at.fecha) AS fecha_ref,
				c.nombre AS cliente_nombre, a.clave AS area_clave, a.nombre AS area_nombre
			FROM atencion at
			INNER JOIN cliente c ON c.id_cliente=at.id_cliente
			INNER JOIN area a ON a.id_area=at.id_area
			WHERE ".$filtro_estado."
				AND DATE(COALESCE(at.fecha_turno, at.fecha))=?".$extra."
			ORDER BY COALESCE(at.fecha_turno, at.fecha) ASC",
			's'.$types,
			array_merge(array($hoy), $vals)
		);
	}
}

if(!$sin_db && $id_yo>0 && function_exists('crm_avisos_extra_mios'))
{
	$avisos=crm_avisos_extra_mios();
	if(count($avisos)>6) { $avisos=array_slice($avisos, 0, 6); }
}

// Actividades relacionadas del área activa
$actividades_rel=array();
if(!$sin_db && function_exists('crm_eventos_de_area') && $area_act!=='' && $area_act!=='general')
{
	$evs=crm_eventos_de_area($area_act);
	foreach($evs as $ev)
	{
		if(function_exists('actividad_puede_ver') && actividad_puede_ver($ev))
		{
			$actividades_rel[]=$ev;
			if(count($actividades_rel)>=6) { break; }
		}
	}
}

$n_hoy_tar=isset($tot_hv) && $tot_hv ? intval($tot_hv['hoy']) : count($tareas_hoy);
$n_venc=isset($tot_hv) && $tot_hv ? intval($tot_hv['venc']) : count($tareas_vencidas);
$n_proc=isset($cont['En proceso']) ? intval($cont['En proceso']) : count($tareas_proceso);
$n_turnos=($area_act==='sistemas') ? 0 : count($turnos_hoy);

$menu_activo='dashboard';
$nombre_vista=($alcance=='todo') ? 'Todas las áreas' : $titulo_area;
if($alcance==='todo' && $filtro!=='todas' && isset($areas_operativas[$filtro]))
{
	$nombre_vista=$areas_operativas[$filtro];
}
$base_tareas=url_crm(($alcance==='todo' ? 'general' : $area_links).'/tareas.php');
$base_aten=url_crm($area_links.'/atenciones.php');
// Recepción gestiona turnos generales; el dashboard enlaza directamente a los de hoy.
if($area_act==='recepcion')
{
	$base_aten=url_crm('recepcion/atenciones.php?fecha=hoy');
}
elseif($alcance==='todo' && isset($areas_operativas['recepcion']))
{
	$base_aten=url_crm('recepcion/atenciones.php');
}
elseif(isset($areas_operativas[$area_act]))
{
	$base_aten=url_crm($area_act.'/atenciones.php');
}
$base_acts=url_crm(($alcance==='todo' ? 'general' : $area_links).'/actividades.php');
$link_tareas=function($extra=array()) use ($base_tareas, $alcance, $filtro, $area_act) {
	if($area_act!=='sistemas' && $alcance==='todo' && $filtro!=='todas')
	{
		$extra['area']=$filtro;
	}
	return $base_tareas.(count($extra) ? '?'.http_build_query($extra) : '');
};
$bajas_pend=function_exists('crm_tarea_bajas_pendientes_mias') ? crm_tarea_bajas_pendientes_mias() : array();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | Inicio</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<style>
.crm-dash-panels {
	display: flex;
	flex-wrap: wrap;
	margin-left: -10px;
	margin-right: -10px;
}
.crm-dash-panels > [class*="col-"] {
	display: flex;
	padding-left: 10px;
	padding-right: 10px;
}
.crm-dash-panels .crm-dashboard-panel {
	display: flex;
	flex: 1 1 auto;
	flex-direction: column;
	width: 100%;
}
.crm-dash-panels .crm-dashboard-panel .grid-body {
	flex: 1 1 auto;
}
.crm-dashboard-panel {
	margin-bottom: 18px;
	border: 1px solid #e2e8f0 !important;
	border-top: 3px solid var(--crm-panel-accent, #cbd5e1) !important;
	border-radius: 10px !important;
	box-shadow: 0 5px 16px rgba(15, 23, 42, .055);
	overflow: hidden;
}
.crm-dashboard-panel .grid-title {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 13px 16px;
	border-bottom: 1px solid #e8edf2;
	background: linear-gradient(180deg, #ffffff 0%, #f9fbfd 100%);
}
.crm-dashboard-panel .grid-title h4 {
	display: flex;
	align-items: center;
	gap: 10px;
	min-width: 0;
	margin: 0;
	color: #334155;
	font-size: 15px;
	font-weight: 600;
	line-height: 1.4;
}
.crm-dashboard-panel .crm-dashboard-panel-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 32px;
	width: 32px;
	height: 32px;
	border-radius: 9px;
	background: var(--crm-panel-tint, #eff6ff);
	color: var(--crm-panel-accent, #2563eb);
	font-size: 14px;
}
.crm-dashboard-panel--appointments { --crm-panel-accent: #0284c7; --crm-panel-tint: #e0f2fe; }
.crm-dashboard-panel--today { --crm-panel-accent: #7c3aed; --crm-panel-tint: #f3e8ff; }
.crm-dashboard-panel--overdue { --crm-panel-accent: #dc2626; --crm-panel-tint: #fee2e2; }
.crm-dashboard-panel--progress { --crm-panel-accent: #059669; --crm-panel-tint: #d1fae5; }
.crm-dashboard-panel--notices { --crm-panel-accent: #d97706; --crm-panel-tint: #fef3c7; }
.crm-dashboard-panel--status { --crm-panel-accent: #475569; --crm-panel-tint: #e2e8f0; }
.crm-dashboard-panel .grid-title h4 > span:last-child {
	overflow: hidden;
	text-overflow: ellipsis;
}
.crm-dashboard-panel-count {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 28px;
	height: 24px;
	padding: 0 8px;
	border: 1px solid var(--crm-panel-tint, #e2e8f0);
	border-radius: 999px;
	background: var(--crm-panel-tint, #f1f5f9);
	color: var(--crm-panel-accent, #475569);
	font-size: 12px;
	font-weight: 700;
	line-height: 1;
}
.crm-dashboard-panel .grid-body {
	padding: 14px 16px 15px;
}
.crm-dashboard-panel .crm-dashboard-item {
	margin: 0 0 8px;
	padding: 10px 12px;
	border: 1px solid #e8edf3;
	border-left: 3px solid var(--crm-panel-accent, #cbd5e1);
	border-radius: 8px;
	background: #fbfcfe;
	color: #475569;
	line-height: 1.4;
}
.crm-dashboard-panel .crm-dashboard-item-link {
	position: relative;
	display: block;
	padding-right: 36px;
	color: inherit;
	text-decoration: none !important;
	transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
}
.crm-dashboard-panel .crm-dashboard-item-link .crm-dashboard-item-title {
	color: #1e3a5f;
	font-weight: 600;
}
.crm-dashboard-panel .crm-dashboard-item-link:hover {
	border-color: var(--crm-panel-accent, #94a3b8);
	background: #fff;
	box-shadow: 0 2px 7px rgba(15, 23, 42, .08);
}
.crm-dashboard-panel .crm-dashboard-item-link:focus-visible {
	outline: 3px solid rgba(37, 99, 235, .35);
	outline-offset: 2px;
}
.crm-dashboard-panel .crm-dashboard-item-arrow {
	position: absolute;
	right: 13px;
	top: 50%;
	color: var(--crm-panel-accent, #64748b);
	transform: translateY(-50%);
}
.crm-dashboard-panel .crm-dashboard-list .crm-dashboard-item:last-child {
	margin-bottom: 0;
}
.crm-dashboard-panel .crm-dashboard-item a:not(.btn) {
	font-weight: 600;
	color: #1e3a5f;
}
.crm-dashboard-panel .crm-dashboard-item-main,
.crm-dashboard-panel .crm-dashboard-item-meta {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px 12px;
}
.crm-dashboard-panel .crm-dashboard-item-main {
	min-width: 0;
}
.crm-dashboard-panel .crm-dashboard-item-title {
	min-width: 0;
	overflow-wrap: anywhere;
	color: #1e3a5f;
	font-weight: 600;
}
.crm-dashboard-panel .crm-dashboard-item-meta {
	justify-content: flex-start;
	flex-wrap: wrap;
	margin-top: 5px;
	color: #64748b;
	font-size: 12px;
}
.crm-dashboard-panel .crm-dashboard-item-meta i {
	margin-right: 4px;
	color: #94a3b8;
}
.crm-dashboard-panel .crm-dashboard-item-status {
	display: inline-flex;
	align-items: center;
	flex: 0 0 auto;
	padding: 3px 8px;
	border-radius: 999px;
	background: var(--crm-panel-tint, #f1f5f9);
	color: var(--crm-panel-accent, #475569);
	font-size: 11px;
	font-weight: 600;
	white-space: nowrap;
}
.crm-dashboard-panel .crm-dashboard-empty {
	display: flex;
	align-items: center;
	gap: 10px;
	margin: 0 0 8px;
	padding: 12px;
	border: 1px dashed #dbe3ec;
	border-radius: 8px;
	background: #f8fafc;
	color: #64748b;
	font-size: 13px;
}
.crm-dashboard-panel .crm-dashboard-empty i {
	color: var(--crm-panel-accent, #94a3b8);
	font-size: 15px;
}
.crm-dashboard-panel .crm-dashboard-actions {
	margin: 12px 0 0;
	padding-top: 10px;
	border-top: 1px solid #eef2f6;
}
.crm-dashboard-panel .crm-dashboard-actions .btn,
.crm-dashboard-panel .crm-dashboard-summary-actions .btn {
	box-sizing: border-box;
	min-height: 30px;
	padding: 6px 11px;
	font-size: 12px;
	font-weight: 600;
	line-height: 1.4;
	border-radius: 6px;
}
.crm-dashboard-panel .crm-dashboard-summary {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 8px;
	margin: 0;
}
.crm-dashboard-panel .crm-dashboard-stat {
	display: flex;
	flex-direction: column;
	gap: 3px;
	min-width: 0;
	padding: 10px 11px;
	border: 1px solid #e8edf2;
	border-radius: 8px;
	background: #f8fafc;
}
.crm-dashboard-panel .crm-dashboard-stat-label {
	color: #64748b;
	font-size: 11px;
	line-height: 1.3;
}
.crm-dashboard-panel .crm-dashboard-stat-value {
	color: #1e293b;
	font-size: 18px;
	font-weight: 700;
	line-height: 1.1;
}
.crm-dashboard-stat--total { border-left: 3px solid #64748b !important; }
.crm-dashboard-stat--new { border-left: 3px solid #2563eb !important; }
.crm-dashboard-stat--progress { border-left: 3px solid #059669 !important; }
.crm-dashboard-stat--paused { border-left: 3px solid #d97706 !important; }
.crm-dashboard-stat--done { border-left: 3px solid #0d9488 !important; }
.crm-dashboard-stat--overdue { border-left: 3px solid #dc2626 !important; }
.crm-dashboard-panel .crm-dashboard-summary-actions {
	margin: 12px 0 0;
}
@media (max-width: 767px) {
	.crm-dashboard-panel .grid-title { padding: 11px 12px; }
	.crm-dashboard-panel .grid-body { padding: 12px; }
	.crm-dashboard-panel .crm-dashboard-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 420px) {
	.crm-dashboard-panel .crm-dashboard-item-main { align-items: flex-start; flex-direction: column; }
	.crm-dashboard-panel .crm-dashboard-summary { grid-template-columns: 1fr 1fr; }
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
                <ul class="breadcrumb">
                    <li><p>ESTÁS AQUÍ</p></li>
                    <li><a href="#" class="active">Inicio</a></li>
                </ul>
                <?php if(file_exists('menu-seccion.php')) { include('menu-seccion.php'); } ?>
                <?php if(isset($_SESSION['msg_cliente_exito']) && $_SESSION['msg_cliente_exito']!='') { ?>
                <div class="alert alert-success">
                    <button class="close" data-dismiss="alert"></button>
                    <i class="fa fa-check"></i> <?php echo htmlspecialchars($_SESSION['msg_cliente_exito']); unset($_SESSION['msg_cliente_exito']); ?>
                </div>
                <?php } ?>
                <div class="page-title">
                    <h3>Inicio · <?php echo htmlspecialchars($nombre_vista); ?></h3>
                    <p class="muted" style="margin:0;">
                        Hoy <?php echo date('d/m/Y'); ?> · 
                        <?php if($alcance==='empleado') { ?>
                        Panel de tareas y actividades asignadas al colaborador
                        <?php } elseif(acceso_es_super() || acceso_es_coord_general()) { ?>
                        Supervisión general del área activa
                        <?php } else { ?>
                        Panel de supervisión y gestión de coordinación
                        <?php } ?>
                    </p>
                    <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                        <?php if(function_exists('acceso_puede_alta_cliente') && acceso_puede_alta_cliente($area_act) && !in_array($area_act, array('rrhh','sistemas'), true)) { ?>
                        <a class="btn btn-white btn-cons" href="<?php echo url_crm($area_act.'/alta-cliente.php'); ?>"><i class="fa fa-user"></i> Nuevo cliente</a>
                        <?php } ?>
                        <?php if($area_act==='recepcion') { ?>
                        <a class="btn btn-white btn-cons" href="<?php echo url_crm('recepcion/nueva-atencion.php'); ?>"><i class="fa fa-calendar-o"></i> Nuevo turno</a>
                        <?php } elseif(function_exists('acceso_puede_alta_cliente') && acceso_puede_alta_cliente($area_act) && !in_array($area_act, array('rrhh','sistemas','general'), true)) { ?>
                        <a class="btn btn-white btn-cons" href="<?php echo url_crm($area_act.'/nueva-atencion.php'); ?>"><i class="fa fa-calendar-o"></i> Nuevo turno</a>
                        <?php } elseif($area_act==='rrhh' && !acceso_es_empleado()) { ?>
                        <?php if(function_exists('acceso_puede_alta_cliente') && acceso_puede_alta_cliente('rrhh')) { ?>
                        <a class="btn btn-white btn-cons" href="<?php echo url_crm('rrhh/alta-cliente.php'); ?>"><i class="fa fa-user"></i> Nuevo cliente</a>
                        <?php } ?>
                        <a class="btn btn-white btn-cons" href="<?php echo url_crm('rrhh/alta-usuario.php'); ?>"><i class="fa fa-user"></i> Alta de colaborador</a>
                        <a class="btn btn-white btn-cons" href="<?php echo url_crm('rrhh/empleados.php'); ?>"><i class="fa fa-users"></i> Colaboradores</a>
                        <?php } elseif($area_act==='sistemas' && !acceso_es_empleado()) { ?>
                        <?php if(function_exists('acceso_puede_alta_cliente') && acceso_puede_alta_cliente('sistemas')) { ?>
                        <a class="btn btn-white btn-cons" href="<?php echo url_crm('sistemas/alta-cliente.php'); ?>"><i class="fa fa-user"></i> Nuevo cliente</a>
                        <?php } ?>
                        <a class="btn btn-white btn-cons" href="<?php echo url_crm('sistemas/dominios.php'); ?>"><i class="fa fa-globe"></i> Dominios</a>
                        <a class="btn btn-white btn-cons" href="<?php echo url_crm('sistemas/programas.php'); ?>"><i class="fa fa-th-large"></i> Programas</a>
                        <?php } ?>

                        <a class="btn btn-white btn-cons" href="<?php echo url_crm($area_links.'/nueva-tarea.php'); ?>"><i class="fa fa-plus"></i> Nueva tarea</a>
                        <?php if(function_exists('actividad_puede_crear') && actividad_puede_crear($area_act)) { ?>
                        <a class="btn btn-white btn-cons" href="<?php echo url_crm($area_links.'/nueva-actividad.php'); ?>"><i class="fa fa-calendar-o"></i> Nueva actividad</a>
                        <?php } ?>
                    </div>
                </div>

                <?php if($sin_db) { ?>
                <div class="alert alert-error">No hay conexión a la base. Levantá MySQL en XAMPP y recargá.</div>
                <?php } ?>

                <?php if($alcance=='todo' && $area_act!=='sistemas') { ?>
                <div class="grid simple"><div class="grid-body">
                    <form method="get" class="form-inline">
                        <label>Área</label>
                        <select name="area" class="form-control" onchange="this.form.submit()">
                            <option value="todas" <?php if($filtro==='todas') echo 'selected'; ?>>Todas</option>
                            <?php foreach($areas_operativas as $k=>$t) { ?>
                            <option value="<?php echo htmlspecialchars($k); ?>" <?php if($filtro===$k) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
                            <?php } ?>
                        </select>
                        <span class="muted" style="margin-left:10px;">Coordinación general ve el resumen de todo el CRM.</span>
                    </form>
                </div></div>
                <?php } ?>

                <div class="row crm-dash-tiles">
                    <?php if($area_act!=='sistemas') { ?>
                    <div class="col-md-3 col-sm-6"><a href="<?php echo htmlspecialchars($base_aten); ?>"><div class="tiles blue added-margin"><div class="tiles-body">
                        <div class="tiles-title">Atenciones de hoy</div>
                        <div class="heading"><?php echo intval($n_turnos); ?></div>
                    </div></div></a></div>
                    <?php } ?>
                    <div class="<?php echo ($area_act==='sistemas') ? 'col-md-4 col-sm-4' : 'col-md-3 col-sm-6'; ?>"><a href="<?php echo htmlspecialchars($link_tareas(array('vence'=>'hoy'))); ?>"><div class="tiles purple added-margin"><div class="tiles-body">
                        <div class="tiles-title">Tareas que vencen hoy</div>
                        <div class="heading"><?php echo intval($n_hoy_tar); ?></div>
                    </div></div></a></div>
                    <div class="<?php echo ($area_act==='sistemas') ? 'col-md-4 col-sm-4' : 'col-md-3 col-sm-6'; ?>"><a href="<?php echo htmlspecialchars($link_tareas(array('estado'=>'Vencida'))); ?>"><div class="tiles red added-margin"><div class="tiles-body">
                        <div class="tiles-title">Vencidas</div>
                        <div class="heading"><?php echo intval($n_venc); ?></div>
                    </div></div></a></div>
                    <div class="<?php echo ($area_act==='sistemas') ? 'col-md-4 col-sm-4' : 'col-md-3 col-sm-6'; ?>"><a href="<?php echo htmlspecialchars($link_tareas(array('estado'=>'En proceso'))); ?>"><div class="tiles green added-margin"><div class="tiles-body">
                        <div class="tiles-title">En proceso</div>
                        <div class="heading"><?php echo intval($n_proc); ?></div>
                    </div></div></a></div>
                </div>

                <div class="row crm-dash-panels">
                    <?php if($area_act!=='sistemas') { ?>
                    <div class="col-md-6">
                        <div class="grid simple crm-dashboard-panel crm-dashboard-panel--appointments">
                            <div class="grid-title"><h4><span class="crm-dashboard-panel-icon"><i class="fa fa-clock-o"></i></span><span>Atenciones de hoy</span></h4><span class="crm-dashboard-panel-count"><?php echo count($turnos_hoy); ?></span></div>
                            <div class="grid-body">
                                <?php if(count($turnos_hoy)==0) { ?><p class="muted crm-dashboard-empty"><i class="fa fa-check-circle-o" aria-hidden="true"></i><span>No hay atenciones abiertas para hoy.</span></p><?php } ?>
                                <div class="crm-dashboard-list">
                                <?php foreach($turnos_hoy as $t) {
                                	$ft=isset($t['fecha_ref']) ? $t['fecha_ref'] : '';
                                	$area_t=($area_act==='recepcion') ? 'recepcion' : (isset($t['area_clave']) ? $t['area_clave'] : $area_act);
				$puede_abrir_atencion=function_exists('crm_atencion_puede_ver') && crm_atencion_puede_ver($t);
				$url_atencion=url_crm($area_t.'/atencion.php?id='.intval($t['id_atencion']));
                                ?>
                                <?php if($puede_abrir_atencion) { ?><a class="crm-dashboard-item crm-dashboard-item-link" href="<?php echo htmlspecialchars($url_atencion, ENT_QUOTES, 'UTF-8'); ?>"><?php } else { ?><div class="crm-dashboard-item"><?php } ?>
                                    <div class="crm-dashboard-item-main">
                                        <strong class="crm-dashboard-item-title">#<?php echo intval($t['id_atencion']); ?> · <?php echo htmlspecialchars($t['cliente_nombre']); ?></strong>
                                        <span class="crm-dashboard-item-status"><?php echo htmlspecialchars(function_exists('crm_estado_texto') ? crm_estado_texto($t['estado']) : $t['estado']); ?></span>
                                    </div>
                                    <div class="crm-dashboard-item-meta">
                                        <?php if($alcance==='todo' || $area_act==='recepcion') { ?><span><i class="fa fa-folder-o" aria-hidden="true"></i><?php echo htmlspecialchars($t['area_nombre']); ?></span><?php } ?>
                                        <?php if($ft!='') { ?><span><i class="fa fa-calendar-o" aria-hidden="true"></i><?php echo htmlspecialchars(function_exists('crm_turno_fecha_texto') ? crm_turno_fecha_texto($ft) : $ft); ?></span><?php } ?>
                                    </div>
                                    <?php if($puede_abrir_atencion) { ?><i class="fa fa-angle-right crm-dashboard-item-arrow" aria-hidden="true"></i><?php } ?>
                                <?php if($puede_abrir_atencion) { ?></a><?php } else { ?></div><?php } ?>
                                <?php } ?>
                                </div>
                                <p class="crm-dashboard-actions">
                                    <a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($base_aten); ?>">Ver atenciones</a>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    <div class="<?php echo ($area_act==='sistemas') ? 'col-md-12' : 'col-md-6'; ?>">
                        <div class="grid simple crm-dashboard-panel crm-dashboard-panel--today">
                            <div class="grid-title"><h4><span class="crm-dashboard-panel-icon"><i class="fa fa-calendar"></i></span><span>Tareas que vencen hoy</span></h4><span class="crm-dashboard-panel-count"><?php echo count($tareas_hoy); ?></span></div>
                            <div class="grid-body">
                                <?php if(count($tareas_hoy)==0) { ?><p class="muted crm-dashboard-empty"><i class="fa fa-check-circle-o" aria-hidden="true"></i><span>Nada vence hoy.</span></p><?php } ?>
                                <div class="crm-dashboard-list">
                                <?php foreach($tareas_hoy as $t) { $puede_abrir_tarea=($alcance==='empleado') || (function_exists('tarea_puede_ver') && tarea_puede_ver($t)); $url_tarea=url_crm($t['area_clave'].'/ver-tarea.php?id='.intval($t['id_tarea'])); ?>
                                <?php if($puede_abrir_tarea) { ?><a class="crm-dashboard-item crm-dashboard-item-link" href="<?php echo htmlspecialchars($url_tarea, ENT_QUOTES, 'UTF-8'); ?>"><?php } else { ?><div class="crm-dashboard-item"><?php } ?>
                                    <div class="crm-dashboard-item-main">
                                        <strong class="crm-dashboard-item-title"><?php echo htmlspecialchars($t['titulo']); ?></strong>
                                        <span class="crm-dashboard-item-status"><?php echo htmlspecialchars(dash_estado_txt($t['estado'])); ?></span>
                                    </div>
                                    <div class="crm-dashboard-item-meta"><span><i class="fa fa-calendar-o" aria-hidden="true"></i> <?php echo htmlspecialchars(date('d/m/Y', strtotime($t['fecha_cierre']))); ?></span><span><i class="fa fa-tasks" aria-hidden="true"></i> Avance <?php echo intval($t['avance']); ?>%</span></div>
                                    <?php if($puede_abrir_tarea) { ?><i class="fa fa-angle-right crm-dashboard-item-arrow" aria-hidden="true"></i><?php } ?>
                                <?php if($puede_abrir_tarea) { ?></a><?php } else { ?></div><?php } ?>
                                <?php } ?>
                                </div>
                                <p class="crm-dashboard-actions"><a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($link_tareas(array('vence'=>'hoy'))); ?>">Ver las que vencen hoy</a></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row crm-dash-panels">
                    <div class="col-md-6">
                        <div class="grid simple crm-dashboard-panel crm-dashboard-panel--overdue">
                            <div class="grid-title"><h4><span class="crm-dashboard-panel-icon"><i class="fa fa-exclamation-triangle"></i></span><span>Vencidas (sin cerrar)</span></h4><span class="crm-dashboard-panel-count"><?php echo count($tareas_vencidas); ?></span></div>
                            <div class="grid-body">
                                <?php if(count($tareas_vencidas)==0) { ?><p class="muted crm-dashboard-empty"><i class="fa fa-check-circle-o" aria-hidden="true"></i><span>No hay tareas vencidas.</span></p><?php } ?>
                                <div class="crm-dashboard-list">
                                <?php foreach($tareas_vencidas as $t) { $puede_abrir_tarea=($alcance==='empleado') || (function_exists('tarea_puede_ver') && tarea_puede_ver($t)); $url_tarea=url_crm($t['area_clave'].'/ver-tarea.php?id='.intval($t['id_tarea'])); ?>
                                <?php if($puede_abrir_tarea) { ?><a class="crm-dashboard-item crm-dashboard-item-link" href="<?php echo htmlspecialchars($url_tarea, ENT_QUOTES, 'UTF-8'); ?>"><?php } else { ?><div class="crm-dashboard-item"><?php } ?>
                                    <div class="crm-dashboard-item-main">
                                        <strong class="crm-dashboard-item-title"><?php echo htmlspecialchars($t['titulo']); ?></strong>
                                        <span class="crm-dashboard-item-status"><?php echo htmlspecialchars(dash_estado_txt($t['estado'])); ?></span>
                                    </div>
                                    <div class="crm-dashboard-item-meta"><span><i class="fa fa-calendar-times-o" aria-hidden="true"></i> Cierre <?php echo htmlspecialchars(date('d/m/Y', strtotime($t['fecha_cierre']))); ?></span></div>
                                    <?php if($puede_abrir_tarea) { ?><i class="fa fa-angle-right crm-dashboard-item-arrow" aria-hidden="true"></i><?php } ?>
                                <?php if($puede_abrir_tarea) { ?></a><?php } else { ?></div><?php } ?>
                                <?php } ?>
                                </div>
                                <p class="crm-dashboard-actions"><a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($link_tareas(array('estado'=>'Vencida'))); ?>">Ver vencidas</a></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="grid simple crm-dashboard-panel crm-dashboard-panel--progress">
                            <div class="grid-title"><h4><span class="crm-dashboard-panel-icon"><i class="fa fa-tasks"></i></span><span><?php echo ($alcance==='empleado') ? 'Mis tareas' : 'En proceso'; ?></span></h4><span class="crm-dashboard-panel-count"><?php echo count(($alcance==='empleado') ? $mias : $tareas_proceso); ?></span></div>
                            <div class="grid-body">
                                <?php
                                $lista_der=($alcance==='empleado') ? $mias : $tareas_proceso;
                                if(count($lista_der)==0) { ?><p class="muted crm-dashboard-empty"><i class="fa fa-info-circle" aria-hidden="true"></i><span>Nada en curso.</span></p><?php }
                                $n=0;
                                ?>
                                <div class="crm-dashboard-list">
                                <?php
                                foreach($lista_der as $t) {
                                	if($n>=10) break;
                                	$n++;
				$puede_abrir_tarea=($alcance==='empleado') || (function_exists('tarea_puede_ver') && tarea_puede_ver($t));
				$url_tarea=url_crm($t['area_clave'].'/ver-tarea.php?id='.intval($t['id_tarea']));
                                ?>
                                <?php if($puede_abrir_tarea) { ?><a class="crm-dashboard-item crm-dashboard-item-link" href="<?php echo htmlspecialchars($url_tarea, ENT_QUOTES, 'UTF-8'); ?>"><?php } else { ?><div class="crm-dashboard-item"><?php } ?>
                                    <div class="crm-dashboard-item-main">
                                        <strong class="crm-dashboard-item-title"><?php echo htmlspecialchars($t['titulo']); ?></strong>
                                        <span class="crm-dashboard-item-status"><?php echo htmlspecialchars(dash_estado_txt($t['estado'])); ?></span>
                                    </div>
                                    <div class="crm-dashboard-item-meta"><span><i class="fa fa-tasks" aria-hidden="true"></i> Avance <?php echo intval($t['avance']); ?>%</span><?php if(!empty($t['fecha_cierre'])) { ?><span><i class="fa fa-calendar-o" aria-hidden="true"></i> Vence <?php echo htmlspecialchars(date('d/m/Y', strtotime($t['fecha_cierre']))); ?></span><?php } ?></div>
                                    <?php if($puede_abrir_tarea) { ?><i class="fa fa-angle-right crm-dashboard-item-arrow" aria-hidden="true"></i><?php } ?>
                                <?php if($puede_abrir_tarea) { ?></a><?php } else { ?></div><?php } ?>
                                <?php } ?>
                                </div>
                                <p class="crm-dashboard-actions"><a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($link_tareas($alcance==='empleado' ? array() : array('estado'=>'En proceso'))); ?>"><?php echo ($alcance==='empleado') ? 'Ver mis tareas' : 'Ver en proceso'; ?></a></p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if(count($actividades_rel)>0) { ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="grid simple">
                            <div class="grid-title" style="display: flex; align-items: center; justify-content: space-between;">
                                <h4 style="margin: 0;"><i class="fa fa-calendar" style="color:#0090d9; margin-right:6px;"></i>Actividades vinculadas con <?php echo htmlspecialchars($nombre_vista); ?> (<?php echo count($actividades_rel); ?>)</h4>
                                <div class="actions" style="margin-left: auto;">
                                    <a class="btn btn-white btn-xs" style="height: auto !important; padding: 4px 10px !important; line-height: 1.4 !important; border: 1px solid #d5dbe0 !important; font-size: 11px !important; font-weight: 600 !important; color: #5e6871 !important; background: #fff !important; border-radius: 3px !important; display: inline-block !important;" href="<?php echo url_crm($area_act.'/actividades.php'); ?>">Ver todas</a>
                                </div>
                            </div>
                            <div class="grid-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-condensed" style="margin-bottom:0;">
                                        <thead>
                                            <tr>
                                                <th>Actividad</th>
                                                <th>Fecha</th>
                                                <th>Área organizadora</th>
                                                <th>Tipo</th>
                                                <th style="text-align:right;">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($actividades_rel as $ev_rel) { 
                                                $id_ev = intval($ev_rel['id_evento']);
                                                $f_ev = !empty($ev_rel['fecha']) ? date('d/m/Y', strtotime($ev_rel['fecha'])) : 'Sin fecha';
                                                $es_org = (isset($ev_rel['area_clave']) && $ev_rel['area_clave'] === $area_act);
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><a href="<?php echo url_crm($area_act.'/actividades.php?id='.$id_ev); ?>"><?php echo htmlspecialchars($ev_rel['titulo']); ?></a></strong>
                                                    <?php if(!$es_org) { ?>
                                                    <span class="badge badge-info" style="font-size:10px; margin-left:6px;">Participante</span>
                                                    <?php } ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($f_ev); ?></td>
                                                <td><?php echo htmlspecialchars(isset($etq[$ev_rel['area_clave']]) ? $etq[$ev_rel['area_clave']] : (isset($ev_rel['area_nombre']) ? $ev_rel['area_nombre'] : $ev_rel['area_clave'])); ?></td>
                                                <td><span class="label"><?php echo htmlspecialchars(ucfirst(isset($ev_rel['tipo']) ? $ev_rel['tipo'] : 'reunión')); ?></span></td>
                                                <td style="text-align:right;">
                                                    <a class="btn btn-white btn-xs" href="<?php echo url_crm($area_act.'/actividades.php?id='.$id_ev); ?>"><i class="fa fa-eye"></i> Ver</a>
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
                <?php } ?>

                <?php if(count($bajas_pend)>0) { ?>
                <div class="grid simple">
                    <div class="grid-title"><h4>Pedidos de eliminación para revisar (<?php echo count($bajas_pend); ?>)</h4></div>
                    <div class="grid-body">
                        <?php foreach($bajas_pend as $b) { ?>
                        <p>
                            <a href="<?php echo url_crm($b['area_clave'].'/ver-tarea.php?id='.intval($b['id_tarea'])); ?>"><?php echo htmlspecialchars($b['titulo']); ?></a>
                            · <?php echo htmlspecialchars($b['area_nombre']); ?>
                            · <?php echo $b['estado']=='pendiente_general' ? 'Aprobado por el área, falta tu OK' : 'Esperando al coordinador de área'; ?>
                            <br><span class="muted">Motivo: <?php echo htmlspecialchars($b['motivo']); ?></span>
                        </p>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>

            </div>
        </div>
 </div>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
<script>
(function() {
	function activateDashboardRow(event) {
		var link=event.target && event.target.closest ? event.target.closest('a.crm-dashboard-item-link') : null;
		if(!link) { return; }
		if(event.type==='keydown' && (event.code==='Space' || event.key===' ')) {
			event.preventDefault();
			return;
		}
		else if(event.type==='keyup' && (event.code==='Space' || event.key===' ')) {
			event.preventDefault();
			link.click();
		}
	}
	document.addEventListener('keydown', activateDashboardRow);
	document.addEventListener('keyup', activateDashboardRow);
})();
</script>
</body>
</html>
