<?php
// Capa de datos de tareas de Recursos Humanos. Los datos se guardan en MySQL.
require_once BASE_PATH.'/includes/tareas_repositorio.php';

function rrhh_id_area()
{
	static $id=null;
	if($id===null) { $id=repo_id_area_por_clave('rrhh'); }
	return $id;
}

function tareas_iniciar_contexto()
{
	if(function_exists('acceso_area') && acceso_area()=='todas') { $_SESSION['tareas_rol']='coordinador'; }
	else if(isset($_SESSION['rol_area']) && $_SESSION['rol_area']!='') { $_SESSION['tareas_rol']=$_SESSION['rol_area']; }
	if(isset($_SESSION['persona_tareas']) && $_SESSION['persona_tareas']!='') { $_SESSION['tareas_persona']=$_SESSION['persona_tareas']; }
	if(isset($_SESSION['tareas_rol']) && $_SESSION['tareas_rol']=='trabajador') { $_SESSION['tareas_rol']='empleado'; }
	if(isset($_SESSION['tareas_rol']) && ($_SESSION['tareas_rol']=='coordinador_general' || $_SESSION['tareas_rol']=='admin')) { $_SESSION['tareas_rol']='coordinador'; }
	if(!isset($_SESSION['tareas_rol'])) { $_SESSION['tareas_rol']='coordinador'; }
	if(!isset($_SESSION['tareas_persona']) || $_SESSION['tareas_persona']=='') { $_SESSION['tareas_persona']=repo_nombre_logueado(); }
	unset($_SESSION['rrhh_tareas_demo'], $_SESSION['rrhh_tareas_actividad'], $_SESSION['rrhh_tareas_demo_v']);
}

function tareas_rol() { return isset($_SESSION['tareas_rol']) ? $_SESSION['tareas_rol'] : 'coordinador'; }
function tareas_persona() { return isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : repo_nombre_logueado(); }
function tareas_tipos_de_area($area) { return $area=='rrhh' ? repo_tareas_tipos_disponibles(rrhh_id_area()) : array(); }
function tareas_puede_cargar_tipo() { return true; }
function tareas_fecha_cierre_obligatoria() { return true; }

function rrhh_tarea_fecha_inicio_alta_valida($fecha)
{
	$fecha=trim((string)$fecha);
	$error='';
	if($fecha==='')
	{
		$error='La fecha de inicio es obligatoria.';
	}
	else if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes) || !checkdate(intval($partes[2]), intval($partes[3]), intval($partes[1])))
	{
		$error='Ingresá una fecha de inicio válida.';
	}
	else if($fecha<date('Y-m-d'))
	{
		$error='La fecha de inicio no puede ser anterior a la fecha actual.';
	}

	if($error!=='')
	{
		$GLOBALS['rrhh_tarea_error_fecha_inicio']=$error;
		if(function_exists('tareas_mensaje_form_set')) { tareas_mensaje_form_set($error); }
		return false;
	}
	return true;
}

function tareas_aplicar_carga_tipo($tarea)
{
	if(!isset($_POST['cargar_tipo'])) { return $tarea; }
	if(!is_array($tarea)) { $tarea=array(); }
	foreach(array('titulo','descripcion','tipo','prioridad','fecha_inicio','fecha_cierre','cliente_nombre') as $campo) { if(isset($_POST[$campo])) { $tarea[$campo]=$_POST[$campo]; } }
	if(isset($_POST['cliente_id'])) { $tarea['cliente_id']=intval($_POST['cliente_id']); }
	if(isset($_POST['responsables']) && is_array($_POST['responsables'])) { $tarea['responsables']=$_POST['responsables']; }
	$tipo=trim(isset($_POST['tipo_nuevo']) ? $_POST['tipo_nuevo'] : '');
	if($tipo!='' && strlen($tipo)<=80)
	{
		if(!in_array($tipo, tareas_tipos_de_area('rrhh'))) { acceso_ejecutar("INSERT INTO tipo_tarea (id_area, nombre) VALUES (?,?)", 'is', array(rrhh_id_area(), $tipo)); }
		$tarea['tipo']=$tipo;
	}
	return $tarea;
}

