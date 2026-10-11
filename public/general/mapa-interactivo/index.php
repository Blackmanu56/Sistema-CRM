<?php
require_once __DIR__.'/../../_init.php';
crm_proteger_staff();
include __DIR__.'/../datos-tareas.php';
tareas_iniciar_contexto();

if(!acceso_es_coord_general() && !acceso_es_super() && !acceso_es_general())
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
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<style>
#mapa-eventos { height:68vh; min-height:420px; border:1px solid #d8d8d8; }
.mapa-controles { margin:10px 0; }
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
				<div id="mapa-aviso" class="alert alert-warning mapa-aviso" role="status"></div>
				<div id="mapa-error" class="alert alert-danger mapa-aviso" role="alert"></div>
				<div id="mapa-eventos"
					data-endpoint="<?php echo htmlspecialchars(url_crm('general/mapa-interactivo/api.php'), ENT_QUOTES, 'UTF-8'); ?>"
					data-geojson="<?php echo htmlspecialchars($geojson_url, ENT_QUOTES, 'UTF-8'); ?>"></div>
				<div style="margin-top:8px;">
					<i class="fa fa-map-marker" style="color:#337ab7;"></i>
					Cada marcador indica una localidad; el número representa los eventos activos en ella.
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
	var mapa = L.map(mapaEl).setView([-27.4, -54.5], 7);
	var marcadores = L.markerClusterGroup();
	var actualizando = false;
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
			agregarTexto(lista, 'li', evento.titulo + ' · ' + fecha);
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
		if(actualizando || document.visibilityState === 'hidden') { return; }
		actualizando = true;
		error.style.display = 'none';
		fetch(mapaEl.getAttribute('data-endpoint'), {cache:'no-store', credentials:'same-origin'}).then(function(respuesta){
			return respuesta.json().then(function(datos){
				if(!respuesta.ok || datos.ok === false)
				{
					throw new Error(datos.error || 'No se pudieron actualizar los eventos.');
				}
				return datos;
			});
		}).then(function(datos){
			marcadores.clearLayers();
			var totalEventos = 0;
			for(var i = 0; i < datos.features.length; i++)
			{
				var feature = datos.features[i];
				var propiedades = feature.properties;
				totalEventos += propiedades.cantidad_eventos;
				var icono = L.divIcon({
					className:'',
					html:'<span class="mapa-icono">' + propiedades.cantidad_eventos + '</span>',
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
			if(datos.meta && datos.meta.eventos_sin_coordenadas > 0)
			{
				aviso.textContent = datos.meta.eventos_sin_coordenadas + ' evento(s) no aparecen porque falta cargar coordenadas en su localidad.';
				aviso.style.display = 'block';
			}
			else if(geojsonUrl)
			{
				aviso.style.display = 'none';
			}
		}).catch(function(err){
			error.textContent = err.message;
			error.style.display = 'block';
		}).then(function(){
			actualizando = false;
		});
	}

	cargarLimites();
	actualizar();
	document.getElementById('mapa-actualizar').onclick = actualizar;
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
