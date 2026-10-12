<?php
// Tareas de Coordinación de vinculaciones: datos reales en MySQL.
require_once BASE_PATH.'/includes/tareas_repositorio.php';

function vinculaciones_id_area()
{
	static $id=null;
	if($id===null)
	{
		$id=repo_id_area_por_clave('vinculaciones');
	}
	return $id;
}

function tareas_ejemplo()
{
	return array();
}

function actividades_ejemplo()
{
	return array();
}

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
	unset($_SESSION['vin_tareas_demo'], $_SESSION['vin_tareas_actividad'], $_SESSION['vin_tareas_demo_v']);
}

function tareas_rol()
{
	return isset($_SESSION['tareas_rol']) ? $_SESSION['tareas_rol'] : 'coordinador';
}

function tareas_persona()
{
	return isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : repo_nombre_logueado();
}

function tareas_tipos_de_area($area)
{
	return $area=='vinculaciones' ? repo_tareas_tipos_disponibles(vinculaciones_id_area()) : array();
}

function vinculaciones_tipo_cargar($nombre)
{
	return repo_tipo_cargar(vinculaciones_id_area(), $nombre);
}

function tareas_puede_cargar_tipo()
{
	return true;
}

function tareas_fecha_cierre_obligatoria()
{
	return true;
}

function tareas_aplicar_carga_tipo($tarea)
{
	if(!isset($_POST['cargar_tipo']))
	{
		return $tarea;
	}
	if(!is_array($tarea))
	{
		$tarea=array();
	}
	foreach(array('titulo','descripcion','tipo','prioridad','fecha_inicio','fecha_cierre','cliente_nombre') as $campo)
	{
		if(isset($_POST[$campo]))
		{
			$tarea[$campo]=$_POST[$campo];
		}
	}
	if(isset($_POST['cliente_id']))
	{
		$tarea['cliente_id']=intval($_POST['cliente_id']);
	}
	if(isset($_POST['responsables']) && is_array($_POST['responsables']))
	{
		$tarea['responsables']=$_POST['responsables'];
	}
	$tipo=trim(isset($_POST['tipo_nuevo']) ? $_POST['tipo_nuevo'] : '');
	if($tipo!='' && strlen($tipo)<=80)
	{
		if(!in_array($tipo, tareas_tipos_de_area('vinculaciones')))
		{
			vinculaciones_tipo_cargar($tipo);
		}
		$tarea['tipo']=$tipo;
	}
	return $tarea;
}

function tareas_datos_guardar_nueva($area)
{
	$area='vinculaciones';
	$id_area=vinculaciones_id_area();
	if($id_area<=0)
	{
		return $area;
	}
	$responsables=tareas_leer_responsables_post($area);
	$responsables_ids=array();
	foreach($responsables as $nombre)
	{
		$id=repo_nombre_a_id($nombre);
		if($id>0)
		{
			$responsables_ids[]=$id;
		}
	}
	if(count($responsables_ids)==0 && repo_empleado_logueado()>0)
	{
		$responsables_ids[]=repo_empleado_logueado();
	}
	$cliente=tareas_leer_cliente_post();
	$tipo=isset($_POST['tipo']) ? $_POST['tipo'] : '';
	$fecha_cierre=isset($_POST['fecha_cierre']) ? trim($_POST['fecha_cierre']) : '';
	$id_tipo=0;
	if($tipo!='')
	{
		$row=acceso_consulta_una("SELECT id_tipo FROM tipo_tarea WHERE id_area=? AND nombre=? LIMIT 1", 'is', array($id_area, $tipo));
		if($row)
		{
			$id_tipo=intval($row['id_tipo']);
		}
	}
	if($id_tipo<=0 || $fecha_cierre=='')
	{
		return $area;
	}
	$datos=array(
		'titulo'=>isset($_POST['titulo']) ? trim($_POST['titulo']) : '',
		'descripcion'=>isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '',
		'id_area'=>$id_area,
		'id_tipo'=>$id_tipo,
		'prioridad'=>isset($_POST['prioridad']) ? $_POST['prioridad'] : 'Media',
		'fecha_inicio'=>isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-d'),
		'fecha_cierre'=>$fecha_cierre,
		'id_cliente'=>intval($cliente['id'])
	);
	$id=repo_tarea_crear($datos, $responsables_ids, repo_empleado_logueado());
	if($id>0 && function_exists('tareas_avisos_registrar'))
	{
		tareas_avisos_registrar($responsables, $datos['titulo'], $id, $area);
	}
	return $area;
}

