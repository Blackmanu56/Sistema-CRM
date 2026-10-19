<?php
$esc=function($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$normalizar_fecha=function($value) {
	$value=trim((string)$value);
	if($value==='') { return ''; }
	if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) { return false; }
	$partes=explode('-', $value);
	return checkdate(intval($partes[1]), intval($partes[2]), intval($partes[0])) ? $value : false;
};
$form=array(
	'q'=>isset($_GET['q']) && is_scalar($_GET['q']) ? trim((string)$_GET['q']) : '',
	'area'=>isset($_GET['area']) && is_scalar($_GET['area']) ? trim((string)$_GET['area']) : '',
	'rol'=>isset($_GET['rol']) && is_scalar($_GET['rol']) ? trim((string)$_GET['rol']) : '',
	'estado'=>isset($_GET['estado']) && is_scalar($_GET['estado']) ? trim((string)$_GET['estado']) : '',
	'vinculo'=>isset($_GET['vinculo']) && is_scalar($_GET['vinculo']) ? trim((string)$_GET['vinculo']) : '',
	'ingreso_desde'=>isset($_GET['ingreso_desde']) && is_scalar($_GET['ingreso_desde']) ? (string)$_GET['ingreso_desde'] : '',
	'ingreso_hasta'=>isset($_GET['ingreso_hasta']) && is_scalar($_GET['ingreso_hasta']) ? (string)$_GET['ingreso_hasta'] : ''
);
if(function_exists('mb_substr')) { $form['q']=mb_substr($form['q'], 0, 150, 'UTF-8'); }
else { $form['q']=substr($form['q'], 0, 150); }

$areas=acceso_consulta("SELECT id_area, nombre FROM area ORDER BY nombre");
$roles=acceso_consulta("SELECT id_rol, clave, nombre FROM rol WHERE clave<>'superadmin' ORDER BY nombre");
$vinculos=acceso_consulta("SELECT DISTINCT tipo_vinculo AS valor FROM empleado WHERE tipo_vinculo IS NOT NULL AND TRIM(tipo_vinculo)<>'' ORDER BY tipo_vinculo");

$area_ids=array(); foreach($areas as $item) { $area_ids[(string)intval($item['id_area'])]=true; }
$rol_ids=array(); foreach($roles as $item) { $rol_ids[(string)intval($item['id_rol'])]=true; }
$estado_values=array('activo'=>true, 'inactivo'=>true);
$vinculo_values=array(); foreach($vinculos as $item) { $vinculo_values[(string)$item['valor']]=true; }
$errors=array();
$desde=$normalizar_fecha($form['ingreso_desde']);
$hasta=$normalizar_fecha($form['ingreso_hasta']);
if($desde===false) { $errors[]='La fecha de ingreso desde no es válida.'; $desde=''; }
if($hasta===false) { $errors[]='La fecha de ingreso hasta no es válida.'; $hasta=''; }
if($desde!=='' && $hasta!=='' && $desde>$hasta) { $errors[]='La fecha inicial no puede ser posterior a la fecha final.'; }
if($form['area']!=='' && !isset($area_ids[$form['area']])) { $errors[]='El área seleccionada no es válida.'; }
if($form['rol']!=='' && !isset($rol_ids[$form['rol']])) { $errors[]='El rol seleccionado no es válido.'; }
if($form['estado']!=='' && !isset($estado_values[$form['estado']])) { $errors[]='El estado seleccionado no es válido.'; }
if($form['vinculo']!=='' && !isset($vinculo_values[$form['vinculo']])) { $errors[]='El tipo de vínculo seleccionado no es válido.'; }

$where=array("r.clave<>'superadmin'");
$types='';
$values=array();
if($form['q']!=='')
{
	$like='%'.$form['q'].'%';
	$where[]="(e.nombres LIKE ? OR e.apellido LIKE ? OR CONCAT(e.nombres,' ',e.apellido) LIKE ? OR e.dni LIKE ? OR e.nombre_usuario LIKE ?)";
	$types.='sssss';
	for($i=0; $i<5; $i++) { $values[]=$like; }
}
if($form['area']!=='' && isset($area_ids[$form['area']])) { $where[]='e.id_area=?'; $types.='i'; $values[]=intval($form['area']); }
if($form['rol']!=='' && isset($rol_ids[$form['rol']])) { $where[]='e.id_rol=?'; $types.='i'; $values[]=intval($form['rol']); }
if($form['estado']==='activo') { $where[]='e.activo=1'; }
else if($form['estado']==='inactivo') { $where[]='e.activo=0'; }
if($form['vinculo']!=='' && isset($vinculo_values[$form['vinculo']])) { $where[]='e.tipo_vinculo=?'; $types.='s'; $values[]=$form['vinculo']; }
if($desde!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) { $where[]='e.fecha_ingreso>=?'; $types.='s'; $values[]=$desde; }
if($hasta!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) { $where[]='e.fecha_ingreso<=?'; $types.='s'; $values[]=$hasta; }

