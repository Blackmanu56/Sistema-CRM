<?php
// Tareas de Coordinacion de empresas.
// Sigue el contrato del equipo (ver public/comercio/datos-tareas.php): no redefine
// funciones que acceso.php ya define, y completa las funciones CONTRATO que los
// archivos compartidos (tareas-campos-form.php, acceso.php) esperan encontrar.
require_once BASE_PATH.'/includes/tareas_repositorio.php';

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
	if(!isset($_SESSION['tareas_persona']))
	{
		$_SESSION['tareas_persona']='Tito - Empresas';
	}
	// Las tareas de Empresas salen de la base (tabla tarea): ya no se cargan
	// tareas de ejemplo en sesion.
}

function tareas_rol()
{
	return isset($_SESSION['tareas_rol']) ? $_SESSION['tareas_rol'] : 'coordinador';
}

function tareas_persona()
{
	return isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : 'Tito - Empresas';
}

// Lee las tareas que se van a mostrar. Salen de la base (tabla tarea).
function tareas_lista()
{
	global $con;

    $lista=array();

    if(!$con)
    {
        return $lista;
    }

    $sql="SELECT
            t.id_tarea,
            t.titulo,
            t.descripcion,
            t.prioridad,
            t.estado,
            t.fecha_inicio,
            t.fecha_cierre,
            t.avance,
            t.id_cliente,
            t.uid,
            a.clave AS area_clave,
            a.nombre AS area_nombre,
            tt.nombre AS tipo,
            c.nombre AS cliente_nombre
          FROM tarea t
          INNER JOIN area a ON a.id_area=t.id_area
          INNER JOIN tipo_tarea tt ON tt.id_tipo=t.id_tipo
          LEFT JOIN cliente c ON c.id_cliente=t.id_cliente
          WHERE a.clave='empresas'
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
                    'empresas'
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
                'empresas'
            );

            if(!in_array($persona, $iniciaron))
            {
                $iniciaron[]=$persona;
            }
        }
    }
}

$enlaces=array();

$enlace_comentario_disponible=function_exists('crm_tarea_enlace_comentario_preparar') && crm_tarea_enlace_comentario_preparar();
$columna_enlace_comentario=$enlace_comentario_disponible ? 'id_tarea_comentario' : 'NULL';

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
                'id'=>intval($enlace['id']),
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
        tc.texto,
        tc.fecha_carga,
        tc.id,
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
        $quien=$nombre!=='' ? acceso_armar_persona($nombre, 'empresas') : (isset($com['nombre_actor']) && trim($com['nombre_actor'])!=='' ? trim($com['nombre_actor']) : 'Usuario');

        $comentarios[]=array(
            'id'=>intval($com['id']),
            'id_empleado'=>isset($com['id_empleado']) ? intval($com['id_empleado']) : 0,
            'id_usuario_actor'=>isset($com['id_usuario_actor']) ? intval($com['id_usuario_actor']) : 0,
            'texto'=>$com['texto'],
            'quien'=>$quien,
            'cuando'=>date(
                'd/m/Y H:i',
                strtotime($com['fecha_carga'])
            )
        );
    }
}


    $lista[]=array(
        'id'=>intval($row['id_tarea']),
        'titulo'=>$row['titulo'],
        'descripcion'=>$row['descripcion'],
        'area'=>$row['area_nombre'],
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
        'uid'=>$row['uid'],
        'responsables'=>$responsables,
        'iniciaron'=>$iniciaron,
        'enlaces'=>$enlaces,
        'comentarios'=>$comentarios
    );
}

	return $lista;
}

// tareas_guardar(): ya no guarda en sesion. Se deja definida (sin efecto)
// por compatibilidad, igual que en public/comercio/datos-tareas.php.
// El alta y la edicion van a la base via tareas_datos_guardar_nueva() y
// tareas_datos_actualizar().
function tareas_guardar($lista)
{
	// No-op: las tareas de Empresas viven en MySQL.
}

// ---------------------------------------------------------------
// Alta de tareas en base de datos (Empresas).
// includes/acceso.php::tareas_guardar_nueva_desde_form() detecta
// tareas_datos_guardar_nueva() y la usa, asi "Nueva tarea" en Empresas
// queda grabada en la base real (tabla tarea + tarea_responsable + actividad).
// ---------------------------------------------------------------

function emp_area_id($area_clave)
{
	global $con;
	$q=mysqli_query($con, "SELECT id_area FROM area WHERE clave='".acceso_sql($area_clave)."' LIMIT 1");
	if($q && ($row=mysqli_fetch_assoc($q)))
	{
		return intval($row['id_area']);
	}
	return 0;
}