function tareas_datos_actualizar($id)
{
	$id=intval($id);
	$tarea=repo_tarea_por_id($id, tareas_rol(), repo_empleado_logueado());
	if(!$tarea || !tarea_puede_editar($tarea))
	{
		return false;
	}
	$responsables=tareas_leer_responsables_post('vinculaciones', $tarea);
	$responsables_ids=array();
	foreach($responsables as $nombre)
	{
		$empleado=repo_nombre_a_id($nombre);
		if($empleado>0)
		{
			$responsables_ids[]=$empleado;
		}
	}
	$cliente=tareas_leer_cliente_post();
	$tipo=isset($_POST['tipo']) ? $_POST['tipo'] : $tarea['tipo'];
	$id_tipo=0;
	if($tipo!='')
	{
		$row=acceso_consulta_una("SELECT id_tipo FROM tipo_tarea WHERE id_area=? AND nombre=? LIMIT 1", 'is', array(vinculaciones_id_area(), $tipo));
		if($row)
		{
			$id_tipo=intval($row['id_tipo']);
		}
	}
	$fecha_cierre=isset($_POST['fecha_cierre']) ? trim($_POST['fecha_cierre']) : '';
	if($id_tipo<=0 || $fecha_cierre=='')
	{
		return false;
	}
	$datos=array(
		'titulo'=>isset($_POST['titulo']) ? trim($_POST['titulo']) : $tarea['titulo'],
		'descripcion'=>isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '',
		'id_area'=>vinculaciones_id_area(),
		'id_tipo'=>$id_tipo,
		'prioridad'=>isset($_POST['prioridad']) ? $_POST['prioridad'] : 'Media',
		'fecha_inicio'=>isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '',
		'fecha_cierre'=>$fecha_cierre,
		'id_cliente'=>intval($cliente['id'])
	);
	return repo_tarea_actualizar($id, $datos, $responsables_ids);
}

function tareas_datos_guardar_extras($id)
{
	$id=intval($id);
	$tarea=repo_tarea_por_id($id, tareas_rol(), repo_empleado_logueado());
	if(!$tarea || !tarea_puede_agregar_extras($tarea))
	{
		return false;
	}
	if(isset($_POST['agregar_enlace']))
	{
		$url=trim(isset($_POST['enlace_nuevo']) ? $_POST['enlace_nuevo'] : '');
		if($url!='')
		{
			repo_tarea_agregar_enlace($id, $url);
		}
	}
	if(isset($_POST['agregar_comentario']))
	{
		$texto=trim(isset($_POST['comentario_nuevo']) ? $_POST['comentario_nuevo'] : '');
		if($texto!='' && repo_empleado_logueado()>0)
		{
			repo_tarea_agregar_comentario($id, repo_empleado_logueado(), $texto);
		}
	}
	return true;
}

function tareas_datos_procesar_accion()
{
	if(isset($_POST['id_tarea']) && (isset($_POST['agregar_comentario']) || isset($_POST['agregar_enlace'])))
	{
		return tareas_datos_guardar_extras(intval($_POST['id_tarea']));
	}
	if(!isset($_POST['accion_tarea']) || !isset($_POST['id_tarea']))
	{
		return false;
	}
	$tarea=repo_tarea_por_id(intval($_POST['id_tarea']), tareas_rol(), repo_empleado_logueado());
	if(!$tarea || !tarea_puede_cambiar_estado($tarea))
	{
		return false;
	}
	$map=array('iniciar'=>'en_proceso','pausar'=>'pausada','reanudar'=>'en_proceso','terminar'=>'terminada');
	$accion=$_POST['accion_tarea'];
	return isset($map[$accion]) ? repo_tarea_cambiar_estado($tarea['id'], $map[$accion], repo_empleado_logueado()) : false;
}

