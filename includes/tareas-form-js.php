<script>
// Buscador de clientes vía AJAX (no carga todos los registros).
var urlBuscarClientes = <?php echo json_encode(url_crm('ajax/clientes-buscar.php'), JSON_UNESCAPED_UNICODE); ?>;
var timerBuscarCli = null;
var solicitudClientesTarea = 0;
function textoClienteItem(cli)
{
	var t = cli.nombre || '';
	if(cli.cuil_cuit) { t += ' · ' + cli.cuil_cuit; }
	else if(cli.dni) { t += ' · DNI ' + cli.dni; }
	if(cli.email) { t += ' — ' + cli.email; }
	if(cli.localidad) { t += ' (' + cli.localidad + ')'; }
	return t;
}
function mostrarResultadosCliente(lista)
{
	var caja = document.getElementById('resultados_cliente');
	if(!caja) { return; }
	caja.innerHTML = '';
	if(!lista || lista.length === 0)
	{
		caja.style.display = 'none';
		return;
	}
	caja.style.display = 'block';
	for(var i=0; i<lista.length; i++)
	{
		(function(cli)
		{
			var fila = document.createElement('div');
			fila.style.padding = '8px 10px';
			fila.style.cursor = 'pointer';
			fila.style.borderBottom = '1px solid #eee';
			fila.style.background = '#fff';
			var nombre = document.createElement('strong');
		nombre.appendChild(document.createTextNode(cli.nombre || 'Cliente'));
			fila.appendChild(nombre);
			var identificador = cli.cuil_cuit || (cli.dni ? 'DNI ' + cli.dni : '');
			if(identificador)
			{
				var detalle = document.createElement('small');
				detalle.style.display = 'block';
				detalle.style.color = '#777';
				detalle.appendChild(document.createTextNode(identificador));
				fila.appendChild(detalle);
			}
			fila.onmouseover = function(){ fila.style.background = '#f5f5f5'; };
			fila.onmouseout = function(){ fila.style.background = '#fff'; };
			fila.onclick = function(){ elegirClienteTarea(cli); };
			caja.appendChild(fila);
		})(lista[i]);
	}
}
function elegirClienteTarea(cli)
{
	solicitudClientesTarea++;
	if(timerBuscarCli) { clearTimeout(timerBuscarCli); }
	var id = document.getElementById('cliente_id');
	var nom = document.getElementById('cliente_nombre');
	var bus = document.getElementById('buscar_cliente');
	if(id) { id.value = cli.id; }
	if(nom) { nom.value = cli.nombre; }
	if(bus) { bus.value = ''; }
	if(nom) { nom.style.borderColor = '#5cb85c'; nom.style.background = '#f4fbf4'; }
	var limpiar = document.getElementById('btn_quitar_cliente');
	if(limpiar) { limpiar.disabled = false; }
	mostrarResultadosCliente([]);
}
function buscarClientesTarea(q, limite, demora)
{
	if(timerBuscarCli) { clearTimeout(timerBuscarCli); }
	var solicitud = ++solicitudClientesTarea;
	timerBuscarCli = setTimeout(function()
	{
		var xhr = new XMLHttpRequest();
		xhr.open('GET', urlBuscarClientes + '?q=' + encodeURIComponent(q) + '&limit=' + limite, true);
		xhr.onreadystatechange = function()
		{
			if(xhr.readyState !== 4 || solicitud !== solicitudClientesTarea) { return; }
			try
			{
				var data = JSON.parse(xhr.responseText || '{}');
				mostrarResultadosCliente(data.lista || []);
			}
			catch(e) { mostrarResultadosCliente([]); }
		};
		xhr.send();
	}, demora);
}
function filtrarClientesTarea()
{
	var bus = document.getElementById('buscar_cliente');
	var q = bus ? bus.value.replace(/^\s+|\s+$/g, '') : '';
	if(q.length===0) { buscarClientesTarea('',10,180); return; }
	if(q.length<2) { solicitudClientesTarea++; if(timerBuscarCli) { clearTimeout(timerBuscarCli); } mostrarResultadosCliente([]); return; }
	buscarClientesTarea(q,15,280);
}
function quitarClienteTarea()
{
	solicitudClientesTarea++;
	if(timerBuscarCli) { clearTimeout(timerBuscarCli); }
	var id = document.getElementById('cliente_id');
	var nom = document.getElementById('cliente_nombre');
	if(id) { id.value = ''; }
	if(nom) { nom.value = ''; }
	if(nom) { nom.style.borderColor = ''; nom.style.background = ''; }
	var limpiar = document.getElementById('btn_quitar_cliente');
	if(limpiar) { limpiar.disabled = true; }
	mostrarResultadosCliente([]);
}
var busCli = document.getElementById('buscar_cliente');
if(busCli)
{
	busCli.oninput = filtrarClientesTarea;
	busCli.onfocus = filtrarClientesTarea;
}
var btnQuitarCli = document.getElementById('btn_quitar_cliente');
if(btnQuitarCli)
{
	btnQuitarCli.onclick = quitarClienteTarea;
}

