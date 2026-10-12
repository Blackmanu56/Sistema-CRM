<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('recepcion');
acceso_exigir_gestion('recepcion');
include("datos-tareas.php");
tareas_iniciar_contexto();

$menu_activo='atenciones';
$id_pre=isset($_GET['id']) ? intval($_GET['id']) : 0;
if($id_pre<=0)
{
	$id_pre=intval(crm_campo('id_cliente', 0));
}
$fecha_def=isset($_GET['fecha']) ? trim($_GET['fecha']) : crm_campo('fecha', date('Y-m-d'));
if($fecha_def=='' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_def))
{
	$fecha_def=date('Y-m-d');
}
$hora_def=crm_campo('hora', '');
$area_def=isset($_GET['area']) ? trim($_GET['area']) : crm_campo('area', '');
$cli_nombre='';
if($id_pre>0)
{
	$cli=cliente_traer($id_pre);
	if($cli)
	{
		$cli_nombre=isset($cli['name']) ? $cli['name'] : (isset($cli['nombre']) ? $cli['nombre'] : '');
	}
	else
	{
		$id_pre=0;
	}
}

if(isset($_POST['abrir']))
{
	$id_cli=isset($_POST['id_cliente']) ? intval($_POST['id_cliente']) : 0;
	$area=isset($_POST['area']) ? trim($_POST['area']) : '';
	$fecha=isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
	$hora=isset($_POST['hora']) ? trim($_POST['hora']) : '';
	if($id_cli<=0)
	{
		$_SESSION['msgrecepcion']='Seleccioná el cliente.';
	}
	else if($area==='')
	{
		$_SESSION['msgrecepcion']='Seleccioná el área de la atención.';
	}
	else if($fecha=='' || $hora=='')
	{
		$_SESSION['msgrecepcion']='Indicá fecha y horario de la atención.';
	}
	else if(!cliente_traer($id_cli))
	{
		$_SESSION['msgrecepcion']='El cliente no está en el listado.';
	}
	else
	{
		$err=crm_turno_validar_alta($area, $fecha, $hora, 0);
		if($err!='')
		{
			$_SESSION['msgrecepcion']=$err;
		}
		else
		{
			$id_at=crm_atencion_abrir(array(
				'id_cliente'=>$id_cli,
				'area'=>$area,
				'motivo'=>isset($_POST['motivo']) ? $_POST['motivo'] : 'consulta',
				'canal'=>isset($_POST['canal']) ? $_POST['canal'] : 'presencial',
				'detalle'=>isset($_POST['detalle']) ? $_POST['detalle'] : '',
				'fecha'=>$fecha,
				'hora'=>$hora
			));
			if($id_at>0)
			{
				$_SESSION['msgatencion']='Atención agendada. Queda a la espera del cliente.';
				echo "<script>window.location.href='atencion.php?id=".$id_at."'</script>";
				exit();
			}
			if(!isset($_SESSION['msgrecepcion']) || $_SESSION['msgrecepcion']=='')
			{
				$_SESSION['msgrecepcion']='No se pudo agendar la atención.';
			}
		}
	}
	$id_pre=$id_cli;
	$fecha_def=$fecha!='' ? $fecha : $fecha_def;
	$hora_def=$hora;
	$area_def=$area;
	$cli=cliente_traer($id_pre);
	$cli_nombre=$cli ? (isset($cli['name']) ? $cli['name'] : (isset($cli['nombre']) ? $cli['nombre'] : '')) : '';
}
$hoy_turnos=date('Y-m-d');
$ahora_turnos=date('H:i');
if($area_def!='' && $fecha_def!='')
{
	$horarios=crm_turno_horarios_disponibles($area_def, $fecha_def);
}
else
{
	$horarios=crm_turno_horarios_fijos();
	if($fecha_def < $hoy_turnos)
	{
		$horarios=array();
	}
	else if($fecha_def===$hoy_turnos)
	{
		$horarios=array_values(array_filter($horarios, function($h) use ($ahora_turnos) {
			return $h > $ahora_turnos;
		}));
	}
}
$url_ajax=url_crm('ajax/clientes-buscar.php');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | Recepción - Agendar atención</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
</head>
<body class="">
<?php crm_header_staff();?>
<div class="page-container row">
      <?php crm_menu_staff();?>
      <div class="clearfix"></div>
    </div>
  </div>
    <div class="page-content">
            <div class="clearfix"></div>
            <div class="content">
                <ul class="breadcrumb">
                    <li><p>ESTÁS AQUÍ</p></li>
                    <li><a href="atenciones.php">Agenda</a></li>
                    <li><a href="#" class="active">Agendar atención</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title"><h3>Agendar atención</h3></div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="grid simple">
                            <div class="grid-title no-border"><h4>Datos de la atención</h4></div>
                            <div class="grid-body no-border">
                                <form name="form1" id="form_turno" method="post" action="" onSubmit="return validAtencion();">
                                <?php if(isset($_SESSION['msgrecepcion']) && $_SESSION['msgrecepcion']!='') { ?>
                                <div class="alert alert-warning"><?php echo htmlspecialchars($_SESSION['msgrecepcion']); $_SESSION['msgrecepcion']=''; ?></div>
                                <?php } ?>
                                <p class="muted">Cupo de atenciones del día por área: <?php echo intval(crm_turno_cupo_diario()); ?> · Horarios cada media hora.</p>

                                <div class="form-group">
                                    <label>Cliente</label>
                                    <input type="hidden" name="id_cliente" id="id_cliente" value="<?php echo intval($id_pre); ?>">
                                    <div class="crm-cli-pick">
                                        <input type="text" id="buscar_cliente" class="form-control" autocomplete="off"
                                            placeholder="Escribí nombre, DNI o CUIL/CUIT…"
                                            value="<?php echo htmlspecialchars($cli_nombre); ?>">
                                        <div id="resultados_cliente" class="crm-cli-suggest" style="display:none;"></div>
                                    </div>
                                    <p class="muted" id="cli_hint"><?php echo $id_pre>0 ? 'Cliente seleccionado. Podés buscar otro escribiendo arriba.' : 'Escribí al menos 2 letras y elegí de la lista.'; ?></p>
                                </div>

                                <div class="form-group">
                                    <label>Área</label>
                                    <select name="area" id="area_turno" class="form-control">
                                        <option value="">Seleccionar</option>
                                        <?php foreach(recepcion_areas_cliente() as $clave=>$titulo) { ?>
                                        <option value="<?php echo htmlspecialchars($clave); ?>" <?php if($area_def==$clave) echo 'selected'; ?>><?php echo htmlspecialchars($titulo); ?></option>
                                        <?php } ?>
                                    </select>
                                    <p class="muted">Al cambiar área o fecha se actualizan los horarios libres.</p>
                                </div>
                                <div class="form-group">
                                    <label>Fecha</label>
                                    <input type="date" name="fecha" id="fecha_turno" class="form-control" value="<?php echo htmlspecialchars($fecha_def); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Horario</label>
                                    <select name="hora" id="hora_turno" class="form-control" required>
                                        <option value="">Seleccionar</option>
                                        <?php foreach($horarios as $h) { ?>
                                        <option value="<?php echo htmlspecialchars($h); ?>" <?php if($hora_def===$h) echo 'selected'; ?>><?php echo htmlspecialchars($h); ?></option>
                                        <?php } ?>
                                    </select>
                                    <?php if($area_def!='' && count($horarios)==0) { ?>
                                    <p class="muted">No quedan horarios libres ese día para el área (cupo o slots ocupados).</p>
                                    <?php } ?>
                                </div>
                                <div class="form-group">
                                    <label>Motivo</label>
                                    <select name="motivo" class="form-control">
                                        <option value="consulta">Consulta</option>
                                        <option value="documentacion">Documentación</option>
                                        <option value="seguimiento">Seguimiento</option>
                                        <option value="tramite">Trámite</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Canal</label>
                                    <input type="hidden" name="canal" value="presencial">
                                    <p class="form-control-static">Presencial <span class="muted">(las atenciones son siempre presenciales)</span></p>
                                </div>
                                <div class="form-group">
                                    <label>Nota (opcional)</label>
                                    <textarea name="detalle" class="form-control" rows="2"><?php echo htmlspecialchars(crm_campo('detalle')); ?></textarea>
                                </div>
                                <div class="form-actions">
                                    <a href="atenciones.php" class="btn btn-white">Cancelar</a>
                                    <button type="submit" name="abrir" value="1" class="btn btn-primary">Guardar atención</button>
                                </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
 </div>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
