<?php
// Provincias y localidades (tablas provincia y localidad).
// Las carga Coordinacion general.

function provincias_todas()
{
	global $con;
	$salida=array();
	if(!$con)
	{
		return $salida;
	}
	$q=@mysqli_query($con,"SELECT id_provincia, nombre FROM provincia ORDER BY nombre");
	if(!$q)
	{
		return $salida;
	}
	while($row=mysqli_fetch_assoc($q))
	{
		$salida[]=$row;
	}
	return $salida;
}

function localidades_de_provincia($id_provincia)
{
	global $con;
	$salida=array();
	$id=intval($id_provincia);
	if(!$con || $id<=0)
	{
		return $salida;
	}
	$q=@mysqli_query($con,"SELECT id_localidad, nombre, id_provincia FROM localidad WHERE id_provincia='".$id."' ORDER BY nombre");
	if(!$q)
	{
		return $salida;
	}
	while($row=mysqli_fetch_assoc($q))
	{
		$salida[]=$row;
	}
	return $salida;
}

function provincia_guardar($nombre)
{
	global $con;
	$nombre=trim($nombre);
	if($nombre=='' || !$con)
	{
		return 'Falta el nombre de la provincia.';
	}
	$q=@mysqli_query($con,"INSERT INTO provincia (nombre) VALUES ('".acceso_sql($nombre)."')");
	if(!$q)
	{
		return 'Esa provincia ya está cargada o no se pudo guardar.';
	}
	return '';
}

function localidad_guardar($nombre, $id_provincia)
{
	global $con;
	$nombre=trim($nombre);
	$id=intval($id_provincia);
	if($nombre=='' || $id<=0 || !$con)
	{
		return 'Falta la localidad o la provincia.';
	}
	$hay=@mysqli_query($con,"SELECT id_provincia FROM provincia WHERE id_provincia='".$id."' LIMIT 1");
	if(!$hay || mysqli_num_rows($hay)==0)
	{
		return 'La provincia no existe.';
	}
	$q=@mysqli_query($con,"INSERT INTO localidad (nombre, id_provincia) VALUES ('".acceso_sql($nombre)."','".$id."')");
	if(!$q)
	{
		return 'Esa localidad ya está en esa provincia o no se pudo guardar.';
	}
	return '';
}

function provincia_actualizar($id_provincia, $nombre)
{
	global $con;
	$id=intval($id_provincia);
	$nombre=trim((string)$nombre);
	if($id<=0 || !$con)
	{
		return 'Provincia inválida.';
	}
	if($nombre==='')
	{
		return 'El nombre de la provincia no puede estar vacío.';
	}
	$dup=@mysqli_query($con, "SELECT id_provincia FROM provincia WHERE nombre='".acceso_sql($nombre)."' AND id_provincia<>'".$id."' LIMIT 1");
	if($dup && mysqli_num_rows($dup)>0)
	{
		return 'Ya existe otra provincia con el nombre “'.htmlspecialchars($nombre).'”.';
	}
	$ok=@mysqli_query($con, "UPDATE provincia SET nombre='".acceso_sql($nombre)."' WHERE id_provincia='".$id."'");
	if(!$ok)
	{
		return 'No se pudo actualizar la provincia.';
	}
	return '';
}

function provincia_eliminar($id_provincia)
{
	global $con;
	$id=intval($id_provincia);
	if($id<=0 || !$con)
	{
		return 'Provincia inválida.';
	}
	$locs=@mysqli_query($con, "SELECT COUNT(*) AS n FROM localidad WHERE id_provincia='".$id."'");
	$row_l=$locs ? mysqli_fetch_assoc($locs) : null;
	$n_locs=$row_l ? intval($row_l['n']) : 0;
	if($n_locs>0)
	{
		return 'No se puede eliminar la provincia porque tiene '.$n_locs.' localidad(es) asociada(s). Primero eliminá o reasigná esas localidades.';
	}
	$ok=@mysqli_query($con, "DELETE FROM provincia WHERE id_provincia='".$id."'");
	if(!$ok)
	{
		return 'No se pudo eliminar la provincia.';
	}
	return '';
}

function localidad_actualizar($id_localidad, $nombre)
{
	global $con;
	$id=intval($id_localidad);
	$nombre=trim((string)$nombre);
	if($id<=0 || !$con)
	{
		return 'Localidad inválida.';
	}
	if($nombre==='')
	{
		return 'El nombre de la localidad no puede estar vacío.';
	}
	$curr=@mysqli_query($con, "SELECT id_provincia FROM localidad WHERE id_localidad='".$id."' LIMIT 1");
	$row_c=$curr ? mysqli_fetch_assoc($curr) : null;
	if(!$row_c)
	{
		return 'La localidad no existe.';
	}
	$id_prov=intval($row_c['id_provincia']);
	$dup=@mysqli_query($con, "SELECT id_localidad FROM localidad WHERE nombre='".acceso_sql($nombre)."' AND id_provincia='".$id_prov."' AND id_localidad<>'".$id."' LIMIT 1");
	if($dup && mysqli_num_rows($dup)>0)
	{
		return 'Ya existe otra localidad con el nombre “'.htmlspecialchars($nombre).'” en esta provincia.';
	}
	$ok=@mysqli_query($con, "UPDATE localidad SET nombre='".acceso_sql($nombre)."' WHERE id_localidad='".$id."'");
	if(!$ok)
	{
		return 'No se pudo actualizar la localidad.';
	}
	return '';
}

function localidad_eliminar($id_localidad)
{
	global $con;
	$id=intval($id_localidad);
	if($id<=0 || !$con)
	{
		return 'Localidad inválida.';
	}
	$clis=@mysqli_query($con, "SELECT COUNT(*) AS n FROM cliente WHERE id_localidad='".$id."'");
	$row_c=$clis ? mysqli_fetch_assoc($clis) : null;
	$n_clis=$row_c ? intval($row_c['n']) : 0;
	if($n_clis>0)
	{
		return 'No se puede eliminar la localidad porque está asignada a '.$n_clis.' cliente(s).';
	}
	$ok=@mysqli_query($con, "DELETE FROM localidad WHERE id_localidad='".$id."'");
	if(!$ok)
	{
		return 'No se pudo eliminar la localidad.';
	}
	return '';
}