$rows=array();
if(count($errors)===0)
{
	$rows=acceso_consulta("SELECT e.id_empleado, e.nombres, e.apellido, e.dni, e.puesto, e.tipo_vinculo,
		e.estado_laboral, e.activo, e.fecha_ingreso, a.nombre AS area_nombre, r.clave AS rol_clave, r.nombre AS rol_nombre
		FROM empleado e
		INNER JOIN rol r ON r.id_rol=e.id_rol
		LEFT JOIN area a ON a.id_area=e.id_area
		WHERE ".implode(' AND ', $where)."
		ORDER BY e.apellido ASC, e.nombres ASC, e.id_empleado ASC", $types, $values);
}
$total=count($rows);
$area_labels=array(); foreach($areas as $item) { $area_labels[(string)intval($item['id_area'])]=$item['nombre']; }
$rol_labels=array(); foreach($roles as $item) { $rol_labels[(string)intval($item['id_rol'])]=$item['nombre']; }
$filtros_aplicados=array();
if($form['q']!=='') { $filtros_aplicados[]='Búsqueda: '.$form['q']; }
if($form['area']!=='' && isset($area_labels[$form['area']])) { $filtros_aplicados[]='Área: '.$area_labels[$form['area']]; }
if($form['rol']!=='' && isset($rol_labels[$form['rol']])) { $filtros_aplicados[]='Rol principal: '.$rol_labels[$form['rol']]; }
if($form['estado']==='activo') { $filtros_aplicados[]='Estado laboral: Activo'; }
else if($form['estado']==='inactivo') { $filtros_aplicados[]='Estado laboral: Inactivo'; }
if($form['vinculo']!=='') { $filtros_aplicados[]='Tipo de vínculo: '.ucfirst(str_replace('_', ' ', $form['vinculo'])); }
if($desde!=='') { $filtros_aplicados[]='Ingreso desde: '.date('d/m/Y', strtotime($desde)); }
if($hasta!=='') { $filtros_aplicados[]='Ingreso hasta: '.date('d/m/Y', strtotime($hasta)); }
$fecha_emision=date('d/m/Y');
?>
<?php if($impresion_fichas) {
	if($total===0 || count($errors)>0) {
		http_response_code(200);
		echo '<!DOCTYPE html><html lang="es"><meta charset="utf-8"><title>Sin resultados</title><body><p>No hay colaboradores para imprimir con los filtros seleccionados.</p></body></html>';
		exit();
	}
	if(!acceso_puede_gestionar('rrhh')) {
		http_response_code(403);
		echo '<!DOCTYPE html><html lang="es"><meta charset="utf-8"><title>Impresión no disponible</title><body><p>No estás autorizado para consultar las fichas individuales.</p></body></html>';
		exit();
	}
	require_once BASE_PATH.'/includes/rrhh/fotos.php';
	$area_etiquetas_ficha=acceso_etiquetas_area();
	$fecha_obj_ficha=function($valor) {
		$valor=trim((string)$valor);
		if($valor==='' || $valor==='0000-00-00') { return null; }
		$fecha=DateTime::createFromFormat('!Y-m-d', substr($valor, 0, 10));
		$errores=DateTime::getLastErrors();
		return (!$fecha || ($errores && ($errores['warning_count']>0 || $errores['error_count']>0)) || $fecha->format('Y-m-d')!==substr($valor, 0, 10)) ? null : $fecha;
	};
	$fecha_txt_ficha=function($valor) use ($fecha_obj_ficha) { $fecha=$fecha_obj_ficha($valor); return $fecha ? $fecha->format('d/m/Y') : 'No registrado'; };
	$documentos_ficha=array();
	$hoy_ficha=new DateTime('today');
	foreach($rows as $row)
	{
		$id_ficha=intval($row['id_empleado']);
		$empleado_ficha=acceso_empleado_por_id($id_ficha);
		if(!$empleado_ficha || (isset($empleado_ficha['rol']) && $empleado_ficha['rol']==='superadmin')) { continue; }
		$nombre_ficha=trim((string)$empleado_ficha['nombres'].' '.(string)$empleado_ficha['apellido']);
		$area_ficha=isset($area_etiquetas_ficha[$empleado_ficha['area']]) ? $area_etiquetas_ficha[$empleado_ficha['area']] : (isset($row['area_nombre']) && trim((string)$row['area_nombre'])!=='' ? $row['area_nombre'] : 'Sin área asignada');
		$rol_ficha=isset($empleado_ficha['rol']) && trim((string)$empleado_ficha['rol'])!=='' ? acceso_mostrar_rol($empleado_ficha['rol']) : 'No registrado';
		$estado_laboral_ficha=isset($empleado_ficha['estado_laboral']) ? trim((string)$empleado_ficha['estado_laboral']) : '';
		$estado_ficha=$estado_laboral_ficha!=='' ? ucfirst(str_replace('_', ' ', $estado_laboral_ficha)) : (intval($empleado_ficha['activo'])===1 ? 'Activo' : 'Inactivo');
		$fecha_baja_ficha='';
		$tiene_fecha_baja_ficha=function_exists('crm_columna_existe') && crm_columna_existe('empleado', 'fecha_baja');
		if($tiene_fecha_baja_ficha) {
			$fila_baja_ficha=acceso_consulta_una('SELECT fecha_baja FROM empleado WHERE id_empleado=? LIMIT 1', 'i', array($id_ficha));
			if($fila_baja_ficha && isset($fila_baja_ficha['fecha_baja'])) { $fecha_baja_ficha=$fila_baja_ficha['fecha_baja']; }
		}
		$domicilio_ficha=array();
		if(isset($empleado_ficha['direccion']) && trim((string)$empleado_ficha['direccion'])!=='') { $domicilio_ficha[]=trim((string)$empleado_ficha['direccion']); }
		$columnas_domicilio_ficha=array();
		foreach(array('localidad_domicilio','provincia_domicilio') as $columna_ficha) {
			if(function_exists('crm_columna_existe') && crm_columna_existe('empleado', $columna_ficha)) { $columnas_domicilio_ficha[]=$columna_ficha; }
		}
		if(count($columnas_domicilio_ficha)>0) {
			$fila_domicilio_ficha=acceso_consulta_una('SELECT '.implode(', ', $columnas_domicilio_ficha).' FROM empleado WHERE id_empleado=? LIMIT 1', 'i', array($id_ficha));
			if($fila_domicilio_ficha) { foreach($columnas_domicilio_ficha as $columna_ficha) { if(isset($fila_domicilio_ficha[$columna_ficha]) && trim((string)$fila_domicilio_ficha[$columna_ficha])!=='') { $domicilio_ficha[]=trim((string)$fila_domicilio_ficha[$columna_ficha]); } } }
		}
		$ingreso_ficha=$fecha_obj_ficha(isset($empleado_ficha['fecha_ingreso']) ? $empleado_ficha['fecha_ingreso'] : '');
		$baja_obj_ficha=$fecha_obj_ficha($fecha_baja_ficha);
		$hasta_ficha=($baja_obj_ficha && $baja_obj_ficha<$hoy_ficha) ? $baja_obj_ficha : $hoy_ficha;
		$antiguedad_ficha='No registrado';
		if($ingreso_ficha) {
			$intervalo_ficha=$ingreso_ficha->diff($hasta_ficha);
			if($intervalo_ficha->invert) { $antiguedad_ficha='Fecha de ingreso futura'; }
			else { $partes_ficha=array(); if($intervalo_ficha->y>0) { $partes_ficha[]=$intervalo_ficha->y.' '.($intervalo_ficha->y===1 ? 'año' : 'años'); } if($intervalo_ficha->m>0) { $partes_ficha[]=$intervalo_ficha->m.' '.($intervalo_ficha->m===1 ? 'mes' : 'meses'); } if(count($partes_ficha)===0) { $partes_ficha[]=$intervalo_ficha->d.' '.($intervalo_ficha->d===1 ? 'día' : 'días'); } $antiguedad_ficha=implode(' y ', $partes_ficha); }
		}
		$roles_extra_ficha=array();
		if(isset($empleado_ficha['roles_adicionales']) && is_array($empleado_ficha['roles_adicionales'])) {
			foreach($empleado_ficha['roles_adicionales'] as $extra_ficha) {
				$extra_rol_ficha=isset($extra_ficha['rol']) ? acceso_mostrar_rol($extra_ficha['rol']) : '';
				$extra_area_ficha=isset($extra_ficha['area']) && $extra_ficha['area']!=='' ? (isset($area_etiquetas_ficha[$extra_ficha['area']]) ? $area_etiquetas_ficha[$extra_ficha['area']] : $extra_ficha['area']) : '';
				$texto_extra_ficha=$extra_rol_ficha.($extra_area_ficha!=='' ? ' · '.$extra_area_ficha : '');
				if($texto_extra_ficha!=='') { $roles_extra_ficha[]=$texto_extra_ficha; }
			}
		}
		$iniciales_ficha='';
		foreach(array($empleado_ficha['nombres'], $empleado_ficha['apellido']) as $nombre_parte_ficha) {
			$nombre_parte_ficha=trim((string)$nombre_parte_ficha);
			if($nombre_parte_ficha!=='') { $inicial_ficha=function_exists('mb_substr') ? mb_substr($nombre_parte_ficha, 0, 1, 'UTF-8') : substr($nombre_parte_ficha, 0, 1); $iniciales_ficha.=function_exists('mb_strtoupper') ? mb_strtoupper($inicial_ficha, 'UTF-8') : strtoupper($inicial_ficha); }
		}
		if($iniciales_ficha==='') { $iniciales_ficha='E'; }
		$foto_key_ficha=crm_rrhh_foto_columna_disponible() ? crm_rrhh_foto_key_por_empleado($id_ficha) : '';
		$foto_url_ficha=($foto_key_ficha!=='' && crm_rrhh_foto_ruta_por_key($foto_key_ficha)!=='') ? url_crm('foto-empleado.php?id='.$id_ficha.'&v='.rawurlencode($foto_key_ficha)) : '';
		$datos_personales_ficha=array(
			array('Nombre y apellido', $nombre_ficha),
			array('DNI', isset($empleado_ficha['dni']) && $empleado_ficha['dni']!=='' ? $empleado_ficha['dni'] : 'No registrado'),
			array('CUIL', isset($empleado_ficha['cuil']) && $empleado_ficha['cuil']!=='' ? $empleado_ficha['cuil'] : 'No registrado'),
			array('Correo electrónico', isset($empleado_ficha['correo']) && $empleado_ficha['correo']!=='' ? $empleado_ficha['correo'] : 'No registrado'),
			array('Teléfono', isset($empleado_ficha['telefono']) && $empleado_ficha['telefono']!=='' ? $empleado_ficha['telefono'] : 'No registrado'),
			array('Domicilio', count($domicilio_ficha)>0 ? implode(', ', $domicilio_ficha) : 'No registrado')
		);
		$datos_laborales_ficha=array(
			array('Área principal', $area_ficha),
			array('Rol principal', $rol_ficha),
			array('Puesto', isset($empleado_ficha['puesto']) && trim((string)$empleado_ficha['puesto'])!=='' ? $empleado_ficha['puesto'] : 'No registrado'),
			array('Vínculo laboral', isset($empleado_ficha['tipo_vinculo']) && trim((string)$empleado_ficha['tipo_vinculo'])!=='' ? ucfirst(str_replace('_', ' ', $empleado_ficha['tipo_vinculo'])) : 'No registrado'),
			array('Estado laboral', $estado_ficha),
			array('Fecha de ingreso', $fecha_txt_ficha(isset($empleado_ficha['fecha_ingreso']) ? $empleado_ficha['fecha_ingreso'] : '')),
			array('Antigüedad', $antiguedad_ficha)
		);
		if($tiene_fecha_baja_ficha) { $datos_laborales_ficha[]=array('Fecha de baja', $fecha_txt_ficha($fecha_baja_ficha)); }
		$datos_laborales_ficha[]=array('Roles adicionales', count($roles_extra_ficha)>0 ? implode(' · ', $roles_extra_ficha) : 'No registrado');
		$documentos_ficha[]=array('nombre'=>$nombre_ficha, 'area'=>$area_ficha, 'rol'=>$rol_ficha, 'estado'=>$estado_ficha, 'fecha'=>$fecha_emision, 'foto'=>$foto_url_ficha, 'iniciales'=>$iniciales_ficha, 'personales'=>$datos_personales_ficha, 'laborales'=>$datos_laborales_ficha);
	}
	if(count($documentos_ficha)===0) {
		http_response_code(404);
		echo '<!DOCTYPE html><html lang="es"><meta charset="utf-8"><title>Impresión no disponible</title><body><p>No se encontraron fichas autorizadas para imprimir.</p></body></html>';
		exit();
	}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ADEMI | Fichas de colaboradores</title>
<style>@page{size:A4 portrait;margin:8mm}*{box-sizing:border-box}html,body{margin:0;padding:0;width:auto;min-width:0;height:auto;min-height:0;background:#fff;color:#263548;font:10pt Arial,sans-serif}body{padding:0}.rrhh-fichas-print-list{display:block;width:100%;margin:0;padding:0}.rrhh-fichas-print-item{display:block;width:100%;min-height:0;height:auto;margin:0;padding:0;border:1px solid #dce3eb;border-radius:7px;background:#fff;color:#263548;break-inside:auto;page-break-inside:auto}.rrhh-fichas-print-item+.rrhh-fichas-print-item{break-before:page;page-break-before:always}.rrhh-fichas-print-head{display:flex;align-items:center;gap:6mm;padding:7mm 9mm;background:#f1f6fc;border-bottom:1px solid #dce5ef;break-inside:avoid;page-break-inside:avoid;print-color-adjust:exact;-webkit-print-color-adjust:exact}.rrhh-fichas-print-avatar{display:flex;flex:0 0 22mm;width:22mm;height:22mm;align-items:center;justify-content:center;overflow:hidden;border:1px solid #dce3eb;border-radius:50%;background:#dceaff;color:#2464ad;font-size:18pt;font-weight:700}.rrhh-fichas-print-avatar img{display:block;width:100%;height:100%;object-fit:cover}.rrhh-fichas-print-main{flex:1 1 auto;min-width:0}.rrhh-fichas-print-brand{margin:0 0 2mm;color:#2870bd;font-size:9pt;font-weight:700;letter-spacing:1.2px;text-transform:uppercase}.rrhh-fichas-print-head h1{margin:0 0 2mm;color:#1f3147;font-size:19pt;line-height:1.2}.rrhh-fichas-print-meta{display:flex;flex-wrap:wrap;gap:1.5mm}.rrhh-fichas-print-pill{display:inline-block;padding:1mm 2mm;border:1px solid #d5e1ef;border-radius:10mm;background:#fff;color:#52657a;font-size:8.5pt}.rrhh-fichas-print-date{align-self:flex-start;margin-left:auto;color:#718096;font-size:8.5pt;white-space:nowrap}.rrhh-fichas-print-body{padding:6mm 9mm}.rrhh-fichas-print-section{break-inside:auto;page-break-inside:auto}.rrhh-fichas-print-section+.rrhh-fichas-print-section{margin-top:5mm;padding-top:4mm;border-top:1px solid #e8edf3}.rrhh-fichas-print-section h2{margin:0 0 3mm;color:#34465b;font-size:11pt;break-after:avoid;page-break-after:avoid}.rrhh-fichas-print-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:3.5mm 9mm}.rrhh-fichas-print-field{min-width:0;break-inside:avoid;page-break-inside:avoid}.rrhh-fichas-print-label{display:block;margin:0 0 1mm;color:#718096;font-size:8pt;font-weight:700;letter-spacing:.35px;text-transform:uppercase}.rrhh-fichas-print-value{color:#2d3d50;font-size:9.5pt;line-height:1.3;overflow-wrap:anywhere}.rrhh-fichas-print-footer{margin-top:4mm;padding:3mm 9mm;border-top:1px solid #e8edf3;color:#8491a0;font-size:8pt;text-align:right;break-inside:avoid;page-break-inside:avoid}@media screen{body{padding:16px;background:#eef2f6}.rrhh-fichas-print-item{max-width:190mm;margin:0 auto 16px;box-shadow:0 2px 12px rgba(0,0,0,.12)}}@media print{html,body{overflow:visible!important}.rrhh-fichas-print-item{margin:0!important;border-radius:0!important;box-shadow:none!important}.rrhh-fichas-print-head{padding-top:6mm;padding-bottom:6mm}.rrhh-fichas-print-body{padding-top:5mm;padding-bottom:5mm}}</style></head><body class="rrhh-fichas-print-document"><main class="rrhh-fichas-print-list">
<?php foreach($documentos_ficha as $ficha) { ?>
<article class="rrhh-fichas-print-item"><header class="rrhh-fichas-print-head"><div class="rrhh-fichas-print-avatar"><?php if($ficha['foto']!=='') { ?><img src="<?php echo $esc($ficha['foto']); ?>" alt="Fotografía de <?php echo $esc($ficha['nombre']); ?>" /><?php } else { echo $esc($ficha['iniciales']); } ?></div><div class="rrhh-fichas-print-main"><div class="rrhh-fichas-print-brand">ADEMI · Recursos Humanos</div><h1><?php echo $esc($ficha['nombre']); ?></h1><div class="rrhh-fichas-print-meta"><span class="rrhh-fichas-print-pill"><?php echo $esc($ficha['area']); ?></span><span class="rrhh-fichas-print-pill"><?php echo $esc($ficha['rol']); ?></span><span class="rrhh-fichas-print-pill"><?php echo $esc($ficha['estado']); ?></span></div></div><div class="rrhh-fichas-print-date">Emitida el <?php echo $esc($ficha['fecha']); ?></div></header><div class="rrhh-fichas-print-body">
<section class="rrhh-fichas-print-section"><h2>Datos personales</h2><div class="rrhh-fichas-print-grid"><?php foreach($ficha['personales'] as $campo) { ?><div class="rrhh-fichas-print-field"><span class="rrhh-fichas-print-label"><?php echo $esc($campo[0]); ?></span><div class="rrhh-fichas-print-value"><?php echo $esc($campo[1]); ?></div></div><?php } ?></div></section>
<section class="rrhh-fichas-print-section"><h2>Datos laborales</h2><div class="rrhh-fichas-print-grid"><?php foreach($ficha['laborales'] as $campo) { ?><div class="rrhh-fichas-print-field"><span class="rrhh-fichas-print-label"><?php echo $esc($campo[0]); ?></span><div class="rrhh-fichas-print-value"><?php echo $esc($campo[1]); ?></div></div><?php } ?></div></section>
</div><footer class="rrhh-fichas-print-footer">Ficha laboral · ADEMI</footer></article>
<?php } ?>
</main><script>window.addEventListener('load',function(){var cards=document.querySelectorAll('.rrhh-fichas-print-item');if(!cards.length){document.documentElement.setAttribute('data-rrhh-print-error','1');document.documentElement.setAttribute('data-rrhh-print-message','No se encontraron fichas autorizadas para imprimir.');return;}document.documentElement.setAttribute('data-rrhh-print-ready','1');});</script></body></html>
<?php exit(); } ?>
<?php if(!$ajax_resultados) { ?>
<div class="rrhh-report-wrap">
<div class="grid simple rrhh-report-grid no-print"><div class="grid-title no-border"><h4><i class="fa fa-filter"></i> Filtros</h4></div><div class="grid-body no-border">
<form method="get" action="<?php echo htmlspecialchars(url_crm('informes.php'), ENT_QUOTES, 'UTF-8'); ?>" class="rrhh-report-filters" data-report-filters>
<input type="hidden" name="reporte" value="colaboradores">
<div class="rrhh-report-filters-grid">
<div class="form-group"><label for="report-q">Buscar colaborador</label><input id="report-q" type="search" name="q" class="form-control" maxlength="150" value="<?php echo $esc($form['q']); ?>" placeholder="Nombre, apellido, DNI o usuario"></div>
<div class="form-group"><label for="report-area">Área</label><select id="report-area" name="area" class="form-control"><option value="">Todas</option><?php foreach($areas as $item) { $v=(string)intval($item['id_area']); ?><option value="<?php echo $esc($v); ?>" <?php echo $form['area']===$v?'selected':''; ?>><?php echo $esc($item['nombre']); ?></option><?php } ?></select></div>
<div class="form-group"><label for="report-rol">Rol principal</label><select id="report-rol" name="rol" class="form-control"><option value="">Todos</option><?php foreach($roles as $item) { $v=(string)intval($item['id_rol']); ?><option value="<?php echo $esc($v); ?>" <?php echo $form['rol']===$v?'selected':''; ?>><?php echo $esc($item['nombre']); ?></option><?php } ?></select></div>
<div class="form-group"><label for="report-estado">Estado laboral</label><select id="report-estado" name="estado" class="form-control"><option value="" <?php echo $form['estado']===''?'selected':''; ?>>Todos</option><option value="activo" <?php echo $form['estado']==='activo'?'selected':''; ?>>Activos</option><option value="inactivo" <?php echo $form['estado']==='inactivo'?'selected':''; ?>>Inactivos</option></select></div>
<div class="form-group"><label for="report-vinculo">Tipo de vínculo</label><select id="report-vinculo" name="vinculo" class="form-control"><option value="">Todos</option><?php foreach($vinculos as $item) { $v=(string)$item['valor']; ?><option value="<?php echo $esc($v); ?>" <?php echo $form['vinculo']===$v?'selected':''; ?>><?php echo $esc(ucfirst(str_replace('_',' ',$v))); ?></option><?php } ?></select></div>
<div class="form-group"><label for="report-desde">Fecha desde</label><input id="report-desde" type="date" name="ingreso_desde" class="form-control" value="<?php echo $esc($form['ingreso_desde']); ?>"></div>
<div class="form-group"><label for="report-hasta">Fecha hasta</label><input id="report-hasta" type="date" name="ingreso_hasta" class="form-control" value="<?php echo $esc($form['ingreso_hasta']); ?>"></div>
<div class="rrhh-report-actions"><button type="button" class="btn btn-white" data-report-clear>Limpiar</button></div>
</div></form></div></div>
<div id="rrhh-report-fetch-error" class="alert alert-warning rrhh-report-async-error no-print" role="status" hidden></div>
<?php } ?>
<div id="rrhh-report-async-response" class="rrhh-report-async-response" data-total="<?php echo intval($total); ?>" data-invalid="<?php echo count($errors)>0 ? '1' : '0'; ?>">
<?php if(count($errors)>0) { ?><div class="alert alert-danger no-print"><?php echo $esc(implode(' ', $errors)); ?></div><?php } ?>
<div class="rrhh-report-print-only rrhh-report-print-header"><div class="institution">ADEMI</div><h1>Informe de colaboradores</h1><div class="rrhh-report-meta">Fecha de emisión: <?php echo $esc($fecha_emision); ?></div>
<?php if(count($filtros_aplicados)>0) { ?><div class="rrhh-report-filters-print"><strong>Filtros:</strong> <?php echo $esc(implode(' · ', $filtros_aplicados)); ?></div><?php } ?></div>
<div class="grid simple rrhh-report-grid"><div class="grid-title no-border rrhh-report-results-heading"><h4>Resultados <span class="rrhh-report-total-count">Total de colaboradores: <?php echo intval($total); ?></span></h4><div class="rrhh-report-result-actions no-print">
<button type="button" class="btn btn-primary" data-print-list <?php echo (count($errors)>0 || $total===0) ? 'disabled' : ''; ?>><i class="fa fa-print"></i> Imprimir informe</button>
<?php if(acceso_puede_gestionar('rrhh')) { ?><button type="button" class="btn btn-white" data-print-fichas <?php echo (count($errors)>0 || $total===0) ? 'disabled' : ''; ?>><i class="fa fa-id-card-o"></i> Imprimir fichas</button><span class="rrhh-report-print-fichas-status" data-print-fichas-status role="status" aria-live="polite" hidden></span><button type="button" class="btn btn-white btn-sm" data-print-fichas-retry hidden>Reintentar impresión</button><?php } ?>
</div></div><div class="grid-body no-border">
<div class="table-responsive"><table class="table table-hover rrhh-report-table"><thead><tr><th>Nombre y apellido</th><th>DNI</th><th>Área principal</th><th>Rol principal</th><th>Puesto o función</th><th>Tipo de vínculo</th><th>Estado laboral</th><th>Fecha de ingreso</th></tr></thead><tbody>
<?php if(count($errors)>0 || $total===0) { ?><tr><td colspan="8" class="rrhh-report-empty"><?php echo count($errors)>0 ? 'Corregí los filtros para consultar el informe.' : 'No se encontraron colaboradores con estos filtros.'; ?></td></tr><?php } else { foreach($rows as $row) {
	$estado=isset($row['activo']) && intval($row['activo'])===0 ? 'Inactivo' : (isset($row['estado_laboral']) && trim((string)$row['estado_laboral'])!=='' ? ucfirst(str_replace('_',' ',(string)$row['estado_laboral'])) : 'No registrado');
	$fecha_ingreso=isset($row['fecha_ingreso']) ? $normalizar_fecha($row['fecha_ingreso']) : '';
	$fecha_ingreso=($fecha_ingreso!=='' && $fecha_ingreso!==false) ? date('d/m/Y', strtotime($fecha_ingreso)) : 'No registrado';
	$ficha_url=url_crm('rrhh/ficha-empleado.php?id='.intval($row['id_empleado']).'&modal_informe=1');
	$nombre_completo=trim($row['nombres'].' '.$row['apellido']);
?>
<tr class="rrhh-report-row-link" tabindex="0" role="button" data-report-open data-href="<?php echo $esc($ficha_url); ?>" aria-haspopup="dialog" aria-label="Abrir ficha de <?php echo $esc($nombre_completo); ?>"><td><?php echo $esc($nombre_completo); ?></td><td><?php echo $esc($row['dni']); ?></td><td><?php echo $esc(isset($row['area_nombre']) && $row['area_nombre']!=='' ? $row['area_nombre'] : 'Sin área asignada'); ?></td><td><?php echo $esc($row['rol_nombre']); ?></td><td><?php echo $esc(isset($row['puesto']) && trim((string)$row['puesto'])!=='' ? $row['puesto'] : 'No registrado'); ?></td><td><?php echo $esc(isset($row['tipo_vinculo']) && trim((string)$row['tipo_vinculo'])!=='' ? ucfirst(str_replace('_',' ',(string)$row['tipo_vinculo'])) : 'No registrado'); ?></td><td><?php echo $esc($estado); ?></td><td><?php echo $esc($fecha_ingreso); ?></td></tr>
<?php } } ?>
</tbody></table></div><div class="rrhh-report-print-only rrhh-report-print-footer">ADEMI · Informe interno de colaboradores <span> · Registros: <?php echo intval($total); ?></span></div></div></div>
 </div>
<?php if(!$ajax_resultados) { ?>
</div>
<div class="rrhh-report-modal no-print" id="rrhh-report-modal" role="dialog" aria-modal="true" aria-labelledby="rrhh-report-modal-title" hidden>
	<div class="rrhh-report-modal-dialog">
		<div class="rrhh-report-modal-head"><h2 id="rrhh-report-modal-title">Ficha del colaborador</h2><button type="button" class="btn btn-white btn-sm" data-report-modal-close aria-label="Cerrar ficha"><i class="fa fa-times"></i></button></div>
		<div class="rrhh-report-modal-body"><div class="rrhh-report-modal-loading" data-report-modal-loading role="status">Cargando ficha…</div><iframe class="rrhh-report-modal-frame" data-report-modal-frame title="Ficha del colaborador" src="about:blank"></iframe></div>
		<div class="rrhh-report-modal-foot"><p class="rrhh-report-modal-hint">Para guardar como PDF, elegí esa opción en el diálogo de impresión del navegador.</p><button type="button" class="btn btn-white" data-report-modal-print><i class="fa fa-print"></i> Imprimir</button><button type="button" class="btn btn-primary" data-report-modal-pdf><i class="fa fa-file-pdf-o"></i> Guardar como PDF</button><button type="button" class="btn btn-white" data-report-modal-close>Cerrar</button></div>
	</div>
</div>
<script>
(function(){
	var form=document.querySelector('[data-report-filters]');
	var modal=document.getElementById('rrhh-report-modal');if(modal&&modal.parentNode!==document.body){document.body.appendChild(modal);}
	var frame=document.querySelector('[data-report-modal-frame]');
	var loader=document.querySelector('[data-report-modal-loading]');
	var bodyOverflow='';
	var returnFocus=null;
	var timer=null;
	var sequence=0;
	var controller=null;
	var pending=false;
	var syncFailed=false;
	var massPrintFrame=null;
	var massPrintTimer=null;
	var massPrintDialogRequested=false;
	var massPrintStatus=document.querySelector('[data-print-fichas-status]');
	var massPrintRetry=document.querySelector('[data-print-fichas-retry]');
	function currentRoot(){return document.getElementById('rrhh-report-async-response');}
	function setPrintEnabled(){var root=currentRoot();var disabled=pending||syncFailed||!root||root.getAttribute('data-invalid')==='1'||parseInt(root.getAttribute('data-total'),10)===0;var listButton=document.querySelector('[data-print-list]');var fichasButton=document.querySelector('[data-print-fichas]');if(listButton){listButton.disabled=disabled;}if(fichasButton){fichasButton.disabled=disabled||!!massPrintFrame;}if(massPrintRetry){massPrintRetry.disabled=disabled||!massPrintFrame;}}
	function queryUrl(withAjax){
		var target=new URL(form.action,window.location.href);
		var params=new URLSearchParams(new FormData(form));
		if(withAjax){params.set('ajax','resultados');}
		target.search=params.toString();
		return target;
	}
	function updateAddress(){var address=queryUrl(false);window.history.replaceState({},'',address.pathname+address.search+address.hash);}
	function refreshResults(){
		if(massPrintFrame&&!massPrintDialogRequested){disposeMassPrint();clearMassPrintStatus();}
		if(timer){window.clearTimeout(timer);timer=null;}
		if(controller){controller.abort();}
		controller=typeof AbortController!=='undefined'?new AbortController():null;
		var current=++sequence;
		pending=true;syncFailed=false;setPrintEnabled();form.setAttribute('aria-busy','true');
		var options={method:'GET',credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-store'};
		if(controller){options.signal=controller.signal;}
		return fetch(queryUrl(true).toString(),options).then(function(response){if(!response.ok){throw new Error('No se pudo actualizar el informe.');}return response.text();}).then(function(html){
			if(current!==sequence){return false;}
			var parsed=new DOMParser().parseFromString(html,'text/html');
			var replacement=parsed.getElementById('rrhh-report-async-response');
			var root=currentRoot();
			if(!replacement||!root){throw new Error('La respuesta del informe no es válida.');}
			root.replaceWith(document.importNode(replacement,true));
			var fetchError=document.getElementById('rrhh-report-fetch-error');if(fetchError){fetchError.hidden=true;fetchError.textContent='';}
			updateAddress();return true;
		}).catch(function(error){if(current!==sequence||error.name==='AbortError'){return false;}syncFailed=true;var fetchError=document.getElementById('rrhh-report-fetch-error');if(fetchError){fetchError.textContent='No se pudieron actualizar los resultados. Revisá tu conexión e intentá nuevamente.';fetchError.hidden=false;}return false;
		}).then(function(ok){if(current===sequence){pending=false;form.removeAttribute('aria-busy');setPrintEnabled();}return ok;});
	}
	function disposeMassPrint(){massPrintDialogRequested=false;if(massPrintTimer){window.clearTimeout(massPrintTimer);massPrintTimer=null;}if(massPrintFrame){massPrintFrame.remove();massPrintFrame=null;}setPrintEnabled();}
	function showMassPrintStatus(message,allowRetry){if(massPrintStatus){massPrintStatus.textContent=message;massPrintStatus.hidden=false;}if(massPrintRetry){massPrintRetry.hidden=!allowRetry;}}
	function clearMassPrintStatus(){if(massPrintStatus){massPrintStatus.textContent='';massPrintStatus.hidden=true;}if(massPrintRetry){massPrintRetry.hidden=true;}}
	function printMassPrintFrame(){
		if(!massPrintFrame){return;}
		var printWindow=massPrintFrame.contentWindow;
		if(!printWindow){showMassPrintStatus('No se pudo abrir la impresión. Podés reintentar.',true);return;}
		if(massPrintTimer){window.clearTimeout(massPrintTimer);massPrintTimer=null;}
		if(massPrintRetry){massPrintRetry.hidden=true;}
		showMassPrintStatus('Abriendo el diálogo de impresión…',false);
		massPrintDialogRequested=true;
		var targetFrame=massPrintFrame;
		var onAfterPrint=function(){if(massPrintFrame===targetFrame){disposeMassPrint();clearMassPrintStatus();}};
		printWindow.addEventListener('afterprint',onAfterPrint);
		try{printWindow.print();}
		catch(error){massPrintDialogRequested=false;showMassPrintStatus('El navegador no abrió la impresión automáticamente. Podés reintentar.',true);return;}
		massPrintTimer=window.setTimeout(function(){if(massPrintFrame===targetFrame){massPrintDialogRequested=false;showMassPrintStatus('Si no apareció el diálogo, podés reintentar la impresión.',true);}},1800);
	}
	function startMassPrint(printUrl){
		if(massPrintFrame){return;}
		clearMassPrintStatus();
		showMassPrintStatus('Preparando las fichas…',false);
		var printFrame=document.createElement('iframe');
		printFrame.setAttribute('aria-hidden','true');printFrame.setAttribute('tabindex','-1');printFrame.className='no-print rrhh-report-print-host';
		printFrame.title='Documento temporal de impresión';
		printFrame.style.cssText='position:fixed;left:-10000px;top:0;width:1000px;height:1200px;border:0;opacity:0;pointer-events:none;z-index:-1';
		massPrintFrame=printFrame;
		printFrame.addEventListener('load',function(){
			if(massPrintFrame!==printFrame){return;}
			try{
				var root=printFrame.contentDocument.documentElement;
				if(root.getAttribute('data-rrhh-print-error')==='1'){var message=root.getAttribute('data-rrhh-print-message')||'No se pudieron preparar las fichas autorizadas.';disposeMassPrint();showMassPrintStatus(message,false);return;}
				if(root.getAttribute('data-rrhh-print-ready')!=='1'){disposeMassPrint();showMassPrintStatus('No se pudieron preparar las fichas para imprimir. Actualizá el informe e intentá nuevamente.',false);return;}
			}catch(error){disposeMassPrint();showMassPrintStatus('No se pudieron preparar las fichas para imprimir. Revisá tu sesión e intentá nuevamente.',false);return;}
			printMassPrintFrame();
		});
		printFrame.src=printUrl.toString();
		document.body.appendChild(printFrame);
	}
	function scheduleSearch(delay){if(timer){window.clearTimeout(timer);}timer=window.setTimeout(function(){refreshResults();},delay);}
	if(form){
		form.addEventListener('submit',function(event){event.preventDefault();refreshResults();});
		var search=form.querySelector('[name="q"]');if(search){search.addEventListener('input',function(){scheduleSearch(350);});}
		var controls=form.querySelectorAll('select,input[type="date"]');for(var i=0;i<controls.length;i++){controls[i].addEventListener('change',function(){scheduleSearch(0);});controls[i].addEventListener('input',function(){scheduleSearch(0);});}
		var clear=form.querySelector('[data-report-clear]');if(clear){clear.addEventListener('click',function(){form.reset();var query=form.querySelector('[name="q"]');if(query){query.focus();}refreshResults();});}
	}
	document.addEventListener('click',function(event){
		var printButton=event.target.closest('[data-print-list]');
		if(printButton){event.preventDefault();refreshResults().then(function(ok){var root=currentRoot();if(ok&&root&&parseInt(root.getAttribute('data-total'),10)>0&&root.getAttribute('data-invalid')!=='1'){window.print();}});return;}
	var retryPrint=event.target.closest('[data-print-fichas-retry]');if(retryPrint){event.preventDefault();printMassPrintFrame();return;}
	var printFichas=event.target.closest('[data-print-fichas]');
	if(printFichas){event.preventDefault();if(printFichas.disabled){return;}var printUrl=queryUrl(false);printUrl.searchParams.set('imprimir_fichas','1');startMassPrint(printUrl);return;}
		var row=event.target.closest('[data-report-open][data-href]');
		if(row){event.preventDefault();returnFocus=row;document.getElementById('rrhh-report-modal-title').textContent='Ficha de '+row.cells[0].textContent.trim();modal.hidden=false;bodyOverflow=document.body.style.overflow;document.body.style.overflow='hidden';loader.hidden=false;frame.src=row.getAttribute('data-href');var close=modal.querySelector('[data-report-modal-close]');if(close){close.focus();}}
		var closeButton=event.target.closest('[data-report-modal-close]');if(closeButton){closeModal();}
		var printFicha=event.target.closest('[data-report-modal-print],[data-report-modal-pdf]');if(printFicha){event.preventDefault();try{frame.contentWindow.focus();frame.contentWindow.print();}catch(error){loader.textContent='No se pudo abrir el diálogo de impresión. Verificá que la ficha haya terminado de cargar.';loader.hidden=false;}}
	});
	document.addEventListener('keydown',function(event){
		if(event.key==='Escape'&&modal&&!modal.hidden){closeModal();return;}
		var row=event.target.closest&&event.target.closest('[data-report-open][data-href]');
		if(row&&(event.key==='Enter'||event.key===' ')){event.preventDefault();row.click();}
	});
	function closeModal(){if(!modal||modal.hidden){return;}modal.hidden=true;frame.src='about:blank';document.body.style.overflow=bodyOverflow;if(returnFocus&&document.contains(returnFocus)){returnFocus.focus();}else{var search=document.querySelector('[data-report-filters] [name="q"]');if(search){search.focus();}}returnFocus=null;}
	var frameDocument=null;
	frame.addEventListener('load',function(){
		if(!modal||modal.hidden||frame.getAttribute('src')==='about:blank'){return;}
		try{
			var loadedDocument=frame.contentDocument;
			if(loadedDocument&&loadedDocument.querySelector('[data-rrhh-ficha]')){
				loader.hidden=true;
				if(loadedDocument!==frameDocument){loadedDocument.addEventListener('keydown',function(event){if(event.key==='Escape'){event.preventDefault();closeModal();}});frameDocument=loadedDocument;}
				return;
			}
		}catch(error){}
		loader.textContent='No se pudo cargar la ficha. Verificá que tu sesión siga activa y tengas autorización para consultarla.';
		loader.hidden=false;
	});
	setPrintEnabled();
})();
</script>
<?php } ?>
