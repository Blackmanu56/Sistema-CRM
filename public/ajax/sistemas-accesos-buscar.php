<?php
require_once __DIR__.'/../_init.php';
crm_proteger_staff('sistemas');
acceso_exigir_gestion('sistemas');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$tipo=isset($_GET['tipo']) ? trim((string)$_GET['tipo']) : '';
$q=isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if(strlen($q)<2 || !in_array($tipo, array('empleados','clientes'), true))
{
	echo json_encode(array('ok'=>true, 'resultados'=>array()), JSON_UNESCAPED_UNICODE);
	exit();
}

$like='%'.$q.'%';
$resultados=array();
if($tipo==='empleados')
{
	$filas=acceso_consulta(
		"SELECT e.id_empleado AS id,
			TRIM(CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,''))) AS nombre,
			IFNULL(e.nombre_usuario,'') AS usuario,
			IFNULL(e.dni,'') AS dni,
			IFNULL(a.nombre,'') AS area,
			IFNULL(r.clave,'') AS rol
		FROM empleado e
		INNER JOIN rol r ON r.id_rol=e.id_rol
		LEFT JOIN area a ON a.id_area=e.id_area
		WHERE r.clave<>'superadmin'
		AND (e.nombres LIKE ? OR e.apellido LIKE ? OR CONCAT(e.nombres,' ',e.apellido) LIKE ?
			OR e.nombre_usuario LIKE ? OR e.dni LIKE ? OR e.correo LIKE ?)
		ORDER BY e.apellido, e.nombres LIMIT 12",
		'ssssss',
		array($like,$like,$like,$like,$like,$like)
	);
	foreach($filas as $fila)
	{
		$resultados[]=array(
			'id'=>intval($fila['id']),
			'nombre'=>(string)$fila['nombre'],
			'usuario'=>(string)$fila['usuario'],
			'dni'=>(string)$fila['dni'],
			'area'=>(string)$fila['area']
		);
	}
}
else
{
	$filas=acceso_consulta(
		"SELECT id_usuario AS id, nombre, IFNULL(correo,'') AS correo, IFNULL(dni,'') AS dni
		FROM usuario_cliente
		WHERE nombre LIKE ? OR correo LIKE ? OR dni LIKE ?
		ORDER BY nombre LIMIT 12",
		'sss',
		array($like,$like,$like)
	);
	foreach($filas as $fila)
	{
		$resultados[]=array(
			'id'=>intval($fila['id']),
			'nombre'=>(string)$fila['nombre'],
			'correo'=>(string)$fila['correo'],
			'dni'=>(string)$fila['dni']
		);
	}
}

echo json_encode(array('ok'=>true, 'resultados'=>$resultados), JSON_UNESCAPED_UNICODE);
