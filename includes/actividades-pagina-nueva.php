<?php
// Alta de actividad. Coordinacion general puede elegir el area destino.
include_once BASE_PATH.'/includes/actividades.php';
$menu_activo='Actividades';
$id_editar=isset($_GET['id']) ? intval($_GET['id']) : 0;
$ev_edit=null;
if($id_editar>0)
{
	$ev_edit=crm_evento_por_id($id_editar);
	if(!$ev_edit || intval($ev_edit['activo'])!==1 || !actividad_puede_editar($ev_edit))
	{
		header("Location: ".tareas_link('actividades.php'));
		exit();
	}
}
else if(!actividad_puede_crear())
{
	header("Location: ".tareas_link('actividades.php'));
	exit();
}
if(isset($_POST['guardar']))
{
	if($ev_edit)
	{
		$msg=actividades_actualizar_desde_form($id_editar);
		if($msg==='')
		{
			$gen=actividades_generar_tareas_desde_post($id_editar, 'edicion'); // LOG-DIAG
			$msg='Actividad actualizada.';
			$extra=actividades_msg_tareas_generadas($gen);
			if($extra!='') { $msg.=' '.$extra; }
		}

		$_SESSION['msgactividad']=$msg;
		header("Location: ".url_crm($ev_edit['area_clave'].'/actividades.php?id='.$id_editar));
		exit();
	}
	$alta=actividades_guardar_nueva_desde_form();
	$area_alta=is_array($alta) ? $alta['area'] : $alta;
	$id_nuevo=is_array($alta) ? intval($alta['id']) : 0;
	$_SESSION['msgactividad']=is_array($alta) && isset($alta['msg']) ? $alta['msg'] : 'Actividad guardada.';
	if($id_nuevo>0)
	{
		header("Location: ".url_crm($area_alta.'/actividades.php?id='.$id_nuevo));
	}
	else
	{
		header("Location: ".url_crm($area_alta.'/actividades.php'));
	}
	exit();
}
$v=array(
	'titulo'=>$ev_edit ? $ev_edit['titulo'] : '',
	'descripcion'=>$ev_edit ? (string)$ev_edit['descripcion'] : '',
	'tipo'=>$ev_edit ? $ev_edit['tipo'] : 'reunion',
	'fecha'=>$ev_edit ? substr($ev_edit['fecha'], 0, 10) : date('Y-m-d'),
	'organismo'=>$ev_edit ? (string)$ev_edit['organismo'] : '',
	'organismo_id'=>$ev_edit ? intval(isset($ev_edit['id_cliente']) ? $ev_edit['id_cliente'] : 0) : 0,
	'organismo_nombre'=>$ev_edit ? (isset($ev_edit['cliente_nombre']) && trim($ev_edit['cliente_nombre'])!='' ? $ev_edit['cliente_nombre'] : (string)$ev_edit['organismo']) : '',
	'id_localidad'=>$ev_edit ? intval(isset($ev_edit['id_localidad']) ? $ev_edit['id_localidad'] : 0) : 0,
	'responsable'=>$ev_edit ? intval($ev_edit['id_empleado']) : crm_id_empleado_sesion(),
	'auto'=>$ev_edit ? intval($ev_edit['autoinscripcion']) : 0,
	'publico'=>$ev_edit ? $ev_edit['publico'] : 'todos'
);
$area_act=acceso_area_pantalla();
$etq=acceso_etiquetas_area();
$nombre_area=isset($etq[$area_act]) ? $etq[$area_act] : $area_act;
$elige_area=(!$ev_edit && $area_act=='general' && acceso_es_general());
$titulo_pag=$ev_edit ? 'Editar actividad' : 'Nueva actividad';
$areas_alta=acceso_etiquetas_area_con_tareas();
$responsables=actividades_responsables_por_area();
$acceso_global_actividades=acceso_es_super() || acceso_es_coord_general() || acceso_es_general();
if(!$acceso_global_actividades)
{
	$areas_alta=isset($areas_alta[$area_act]) ? array($area_act=>$areas_alta[$area_act]) : array();
	$lista_area=isset($responsables[$area_act]) ? $responsables[$area_act] : array();
	$lista_filtrada=array();
	foreach($lista_area as $responsable_area)
	{
		if(actividad_responsable_autorizado_para_area($area_act,intval($responsable_area['id'])))
		{
			$lista_filtrada[]=$responsable_area;
		}
	}
	$id_responsable_actual=$ev_edit ? intval(isset($ev_edit['id_empleado']) ? $ev_edit['id_empleado'] : 0) : 0;
	$actual_en_lista=false;
	foreach($lista_filtrada as $responsable_area)
	{
		if(intval($responsable_area['id'])===$id_responsable_actual) { $actual_en_lista=true; break; }
	}
	if($id_responsable_actual>0 && !$actual_en_lista)
	{
		$lista_filtrada[]=array('id'=>$id_responsable_actual,'nombre'=>trim((string)(isset($ev_edit['responsable']) ? $ev_edit['responsable'] : 'Responsable actual')));
	}
	$responsables=$area_act!=='' ? array($area_act=>$lista_filtrada) : array();
}
$organismos_catalogo=function_exists('actividades_organismos_catalogo') ? actividades_organismos_catalogo() : array();
$localidades_misiones=acceso_consulta(
	"SELECT l.id_localidad, l.nombre
	FROM localidad l INNER JOIN provincia p ON p.id_provincia=l.id_provincia
	WHERE p.nombre='Misiones'
	ORDER BY l.nombre"
);
$id_yo=crm_id_empleado_sesion();
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | <?php echo texto_mostrar($nombre_area); ?> - <?php echo $titulo_pag; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<meta content="" name="description" />
<meta content="" name="author" />
<link href="../assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/animate.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/jquery-scrollbar/jquery.scrollbar.css" rel="stylesheet" type="text/css"/>
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
                    <li>
                        <p>ESTÁS AQUÍ</p>
                    </li>
                    <li><a href="<?php echo tareas_link('tareas.php'); ?>"><?php echo texto_mostrar($nombre_area); ?></a></li>
                    <li><a href="<?php echo tareas_link('actividades.php'); ?>">Actividades</a></li>
                    <?php if($ev_edit) { ?>
                    <li><a href="<?php echo tareas_link('actividades.php', array('id'=>$id_editar)); ?>"><?php echo texto_mostrar($ev_edit['titulo']); ?></a></li>
                    <?php } ?>
                    <li><a href="#" class="active"><?php echo $titulo_pag; ?></a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title">
                    <h3><?php echo $titulo_pag; ?></h3>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="grid simple">
                        <div class="grid-title no-border">
                            <h4>Datos de la actividad</h4>
                        </div>
                        <div class="grid-body no-border">
                        <form method="post" action="" id="form_actividad">
                            <?php if($elige_area) { ?>
                            <div class="form-group">
                                <label>Área</label>
                                <select name="area_actividad" id="area_actividad" class="form-control">
                                    <?php foreach($areas_alta as $clave=>$titulo) { ?>
                                    <option value="<?php echo texto_mostrar($clave); ?>"><?php echo texto_mostrar($titulo); ?></option>
                                    <?php } ?>
                                </select>
                                <p class="muted">La actividad queda en el área que elijas.</p>
                            </div>
                            <?php } ?>
                            <div class="form-group">
                                <label>Título</label>
                                <input type="text" name="titulo" required class="form-control" value="<?php echo texto_mostrar($v['titulo']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Descripción</label>
                                <textarea name="descripcion" class="form-control" rows="3"><?php echo texto_mostrar($v['descripcion']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Tipo de actividad</label>
                                <select name="tipo_actividad" class="form-control">
                                    <?php foreach(array('reunion'=>'Reunión','capacitacion'=>'Capacitación','ronda'=>'Ronda de negocios','otro'=>'Otro') as $k_t=>$t_t) { ?>
                                    <option value="<?php echo $k_t; ?>" <?php if($v['tipo']===$k_t) echo 'selected'; ?>><?php echo $t_t; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Fecha</label>
                                <?php
                                $min_f_act = $ev_edit ? min(date('Y-m-d'), substr($ev_edit['fecha'], 0, 10)) : date('Y-m-d');
                                ?>
                                <input type="date" name="fecha" min="<?php echo $min_f_act; ?>" value="<?php echo texto_mostrar($v['fecha']); ?>" class="form-control" required>
                                <p class="muted" style="margin-top:4px;">No puede ser anterior a la fecha actual.</p>
                            </div>
                            <div class="form-group">
                                <label>Localidad del evento</label>
                                <select name="id_localidad" class="form-control">
                                    <option value="">Sin localidad seleccionada</option>
                                    <?php foreach($localidades_misiones as $localidad) { ?>
                                    <option value="<?php echo intval($localidad['id_localidad']); ?>" <?php if(intval($v['id_localidad'])===intval($localidad['id_localidad'])) echo 'selected'; ?>>
                                        <?php echo texto_mostrar($localidad['nombre']); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                                <p class="muted" style="margin-top:4px;">Opcional. La localidad se guarda directamente en la actividad; no depende del cliente asociado.</p>
                            </div>
                            <div class="form-group">
                                <label>Organismo / aliado (opcional)</label>
                                <input type="hidden" name="organismo" id="organismo_val" value="<?php echo texto_mostrar($v['organismo_nombre']); ?>">
                                <input type="hidden" name="organismo_id" id="organismo_id" value="<?php echo intval($v['organismo_id']); ?>">
                                <input type="hidden" name="organismo_limpiar" id="organismo_limpiar" value="0">
                                
                                <div id="caja_organismo_actual" style="margin-bottom:8px; padding:8px 12px; background:#f4f5f7; border:1px solid #e2e8f0; border-radius:4px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                                    <div>
                                        <span class="text-muted"><i class="fa fa-institution"></i> Organismo actual:</span>
                                        <strong id="texto_organismo_actual" style="margin-left:6px; font-size:13px; color:#2c3e50;">
                                            <?php echo ($v['organismo_nombre']!=='' ? texto_mostrar($v['organismo_nombre']) : '<span class="text-muted" style="font-weight:normal;">Ninguno (sin organismo)</span>'); ?>
                                        </strong>
                                    </div>
                                    <button type="button" id="btn_quitar_organismo" class="btn btn-white btn-xs btn-mini" style="<?php if($v['organismo_nombre']==='') echo 'display:none;'; ?> color:#d9534f;" title="Quitar organismo"><i class="fa fa-times"></i> Limpiar</button>
                                </div>

                                <div class="row" style="margin-left:-5px; margin-right:-5px;">
                                    <div class="col-sm-5" style="padding-left:5px; padding-right:5px; margin-bottom:6px;">
                                        <input type="text" id="buscar_organismo" class="form-control" placeholder="Buscar en el padrón de clientes..." autocomplete="off">
                                    </div>
                                    <div class="col-sm-5" style="padding-left:5px; padding-right:5px; margin-bottom:6px;">
                                        <select id="select_organismo" class="form-control">
                                            <option value="" data-id="0">-- Clientes tipo organismo / empresa / municipalidad --</option>
                                            <?php foreach($organismos_catalogo as $org) { 
                                                $es_sel = (intval($org['id']) === intval($v['organismo_id']));
                                                $etiq_tipo = isset($org['tipo']) ? ' ('.$org['tipo'].')' : '';
                                            ?>
                                            <option value="<?php echo texto_mostrar($org['nombre']); ?>" data-id="<?php echo intval($org['id']); ?>" <?php if($es_sel) echo 'selected'; ?>>
                                                <?php echo texto_mostrar($org['nombre'].$etiq_tipo); ?>
                                            </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-2" style="padding-left:5px; padding-right:5px; margin-bottom:6px;">
                                        <button type="button" id="btn_agregar_organismo" class="btn btn-primary btn-block"><i class="fa fa-plus"></i> Agregar</button>
                                    </div>
                                </div>
                                <p class="muted" style="margin:2px 0 0; font-size:12px;">Solo clientes del padrón (tipo organismo, municipalidad o empresa). Si no está, dalo de alta como cliente primero. <strong>Limpiar</strong> quita el organismo de la actividad.</p>
                            </div>
                            <div class="form-group">
                                <label>Responsable a cargo</label>
                                <select name="responsable_id" class="form-control">
                                    <?php foreach($responsables as $clave_area=>$lista_resp) { ?>
                                    <optgroup label="<?php echo texto_mostrar(isset($etq[$clave_area]) ? $etq[$clave_area] : $clave_area); ?>">
                                        <?php foreach($lista_resp as $r) { ?>
                                        <option value="<?php echo intval($r['id']); ?>" <?php if(intval($r['id'])==$v['responsable']) echo 'selected'; ?>><?php echo texto_mostrar($r['nombre']); ?></option>
                                        <?php } ?>
                                    </optgroup>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <div class="checkbox check-primary" style="padding-left:0; margin-top:4px;">
                                    <input type="checkbox" name="autoinscripcion" value="1" id="chk_autoinscripcion" <?php if($v['auto']==1) echo 'checked'; ?>>
                                    <label for="chk_autoinscripcion" style="font-weight:600;">Permitir autoinscripción de clientes</label>
                                </div>
                                <p class="muted" style="margin:6px 0 0;">Si está marcado, el cliente ve la actividad en el portal y puede anotarse solo. Si no, solo el personal puede registrar participantes.</p>
                            </div>
                            <div class="form-group" id="bloque_publico">
                                <label>Dirigida a</label>
                                <select name="publico" class="form-control">
                                    <option value="todos">Todos</option>
                                    <option value="emprendedor" <?php if($v['publico']==='emprendedor' || $v['publico']==='persona') echo 'selected'; ?>>Emprendedores</option>
                                    <option value="empresa" <?php if($v['publico']==='empresa') echo 'selected'; ?>>Empresas</option>
                                    <option value="municipalidad" <?php if($v['publico']==='municipalidad') echo 'selected'; ?>>Municipalidades</option>
                                    <option value="organismo" <?php if($v['publico']==='organismo') echo 'selected'; ?>>Organismos</option>
                                </select>
                                <p class="muted">Solo aplica si permitís autoinscripción.</p>
                            </div>
                            <div class="form-group">
                                <button type="button" class="btn btn-white" id="btn_tareas_act"><i class="fa fa-plus"></i> Generar tareas a partir de esta actividad</button>
                                <div id="bloque_tareas_act" style="display:none; margin-top:12px; padding:12px; border:1px solid #e5e7eb; border-radius:6px;">
                                    <p class="muted" style="margin-top:0;">Cada tarea se crea en el área elegida. Elegí responsable para que le llegue el aviso y pueda iniciarla. Si no hay responsable, se avisa a todo el personal de esa área.</p>
                                    <?php if($ev_edit) { $n_tareas_ev=function_exists('crm_evento_tareas') ? count(crm_evento_tareas($id_editar)) : 0; ?>
                                    <p style="margin:0 0 10px; padding:8px 10px; background:#fcf8e3; border:1px solid #faebcc; border-radius:4px; color:#8a6d3b; font-weight:600;">Esta actividad ya tiene <?php echo intval($n_tareas_ev); ?> tarea(s). Al guardar solo se CREAN las tareas de las filas de abajo: si no agregás ninguna fila, no se genera ninguna tarea nueva.</p>
                                    <?php } ?>
                                    <div id="aviso_error_tareas" class="alert alert-error" style="display:none; margin:10px 0;"></div>
                                    <div id="lista_tareas_act"></div>
                                    <button type="button" class="btn btn-white btn-small" id="btn_otra_tarea"><i class="fa fa-plus"></i> Agregar otra tarea</button>
                                </div>
                            </div>
                            <div class="form-actions">
                                <a href="<?php echo tareas_link('actividades.php', $ev_edit ? array('id'=>$id_editar) : array()); ?>" class="btn btn-white">Cancelar</a>
                                <input type="submit" value="<?php echo $ev_edit ? 'Guardar cambios y generar tareas de abajo' : 'Guardar actividad'; ?>" name="guardar" class="btn btn-primary">
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
<script src="../assets/plugins/jquery-ui/jquery-ui-1.10.1.custom.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/plugins/breakpoints.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-unveil/jquery.unveil.min.js" type="text/javascript"></script>
<script src="../assets/plugins/pace/pace.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-scrollbar/jquery.scrollbar.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-block-ui/jqueryblockui.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
<script src="../assets/js/chat.js" type="text/javascript"></script>
<script src="../assets/js/demo.js" type="text/javascript"></script>
<script>
(function(){
	var areasTarea = <?php echo json_encode($areas_alta, JSON_UNESCAPED_UNICODE); ?>;
	var respPorArea = <?php echo json_encode($responsables, JSON_UNESCAPED_UNICODE); ?>;
	var areaDefecto = <?php echo json_encode($area_act); ?>;
	var areasTareaClaves = [];
	for(var kArea in areasTarea) { if(areasTarea.hasOwnProperty(kArea)) { areasTareaClaves.push(kArea); } }
	// el <select> de área solo tiene las claves con tareas: si el default no está,
	// el browser marca la primera opción y nada coincidiría con areaDefecto.
	if(!areasTarea.hasOwnProperty(areaDefecto))
	{
		areaDefecto = areasTareaClaves.length ? areasTareaClaves[0] : '';
	}
	var lista = document.getElementById('lista_tareas_act');
	var bloque = document.getElementById('bloque_tareas_act');
	var btn = document.getElementById('btn_tareas_act');
	function opcionesResp(areaClave, sel, aviso, inpTitulo)
	{
		sel.innerHTML = '';
		var listaR = respPorArea[areaClave] || [];
		if(listaR.length === 0)
		{
			var opSin = document.createElement('option');
			opSin.value = '0';
			opSin.text = 'Sin personal asignado en esta área';
			opSin.selected = true;
			sel.appendChild(opSin);
			sel.disabled = true;
			if(aviso)
			{
				aviso.innerHTML = '<strong>Área sin colaboradores:</strong> Esta área no tiene personal asignado. No se puede crear una tarea para esta área porque nadie podrá recibir la notificación ni gestionarla. Seleccioná otra área.';
				aviso.style.display = 'block';
				aviso.style.background = '#f2dede';
				aviso.style.color = '#a94442';
				aviso.style.borderColor = '#ebccd1';
			}
			if(inpTitulo)
			{
				inpTitulo.style.borderColor = '#d9534f';
			}
		}
		else
		{
			sel.disabled = false;
			var op0 = document.createElement('option');
			op0.value = '0';
			op0.text = 'Sin responsable (asignar después)';
			sel.appendChild(op0);
			for(var i=0; i<listaR.length; i++)
			{
				var op = document.createElement('option');
				op.value = listaR[i].id;
				op.text = listaR[i].nombre;
				sel.appendChild(op);
			}
			if(aviso)
			{
				aviso.style.display = 'none';
			}
			if(inpTitulo)
			{
				inpTitulo.style.borderColor = '';
			}
		}
	}
	function filaTarea()
	{
		var fila = document.createElement('div');
		fila.className = 'row';
		fila.style.marginBottom = '8px';
		var c1 = document.createElement('div'); c1.className = 'col-sm-4';
		var inp = document.createElement('input');
		inp.type = 'text'; inp.name = 'tarea_titulo[]'; inp.className = 'form-control';
		inp.placeholder = 'Qué hay que hacer'; inp.maxLength = 200;
		c1.appendChild(inp);
		var c2 = document.createElement('div'); c2.className = 'col-sm-3';
		var sel = document.createElement('select');
		sel.name = 'tarea_area[]'; sel.className = 'form-control';
		for(var clave in areasTarea)
		{
			if(!areasTarea.hasOwnProperty(clave)) { continue; }
			var op = document.createElement('option');
			op.value = clave; op.text = areasTarea[clave];
			if(clave === areaDefecto) { op.selected = true; }
			sel.appendChild(op);
		}
		c2.appendChild(sel);
		var c3 = document.createElement('div'); c3.className = 'col-sm-4';
		var selR = document.createElement('select');
		selR.name = 'tarea_responsable[]'; selR.className = 'form-control';
		var aviso = document.createElement('p');
		aviso.style.cssText = 'clear:both; display:none; margin:6px 15px 0; padding:8px 12px; font-size:12px; font-weight:600; border-radius:4px; border:1px solid #faebcc;';
		opcionesResp(sel.value, selR, aviso, inp);
		sel.onchange = function(){ opcionesResp(sel.value, selR, aviso, inp); };
		c3.appendChild(selR);
		var c4 = document.createElement('div'); c4.className = 'col-sm-1';
		var quitar = document.createElement('button');
		quitar.type = 'button'; quitar.className = 'btn btn-white'; quitar.title = 'Quitar';
		quitar.innerHTML = '<i class="fa fa-times"></i>';
		quitar.onclick = function(){ lista.removeChild(fila); if(!lista.children.length) { cerrar(); } };
		c4.appendChild(quitar);
		fila.appendChild(c1); fila.appendChild(c2); fila.appendChild(c3); fila.appendChild(c4);
		fila.appendChild(aviso);
		lista.appendChild(fila);
		inp.focus();
	}
	function filasConDatos()
	{
		var n = 0;
		var filas = lista.children;
		for(var i=0; i<filas.length; i++)
		{
			var inp = filas[i].getElementsByTagName('input');
			var tit = inp.length ? inp[0].value.replace(/^\s+|\s+$/g, '') : '';
			var sels = filas[i].getElementsByTagName('select');
			var areaCambiada = sels.length > 0 ? sels[0].value !== areaDefecto : false;
			var respElegido = sels.length > 1 ? sels[1].value !== '0' : false;
			if(tit !== '' || areaCambiada || respElegido) { n++; }
		}
		return n;
	}
	function ocultarBloque()
	{
		bloque.style.display = 'none';
		btn.innerHTML = '<i class="fa fa-plus"></i> Generar tareas a partir de esta actividad';
	}
	function cerrar()
	{
		var n = filasConDatos();
		if(n > 0 && !window.confirm('Hay ' + n + ' fila(s) con datos. ¿Querés borrarlas?'))
		{
			ocultarBloque();
			return;
		}
		lista.innerHTML = '';
		ocultarBloque();
	}
	btn.onclick = function(){
		if(bloque.style.display === 'none')
		{
			bloque.style.display = 'block';
			btn.innerHTML = '<i class="fa fa-times"></i> No generar tareas';
			if(!lista.children.length) { filaTarea(); }
		}
		else { cerrar(); }
	};
	document.getElementById('btn_otra_tarea').onclick = filaTarea;

	var formAct = document.getElementById('form_actividad');
	formAct.onsubmit = function(){
		var avisoErr = document.getElementById('aviso_error_tareas');
		if(avisoErr) { avisoErr.style.display = 'none'; avisoErr.innerHTML = ''; }
		function mostrarErrorTarea(msg, inputTarget)
		{
			if(avisoErr)
			{
				avisoErr.innerHTML = '<strong>Atención:</strong> ' + msg;
				avisoErr.style.display = 'block';
				avisoErr.scrollIntoView({behavior: 'smooth', block: 'nearest'});
			}
			if(inputTarget) { inputTarget.focus(); }
		}
		var inputs = lista.getElementsByTagName('input');
		var filaN = 0;
		var intactas = [];
		for(var i=0; i<inputs.length; i++)
		{
			if(inputs[i].name !== 'tarea_titulo[]') { continue; }
			filaN++;
			var titulo = inputs[i].value.replace(/^\s+|\s+$/g, '');
			var fila = inputs[i].parentNode.parentNode;
			var sels = fila.getElementsByTagName('select');
			var areaSel = sels.length > 0 ? sels[0].value : '';
			var respSel = sels.length > 1 ? sels[1].value : '0';
			var cantResp = (respPorArea[areaSel] || []).length;

			if(titulo === '')
			{
				if(areaSel === areaDefecto && respSel === '0')
				{
					intactas.push(fila);
					continue;
				}
				mostrarErrorTarea('Fila ' + filaN + ' sin título: escribí el título o quitá la fila.', inputs[i]);
				return false;
			}

			if(cantResp === 0)
			{
				var nomArea = areasTarea[areaSel] || areaSel;
				mostrarErrorTarea('Fila ' + filaN + ': El área "' + nomArea + '" no tiene personal asignado. Elegí un área con personal o quitá la fila.', inputs[i]);
				return false;
			}
		}
		for(var j=0; j<intactas.length; j++)
		{
			if(intactas[j].parentNode) { intactas[j].parentNode.removeChild(intactas[j]); }
		}
		if(intactas.length && !lista.children.length) { ocultarBloque(); }
		return true;
	};

	var chk = document.getElementById('chk_autoinscripcion');
	var pub = document.getElementById('bloque_publico');
	function syncPublico(){ pub.style.display = chk.checked ? '' : 'none'; }
	chk.onchange = syncPublico;
	syncPublico();

	var busOrg = document.getElementById('buscar_organismo');
	var selOrg = document.getElementById('select_organismo');
	var btnAddOrg = document.getElementById('btn_agregar_organismo');
	var btnQuitOrg = document.getElementById('btn_quitar_organismo');
	var txtActualOrg = document.getElementById('texto_organismo_actual');
	var inpOrgVal = document.getElementById('organismo_val');
	var inpOrgId = document.getElementById('organismo_id');
	var inpOrgLimpiar = document.getElementById('organismo_limpiar');

	if(busOrg && selOrg && btnAddOrg)
	{
		var opcionesOriginales = [];
		for(var o = 0; o < selOrg.options.length; o++)
		{
			opcionesOriginales.push({
				valor: selOrg.options[o].value,
				texto: selOrg.options[o].text,
				id: selOrg.options[o].getAttribute('data-id') || '0'
			});
		}

		function filtrarSelect(query)
		{
			var q = (query || '').toLowerCase().replace(/^\s+|\s+$/g, '');
			var valorPrevio = selOrg.value;
			selOrg.innerHTML = '';
			for(var i = 0; i < opcionesOriginales.length; i++)
			{
				var item = opcionesOriginales[i];
				if(i === 0 || q === '' || item.texto.toLowerCase().indexOf(q) !== -1)
				{
					var opt = document.createElement('option');
					opt.value = item.valor;
					opt.text = item.texto;
					opt.setAttribute('data-id', item.id);
					if(item.valor === valorPrevio && valorPrevio !== '')
					{
						opt.selected = true;
					}
					selOrg.appendChild(opt);
				}
			}
		}

		busOrg.oninput = function(){
			filtrarSelect(busOrg.value);
		};

		selOrg.onchange = function(){
			if(selOrg.value !== '')
			{
				busOrg.value = selOrg.value;
			}
		};

		btnAddOrg.onclick = function(e){
			e.preventDefault();
			var nombre = '';
			var id = '0';
			if(selOrg.value !== '')
			{
				nombre = selOrg.value;
				var optSel = selOrg.options[selOrg.selectedIndex];
				id = optSel ? (optSel.getAttribute('data-id') || '0') : '0';
			}

			if(nombre === '' || !id || id === '0')
			{
				window.alert('Elegí un cliente del padrón (organismo / empresa / municipalidad). No se admite texto libre.');
				return;
			}

			inpOrgVal.value = nombre;
			inpOrgId.value = id;
			inpOrgLimpiar.value = '0';
			txtActualOrg.textContent = nombre;
			if(btnQuitOrg) { btnQuitOrg.style.display = ''; }

			busOrg.value = '';
			filtrarSelect('');
			selOrg.value = '';
		};

		if(btnQuitOrg)
		{
			btnQuitOrg.onclick = function(e){
				e.preventDefault();
				inpOrgVal.value = '';
				inpOrgId.value = '0';
				inpOrgLimpiar.value = '1';
				txtActualOrg.innerHTML = '<span class="text-muted" style="font-weight:normal;">Ninguno (sin organismo)</span>';
				btnQuitOrg.style.display = 'none';
				busOrg.value = '';
				filtrarSelect('');
				selOrg.value = '';
			};
		}
	}
})();
</script>
</body>
</html>
