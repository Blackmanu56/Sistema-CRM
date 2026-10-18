<?php
function crm_historial_tarea_accion($tipo)
{
	$acciones=array(
		'creada'=>'Tarea creada',
		'proceso'=>'Estado cambiado a En proceso',
		'completada'=>'Tarea finalizada'
	);
	return isset($acciones[$tipo]) ? $acciones[$tipo] : '';
}

function crm_historial_fecha_legible($fecha)
{
	$fecha=trim((string)$fecha);
	if($fecha==='' || strpos($fecha,'0000-00-00')===0) { return ''; }
	$timestamp=strtotime($fecha);
	return $timestamp===false ? '' : date('d/m/Y H:i', $timestamp);
}
?>
