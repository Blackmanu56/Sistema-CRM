<?php
// Tareas de Coordinacion de emprendedores.
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
	if(!isset($_SESSION['tareas_persona']))
	{
		$_SESSION['tareas_persona']='';
	}
	unset($_SESSION['emp_tareas_demo'], $_SESSION['emp_tareas_actividad'], $_SESSION['emp_tareas_demo_v']);
}

function tareas_rol()
{
	return isset($_SESSION['tareas_rol']) ? $_SESSION['tareas_rol'] : 'coordinador';
}

function tareas_persona()
{
	return isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : '';
}

// Lee las tareas de Emprendedores desde la base de datos.
function tareas_lista()
{
	global $con;

    $lista=array();

    if(!$con)
    {
        return $lista;
    }
	$enlace_comentario_disponible=function_exists('crm_tarea_enlace_comentario_preparar') && crm_tarea_enlace_comentario_preparar();
	$columna_enlace_comentario=$enlace_comentario_disponible ? 'id_tarea_comentario' : 'NULL';

    $sql="SELECT
            t.id_tarea,
            t.id_area,
            t.titulo,
            t.descripcion,
            t.prioridad,
            t.estado,
            t.fecha_inicio,
            t.fecha_cierre,
            t.avance,
            t.id_cliente,
            t.id_atencion,
            t.id_tarea_padre,
            t.uid,
            a.clave AS area_clave,
            a.nombre AS area_nombre,
            tt.nombre AS tipo,
            c.nombre AS cliente_nombre
          FROM tarea t
          INNER JOIN area a ON a.id_area=t.id_area
          INNER JOIN tipo_tarea tt ON tt.id_tipo=t.id_tipo
          LEFT JOIN cliente c ON c.id_cliente=t.id_cliente
          WHERE a.clave='emprendedores'
          ORDER BY t.id_tarea DESC";

    $resultado=mysqli_query($con, $sql);

    if(!$resultado)
    {
        return $lista;
    }

  while($row=mysqli_fetch_assoc($resultado))
{
    $responsables=array();

    $q_responsables=mysqli_query(
        $con,
        "SELECT e.nombres, e.apellido
         FROM tarea_responsable tr
         INNER JOIN empleado e ON e.id_empleado=tr.id_empleado
         WHERE tr.id_tarea='".intval($row['id_tarea'])."'
         ORDER BY tr.es_principal DESC, tr.id ASC"
    );

    if($q_responsables)
    {
        while($resp=mysqli_fetch_assoc($q_responsables))
        {
            $nombre=trim($resp['nombres'].' '.$resp['apellido']);

            if($nombre!='')
            {
                $responsables[]=acceso_armar_persona(
                    $nombre,
                    'emprendedores'
                );
            }
        }
    }


$iniciaron=array();

$q_iniciaron=mysqli_query(
    $con,
    "SELECT e.nombres, e.apellido
     FROM tarea_inicio ti
     INNER JOIN empleado e ON e.id_empleado=ti.id_empleado
     WHERE ti.id_tarea='".intval($row['id_tarea'])."'
     ORDER BY ti.fecha_click_inicio ASC"
);

if($q_iniciaron)
{
    while($ini=mysqli_fetch_assoc($q_iniciaron))
    {
        $nombre=trim($ini['nombres'].' '.$ini['apellido']);

        if($nombre!='')
        {
            $persona=acceso_armar_persona(
                $nombre,
                'emprendedores'
            );

            if(!in_array($persona, $iniciaron))
            {
                $iniciaron[]=$persona;
            }
        }
    }
}

$enlaces=array();

$q_enlaces=mysqli_query(
    $con,
    "SELECT id, url, fecha_carga, ".$columna_enlace_comentario." AS id_tarea_comentario
     FROM tarea_enlace
     WHERE id_tarea='".intval($row['id_tarea'])."'
     ORDER BY fecha_carga ASC"
);

if($q_enlaces)
{
    while($enlace=mysqli_fetch_assoc($q_enlaces))
    {
        if(trim($enlace['url'])!='')
        {
            $enlaces[]=array(
                'id'=>isset($enlace['id']) ? intval($enlace['id']) : 0,
                'url'=>$enlace['url'],
                'fecha_carga'=>isset($enlace['fecha_carga']) ? $enlace['fecha_carga'] : '',
                'id_tarea_comentario'=>isset($enlace['id_tarea_comentario']) ? intval($enlace['id_tarea_comentario']) : 0
            );
        }
    }	
}

$comentarios=array();

$q_comentarios=mysqli_query(
    $con,
    "SELECT
        tc.id,
        tc.texto,
        tc.fecha_carga,
        tc.id_empleado,
        tc.id_usuario_actor,
        e.nombres,
        e.apellido,
        u.nombre AS nombre_actor
     FROM tarea_comentario tc
     LEFT JOIN empleado e ON e.id_empleado=tc.id_empleado
     LEFT JOIN usuario_cliente u ON u.id_usuario=tc.id_usuario_actor
     WHERE tc.id_tarea='".intval($row['id_tarea'])."'
     ORDER BY tc.fecha_carga ASC"
);

if($q_comentarios)
{
    while($com=mysqli_fetch_assoc($q_comentarios))
    {
        $nombre=trim((isset($com['nombres']) ? $com['nombres'] : '').' '.(isset($com['apellido']) ? $com['apellido'] : ''));
        $quien=$nombre!=='' ? acceso_armar_persona($nombre, 'emprendedores') : (isset($com['nombre_actor']) && trim($com['nombre_actor'])!=='' ? trim($com['nombre_actor']) : 'Usuario');
        $fc=isset($com['fecha_carga']) ? $com['fecha_carga'] : '';

        $comentarios[]=array(
            'id'=>intval($com['id']),
            'id_empleado'=>isset($com['id_empleado']) ? intval($com['id_empleado']) : 0,
            'id_usuario_actor'=>isset($com['id_usuario_actor']) ? intval($com['id_usuario_actor']) : 0,
            'texto'=>$com['texto'],
            'quien'=>$quien,
            'cuando'=>($fc!='' ? date('d/m/Y H:i', strtotime($fc)) : ''),
            'fecha_carga'=>$fc
        );
    }
}


    $lista[]=array(
        'id'=>intval($row['id_tarea']),
        'titulo'=>$row['titulo'],
        'descripcion'=>$row['descripcion'],
        'area'=>$row['area_nombre'],
        'area_nombre'=>$row['area_nombre'],
        'area_clave'=>isset($row['area_clave']) && $row['area_clave']!='' ? $row['area_clave'] : 'emprendedores',
        'id_area'=>isset($row['id_area']) ? intval($row['id_area']) : 0,
        'tipo'=>$row['tipo'],
        'prioridad'=>ucfirst($row['prioridad']),
        'estado'=>match($row['estado']) {
            'no_iniciada'=>'No iniciada',
            'en_proceso'=>'En proceso',
            'pausada'=>'Pausada',
            'terminada'=>'Terminada',
            'cancelada'=>'Eliminada',
            default=>'No iniciada'
        },
        'fecha_inicio'=>$row['fecha_inicio'],
        'fecha_cierre'=>$row['fecha_cierre'],
        'avance'=>intval($row['avance']),
        'cliente_id'=>intval($row['id_cliente']),
        'cliente_nombre'=>$row['cliente_nombre'],
        'id_tarea_padre'=>isset($row['id_tarea_padre']) ? intval($row['id_tarea_padre']) : 0,
        'id_atencion'=>isset($row['id_atencion']) ? intval($row['id_atencion']) : 0,
        'uid'=>$row['uid'],
        'responsables'=>$responsables,
        'iniciaron'=>$iniciaron,
        'enlaces'=>$enlaces,
        'comentarios'=>$comentarios
    );
}

	return $lista;
}