// Busca el id_tipo de tarea para el area por nombre; si no existe, lo crea
// (mismo criterio de "cargar tipo sobre la marcha" que ya usan otras areas).
function emp_tipo_tarea_id($area_clave, $nombre)
{
	global $con;
	$nombre=trim($nombre);
	if($nombre=='')
	{
		$nombre='General';
	}
	$q=mysqli_query(
		$con,
		"SELECT tt.id_tipo
		 FROM tipo_tarea tt
		 INNER JOIN area a ON a.id_area=tt.id_area
		 WHERE a.clave='".acceso_sql($area_clave)."' AND tt.nombre='".acceso_sql($nombre)."'
		 LIMIT 1"
	);
	if($q && ($row=mysqli_fetch_assoc($q)))
	{
		return intval($row['id_tipo']);
	}
	$id_area=emp_area_id($area_clave);
	if($id_area<=0)
	{
		return 0;
	}
	mysqli_query($con, "INSERT INTO tipo_tarea (id_area, nombre) VALUES ('".$id_area."','".acceso_sql($nombre)."')");
	return intval(mysqli_insert_id($con));
}

// Las responsables llegan como texto "Nombre Apellido - Etiqueta de area"
// (acceso_armar_persona). Para guardar tarea_responsable hace falta el
// id_empleado real, asi que se busca por nombre+apellido entre los
// empleados activos.
function emp_empleado_id_por_persona($persona)
{
	global $con;
	$nombre=trim(preg_replace('/\s*-\s*[^-]+$/', '', $persona));
	if($nombre=='')
	{
		return 0;
	}
	$q=mysqli_query(
		$con,
		"SELECT id_empleado FROM empleado
		 WHERE activo=1 AND CONCAT(nombres,' ',apellido)='".acceso_sql($nombre)."'
		 LIMIT 1"
	);
	if($q && ($row=mysqli_fetch_assoc($q)))
	{
		return intval($row['id_empleado']);
	}
	return 0;
}

function emp_prioridad_bd($prioridad)
{
	$p=strtolower(trim($prioridad));
	if(in_array($p, array('urgente','alta','media','baja')))
	{
		return $p;
	}
	return 'media';
}

// Alta real de una tarea de Empresas: INSERT directo a la base, sin pasar
// por la capa de sesion. Devuelve $area (mismo contrato que espera
// tareas_guardar_nueva_desde_form en includes/acceso.php).
function tareas_datos_guardar_nueva($area)
{
	global $con;

	if(!$con)
	{
		return $area;
	}

	$id_area=emp_area_id($area);

	if($id_area<=0)
	{
		return $area;
	}

	$responsables=tareas_leer_responsables_post($area);
	$cliente=tareas_leer_cliente_post();
	$titulo=isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
	$descripcion=isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
	$id_tipo=emp_tipo_tarea_id($area, isset($_POST['tipo']) ? $_POST['tipo'] : '');
	$prioridad=emp_prioridad_bd(isset($_POST['prioridad']) ? $_POST['prioridad'] : 'Media');
	$fecha_inicio=(isset($_POST['fecha_inicio']) && $_POST['fecha_inicio']!='') ? $_POST['fecha_inicio'] : date('Y-m-d');
	$fecha_cierre=(isset($_POST['fecha_cierre']) && $_POST['fecha_cierre']!='') ? $_POST['fecha_cierre'] : date('Y-m-d', strtotime($fecha_inicio.' +7 days'));

	if(strtotime($fecha_cierre) < strtotime($fecha_inicio))
	{
		$fecha_cierre=$fecha_inicio;
	}

	if($titulo=='' || $id_tipo<=0)
	{
		return $area;
	}

	$id_cliente_sql=($cliente['id']>0) ? ("'".intval($cliente['id'])."'") : 'NULL';

	$sql="INSERT INTO tarea (titulo, descripcion, id_area, id_tipo, prioridad, estado, fecha_inicio, fecha_cierre, avance, id_cliente)
	      VALUES (
	        '".acceso_sql($titulo)."',
	        '".acceso_sql($descripcion)."',
	        '".$id_area."',
	        '".$id_tipo."',
	        '".$prioridad."',
	        'no_iniciada',
	        '".acceso_sql($fecha_inicio)."',
	        '".acceso_sql($fecha_cierre)."',
	        0,
	        ".$id_cliente_sql."
	      )";

	if(!mysqli_query($con, $sql))
	{
		return $area;
	}

	$id_tarea=intval(mysqli_insert_id($con));
	if(function_exists('crm_aplicar_eje_post'))
	{
		crm_aplicar_eje_post($id_tarea, $id_tipo);
	}

	foreach($responsables as $i=>$persona)
	{
		$id_empleado=emp_empleado_id_por_persona($persona);
		if($id_empleado<=0)
		{
			continue;
		}
		mysqli_query(
			$con,
			"INSERT IGNORE INTO tarea_responsable (id_tarea, id_empleado, es_principal)
			 VALUES ('".$id_tarea."','".$id_empleado."','".($i==0 ? 1 : 0)."')"
		);
	}

	mysqli_query($con, "INSERT INTO actividad (id_tarea, tipo, descripcion) VALUES ('".$id_tarea."', 'creada', 'Tarea creada')");

	tareas_avisos_registrar($responsables, $titulo, $id_tarea, $area);

	return $area;
}

