<?php
// Tareas de Recepción. Listado y guardado salen de la base, no de arreglos en sesión.

require_once __DIR__.'/tareas-base.php';

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
	// Limpia restos de las pruebas en sesión (ya no se usan).
	unset($_SESSION['rec_tareas_demo']);
	unset($_SESSION['rec_tareas_actividad']);
	unset($_SESSION['rec_tareas_demo_v']);
}

function tareas_rol()
{
	return isset($_SESSION['tareas_rol']) ? $_SESSION['tareas_rol'] : 'coordinador';
}

function tareas_persona()
{
	return isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : '';
}

// Lee las tareas del área. Siempre desde la base.
function tareas_lista()
{
	return rec_tareas_leer();
}

// Guarda el alta o la edición en la base.
function tareas_guardar($lista)
{
	rec_tareas_guardar_lista($lista);
}

function rec_aplicar_carga_tipo($tarea)
{
	if(!isset($_POST['cargar_tipo']))
	{
		return $tarea;
	}
	if(!is_array($tarea))
	{
		$tarea=array();
	}
	$campos=array('titulo','descripcion','tipo','prioridad','fecha_inicio','fecha_cierre','cliente_nombre');
	foreach($campos as $campo)
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
	$cargado=rec_tipo_cargar(isset($_POST['tipo_nuevo']) ? $_POST['tipo_nuevo'] : '');
	if($cargado!='')
	{
		$tarea['tipo']=$cargado;
	}
	return $tarea;
}

// Contrato para los archivos compartidos. Las otras áreas copian estas mismas firmas.
function tareas_tipos_de_area($area)
{
	return rec_tipos_listar();
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
	return rec_aplicar_carga_tipo($tarea);
}

function tareas_datos_guardar_nueva($area)
{
	$responsables=tareas_leer_responsables_post($area);
	$responsable=isset($responsables[0]) ? $responsables[0] : '';
	$cliente=tareas_leer_cliente_post();
	$tipo=isset($_POST['tipo']) ? $_POST['tipo'] : '';
	$nueva=array(
		'id'=>0,
		'titulo'=>isset($_POST['titulo']) ? $_POST['titulo'] : '',
		'descripcion'=>isset($_POST['descripcion']) ? $_POST['descripcion'] : '',
		'area'=>$tipo,
		'prioridad'=>isset($_POST['prioridad']) ? $_POST['prioridad'] : 'Media',
		'tipo'=>$tipo,
		'responsable'=>$responsable,
		'responsables'=>$responsables,
		'enlaces'=>array(),
		'comentarios'=>array(),
		'iniciaron'=>array(),
		'cliente_id'=>$cliente['id'],
		'cliente_nombre'=>$cliente['nombre'],
		'fecha_inicio'=>isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '',
		'fecha_cierre'=>isset($_POST['fecha_cierre']) ? $_POST['fecha_cierre'] : '',
		'avance'=>0,
		'estado'=>'No iniciada'
	);
	$nueva=rec_tarea_alta($nueva);
	tareas_avisos_registrar($responsables, $nueva['titulo'], $nueva['id'], $area, $nueva);
	return $area;
}

function tareas_datos_actualizar($id)
{
	$item=rec_tarea_por_id($id);
	if(!$item || !tarea_puede_editar($item))
	{
		return false;
	}
	$area=acceso_area_pantalla();
	$responsables=tareas_leer_responsables_post($area, $item);
	$cliente=tareas_leer_cliente_post();
	$tipo=isset($_POST['tipo']) ? $_POST['tipo'] : (isset($item['tipo']) ? $item['tipo'] : '');
	$item['titulo']=isset($_POST['titulo']) ? $_POST['titulo'] : $item['titulo'];
	$item['descripcion']=isset($_POST['descripcion']) ? $_POST['descripcion'] : '';
	$item['area']=$tipo;
	$item['prioridad']=isset($_POST['prioridad']) ? $_POST['prioridad'] : 'Media';
	$item['tipo']=$tipo;
	$item['responsable']=isset($responsables[0]) ? $responsables[0] : (isset($item['responsable']) ? $item['responsable'] : '');
	$item['responsables']=$responsables;
	$item['cliente_id']=$cliente['id'];
	$item['cliente_nombre']=$cliente['nombre'];
	$ini=isset($item['iniciaron']) && is_array($item['iniciaron']) ? $item['iniciaron'] : array();
	$queda=array();
	foreach($ini as $nom)
	{
		if(in_array($nom, $responsables))
		{
			$queda[]=$nom;
		}
	}
	$item['iniciaron']=$queda;
	$item['fecha_inicio']=isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '';
	$item['fecha_cierre']=isset($_POST['fecha_cierre']) ? $_POST['fecha_cierre'] : '';
	rec_tarea_guardar_una($item);
	return true;
}

