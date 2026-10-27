<?php
// Programas: lista/catálogo y detalle (?id=) por separado.
crm_asegurar_tablas_programa();
if(function_exists('crm_sembrar_programas_emprendedores'))
{
	crm_sembrar_programas_emprendedores();
}
if(!isset($menu_activo)) { $menu_activo='programas'; }
$area_prog=acceso_area_pantalla();
$area_prog=function_exists('acceso_area_activa') ? acceso_area_activa() : $area_prog;
$puede_gestionar=!empty($programas_puede_gestionar);
$etq=acceso_etiquetas_area();
$nombre_area=isset($etq[$area_prog]) ? $etq[$area_prog] : $area_prog;

$acciones_catalogo=array('alta_objetivo','sumar_areas_objetivo','alta_tarea','editar_tarea_modelo','eliminar_tarea_modelo','alta_programa','agregar_items','quitar_item');
foreach($acciones_catalogo as $accion_catalogo)
{
	if(isset($_POST[$accion_catalogo]) && !$puede_gestionar)
	{
		http_response_code(403);
		exit('No tenés permisos para administrar el catálogo de Programas.');
	}
}

if(isset($_POST['alta_objetivo']))
{
	$msg=crm_objetivo_guardar_multi(
		isset($_POST['nombre_objetivo']) ? $_POST['nombre_objetivo'] : '',
		isset($_POST['areas_objetivo']) && is_array($_POST['areas_objetivo']) ? $_POST['areas_objetivo'] : array()
	);
	$_SESSION['msgprog']=($msg=='') ? 'Objetivo guardado.' : $msg;
	header('Location: programas.php');
	exit();
}
if(isset($_POST['sumar_areas_objetivo']))
{
	$msg=crm_objetivo_sumar_areas(
		isset($_POST['nombre_objetivo']) ? $_POST['nombre_objetivo'] : '',
		isset($_POST['areas_objetivo']) && is_array($_POST['areas_objetivo']) ? $_POST['areas_objetivo'] : array()
	);
	$_SESSION['msgprog']=($msg=='') ? 'Áreas sumadas al objetivo.' : $msg;
	header('Location: programas.php');
	exit();
}
if(isset($_POST['alta_tarea']))
{
	$obj=isset($_POST['objetivo_nombre']) ? trim($_POST['objetivo_nombre']) : '';
	$area=isset($_POST['area_tarea']) ? trim($_POST['area_tarea']) : '';
	$titulo=isset($_POST['titulo_tarea']) ? trim($_POST['titulo_tarea']) : '';
	$id_tipo=crm_tipo_id_de_nombre($area, $obj);
	$msg=crm_tarea_modelo_guardar($area, $id_tipo, 0, $titulo);
	$_SESSION['msgprog']=($msg=='') ? 'Tarea guardada.' : $msg;
	header('Location: programas.php');
	exit();
}
if(isset($_POST['editar_tarea_modelo']))
{
	$msg=crm_tarea_modelo_actualizar(
		isset($_POST['id_modelo']) ? intval($_POST['id_modelo']) : 0,
		isset($_POST['titulo_tarea']) ? $_POST['titulo_tarea'] : ''
	);
	$_SESSION['msgprog']=($msg=='') ? 'Tarea actualizada.' : $msg;
	header('Location: programas.php');
	exit();
}
if(isset($_POST['eliminar_tarea_modelo']))
{
	$msg=crm_tarea_modelo_eliminar(isset($_POST['id_modelo']) ? intval($_POST['id_modelo']) : 0);
	$_SESSION['msgprog']=($msg=='') ? 'Tarea eliminada del catálogo.' : $msg;
	header('Location: programas.php');
	exit();
}
if(isset($_POST['alta_programa']))
{
	$checks=isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();
	$msg=crm_programa_crear(
		isset($_POST['nombre']) ? $_POST['nombre'] : '',
		isset($_POST['descripcion']) ? $_POST['descripcion'] : '',
		$checks
	);
	$_SESSION['msgprog']=($msg=='') ? 'Programa creado.' : $msg;
	if($msg=='')
	{
		$nuevo=acceso_consulta_una(
			"SELECT id_programa FROM programa WHERE nombre=? LIMIT 1",
			's',
			array(trim(isset($_POST['nombre']) ? $_POST['nombre'] : ''))
		);
		if($nuevo)
		{
			header('Location: programas.php?id='.intval($nuevo['id_programa']));
			exit();
		}
	}
	header('Location: programas.php');
	exit();
}
if(isset($_POST['agregar_items']))
{
	$id_p=isset($_POST['id_programa']) ? intval($_POST['id_programa']) : 0;
	$checks=isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();
	$msg=crm_programa_guardar_items($id_p, $checks);
	$_SESSION['msgprog']=($msg=='') ? 'Tareas agregadas al programa.' : $msg;
	header('Location: programas.php?id='.$id_p);
	exit();
}
if(isset($_POST['quitar_item']))
{
	$id_p=isset($_POST['id_programa']) ? intval($_POST['id_programa']) : 0;
	$msg=crm_programa_quitar_item($id_p, isset($_POST['id_item']) ? $_POST['id_item'] : 0);
	$_SESSION['msgprog']=($msg=='') ? 'Tarea quitada del programa.' : $msg;
	header('Location: programas.php?id='.$id_p);
	exit();
}
if(isset($_POST['anotar_cliente']))
{
	$id_p=isset($_POST['id_programa']) ? intval($_POST['id_programa']) : 0;
	if(!function_exists('crm_puede_anotar_programa') || !crm_puede_anotar_programa())
	{
		$_SESSION['msgprog']='Solo un coordinador de área puede anotar clientes en un programa.';
		header('Location: programas.php'.($id_p>0 ? '?id='.$id_p : ''));
		exit();
	}
	$msg=crm_programa_aplicar_cliente(
		isset($_POST['id_cliente']) ? $_POST['id_cliente'] : 0,
		$id_p
	);
	$_SESSION['msgprog']=($msg=='') ? 'Cliente anotado. Se generaron las tareas del programa.' : $msg;
	header('Location: programas.php?id='.$id_p);
	exit();
}

