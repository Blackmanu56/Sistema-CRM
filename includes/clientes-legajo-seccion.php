<?php
require_once BASE_PATH.'/includes/clientes-legajo.php';
$cliente_legajo_id=intval($id);
$cliente_legajo_docs=crm_cliente_legajo_listar($cliente_legajo_id);
$cliente_legajo_categorias=crm_cliente_legajo_categorias();
$cliente_legajo_modo_consulta=!empty($cliente_legajo_modo_consulta);
$cliente_legajo_gestion=!$cliente_legajo_modo_consulta && !crm_cliente_area_solo_lectura($area_cli);
$cliente_legajo_return=$cliente_legajo_modo_consulta ? 'ficha' : 'editar';
$cliente_legajo_csrf=crm_tarea_extra_csrf_token();
$cliente_legajo_flash=isset($_SESSION['crm_cliente_legajo_mensaje']) && is_array($_SESSION['crm_cliente_legajo_mensaje']) ? $_SESSION['crm_cliente_legajo_mensaje'] : null;
unset($_SESSION['crm_cliente_legajo_mensaje']);
$cliente_legajo_valores=isset($_SESSION['crm_cliente_legajo_formulario']) && is_array($_SESSION['crm_cliente_legajo_formulario']) ? $_SESSION['crm_cliente_legajo_formulario'] : array();
unset($_SESSION['crm_cliente_legajo_formulario']);
$cliente_legajo_categoria_valor=isset($cliente_legajo_valores['categoria']) ? (string)$cliente_legajo_valores['categoria'] : '';
$cliente_legajo_descripcion_valor=isset($cliente_legajo_valores['descripcion']) ? (string)$cliente_legajo_valores['descripcion'] : '';
$cliente_legajo_reabrir_formulario=($cliente_legajo_flash && isset($cliente_legajo_flash[0]) && $cliente_legajo_flash[0]==='error' && !empty($cliente_legajo_valores['reabrir']));
$cliente_legajo_escape=function($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
?>
<style>
.cliente-legajo { margin-top:22px; border:1px solid #dfe7f0; border-radius:6px; }
.cliente-legajo .cliente-legajo-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
.cliente-legajo .cliente-legajo-heading h4 { margin:0; }
.cliente-legajo .cliente-legajo-title { display:flex; align-items:center; gap:8px; }
.cliente-legajo .cliente-legajo-title i { color:#4778a8; }
.cliente-legajo .cliente-legajo-table-wrap { overflow-x:auto; border:1px solid #e4eaf1; border-radius:8px; }
.cliente-legajo .cliente-legajo-table { width:100%; margin:0; background:#fff; }
.cliente-legajo .cliente-legajo-table th { background:#f5f8fc; color:#53677d; font-size:11px; letter-spacing:.35px; text-transform:uppercase; white-space:nowrap; }
.cliente-legajo .cliente-legajo-table td { vertical-align:middle !important; color:#34465b; font-size:12px; overflow-wrap:anywhere; }
.cliente-legajo .cliente-legajo-document-name { font-weight:600; overflow-wrap:anywhere; }
.cliente-legajo .cliente-legajo-actions { display:flex; flex-wrap:nowrap; gap:4px; align-items:center; white-space:nowrap; }
.cliente-legajo .cliente-legajo-actions form { margin:0; }
.cliente-legajo .cliente-legajo-actions .btn { padding:3px 5px; font-size:11px; }
.cliente-legajo .cliente-legajo-delete { color:#fff; background:#dc3545; border-color:#dc3545; }
.cliente-legajo .cliente-legajo-delete:hover,.cliente-legajo .cliente-legajo-delete:focus { color:#fff; background:#c82333; border-color:#bd2130; }
.cliente-legajo .cliente-legajo-upload { margin:0 0 12px; padding:12px; border:1px solid #dce5ef; border-radius:8px; background:#f8fbff; text-align:left; }
.cliente-legajo .cliente-legajo-upload-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px 16px; }
.cliente-legajo .cliente-legajo-upload .form-group { margin:0; min-width:0; }
.cliente-legajo .cliente-legajo-upload-file { grid-column:1 / -1; }
.cliente-legajo .cliente-legajo-upload-actions { display:flex; justify-content:center; flex-wrap:wrap; gap:8px; margin-top:10px; }
.cliente-legajo .cliente-legajo-upload-help { color:#64748b; display:block; margin-top:5px; }
.cliente-legajo .cliente-legajo-empty { color:#64748b; margin:0; }
@media(max-width:767px) {
	.cliente-legajo .cliente-legajo-heading { align-items:flex-start; }
	.cliente-legajo .cliente-legajo-heading > .btn { width:100%; }
	.cliente-legajo .cliente-legajo-upload-grid { grid-template-columns:1fr; }
	.cliente-legajo .cliente-legajo-upload-file { grid-column:auto; }
	.cliente-legajo .cliente-legajo-table-wrap { overflow:visible; border:0; }
	.cliente-legajo .cliente-legajo-table,.cliente-legajo .cliente-legajo-table tbody,.cliente-legajo .cliente-legajo-table tr,.cliente-legajo .cliente-legajo-table td { display:block; width:100%; }
	.cliente-legajo .cliente-legajo-table thead { display:none; }
	.cliente-legajo .cliente-legajo-table tr { margin:0 0 10px; padding:8px 10px; border:1px solid #e4eaf1; border-radius:8px; background:#fff; }
	.cliente-legajo .cliente-legajo-table td { display:grid; grid-template-columns:minmax(86px,30%) minmax(0,1fr); gap:8px; padding:6px 3px !important; border:0 !important; }
	.cliente-legajo .cliente-legajo-table td:before { content:attr(data-label); color:#53677d; font-size:11px; font-weight:600; }
	.cliente-legajo .cliente-legajo-table td:last-child { display:block; }
	.cliente-legajo .cliente-legajo-table td:last-child:before { display:block; margin-bottom:6px; }
	.cliente-legajo .cliente-legajo-actions { flex-wrap:wrap; white-space:normal; }
}
</style>
<section class="grid simple cliente-ficha-seccion cliente-legajo" id="clienteLegajoDigital" aria-labelledby="clienteLegajoTitulo">
	<div class="grid-title">
		<div class="cliente-legajo-heading">
		<h4 class="cliente-legajo-title" id="clienteLegajoTitulo"><i class="fa fa-folder-open-o" aria-hidden="true"></i> Legajo digital</h4>
		<?php if($cliente_legajo_gestion) { ?><button type="button" class="btn btn-primary btn-sm" id="clienteLegajoToggle" aria-expanded="<?php echo $cliente_legajo_reabrir_formulario ? 'true' : 'false'; ?>" aria-controls="clienteLegajoFormulario"><i class="fa fa-plus" aria-hidden="true"></i> Agregar documento</button><?php } ?>
		</div>
	</div>
	<div class="grid-body">
		<?php if($cliente_legajo_flash && !$cliente_legajo_reabrir_formulario) { ?>
		<div class="alert alert-<?php echo $cliente_legajo_escape(isset($cliente_legajo_flash[0]) && $cliente_legajo_flash[0]==='success' ? 'success' : 'danger'); ?>" role="status"><?php echo $cliente_legajo_escape(isset($cliente_legajo_flash[1]) ? $cliente_legajo_flash[1] : ''); ?></div>
		<?php } ?>
		<?php if(!crm_cliente_legajo_tabla_disponible()) { ?>
		<div class="alert alert-warning" role="status">El legajo digital está pendiente de habilitación. Se debe aplicar <code>sql/migrations/20261022_003_cliente_documento_legajo.sql</code>.</div>
		<?php } elseif($cliente_legajo_docs===false) { ?>
		<div class="alert alert-danger" role="alert">No se pudieron cargar los documentos de este cliente. Intentá nuevamente más tarde.</div>
		<?php } else { ?>
		<?php if($cliente_legajo_gestion) { ?>
		<div class="cliente-legajo-upload" id="clienteLegajoFormulario" <?php echo $cliente_legajo_reabrir_formulario ? '' : 'hidden'; ?>>
			<?php if($cliente_legajo_reabrir_formulario && isset($cliente_legajo_flash[1])) { ?><div class="alert alert-danger" role="alert"><?php echo $cliente_legajo_escape($cliente_legajo_flash[1]); ?></div><p class="text-muted">Por seguridad del navegador, seleccioná nuevamente el archivo antes de reenviar el formulario.</p><?php } ?>
			<form method="post" enctype="multipart/form-data" action="<?php echo $cliente_legajo_escape(url_crm('cliente-legajo.php')); ?>">
				<input type="hidden" name="csrf_token" value="<?php echo $cliente_legajo_escape($cliente_legajo_csrf); ?>" />
				<input type="hidden" name="accion" value="agregar" />
				<input type="hidden" name="id_cliente" value="<?php echo $cliente_legajo_id; ?>" />
				<input type="hidden" name="volver" value="<?php echo $cliente_legajo_return; ?>" />
				<input type="hidden" name="MAX_FILE_SIZE" value="15728640" />
				<div class="cliente-legajo-upload-grid">
					<div class="form-group"><label for="clienteLegajoCategoria">Categoría</label><select id="clienteLegajoCategoria" class="form-control" name="categoria" required><option value="" <?php echo !isset($cliente_legajo_categorias[$cliente_legajo_categoria_valor]) ? 'selected' : ''; ?>>Seleccioná una categoría</option><?php foreach($cliente_legajo_categorias as $categoria_key=>$categoria_nombre) { ?><option value="<?php echo $cliente_legajo_escape($categoria_key); ?>" <?php echo $cliente_legajo_categoria_valor===$categoria_key ? 'selected' : ''; ?>><?php echo $cliente_legajo_escape($categoria_nombre); ?></option><?php } ?></select></div>
					<div class="form-group"><label for="clienteLegajoDescripcion">Descripción (opcional)</label><input id="clienteLegajoDescripcion" class="form-control" type="text" name="descripcion" maxlength="1000" value="<?php echo $cliente_legajo_escape($cliente_legajo_descripcion_valor); ?>" /></div>
					<div class="form-group cliente-legajo-upload-file"><label for="clienteLegajoArchivo">Documento PDF, DOC o DOCX (máximo 15 MB)</label><input id="clienteLegajoArchivo" class="form-control" type="file" name="documento" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required /><small class="cliente-legajo-upload-help">El archivo se guarda en almacenamiento privado.</small></div>
				</div>
				<div class="cliente-legajo-upload-actions"><button type="submit" class="btn btn-primary"><i class="fa fa-upload" aria-hidden="true"></i> Guardar documento</button><button type="button" class="btn btn-white" id="clienteLegajoCancelar">Cancelar</button></div>
			</form>
		</div>
		<?php } ?>
		<?php if(!$cliente_legajo_docs) { ?>
		<p class="cliente-legajo-empty">Todavía no hay documentos cargados en este legajo.</p>
		<?php } else { ?>
		<div class="cliente-legajo-table-wrap"><table class="table table-hover cliente-legajo-table">
			<thead><tr><th>Fecha de carga</th><th>Categoría</th><th>Documento</th><th>Descripción</th><th>Acciones</th></tr></thead>
			<tbody>
			<?php foreach($cliente_legajo_docs as $documento) {
				$doc_id=intval($documento['id_documento']);
				$doc_extension=strtolower(pathinfo(isset($documento['nombre_original']) ? $documento['nombre_original'] : '', PATHINFO_EXTENSION));
				$doc_pdf=$doc_extension==='pdf';
				$doc_categoria_key=isset($documento['categoria']) ? (string)$documento['categoria'] : '';
				$doc_categoria=isset($cliente_legajo_categorias[$doc_categoria_key]) ? $cliente_legajo_categorias[$doc_categoria_key] : 'Otros';
				$doc_fecha='';
				if(!empty($documento['fecha_carga'])) { $ts=strtotime($documento['fecha_carga']); $doc_fecha=$ts ? date('d/m/Y H:i', $ts) : $documento['fecha_carga']; }
				$url_ver=url_crm('cliente-legajo.php?id_documento='.$doc_id.'&modo=ver');
				$url_imprimir=url_crm('cliente-legajo.php?id_documento='.$doc_id.'&modo=imprimir');
				$url_descargar=url_crm('cliente-legajo.php?id_documento='.$doc_id.'&modo=descargar');
			?>
			<tr>
				<td data-label="Fecha de carga"><?php echo $cliente_legajo_escape($doc_fecha); ?></td>
				<td data-label="Categoría"><?php echo $cliente_legajo_escape($doc_categoria); ?></td>
				<td data-label="Documento" class="cliente-legajo-document-name"><?php echo $cliente_legajo_escape(isset($documento['nombre_original']) ? $documento['nombre_original'] : 'Documento'); ?></td>
				<td data-label="Descripción"><?php echo $cliente_legajo_escape(isset($documento['descripcion']) && $documento['descripcion']!==null && $documento['descripcion']!=='' ? $documento['descripcion'] : '—'); ?></td>
				<td data-label="Acciones"><div class="cliente-legajo-actions">
					<a class="btn btn-white btn-xs" href="<?php echo $cliente_legajo_escape($url_ver); ?>" target="_blank" rel="noopener"><i class="fa fa-eye" aria-hidden="true"></i> <?php echo $doc_pdf ? 'Ver PDF' : 'Ver'; ?></a>
					<?php if($cliente_legajo_gestion) { ?><a class="btn btn-white btn-xs" href="<?php echo $cliente_legajo_escape($url_imprimir); ?>" target="_blank" rel="noopener" title="Abrir el documento para imprimir"><i class="fa fa-print" aria-hidden="true"></i> Imprimir</a><?php } ?>
					<a class="btn btn-white btn-xs" href="<?php echo $cliente_legajo_escape($url_descargar); ?>"><i class="fa fa-download" aria-hidden="true"></i> Descargar</a>
					<?php if($cliente_legajo_gestion) { ?><form method="post" action="<?php echo $cliente_legajo_escape(url_crm('cliente-legajo.php')); ?>" onsubmit="return confirm('¿Eliminar este documento del legajo del cliente?');"><input type="hidden" name="csrf_token" value="<?php echo $cliente_legajo_escape($cliente_legajo_csrf); ?>" /><input type="hidden" name="accion" value="eliminar" /><input type="hidden" name="id_cliente" value="<?php echo $cliente_legajo_id; ?>" /><input type="hidden" name="volver" value="<?php echo $cliente_legajo_return; ?>" /><input type="hidden" name="id_documento" value="<?php echo $doc_id; ?>" /><button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-trash" aria-hidden="true"></i> Eliminar</button></form><?php } ?>
				</div></td>
			</tr>
			<?php } ?>
			</tbody>
		</table></div>
		<?php } ?>
		<?php } ?>
	</div>
</section>
<script>
(function(){
	var boton=document.getElementById('clienteLegajoToggle');
	var panel=document.getElementById('clienteLegajoFormulario');
	var cancelar=document.getElementById('clienteLegajoCancelar');
	if(!boton || !panel) { return; }
	var abrir=function(){ panel.hidden=false; boton.setAttribute('aria-expanded','true'); };
	var cerrar=function(){ panel.hidden=true; boton.setAttribute('aria-expanded','false'); };
	boton.onclick=function(){ if(panel.hidden) { abrir(); } else { cerrar(); } };
	if(cancelar) { cancelar.onclick=cerrar; }
	if(boton.getAttribute('aria-expanded')==='true') { abrir(); }
})();
</script>