function tareas_datos_guardar_extras($id)
{
	$item=rec_tarea_por_id($id);
	if(!$item || !tarea_puede_agregar_extras($item))
	{
		return false;
	}
	$ok=false;
	if(isset($_POST['agregar_enlace']) || isset($_POST['agregar_avance']))
	{
		$ok=crm_tarea_agregar_enlace($id, isset($_POST['enlace_nuevo']) ? $_POST['enlace_nuevo'] : '') || $ok;
	}
	if(isset($_POST['agregar_comentario']) || isset($_POST['agregar_avance']))
	{
		$ok=crm_tarea_agregar_comentario($id, isset($_POST['comentario_nuevo']) ? $_POST['comentario_nuevo'] : '') || $ok;
	}
	return $ok;
}

function tareas_datos_procesar_accion()
{
	if(isset($_POST['id_tarea']) && (isset($_POST['agregar_comentario']) || isset($_POST['agregar_enlace']) || isset($_POST['agregar_avance'])))
	{
		return tareas_datos_guardar_extras(intval($_POST['id_tarea']));
	}
	if(!isset($_POST['accion_tarea']) || !isset($_POST['id_tarea']))
	{
		return false;
	}
	$id=intval($_POST['id_tarea']);
	$lista=tareas_aplicar_accion_en_lista(rec_tareas_leer(), $id, $_POST['accion_tarea'], $_POST);
	rec_tareas_guardar_lista($lista);
	if(function_exists('crm_tarea_sync_padre_de'))
	{
		crm_tarea_sync_padre_de($id);
	}
	return true;
}

function actividades_lista()
{
	return rec_actividades_recientes();
}

// El coordinador ve las suyas y las del equipo.
// El empleado ve solo las suyas.
function tareas_visibles()
{
	return rec_tareas_leer(array(
		'solo_mias'=>acceso_es_empleado()
	));
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
	return rec_tarea_por_id($id);
}

// Aplica los filtros de la pantalla (mes, area, prioridad, tipo, responsable, estado).
function tareas_filtradas()
{
	return rec_tareas_leer(array(
		'solo_mias'=>acceso_es_empleado(),
		'mes'=>isset($_GET['mes']) ? $_GET['mes'] : '',
		'area'=>isset($_GET['area']) ? $_GET['area'] : '',
		'prioridad'=>isset($_GET['prioridad']) ? $_GET['prioridad'] : '',
		'tipo'=>isset($_GET['tipo']) ? $_GET['tipo'] : '',
		'responsable'=>isset($_GET['responsable']) ? $_GET['responsable'] : '',
		'estado'=>isset($_GET['estado']) ? $_GET['estado'] : '',
		'cliente'=>isset($_GET['cliente']) ? $_GET['cliente'] : ''
	));
}

// Numeros de las tarjetas de arriba. Se calculan sobre lo que la persona puede ver.
function tareas_contadores($lista)
{
	$cont=array('Pendiente'=>0,'No iniciada'=>0,'En proceso'=>0,'Pausada'=>0,'Terminada'=>0,'Vencida'=>0);
	foreach($lista as $tarea)
	{
		$est_txt=tarea_texto_estado($tarea);
		if($est_txt=='No iniciada')
		{
			$cont['Pendiente']++;
			$cont['No iniciada']++;
		}
		else if(isset($cont[$est_txt]))
		{
			$cont[$est_txt]++;
		}
		if(tarea_esta_vencida($tarea))
		{
			$cont['Vencida']++;
		}
	}
	return $cont;
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
	$meses=array();
	foreach(tareas_visibles() as $tarea)
	{
		$m1=substr($tarea['fecha_inicio'],0,7);
		$m2=substr($tarea['fecha_cierre'],0,7);
		if($m1!='') { $meses[$m1]=1; }
		if($m2!='') { $meses[$m2]=1; }
	}
	krsort($meses);
	return array_keys($meses);
}

function tareas_valores_unicos($campo)
{
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
	$dias=array();
	$prefijo=sprintf('%04d-%02d', $anio, $mes);
	foreach($lista as $tarea)
	{
		foreach(array('fecha_inicio','fecha_cierre') as $campo)
		{
			if(substr($tarea[$campo],0,7)==$prefijo)
			{
				$d=intval(substr($tarea[$campo],8,2));
				$dias[$d]=1;
			}
		}
	}
	return $dias;
}
?>