function tareas_lista()
{
	return repo_tareas_listar(vinculaciones_id_area(), array(), tareas_rol(), repo_empleado_logueado());
}

function tareas_visibles()
{
	return tareas_lista();
}

function tareas_filtradas()
{
	$filtros=array();
	foreach(array('mes','area','prioridad','tipo','responsable','estado','cliente') as $filtro)
	{
		if(isset($_GET[$filtro]) && $_GET[$filtro]!='')
		{
			$filtros[$filtro]=$_GET[$filtro];
		}
	}
	return repo_tareas_listar(vinculaciones_id_area(), $filtros, tareas_rol(), repo_empleado_logueado());
}

function tareas_contadores($lista=null)
{
	return repo_tareas_contadores(vinculaciones_id_area(), tareas_rol(), repo_empleado_logueado());
}

function tarea_por_id($id)
{
	return repo_tarea_por_id(intval($id), tareas_rol(), repo_empleado_logueado());
}

function tareas_siguiente_id()
{
	return 0;
}

function fecha_mostrar($fecha)
{
	return $fecha=='' ? '' : date('d/m/Y', strtotime($fecha));
}

function texto_mostrar($texto)
{
	return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function tareas_link($pagina, $extra=array())
{
	foreach($extra as $clave=>$valor)
	{
		if($valor==='' || $valor===null)
		{
			unset($extra[$clave]);
		}
	}
	$q=http_build_query($extra);
	return $pagina.($q!='' ? '?'.$q : '');
}

function tareas_link_filtro($cambios)
{
	$actual=array();
	foreach(array('mes','area','prioridad','tipo','responsable','estado','cliente','orden','cal') as $clave)
	{
		$actual[$clave]=isset($_GET[$clave]) ? $_GET[$clave] : '';
	}
	return tareas_link('tareas.php', array_merge($actual, $cambios));
}

function mes_nombre($numero)
{
	$nombres=array(1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre');
	return isset($nombres[intval($numero)]) ? $nombres[intval($numero)] : '';
}

function tareas_meses_disponibles()
{
	return repo_tareas_meses_disponibles(vinculaciones_id_area());
}

function tareas_valores_unicos($campo)
{
	if($campo=='responsable')
	{
		return repo_tareas_responsables_disponibles(vinculaciones_id_area());
	}
	if($campo=='tipo')
	{
		return repo_tareas_tipos_disponibles(vinculaciones_id_area());
	}
	if($campo=='prioridad')
	{
		return array('Urgente','Alta','Media','Baja');
	}
	if($campo=='area')
	{
		return array(acceso_etiqueta_corta_area('vinculaciones'));
	}
	return array();
}

function label_prioridad($prioridad)
{
	return $prioridad=='Urgente' ? 'label-important' : (($prioridad=='Alta' || $prioridad=='Media') ? 'label-warning' : 'label-success');
}

function label_estado($estado)
{
	return $estado=='Terminada' ? 'label-success' : ($estado=='En proceso' ? 'label-info' : (($estado=='Pendiente' || $estado=='No iniciada') ? 'label-warning' : ($estado=='Pausada' ? 'label-inverse' : 'label-important')));
}

function icono_actividad($tipo)
{
	return $tipo=='completada' ? 'fa-check-circle text-success' : ($tipo=='proceso' ? 'fa-clock-o text-warning' : ($tipo=='creada' ? 'fa-plus-circle text-info' : 'fa-exclamation-circle text-danger'));
}

function tarea_esta_vencida($tarea)
{
	return is_array($tarea) && $tarea['estado']!='Terminada' && $tarea['fecha_cierre']!='' && strtotime($tarea['fecha_cierre'])<strtotime(date('Y-m-d'));
}

function calendario_dias_con_tareas($anio, $mes, $lista=null)
{
	return repo_calendario_dias_con_tareas($anio, $mes, vinculaciones_id_area(), tareas_rol(), repo_empleado_logueado());
}

function actividades_lista()
{
	return repo_tarea_actividad(vinculaciones_id_area());
}

function tareas_guardar($lista)
{
	return;
}
?>
