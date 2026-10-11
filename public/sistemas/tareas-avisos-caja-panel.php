<?php
// Caja de avisos del panel Sistemas. Lee los avisos pendientes de la
// base (tabla aviso) via el repositorio compartido. Si el repositorio no
// existe no se muestra nada (sin avisos JSON: la ruta de escritura actual
// es BD). El boton OK confirma el aviso en la base (name cerrar_aviso_panel,
// distinto del compartido para que acceso.php no lo intercepte) y el
// handler local en tareas.php solo confirma avisos propios. "Ir a la
// tarea" abre el detalle.
$avisos_aten=function_exists('crm_avisos_atencion_mios') ? crm_avisos_atencion_mios() : array();
if(count($avisos_aten)>0) {
?>
                <div class="alert alert-info">
                    <p><strong>Hay clientes para atender</strong></p>
                    <?php foreach($avisos_aten as $av) { ?>
                    <div style="margin-bottom:10px;">
                        <p>Cliente <strong><?php echo texto_mostrar($av['cliente_nombre']); ?></strong></p>
                        <a href="<?php echo url_crm((isset($av['area_clave']) ? $av['area_clave'] : 'sistemas').'/atencion.php?id='.intval($av['id_atencion'])); ?>" class="btn btn-primary btn-cons">Atender</a>
                    </div>
                    <?php } ?>
                </div>
<?php }
$id_empleado=tareas_empleado_actual_id();
$avisos_mios=function_exists('repo_avisos_mios') ? repo_avisos_mios($id_empleado) : array();
if(count($avisos_mios)>0) {
?>
                <div class="alert alert-warning">
                    <p><strong>Te asignaron una o más tareas</strong></p>
                    <p>OK confirma que lo viste y saca este aviso. Ir a la tarea abre el detalle.</p>
                    <?php foreach($avisos_mios as $aviso) {
                        $de=isset($aviso['de']) ? $aviso['de'] : '';
                        $tit=isset($aviso['titulo']) ? $aviso['titulo'] : '';
                    ?>
                    <div style="margin-bottom:10px;">
                        <p><?php echo texto_mostrar($de); ?> te asignó: <strong><?php echo texto_mostrar($tit); ?></strong></p>
                        <a href="<?php echo texto_mostrar(tareas_aviso_url($aviso)); ?>" class="btn btn-white btn-cons">Ir a la tarea</a>
                        <form method="post" action="<?php echo tareas_link('tareas.php'); ?>" style="display:inline;">
                            <input type="hidden" name="cerrar_aviso_panel" value="<?php echo texto_mostrar(isset($aviso['id']) ? $aviso['id'] : ''); ?>">
                            <button type="submit" class="btn btn-primary btn-cons">OK</button>
                        </form>
                    </div>
                    <?php } ?>
                </div>
<?php } ?>