<?php
require_once __DIR__.'/../../_init.php';
require_once __DIR__.'/contexto.php';
require_once BASE_PATH.'/includes/navegacion-contexto.php';
crm_proteger_staff();
include __DIR__.'/../datos-tareas.php';
tareas_iniciar_contexto();

if(!acceso_puede_ver_mapa_eventos())
{
	http_response_code(403);
	exit('No tenés permiso para ver este mapa.');
}

$geojson=__DIR__.'/geojson/misiones-municipios.geojson';
$geojson_url=is_file($geojson)
	? url_crm('general/mapa-interactivo/geojson/misiones-municipios.geojson')
	: '';
$mapa_token_general=isset($_GET['origen_general']) && mapa_interactivo_contexto_general_valido($_GET['origen_general'])
	? $_GET['origen_general']
	: '';
$mapa_contexto_nav_general=isset($_GET['contexto_general']) && is_string($_GET['contexto_general']) && crm_navegacion_contexto_general_valido($_GET['contexto_general']);
$mapa_token_nav_general=$mapa_contexto_nav_general ? $_GET['contexto_general'] : '';
$mapa_navbar_path='';
$mapa_navbar_area=function_exists('acceso_area_activa') ? acceso_area_activa() : '';
if($mapa_contexto_nav_general) { $mapa_navbar_path=BASE_PATH.'/public/general/menu-seccion.php'; }
elseif($mapa_navbar_area!=='' && $mapa_navbar_area!=='admin' && is_file(BASE_PATH.'/public/'.$mapa_navbar_area.'/menu-seccion.php')) { $mapa_navbar_path=BASE_PATH.'/public/'.$mapa_navbar_area.'/menu-seccion.php'; }
elseif(acceso_es_super() || acceso_es_coord_general()) { $mapa_navbar_path=BASE_PATH.'/public/general/menu-seccion.php'; }
$menu_activo='mapa_eventos';
$mapa_token_retorno=mapa_interactivo_token_retorno();
$mapa_retorno_valido=isset($_GET['mapa_retorno']) && mapa_interactivo_token_retorno_valido($_GET['mapa_retorno'])
	? $_GET['mapa_retorno']
	: '';
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Mapa interactivo de eventos</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<style>
#mapa-eventos { height:68vh; min-height:420px; border:1px solid #d8d8d8; }
.mapa-controles { margin:10px 0; }
.mapa-filtros { display:flex; flex-wrap:wrap; align-items:flex-end; gap:10px; margin:14px 0; }
.mapa-filtro { min-width:190px; flex:1 1 190px; }
.mapa-filtro label { display:block; margin-bottom:4px; }
.mapa-contador { display:inline-block; margin-right:18px; font-weight:600; }
.mapa-aviso { display:none; }
.mapa-popup { max-height:60vh; overflow-y:auto; padding:2px; }
.mapa-popup h4 { border-bottom:1px solid #e7eaf0; color:#243447; font-size:15px; font-weight:600; margin:0 0 9px; padding:0 0 8px; }
.mapa-popup-eventos { display:grid; gap:8px; }
.mapa-popup-evento-card { align-items:stretch; background:#f8fafc; border:1px solid #e5eaf0; border-radius:6px; color:inherit; display:block; padding:9px 10px; text-align:left; width:100%; }
.mapa-popup-evento-card-action { appearance:none; cursor:pointer; }
.mapa-popup-evento-card-action:hover { background:#f1f6fb; border-color:#cbd9e6; }
.mapa-popup-evento-card-action:focus { border-color:#337ab7; box-shadow:0 0 0 2px rgba(51,122,183,.2); outline:2px solid transparent; }
.mapa-popup-evento-titulo { color:#243447; display:flex; font-size:13px; font-weight:600; justify-content:space-between; line-height:1.4; }
.mapa-popup-evento-flecha { color:#64748b; flex:0 0 auto; margin-left:12px; }
.mapa-popup-meta { align-items:center; color:#5b6770; display:flex; flex-wrap:wrap; font-size:11px; gap:5px 10px; line-height:1.4; margin-top:6px; }
.mapa-popup-estado { border-radius:999px; font-size:10px; font-weight:600; padding:2px 7px; }
.mapa-popup-estado.activo { background:#e7f5ed; color:#287447; }
.mapa-popup-estado.inactivo { background:#eef1f4; color:#5d6872; }
.mapa-icono { background:#337ab7; border:2px solid #fff; border-radius:24px; box-shadow:0 1px 5px #555; color:#fff; font:bold 14px/38px Arial,sans-serif; height:42px; text-align:center; width:42px; }
</style>
</head>
<body>
<?php crm_header_staff(); ?>
<div class="page-container row">
	<?php crm_menu_staff(); ?>
	<div class="clearfix"></div>
</div>
</div>
<div class="page-content">
	<div class="clearfix"></div>
	<div class="content">
		<ul class="breadcrumb">
			<li><p>ESTÁS AQUÍ</p></li>
			<li><a href="<?php echo htmlspecialchars(url_crm('general/actividades.php'), ENT_QUOTES, 'UTF-8'); ?>">Actividades</a></li>
			<li><a href="#" class="active">Mapa interactivo</a></li>
		</ul>
		<?php if($mapa_navbar_path!=='') { require $mapa_navbar_path; } ?>
		<div class="page-title"><h3>Mapa interactivo de eventos · Misiones</h3></div>
		<div class="grid simple">
			<div class="grid-body">
				<div class="mapa-controles">
					<span class="mapa-contador">Localidades: <span id="mapa-total-localidades">—</span></span>
					<span class="mapa-contador">Eventos: <span id="mapa-total-eventos">—</span></span>
					<button type="button" id="mapa-actualizar" class="btn btn-white btn-small"><i class="fa fa-refresh"></i> Actualizar</button>
					<span id="mapa-ultima-actualizacion" class="text-muted"></span>
				</div>
				<div class="mapa-filtros" aria-label="Filtros del mapa">
					<div class="mapa-filtro">
						<label for="mapa-filtro-area">Área</label>
						<select id="mapa-filtro-area" class="form-control"><option value="">Todas las áreas</option></select>
					</div>
					<div class="mapa-filtro">
						<label for="mapa-filtro-tipo">Tipo de evento</label>
						<select id="mapa-filtro-tipo" class="form-control"><option value="">Todos los tipos</option></select>
					</div>
					<div class="mapa-filtro">
						<label for="mapa-filtro-localidad">Localidad</label>
						<select id="mapa-filtro-localidad" class="form-control"><option value="">Todas las localidades</option></select>
					</div>
					<div class="mapa-filtro">
						<label for="mapa-filtro-activo">Estado</label>
						<select id="mapa-filtro-activo" class="form-control">
							<option value="1" selected>Activos</option>
							<option value="0">No activos</option>
							<option value="todos">Todos</option>
						</select>
					</div>
					<button type="button" id="mapa-limpiar-filtros" class="btn btn-white">Limpiar filtros</button>
				</div>
				<div id="mapa-aviso" class="alert alert-warning mapa-aviso" role="status"></div>
				<div id="mapa-error" class="alert alert-danger mapa-aviso" role="alert"></div>
				<div id="mapa-eventos"
					data-endpoint="<?php echo htmlspecialchars(url_crm('general/mapa-interactivo/api.php'), ENT_QUOTES, 'UTF-8'); ?>"
					data-origen-general="<?php echo htmlspecialchars($mapa_token_general, ENT_QUOTES, 'UTF-8'); ?>"
					data-contexto-general="<?php echo htmlspecialchars($mapa_token_nav_general, ENT_QUOTES, 'UTF-8'); ?>"
					data-return-token="<?php echo htmlspecialchars($mapa_token_retorno, ENT_QUOTES, 'UTF-8'); ?>"
					data-return-marker="<?php echo htmlspecialchars($mapa_retorno_valido, ENT_QUOTES, 'UTF-8'); ?>"
					data-geojson="<?php echo htmlspecialchars($geojson_url, ENT_QUOTES, 'UTF-8'); ?>"></div>
				<div id="mapa-sin-resultados" class="alert alert-info" role="status" hidden>No hay eventos con los filtros seleccionados.</div>
				<div style="margin-top:8px;">
					<i class="fa fa-map-marker" style="color:#337ab7;"></i>
					Cada marcador indica una localidad; el número representa los eventos que coinciden con los filtros actuales.
					Los polígonos muestran los límites municipales; pasá el cursor para ver el nombre (Fuente: Gobierno de Misiones).
				</div>
				<p class="text-muted" style="margin-top:8px;">Actualización automática cada minuto mientras esta página esté visible.</p>
			</div>
		</div>
	</div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="../../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script>
(function(){
	var mapaEl = document.getElementById('mapa-eventos');
	var aviso = document.getElementById('mapa-aviso');
	var error = document.getElementById('mapa-error');
	var filtroArea = document.getElementById('mapa-filtro-area');
	var filtroTipo = document.getElementById('mapa-filtro-tipo');
	var filtroLocalidad = document.getElementById('mapa-filtro-localidad');
	var filtroActivo = document.getElementById('mapa-filtro-activo');
	var mapa = L.map(mapaEl).setView([-26.8, -54.6], 7);
	var marcadores = L.markerClusterGroup();
	var actualizando = false;
	var actualizarPendiente = false;
	var temporizador = null;
	var geojsonUrl = mapaEl.getAttribute('data-geojson');
	var limitesMunicipales = null;
	var vistaManual = false;
	var temporizadorRedimension = null;
	var claveEstadoMapa = 'crm_mapa_eventos_return_state';
	var estadoRetorno = null;
	var restaurarFiltrosPendiente = false;
	try
	{
		var marcadorRetorno = mapaEl.getAttribute('data-return-marker');
		var estadoGuardado = sessionStorage.getItem(claveEstadoMapa);
		if(marcadorRetorno && estadoGuardado)
		{
			var candidatoEstado = JSON.parse(estadoGuardado);
			if(candidatoEstado && candidatoEstado.returnToken === marcadorRetorno)
			{
				estadoRetorno = candidatoEstado;
				restaurarFiltrosPendiente = true;
				var centro = candidatoEstado.center;
				if(centro && isFinite(centro.lat) && isFinite(centro.lng) && isFinite(centro.zoom)
					&& centro.lat >= -90 && centro.lat <= 90 && centro.lng >= -180 && centro.lng <= 180
					&& centro.zoom >= 0 && centro.zoom <= 18)
				{ mapa.setView([centro.lat, centro.lng], centro.zoom); vistaManual = true; }
			}
		}
		if(estadoRetorno) { sessionStorage.removeItem(claveEstadoMapa); }
	}
	catch(errorEstado) { estadoRetorno = null; }

	L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom:18,
		attribution:'&copy; OpenStreetMap'
	}).addTo(mapa);
	marcadores.addTo(mapa);

	function agregarTexto(parent, tag, texto)
	{
		var elemento = document.createElement(tag);
		elemento.textContent = texto;
		parent.appendChild(elemento);
		return elemento;
	}

	function urlActividadAutorizada(evento)
	{
		if(!evento || typeof evento.url !== 'string' || !evento.url) { return null; }
		try
		{
			var destino = new URL(evento.url, window.location.href);
			if(destino.origin !== window.location.origin || !/\/public\/[a-z0-9_-]+\/actividades\.php$/i.test(destino.pathname)) { return null; }
			var idActividad = parseInt(destino.searchParams.get('id'), 10);
			if(!idActividad || idActividad < 1) { return null; }
			if(destino.searchParams.get('contexto') === 'general')
			{
				var tokenGeneral = mapaEl.getAttribute('data-origen-general');
				if(!tokenGeneral || destino.searchParams.get('origen_general') !== tokenGeneral) { return null; }
			}
			var contextoGeneral = mapaEl.getAttribute('data-contexto-general');
			if(contextoGeneral) { destino.searchParams.set('contexto_general', contextoGeneral); }
			var tokenRetorno = mapaEl.getAttribute('data-return-token');
			if(tokenRetorno) { destino.searchParams.set('mapa_retorno', tokenRetorno); }
			return destino.href;
		}
		catch(errorUrl) { return null; }
	}

	function cargarOpciones(select, opciones, valor, etiqueta, opcionVacia)
	{
		var seleccionado = select.value;
		select.innerHTML = '';
		var opcion = document.createElement('option');
		opcion.value = '';
		opcion.textContent = opcionVacia;
		select.appendChild(opcion);
		for(var i = 0; i < opciones.length; i++)
		{
			opcion = document.createElement('option');
			opcion.value = opciones[i][valor];
			opcion.textContent = opciones[i][etiqueta];
			select.appendChild(opcion);
		}
		select.value = seleccionado;
	}

	function crearPopup(feature)
	{
		var propiedades = feature.properties;
		var contenedor = document.createElement('div');
		contenedor.className = 'mapa-popup';
		agregarTexto(contenedor, 'h4', propiedades.localidad + ' · ' + propiedades.cantidad_eventos + ' eventos');
		var lista = document.createElement('div');
		lista.className = 'mapa-popup-eventos';
		for(var i = 0; i < propiedades.eventos.length; i++)
		{
			var evento = propiedades.eventos[i];
			var fecha = evento.fecha ? new Date(evento.fecha + 'T00:00:00').toLocaleDateString('es-AR') : 'Fecha sin especificar';
			var detalleUrl = urlActividadAutorizada(evento);
			var item = detalleUrl ? document.createElement('button') : document.createElement('article');
			if(detalleUrl)
			{
				item.type = 'button';
				item.className = 'mapa-popup-evento-card mapa-popup-evento-card-action';
				item.addEventListener('click', function(url){
					return function(){
						var tokenRetorno = mapaEl.getAttribute('data-return-token');
						try
						{
							if(tokenRetorno) { sessionStorage.setItem(claveEstadoMapa, JSON.stringify({
								returnToken:tokenRetorno,
								filters:{area:filtroArea.value, tipo:filtroTipo.value, localidad:filtroLocalidad.value, activo:filtroActivo.value},
								center:{lat:mapa.getCenter().lat, lng:mapa.getCenter().lng, zoom:mapa.getZoom()}
							})); }
						}
						catch(errorStorage) {}
						window.location.assign(url);
					};
				}(detalleUrl));
			}
			else { item.className = 'mapa-popup-evento-card'; }
			var titulo = document.createElement('span');
			titulo.className = 'mapa-popup-evento-titulo';
			var textoTitulo = document.createElement('span');
			textoTitulo.textContent = evento.titulo || 'Actividad sin título';
			titulo.appendChild(textoTitulo);
			if(detalleUrl)
			{
				var flecha = document.createElement('i');
				flecha.className = 'fa fa-arrow-right mapa-popup-evento-flecha';
				flecha.setAttribute('aria-hidden', 'true');
				titulo.appendChild(flecha);
			}
			item.appendChild(titulo);
			var metadatos = document.createElement('div');
			metadatos.className = 'mapa-popup-meta';
			var fechaElemento = document.createElement('span');
			fechaElemento.textContent = fecha;
			metadatos.appendChild(fechaElemento);
			var areaElemento = document.createElement('span');
			areaElemento.textContent = evento.area || 'Área sin especificar';
			metadatos.appendChild(areaElemento);
			var estadoElemento = document.createElement('span');
			estadoElemento.className = 'mapa-popup-estado ' + (evento.activo === 1 ? 'activo' : 'inactivo');
			estadoElemento.textContent = evento.activo === 1 ? 'Activo' : 'No activo';
			metadatos.appendChild(estadoElemento);
			if(!detalleUrl)
			{
				var noDisponible = document.createElement('span');
				noDisponible.className = 'text-muted';
				noDisponible.textContent = 'Detalle no disponible';
				metadatos.appendChild(noDisponible);
			}
			item.appendChild(metadatos);
			lista.appendChild(item);
		}
		contenedor.appendChild(lista);
		return contenedor;
	}

	function cargarLimites()
	{
		if(!geojsonUrl)
		{
			aviso.textContent = 'Para ver los límites municipales, colocá el GeoJSON en public/general/mapa-interactivo/geojson/misiones-municipios.geojson.';
			aviso.style.display = 'block';
			return;
		}
		fetch(geojsonUrl, {cache:'no-cache'}).then(function(respuesta){
			if(!respuesta.ok) { throw new Error('No se pudo cargar el GeoJSON de límites.'); }
			return respuesta.json();
		}).then(function(geojson){
			var limites = L.geoJSON(geojson, {
				style:{color:'#356b8c', weight:0.5, fillColor:'#9fc5dc', fillOpacity:0.12},
				onEachFeature:function(feature, layer){
					var municipio = feature.properties && feature.properties.Municipio;
					if(municipio)
					{
						layer.bindTooltip(municipio);
					}
					layer.on({
						mouseover:function(){ layer.setStyle({weight:0.8, fillOpacity:0.22}); },
						mouseout:function(){ limites.resetStyle(layer); }
					});
				}
			}).addTo(mapa);
			var bounds = limites.getBounds();
			if(bounds.isValid())
			{
				limitesMunicipales = bounds;
				mapa.invalidateSize({pan:false});
				if(!vistaManual)
				{
					mapa.fitBounds(limitesMunicipales, {padding:[20,20], maxZoom:9});
				}
			}
		}).catch(function(err){
			aviso.textContent = err.message;
			aviso.style.display = 'block';
		});
	}

	function registrarInteraccionManual()
	{
		vistaManual = true;
	}
	mapa.on('dragstart', registrarInteraccionManual);
	['wheel', 'dblclick', 'keydown'].forEach(function(tipo){
		mapaEl.addEventListener(tipo, registrarInteraccionManual, {passive:true});
	});
	mapaEl.addEventListener('touchstart', function(evento){
		if(evento.touches && evento.touches.length > 1) { registrarInteraccionManual(); }
	}, {passive:true});
	window.addEventListener('resize', function(){
		if(temporizadorRedimension) { window.clearTimeout(temporizadorRedimension); }
		temporizadorRedimension = window.setTimeout(function(){
			mapa.invalidateSize({pan:false});
			if(limitesMunicipales && !vistaManual)
			{
				mapa.fitBounds(limitesMunicipales, {padding:[20,20], maxZoom:9});
			}
		}, 150);
	});

	function actualizar()
	{
		if(document.visibilityState === 'hidden') { return; }
		if(actualizando)
		{
			actualizarPendiente = true;
			return;
		}
		actualizando = true;
		error.style.display = 'none';
		var parametros = [];
		if(filtroArea.value !== '') { parametros.push('id_area=' + encodeURIComponent(filtroArea.value)); }
		if(filtroTipo.value !== '') { parametros.push('tipo=' + encodeURIComponent(filtroTipo.value)); }
		if(filtroLocalidad.value !== '') { parametros.push('id_localidad=' + encodeURIComponent(filtroLocalidad.value)); }
		if(filtroActivo.value !== '') { parametros.push('activo=' + encodeURIComponent(filtroActivo.value)); }
		var endpoint = mapaEl.getAttribute('data-endpoint');
		var tokenGeneral = mapaEl.getAttribute('data-origen-general');
		if(tokenGeneral) { parametros.push('origen_general=' + encodeURIComponent(tokenGeneral)); }
		if(parametros.length > 0) { endpoint += '?' + parametros.join('&'); }
		fetch(endpoint, {cache:'no-store', credentials:'same-origin'}).then(function(respuesta){
			return respuesta.json().then(function(datos){
				if(!respuesta.ok || datos.ok === false)
				{
					throw new Error(datos.error || 'No se pudieron actualizar los eventos.');
				}
				return datos;
			});
		}).then(function(datos){
			if(datos.filters)
			{
				cargarOpciones(filtroArea, datos.filters.areas, 'id', 'nombre', 'Todas las áreas');
				cargarOpciones(filtroTipo, datos.filters.tipos.map(function(tipo){ return {id:tipo, nombre:tipo}; }), 'id', 'nombre', 'Todos los tipos');
				cargarOpciones(filtroLocalidad, datos.filters.localidades, 'id', 'nombre', 'Todas las localidades');
			}
			if(restaurarFiltrosPendiente && estadoRetorno && estadoRetorno.filters)
			{
				var filtrosGuardados = estadoRetorno.filters;
				[filtroArea, filtroTipo, filtroLocalidad].forEach(function(select, indice){
					var valor = [filtrosGuardados.area, filtrosGuardados.tipo, filtrosGuardados.localidad][indice];
					if(typeof valor === 'string' && Array.prototype.some.call(select.options, function(opcion){ return opcion.value === valor; })) { select.value = valor; }
				});
				if(['', '1', '0', 'todos'].indexOf(filtrosGuardados.activo) !== -1) { filtroActivo.value = filtrosGuardados.activo; }
				restaurarFiltrosPendiente = false;
				actualizarPendiente = true;
			}
			marcadores.clearLayers();
			var totalEventos = 0;
			for(var i = 0; i < datos.features.length; i++)
			{
				var feature = datos.features[i];
				var propiedades = feature.properties;
				totalEventos += propiedades.cantidad_eventos;
				var icono = L.divIcon({
					className:'',
					html:'<span class="mapa-icono">' + parseInt(propiedades.cantidad_eventos, 10) + '</span>',
					iconSize:[42,42],
					iconAnchor:[21,21]
				});
				var marcador = L.marker([
					feature.geometry.coordinates[1],
					feature.geometry.coordinates[0]
				], {icon:icono});
				marcador.bindPopup(crearPopup(feature), {maxWidth:380, minWidth:250, maxHeight:420});
				marcadores.addLayer(marcador);
			}
			document.getElementById('mapa-total-localidades').textContent = datos.features.length;
			document.getElementById('mapa-total-eventos').textContent = totalEventos;
			document.getElementById('mapa-sin-resultados').hidden = totalEventos !== 0;
			document.getElementById('mapa-ultima-actualizacion').textContent = 'Actualizado: ' + new Date().toLocaleTimeString('es-AR');
			var avisos = [];
			if(!geojsonUrl) { avisos.push('Para ver los límites municipales, colocá el GeoJSON en public/general/mapa-interactivo/geojson/misiones-municipios.geojson.'); }
			if(datos.meta && datos.meta.eventos_sin_coordenadas > 0) { avisos.push(datos.meta.eventos_sin_coordenadas + ' evento(s) tienen localidad sin coordenadas. No se asignaron ubicaciones automáticamente.'); }
			aviso.textContent = avisos.join(' ');
			aviso.style.display = avisos.length ? 'block' : 'none';
		}).catch(function(err){
			error.textContent = err.message;
			error.style.display = 'block';
		}).then(function(){
			actualizando = false;
			if(actualizarPendiente && document.visibilityState !== 'hidden')
			{
				actualizarPendiente = false;
				actualizar();
			}
		});
	}

	cargarLimites();
	actualizar();
	document.getElementById('mapa-actualizar').onclick = actualizar;
	[filtroArea, filtroTipo, filtroLocalidad, filtroActivo].forEach(function(filtro){
		filtro.addEventListener('change', actualizar);
	});
	document.getElementById('mapa-limpiar-filtros').addEventListener('click', function(){
		filtroArea.value = '';
		filtroTipo.value = '';
		filtroLocalidad.value = '';
		filtroActivo.value = '1';
		actualizar();
	});
	function actualizarTemporizador()
	{
		if(temporizador) { window.clearInterval(temporizador); }
		if(document.visibilityState === 'visible')
		{
			temporizador = window.setInterval(actualizar, 60000);
		}
	}
	document.addEventListener('visibilitychange', function(){
		actualizarTemporizador();
		if(document.visibilityState === 'visible') { actualizar(); }
	});
	actualizarTemporizador();

})();
</script>
</body>
</html>
