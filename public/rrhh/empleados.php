<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('rrhh');
acceso_exigir_gestion('rrhh');

$menu_activo='empleados';
$etiquetas=acceso_etiquetas_area();
$bus=crm_empleados_buscar();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | Recursos humanos - Colaboradores</title>
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
                    <li><a href="#" class="active">Recursos humanos / Colaboradores</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title">
                    	<h3>Colaboradores</h3>
                    	<a href="alta-usuario.php" class="btn btn-primary">Nuevo colaborador</a>
                </div>
                <p>Buscá por nombre, apellido, DNI, usuario o correo. Filtrá por área y rol.</p>
                <?php if(isset($_SESSION['msgrrhh']) && $_SESSION['msgrrhh']!='') { ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['msgrrhh']); $_SESSION['msgrrhh']=''; ?></div>
                <?php } ?>
                <?php echo crm_html_busqueda_empleados(); ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="grid simple">
                            <div class="grid-title no-border"><h4>Listado</h4></div>
                            <div class="grid-body no-border">
                                <?php echo crm_html_paginacion($bus); ?>
                                <table class="table table-hover no-more-tables">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Usuario</th>
                                            <th>Correo</th>
                                            <th>DNI</th>
                                            <th>Área</th>
                                            <th>Rol</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if(count($bus['lista'])==0) { ?>
                                        <tr><td colspan="7">No hay resultados.</td></tr>
                                    <?php } foreach($bus['lista'] as $persona) { ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($persona['nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($persona['usuario']); ?></td>
                                            <td><?php echo htmlspecialchars(isset($persona['correo']) ? $persona['correo'] : ''); ?></td>
                                            <td><?php echo htmlspecialchars(isset($persona['dni']) ? $persona['dni'] : ''); ?></td>
                                            <td><?php echo isset($etiquetas[$persona['area']]) ? htmlspecialchars($etiquetas[$persona['area']]) : htmlspecialchars($persona['area']); ?></td>
                                            <td><?php echo htmlspecialchars(acceso_mostrar_rol($persona['rol'])); ?></td>
                                            <td>
                                                <a href="editar-empleado.php?usuario=<?php echo urlencode($persona['usuario']); ?>" class="btn btn-primary btn-xs">Modificar</a>
                                            </td>
                                        </tr>
                                    <?php } ?>
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
