<?php
include_once BASE_PATH.'/includes/crm_historiales.php';
$historial_tareas=function_exists('repo_tarea_historial_por_ids') ? repo_tarea_historial_por_ids($tareas_calendario) : array();
$crm_historial_fecha_tarea=function($fecha) {
	$fecha=trim((string)$fecha);
	if($fecha==='' || strpos($fecha,'0000-00-00')===0) { return 'Sin fecha registrada'; }
	$timestamp=strtotime($fecha);
	return $timestamp===false ? 'Sin fecha registrada' : date('d/m/Y',$timestamp);
};
?>
<div class="grid simple">
	<div class="grid-title"><h4>Historial de tareas</h4></div>
	<div class="grid-body crm-tarea-historial-lista">
		<?php if(!$historial_tareas) { ?><p class="muted">No hay movimientos de tareas registrados.</p><?php } ?>
		<?php foreach($historial_tareas as $movimiento) {
			$fecha_historial=crm_historial_fecha_legible($movimiento['fecha_actividad']);
			$usuario_historial=trim((string)$movimiento['usuario_nombre']);
			$estado_raw=isset($movimiento['tarea_estado_actual']) ? (string)$movimiento['tarea_estado_actual'] : '';
			$prioridad_raw=isset($movimiento['tarea_prioridad_actual']) ? (string)$movimiento['tarea_prioridad_actual'] : '';
			$estado_label=function_exists('repo_estado_a_display') && $estado_raw!=='' ? repo_estado_a_display($estado_raw) : 'No registrado';
			$prioridad_label=function_exists('repo_prioridad_a_display') && $prioridad_raw!=='' ? repo_prioridad_a_display($prioridad_raw) : 'No registrada';
			$estado_clase=in_array($estado_raw,array('no_iniciada','en_proceso','pausada','terminada','cancelada'),true) ? $estado_raw : 'desconocido';
			$prioridad_clase=in_array($prioridad_raw,array('urgente','alta','media','baja'),true) ? $prioridad_raw : 'desconocida';
			$accion_modal=isset($movimiento['tipo']) && $movimiento['tipo']==='creada' ? 'Tarea creada' : (isset($movimiento['tipo']) && $movimiento['tipo']==='completada' ? 'Tarea finalizada' : 'Cambio de estado');
			$datos_historial=array(
				'name'=>isset($movimiento['tarea_titulo']) ? (string)$movimiento['tarea_titulo'] : '',
				'action'=>$accion_modal,
				'explanation'=>isset($movimiento['descripcion']) ? trim((string)$movimiento['descripcion']) : '',
				'date'=>$fecha_historial,
				'user'=>$usuario_historial!=='' ? $usuario_historial : 'Usuario no registrado',
				'state'=>$estado_label,
				'stateClass'=>$estado_clase,
				'priority'=>$prioridad_label,
				'priorityClass'=>$prioridad_clase,
				'area'=>isset($movimiento['tarea_area_nombre']) && trim((string)$movimiento['tarea_area_nombre'])!=='' ? (string)$movimiento['tarea_area_nombre'] : 'Área no registrada',
				'responsibles'=>isset($movimiento['tarea_responsables']) && trim((string)$movimiento['tarea_responsables'])!=='' ? (string)$movimiento['tarea_responsables'] : 'Sin asignar',
				'startDate'=>$crm_historial_fecha_tarea(isset($movimiento['tarea_fecha_inicio']) ? $movimiento['tarea_fecha_inicio'] : ''),
				'dueDate'=>$crm_historial_fecha_tarea(isset($movimiento['tarea_fecha_vencimiento']) ? $movimiento['tarea_fecha_vencimiento'] : ''),
				'description'=>isset($movimiento['tarea_descripcion']) && trim((string)$movimiento['tarea_descripcion'])!=='' ? (string)$movimiento['tarea_descripcion'] : 'Sin descripción registrada'
			);
			$datos_attr=htmlspecialchars(json_encode($datos_historial,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),ENT_QUOTES,'UTF-8');
		?>
		<button type="button" class="crm-tarea-historial-fila" data-crm-tarea-movimiento="<?php echo $datos_attr; ?>">
			<span class="crm-tarea-historial-texto"><strong><?php echo texto_mostrar($datos_historial['name']); ?></strong><span><?php echo texto_mostrar($datos_historial['action']); ?></span></span>
			<time datetime="<?php echo htmlspecialchars((string)$movimiento['fecha_actividad'],ENT_QUOTES,'UTF-8'); ?>"><?php echo texto_mostrar($fecha_historial!==''?$fecha_historial:'Fecha no registrada'); ?></time>
		</button>
		<?php } ?>
	</div>