<script type="text/javascript">
var urlBuscarClientes = <?php echo json_encode($url_ajax, JSON_UNESCAPED_UNICODE); ?>;
var timerBuscarCli = null;
var cliLock = <?php echo $id_pre>0 ? 'true' : 'false'; ?>;

function validAtencion()
{
	if(!document.getElementById('id_cliente').value || document.getElementById('id_cliente').value==="0")
	{
		alert("Seleccioná el cliente de la lista.");
		document.getElementById('buscar_cliente').focus();
		return false;
	}
	if(!document.getElementById('area_turno').value)
	{
		alert("Seleccioná el área de la atención.");
		return false;
	}
	var fVal = document.getElementById('fecha_turno').value;
	var hVal = document.getElementById('hora_turno').value;
	if(!fVal || !hVal)
	{
		alert("Indicá fecha y horario.");
		return false;
	}
	var hoy = new Date();
	var yyyy = hoy.getFullYear();
	var mm = String(hoy.getMonth() + 1).padStart(2, '0');
	var dd = String(hoy.getDate()).padStart(2, '0');
	var hoyStr = yyyy + '-' + mm + '-' + dd;
	if(fVal < hoyStr)
	{
		alert("No se pueden agendar atenciones para fechas pasadas.");
		return false;
	}
	if(fVal === hoyStr)
	{
		var hh = String(hoy.getHours()).padStart(2, '0');
		var min = String(hoy.getMinutes()).padStart(2, '0');
		var ahoraHora = hh + ':' + min;
		if(hVal <= ahoraHora)
		{
			alert("El horario seleccionado (" + hVal + ") ya pasó. Elegí un horario posterior a las " + ahoraHora + ".");
			return false;
		}
	}
	return true;
}