function actividades_lista()
{
    global $con;

    $lista=array();

    if(!$con)
    {
        return $lista;
    }

    $filtro_empleado='';

    if(acceso_es_empleado())
    {
        $id_empleado=isset($_SESSION['id_empleado'])
            ? intval($_SESSION['id_empleado'])
            : 0;

        if($id_empleado<=0)
        {
            return $lista;
        }

        $filtro_empleado="
            AND EXISTS
            (
                SELECT 1
                FROM tarea_responsable tr
                WHERE tr.id_tarea=t.id_tarea
                AND tr.id_empleado='".$id_empleado."'
            )";
    }

    $sql="SELECT
            ac.id_actividad,
            ac.tipo,
            ac.descripcion,
            ac.fecha_actividad,
            t.titulo AS tarea_titulo,
            tt.nombre AS tipo_tarea
          FROM actividad ac
          INNER JOIN tarea t ON t.id_tarea=ac.id_tarea
          INNER JOIN area a ON a.id_area=t.id_area
          LEFT JOIN tipo_tarea tt ON tt.id_tipo=t.id_tipo
          WHERE a.clave='emprendedores'
          ".$filtro_empleado."
          ORDER BY ac.fecha_actividad DESC, ac.id_actividad DESC
          LIMIT 10";

    $resultado=mysqli_query($con, $sql);

    if(!$resultado)
    {
        return $lista;
    }

    while($row=mysqli_fetch_assoc($resultado))
    {
        $titulo='Actividad';

        if($row['tipo']=='creada')
        {
            $titulo='Tarea creada';
        }
        else if($row['tipo']=='proceso')
        {
            $titulo='Tarea en proceso';
        }
        else if($row['tipo']=='completada')
        {
            $titulo='Tarea completada';
        }
        else if($row['tipo']=='vencida')
        {
            $titulo='Tarea vencida';
        }

        $lista[]=array(
            'tipo'=>$row['tipo'],
            'titulo'=>$titulo,
            'detalle'=>$row['descripcion']!=''
                ? $row['descripcion']
                : $row['tarea_titulo'],
            'extra'=>$row['tipo_tarea'],
            'cuando'=>date(
                'd/m/Y H:i',
                strtotime($row['fecha_actividad'])
            )
        );
    }

    return $lista;
}