$id_ver=isset($_GET['id']) ? intval($_GET['id']) : 0;
$programas=crm_programas_lista();
$prog_actual=null;
if($id_ver>0)
{
	foreach($programas as $p)
	{
		if(intval($p['id_programa'])===$id_ver)
		{
			$prog_actual=$p;
			break;
		}
	}
	if(!$prog_actual)
	{
		$_SESSION['msgprog']='No se encontró ese programa.';
		header('Location: programas.php');
		exit();
	}
}
$items=$id_ver>0 ? crm_programa_items($id_ver) : array();
$items_por_area=crm_agrupar_items_por_area($items);
$objetivos=crm_objetivos_agrupados();
$tareas_por_obj=crm_tareas_modelo_por_objetivo();
$modelos=crm_tareas_modelo_lista();
$modelos_agrupados=crm_agrupar_modelos_por_objetivo_area($modelos);
$areas=crm_areas_programa();
$ya_ids=array();
foreach($items as $it)
{
	$ya_ids[]=strtolower($it['area_clave'].'|'.$it['titulo'].'|'.$it['tipo_nombre']);
}
$titulo_pag=$id_ver>0
	? 'Programa · '.(isset($prog_actual['nombre']) ? $prog_actual['nombre'] : '')
	: 'Programas';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | <?php echo htmlspecialchars($titulo_pag); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<link href="../assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/animate.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/jquery-scrollbar/jquery.scrollbar.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<style type="text/css">