function textoClienteItem(cli)
{
	var t = cli.nombre || '';
	if(cli.cuil_cuit) { t += ' · ' + cli.cuil_cuit; }
	else if(cli.dni) { t += ' · DNI ' + cli.dni; }
	if(cli.localidad) { t += ' · ' + cli.localidad; }
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
			var fila = document.createElement('button');
			fila.type = 'button';
			fila.className = 'crm-cli-suggest-item';
			fila.appendChild(document.createTextNode(textoClienteItem(cli)));
			fila.onclick = function(){ elegirCliente(cli); };
			caja.appendChild(fila);
		})(lista[i]);
	}
}

function elegirCliente(cli)
{
	document.getElementById('id_cliente').value = cli.id;
	document.getElementById('buscar_cliente').value = cli.nombre || '';
	document.getElementById('cli_hint').textContent = 'Cliente seleccionado. Podés buscar otro escribiendo arriba.';
	cliLock = true;
	mostrarResultadosCliente([]);
}

function filtrarClientes()
{
	var bus = document.getElementById('buscar_cliente');
	var q = bus ? bus.value.replace(/^\s+|\s+$/g, '') : '';
	if(cliLock)
	{
		document.getElementById('id_cliente').value = '0';
		cliLock = false;
		document.getElementById('cli_hint').textContent = 'Escribí al menos 2 letras y elegí de la lista.';
	}
	if(timerBuscarCli) { clearTimeout(timerBuscarCli); }
	if(q.length < 2)
	{
		mostrarResultadosCliente([]);
		return;
	}
	timerBuscarCli = setTimeout(function()
	{
		var xhr = new XMLHttpRequest();
		xhr.open('GET', urlBuscarClientes + '?q=' + encodeURIComponent(q) + '&todas=1&limit=20', true);
		xhr.onreadystatechange = function()
		{
			if(xhr.readyState !== 4) { return; }
			try
			{
				var data = JSON.parse(xhr.responseText || '{}');
				mostrarResultadosCliente(data.lista || []);
			}
			catch(e) { mostrarResultadosCliente([]); }
		};
		xhr.send();
	}, 250);
}

function refrescarHorarios()
{
	var id = document.getElementById('id_cliente').value || '0';
	var area = document.getElementById('area_turno').value || '';
	var fecha = document.getElementById('fecha_turno').value || '';
	var url = 'nueva-atencion.php?id=' + encodeURIComponent(id)
		+ '&area=' + encodeURIComponent(area)
		+ '&fecha=' + encodeURIComponent(fecha);
	window.location.href = url;
}

document.getElementById('buscar_cliente').onkeyup = filtrarClientes;
document.getElementById('buscar_cliente').onfocus = function()
{
	if(!cliLock) { filtrarClientes(); }
};
document.getElementById('area_turno').onchange = refrescarHorarios;
document.getElementById('fecha_turno').onchange = refrescarHorarios;
document.addEventListener('click', function(e)
{
	var caja = document.getElementById('resultados_cliente');
	var bus = document.getElementById('buscar_cliente');
	if(!caja || !bus) { return; }
	if(e.target!==bus && !caja.contains(e.target))
	{
		caja.style.display = 'none';
	}
});
</script>
</body>
</html>
