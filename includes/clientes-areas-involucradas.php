<?php
// Áreas con intervenciones o relaciones institucionales verificables del cliente.
$cliente_areas_involucradas=array();
$cliente_agregar_area=function($clave,$nombre) use (&$cliente_areas_involucradas) {
	$clave=trim((string)$clave);
	$nombre=trim((string)$nombre);
	if($clave==='' || $nombre==='') { return; }
	$es_global=function_exists('acceso_es_general') && acceso_es_general();
	if(!$es_global && (!function_exists('acceso_puede') || !acceso_puede($clave))) { return; }
	$cliente_areas_involucradas[$clave]=$nombre;
};
$cliente_agregar_resultados=function($sql,$types,$values) use ($cliente_agregar_area) {
	$filas=acceso_consulta($sql,$types,$values);
	foreach($filas as $fila)
	{
		$cliente_agregar_area(isset($fila['clave']) ? $fila['clave'] : '',isset($fila['nombre']) ? $fila['nombre'] : '');
	}
};

if(acceso_tabla_existe('atencion'))
{
	$cliente_agregar_resultados('SELECT DISTINCT a.clave,a.nombre FROM atencion t INNER JOIN area a ON a.id_area=t.id_area WHERE t.id_cliente=?', 'i', array(intval($id)));
	if(function_exists('crm_columna_existe') && crm_columna_existe('atencion','id_atencion_origen'))
	{
		$cliente_agregar_resultados('SELECT DISTINCT ao.clave,ao.nombre FROM atencion t INNER JOIN atencion origen ON origen.id_atencion=t.id_atencion_origen INNER JOIN area ao ON ao.id_area=origen.id_area WHERE t.id_cliente=?', 'i', array(intval($id)));
	}
	if(acceso_tabla_existe('atencion_seguimiento'))
	{
		$cliente_agregar_resultados('SELECT DISTINCT a.clave,a.nombre FROM atencion_seguimiento s INNER JOIN atencion t ON t.id_atencion=s.id_atencion INNER JOIN area a ON a.id_area=s.id_area WHERE t.id_cliente=?', 'i', array(intval($id)));
	}
}
if(acceso_tabla_existe('tarea'))
{
	$cliente_agregar_resultados("SELECT DISTINCT a.clave,a.nombre FROM tarea t INNER JOIN area a ON a.id_area=t.id_area WHERE t.id_cliente=? AND LOWER(t.estado) NOT IN ('cancelada','eliminada')", 'i', array(intval($id)));
}
if(acceso_tabla_existe('evento'))
{
	if(acceso_tabla_existe('evento_inscripcion'))
	{
		$cliente_agregar_resultados('SELECT DISTINCT a.clave,a.nombre FROM evento e INNER JOIN area a ON a.id_area=e.id_area LEFT JOIN evento_inscripcion i ON i.id_evento=e.id_evento WHERE e.id_cliente=? OR i.id_cliente=?', 'ii', array(intval($id),intval($id)));
	}
	else
	{
		$cliente_agregar_resultados('SELECT DISTINCT a.clave,a.nombre FROM evento e INNER JOIN area a ON a.id_area=e.id_area WHERE e.id_cliente=?', 'i', array(intval($id)));
	}
	if(acceso_tabla_existe('evento_tarea') && acceso_tabla_existe('tarea'))
	{
		$cliente_agregar_resultados('SELECT DISTINCT a.clave,a.nombre FROM evento e INNER JOIN area a ON a.id_area=e.id_area INNER JOIN evento_tarea et ON et.id_evento=e.id_evento INNER JOIN tarea t ON t.id_tarea=et.id_tarea WHERE t.id_cliente=? AND LOWER(t.estado) NOT IN (\'cancelada\',\'eliminada\')', 'i', array(intval($id)));
		$cliente_agregar_resultados('SELECT DISTINCT a.clave,a.nombre FROM evento_tarea et INNER JOIN tarea t ON t.id_tarea=et.id_tarea INNER JOIN area a ON a.id_area=t.id_area WHERE t.id_cliente=? AND LOWER(t.estado) NOT IN (\'cancelada\',\'eliminada\')', 'i', array(intval($id)));
	}
}
asort($cliente_areas_involucradas,SORT_NATURAL|SORT_FLAG_CASE);
$cliente_areas_escape=function($valor) { return htmlspecialchars((string)$valor,ENT_QUOTES,'UTF-8'); };
?>
<section class="grid simple cliente-ficha-seccion cliente-areas-involucradas" aria-labelledby="clienteAreasInvolucradasTitulo">
	<div class="grid-title"><h4 id="clienteAreasInvolucradasTitulo">Áreas involucradas</h4></div>
	<div class="grid-body">
	<p class="cliente-area-referencia"><strong>Área de referencia registrada:</strong> <?php echo $cliente_areas_escape(isset($area_cliente_ficha) ? $area_cliente_ficha : 'No registrada'); ?></p>
		<?php if($cliente_areas_involucradas) { ?>
		<div class="cliente-areas-etiquetas"><?php foreach($cliente_areas_involucradas as $nombre_area_involucrada) { ?><span class="label label-info"><?php echo $cliente_areas_escape($nombre_area_involucrada); ?></span><?php } ?></div>
		<?php } else { ?><p class="text-muted" style="margin:0;">Todavía no hay intervenciones institucionales registradas para este cliente.</p><?php } ?>
	</div>
</section>
<style>
.cliente-areas-etiquetas { display:flex; flex-wrap:wrap; gap:7px; }
.cliente-areas-etiquetas .label { display:inline-block; padding:6px 10px; font-size:12px; font-weight:600; }
.cliente-area-referencia { margin:0 0 10px; color:#64748b; }
</style>
