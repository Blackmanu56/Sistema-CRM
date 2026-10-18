<style>
.crm-historial-row{display:block;width:100%;padding:9px 0;border:0;border-bottom:1px solid #edf0f2;background:transparent;text-align:left;color:inherit}
.crm-historial-row:hover,.crm-historial-row:focus{background:#f7fafc;outline:0}
.crm-historial-row strong,.crm-historial-row span{display:block;overflow:hidden;text-overflow:ellipsis}
.crm-historial-row .crm-historial-meta{font-size:12px;color:#64748b;margin-top:3px}
.crm-historial-actividad-modal{display:none;position:fixed;inset:0;z-index:10050;background:rgba(15,23,42,.58);align-items:center;justify-content:center;padding:18px;overflow-y:auto}
.crm-historial-actividad-modal.is-open{display:flex}
.crm-historial-actividad-dialog{position:relative;width:min(680px,100%);max-height:calc(100vh - 36px);overflow-y:auto;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 20px 55px rgba(15,23,42,.28);color:#1e293b}
.crm-historial-actividad-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:18px 22px 15px;background:#f8fafc;border-bottom:1px solid #e8edf2;border-radius:10px 10px 0 0}
.crm-historial-actividad-heading{margin:0 0 5px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
.crm-historial-actividad-name{margin:0;font-size:20px;line-height:1.3;font-weight:700;color:#172554;overflow-wrap:anywhere}
.crm-historial-actividad-badges{display:flex;flex-wrap:wrap;gap:7px;margin-top:10px}
.crm-historial-actividad-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border:1px solid #dbeafe;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:600}
.crm-historial-actividad-badge--type{border-color:#e2e8f0;background:#fff;color:#475569}
.crm-historial-actividad-close{flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;padding:0;border:1px solid #dbe3eb;border-radius:6px;background:#fff;color:#475569;font-size:20px;line-height:1;cursor:pointer}
.crm-historial-actividad-close:hover{background:#f1f5f9}
.crm-historial-actividad-body{padding:17px 22px 19px}
.crm-historial-actividad-section+.crm-historial-actividad-section{margin-top:17px;padding-top:15px;border-top:1px solid #e8edf2}
.crm-historial-actividad-section-title{margin:0 0 11px;font-size:14px;font-weight:700;color:#334155}
.crm-historial-actividad-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 18px}
.crm-historial-actividad-field{min-width:0}
.crm-historial-actividad-label{display:block;margin-bottom:3px;color:#64748b;font-size:10px;font-weight:700;letter-spacing:.045em;text-transform:uppercase}
.crm-historial-actividad-value{display:block;color:#1e293b;font-size:13px;line-height:1.45;overflow-wrap:anywhere}
.crm-historial-actividad-description{margin-top:11px;padding:10px 12px;border:1px solid #e8edf2;border-radius:6px;background:#f8fafc}
.crm-historial-actividad-description .crm-historial-actividad-value{white-space:pre-wrap}
.crm-historial-actividad-footer{display:flex;justify-content:flex-end;padding:0 22px 17px}
@media(max-width:560px){.crm-historial-actividad-modal{padding:10px}.crm-historial-actividad-dialog{max-height:calc(100vh - 20px)}.crm-historial-actividad-header{padding:15px 16px 13px}.crm-historial-actividad-body{padding:14px 16px}.crm-historial-actividad-footer{padding:0 16px 15px}.crm-historial-actividad-name{font-size:18px}.crm-historial-actividad-grid{grid-template-columns:minmax(0,1fr);gap:10px}}
</style>
<div class="crm-historial-actividad-modal" id="crmHistorialActividadModal" aria-hidden="true">
	<div class="crm-historial-actividad-dialog" role="dialog" aria-modal="true" aria-labelledby="crmHistorialActividadNombre">
		<header class="crm-historial-actividad-header">
			<div>
				<p class="crm-historial-actividad-heading" id="crmHistorialActividadHeading">Historial de actividades</p>
				<h4 class="crm-historial-actividad-name" id="crmHistorialActividadNombre"></h4>
				<div class="crm-historial-actividad-badges">
					<span class="crm-historial-actividad-badge"><i class="fa fa-circle" aria-hidden="true"></i><span id="crmHistorialActividadEstado"></span></span>
					<span class="crm-historial-actividad-badge crm-historial-actividad-badge--type"><i class="fa fa-tag" aria-hidden="true"></i><span id="crmHistorialActividadTipo"></span></span>
				</div>
			</div>
			<button type="button" class="crm-historial-actividad-close" data-crm-actividad-historial-cerrar aria-label="Cerrar">&times;</button>
		</header>
		<div class="crm-historial-actividad-body">
			<section class="crm-historial-actividad-section" aria-labelledby="crmHistorialMovimientoTitulo">
				<h5 class="crm-historial-actividad-section-title" id="crmHistorialMovimientoTitulo">Movimiento registrado</h5>
				<div class="crm-historial-actividad-grid">
					<div class="crm-historial-actividad-field"><span class="crm-historial-actividad-label">Acción realizada</span><span class="crm-historial-actividad-value" id="crmHistorialActividadAccion"></span></div>
					<div class="crm-historial-actividad-field"><span class="crm-historial-actividad-label">Fecha y hora del movimiento</span><time class="crm-historial-actividad-value" id="crmHistorialActividadFechaMovimiento"></time></div>
				</div>
				<div class="crm-historial-actividad-description"><span class="crm-historial-actividad-label">Detalle</span><span class="crm-historial-actividad-value" id="crmHistorialActividadDetalleMovimiento"></span></div>
				<div class="crm-historial-actividad-field" id="crmHistorialActividadAutorCampo" hidden><span class="crm-historial-actividad-label">Realizado por</span><span class="crm-historial-actividad-value" id="crmHistorialActividadAutor"></span></div>
			</section>
			<section class="crm-historial-actividad-section" aria-labelledby="crmHistorialActividadDatosTitulo">
				<h5 class="crm-historial-actividad-section-title" id="crmHistorialActividadDatosTitulo">Información actual de la actividad</h5>
				<div class="crm-historial-actividad-grid">
					<div class="crm-historial-actividad-field"><span class="crm-historial-actividad-label">Área</span><span class="crm-historial-actividad-value" id="crmHistorialActividadArea"></span></div>
					<div class="crm-historial-actividad-field"><span class="crm-historial-actividad-label">Responsable</span><span class="crm-historial-actividad-value" id="crmHistorialActividadResponsable"></span></div>
					<div class="crm-historial-actividad-field"><span class="crm-historial-actividad-label">Fecha de la actividad</span><span class="crm-historial-actividad-value" id="crmHistorialActividadFecha"></span></div>
					<div class="crm-historial-actividad-field"><span class="crm-historial-actividad-label">Tipo</span><span class="crm-historial-actividad-value" id="crmHistorialActividadTipoActual"></span></div>
				</div>
				<div class="crm-historial-actividad-description"><span class="crm-historial-actividad-label">Descripción actual</span><span class="crm-historial-actividad-value" id="crmHistorialActividadDescripcion"></span></div>
			</section>
		</div>
		<footer class="crm-historial-actividad-footer"><button type="button" class="btn btn-white" data-crm-actividad-historial-cerrar>Cerrar</button></footer>
	</div>
</div>
<script>
(function(){
	var modal=document.getElementById('crmHistorialActividadModal');
	if(!modal || modal.getAttribute('data-ready')) return;
	modal.setAttribute('data-ready','1');
	if(modal.parentNode!==document.body) document.body.appendChild(modal);
	function setText(id,value){var element=document.getElementById(id);if(element) element.textContent=value||'';}
	function close(){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');}
	document.addEventListener('click',function(event){
		var row=event.target.closest ? event.target.closest('[data-crm-historial]') : null;
		if(row){
			var data={};try{data=JSON.parse(row.getAttribute('data-crm-historial')||'{}');}catch(e){return;}
			if(data.kind!=='activity') return;
			setText('crmHistorialActividadHeading',data.heading||'Historial de actividades');
			setText('crmHistorialActividadNombre',data.name||'Actividad sin nombre');
			setText('crmHistorialActividadEstado',data.status||'Estado no disponible');
			setText('crmHistorialActividadTipo',data.type||'Tipo no disponible');
			setText('crmHistorialActividadAccion',data.action||'Acción no disponible');
			setText('crmHistorialActividadFechaMovimiento',data.date||'Fecha no registrada');
			setText('crmHistorialActividadDetalleMovimiento',data.movementDescription||'No hay más detalles históricos disponibles.');
			setText('crmHistorialActividadArea',data.area||'No disponible');
			setText('crmHistorialActividadResponsable',data.responsible||'Sin asignar');
			setText('crmHistorialActividadFecha',data.activityDate||'No disponible');
			setText('crmHistorialActividadTipoActual',data.type||'Tipo no disponible');
			setText('crmHistorialActividadDescripcion',data.description||'Sin descripción registrada.');
			var authorField=document.getElementById('crmHistorialActividadAutorCampo');
			if(authorField){authorField.hidden=!data.user;setText('crmHistorialActividadAutor',data.user||'');}
			modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');
		}
		if(event.target===modal || (event.target.closest && event.target.closest('[data-crm-actividad-historial-cerrar]'))) close();
	});
	document.addEventListener('keydown',function(event){if(event.key==='Escape' && modal.classList.contains('is-open')) close();});
})();
</script>
