<?php
// Control de acceso del personal interno (admin y areas).
function check_login($area=null)
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
}
?>
