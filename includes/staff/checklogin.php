<?php
// Control de acceso del personal interno (admin y areas).
function check_login($area=null,$preservar_area_activa=false)
{
	$id_ses=isset($_SESSION['id_empleado']) ? intval($_SESSION['id_empleado']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
	if($id_ses<=0 || !isset($_SESSION['alogin']) || $_SESSION['alogin']==='')
	{
		header('Location: '.url_crm('admin/index.php'));
		exit();
	}
	if($area!==null && $area!=='' && !acceso_puede($area))
	{
		header('Location: '.acceso_destino_area());
		exit();
	}
	if(!$preservar_area_activa && $area!==null && $area!=='' && $area!=='admin' && function_exists('acceso_cambiar_area_activa'))
	{
		$act = function_exists('acceso_area_activa') ? acceso_area_activa() : (isset($_SESSION['area_activa']) ? $_SESSION['area_activa'] : '');
		if($act !== $area && acceso_puede($area))
		{
			acceso_cambiar_area_activa($area);
		}
	}
}
?>
