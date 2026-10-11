<?php
// Tareas de Administracion.

require_once BASE_PATH.'/includes/tareas_repositorio.php';

function administracion_id_area()
{
	static $cache=null;
	if($cache===null) { $cache=repo_id_area_por_clave('administracion'); }
	return $cache;
}

function administracion_tipo_opciones()
{
	return repo_tareas_tipos_disponibles(administracion_id_area());
}

function administracion_tipo_cargar($nombre)
{
	return repo_tipo_cargar(administracion_id_area(), trim($nombre));
}

function tareas_tipos_de_area($area)
{
	return $area==='administracion' ? administracion_tipo_opciones() : array();
}

function tareas_puede_cargar_tipo()
{
	return true;
}

function tareas_fecha_cierre_obligatoria()
{
	return true;
}

function tareas_aplicar_carga_tipo($tarea=array())
{
	if(!isset($_POST['cargar_tipo'])) { return $tarea; }
	if(!is_array($tarea)) { $tarea=array(); }
	foreach(array('titulo','descripcion','tipo','prioridad','fecha_inicio','fecha_cierre','cliente_nombre') as $campo)
	{
		if(isset($_POST[$campo])) { $tarea[$campo]=$_POST[$campo]; }
	}
	if(isset($_POST['cliente_id'])) { $tarea['cliente_id']=intval($_POST['cliente_id']); }
	if(isset($_POST['responsables']) && is_array($_POST['responsables'])) { $tarea['responsables']=$_POST['responsables']; }
	$cargado=administracion_tipo_cargar(isset($_POST['tipo_nuevo']) ? $_POST['tipo_nuevo'] : '');
	if($cargado!=='') { $tarea['tipo']=$cargado; }
	return $tarea;
}

function administracion_tipo_id($nombre)
{
	$nombre=trim($nombre);
	if($nombre==='') { return 0; }
	global $con;
	$stmt=@mysqli_prepare($con, 'SELECT id_tipo FROM tipo_tarea WHERE id_area=? AND nombre=? LIMIT 1');
	if(!$stmt) { return 0; }
	$id_area=administracion_id_area();
	@mysqli_stmt_bind_param($stmt, 'is', $id_area, $nombre);
	@mysqli_stmt_execute($stmt);
	@mysqli_stmt_bind_result($stmt, $id_tipo);
	@mysqli_stmt_fetch($stmt);
	@mysqli_stmt_close($stmt);
	$id_tipo=intval($id_tipo);
	if($id_tipo<=0)
	{
		administracion_tipo_cargar($nombre);
		$stmt=@mysqli_prepare($con, 'SELECT id_tipo FROM tipo_tarea WHERE id_area=? AND nombre=? LIMIT 1');
		if($stmt)
		{
			$id_area=administracion_id_area();
			@mysqli_stmt_bind_param($stmt, 'is', $id_area, $nombre);
			@mysqli_stmt_execute($stmt);
			@mysqli_stmt_bind_result($stmt, $id_tipo);
			@mysqli_stmt_fetch($stmt);
			@mysqli_stmt_close($stmt);
		}
	}
	return intval($id_tipo);
}

function tareas_datos_guardar_nueva($area)
{
	$responsables=tareas_leer_responsables_post($area);
	$responsables_ids=array();
	foreach($responsables as $nombre)
	{
		$id=repo_nombre_a_id($nombre);
		if($id>0) { $responsables_ids[]=$id; }
	}
	if(count($responsables_ids)==0)
	{
		$id=repo_empleado_logueado();
		if($id>0) { $responsables_ids[]=$id; }
	}
	$cliente=tareas_leer_cliente_post();
	$datos=array(
		'titulo'=>isset($_POST['titulo']) ? trim($_POST['titulo']) : '',
		'descripcion'=>isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '',
		'id_area'=>administracion_id_area(),
		'id_tipo'=>administracion_tipo_id(isset($_POST['tipo']) ? $_POST['tipo'] : ''),
		'prioridad'=>isset($_POST['prioridad']) ? $_POST['prioridad'] : 'Media',
		'fecha_inicio'=>isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-d'),
		'fecha_cierre'=>isset($_POST['fecha_cierre']) ? $_POST['fecha_cierre'] : '',
		'id_cliente'=>isset($cliente['id']) ? intval($cliente['id']) : 0
	);
	$nuevo_id=repo_tarea_crear($datos, $responsables_ids, repo_empleado_logueado());
	if($nuevo_id>0 && function_exists('tareas_avisos_registrar'))
	{
		tareas_avisos_registrar($responsables, $datos['titulo'], $nuevo_id, $area);
	}
	return $area;
}