.crm-prog-area { margin:14px 0 8px; padding-bottom:4px; border-bottom:1px solid #eee; font-size:14px; }
.crm-prog-obj { margin:10px 0 4px; color:#666; font-style:italic; }
.crm-prog-item { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:6px 0; border-bottom:1px solid #f3f3f3; }
.crm-prog-item form { margin:0; }
.crm-prog-check { display:block; margin:4px 0 4px 12px; }
.crm-prog-lista a { display:block; padding:10px 0; border-bottom:1px solid #eee; }
.crm-programas-listado > .page-title { margin-top:0; margin-bottom:6px; }
.crm-programas-listado > .page-title h3 { margin-top:0; margin-bottom:0; }
.crm-programas-listado > .crm-programas-descripcion { margin:0 0 12px; line-height:1.45; }
.crm-programas-listado > .grid.simple { margin-top:0; margin-bottom:14px; }
.crm-programas-listado .crm-prog-lista a { padding-top:8px; padding-bottom:8px; }
@media (max-width:767px) {
	.crm-programas-listado > .crm-programas-descripcion { margin-bottom:10px; }
	.crm-programas-listado > .grid.simple { margin-bottom:12px; }
}
</style>
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
                    <li><a href="<?php echo is_file(BASE_PATH.'/public/'.$area_prog.'/tareas.php') ? url_crm($area_prog.'/tareas.php') : url_crm('dashboard.php'); ?>"><?php echo texto_mostrar($nombre_area); ?></a></li>
                    <li><a href="programas.php" class="<?php echo $id_ver>0 ? '' : 'active'; ?>">Programas</a></li>
                    <?php if($id_ver>0) { ?><li><a href="#" class="active"><?php echo htmlspecialchars($prog_actual['nombre']); ?></a></li><?php } ?>
                </ul>
                <?php
                $menu_seccion_programas=BASE_PATH.'/public/'.$area_prog.'/menu-seccion.php';
                if(is_file($menu_seccion_programas)) { include $menu_seccion_programas; }
                ?>
                <?php if(isset($_SESSION['msgprog']) && $_SESSION['msgprog']!='') { ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['msgprog']); $_SESSION['msgprog']=''; ?></div>
                <?php } ?>

<?php if($id_ver>0) {
	$n_items=count($items);
?>
                <div class="page-title">
                    <h3><?php echo htmlspecialchars($prog_actual['nombre']); ?></h3>
                </div>
                <p><a href="programas.php" class="btn btn-white btn-xs">&larr; Volver a programas</a></p>
                <?php if(!empty($prog_actual['descripcion'])) { ?>
                <p class="muted"><?php echo htmlspecialchars($prog_actual['descripcion']); ?></p>
                <?php } ?>

                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Tareas del programa (<?php echo intval($n_items); ?>)</h4></div>
                    <div class="grid-body no-border">
                        <?php if($n_items==0) { ?>
                        <p class="muted">Todavía no tiene tareas. Agregá desde el catálogo abajo.</p>
                        <?php } else {
                        	foreach($items_por_area as $areaNom=>$listaArea) {
                        		$por_obj=array();
                        		foreach($listaArea as $it) {
                        			$obj=isset($it['tipo_nombre']) && $it['tipo_nombre']!='' ? $it['tipo_nombre'] : 'Sin objetivo';
                        			if(!isset($por_obj[$obj])) { $por_obj[$obj]=array(); }
                        			$por_obj[$obj][]=$it;
                        		}
                        ?>
                        <p class="crm-prog-area"><strong><?php echo htmlspecialchars($areaNom); ?></strong></p>
                        <?php foreach($por_obj as $objNom=>$listaObj) { ?>
                        <p class="crm-prog-obj"><?php echo htmlspecialchars($objNom); ?></p>
                        <?php foreach($listaObj as $it) { ?>
                        <div class="crm-prog-item">
                            <span><?php echo htmlspecialchars($it['titulo']); ?></span>
                            <?php if($puede_gestionar) { ?><form method="post" onsubmit="return confirm('¿Quitar esta tarea del programa?');">
                                <input type="hidden" name="id_programa" value="<?php echo intval($id_ver); ?>">
                                <input type="hidden" name="id_item" value="<?php echo intval($it['id']); ?>">
                                <button type="submit" name="quitar_item" value="1" class="btn btn-white btn-xs">Quitar</button>
                            </form><?php } ?>
                        </div>
                        <?php } } } } ?>
                    </div>
                </div>

                <?php if($puede_gestionar) { ?><div class="grid simple">
                    <div class="grid-title no-border"><h4>Agregar tareas al programa</h4></div>
                    <div class="grid-body no-border">
                        <form method="post">
                            <input type="hidden" name="id_programa" value="<?php echo intval($id_ver); ?>">
                            <?php
                            $hubo=false;
                            foreach($modelos_agrupados as $objNom=>$porArea) {
                            	$bloque='';
                            	foreach($porArea as $areaNom=>$lista) {
                            		$lineas='';
                            		foreach($lista as $m) {
                            			$clave=strtolower($m['area_clave'].'|'.$m['titulo'].'|'.$m['tipo_nombre']);
                            			if(in_array($clave, $ya_ids)) { continue; }
                            			$hubo=true;
                            			$lineas.='<label class="crm-prog-check"><input type="checkbox" name="items[]" value="'.intval($m['id_modelo']).'"> '
                            				.htmlspecialchars($m['titulo']).'</label>';
                            		}
                            		if($lineas!=='') {
                            			$bloque.='<p class="crm-prog-area" style="margin-left:0;font-size:13px;">'.htmlspecialchars($areaNom).'</p>'.$lineas;
                            		}
                            	}
                            	if($bloque!=='') {
                            		echo '<p class="crm-prog-obj" style="margin-top:14px;"><strong>'.htmlspecialchars($objNom).'</strong></p>'.$bloque;
                            	}
                            }
                            if(!$hubo) { ?>
                            <p class="muted">No hay más tareas del catálogo para agregar. Cargá nuevas desde la lista de programas.</p>
                            <?php } else { ?>
                            <p style="margin-top:12px;"><button type="submit" name="agregar_items" value="1" class="btn btn-primary">Agregar seleccionadas</button></p>
                            <?php } ?>
                        </form>
                    </div>
                </div><?php } ?>

                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Anotar cliente</h4></div>
                    <div class="grid-body no-border">
                        <?php if(function_exists('crm_puede_anotar_programa') && crm_puede_anotar_programa()) { ?>
                        <p class="muted">Al anotar se generan las <?php echo intval($n_items); ?> tareas del programa para ese cliente.</p>
                        <form method="post" id="form_anotar_cli">
                            <input type="hidden" name="id_programa" value="<?php echo intval($id_ver); ?>">
                            <input type="hidden" name="id_cliente" id="prog_cliente_id" value="" required>
                            <input type="text" id="prog_cliente_nombre" class="form-control" readonly placeholder="Todavía no hay un cliente elegido">
                            <p style="margin-top:8px;">
                                <input type="text" id="prog_buscar_cliente" class="form-control" placeholder="Nombre, DNI, CUIL/CUIT o localidad…" autocomplete="off">
                            </p>
                            <div id="prog_resultados_cliente" style="display:none; border:1px solid #ddd; max-height:180px; overflow:auto; background:#fff; margin-bottom:8px;"></div>
                            <p style="margin-top:8px;"><button type="submit" name="anotar_cliente" value="1" class="btn btn-primary">Anotar y generar tareas</button></p>
                        </form>
                        <?php } else { ?>
                        <p class="muted">Solo un coordinador de área puede anotar clientes en un programa.</p>
                        <?php } ?>
                    </div>
                </div>

<?php } else { ?>
                <div class="crm-programas-listado">
                <div class="page-title"><h3>Programas</h3></div>
                <p class="muted crm-programas-descripcion"><?php echo $puede_gestionar ? 'Catálogo de objetivos y tareas · armá programas · al entrar a uno gestionás sus tareas y anotás clientes.' : 'Consulta de programas y tareas disponibles para las áreas involucradas.'; ?></p>

                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Programas</h4></div>
                    <div class="grid-body no-border crm-prog-lista">
                        <?php if(count($programas)==0) { ?>
                        <p class="muted">No hay programas disponibles.</p>
                        <?php } foreach($programas as $p) {
                        	$n=count(crm_programa_items(intval($p['id_programa'])));
                        ?>
                        <a href="programas.php?id=<?php echo intval($p['id_programa']); ?>">
                            <strong><?php echo htmlspecialchars($p['nombre']); ?></strong>
                            <span class="muted"> · <?php echo intval($n); ?> tarea<?php echo $n==1 ? '' : 's'; ?></span>
                            <?php if(!empty($p['descripcion'])) { ?><br><span class="muted"><?php echo htmlspecialchars($p['descripcion']); ?></span><?php } ?>
                        </a>
                        <?php } ?>
                    </div>
                </div>
                </div>

                <?php if($puede_gestionar) { ?><div class="grid simple">
                    <div class="grid-title no-border"><h4>Catálogo · Objetivos</h4></div>
                    <div class="grid-body no-border">
                        <form method="post">
                            <div class="form-group">
                                <label>Nombre del objetivo</label>
                                <input type="text" name="nombre_objetivo" class="form-control" placeholder="Ej. Acelerar" required>
                            </div>
                            <p><strong>Áreas a las que aplica</strong></p>
                            <?php foreach($areas as $clave=>$titulo) { ?>
                            <label style="display:inline-block; margin:0 14px 8px 0;">
                                <input type="checkbox" name="areas_objetivo[]" value="<?php echo htmlspecialchars($clave); ?>">
                                <?php echo htmlspecialchars($titulo); ?>
                            </label>
                            <?php } ?>
                            <p style="margin-top:8px;"><button type="submit" name="alta_objetivo" value="1" class="btn btn-primary">Guardar objetivo</button></p>
                        </form>
                        <?php if(count($objetivos)>0) { ?>
                        <hr>
                        <?php foreach($objetivos as $ob) {
                        	$ya_areas=isset($ob['areas']) ? array_keys($ob['areas']) : array();
                        	$faltan=array();
                        	foreach($areas as $clave=>$titulo) {
                        		if(!in_array($clave, $ya_areas, true)) { $faltan[$clave]=$titulo; }
                        	}
                        ?>
                        <div style="margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid #eee;">
                            <p style="margin-bottom:6px;"><strong><?php echo htmlspecialchars($ob['nombre']); ?></strong>
                                · <?php echo htmlspecialchars(implode(', ', array_values($ob['areas']))); ?></p>
                            <?php if(count($faltan)>0) { ?>
                            <form method="post" class="form-inline">
                                <input type="hidden" name="nombre_objetivo" value="<?php echo htmlspecialchars($ob['nombre']); ?>">
                                <?php foreach($faltan as $clave=>$titulo) { ?>
                                <label style="display:inline-block; margin:0 10px 6px 0; font-weight:normal;">
                                    <input type="checkbox" name="areas_objetivo[]" value="<?php echo htmlspecialchars($clave); ?>">
                                    <?php echo htmlspecialchars($titulo); ?>
                                </label>
                                <?php } ?>
                                <button type="submit" name="sumar_areas_objetivo" value="1" class="btn btn-white btn-xs">Sumar áreas</button>
                            </form>
                            <?php } else { ?>
                            <p class="muted" style="margin:0;">Ya aplica a todas las áreas del catálogo.</p>
                            <?php } ?>
                        </div>
                        <?php } } ?>
                    </div>
                </div><?php } ?>

                <?php if($puede_gestionar) { ?><div class="grid simple">
                    <div class="grid-title no-border"><h4>Catálogo · Tareas por objetivo y área</h4></div>
                    <div class="grid-body no-border">
                        <?php if(count($objetivos)==0) { ?>
                        <p class="muted">Primero cargá un objetivo.</p>
                        <?php } else { ?>
                        <form method="post">
                            <div class="form-group">
                                <label>Objetivo</label>
                                <select name="objetivo_nombre" id="objetivo_nombre" class="form-control" required>
                                    <option value="">Seleccionar</option>
                                    <?php foreach($objetivos as $ob) { ?>
                                    <option value="<?php echo htmlspecialchars($ob['nombre']); ?>"><?php echo htmlspecialchars($ob['nombre']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Área de la tarea</label>
                                <select name="area_tarea" id="area_tarea" class="form-control" required>
                                    <option value="">Seleccioná el objetivo primero</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Título de la tarea</label>
                                <input type="text" name="titulo_tarea" class="form-control" placeholder="Ej. Plan de marketing" required>
                            </div>
                            <button type="submit" name="alta_tarea" value="1" class="btn btn-primary">Guardar tarea</button>
                        </form>
                        <?php } ?>
                        <?php if(count($modelos_agrupados)>0) { ?>
                        <hr>
                        <?php foreach($modelos_agrupados as $objNom=>$porArea) { ?>
                        <p style="margin-top:12px;"><strong><?php echo htmlspecialchars($objNom); ?></strong></p>
                        <?php foreach($porArea as $areaNom=>$lista) { ?>
                        <p class="crm-prog-area" style="font-size:13px;"><?php echo htmlspecialchars($areaNom); ?></p>
                        <ul>
                            <?php foreach($lista as $m) { ?>
                            <li style="margin-bottom:8px;">
                                <form method="post" style="display:inline-block; width:100%; max-width:520px;">
                                    <input type="hidden" name="id_modelo" value="<?php echo intval($m['id_modelo']); ?>">
                                    <input type="text" name="titulo_tarea" class="form-control" value="<?php echo htmlspecialchars($m['titulo']); ?>" style="display:inline-block; width:70%; max-width:360px;">
                                    <button type="submit" name="editar_tarea_modelo" value="1" class="btn btn-white btn-xs">Guardar</button>
                                    <button type="submit" name="eliminar_tarea_modelo" value="1" class="btn btn-white btn-xs" onclick="return confirm('¿Eliminar esta tarea del catálogo?');">Eliminar</button>
                                </form>
                            </li>
                            <?php } ?>
                        </ul>
                        <?php } } } ?>
                    </div>
                </div><?php } ?>

                <?php if($puede_gestionar) { ?><div class="grid simple">
                    <div class="grid-title no-border"><h4>Nuevo programa</h4></div>
                    <div class="grid-body no-border">
                        <form method="post">
                            <div class="form-group">
                                <label>Nombre</label>
                                <input type="text" name="nombre" class="form-control" placeholder="Ej. Programa 1" required>
                            </div>
                            <div class="form-group">
                                <label>Descripción (opcional)</label>
                                <textarea name="descripcion" class="form-control" rows="2"></textarea>
                            </div>
                            <p><strong>Tareas que incluye</strong></p>
                            <?php if(count($modelos)==0) { ?>
                            <p class="muted">Todavía no hay tareas en el catálogo.</p>
                            <?php } foreach($modelos_agrupados as $objNom=>$porArea) { ?>
                            <p class="crm-prog-obj" style="margin-top:12px;"><strong><?php echo htmlspecialchars($objNom); ?></strong></p>
                            <?php foreach($porArea as $areaNom=>$lista) { ?>
                            <p class="crm-prog-area" style="font-size:13px;"><?php echo htmlspecialchars($areaNom); ?></p>
                            <?php foreach($lista as $m) { ?>
                            <label class="crm-prog-check">
                                <input type="checkbox" name="items[]" value="<?php echo intval($m['id_modelo']); ?>">
                                <?php echo htmlspecialchars($m['titulo']); ?>
                            </label>
                            <?php } } } ?>
                            <p style="margin-top:12px;"><button type="submit" name="alta_programa" value="1" class="btn btn-primary">Crear programa</button></p>
                        </form>
                    </div>
                </div><?php } ?>
<?php } ?>
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
<script type="text/javascript">
<?php if($id_ver<=0) { ?>
var objetivosAreas = <?php
$mapa=array();
foreach($objetivos as $ob)
{
	$mapa[$ob['nombre']]=$ob['areas'];
}
echo json_encode($mapa, JSON_UNESCAPED_UNICODE);
?>;
function llenarAreasTarea()
{
	var obj = document.getElementById('objetivo_nombre');
	var sel = document.getElementById('area_tarea');
	if(!obj || !sel) { return; }
	sel.innerHTML = '';
	var vacio = document.createElement('option');
	vacio.value = '';
	vacio.text = 'Seleccionar área';
	sel.appendChild(vacio);
	var areas = objetivosAreas[obj.value] || {};
	for(var clave in areas)
	{
		if(!areas.hasOwnProperty(clave)) { continue; }
		var o = document.createElement('option');
		o.value = clave;
		o.text = areas[clave];
		sel.appendChild(o);
	}
}
var objSel = document.getElementById('objetivo_nombre');
if(objSel) { objSel.onchange = llenarAreasTarea; }
<?php } else if(function_exists('crm_puede_anotar_programa') && crm_puede_anotar_programa()) { ?>
(function(){
  var url=<?php echo json_encode(url_crm('ajax/clientes-buscar.php'), JSON_UNESCAPED_UNICODE); ?>;
  var t=null;
  var bus=document.getElementById('prog_buscar_cliente');
  var caja=document.getElementById('prog_resultados_cliente');
  function pintar(lista){
    caja.innerHTML='';
    if(!lista||!lista.length){ caja.style.display='none'; return; }
    caja.style.display='block';
    for(var i=0;i<lista.length;i++){
      (function(cli){
        var f=document.createElement('div');
        f.style.padding='8px 10px'; f.style.cursor='pointer'; f.style.borderBottom='1px solid #eee';
        f.appendChild(document.createTextNode((cli.nombre||'')+(cli.cuil_cuit?' · '+cli.cuil_cuit:'')+(cli.localidad?' ('+cli.localidad+')':'')));
        f.onclick=function(){
          document.getElementById('prog_cliente_id').value=cli.id;
          document.getElementById('prog_cliente_nombre').value=cli.nombre||'';
          bus.value=''; caja.style.display='none';
        };
        caja.appendChild(f);
      })(lista[i]);
    }
  }
  if(bus){
    bus.onkeyup=function(){
      var q=bus.value.replace(/^\s+|\s+$/g,'');
      if(t) clearTimeout(t);
      if(q.length<2){ pintar([]); return; }
      t=setTimeout(function(){
        var x=new XMLHttpRequest();
        x.open('GET', url+'?q='+encodeURIComponent(q)+'&limit=15', true);
        x.onreadystatechange=function(){
          if(x.readyState!==4) return;
          try{ pintar((JSON.parse(x.responseText||'{}')).lista||[]); }catch(e){ pintar([]); }
        };
        x.send();
      }, 280);
    };
  }
  var form=document.getElementById('form_anotar_cli');
  if(form){
    form.onsubmit=function(){
      if(!document.getElementById('prog_cliente_id').value){ alert('Seleccioná un cliente.'); return false; }
      return true;
    };
  }
})();
<?php } ?>
</script>
</body>
</html>