function rrhh_tipo_id($nombre)
{
	$row=acceso_consulta_una("SELECT id_tipo FROM tipo_tarea WHERE id_area=? AND nombre=? LIMIT 1", 'is', array(rrhh_id_area(), trim($nombre)));
	return $row ? intval($row['id_tipo']) : 0;
}

function tareas_datos_guardar_nueva($area)
{
	$area='rrhh';
	$fecha_inicio=isset($_POST['fecha_inicio']) ? trim((string)$_POST['fecha_inicio']) : '';
	if(!rrhh_tarea_fecha_inicio_alta_valida($fecha_inicio)) { return false; }
	$responsables=tareas_leer_responsables_post($area);
	$responsables_ids=array();
	foreach($responsables as $nombre) { $id=repo_nombre_a_id($nombre); if($id>0) { $responsables_ids[]=$id; } }
	if(count($responsables_ids)==0 && repo_empleado_logueado()>0) { $responsables_ids[]=repo_empleado_logueado(); }
	$cliente=tareas_leer_cliente_post();
	$tipo=isset($_POST['tipo']) ? $_POST['tipo'] : '';
	$fecha_cierre=isset($_POST['fecha_cierre']) ? trim($_POST['fecha_cierre']) : '';
	$id_tipo=rrhh_tipo_id($tipo);
	if($id_tipo<=0 || $fecha_cierre=='')
	{
		tareas_mensaje_form_set($id_tipo<=0 ? 'Seleccioná un objetivo válido para la tarea.' : 'Indicá la fecha estimada de finalización.');
		return false;
	}
	$datos=array('titulo'=>isset($_POST['titulo']) ? trim($_POST['titulo']) : '', 'descripcion'=>isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '', 'id_area'=>rrhh_id_area(), 'id_tipo'=>$id_tipo, 'prioridad'=>isset($_POST['prioridad']) ? $_POST['prioridad'] : 'Media', 'fecha_inicio'=>$fecha_inicio, 'fecha_cierre'=>$fecha_cierre, 'id_cliente'=>intval($cliente['id']));
	$id=repo_tarea_crear($datos, $responsables_ids, repo_empleado_logueado());
	if($id>0 && function_exists('tareas_avisos_registrar')) { tareas_avisos_registrar($responsables, $datos['titulo'], $id, $area); }
	if($id<=0)
	{
		if(!isset($_SESSION['msgtarea_form']) || $_SESSION['msgtarea_form']==='') { tareas_mensaje_form_set('No se pudo guardar la tarea. Revisá los datos e intentá de nuevo.'); }
		return false;
	}
	return $area;
}

function tareas_datos_actualizar($id)
{
	$tarea=repo_tarea_por_id($id, tareas_rol(), repo_empleado_logueado());
	if(!$tarea || !tarea_puede_editar($tarea)) { return false; }
	$responsables=tareas_leer_responsables_post('rrhh', $tarea);
	$responsables_ids=array();
	foreach($responsables as $nombre) { $empleado=repo_nombre_a_id($nombre); if($empleado>0) { $responsables_ids[]=$empleado; } }
	$cliente=tareas_leer_cliente_post();
	$tipo=isset($_POST['tipo']) ? $_POST['tipo'] : $tarea['tipo'];
	$id_tipo=rrhh_tipo_id($tipo);
	$fecha_cierre=isset($_POST['fecha_cierre']) ? trim($_POST['fecha_cierre']) : '';
	if($id_tipo<=0 || $fecha_cierre=='') { return false; }
	$datos=array('titulo'=>isset($_POST['titulo']) ? trim($_POST['titulo']) : $tarea['titulo'], 'descripcion'=>isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '', 'id_area'=>rrhh_id_area(), 'id_tipo'=>$id_tipo, 'prioridad'=>isset($_POST['prioridad']) ? $_POST['prioridad'] : 'Media', 'fecha_inicio'=>isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '', 'fecha_cierre'=>$fecha_cierre, 'id_cliente'=>intval($cliente['id']));
	return repo_tarea_actualizar($id, $datos, $responsables_ids);
}