// El coordinador ve las suyas y las del equipo.
// El empleado ve solo las suyas.
function tareas_visibles()
{
	return acceso_tareas_visibles(tareas_lista(), acceso_area_pantalla());
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
	$id=intval($id);
	if($id<=0)
	{
		return null;
	}
	foreach(tareas_visibles() as $tarea)
	{
		if(intval($tarea['id'])===$id)
		{
			return $tarea;
		}
	}
	// Fallback BD (subtareas recién creadas o filtradas por rol)
	if(function_exists('crm_tarea_fila'))
	{
		$f=crm_tarea_fila($id);
		if($f && isset($f['area_clave']) && $f['area_clave']==='emprendedores')
		{
			if(acceso_es_empleado() && function_exists('tarea_es_responsable'))
			{
				// armar mínimo y chequear responsable
				$tmp=array(
					'id'=>$id,
					'titulo'=>$f['titulo'],
					'descripcion'=>isset($f['descripcion']) ? $f['descripcion'] : '',
					'area'=>isset($f['area_nombre']) ? $f['area_nombre'] : 'Emprendedores',
					'tipo'=>'',
					'prioridad'=>ucfirst($f['prioridad']),
					'estado'=>emp_map_estado($f['estado']),
					'fecha_inicio'=>$f['fecha_inicio'],
					'fecha_cierre'=>$f['fecha_cierre'],
					'avance'=>intval($f['avance']),
					'cliente_id'=>intval($f['id_cliente']),
					'cliente_nombre'=>'',
					'id_tarea_padre'=>intval($f['id_tarea_padre']),
					'id_atencion'=>intval($f['id_atencion']),
					'responsables'=>array(),
					'iniciaron'=>array(),
					'enlaces'=>array(),
					'comentarios'=>array()
				);
				$resps=acceso_consulta(
					"SELECT CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) AS nombre
					FROM tarea_responsable tr INNER JOIN empleado e ON e.id_empleado=tr.id_empleado
					WHERE tr.id_tarea=?",
					'i',
					array($id)
				);
				foreach($resps as $r)
				{
					$tmp['responsables'][]=acceso_armar_persona(trim($r['nombre']), 'emprendedores');
				}
				if(!tarea_es_responsable($tmp))
				{
					return null;
				}
				return $tmp;
			}
			// Coord: forzar recarga completa desde lista cruda
			foreach(tareas_lista() as $tarea)
			{
				if(intval($tarea['id'])===$id)
				{
					return $tarea;
				}
			}
		}
	}
	return null;
}

