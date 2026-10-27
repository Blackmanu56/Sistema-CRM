<?php
// Listado de atenciones del área (también las ya cerradas/derivadas de cuando el cliente pasó por acá).
$area_cli=acceso_area_pantalla();
if(!acceso_puede_clientes_de_area($area_cli) && !crm_area_ve_todo($area_cli) && !acceso_puede($area_cli))
{
	header('Location: '.acceso_destino_login());
	exit();
}
$menu_activo='atenciones';
$tipo_filtro=isset($_GET['tipo']) ? trim($_GET['tipo']) : 'todas';
if(!in_array($tipo_filtro, array('todas','recibidas','enviadas'), true))
{
	$tipo_filtro='todas';
}
$estado_filtro=isset($_GET['estado']) ? trim($_GET['estado']) : 'todas';
if(!in_array($estado_filtro, array('todas','turnos','en_atencion','cerrada','cancelada'), true))
{
	$estado_filtro='todas';
}
$fecha_filtro=isset($_GET['fecha']) ? trim($_GET['fecha']) : 'todas';
if(!in_array($fecha_filtro, array('todas','hoy','proximos','semana'), true) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_filtro))
{
	$fecha_filtro='todas';
}
$colab_filtro=isset($_GET['colaborador']) ? intval($_GET['colaborador']) : 0;

$id_area_act=acceso_id_por_clave('area', $area_cli);
$colaboradores_area=($id_area_act>0 && function_exists('crm_empleados_de_area_id')) ? crm_empleados_de_area_id($id_area_act) : array();
$mostrar_filtro_hoy=area_capacidad($area_cli, 'puede_atenciones');
$accion_fecha_hoy='';
if($mostrar_filtro_hoy)
{
	if($fecha_filtro==='hoy')
	{
		$accion_fecha_hoy='<a href="atenciones.php" class="btn btn-white btn-xs" style="margin-left: 8px;"><i class="fa fa-list"></i> Ver todas las atenciones</a>';
	}
	else
	{
		$accion_fecha_hoy='<a href="atenciones.php?fecha=hoy" class="btn btn-info btn-xs" style="margin-left: 8px;"><i class="fa fa-calendar"></i> Ver solo atenciones de hoy</a>';
	}
}

