<?php
// Listado de clientes del area que esta abierta.
// Cada area ve los que tiene asignados y puede entrar a cambiarles datos.
$area_cli=acceso_area_pantalla();
if(!acceso_puede_clientes_de_area($area_cli))
{
	header('Location: '.acceso_destino_login());
	exit();
}
$clientes_solo_lectura=crm_cliente_area_solo_lectura($area_cli);
clientes_asegurar_columnas();
$menu_activo='clientes';
$etq=acceso_etiquetas_area();
$nombre_area=isset($etq[$area_cli]) ? $etq[$area_cli] : $area_cli;
$nombres_cli=recepcion_areas_cliente();
$bus=crm_clientes_buscar(array('area'=>$area_cli));
$es_padron_tipo=($area_cli==='emprendedores' || $area_cli==='empresas');
$txt_ayuda=$es_padron_tipo
	? ($area_cli==='emprendedores'
		? 'Padrón de emprendedores y clientes vinculados al área.'
		: 'Padrón de empresas y clientes vinculados al área.')
	: 'Clientes vinculados a esta área (atención, tarea o asignación).';
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | <?php echo texto_mostrar($nombre_area); ?> - Clientes</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<style>
body.crm-clientes-page .page-title.crm-compact-heading {
	margin-bottom: 6px !important;
}
body.crm-clientes-page .crm-clientes-subtitle {
	margin: 0 0 9px;
}
body.crm-clientes-page form.crm-busqueda {
	margin-bottom: 10px !important;
}
body.crm-clientes-page .crm-clientes-list-row {
	margin-top: 0;
}
body.crm-clientes-page .crm-clientes-list-grid {
	margin-bottom: 0;
}
body.crm-clientes-page .crm-clientes-list-grid > .grid-title {
	padding-top: 8px;
	padding-bottom: 8px;
}
body.crm-clientes-page .crm-clientes-list-grid > .grid-body > p.muted {
	margin-top: 0;
	margin-bottom: 6px;
}
body.crm-clientes-page .crm-clientes-list-grid > .grid-body > p:not(.muted) {
	margin-top: 4px;
	margin-bottom: 0;
}
body.crm-clientes-page .crm-client-row-actions {
	display: flex;
	flex-wrap: nowrap;
	align-items: center;
	gap: 7px;
	white-space: nowrap;
}
body.crm-clientes-page .crm-client-row-actions .btn {
	flex: 0 0 auto;
	margin: 0;
}
body.crm-clientes-page .crm-client-row-action-cell {
	min-width: 190px;
	white-space: nowrap;
}
@media (max-width: 767px) {
	body.crm-clientes-page .crm-client-row-actions {
		flex-wrap: wrap;
		white-space: normal;
	}
	body.crm-clientes-page .crm-client-row-action-cell {
		min-width: 0;
	}
	body.crm-clientes-page .crm-clientes-subtitle {
		margin-bottom: 8px;
	}
	body.crm-clientes-page form.crm-busqueda .form-group {
		margin-bottom: 7px;
	}
	body.crm-clientes-page .crm-clientes-list-grid > .grid-body > p:not(.muted) {
		margin-bottom: 5px;
	}
}
</style>
</head>
<body class="crm-clientes-page">
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
                    <li><a href="<?php echo tareas_link('tareas.php'); ?>"><?php echo texto_mostrar($nombre_area); ?></a></li>
                    <li><a href="#" class="active">Clientes</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <?php if(isset($_SESSION['msg_cliente_exito']) && $_SESSION['msg_cliente_exito']!='') { ?>
                <div class="alert alert-success">
                    <button class="close" data-dismiss="alert"></button>
                    <i class="fa fa-check"></i> <?php echo texto_mostrar($_SESSION['msg_cliente_exito']); unset($_SESSION['msg_cliente_exito']); ?>
                </div>
                <?php } ?>
                <div class="page-title crm-compact-heading" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <h3 style="margin:0;">Clientes</h3>
                    <?php if(!$clientes_solo_lectura && function_exists('acceso_puede_alta_cliente') && acceso_puede_alta_cliente($area_cli)) { ?>
                    <a href="<?php echo url_crm($area_cli.'/alta-cliente.php'); ?>" class="btn btn-primary btn-cons" style="margin-bottom:0;"><i class="fa fa-plus"></i> Nuevo cliente</a>
                    <?php } ?>
                </div>
                <p class="crm-clientes-subtitle"><?php echo texto_mostrar($txt_ayuda); ?> Buscá por nombre, DNI, CUIL/CUIT o localidad.</p>
                <?php echo crm_html_busqueda_clientes(); ?>
                <div class="row crm-clientes-list-row">
                    <div class="col-md-12">
                        <div class="grid simple crm-clientes-list-grid">
                            <div class="grid-title no-border"><h4>Listado</h4></div>
                            <div class="grid-body no-border">
                                <?php echo crm_html_paginacion($bus); ?>
                                <table class="table table-hover no-more-tables">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Nombre completo</th>
                                            <th>Tipo</th>
                                            <th>Correo</th>
                                            <th>CUIL / CUIT</th>
                                            <th>DNI</th>
                                            <th>Áreas</th>
                                            <th>Teléfono</th>
                                            <th>Localidad</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $ret=$bus['lista'];
                                    $cnt=(($bus['pagina']-1)*$bus['limite'])+1;
                                    if(count($ret)==0) { ?>
                                        <tr><td colspan="10">No hay resultados.</td></tr>
                                    <?php } foreach($ret as $row) {
                                    	$tipo_txt=isset($row['tipo_cliente']) ? cliente_tipo_normalizar($row['tipo_cliente']) : '';
                                    ?>
                                        <tr>
                                            <td><?php echo $cnt;?></td>
                                            <td><?php echo texto_mostrar($row['name']);?></td>
                                            <td><?php echo texto_mostrar($tipo_txt);?></td>
                                            <td><?php echo texto_mostrar($row['email']);?></td>
                                            <td><?php echo texto_mostrar($row['cuil_cuit']);?></td>
                                            <td><?php echo texto_mostrar(isset($row['dni']) ? $row['dni'] : '');?></td>
                                            <td><?php echo texto_mostrar(crm_nombres_areas_cliente($row['id'])); ?></td>
                                            <td><?php echo texto_mostrar($row['mobile']);?></td>
                                            <td><?php echo texto_mostrar($row['localidad']);?></td>
                                            <td class="crm-client-row-action-cell">
                                                <div class="crm-client-row-actions" role="group" aria-label="Acciones del cliente">
                                                    <a href="editar-cliente.php?id=<?php echo intval($row['id']);?>&amp;vista=ficha" class="btn btn-white btn-xs btn-mini">Ver ficha</a>
                                                    <?php if(!$clientes_solo_lectura) { ?><a href="editar-cliente.php?id=<?php echo intval($row['id']);?>" class="btn btn-primary btn-xs btn-mini">Editar cliente</a><?php } ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php $cnt++; } ?>
                                    </tbody>
                                </table>
                                <?php echo crm_html_paginacion($bus); ?>
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
</body>
</html>
