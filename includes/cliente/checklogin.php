<?php
// Control de acceso de los clientes y responsables (no de la empresa como tal).
function check_login()
{
	if(!isset($_SESSION['login']) || strlen($_SESSION['login'])==0)
	{
		$_SESSION['login']='';
		header('Location: '.url_crm('login.php'));
		exit();
	}
}
?>