// ---------------------------------------------------------------
// Edicion de tareas en base de datos (Empresas).
// Mismo criterio que tareas_datos_guardar_nueva() de arriba:
// includes/acceso.php::tareas_actualizar_desde_form($id) la detecta
// automaticamente y, si existe, hace que "Editar tarea" en Empresas
// guarde los cambios en la base real (tarea + tarea_responsable) en
// vez de en sesion. No se toco la edicion de las demas areas.
// ---------------------------------------------------------------

function tareas_datos_actualizar($id)
{
	global $con;

	$id=intval($id);

	if(!$con || $id<=0)
	{
		return false;
	}

	$area='empresas';
	$tarea=tarea_por_id($id);

	if($tarea==null || !tarea_puede_editar($tarea))
	{
		return false;
	}

	$id_area=emp_area_id($area);

	if($id_area<=0)
	{
		return false;
	}

	$responsables=tareas_leer_responsables_post($area, $tarea);
	$cliente=tareas_leer_cliente_post();
	$titulo=isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
	$descripcion=isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
	$id_tipo=emp_tipo_tarea_id($area, isset($_POST['tipo']) ? $_POST['tipo'] : '');
	$prioridad=emp_prioridad_bd(isset($_POST['prioridad']) ? $_POST['prioridad'] : 'Media');
	$fecha_inicio=(isset($_POST['fecha_inicio']) && $_POST['fecha_inicio']!='') ? $_POST['fecha_inicio'] : $tarea['fecha_inicio'];
	$fecha_cierre=(isset($_POST['fecha_cierre']) && $_POST['fecha_cierre']!='') ? $_POST['fecha_cierre'] : $tarea['fecha_cierre'];

	if(strtotime($fecha_cierre) < strtotime($fecha_inicio))
	{
		$fecha_cierre=$fecha_inicio;
	}

	if($titulo=='' || $id_tipo<=0)
	{
		return false;
	}

	$id_cliente_sql=($cliente['id']>0) ? ("'".intval($cliente['id'])."'") : 'NULL';

	$sql="UPDATE tarea SET
	        titulo='".acceso_sql($titulo)."',
	        descripcion='".acceso_sql($descripcion)."',
	        id_tipo='".$id_tipo."',
	        prioridad='".$prioridad."',
	        fecha_inicio='".acceso_sql($fecha_inicio)."',
	        fecha_cierre='".acceso_sql($fecha_cierre)."',
	        id_cliente=".$id_cliente_sql."
	      WHERE id_tarea='".$id."' AND id_area='".$id_area."'";

	if(!mysqli_query($con, $sql))
	{
		return false;
	}

	// Responsables: se borran los anteriores y se cargan de nuevo (mismo
	// criterio que usa el resto del equipo en su capa compartida al editar).
	mysqli_query($con, "DELETE FROM tarea_responsable WHERE id_tarea='".$id."'");

	foreach($responsables as $i=>$persona)
	{
		$id_empleado=emp_empleado_id_por_persona($persona);
		if($id_empleado<=0)
		{
			continue;
		}
		mysqli_query(
			$con,
			"INSERT IGNORE INTO tarea_responsable (id_tarea, id_empleado, es_principal)
			 VALUES ('".$id."','".$id_empleado."','".($i==0 ? 1 : 0)."')"
		);
	}

	return true;
}

// ============================================================
// CONTRATO: funciones que los archivos compartidos esperan
// (tareas-campos-form.php y includes/acceso.php). Se agregan hoy
// porque no estaban completas; el resto de este archivo (alta,
// edicion, listado) ya funcionaba y no se toco.
// ============================================================