function emp_map_estado($est)
{
	$map=array('no_iniciada'=>'No iniciada','en_proceso'=>'En proceso','pausada'=>'Pausada','terminada'=>'Terminada','cancelada'=>'Eliminada');
	return isset($map[$est]) ? $map[$est] : 'No iniciada';
}

// Aplica los filtros de la pantalla (mes, area, prioridad, tipo, responsable, estado).
function tareas_filtradas()
{
	$lista=tareas_visibles();
	$mes=isset($_GET['mes']) ? $_GET['mes'] : '';
	$area=isset($_GET['area']) ? $_GET['area'] : '';
	$prioridad=isset($_GET['prioridad']) ? $_GET['prioridad'] : '';
	$tipo=isset($_GET['tipo']) ? $_GET['tipo'] : '';
	$responsable=isset($_GET['responsable']) ? $_GET['responsable'] : '';
	$estado=isset($_GET['estado']) ? $_GET['estado'] : '';

	$salida=array();
	foreach($lista as $tarea)
	{
		if($mes!='' && substr($tarea['fecha_inicio'],0,7)!=$mes && substr($tarea['fecha_cierre'],0,7)!=$mes)
		{
			continue;
		}
		if($area!='' && strcasecmp($tarea['area'], $area)!==0 && (!isset($tarea['area_clave']) || $tarea['area_clave']!==$area))
		{
			continue;
		}
		if($prioridad!='' && $tarea['prioridad']!=$prioridad)
		{
			continue;
		}
		if($tipo!='' && $tarea['tipo']!=$tipo)
		{
			continue;
		}
		if($responsable!='' && !tarea_tiene_responsable($tarea, $responsable))
		{
			continue;
		}
		if($estado=='Vencida')
		{
			if(!tarea_esta_vencida($tarea))
			{
				continue;
			}
		}
		else if($estado!='' && !tarea_coincide_estado($tarea, $estado))
		{
			continue;
		}
		$salida[]=$tarea;
	}
	return $salida;
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

// Contrato de integración acordado en PARA-EL-EQUIPO.txt.
function tareas_tipos_de_area($area)
{
    if ($area!=='emprendedores') { return array(); }
    try {
        return array_column(emp_consulta('SELECT tt.nombre FROM tipo_tarea tt INNER JOIN area a ON a.id_area=tt.id_area WHERE a.clave=? ORDER BY tt.nombre', 's', array($area)), 'nombre');
    } catch (Throwable $e) { return array(); }
}

function tareas_puede_cargar_tipo() { return !acceso_es_empleado(); }
function tareas_fecha_cierre_obligatoria() { return true; }
function tareas_aplicar_carga_tipo($tarea)
{
    if (!isset($_POST['cargar_tipo'])) { return $tarea; }
    if (!is_array($tarea)) { $tarea=array(); }
    foreach (array('titulo','descripcion','tipo','prioridad','fecha_inicio','fecha_cierre','cliente_nombre') as $campo) {
        if (isset($_POST[$campo]) && is_string($_POST[$campo])) { $tarea[$campo]=$_POST[$campo]; }
    }
    if (isset($_POST['cliente_id'])) { $tarea['cliente_id']=intval($_POST['cliente_id']); }
    if (isset($_POST['responsables']) && is_array($_POST['responsables'])) { $tarea['responsables']=$_POST['responsables']; }
    $nombre=isset($_POST['tipo_nuevo']) && is_string($_POST['tipo_nuevo']) ? trim($_POST['tipo_nuevo']) : '';
    if ($nombre==='' || strlen($nombre)>80) {
        $GLOBALS['emp_error']='Escribí el nombre del objetivo (hasta 80 caracteres).';
        return $tarea;
    }
    try {
        $id_area=acceso_id_por_clave('area','emprendedores');
        if ($id_area<=0) { throw new RuntimeException('Área no disponible.'); }
        $filas=emp_consulta('SELECT nombre FROM tipo_tarea WHERE id_area=? AND nombre=? LIMIT 1','is',array($id_area,$nombre));
        if ($filas) { $nombre=$filas[0]['nombre']; }
        else { emp_consulta('INSERT INTO tipo_tarea (id_area,nombre) VALUES (?,?)','is',array($id_area,$nombre)); }
        $tarea['tipo']=$nombre;
        $GLOBALS['emp_error']='';
        if(function_exists('tareas_mensaje_form_set'))
        {
            tareas_mensaje_form_set('Objetivo “'.$nombre.'” listo. Es el mismo catálogo que en Programas.');
        }
    } catch (Throwable $e) {
        error_log('Tipo de Emprendedores: '.$e->getMessage());
        $GLOBALS['emp_error']='No se pudo cargar el objetivo. Intentá nuevamente.';
    }
    return $tarea;
}

function tareas_datos_guardar_nueva($area)
{
    if ($area==='' || $area===null) { $area=tareas_area_destino_alta(); }
    $id=emp_transaccion(function() use ($area) { return emp_guardar_form(emp_datos_form(null, $area), 0, $area); });
    if (!$id) { return false; }
    // Usa el mecanismo de avisos disponible en la versión compartida del proyecto.
    try {
        $nueva=tarea_por_id($id);
        if ($nueva) { tareas_avisos_registrar($nueva['responsables'], $nueva['titulo'], $id, $area, $nueva); }
    } catch (Throwable $e) { error_log('Aviso de Emprendedores: '.$e->getMessage()); }
    return $area;
}

function tareas_datos_actualizar($id)
{
    $item=tarea_por_id($id);
    if (!$item || !tarea_puede_editar($item)) { return false; }
    $area_clave = (isset($item['area_clave']) && $item['area_clave']!='') ? $item['area_clave'] : 'emprendedores';
    return emp_transaccion(function() use ($item,$id,$area_clave) { emp_guardar_form(emp_datos_form($item, $area_clave),$id, $area_clave); return true; });
}

function tareas_datos_guardar_extras($id)
{
    $item=tarea_por_id($id);
    if (!$item || !tarea_puede_agregar_extras($item)) { return false; }
    if (isset($_POST['agregar_avance'])) {
        $url=isset($_POST['enlace_nuevo']) && is_string($_POST['enlace_nuevo']) ? trim($_POST['enlace_nuevo']) : '';
        $txt=isset($_POST['comentario_nuevo']) && is_string($_POST['comentario_nuevo']) ? trim($_POST['comentario_nuevo']) : '';
        if ($url==='' && $txt==='') { return false; }
        if ($url!=='' && (!filter_var($url,FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url,PHP_URL_SCHEME)),array('http','https')))) {
            $GLOBALS['emp_error']='Ingresá un enlace válido que empiece con http o https.';
            return false;
        }
        return emp_transaccion(function() use ($id,$url,$txt) {
            if ($url!=='') { emp_consulta('INSERT INTO tarea_enlace (id_tarea,url) VALUES (?,?)','is',array($id,$url)); }
            if ($txt!=='') {
                $empleado=isset($_SESSION['id_empleado']) ? intval($_SESSION['id_empleado']) : 0;
                if ($empleado<=0) { throw new RuntimeException('Colaborador no identificado.'); }
                emp_consulta('INSERT INTO tarea_comentario (id_tarea,id_empleado,texto) VALUES (?,?,?)','iis',array($id,$empleado,$txt));
            }
            return true;
        });
    }
    $enlace=isset($_POST['agregar_enlace']);
    $campo=$enlace ? 'enlace_nuevo' : 'comentario_nuevo';
    $texto=isset($_POST[$campo]) && is_string($_POST[$campo]) ? trim($_POST[$campo]) : '';
    if ($texto==='' || (!$enlace && !isset($_POST['agregar_comentario']))) { return false; }
    if ($enlace && (!filter_var($texto,FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($texto,PHP_URL_SCHEME)),array('http','https')))) {
        $GLOBALS['emp_error']='Ingresá un enlace válido que empiece con http o https.';
        return false;
    }
    return emp_transaccion(function() use ($id,$texto,$enlace) {
        if ($enlace) { emp_consulta('INSERT INTO tarea_enlace (id_tarea,url) VALUES (?,?)','is',array($id,$texto)); }
        else {
            $empleado=isset($_SESSION['id_empleado']) ? intval($_SESSION['id_empleado']) : 0;
            if ($empleado<=0) { throw new RuntimeException('Colaborador no identificado.'); }
            emp_consulta('INSERT INTO tarea_comentario (id_tarea,id_empleado,texto) VALUES (?,?,?)','iis',array($id,$empleado,$texto));
        }
        return true;
    });
}

