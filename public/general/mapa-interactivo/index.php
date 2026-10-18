<?php
require_once __DIR__.'/../../_init.php';
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
.mapa-popup h4 { margin:0 0 8px; }
.mapa-popup ul { margin:0; padding-left:18px; }
.mapa-popup li { margin-bottom:5px; }
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
					data-geojson="<?php echo htmlspecialchars($geojson_url, ENT_QUOTES, 'UTF-8'); ?>"></div>
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
	var mapa = L.map(mapaEl).setView([-27.4, -54.5], 7);
	var marcadores = L.markerClusterGroup();
	var actualizando = false;
	var actualizarPendiente = false;
	var temporizador = null;
	var geojsonUrl = mapaEl.getAttribute('data-geojson');

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
		agregarTexto(contenedor, 'h4', propiedades.localidad + ' (' + propiedades.cantidad_eventos + ')');
		var lista = document.createElement('ul');
		for(var i = 0; i < propiedades.eventos.length; i++)
		{
			var evento = propiedades.eventos[i];
			var fecha = evento.fecha
				? new Date(evento.fecha + 'T00:00:00').toLocaleDateString('es-AR')
				: 'Fecha sin especificar';
			var item = document.createElement('li');
			if(evento.url)
			{
				var enlace = document.createElement('a');
				enlace.href = evento.url;
				enlace.title = 'Ver actividad en ' + (evento.area || 'el área correspondiente');
				enlace.textContent = evento.titulo;
				item.appendChild(enlace);
			}
			else
			{
				item.appendChild(document.createTextNode(evento.titulo));
			}
			item.appendChild(document.createTextNode(' · ' + fecha + ' · ' + (evento.area || 'Área sin especificar') + ' · ' + (evento.activo === 1 ? 'Activo' : 'No activo')));
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
				mapa.fitBounds(bounds, {padding:[20,20], maxZoom:7});
			}
		}).catch(function(err){
			aviso.textContent = err.message;
			aviso.style.display = 'block';
		});
	}

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
				marcador.bindPopup(crearPopup(feature));
				marcadores.addLayer(marcador);
			}
			document.getElementById('mapa-total-localidades').textContent = datos.features.length;
			document.getElementById('mapa-total-eventos').textContent = totalEventos;
			document.getElementById('mapa-ultima-actualizacion').textContent = 'Actualizado: ' + new Date().toLocaleTimeString('es-AR');
			var avisos = [];
			if(!geojsonUrl) { avisos.push('Para ver los límites municipales, colocá el GeoJSON en public/general/mapa-interactivo/geojson/misiones-municipios.geojson.'); }
			if(datos.meta && datos.meta.eventos_sin_localidad > 0) { avisos.push(datos.meta.eventos_sin_localidad + ' evento(s) no tienen localidad (ubicación desconocida; no se presume que sean de Misiones).'); }
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