// 1) Tipos de tarea del area, para el desplegable del formulario.
//    Reemplaza la lista generica de acceso.php por los tipos reales de Empresas.
function tareas_tipos_de_area($area)
{
	if($area!=='empresas')
	{
		return array();
	}
	global $con;
	$id_area=emp_area_id($area);
	if($id_area<=0 || !$con)
	{
		return array();
	}
	$opciones=array();
	$stmt=@mysqli_prepare($con, "SELECT nombre FROM tipo_tarea WHERE id_area=? ORDER BY nombre");
	if($stmt)
	{
		@mysqli_stmt_bind_param($stmt, 'i', $id_area);
		@mysqli_stmt_execute($stmt);
		$result=@mysqli_stmt_get_result($stmt);
		while($result && ($row=@mysqli_fetch_assoc($result)))
		{
			$opciones[]=$row['nombre'];
		}
		@mysqli_stmt_close($stmt);
	}
	return $opciones;
}

// 2) Boton "Cargar tipo" en el formulario: Empresas no lo necesita, porque
//    tareas_datos_guardar_nueva()/tareas_datos_actualizar() ya crean el tipo
//    sobre la marcha (ver emp_tipo_tarea_id) si no existe todavia.
function tareas_puede_cargar_tipo()
{
	return !acceso_es_empleado();
}

// 3) Fecha de cierre obligatoria en el formulario: Empresas ya la completa
//    automaticamente si viene vacia (ver tareas_datos_guardar_nueva), asi
//    que no hace falta exigirla en el HTML.
function tareas_fecha_cierre_obligatoria()
{
	return false;
}

// 4) Procesar "Cargar tipo": no aplica en Empresas (ver punto 2). Se deja
//    definida, sin efecto, solo para cumplir el contrato.
function tareas_aplicar_carga_tipo($tarea)
{
	if(!isset($_POST['cargar_tipo']))
	{
		return is_array($tarea) ? $tarea : array();
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
	$nombre=isset($_POST['tipo_nuevo']) ? trim($_POST['tipo_nuevo']) : '';
	if($nombre==='')
	{
		tareas_mensaje_form_set('Escribí el nombre del objetivo a cargar.');
		return $tarea;
	}
	$cargado=tareas_objetivo_guardar('empresas', $nombre);
	if($cargado!='')
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

// 5) Guardar "extras" (enlace o comentario nuevo) desde Ver tarea.
//    Antes de hoy esta funcion no existia: los botones de Ver tarea se
//    mostraban pero no hacian nada para Empresas.
function tareas_datos_guardar_extras($id)
{
	$id=intval($id);
	if($id<=0)
	{
		return false;
	}
	$tarea=tarea_por_id($id);
	if($tarea===null)
	{
		return false;
	}
	if(!function_exists('tarea_puede_agregar_extras') || !tarea_puede_agregar_extras($tarea))
	{
		return false;
	}
	$id_empleado=emp_empleado_id_por_persona(tareas_persona());
	if(isset($_POST['agregar_enlace']))
	{
		$url=trim(isset($_POST['enlace_nuevo']) ? $_POST['enlace_nuevo'] : '');
		if($url!=='')
		{
			repo_tarea_agregar_enlace($id, $url);
		}
	}
	if(isset($_POST['agregar_comentario']))
	{
		$txt=trim(isset($_POST['comentario_nuevo']) ? $_POST['comentario_nuevo'] : '');
		if($txt!=='' && $id_empleado>0)
		{
			repo_tarea_agregar_comentario($id, $id_empleado, $txt);
		}
	}
	return true;
}

// 6) Procesar acciones de Ver tarea / Tareas: iniciar, pausar, reanudar,
//    terminar, y tambien agregar enlace/comentario (delega en el punto 5).
//    Antes de hoy tampoco existia: los botones "Iniciar/Pausar/Terminar"
//    se mostraban en Ver tarea pero clickearlos no hacia nada.
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
	$id=intval($_POST['id_tarea']);
	$accion=$_POST['accion_tarea'];
	$mapa=array('iniciar'=>'en_proceso','pausar'=>'pausada','reanudar'=>'en_proceso','terminar'=>'terminada');
	if(!isset($mapa[$accion]))
	{
		return false;
	}
	$tarea=tarea_por_id($id);
	if($tarea===null)
	{
		return false;
	}
	if(!function_exists('tarea_puede_cambiar_estado') || !tarea_puede_cambiar_estado($tarea))
	{
		return false;
	}
	$id_empleado=emp_empleado_id_por_persona(tareas_persona());
	return repo_tarea_cambiar_estado($id, $mapa[$accion], $id_empleado);
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
          WHERE a.clave='empresas'
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
	foreach(tareas_visibles() as $tarea)
	{
		if($tarea['id']==$id)
		{
			return $tarea;
		}
	}
	return null;
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
?>