function tareas_datos_procesar_accion()
{
    $id=isset($_POST['id_tarea']) ? intval($_POST['id_tarea']) : 0;
    if ($id<=0) { return false; }
    if (isset($_POST['agregar_enlace']) || isset($_POST['agregar_comentario']) || isset($_POST['agregar_avance'])) { return tareas_datos_guardar_extras($id); }
    $accion=isset($_POST['accion_tarea']) ? $_POST['accion_tarea'] : '';
    if (!in_array($accion,array('iniciar','pausar','reanudar','terminar'),true)) { return false; }
    return emp_transaccion(function() use ($id,$accion) {
        // Serializa acciones simultáneas y comprueba pertenencia al área.
        $filas=emp_consulta('SELECT t.id_tarea FROM tarea t INNER JOIN area a ON a.id_area=t.id_area WHERE t.id_tarea=? AND a.clave=? FOR UPDATE','is',array($id,'emprendedores'));
        $item=$filas ? tarea_por_id($id) : null;
        if (!$item || !tarea_puede_cambiar_estado($item)) { return false; }
        $lista=tareas_aplicar_accion_en_lista(array($item),$id,$accion,$_POST);
        $nuevo=$lista[0];
        if ($nuevo==$item) { return false; }
        $estados=array('No iniciada'=>'no_iniciada','En proceso'=>'en_proceso','Pausada'=>'pausada','Terminada'=>'terminada');
        emp_consulta('UPDATE tarea SET estado=?, avance=?, fecha_inicio=? WHERE id_tarea=?','sisi',array($estados[$nuevo['estado']],$nuevo['avance'],$nuevo['fecha_inicio'],$id));
        if ($accion==='iniciar') {
            $ids=emp_responsables_ids(array(tareas_persona()));
            emp_consulta('INSERT INTO tarea_inicio (id_tarea,id_empleado) VALUES (?,?) ON DUPLICATE KEY UPDATE id_empleado=VALUES(id_empleado)','ii',array($id,$ids[0]));
        }
        emp_actividad($id,$accion==='terminar' ? 'completada' : 'proceso',$nuevo['titulo']);
        if(function_exists('crm_tarea_sync_padre_de'))
        {
        	crm_tarea_sync_padre_de($id);
        }
        return true;
    });
}