<?php if(!acceso_es_empleado()) { ?>
// Elegir responsable, agregar otro (sin repetir) o quitar si se cargo mal.
var equiposTarea = <?php echo json_encode(acceso_equipos_por_area(), JSON_UNESCAPED_UNICODE); ?>;
var tiposTarea = <?php echo json_encode(tareas_tipos_por_area(), JSON_UNESCAPED_UNICODE); ?>;
function areaActualTarea()
{
	var area = document.getElementById('area_crm');
	if(!area) { return ''; }
	return area.value;
}
function cajaResponsables()
{
	return document.getElementById('caja_responsables');
}
function selectsResponsables()
{
	var caja = cajaResponsables();
	if(!caja) { return []; }
	return caja.getElementsByClassName('sel-responsable');
}
function nombresElegidos(salvo)
{
	var sels = selectsResponsables();
	var out = [];
	for(var i=0; i<sels.length; i++)
	{
		if(sels[i] === salvo) { continue; }
		if(sels[i].value !== '') { out.push(sels[i].value); }
	}
	return out;
}
function llenarOpciones(sel, valor)
{
	var lista = (equiposTarea[areaActualTarea()] || []).slice();
	var usados = nombresElegidos(sel);
	if(valor && valor !== '' && lista.indexOf(valor) === -1)
	{
		lista.push(valor);
	}
	sel.innerHTML = '';
	var vacio = document.createElement('option');
	vacio.value = '';
	vacio.text = 'Elegir responsable';
	sel.appendChild(vacio);
	for(var i=0; i<lista.length; i++)
	{
		if(usados.indexOf(lista[i]) !== -1 && lista[i] !== valor) { continue; }
		var o = document.createElement('option');
		o.value = lista[i];
		o.text = lista[i];
		if(lista[i] === valor) { o.selected = true; }
		sel.appendChild(o);
	}
}
function actualizarOpcionesResponsables()
{
	var sels = selectsResponsables();
	for(var i=0; i<sels.length; i++)
	{
		llenarOpciones(sels[i], sels[i].value);
		sels[i].required = (i === 0);
	}
	var filas = cajaResponsables() ? cajaResponsables().getElementsByClassName('fila-responsable') : [];
	for(var j=0; j<filas.length; j++)
	{
		var btn = filas[j].getElementsByClassName('btn-quitar-resp')[0];
		if(btn) { btn.style.display = (filas.length > 1) ? 'inline-block' : 'none'; }
	}
	var agregar = document.getElementById('btn_agregar_resp');
	if(agregar)
	{
		var lista = equiposTarea[areaActualTarea()] || [];
		agregar.style.display = (nombresElegidos(null).length >= lista.length) ? 'none' : 'inline-block';
	}
}
function htmlFilaResponsable(valor)
{
	var fila = document.createElement('div');
	fila.className = 'fila-responsable';
	fila.style.marginBottom = '6px';
	var sel = document.createElement('select');
	sel.name = 'responsables[]';
	sel.className = 'form-control sel-responsable';
	sel.style.width = '70%';
	sel.style.display = 'inline-block';
	sel.onchange = actualizarOpcionesResponsables;
	llenarOpciones(sel, valor || '');
	var btn = document.createElement('button');
	btn.type = 'button';
	btn.className = 'btn btn-white btn-quitar-resp';
	btn.style.marginLeft = '6px';
	btn.onclick = function(){ quitarSelectResponsable(btn); };
	btn.appendChild(document.createTextNode('Quitar'));
	fila.appendChild(sel);
	fila.appendChild(document.createTextNode(' '));
	fila.appendChild(btn);
	return fila;
}
function agregarSelectResponsable()
{
	var caja = cajaResponsables();
	if(!caja) { return; }
	var lista = equiposTarea[areaActualTarea()] || [];
	if(nombresElegidos(null).length >= lista.length) { return; }
	caja.appendChild(htmlFilaResponsable(''));
	actualizarOpcionesResponsables();
}
function quitarSelectResponsable(btn)
{
	var caja = cajaResponsables();
	if(!caja) { return; }
	var filas = caja.getElementsByClassName('fila-responsable');
	if(filas.length < 2) { return; }
	var fila = btn.parentNode;
	if(fila && fila.parentNode === caja) { caja.removeChild(fila); }
	actualizarOpcionesResponsables();
}
function recargarSelectsPorArea()
{
	var caja = cajaResponsables();
	if(!caja) { return; }
	caja.innerHTML = '';
	caja.appendChild(htmlFilaResponsable(''));
	actualizarOpcionesResponsables();
	var selTipo = document.getElementById('tipo_tarea');
	if(selTipo)
	{
		var tipos = tiposTarea[areaActualTarea()] || [];
		var tipoActual = selTipo.value;
		selTipo.innerHTML = '';
		for(var k=0; k<tipos.length; k++)
		{
			var o = document.createElement('option');
			o.value = tipos[k];
			o.text = tipos[k];
			if(tipos[k] === tipoActual) { o.selected = true; }
			selTipo.appendChild(o);
		}
	}
	var labTipo = document.getElementById('etiqueta_tipo_tarea');
	if(labTipo)
	{
		labTipo.innerHTML = 'Objetivo';
	}
	llenarEjesTarea();
}
var areaSel = document.getElementById('area_crm');
if(areaSel && areaSel.tagName === 'SELECT')
{
	areaSel.onchange = recargarSelectsPorArea;
}
var selsIni = selectsResponsables();
for(var s=0; s<selsIni.length; s++)
{
	selsIni[s].onchange = actualizarOpcionesResponsables;
}
actualizarOpcionesResponsables();
<?php } ?>
var ejesPorArea = <?php echo json_encode(function_exists('crm_ejes_por_area_objetivo') ? crm_ejes_por_area_objetivo() : array(), JSON_UNESCAPED_UNICODE); ?>;
function areaParaEje()
{
	var area = document.getElementById('area_crm');
	if(area && area.value) { return area.value; }
	return <?php echo json_encode(acceso_area_pantalla(), JSON_UNESCAPED_UNICODE); ?>;
}
function llenarEjesTarea()
{
	var selTipo = document.getElementById('tipo_tarea');
	var selEje = document.getElementById('eje_tarea');
	if(!selEje) { return; }
	var actual = selEje.value;
	var obj = selTipo ? selTipo.value : '';
	var lista = [];
	if(ejesPorArea[areaParaEje()] && ejesPorArea[areaParaEje()][obj])
	{
		lista = ejesPorArea[areaParaEje()][obj];
	}
	selEje.innerHTML = '';
	var vacio = document.createElement('option');
	vacio.value = '';
	vacio.text = 'Sin eje';
	selEje.appendChild(vacio);
	for(var i=0; i<lista.length; i++)
	{
		var o = document.createElement('option');
		o.value = lista[i];
		o.text = lista[i];
		if(lista[i] === actual) { o.selected = true; }
		selEje.appendChild(o);
	}
}
var selTipoEje = document.getElementById('tipo_tarea');
if(selTipoEje)
{
	var viejoTipo = selTipoEje.onchange;
	selTipoEje.onchange = function()
	{
		if(typeof viejoTipo === 'function') { viejoTipo(); }
		llenarEjesTarea();
	};
}
llenarEjesTarea();
(function(){
	var fi=document.getElementById('fecha_inicio');
	var fc=document.getElementById('fecha_cierre');
	if(!fi || !fc) { return; }
	var hoy='<?php echo date('Y-m-d'); ?>';
	var baseMin=fc.getAttribute('min') || hoy;
	function syncMin()
	{
		var m = baseMin;
		if(hoy > m) { m = hoy; }
		if(fi.value && fi.value > m) { m = fi.value; }
		fc.min = m;
		if(fi.value)
		{
			if(fi.value < hoy && (!fi.defaultValue || fi.value !== fi.defaultValue))
			{
				fi.setCustomValidity('La fecha de inicio no puede ser anterior a la fecha actual.');
			}
			else
			{
				fi.setCustomValidity('');
			}
		}
		else
		{
			fi.setCustomValidity('');
		}

		if(fc.value)
		{
			if(fc.value < hoy && (!fc.defaultValue || fc.value !== fc.defaultValue))
			{
				fc.setCustomValidity('La fecha estimada no puede ser anterior a la fecha actual.');
			}
			else if(fi.value && fc.value < fi.value)
			{
				fc.setCustomValidity('La fecha estimada no puede ser anterior a la fecha de inicio.');
			}
			else
			{
				fc.setCustomValidity('');
			}
		}
		else
		{
			fc.setCustomValidity('');
		}
	}
	fi.addEventListener('change', syncMin);
	fi.addEventListener('input', syncMin);
	fc.addEventListener('change', syncMin);
	fc.addEventListener('input', syncMin);
	syncMin();
})();
</script>