function tareas_datos_actualizar($id)
{
	$tarea=repo_tarea_por_id(intval($id), tareas_rol(), repo_empleado_logueado());
	if(!$tarea || !tarea_puede_editar($tarea)) { return false; }
	$responsables=tareas_leer_responsables_post('administracion', $tarea);
	$responsables_ids=array();
	foreach($responsables as $nombre)
	{
		$empleado=repo_nombre_a_id($nombre);
		if($empleado>0) { $responsables_ids[]=$empleado; }
	}
	$cliente=tareas_leer_cliente_post();
	$datos=array(
		'titulo'=>isset($_POST['titulo']) ? trim($_POST['titulo']) : $tarea['titulo'],
		'descripcion'=>isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '',
		'id_area'=>administracion_id_area(),
		'id_tipo'=>administracion_tipo_id(isset($_POST['tipo']) ? $_POST['tipo'] : $tarea['tipo']),
		'prioridad'=>isset($_POST['prioridad']) ? $_POST['prioridad'] : 'Media',
		'fecha_inicio'=>isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '',
		'fecha_cierre'=>isset($_POST['fecha_cierre']) ? $_POST['fecha_cierre'] : '',
		'id_cliente'=>isset($cliente['id']) ? intval($cliente['id']) : 0
	);
	return repo_tarea_actualizar(intval($id), $datos, $responsables_ids);
}

function tareas_datos_guardar_extras($id)
{
	$tarea=repo_tarea_por_id(intval($id), tareas_rol(), repo_empleado_logueado());
	if(!$tarea || !tarea_puede_agregar_extras($tarea)) { return false; }
	if(isset($_POST['agregar_enlace']))
	{
		$url=trim(isset($_POST['enlace_nuevo']) ? $_POST['enlace_nuevo'] : '');
		if($url!=='') { repo_tarea_agregar_enlace($id, $url); }
	}
	if(isset($_POST['agregar_comentario']))
	{
		$texto=trim(isset($_POST['comentario_nuevo']) ? $_POST['comentario_nuevo'] : '');
		if($texto!=='') { repo_tarea_agregar_comentario($id, repo_empleado_logueado(), $texto); }
	}
	return true;
}

function tareas_datos_procesar_accion()
{
	if(isset($_POST['id_tarea']) && (isset($_POST['agregar_comentario']) || isset($_POST['agregar_enlace'])))
	{
		return tareas_datos_guardar_extras(intval($_POST['id_tarea']));
	}
	if(!isset($_POST['accion_tarea']) || !isset($_POST['id_tarea'])) { return false; }
	$mapa=array('iniciar'=>'en_proceso','pausar'=>'pausada','reanudar'=>'en_proceso','terminar'=>'terminada');
	$accion=$_POST['accion_tarea'];
	if(!isset($mapa[$accion])) { return false; }
	$tarea=repo_tarea_por_id(intval($_POST['id_tarea']), tareas_rol(), repo_empleado_logueado());
	if(!$tarea || !tarea_puede_cambiar_estado($tarea)) { return false; }
	return repo_tarea_cambiar_estado(intval($_POST['id_tarea']), $mapa[$accion], repo_empleado_logueado());
}

function tareas_ejemplo()
{
	return array();
}

function actividades_ejemplo()
{
	return array();
}