</div>
<style>
.crm-tarea-historial-lista{padding-top:5px!important;padding-bottom:5px!important}
.crm-tarea-historial-fila{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:12px;width:100%;padding:10px 3px;border:0;border-bottom:1px solid #e8edf1;background:transparent;color:#334155;text-align:left;cursor:pointer;transition:background-color .15s ease}
.crm-tarea-historial-fila:last-child{border-bottom:0}
.crm-tarea-historial-fila:hover,.crm-tarea-historial-fila:focus-visible{background:#f6f9fb;outline:none}
.crm-tarea-historial-texto{display:block;min-width:0}
.crm-tarea-historial-texto strong{display:block;overflow:hidden;color:#1e293b;font-size:13px;font-weight:700;line-height:1.35;text-overflow:ellipsis;white-space:nowrap}
.crm-tarea-historial-texto>span{display:block;margin-top:3px;color:#64748b;font-size:12px;line-height:1.3}
.crm-tarea-historial-fila time{color:#64748b;font-size:11px;font-weight:600;text-align:right;white-space:nowrap}
.crm-tarea-historial-modal{position:fixed;inset:0;z-index:20000;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(15,23,42,.62)}
.crm-tarea-historial-modal.is-open{display:flex}
.crm-tarea-historial-dialog{width:min(680px,100%);max-height:calc(100vh - 36px);overflow:auto;border:1px solid #e5eaf0;border-radius:12px;background:#fff;box-shadow:0 22px 60px rgba(15,23,42,.30)}
.crm-tarea-historial-header{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:18px 22px 16px;background:#f7f9fc;border-bottom:1px solid #e8edf2;border-radius:12px 12px 0 0}
.crm-tarea-historial-header small{display:block;margin-bottom:6px;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em}
.crm-tarea-historial-header h4{margin:0 0 12px;color:#1e293b;font-size:16px;font-weight:700;line-height:1.35}
.crm-tarea-historial-header h3{margin:0;color:#0f172a;font-size:20px;font-weight:700;line-height:1.3;overflow-wrap:anywhere}
.crm-tarea-historial-etiquetas{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}
.crm-tarea-historial-etiqueta{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border:1px solid #e2e8f0;border-radius:999px;background:#fff;color:#334155;font-size:11px;font-weight:700}
.crm-tarea-historial-etiqueta small{display:inline;margin:0;color:#64748b;font-size:9px;letter-spacing:.04em}
.crm-tarea-historial-etiqueta:before{width:7px;height:7px;border-radius:50%;background:#94a3b8;content:""}
.crm-tarea-historial-etiqueta.estado-no_iniciada:before{background:#64748b}.crm-tarea-historial-etiqueta.estado-en_proceso:before{background:#0ea5e9}.crm-tarea-historial-etiqueta.estado-pausada:before{background:#f59e0b}.crm-tarea-historial-etiqueta.estado-terminada:before{background:#22c55e}.crm-tarea-historial-etiqueta.estado-cancelada:before{background:#ef4444}
.crm-tarea-historial-etiqueta.prioridad-urgente:before{background:#dc2626}.crm-tarea-historial-etiqueta.prioridad-alta:before{background:#f97316}.crm-tarea-historial-etiqueta.prioridad-media:before{background:#eab308}.crm-tarea-historial-etiqueta.prioridad-baja:before{background:#22c55e}
.crm-tarea-historial-cerrar-x{display:inline-flex;flex:0 0 32px;align-items:center;justify-content:center;width:32px;height:32px;border:0;border-radius:7px;background:#e9eef5;color:#475569;font-size:16px;cursor:pointer}
.crm-tarea-historial-cerrar-x:hover,.crm-tarea-historial-cerrar-x:focus-visible{background:#dce4ee;outline:2px solid #94a3b8}
.crm-tarea-historial-seccion{padding:17px 22px 18px;border-bottom:1px solid #edf0f2}
.crm-tarea-historial-seccion:last-of-type{border-bottom:0}
.crm-tarea-historial-seccion h4{display:flex;align-items:center;gap:8px;margin:0 0 13px;color:#334155;font-size:13px;font-weight:700}
.crm-tarea-historial-seccion h4 i{color:#1684b5;font-size:13px}
.crm-tarea-historial-accion{display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border-radius:999px;background:#eaf5fb;color:#0878a9;font-size:12px;font-weight:700}
.crm-tarea-historial-explicacion{margin:11px 0 0;padding:11px 13px;border-left:3px solid #9acfe5;border-radius:0 6px 6px 0;background:#f7fafc;color:#475569;font-size:13px;line-height:1.5;overflow-wrap:anywhere}
.crm-tarea-historial-datos{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 22px;margin:0}
.crm-tarea-historial-datos div{min-width:0}
.crm-tarea-historial-datos dt{margin:0 0 4px;color:#64748b;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.crm-tarea-historial-datos dd{margin:0;color:#1e293b;font-size:13px;font-weight:600;line-height:1.4;overflow-wrap:anywhere}
.crm-tarea-historial-datos .descripcion{grid-column:1/-1}
.crm-tarea-historial-descripcion{margin-top:6px;padding:10px 12px;border:1px solid #edf0f2;border-radius:6px;background:#f8fafc;color:#475569;font-size:12px;font-weight:400;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere}
.crm-tarea-historial-footer{display:flex;justify-content:flex-end;padding:13px 22px 17px}
body.crm-tarea-historial-abierto{overflow:hidden}
@media(max-width:520px){.crm-tarea-historial-modal{padding:10px}.crm-tarea-historial-dialog{max-height:calc(100vh - 20px);border-radius:9px}.crm-tarea-historial-header,.crm-tarea-historial-seccion{padding-left:16px;padding-right:16px}.crm-tarea-historial-header h3{font-size:18px}.crm-tarea-historial-datos{grid-template-columns:minmax(0,1fr);gap:12px}.crm-tarea-historial-datos .descripcion{grid-column:auto}.crm-tarea-historial-footer{padding:12px 16px 15px}}
</style>
<div class="crm-tarea-historial-modal" id="crmTareaHistorialModal" role="dialog" aria-modal="true" aria-labelledby="crmTareaHistorialNombre" aria-hidden="true">
	<div class="crm-tarea-historial-dialog">
		<div class="crm-tarea-historial-header">
			<div><small>Historial de tareas</small><h3 id="crmTareaHistorialNombre">Tarea</h3><div class="crm-tarea-historial-etiquetas"><span class="crm-tarea-historial-etiqueta" id="crmTareaHistorialEstado"><small>Estado actual</small><span></span></span><span class="crm-tarea-historial-etiqueta" id="crmTareaHistorialPrioridad"><small>Prioridad actual</small><span></span></span></div></div>
			<button type="button" class="crm-tarea-historial-cerrar-x" aria-label="Cerrar detalle" data-crm-tarea-cerrar><i class="fa fa-times" aria-hidden="true"></i></button>
		</div>
		<section class="crm-tarea-historial-seccion" aria-labelledby="crmTareaHistorialMovimientoTitulo">
			<h4 id="crmTareaHistorialMovimientoTitulo"><i class="fa fa-history" aria-hidden="true"></i>Movimiento registrado</h4>
			<span class="crm-tarea-historial-accion"><i class="fa fa-bolt" aria-hidden="true"></i><span id="crmTareaHistorialAccion"></span></span>
			<p class="crm-tarea-historial-explicacion" id="crmTareaHistorialExplicacion"></p>
			<dl class="crm-tarea-historial-datos" style="margin-top:14px"><div><dt>Fecha y hora</dt><dd id="crmTareaHistorialFecha"></dd></div><div><dt>Realizado por</dt><dd id="crmTareaHistorialUsuario"></dd></div></dl>
		</section>
		<section class="crm-tarea-historial-seccion" aria-labelledby="crmTareaHistorialInfoTitulo">
			<h4 id="crmTareaHistorialInfoTitulo"><i class="fa fa-clipboard" aria-hidden="true"></i>Información actual de la tarea</h4>
			<dl class="crm-tarea-historial-datos"><div><dt>Área</dt><dd id="crmTareaHistorialArea"></dd></div><div><dt>Responsable asignado</dt><dd id="crmTareaHistorialResponsables"></dd></div><div><dt>Fecha de inicio</dt><dd id="crmTareaHistorialInicio"></dd></div><div><dt>Fecha de vencimiento</dt><dd id="crmTareaHistorialVencimiento"></dd></div><div class="descripcion"><dt>Descripción</dt><dd class="crm-tarea-historial-descripcion" id="crmTareaHistorialDescripcion"></dd></div></dl>
		</section>
		<div class="crm-tarea-historial-footer"><button type="button" class="btn btn-white" data-crm-tarea-cerrar>Cerrar</button></div>
	</div>
</div>
<script>
(function(){
	var modal=document.getElementById('crmTareaHistorialModal');
	if(!modal || modal.getAttribute('data-ready')) return;
	modal.setAttribute('data-ready','1');
	if(modal.parentNode!==document.body) document.body.appendChild(modal);
	var trigger=null;
	var previousOverflow='';
	var closeButtons=modal.querySelectorAll('[data-crm-tarea-cerrar]');
	function setText(id,value){var element=document.getElementById(id);if(element) element.textContent=value||'';}
	function setBadge(id,label,cssClass){var badge=document.getElementById(id);if(!badge) return;badge.className='crm-tarea-historial-etiqueta '+cssClass;var value=badge.querySelector('span');if(value) value.textContent=label||'No registrado';}
	function close(){
		if(!modal.classList.contains('is-open')) return;
		modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');document.body.classList.remove('crm-tarea-historial-abierto');document.body.style.overflow=previousOverflow;
		if(trigger){trigger.focus();trigger=null;}
	}
	document.addEventListener('click',function(event){
		var fila=event.target.closest ? event.target.closest('[data-crm-tarea-movimiento]') : null;
		if(fila){
			var datos={};try{datos=JSON.parse(fila.getAttribute('data-crm-tarea-movimiento')||'{}');}catch(e){}
			setText('crmTareaHistorialNombre',datos.name||'Tarea sin nombre');
			setBadge('crmTareaHistorialEstado',datos.state,'estado-'+(datos.stateClass||'desconocido'));
			setBadge('crmTareaHistorialPrioridad',datos.priority,'prioridad-'+(datos.priorityClass||'desconocida'));
			setText('crmTareaHistorialAccion',datos.action||'Movimiento registrado');
			setText('crmTareaHistorialExplicacion',datos.explanation||'No se registró una explicación adicional.');
			setText('crmTareaHistorialFecha',datos.date||'Fecha no registrada');
			setText('crmTareaHistorialUsuario',datos.user||'Usuario no registrado');
			setText('crmTareaHistorialArea',datos.area||'Área no registrada');
			setText('crmTareaHistorialResponsables',datos.responsibles||'Sin asignar');
			setText('crmTareaHistorialInicio',datos.startDate||'Sin fecha registrada');
			setText('crmTareaHistorialVencimiento',datos.dueDate||'Sin fecha registrada');
			setText('crmTareaHistorialDescripcion',datos.description||'Sin descripción registrada');
			trigger=fila;previousOverflow=document.body.style.overflow;document.body.classList.add('crm-tarea-historial-abierto');document.body.style.overflow='hidden';modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');closeButtons[0].focus();return;
		}
		if(event.target===modal || (event.target.closest && event.target.closest('[data-crm-tarea-cerrar]'))) close();
	});
	document.addEventListener('keydown',function(event){if(event.key==='Escape' && modal.classList.contains('is-open')) close();});
})();
</script>
