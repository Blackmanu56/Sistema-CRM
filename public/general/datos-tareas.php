<?php
// Tareas de Coordinación general: ve y gestiona las tareas de todas las áreas (datos reales en MySQL).
require_once BASE_PATH.'/includes/tareas_repositorio.php';

function general_id_area_de($clave)
{
	static $cache=array();
	if(!isset($cache[$clave]))
	{
		$cache[$clave]=repo_id_area_por_clave($clave);
	}
	return $cache[$clave];
}

function general_tipo_id($clave_area, $nombre)
{
	$nombre=trim($nombre);
	$id_area=general_id_area_de($clave_area);
	if($nombre==='' || $id_area<=0)
	{
		return 0;
	}
	$fila=acceso_consulta_una("SELECT id_tipo FROM tipo_tarea WHERE id_area=? AND nombre=? LIMIT 1", 'is', array($id_area, $nombre));
	if(!$fila)
	{
		tareas_objetivo_guardar($clave_area, $nombre);
		$fila=acceso_consulta_una("SELECT id_tipo FROM tipo_tarea WHERE id_area=? AND nombre=? LIMIT 1", 'is', array($id_area, $nombre));
	}
	return $fila ? intval($fila['id_tipo']) : 0;
}

function general_ids_responsables($nombres)
{
	$ids=array();
	foreach($nombres as $nombre)
	{
		$id=repo_nombre_a_id($nombre);
		if($id>0 && !in_array($id, $ids)) { $ids[]=$id; }
	}
	return $ids;
}

function tareas_tipos_de_area($area)
{
	return tareas_objetivos_de_bd($area);
}

function tareas_puede_cargar_tipo()
{
	return !acceso_es_empleado();
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
	$destino=isset($_POST['area_crm']) ? $_POST['area_crm'] : 'general';
	$nombre=isset($_POST['tipo_nuevo']) ? trim($_POST['tipo_nuevo']) : '';
	if($nombre==='')
	{
		tareas_mensaje_form_set('Escribí el nombre del objetivo a cargar.');
		return $tarea;
	}
	$cargado=tareas_objetivo_guardar($destino, $nombre);
	if($cargado!=='')
	{
		$tarea['tipo']=$cargado;
		tareas_mensaje_form_set('Objetivo “'.$cargado.'” listo. Es el mismo catálogo que en Programas.');
	}
	else
	{
		tareas_mensaje_form_set('No se pudo cargar el objetivo.');
	}
	return $tarea;
}

function tareas_datos_guardar_nueva($area)
{
	$fi=isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-d');
	$fc=isset($_POST['fecha_cierre']) ? $_POST['fecha_cierre'] : '';
	$err=tareas_validar_fechas_alta($fi, $fc);
	if($err!='')
	{
		tareas_mensaje_form_set($err);
		return false;
	}
	$responsables=tareas_leer_responsables_post($area);
	$ids=general_ids_responsables($responsables);
	if(count($ids)==0)
	{
		$id=repo_empleado_logueado();
		if($id>0) { $ids[]=$id; }
	}
	$cliente=tareas_leer_cliente_post();
	$datos=array(
		'titulo'=>isset($_POST['titulo']) ? trim($_POST['titulo']) : '',
		'descripcion'=>isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '',
		'id_area'=>general_id_area_de($area),
		'id_tipo'=>general_tipo_id($area, isset($_POST['tipo']) ? $_POST['tipo'] : ''),
		'prioridad'=>isset($_POST['prioridad']) ? $_POST['prioridad'] : 'Media',
		'fecha_inicio'=>$fi,
		'fecha_cierre'=>$fc,
		'id_cliente'=>isset($cliente['id']) ? intval($cliente['id']) : 0
	);
	if($datos['id_area']<=0 || $datos['titulo']==='')
	{
		tareas_mensaje_form_set('Falta el título o el área de la tarea.');
		return false;
	}
	if($datos['id_tipo']<=0)
	{
		tareas_mensaje_form_set('Elegí un objetivo válido.');
		return false;
	}
	$nuevo_id=repo_tarea_crear($datos, $ids, repo_empleado_logueado());
	if($nuevo_id<=0)
	{
		tareas_mensaje_form_set('No se pudo guardar la tarea. Revisá fechas, objetivo y responsables.');
		return false;
	}
	if(isset($_POST['id_atencion']) && intval($_POST['id_atencion'])>0)
	{
		acceso_ejecutar("UPDATE tarea SET id_atencion=? WHERE id_tarea=?", 'ii', array(intval($_POST['id_atencion']), $nuevo_id));
	}
	if(function_exists('tareas_avisos_registrar'))
	{
		tareas_avisos_registrar($responsables, $datos['titulo'], $nuevo_id, $area);
	}
	return $area;
}