// Quien entro: el admin ve todo; el coordinador ve las del equipo; el empleado, solo las suyas.
function tareas_iniciar_contexto()
{
	if(function_exists('acceso_area') && acceso_area()=='todas')
	{
		$_SESSION['tareas_rol']='coordinador';
	}
	else if(isset($_SESSION['rol_area']) && $_SESSION['rol_area']!='')
	{
		$_SESSION['tareas_rol']=$_SESSION['rol_area'];
	}
	if(isset($_SESSION['persona_tareas']) && $_SESSION['persona_tareas']!='')
	{
		$_SESSION['tareas_persona']=$_SESSION['persona_tareas'];
	}
	if(isset($_SESSION['tareas_rol']) && $_SESSION['tareas_rol']=='trabajador')
	{
		$_SESSION['tareas_rol']='empleado';
	}
	if(isset($_SESSION['tareas_rol']) && ($_SESSION['tareas_rol']=='coordinador_general' || $_SESSION['tareas_rol']=='admin'))
	{
		$_SESSION['tareas_rol']='coordinador';
	}
	if(!isset($_SESSION['tareas_rol']))
	{
		$_SESSION['tareas_rol']='coordinador';
	}
	if(!isset($_SESSION['tareas_persona']) || $_SESSION['tareas_persona']=='')
	{
		$_SESSION['tareas_persona']=repo_nombre_logueado();
	}
	unset($_SESSION['administracion_tareas_demo'], $_SESSION['administracion_tareas_actividad'], $_SESSION['administracion_tareas_demo_v']);
}

function tareas_rol()
{
	return isset($_SESSION['tareas_rol']) ? $_SESSION['tareas_rol'] : 'coordinador';
}

function tareas_persona()
{
	return isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : repo_nombre_logueado();
}

// Lee las tareas que se van a mostrar. Salen de la sesion.
function tareas_lista()
{
	return repo_tareas_listar(administracion_id_area(), array(), tareas_rol(), repo_empleado_logueado());
}

// Guarda el alta o la edicion. Se guarda en la sesion.
function tareas_guardar($lista)
{
	// El repositorio guarda las tareas individualmente.
}

function actividades_lista()
{
	return repo_tarea_actividad(administracion_id_area());
}

// El coordinador ve las suyas y las del equipo.
// El empleado ve solo las suyas.
function tareas_visibles()
{
	return tareas_lista();
}

// Una tarea esta vencida si no esta terminada y la fecha de cierre ya paso.
function tarea_esta_vencida($tarea)
{
	if($tarea['estado']=='Terminada')
	{
		return false;
	}
	if($tarea['fecha_cierre']=='')
	{
		return false;
	}
	return (strtotime($tarea['fecha_cierre']) < strtotime(date('Y-m-d')));
}

function tarea_por_id($id)
{
	return repo_tarea_por_id(intval($id), tareas_rol(), repo_empleado_logueado());
}

function tareas_siguiente_id()
{
	$max=0;
	foreach(tareas_lista() as $tarea)
	{
		if($tarea['id']>$max)
		{
			$max=$tarea['id'];
		}
	}
	return $max+1;
}

// Aplica los filtros de la pantalla (mes, area, prioridad, tipo, responsable, estado).
function tareas_filtradas()
{
	$filtros=array();
	foreach(array('mes','area','prioridad','tipo','responsable','estado','cliente') as $campo)
	{
		if(isset($_GET[$campo]) && $_GET[$campo]!=='') { $filtros[$campo]=$_GET[$campo]; }
	}
	return repo_tareas_listar(administracion_id_area(), $filtros, tareas_rol(), repo_empleado_logueado());
}

// Numeros de las tarjetas de arriba. Se calculan sobre lo que la persona puede ver.
function tareas_contadores($lista)
{
	return repo_tareas_contadores(administracion_id_area(), tareas_rol(), repo_empleado_logueado());
}

