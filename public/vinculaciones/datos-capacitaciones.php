<?php
// Capacitaciones de vinculaciones.


function capacitaciones_ejemplo()
{
	return array();
}

function capacitaciones_iniciar()
{
	if(!isset($_SESSION['vin_capacitaciones']) || !is_array($_SESSION['vin_capacitaciones']) || !isset($_SESSION['vin_capacitaciones_v']) || $_SESSION['vin_capacitaciones_v']<2)
	{
		$_SESSION['vin_capacitaciones']=capacitaciones_ejemplo();
		$_SESSION['vin_capacitaciones_v']=2;
	}
}

function capacitaciones_lista()
{
	capacitaciones_iniciar();
	return $_SESSION['vin_capacitaciones'];
}

function capacitaciones_siguiente_id()
{
	$max=0;
	foreach(capacitaciones_lista() as $item)
	{
		if(isset($item['id']) && $item['id']>$max)
		{
			$max=$item['id'];
		}
	}
	return $max+1;
}

function capacitaciones_guardar_nueva()
{
	$responsable=isset($_POST['responsable']) ? $_POST['responsable'] : '';
	if(acceso_es_empleado())
	{
		$responsable=isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : '';
	}
	else
	{
		$ok=acceso_nombres_equipo('vinculaciones');
		if(!in_array($responsable, $ok))
		{
			$responsable=isset($ok[0]) ? $ok[0] : $responsable;
		}
	}
	$lista=capacitaciones_lista();
	$lista[]=array(
		'id'=>capacitaciones_siguiente_id(),
		'titulo'=>isset($_POST['titulo']) ? $_POST['titulo'] : '',
		'descripcion'=>isset($_POST['descripcion']) ? $_POST['descripcion'] : '',
		'fecha'=>isset($_POST['fecha']) ? $_POST['fecha'] : '',
		'responsable'=>$responsable
	);
	$_SESSION['vin_capacitaciones']=$lista;
}
