<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('recepcion');
if(!acceso_puede_clientes_de_area('recepcion'))
{
	header('Location: '.acceso_destino_area());
	exit();
}
clientes_asegurar_columnas();

$menu_activo='clientes';
$bus=crm_clientes_buscar(array('area'=>''));
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Recepción - Clientes</title>
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
                    <li><a href="#" class="active">Recepción / Clientes</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title crm-compact-heading">
                    <h3>Clientes</h3>
                    <a href="alta-cliente.php" class="btn btn-primary btn-cons">Nuevo cliente</a>
                </div>
                <?php echo crm_html_busqueda_clientes(); ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="grid simple">
                            <div class="grid-title no-border"><h4>Resultados</h4></div>
                            <div class="grid-body no-border">
                                <?php echo crm_html_paginacion($bus); ?>
                                <table class="table table-hover no-more-tables">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Nombre</th>
                                            <th>Tipo</th>
                                            <th>Correo</th>
                                            <th>CUIL / CUIT</th>
                                            <th>DNI</th>
                                            <th>Áreas</th>
                                            <th>Teléfono</th>
                                            <th>Localidad</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $lista_cli=$bus['lista'];
                                    $cnt=(($bus['pagina']-1)*$bus['limite'])+1;
                                    if(count($lista_cli)==0) { ?>
                                        <tr><td colspan="10">No hay resultados.</td></tr>
                                    <?php } foreach($lista_cli as $row) { ?>
                                        <tr>
                                            <td><?php echo $cnt;?></td>
                                            <td><?php echo htmlspecialchars($row['name']);?></td>
                                            <td><?php echo htmlspecialchars(cliente_tipo_etiqueta(isset($row['tipo_cliente']) ? $row['tipo_cliente'] : ''));?></td>
                                            <td><?php echo htmlspecialchars($row['email']);?></td>
                                            <td><?php echo htmlspecialchars($row['cuil_cuit']);?></td>
                                            <td><?php echo htmlspecialchars(isset($row['dni']) ? $row['dni'] : '');?></td>
                                            <td><?php echo htmlspecialchars(crm_nombres_areas_cliente($row['id'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['mobile']);?></td>
                                            <td><?php echo htmlspecialchars($row['localidad']);?></td>
                                            <td style="white-space:nowrap;">
                                                <a href="editar-cliente.php?id=<?php echo intval($row['id']);?>" class="btn btn-white btn-mini">Ficha</a>
                                                <a href="nueva-atencion.php?id=<?php echo intval($row['id']);?>" class="btn btn-white btn-mini">Turno</a>
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