function fecha_mostrar($fecha)
{
	if($fecha=='')
	{
		return '';
	}
	return date('d/m/Y', strtotime($fecha));
}

function texto_mostrar($texto)
{
	return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

// Arma el enlace sumando filtros u otros datos. El rol ya viene de la sesion.
function tareas_link($pagina, $extra=array())
{
	$datos=$extra;
	foreach($datos as $clave=>$valor)
	{
		if($valor==='' || $valor===null)
		{
			unset($datos[$clave]);
		}
	}
	$q=http_build_query($datos);
	return $pagina.($q!='' ? ('?'.$q) : '');
}

// Enlace de filtros: se quedan los que ya estan puestos y se cambia uno.
function tareas_link_filtro($cambios)
{
	$actual=array(
		'mes'=>isset($_GET['mes']) ? $_GET['mes'] : '',
		'area'=>isset($_GET['area']) ? $_GET['area'] : '',
		'prioridad'=>isset($_GET['prioridad']) ? $_GET['prioridad'] : '',
		'tipo'=>isset($_GET['tipo']) ? $_GET['tipo'] : '',
		'responsable'=>isset($_GET['responsable']) ? $_GET['responsable'] : '',
		'estado'=>isset($_GET['estado']) ? $_GET['estado'] : '',
		'cliente'=>isset($_GET['cliente']) ? $_GET['cliente'] : '',
		'orden'=>isset($_GET['orden']) ? $_GET['orden'] : '',
		'cal'=>isset($_GET['cal']) ? $_GET['cal'] : ''
	);
	return tareas_link('tareas.php', array_merge($actual, $cambios));
}

function mes_nombre($numero)
{
	$nombres=array(1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre');
	$n=intval($numero);
	return isset($nombres[$n]) ? $nombres[$n] : '';
}

// Meses que aparecen en las tareas, para el filtro.
function tareas_meses_disponibles()
{
	return repo_tareas_meses_disponibles(administracion_id_area());
}

function tareas_valores_unicos($campo)
{
	if($campo=='responsable') { return repo_tareas_responsables_disponibles(administracion_id_area()); }
	if($campo=='tipo') { return repo_tareas_tipos_disponibles(administracion_id_area()); }
	if($campo=='prioridad') { return array('Urgente','Alta','Media','Baja'); }
	if($campo=='area') { return array(acceso_etiqueta_corta_area('administracion')); }
	$valores=array();
	foreach(tareas_visibles() as $tarea)
	{
		$lista=($campo=='responsable') ? tarea_lista_responsables($tarea) : array(isset($tarea[$campo]) ? $tarea[$campo] : '');
		foreach($lista as $valor)
		{
			if($valor!='' && !in_array($valor, $valores))
			{
				$valores[]=$valor;
			}
		}
	}
	sort($valores);
	return $valores;
}

function label_prioridad($prioridad)
{
	if($prioridad=='Urgente') { return 'label-important'; }
	if($prioridad=='Alta' || $prioridad=='Media') { return 'label-warning'; }
	return 'label-success';
}

function label_estado($estado)
{
	if($estado=='Terminada') { return 'label-success'; }
	if($estado=='En proceso') { return 'label-info'; }
	if($estado=='Pendiente' || $estado=='No iniciada') { return 'label-warning'; }
	if($estado=='Pausada') { return 'label-inverse'; }
	return 'label-important';
}

function icono_actividad($tipo)
{
	if($tipo=='completada') { return 'fa-check-circle text-success'; }
	if($tipo=='proceso') { return 'fa-clock-o text-warning'; }
	if($tipo=='creada') { return 'fa-plus-circle text-info'; }
	return 'fa-exclamation-circle text-danger';
}

// Dias del mes que tienen alguna tarea (para marcarlos en el calendario).
function calendario_dias_con_tareas($anio, $mes, $lista)
{
	return repo_calendario_dias_con_tareas($anio, $mes, administracion_id_area(), tareas_rol(), repo_empleado_logueado());
}
?>