function tareas_datos_actualizar($id)
{
	$tarea=repo_tarea_por_id(intval($id), tareas_rol(), repo_empleado_logueado());
	if(!$tarea || !tarea_puede_editar($tarea)) { return false; }
	$area=$tarea['area_clave'];
	$ids=general_ids_responsables(tareas_leer_responsables_post($area, $tarea));
	$cliente=tareas_leer_cliente_post();
	$datos=array(
		'titulo'=>isset($_POST['titulo']) ? trim($_POST['titulo']) : $tarea['titulo'],
		'descripcion'=>isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '',
		'id_area'=>intval($tarea['id_area']),
		'id_tipo'=>general_tipo_id($area, isset($_POST['tipo']) ? $_POST['tipo'] : $tarea['tipo']),
		'prioridad'=>isset($_POST['prioridad']) ? $_POST['prioridad'] : 'Media',
		'fecha_inicio'=>isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '',
		'fecha_cierre'=>isset($_POST['fecha_cierre']) ? $_POST['fecha_cierre'] : '',
		'id_cliente'=>isset($cliente['id']) ? intval($cliente['id']) : 0
	);
	return repo_tarea_actualizar(intval($id), $datos, $ids);
}

function tareas_datos_procesar_accion()
{
	if(!isset($_POST['accion_tarea']) || !isset($_POST['id_tarea'])) { return false; }
	$mapa=array('iniciar'=>'en_proceso','pausar'=>'pausada','reanudar'=>'en_proceso','terminar'=>'terminada');
	$accion=$_POST['accion_tarea'];
	if(!isset($mapa[$accion])) { return false; }
	$tarea=repo_tarea_por_id(intval($_POST['id_tarea']), tareas_rol(), repo_empleado_logueado());
	if(!$tarea || !tarea_puede_cambiar_estado($tarea)) { return false; }
	return repo_tarea_cambiar_estado(intval($_POST['id_tarea']), $mapa[$accion], repo_empleado_logueado());
}

// Quien entro: el coordinador general ve todo; el empleado, solo las suyas.
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
	if(isset($_SESSION['tareas_rol']) && in_array($_SESSION['tareas_rol'], array('coordinador_general','admin','superadmin'), true))
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
	unset($_SESSION['gen_tareas_demo'], $_SESSION['gen_tareas_actividad'], $_SESSION['gen_tareas_demo_v']);
}

function tareas_rol()
{
	return isset($_SESSION['tareas_rol']) ? $_SESSION['tareas_rol'] : 'coordinador';
}

function tareas_persona()
{
	return isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : repo_nombre_logueado();
}

function tareas_lista()
{
	return repo_tareas_listar(0, array(), tareas_rol(), repo_empleado_logueado());
}

function tareas_guardar($lista)
{
	// El repositorio guarda cada tarea por separado.
}