$etq=acceso_etiquetas_area();
$nombre_area=isset($etq[$area_cli]) ? $etq[$area_cli] : $area_cli;
$lista=crm_atenciones_listado($area_cli, array(
	'tipo'=>$tipo_filtro,
	'estado'=>$estado_filtro,
	'fecha'=>$fecha_filtro,
	'id_empleado'=>$colab_filtro
));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | <?php echo texto_mostrar($nombre_area); ?> - Atenciones y Derivaciones</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
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
                    <li><p>ESTÁS AQUÍ</p></li>
                    <li><a href="#" class="active"><?php echo texto_mostrar($nombre_area); ?> / Atenciones y Derivaciones</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title">
                    <h3>Atenciones y Derivaciones<?php if($area_cli=='recepcion' && isset($_GET['fecha']) && $_GET['fecha']=='hoy') { echo ' de hoy'; } ?></h3>
                    <?php if($area_cli!=='sistemas' && (acceso_puede_alta_cliente($area_cli) || $area_cli==='recepcion' || $area_cli==='superadmin' || $area_cli==='administracion') && area_capacidad($area_cli, 'recibe_turnos')) { ?>
                    <a href="nueva-atencion.php" class="btn btn-primary btn-cons pull-right"><i class="fa fa-calendar-plus-o"></i> Nuevo turno / atención</a>
                    <?php } ?>
                </div>

                <!-- TABS DE NAVEGACIÓN ENTRE ATENCIONES GENERALES, RECIBIDAS Y ENVIADAS -->
                <div style="margin-bottom:16px;">
                    <ul class="nav nav-pills" style="margin:0;">
                        <li class="<?php echo $tipo_filtro==='todas' ? 'active' : ''; ?>">
                            <a href="atenciones.php"><i class="fa fa-list"></i> Todas las atenciones</a>
                        </li>
                        <li class="<?php echo $tipo_filtro==='recibidas' ? 'active' : ''; ?>">
                            <a href="atenciones.php?tipo=recibidas"><i class="fa fa-inbox"></i> Derivaciones recibidas</a>
                        </li>
                        <li class="<?php echo $tipo_filtro==='enviadas' ? 'active' : ''; ?>">
                            <a href="atenciones.php?tipo=enviadas"><i class="fa fa-share"></i> Derivaciones enviadas</a>
                        </li>
                    </ul>
                </div>

                <!-- BARRA DE FILTROS AVANZADOS (ESTADO, FECHA, COLABORADOR) -->
                <form method="get" action="atenciones.php" class="form-inline" style="margin-bottom:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                    <?php if($tipo_filtro!=='todas') { ?>
                    <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo_filtro); ?>">
                    <?php } ?>
                    <div>
                        <label style="font-size:12px; margin-right:4px; font-weight:600;">Estado:</label>
                        <select name="estado" class="form-control input-sm" onchange="this.form.submit()">
                            <option value="todas" <?php if($estado_filtro==='todas') echo 'selected'; ?>>Todos los estados</option>
                            <option value="turnos" <?php if($estado_filtro==='turnos') echo 'selected'; ?>>Turnos agendados / abiertas</option>
                            <option value="en_atencion" <?php if($estado_filtro==='en_atencion') echo 'selected'; ?>>En atención</option>
                            <option value="cerrada" <?php if($estado_filtro==='cerrada') echo 'selected'; ?>>Cerradas</option>
                            <option value="cancelada" <?php if($estado_filtro==='cancelada') echo 'selected'; ?>>Canceladas</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px; margin-right:4px; font-weight:600;">Fecha:</label>
                        <select name="fecha" class="form-control input-sm" onchange="this.form.submit()">
                            <option value="todas" <?php if($fecha_filtro==='todas') echo 'selected'; ?>>Todas las fechas</option>
                            <option value="hoy" <?php if($fecha_filtro==='hoy') echo 'selected'; ?>>Hoy</option>
                            <option value="proximos" <?php if($fecha_filtro==='proximos') echo 'selected'; ?>>Próximos 7 días</option>
                        </select>
                    </div>
                    <?php if(!empty($colaboradores_area)) { ?>
                    <div>
                        <label style="font-size:12px; margin-right:4px; font-weight:600;">Colaborador:</label>
                        <select name="colaborador" class="form-control input-sm" onchange="this.form.submit()">
                            <option value="0">Todos los colaboradores</option>
                            <?php foreach($colaboradores_area as $c_opt) {
                                $nom_c = isset($c_opt['name']) && trim($c_opt['name'])!=='' ? $c_opt['name'] : trim((isset($c_opt['nombres']) ? $c_opt['nombres'] : '').' '.(isset($c_opt['apellido']) ? $c_opt['apellido'] : ''));
                            ?>
                            <option value="<?php echo intval($c_opt['id_empleado']); ?>" <?php if($colab_filtro===intval($c_opt['id_empleado'])) echo 'selected'; ?>><?php echo texto_mostrar($nom_c); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <?php } ?>
                    <?php if($estado_filtro!=='todas' || $fecha_filtro!=='todas' || $colab_filtro>0) { ?>
                    <a href="atenciones.php<?php echo $tipo_filtro!=='todas' ? '?tipo='.urlencode($tipo_filtro) : ''; ?>" class="btn btn-white btn-sm" style="margin-left:auto;"><i class="fa fa-times"></i> Limpiar filtros</a>
                    <?php } ?>
                </form>

                <?php if($tipo_filtro==='recibidas') { ?>
                <p class="muted">Derivaciones que otras áreas derivaron hacia <strong><?php echo texto_mostrar($nombre_area); ?></strong> para su atención o seguimiento.<?php echo $accion_fecha_hoy; ?></p>
                <?php } else if($tipo_filtro==='enviadas') { ?>
                <p class="muted">Derivaciones que <strong><?php echo texto_mostrar($nombre_area); ?></strong> envió a otras áreas operativas y su estado actual en destino.<?php echo $accion_fecha_hoy; ?></p>
                <?php } else if($area_cli=='recepcion') { ?>
                    <?php if($fecha_filtro==='hoy') { ?>
                    <p class="muted">Mostrando los turnos registrados para hoy.<?php echo $accion_fecha_hoy; ?></p>
                    <?php } else { ?>
                    <p class="muted">Atenciones y turnos registrados desde recepción.<?php echo $accion_fecha_hoy; ?></p>
                    <?php } ?>
                <?php } else { ?>
                <p class="muted">Atenciones registradas en esta área y seguimiento de atenciones derivadas.<?php echo $accion_fecha_hoy; ?></p>
                <?php } ?>
                <?php echo crm_html_busqueda_clientes(); ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="grid simple">
                            <div class="grid-title no-border">
                                <h4>
                                    <?php
                                    if($tipo_filtro==='recibidas') echo 'Derivaciones recibidas';
                                    else if($tipo_filtro==='enviadas') echo 'Derivaciones enviadas';
                                    else echo 'Listado de atenciones';
                                    ?>
                                </h4>
                            </div>
                            <div class="grid-body no-border">
                                <p class="muted"><?php echo count($lista); ?> registro(s) encontrado(s) (máx. 200).</p>
                                <table class="table table-hover no-more-tables">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Cliente</th>
                                            <th>Estado</th>
                                            <?php if($tipo_filtro==='recibidas') { ?>
                                            <th>Derivado desde</th>
                                            <?php } else if($tipo_filtro==='enviadas') { ?>
                                            <th>Área destino</th>
                                            <?php } else { ?>
                                            <th>Área activa</th>
                                            <?php } ?>
                                            <th>Colaborador</th>
                                            <th>Motivo</th>
                                            <th>Fecha</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    if(count($lista)==0) { ?>
                                        <tr><td colspan="8">No hay registros para este filtro.</td></tr>
                                    <?php } else {
                                        foreach($lista as $row) {
                                            $ft=isset($row['fecha_turno']) && $row['fecha_turno']!='' ? $row['fecha_turno'] : $row['fecha'];
                                            $es_der=(!empty($row['id_area_origen']) || !empty($row['area_origen_nombre']) || !empty($row['id_atencion_origen']) || $row['estado']=='derivada');
                                            $badge_cls=function_exists('crm_estado_badge_class') ? crm_estado_badge_class($row['estado']) : 'label-info';
                                            $est_txt=function_exists('crm_estado_texto') ? crm_estado_texto($row['estado'], $es_der) : $row['estado'];
                                    ?>
                                        <tr>
                                            <td><?php echo intval($row['id_atencion']); ?></td>
                                            <td><strong><?php echo texto_mostrar($row['cliente_nombre']); ?></strong></td>
                                            <td><span class="badge <?php echo htmlspecialchars($badge_cls); ?>"><?php echo texto_mostrar($est_txt); ?></span></td>
                                            <?php if($tipo_filtro==='recibidas') { ?>
                                            <td>
                                                <strong><?php echo texto_mostrar(!empty($row['area_origen_nombre']) ? $row['area_origen_nombre'] : 'Recepción / otra área'); ?></strong>
                                                <?php if(!empty($row['empleado_origen_nombre'])) { ?>
                                                <div style="font-size:11px; color:#64748b;"><?php echo texto_mostrar($row['empleado_origen_nombre']); ?></div>
                                                <?php } ?>
                                            </td>
                                            <?php } else if($tipo_filtro==='enviadas') { ?>
                                            <td><strong><?php echo texto_mostrar($row['area_nombre']); ?></strong></td>
                                            <?php } else { ?>
                                            <td>
                                                <?php echo texto_mostrar($row['area_nombre']); ?>
                                                <?php if(!empty($row['area_origen_nombre'])) { ?>
                                                <div style="font-size:11px; color:#64748b;"><i class="fa fa-share"></i> Desde: <?php echo texto_mostrar($row['area_origen_nombre']); ?></div>
                                                <?php } ?>
                                            </td>
                                            <?php } ?>
                                            <td>
                                                <?php
                                                $emp_n = !empty($row['empleado_nombre']) ? trim($row['empleado_nombre']) : '';
                                                if($emp_n !== '') {
                                                    echo '<span class="text-success" style="font-weight:600;"><i class="fa fa-user"></i> '.texto_mostrar($emp_n).'</span>';
                                                } else {
                                                    echo '<span class="muted" style="font-size:11px;">Sin asignar</span>';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo texto_mostrar(crm_motivo_texto($row['motivo'])); ?></td>
                                            <td><?php echo texto_mostrar(function_exists('crm_turno_fecha_texto') ? crm_turno_fecha_texto($ft) : $ft); ?></td>
                                            <td><?php if($area_cli==='sistemas') { ?><span class="muted">Solo consulta</span><?php } else { ?><a href="atencion.php?id=<?php echo intval($row['id_atencion']); ?>" class="btn btn-white btn-xs" style="font-weight:600;"><i class="fa fa-folder-open-o"></i> Abrir</a><?php } ?></td>
                                        </tr>
                                    <?php } } ?>
                                    </tbody>
                                </table>
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
</body>
</html>
