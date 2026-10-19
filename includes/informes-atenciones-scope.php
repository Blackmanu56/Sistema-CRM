<?php
/**
 * Construye el predicado parametrizado de visibilidad para el informe de atenciones.
 * Una atención pertenece al área responsable actual (atencion.id_area).
 */
if(!function_exists('crm_informes_atenciones_role_area_keys'))
{
	function crm_informes_atenciones_role_area_keys($primary_area, $additional_roles)
	{
		$keys=array();
		$primary=trim((string)$primary_area);
		if($primary!=='') { $keys[$primary]=true; }
		if(is_array($additional_roles))
		{
			foreach($additional_roles as $role)
			{
				$area=is_array($role) && isset($role['area']) ? trim((string)$role['area']) : '';
				if($area!=='') { $keys[$area]=true; }
			}
		}
		return array_keys($keys);
	}
}

if(!function_exists('crm_informes_atenciones_active_area'))
{
	/** Restringe un conjunto de áreas autorizadas al área activa elegida en la sesión. */
	function crm_informes_atenciones_active_area($authorized_areas, $active_key)
	{
		$active_key=trim((string)$active_key);
		if($active_key==='' || !is_array($authorized_areas) || !isset($authorized_areas[$active_key]))
		{
			return array();
		}
		return array($active_key=>$authorized_areas[$active_key]);
	}
}

if(!function_exists('crm_informes_atenciones_scope_condition'))
{
	function crm_informes_atenciones_scope_condition($area_ids, $global)
	{
		if($global)
		{
			return array('sql'=>'1=1','types'=>'','params'=>array());
		}

		$ids=array_values(array_unique(array_filter(array_map('intval',is_array($area_ids) ? $area_ids : array()),function($id) { return $id>0; })));
		if(count($ids)===0)
		{
			return array('sql'=>'1=0','types'=>'','params'=>array());
		}

		$marks=implode(',',array_fill(0,count($ids),'?'));
		return array(
			'sql'=>'at.id_area IN ('.$marks.')',
			'types'=>str_repeat('i',count($ids)),
			'params'=>$ids
		);
	}
}