function tareas_datos_guardar_extras($id)
{
	$tarea=repo_tarea_por_id($id, tareas_rol(), repo_empleado_logueado());
	if(!$tarea || !tarea_puede_agregar_extras($tarea)) { return false; }
	if(isset($_POST['agregar_enlace'])) { $url=trim(isset($_POST['enlace_nuevo']) ? $_POST['enlace_nuevo'] : ''); if($url!='') { repo_tarea_agregar_enlace($id, $url); } }
	if(isset($_POST['agregar_comentario'])) { $texto=trim(isset($_POST['comentario_nuevo']) ? $_POST['comentario_nuevo'] : ''); if($texto!='' && repo_empleado_logueado()>0) { repo_tarea_agregar_comentario($id, repo_empleado_logueado(), $texto); } }
	return true;
}

function tareas_datos_procesar_accion()
{
	if(isset($_POST['id_tarea']) && (isset($_POST['agregar_comentario']) || isset($_POST['agregar_enlace']))) { return tareas_datos_guardar_extras(intval($_POST['id_tarea'])); }
	if(!isset($_POST['accion_tarea']) || !isset($_POST['id_tarea'])) { return false; }
	$tarea=repo_tarea_por_id(intval($_POST['id_tarea']), tareas_rol(), repo_empleado_logueado());
	if(!$tarea || !tarea_puede_cambiar_estado($tarea)) { return false; }
	$map=array('iniciar'=>'en_proceso','pausar'=>'pausada','reanudar'=>'en_proceso','terminar'=>'terminada');
	$accion=$_POST['accion_tarea'];
	return isset($map[$accion]) ? repo_tarea_cambiar_estado($tarea['id'], $map[$accion], repo_empleado_logueado()) : false;
}

