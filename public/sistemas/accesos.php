<?php
require_once __DIR__.'/../_init.php';
crm_proteger_staff('sistemas');
acceso_exigir_gestion('sistemas');
$menu_activo='accesos';
$csrf_modulo_token=function_exists('crm_tarea_extra_csrf_token') ? crm_tarea_extra_csrf_token() : '';
if($_SERVER['REQUEST_METHOD']==='POST')
{
	$csrf_post=isset($_POST['csrf_tarea_extra']) && is_string($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : '';
	if(!function_exists('crm_tarea_extra_csrf_valido') || !crm_tarea_extra_csrf_valido($csrf_post))
	{
		http_response_code(403);
		exit('La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.');
	}
}

if(isset($_POST['cambiar_usuario']))
{
	$msg=crm_cambiar_nombre_usuario(isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0, isset($_POST['nuevo_usuario']) ? $_POST['nuevo_usuario'] : '');
	$_SESSION['msgsis']=($msg=='') ? 'Nombre de usuario actualizado.' : $msg;
	echo "<script>window.location.href='accesos.php'</script>";
	exit();
}
if(isset($_POST['marcar_coord']))
{
	$msg=crm_marcar_coord_general(isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0);
	$_SESSION['msgsis']=($msg=='') ? 'Coordinador general actualizado.' : $msg;
	echo "<script>window.location.href='accesos.php'</script>";
	exit();
}
if(isset($_POST['reset_empleado']))
{
	$msg=crm_reset_clave_empleado(isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0);
	$_SESSION['msgsis']=($msg=='') ? 'Clave restablecida al DNI. El usuario la cambia en el primer ingreso.' : $msg;
	echo "<script>window.location.href='accesos.php'</script>";
	exit();
}
if(isset($_POST['reset_cliente']))
{
	$msg=crm_reset_clave_cliente(isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : 0);
	$_SESSION['msgsis']=($msg=='') ? 'Clave del cliente restablecida al DNI.' : $msg;
	echo "<script>window.location.href='accesos.php'</script>";
	exit();
}
if(isset($_POST['clave_super']))
{
	$msg=crm_cambiar_clave_super(isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0, isset($_POST['nueva_clave']) ? $_POST['nueva_clave'] : '');
	$_SESSION['msgsis']=($msg=='') ? 'Clave del superadmin actualizada.' : $msg;
	echo "<script>window.location.href='accesos.php'</script>";
	exit();
}
if(isset($_POST['toggle_activo']))
{
	$id=isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0;
	$act=isset($_POST['activo']) ? intval($_POST['activo']) : 0;
	$id_sesion=crm_id_empleado_sesion();
	if($id>0 && $id===$id_sesion)
	{
		$_SESSION['msgsis']='No podés cambiar el estado de tu propia cuenta.';
	}
	else
	{
		$msg=crm_set_empleado_activo($id, $act ? 0 : 1);
		$_SESSION['msgsis']=($msg==='') ? 'Acceso actualizado.' : $msg;
	}
	echo "<script>window.location.href='accesos.php'</script>";
	exit();
}

$coord=crm_coord_general_actual();
$resto=array();
foreach(crm_personal_sin_super() as $p)
{
	$resto[]=array(
		'id'=>intval($p['id_empleado']),
		'nombre'=>trim($p['nombres'].' '.$p['apellido']),
		'usuario'=>$p['nombre_usuario'],
		'area'=>isset($p['area_nombre']) ? $p['area_nombre'] : $p['area_clave'],
		'rol'=>isset($p['rol_clave']) ? $p['rol_clave'] : '',
		'activo'=>intval($p['activo'])
	);
}
$supers=array();
foreach(acceso_consulta("SELECT e.id_empleado, e.nombres, e.apellido, e.nombre_usuario FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol WHERE r.clave='superadmin'") as $p)
{
	$supers[]=array(
		'id'=>intval($p['id_empleado']),
		'nombre'=>trim($p['nombres'].' '.$p['apellido']),
		'usuario'=>$p['nombre_usuario']
	);
}
$clientes=acceso_consulta("SELECT id_usuario, nombre, correo, dni, primer_acceso, activo FROM usuario_cliente ORDER BY nombre");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | Sistemas - Accesos</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<style>
/* Spacing adjustments are intentionally limited to the Accesos page. */
body.crm-accesos-page .content > .page-title {
	margin-bottom: 6px;
}
body.crm-accesos-page .content > .crm-accesos-description {
	margin: 0 0 12px;
	line-height: 1.45;
}
body.crm-accesos-page .content > .grid.simple {
	margin-bottom: 14px;
}
body.crm-accesos-page .content > .grid.simple:last-of-type {
	margin-bottom: 0;
}
.crm-access-search { position:relative; max-width:600px; margin:0 0 12px; }
.crm-access-search label { display:block; margin-bottom:5px; }
.crm-access-search-control { display:flex; align-items:center; gap:8px; }
.crm-access-search-control input { flex:1 1 auto; min-width:0; }
.crm-access-search-control .btn { flex:0 0 auto; }
.crm-access-search-status { margin:5px 0 0; font-size:12px; }
.crm-access-suggestions { display:none; position:absolute; z-index:1050; top:100%; left:0; right:0; max-height:280px; overflow:auto; background:#fff; border:1px solid #cbd5e1; border-radius:4px; box-shadow:0 5px 14px rgba(15,23,42,.16); }
.crm-access-suggestion { display:block; width:100%; padding:9px 12px; border:0; border-bottom:1px solid #edf0f4; background:#fff; text-align:left; }
.crm-access-suggestion:last-child { border-bottom:0; }
.crm-access-suggestion:hover, .crm-access-suggestion:focus { background:#f4f7fb; }
.crm-access-suggestion small { display:block; color:#6b7280; }
.crm-access-row-found { background:#fff7dc !important; outline:2px solid #f0ad4e; }
@media (max-width: 767px) {
	body.crm-accesos-page .content > .crm-accesos-description {
		margin-bottom: 10px;
	}
	body.crm-accesos-page .content > .grid.simple {
		margin-bottom: 10px;
	}
}
</style>
</head>
<body class="crm-accesos-page">
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
                    <li><a href="#" class="active">Sistemas / Accesos</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title crm-compact-heading"><h3>Accesos</h3></div>
                <p class="crm-accesos-description">Para restablecer una clave, buscá el empleado o cliente en su sección y usá <strong>Restablecer clave</strong> en la fila. La clave vuelve al DNI y se solicita cambiarla en el siguiente ingreso.</p>
                <?php if(isset($_SESSION['msgsis']) && $_SESSION['msgsis']!='') { ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['msgsis']); $_SESSION['msgsis']=''; ?></div>
                <?php } ?>

                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Superadmin</h4></div>
                    <div class="grid-body no-border">
                        <?php foreach($supers as $persona) { ?>
                        <p><?php echo htmlspecialchars($persona['nombre']); ?> · <?php echo htmlspecialchars($persona['usuario']); ?></p>
                        <form method="post" action="" class="form-inline">
                            <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_modulo_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="id_empleado" value="<?php echo intval($persona['id']); ?>">
                            <input type="text" name="nueva_clave" class="form-control" placeholder="Nueva clave (mínimo 6)" style="width:220px; display:inline-block;">
                            <button type="submit" name="clave_super" value="1" class="btn btn-primary">Cambiar clave</button>
                        </form>
                        <?php } ?>
                    </div>
                </div>

                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Coordinador general</h4></div>
                    <div class="grid-body no-border">
                        <p>Actual: <?php echo $coord ? htmlspecialchars(trim($coord['nombres'].' '.$coord['apellido']).' ('.$coord['nombre_usuario'].')') : 'ninguno'; ?></p>
                        <?php if(count($resto)==0) { ?>
                        <p>No hay personal cargado. Primero dales de alta en RRHH.</p>
                        <?php } else { ?>
                        <form method="post" action="">
                            <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_modulo_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <select name="id_empleado" class="form-control" style="max-width:360px;" required>
                                <option value="">Seleccionar colaborador</option>
                                <?php foreach($resto as $persona) {
                                	$es_actual=($coord && intval($coord['id_empleado'])==intval($persona['id']));
                                ?>
                                <option value="<?php echo intval($persona['id']); ?>" <?php if($es_actual) echo 'selected'; ?>><?php echo htmlspecialchars($persona['nombre'].' — '.$persona['area'].($es_actual ? ' (actual)' : '')); ?></option>
                                <?php } ?>
                            </select>
                            <p style="margin-top:8px;"><button type="submit" name="marcar_coord" value="1" class="btn btn-primary">Asignar coordinador general</button></p>
                        </form>
                        <?php } ?>
                    </div>
                </div>

                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Personal</h4></div>
                    <div class="grid-body no-border">
                        <div class="crm-access-search">
                            <label for="buscar-personal">Buscar empleado</label>
                            <div class="crm-access-search-control">
                                <input type="search" id="buscar-personal" class="form-control" autocomplete="off" placeholder="Nombre, apellido, usuario o DNI">
                                <button type="button" id="limpiar-buscar-personal" class="btn btn-white" aria-label="Limpiar búsqueda de empleados">Limpiar</button>
                            </div>
                            <div id="sugerencias-personal" class="crm-access-suggestions" role="listbox" aria-label="Coincidencias de empleados"></div>
                            <p class="text-muted crm-access-search-status" id="estado-busqueda-personal" aria-live="polite">Escribí al menos 2 caracteres y seleccioná una coincidencia para ubicarla; luego usá Restablecer clave en su fila.</p>
                        </div>
                        <table class="table table-hover" id="tabla-personal">
                            <thead><tr><th>Nombre</th><th>Usuario</th><th>Área</th><th>Rol</th><th>Activo</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach($resto as $persona) { ?>
                                <tr data-access-id="<?php echo intval($persona['id']); ?>">
                                    <td><?php echo htmlspecialchars($persona['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($persona['usuario']); ?></td>
                                    <td><?php echo htmlspecialchars($persona['area']); ?></td>
                                    <td><?php echo htmlspecialchars(acceso_mostrar_rol($persona['rol'])); ?></td>
                                    <td><?php echo $persona['activo'] ? 'Sí' : 'No'; ?></td>
                                    <td>
                                        <form method="post" action="" style="display:inline-block; margin-bottom:4px;">
                                            <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_modulo_token, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id_empleado" value="<?php echo intval($persona['id']); ?>">
                                            <input type="text" name="nuevo_usuario" value="<?php echo htmlspecialchars($persona['usuario']); ?>" class="form-control input-sm" style="width:130px; display:inline-block;" required>
                                            <button type="submit" name="cambiar_usuario" value="1" class="btn btn-white btn-mini">Cambiar usuario</button>
                                        </form>
                                        <form method="post" action="" style="display:inline;">
                                            <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_modulo_token, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id_empleado" value="<?php echo intval($persona['id']); ?>">
                                            <button type="submit" name="reset_empleado" value="1" class="btn btn-white btn-mini">Restablecer clave</button>
                                        </form>
                                        <form method="post" action="" style="display:inline;">
                                            <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_modulo_token, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id_empleado" value="<?php echo intval($persona['id']); ?>">
                                            <input type="hidden" name="activo" value="<?php echo intval($persona['activo']); ?>">
                                            <button type="submit" name="toggle_activo" value="1" class="btn btn-white btn-mini"><?php echo $persona['activo'] ? 'Desactivar' : 'Activar'; ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Clientes (ingreso web)</h4></div>
                    <div class="grid-body no-border">
                        <div class="crm-access-search">
                            <label for="buscar-cliente-acceso">Buscar cliente</label>
                            <div class="crm-access-search-control">
                                <input type="search" id="buscar-cliente-acceso" class="form-control" autocomplete="off" placeholder="Nombre, correo o documento">
                                <button type="button" id="limpiar-buscar-cliente-acceso" class="btn btn-white" aria-label="Limpiar búsqueda de clientes">Limpiar</button>
                            </div>
                            <div id="sugerencias-cliente-acceso" class="crm-access-suggestions" role="listbox" aria-label="Coincidencias de clientes"></div>
                            <p class="text-muted crm-access-search-status" id="estado-busqueda-cliente-acceso" aria-live="polite">Escribí al menos 2 caracteres y seleccioná una coincidencia para ubicarla; luego usá Restablecer clave en su fila.</p>
                        </div>
                        <table class="table table-hover" id="tabla-clientes-acceso">
                            <thead><tr><th>Nombre</th><th>Correo</th><th>Primer acceso</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach($clientes as $u) { ?>
                                <tr data-access-id="<?php echo intval($u['id_usuario']); ?>">
                                    <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($u['correo']); ?></td>
                                    <td><?php echo intval($u['primer_acceso']) ? 'Sí' : 'No'; ?></td>
                                    <td>
                                        <form method="post" action="">
                                            <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_modulo_token, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id_usuario" value="<?php echo intval($u['id_usuario']); ?>">
                                            <button type="submit" name="reset_cliente" value="1" class="btn btn-white btn-xs">Restablecer clave</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
 </div>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
 <script>
 (function(){
   function normalize(value) {
     value = String(value || '').toLowerCase();
     return value.normalize ? value.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : value;
   }
   function bindSearch(config) {
     var input = document.getElementById(config.input);
     var clearButton = document.getElementById(config.clear);
     var box = document.getElementById(config.box);
     var table = document.getElementById(config.table);
     var status = document.getElementById(config.status);
     if(!input || !box || !table) return;
     var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-access-id]'));
     var timer = null;
     var sequence = 0;
     function closeSuggestions(){ box.style.display = 'none'; box.innerHTML = ''; }
     function filterRows(q, id) {
       var visible = 0;
       rows.forEach(function(row){
         var match = id ? row.getAttribute('data-access-id') === String(id) : (!q || normalize(row.textContent).indexOf(normalize(q)) !== -1);
         row.style.display = match ? '' : 'none';
         if(match) visible++;
       });
       if(status) status.textContent = q || id ? (visible ? visible + ' registro(s) visible(s).' : 'No se encontraron registros en la tabla.') : 'Escribí al menos 2 caracteres para buscar.';
       return visible;
     }
     function renderSuggestions(items, q, requestId) {
       if(requestId !== sequence) return;
       box.innerHTML = '';
       if(!items.length) {
         var empty = document.createElement('div');
         empty.className = 'crm-access-suggestion';
         empty.textContent = 'Sin coincidencias';
         box.appendChild(empty);
       } else {
         items.forEach(function(item){
           var button = document.createElement('button');
           button.type = 'button';
           button.className = 'crm-access-suggestion';
           button.setAttribute('role', 'option');
           var title = document.createElement('strong');
           title.textContent = item.nombre || '';
           button.appendChild(title);
           var details = document.createElement('small');
           details.textContent = config.details(item);
           button.appendChild(details);
           button.addEventListener('click', function(){
             input.value = item.nombre || '';
             filterRows('', item.id);
             closeSuggestions();
             var row = table.querySelector('tbody tr[data-access-id="' + String(item.id).replace(/[^0-9]/g, '') + '"]');
             if(row) {
               if(row.scrollIntoView) { try { row.scrollIntoView({behavior:'smooth', block:'center'}); } catch(e) { row.scrollIntoView(); } }
               row.classList.add('crm-access-row-found');
               window.setTimeout(function(){ row.classList.remove('crm-access-row-found'); }, 2200);
             }
           });
           box.appendChild(button);
         });
       }
       box.style.display = 'block';
     }
     function search(q) {
       q = q.replace(/^\s+|\s+$/g, '');
       sequence++;
       var requestId = sequence;
       if(q.length < 2) { closeSuggestions(); filterRows('', ''); return; }
       filterRows(q, '');
       var xhr = new XMLHttpRequest();
       xhr.open('GET', '../ajax/sistemas-accesos-buscar.php?tipo=' + encodeURIComponent(config.type) + '&q=' + encodeURIComponent(q), true);
       xhr.onreadystatechange = function(){
         if(xhr.readyState !== 4 || requestId !== sequence) return;
         try {
           var data = JSON.parse(xhr.responseText || '{}');
           renderSuggestions(data && data.ok && data.resultados ? data.resultados : [], q, requestId);
         } catch(e) { closeSuggestions(); }
       };
       xhr.send();
     }
     input.addEventListener('input', function(){
       if(timer) window.clearTimeout(timer);
       timer = window.setTimeout(function(){ search(input.value); }, 300);
     });
     input.addEventListener('focus', function(){ if(input.value.replace(/^\s+|\s+$/g, '').length >= 2) search(input.value); });
     document.addEventListener('click', function(event){ if(!input.contains(event.target) && !box.contains(event.target)) closeSuggestions(); });
     input.addEventListener('keydown', function(event){ if(event.key === 'Escape') closeSuggestions(); });
     if(clearButton) {
       clearButton.addEventListener('click', function(){
         if(timer) window.clearTimeout(timer);
         timer = null;
         sequence++;
         input.value = '';
         closeSuggestions();
         filterRows('', '');
         input.focus();
       });
     }
   }
   bindSearch({input:'buscar-personal',clear:'limpiar-buscar-personal',box:'sugerencias-personal',table:'tabla-personal',status:'estado-busqueda-personal',type:'empleados',details:function(item){ return [item.usuario, item.area, item.dni ? 'DNI ' + item.dni : ''].filter(Boolean).join(' · '); }});
   bindSearch({input:'buscar-cliente-acceso',clear:'limpiar-buscar-cliente-acceso',box:'sugerencias-cliente-acceso',table:'tabla-clientes-acceso',status:'estado-busqueda-cliente-acceso',type:'clientes',details:function(item){ return [item.correo, item.dni ? 'DNI ' + item.dni : ''].filter(Boolean).join(' · '); }});
 })();
 </script>
</body>
</html>
