<?php

/**
 * Computes the existing server-side authorization and area availability for
 * every centralized report. Shared by the report page and navigation so that
 * a menu link never advertises a report the current profile cannot open.
 */
function crm_informes_autorizaciones_usuario()
{
	static $resultado=null;
	if($resultado!==null) { return $resultado; }

	require_once BASE_PATH.'/includes/informes-area-config.php';
	require_once BASE_PATH.'/includes/informes-dominios-scope.php';
	require_once BASE_PATH.'/includes/informes-atenciones-scope.php';

	$crm_informe_area_activa=function_exists('acceso_area_activa') ? acceso_area_activa() : '';
	$crm_informes_configuracion_activa=crm_informes_area_configuracion_clave($crm_informe_area_activa);
	$puede_ver_colaboradores=acceso_puede_ver_informe_colaboradores();
	$puede_ver_presidencia=acceso_puede_ver_informes();
	$crm_informe_actividades_global=acceso_es_super() || acceso_es_coord_general();
	$crm_informe_actividades_areas=array();
	if(!$crm_informe_actividades_global)
	{
		$areas_actividades_disponibles=acceso_consulta("SELECT id_area, clave, nombre FROM area WHERE activo=1 ORDER BY nombre");
		$areas_actividades_coordinadas=array();
		foreach($areas_actividades_disponibles as $area_informe_actividad)
		{
			$clave_area_actividad=(string)$area_informe_actividad['clave'];
			if(acceso_es_coord_de_area($clave_area_actividad))
			{
				$areas_actividades_coordinadas[$clave_area_actividad]=array(
					'id'=>intval($area_informe_actividad['id_area']),
					'id_area'=>intval($area_informe_actividad['id_area']),
					'clave'=>$clave_area_actividad,
					'nombre'=>(string)$area_informe_actividad['nombre']
				);
			}
		}
		if(count($areas_actividades_coordinadas)>0)
		{
			$crm_informe_actividades_areas=$areas_actividades_coordinadas;
			// In several coordinator roles, honor the currently selected context.
			if(count($crm_informe_actividades_areas)>1)
			{
				$contexto_actividad=function_exists('acceso_area_activa') ? acceso_area_activa() : '';
				if($contexto_actividad==='' || !isset($crm_informe_actividades_areas[$contexto_actividad]))
				{
					$principal_actividad=function_exists('acceso_area') ? acceso_area() : '';
					$contexto_actividad=isset($crm_informe_actividades_areas[$principal_actividad]) ? $principal_actividad : (string)key($crm_informe_actividades_areas);
				}
				if(isset($crm_informe_actividades_areas[$contexto_actividad])) { $crm_informe_actividades_areas=array($contexto_actividad=>$crm_informe_actividades_areas[$contexto_actividad]); }
			}
		}
		elseif(function_exists('acceso_es_empleado') && acceso_es_empleado())
		{
			// Read-only report access follows the authorized active area.
			$contexto_actividad=function_exists('acceso_area_activa') ? acceso_area_activa() : '';
			if($contexto_actividad!=='' && function_exists('acceso_puede') && acceso_puede($contexto_actividad))
			{
				foreach($areas_actividades_disponibles as $area_informe_actividad)
				{
					if((string)$area_informe_actividad['clave']===$contexto_actividad)
					{
						$crm_informe_actividades_areas[$contexto_actividad]=array(
							'id'=>intval($area_informe_actividad['id_area']),
							'id_area'=>intval($area_informe_actividad['id_area']),
							'clave'=>$contexto_actividad,
							'nombre'=>(string)$area_informe_actividad['nombre']
						);
						break;
					}
				}
			}
		}
	}
	$puede_ver_actividades=$crm_informe_actividades_global || count($crm_informe_actividades_areas)>0;

	$crm_informe_tareas_global=acceso_es_super() || acceso_es_coord_general();
	$crm_informe_tareas_areas=array();
	$crm_informe_tareas_con_permiso=$crm_informe_tareas_global;
	if(!$crm_informe_tareas_global)
	{
		$clave_tareas_principal=function_exists('acceso_area') ? acceso_area() : '';
		if($clave_tareas_principal!=='')
		{
			$crm_informe_tareas_areas[$clave_tareas_principal]=true;
			$rol_tareas_principal=isset($_SESSION['rol_area']) ? (string)$_SESSION['rol_area'] : '';
			if(in_array($rol_tareas_principal,array('coordinador','coordinacion'),true)) { $crm_informe_tareas_con_permiso=true; }
		}
		foreach(acceso_roles_adicionales_sesion() as $rol_tareas_extra)
		{
			$area_tareas_extra=isset($rol_tareas_extra['area']) ? trim((string)$rol_tareas_extra['area']) : '';
			if($area_tareas_extra!=='') { $crm_informe_tareas_areas[$area_tareas_extra]=true; }
			if($area_tareas_extra!=='' && isset($rol_tareas_extra['rol']) && in_array($rol_tareas_extra['rol'],array('coordinador','coordinacion'),true)) { $crm_informe_tareas_con_permiso=true; }
		}
		if(function_exists('acceso_es_coord_rrhh') && acceso_es_coord_rrhh())
		{
			$crm_informe_tareas_areas['recepcion']=true;
			$crm_informe_tareas_con_permiso=true;
		}
		if(count($crm_informe_tareas_areas)>1)
		{
			$contexto_tareas=function_exists('acceso_area_activa') ? acceso_area_activa() : '';
			if($contexto_tareas==='' || !isset($crm_informe_tareas_areas[$contexto_tareas]))
			{
				$principal_tareas=function_exists('acceso_area') ? acceso_area() : '';
				$contexto_tareas=isset($crm_informe_tareas_areas[$principal_tareas]) ? $principal_tareas : (string)key($crm_informe_tareas_areas);
			}
			if(isset($crm_informe_tareas_areas[$contexto_tareas])) { $crm_informe_tareas_areas=array($contexto_tareas=>true); }
		}
		if(!$crm_informe_tareas_con_permiso && function_exists('crm_id_empleado_sesion'))
		{
			$id_tareas_empleado=crm_id_empleado_sesion();
			$id_tareas_areas=array();
			if(count($crm_informe_tareas_areas)>0)
			{
				$claves_tareas=array_keys($crm_informe_tareas_areas);
				$marcas_tareas=implode(',',array_fill(0,count($claves_tareas),'?'));
				$areas_tareas_rows=acceso_consulta("SELECT id_area, clave FROM area WHERE clave IN ($marcas_tareas)",str_repeat('s',count($claves_tareas)),$claves_tareas);
				foreach($areas_tareas_rows as $area_tareas_row) { $id_tareas_areas[]=intval($area_tareas_row['id_area']); }
			}
			if($id_tareas_empleado>0 && count($id_tareas_areas)>0)
			{
				$marcas_tareas=implode(',',array_map('intval',$id_tareas_areas));
				$crm_informe_tareas_con_permiso=(bool)acceso_consulta_una("SELECT 1 AS autorizado FROM tarea t INNER JOIN tarea_responsable tr ON tr.id_tarea=t.id_tarea WHERE tr.id_empleado=? AND t.id_area IN ($marcas_tareas) AND t.estado<>'cancelada' LIMIT 1",'i',array($id_tareas_empleado));
			}
		}
	}
	$puede_ver_tareas=$crm_informe_tareas_con_permiso;

	$crm_informe_atenciones_global=acceso_es_super() || acceso_es_coord_general();
	$crm_informe_atenciones_areas=array();
	$crm_informe_atenciones_detalle_area_ids=array();
	$crm_informe_atenciones_contexto='';
	if(!$crm_informe_atenciones_global)
	{
		$area_principal_atencion=function_exists('acceso_area') ? acceso_area() : '';
		$areas_reales_atencion=array_fill_keys(crm_informes_atenciones_role_area_keys($area_principal_atencion,acceso_roles_adicionales_sesion()),true);
		foreach(acceso_consulta("SELECT id_area, clave, nombre FROM area WHERE activo=1 ORDER BY nombre") as $area_informe_atencion)
		{
			$clave_area_atencion=(string)$area_informe_atencion['clave'];
			if(isset($areas_reales_atencion[$clave_area_atencion]) && is_file(BASE_PATH.'/public/'.$clave_area_atencion.'/atenciones.php'))
			{
				$area_autorizada_atencion=array(
					'id'=>intval($area_informe_atencion['id_area']),
					'id_area'=>intval($area_informe_atencion['id_area']),
					'clave'=>$clave_area_atencion,
					'nombre'=>(string)$area_informe_atencion['nombre']
				);
				$crm_informe_atenciones_areas[$clave_area_atencion]=$area_autorizada_atencion;
				if(is_file(BASE_PATH.'/public/'.$clave_area_atencion.'/atencion.php')) { $crm_informe_atenciones_detalle_area_ids[]=intval($area_informe_atencion['id_area']); }
			}
		}
		$crm_informe_atenciones_contexto=function_exists('acceso_area_activa') ? acceso_area_activa() : '';
		$crm_informe_atenciones_areas=crm_informes_atenciones_active_area($crm_informe_atenciones_areas,$crm_informe_atenciones_contexto);
		$crm_informe_atenciones_detalle_area_ids=array_values(array_intersect(
			$crm_informe_atenciones_detalle_area_ids,
			array_map(function($area) { return intval($area['id_area']); },$crm_informe_atenciones_areas)
		));
	}
	$puede_ver_atenciones=$crm_informe_atenciones_global || count($crm_informe_atenciones_areas)>0;

	$crm_informe_dominios_global=acceso_es_super() || acceso_es_coord_general();
	$crm_informe_dominios_contexto=function_exists('acceso_area_activa') ? acceso_area_activa() : '';
	$puede_ver_dominios=crm_informes_dominios_puede_consultar(
		$crm_informe_dominios_contexto,
		$crm_informe_dominios_global,
		function_exists('acceso_puede') && acceso_puede('sistemas')
	);

	$crm_informe_clientes_global=(function_exists('crm_area_ve_todo') && crm_area_ve_todo()) || acceso_es_super();
	$crm_informe_clientes_areas=array();
	if(!$crm_informe_clientes_global && !acceso_es_presidencia())
	{
		foreach(acceso_consulta("SELECT id_area, clave, nombre FROM area WHERE activo=1 AND puede_clientes=1 ORDER BY nombre") as $area_informe_cliente)
		{
			$clave_area_informe=(string)$area_informe_cliente['clave'];
			if(acceso_puede($clave_area_informe) && acceso_puede_clientes_de_area($clave_area_informe))
			{
				$crm_informe_clientes_areas[$clave_area_informe]=array(
					'id'=>intval($area_informe_cliente['id_area']),
					'id_area'=>intval($area_informe_cliente['id_area']),
					'clave'=>$clave_area_informe,
					'nombre'=>(string)$area_informe_cliente['nombre']
				);
			}
		}
		if(isset($crm_informe_clientes_areas['recepcion'])) { $crm_informe_clientes_global=true; }
	}
	$puede_ver_clientes=$crm_informe_clientes_global || count($crm_informe_clientes_areas)>0;

	$crm_informe_tiene_permiso_nativo=$puede_ver_colaboradores || $puede_ver_clientes || $puede_ver_actividades || $puede_ver_tareas || $puede_ver_atenciones || $puede_ver_dominios || $puede_ver_presidencia;
	$puede_ver_colaboradores=crm_informes_disponible_para_usuario($crm_informes_configuracion_activa,'colaboradores',$puede_ver_colaboradores);
	$puede_ver_clientes=crm_informes_disponible_para_usuario($crm_informes_configuracion_activa,'clientes',$puede_ver_clientes);
	$puede_ver_actividades=crm_informes_disponible_para_usuario($crm_informes_configuracion_activa,'actividades',$puede_ver_actividades);
	$puede_ver_tareas=crm_informes_disponible_para_usuario($crm_informes_configuracion_activa,'tareas',$puede_ver_tareas);
	$puede_ver_atenciones=crm_informes_disponible_para_usuario($crm_informes_configuracion_activa,'atenciones',$puede_ver_atenciones);

	$crm_informe_tiene_tab_o_destino=$puede_ver_colaboradores || $puede_ver_clientes || $puede_ver_actividades || $puede_ver_tareas || $puede_ver_atenciones || $puede_ver_dominios || $puede_ver_presidencia;
	$resultado=compact(
		'crm_informe_area_activa','crm_informes_configuracion_activa',
		'puede_ver_colaboradores','puede_ver_presidencia','puede_ver_actividades','puede_ver_tareas','puede_ver_atenciones','puede_ver_dominios','puede_ver_clientes',
		'crm_informe_actividades_global','crm_informe_actividades_areas','crm_informe_tareas_global','crm_informe_tareas_areas','crm_informe_tareas_con_permiso',
		'crm_informe_atenciones_global','crm_informe_atenciones_areas','crm_informe_atenciones_detalle_area_ids','crm_informe_atenciones_contexto',
		'crm_informe_dominios_global','crm_informe_dominios_contexto','crm_informe_clientes_global','crm_informe_clientes_areas',
		'crm_informe_tiene_permiso_nativo','crm_informe_tiene_tab_o_destino'
	);
	return $resultado;
}