function actividades_lista()
{
	$salida=array();
	$filas=acceso_consulta(
		"SELECT act.tipo, t.titulo, act.descripcion, act.fecha_actividad,
			CASE WHEN e.nombres IS NOT NULL THEN CONCAT(e.nombres,' ',e.apellido,' - ',a.nombre) ELSE '' END AS quien
		FROM actividad act
		INNER JOIN tarea t ON t.id_tarea=act.id_tarea
		INNER JOIN area a ON a.id_area=t.id_area
		LEFT JOIN empleado e ON e.id_empleado=act.id_empleado
		ORDER BY act.fecha_actividad DESC LIMIT 10"
	);
	$titulos=array('creada'=>'Tarea creada','proceso'=>'En proceso','completada'=>'Tarea completada','vencida'=>'Tarea vencida');
	foreach($filas as $r)
	{
		$salida[]=array(
			'tipo'=>$r['tipo'],
			'titulo'=>isset($titulos[$r['tipo']]) ? $titulos[$r['tipo']] : $r['titulo'],
			'detalle'=>isset($titulos[$r['tipo']]) ? $r['titulo'] : (string)$r['descripcion'],
			'extra'=>$r['quien'],
			'cuando'=>$r['fecha_actividad']
		);
	}
	return $salida;
}

function tareas_visibles()
{
	return tareas_lista();
}

function tarea_esta_vencida($tarea)
{
	if($tarea['estado']=='Terminada' || (function_exists('tarea_esta_eliminada') && tarea_esta_eliminada($tarea)))
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
	return 0;
}

function tareas_filtradas()
{
	$filtros=array();
	foreach(array('mes','area','prioridad','tipo','responsable','estado','cliente') as $campo)
	{
		if(isset($_GET[$campo]) && $_GET[$campo]!=='') { $filtros[$campo]=$_GET[$campo]; }
	}
	// El tipo depende del área: sin área elegida se filtra por nombre en PHP.
	$tipo=isset($filtros['tipo']) ? $filtros['tipo'] : '';
	unset($filtros['tipo']);
	$lista=repo_tareas_listar(0, $filtros, tareas_rol(), repo_empleado_logueado());
	if($tipo==='')
	{
		return $lista;
	}
	$out=array();
	foreach($lista as $t)
	{
		if(isset($t['tipo']) && $t['tipo']===$tipo) { $out[]=$t; }
	}
	return $out;
}

function tareas_contadores($lista)
{
	return crm_tareas_contar($lista);
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

function tareas_meses_disponibles()
{
	$meses=array();
	foreach(acceso_consulta(
		"SELECT DISTINCT mes FROM (
			SELECT DATE_FORMAT(fecha_inicio,'%Y-%m') AS mes FROM tarea WHERE estado<>'cancelada'
			UNION SELECT DATE_FORMAT(fecha_cierre,'%Y-%m') AS mes FROM tarea WHERE estado<>'cancelada' AND fecha_cierre IS NOT NULL
		) m WHERE mes IS NOT NULL ORDER BY mes DESC"
	) as $r)
	{
		$meses[]=$r['mes'];
	}
	return $meses;
}

// En el filtro "Área" el valor es la clave y se muestra el nombre (ver tareas.php).
function tareas_valores_unicos($campo)
{
	if($campo=='prioridad') { return array('Urgente','Alta','Media','Baja'); }
	if($campo=='area')
	{
		$claves=array();
		foreach(acceso_etiquetas_area_con_tareas() as $clave=>$nombre)
		{
			$claves[]=$clave;
		}
		return $claves;
	}
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

function calendario_dias_con_tareas($anio, $mes, $lista)
{
	$dias=array();
	$prefijo=sprintf('%04d-%02d', $anio, $mes);
	foreach($lista as $tarea)
	{
		if(function_exists('tarea_esta_eliminada') && tarea_esta_eliminada($tarea))
		{
			continue;
		}
		foreach(array('fecha_inicio','fecha_cierre') as $campo)
		{
			if(isset($tarea[$campo]) && substr($tarea[$campo],0,7)==$prefijo)
			{
				$dias[intval(substr($tarea[$campo],8,2))]=1;
			}
		}
	}
	return $dias;
}
?>