function tareas_lista()
{
	// RRHH también opera recepción: listar ambas áreas.
	$id_rrhh=rrhh_id_area();
	$id_rec=acceso_id_por_clave('area', 'recepcion');
	$rol=tareas_rol();
	$yo=repo_empleado_logueado();
	$a=repo_tareas_listar($id_rrhh, array(), $rol, $yo);
	if($id_rec>0 && $id_rec!=$id_rrhh && (acceso_es_coord_rrhh() || acceso_es_general() || acceso_es_admin_tareas()))
	{
		$b=repo_tareas_listar($id_rec, array(), $rol, $yo);
		$vistos=array();
		$out=array();
		foreach(array_merge($a, $b) as $t)
		{
			$id=isset($t['id']) ? intval($t['id']) : (isset($t['id_tarea']) ? intval($t['id_tarea']) : 0);
			if($id>0 && isset($vistos[$id])) { continue; }
			if($id>0) { $vistos[$id]=1; }
			$out[]=$t;
		}
		return $out;
	}
	return $a;
}
function tareas_visibles() { return tareas_lista(); }
function tareas_filtradas()
{
	$filtros=array();
	foreach(array('mes','area','prioridad','tipo','responsable','estado','cliente') as $filtro) { if(isset($_GET[$filtro]) && $_GET[$filtro]!='') { $filtros[$filtro]=$_GET[$filtro]; } }
	$id_rrhh=rrhh_id_area();
	$id_rec=acceso_id_por_clave('area', 'recepcion');
	$rol=tareas_rol();
	$yo=repo_empleado_logueado();
	$filtro_area=isset($filtros['area']) ? $filtros['area'] : '';
	unset($filtros['area']);
	if($filtro_area==='recepcion' || $filtro_area==='Recepción')
	{
		return repo_tareas_listar($id_rec, $filtros, $rol, $yo);
	}
	if($filtro_area!=='' && ($filtro_area==='rrhh' || $filtro_area==='Recursos humanos' || $filtro_area===acceso_etiqueta_corta_area('rrhh')))
	{
		return repo_tareas_listar($id_rrhh, $filtros, $rol, $yo);
	}
	$a=repo_tareas_listar($id_rrhh, $filtros, $rol, $yo);
	if($id_rec>0 && $id_rec!=$id_rrhh && (acceso_es_coord_rrhh() || acceso_es_general() || acceso_es_admin_tareas()))
	{
		$b=repo_tareas_listar($id_rec, $filtros, $rol, $yo);
		$vistos=array();
		$out=array();
		foreach(array_merge($a, $b) as $t)
		{
			$id=isset($t['id']) ? intval($t['id']) : (isset($t['id_tarea']) ? intval($t['id_tarea']) : 0);
			if($id>0 && isset($vistos[$id])) { continue; }
			if($id>0) { $vistos[$id]=1; }
			$out[]=$t;
		}
		return $out;
	}
	return $a;
}
function tareas_contadores($lista=null) {
	if(is_array($lista)) { return function_exists('crm_tareas_contar') ? crm_tareas_contar($lista) : repo_tareas_contadores(rrhh_id_area(), tareas_rol(), repo_empleado_logueado()); }
	return repo_tareas_contadores(rrhh_id_area(), tareas_rol(), repo_empleado_logueado());
}
function tarea_por_id($id) { return repo_tarea_por_id(intval($id), tareas_rol(), repo_empleado_logueado()); }
function tareas_siguiente_id() { return 0; }
function fecha_mostrar($fecha) { return $fecha=='' ? '' : date('d/m/Y', strtotime($fecha)); }
function texto_mostrar($texto) { return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8'); }

function tareas_link($pagina, $extra=array())
{
	foreach($extra as $clave=>$valor) { if($valor==='' || $valor===null) { unset($extra[$clave]); } }
	$q=http_build_query($extra);
	return $pagina.($q!='' ? '?'.$q : '');
}
function tareas_link_filtro($cambios)
{
	$actual=array();
	foreach(array('mes','area','prioridad','tipo','responsable','estado','cliente','orden','cal') as $clave) { $actual[$clave]=isset($_GET[$clave]) ? $_GET[$clave] : ''; }
	return tareas_link('tareas.php', array_merge($actual, $cambios));
}
function mes_nombre($numero)
{
	$nombres=array(1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre');
	return isset($nombres[intval($numero)]) ? $nombres[intval($numero)] : '';
}
function tareas_meses_disponibles() { return repo_tareas_meses_disponibles(rrhh_id_area()); }
function tareas_valores_unicos($campo)
{
	if($campo=='responsable') { return repo_tareas_responsables_disponibles(rrhh_id_area()); }
	if($campo=='tipo') { return repo_tareas_tipos_disponibles(rrhh_id_area()); }
	if($campo=='prioridad') { return array('Urgente','Alta','Media','Baja'); }
	if($campo=='area') { return array(acceso_etiqueta_corta_area('rrhh'), acceso_etiqueta_corta_area('recepcion')); }
	return array();
}
function label_prioridad($prioridad) { return $prioridad=='Urgente' ? 'label-important' : (($prioridad=='Alta' || $prioridad=='Media') ? 'label-warning' : 'label-success'); }
function label_estado($estado) { return $estado=='Terminada' ? 'label-success' : ($estado=='En proceso' ? 'label-info' : (($estado=='Pendiente' || $estado=='No iniciada') ? 'label-warning' : ($estado=='Pausada' ? 'label-inverse' : 'label-important'))); }
function icono_actividad($tipo) { return $tipo=='completada' ? 'fa-check-circle text-success' : ($tipo=='proceso' ? 'fa-clock-o text-warning' : ($tipo=='creada' ? 'fa-plus-circle text-info' : 'fa-exclamation-circle text-danger')); }
function tarea_esta_vencida($tarea) { return is_array($tarea) && $tarea['estado']!='Terminada' && $tarea['fecha_cierre']!='' && strtotime($tarea['fecha_cierre'])<strtotime(date('Y-m-d')); }
function calendario_dias_con_tareas($anio, $mes, $lista=null) { return repo_calendario_dias_con_tareas($anio, $mes, rrhh_id_area(), tareas_rol(), repo_empleado_logueado()); }
function actividades_lista() { return repo_tarea_actividad(rrhh_id_area()); }
function tareas_ejemplo() { return array(); }
function actividades_ejemplo() { return array(); }
function tareas_guardar($lista) { }
?>
