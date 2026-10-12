<?php
// Dashboard útil por alcance: empleado / área / todo.
// Resumen del día: turnos, tareas de hoy, vencidas, en proceso.
require_once BASE_PATH.'/includes/informes.php';

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
	$area_act=acceso_area();
}

$alcance=crm_dashboard_alcance();
// Coordinador general / presidencia / super: siempre vista global (no la carpeta "general").
if(function_exists('acceso_es_coord_general') && (acceso_es_coord_general() || acceso_es_presidencia() || acceso_es_super() || acceso_es_general()))
{
	if($area_act!=='sistemas')
	{
		$alcance='todo';
	}
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
	if(function_exists('acceso_es_empleado') && acceso_es_empleado())
	{
		$alcance='empleado';
	}
	else
	{
		$alcance='area';
	}
	$filtro='sistemas';
	$area_links='sistemas';
}
else
{
	// Si estamos en /general/ o el área de sesión no es operativa, anclar links a una área real.
	$area_links=$area_act;
	if($alcance==='todo' || !isset($areas_operativas[$area_links]))
	{
		$mia=acceso_area();
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
	$filtro=isset($areas_operativas[$area_act]) ? $area_act : (isset($areas_operativas[acceso_area()]) ? acceso_area() : 'todas');
	if($filtro==='todas')
	{
		// Coord de área sin área operativa válida: mejor mostrar todo a que nada.
		$alcance='todo';
	}
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
			WHERE t.estado<>'cancelada'
			ORDER BY FIELD(t.estado,'en_proceso','no_iniciada','pausada','terminada'), t.fecha_cierre ASC",
			'i',
			array($id_yo)
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

$n_hoy_tar=isset($tot_hv) && $tot_hv ? intval($tot_hv['hoy']) : count($tareas_hoy);
$n_venc=isset($tot_hv) && $tot_hv ? intval($tot_hv['venc']) : count($tareas_vencidas);
$n_proc=isset($cont['En proceso']) ? intval($cont['En proceso']) : count($tareas_proceso);
$n_turnos=($area_act==='sistemas') ? 0 : count($turnos_hoy);

$menu_activo='dashboard';
$nombre_vista=($alcance=='todo') ? 'Todas las áreas' : (($alcance=='empleado') ? 'Mis actividades' : $titulo_area);
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
                <div class="page-title">
                    <h3>Inicio · <?php echo htmlspecialchars($nombre_vista); ?></h3>
                    <p class="muted" style="margin:0;">Hoy <?php echo date('d/m/Y'); ?> · lo que necesita atención ahora</p>
                    <?php if($area_act==='recepcion') { ?>
                    <p style="margin-top:10px;">
                        <a class="btn btn-primary btn-cons" href="<?php echo url_crm('recepcion/alta-cliente.php'); ?>">Nuevo cliente</a>
                        <a class="btn btn-primary btn-cons" href="<?php echo url_crm('recepcion/nueva-atencion.php'); ?>">Agendar atención</a>
                    </p>
                    <?php } ?>
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
                        <div class="tiles-title">Agenda de hoy</div>
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
                        <div class="grid simple">
                            <div class="grid-title"><h4>Agenda de hoy</h4></div>
                            <div class="grid-body">
                                <?php if(count($turnos_hoy)==0) { ?><p class="muted">No hay atenciones abiertas para hoy.</p><?php } ?>
                                <?php foreach($turnos_hoy as $t) {
                                	$ft=isset($t['fecha_ref']) ? $t['fecha_ref'] : '';
                                	$area_t=($area_act==='recepcion') ? 'recepcion' : (isset($t['area_clave']) ? $t['area_clave'] : $area_act);
                                ?>
                                <p>
                                    <a href="<?php echo url_crm($area_t.'/atencion.php?id='.intval($t['id_atencion'])); ?>">
                                        #<?php echo intval($t['id_atencion']); ?> · <?php echo htmlspecialchars($t['cliente_nombre']); ?>
                                    </a>
                                    · <?php echo htmlspecialchars(function_exists('crm_estado_texto') ? crm_estado_texto($t['estado']) : $t['estado']); ?>
                                    <?php if($alcance==='todo' || $area_act==='recepcion') { ?> · <?php echo htmlspecialchars($t['area_nombre']); ?><?php } ?>
                                    <?php if($ft!='') { ?> · <?php echo htmlspecialchars(function_exists('crm_turno_fecha_texto') ? crm_turno_fecha_texto($ft) : $ft); ?><?php } ?>
                                </p>
                                <?php } ?>
                                <p>
                                    <a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($base_aten); ?>">Ver atenciones</a>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    <div class="<?php echo ($area_act==='sistemas') ? 'col-md-12' : 'col-md-6'; ?>">
                        <div class="grid simple">
                            <div class="grid-title"><h4>Tareas que vencen hoy</h4></div>
                            <div class="grid-body">
                                <?php if(count($tareas_hoy)==0) { ?><p class="muted">Nada vence hoy.</p><?php } ?>
                                <?php foreach($tareas_hoy as $t) { ?>
                                <p>
                                    <a href="<?php echo url_crm($t['area_clave'].'/ver-tarea.php?id='.intval($t['id_tarea'])); ?>"><?php echo htmlspecialchars($t['titulo']); ?></a>
                                    · <?php echo htmlspecialchars(dash_estado_txt($t['estado'])); ?>
                                    · <?php echo intval($t['avance']); ?>%
                                </p>
                                <?php } ?>
                                <p><a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($link_tareas(array('vence'=>'hoy'))); ?>">Ver las que vencen hoy</a></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="grid simple">
                            <div class="grid-title"><h4>Vencidas (sin cerrar)</h4></div>
                            <div class="grid-body">
                                <?php if(count($tareas_vencidas)==0) { ?><p class="muted">No hay tareas vencidas.</p><?php } ?>
                                <?php foreach($tareas_vencidas as $t) { ?>
                                <p>
                                    <a href="<?php echo url_crm($t['area_clave'].'/ver-tarea.php?id='.intval($t['id_tarea'])); ?>"><?php echo htmlspecialchars($t['titulo']); ?></a>
                                    · cierre <?php echo htmlspecialchars(date('d/m/Y', strtotime($t['fecha_cierre']))); ?>
                                    · <?php echo htmlspecialchars(dash_estado_txt($t['estado'])); ?>
                                </p>
                                <?php } ?>
                                <p><a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($link_tareas(array('estado'=>'Vencida'))); ?>">Ver vencidas</a></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="grid simple">
                            <div class="grid-title"><h4><?php echo ($alcance==='empleado') ? 'Mis tareas' : 'En proceso'; ?></h4></div>
                            <div class="grid-body">
                                <?php
                                $lista_der=($alcance==='empleado') ? $mias : $tareas_proceso;
                                if(count($lista_der)==0) { ?><p class="muted">Nada en curso.</p><?php }
                                $n=0;
                                foreach($lista_der as $t) {
                                	if($n>=10) break;
                                	$n++;
                                ?>
                                <p>
                                    <a href="<?php echo url_crm($t['area_clave'].'/ver-tarea.php?id='.intval($t['id_tarea'])); ?>"><?php echo htmlspecialchars($t['titulo']); ?></a>
                                    · <?php echo htmlspecialchars(dash_estado_txt($t['estado'])); ?>
                                    · <?php echo intval($t['avance']); ?>%
                                </p>
                                <?php } ?>
                                <p><a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($link_tareas($alcance==='empleado' ? array() : array('estado'=>'En proceso'))); ?>"><?php echo ($alcance==='empleado') ? 'Ver mis tareas' : 'Ver en proceso'; ?></a></p>
                            </div>
                        </div>
                    </div>
                </div>

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

                <?php if(count($avisos)>0) { ?>
                <div class="grid simple">
                    <div class="grid-title"><h4>Avisos</h4></div>
                    <div class="grid-body">
                        <?php foreach($avisos as $a) { ?>
                        <p>
                            <strong><?php echo htmlspecialchars(isset($a['titulo']) ? $a['titulo'] : ''); ?></strong>
                            <?php if(!empty($a['detalle'])) { ?> — <?php echo htmlspecialchars($a['detalle']); ?><?php } ?>
                            <?php if(!empty($a['url'])) { ?> · <a href="<?php echo htmlspecialchars($a['url']); ?>">Abrir</a><?php } ?>
                        </p>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>

                <div class="grid simple">
                    <div class="grid-title"><h4>Resumen de estados</h4></div>
                    <div class="grid-body">
                        <p>
                            Total de tareas: <strong><?php echo intval(isset($cont['total']) ? $cont['total'] : 0); ?></strong>
                            · No iniciadas: <?php echo intval(isset($cont['No iniciada']) ? $cont['No iniciada'] : 0); ?>
                            · En proceso: <?php echo intval(isset($cont['En proceso']) ? $cont['En proceso'] : 0); ?>
                            · Pausadas: <?php echo intval(isset($cont['Pausada']) ? $cont['Pausada'] : 0); ?>
                            · Terminadas: <?php echo intval(isset($cont['Terminada']) ? $cont['Terminada'] : 0); ?>
                            · Vencidas: <?php echo intval(isset($cont['Vencida']) ? $cont['Vencida'] : 0); ?>
                        </p>
                        <p>
                            <a class="btn btn-primary" href="<?php echo htmlspecialchars($base_tareas); ?>">Tareas</a>
                            <a class="btn btn-white" href="<?php echo url_crm(($area_act!='' ? $area_act : 'general').'/actividades.php'); ?>">Actividades</a>
                            <?php if($alcance==='todo') { ?>
                            <a class="btn btn-white" href="<?php echo url_crm('admin/home.php'); ?>">Informes</a>
                            <?php } ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
 </div>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
</body>
</html>
