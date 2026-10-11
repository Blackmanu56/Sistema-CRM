<?php
// Cuerpo de cada tarjeta en la lista de tareas.
$estado_txt=tarea_texto_estado($tarea);
$info_act=function_exists('crm_tarea_actividad_origen_info') ? crm_tarea_actividad_origen_info($tarea['id']) : null;
?>
                                <?php if($info_act) {
                                    $link_ev=($info_act['id_evento']>0) ? tareas_link('actividades.php', array('id'=>$info_act['id_evento'])) : '';
                                    $org_ev=!empty($info_act['evento_area_nombre']) ? ' (Org: '.$info_act['evento_area_nombre'].')' : '';
                                ?>
                                <p class="muted" style="margin:0 0 6px; font-size:12px;">
                                    <i class="fa fa-calendar"></i> Actividad: <?php if($link_ev!='') { ?><a href="<?php echo texto_mostrar($link_ev); ?>"><?php echo texto_mostrar($info_act['evento_titulo'].$org_ev); ?></a><?php } else { ?><?php echo texto_mostrar($info_act['evento_titulo'].$org_ev); ?><?php } ?>
                                </p>
                                <?php } ?>
                                <p><?php echo texto_mostrar(isset($tarea['descripcion']) ? $tarea['descripcion'] : ''); ?></p>
                                <p>
                                    <span class="label label-info"><?php echo texto_mostrar(tareas_etiqueta_tipo()); ?>: <?php echo texto_mostrar(isset($tarea['tipo']) ? $tarea['tipo'] : ''); ?></span>
                                    <span class="label <?php echo label_prioridad(isset($tarea['prioridad']) ? $tarea['prioridad'] : ''); ?>">Prioridad: <?php echo texto_mostrar(isset($tarea['prioridad']) ? $tarea['prioridad'] : ''); ?></span>
                                    <?php if(acceso_area_pantalla()=='general' && !empty($tarea['area_nombre'])) { ?>
                                    <span class="label"><?php echo texto_mostrar($tarea['area_nombre']); ?></span>
                                    <?php } ?>
                                </p>
                                <?php $cli_txt=tarea_texto_cliente($tarea); if($cli_txt!='') { ?>
                                <p><strong>Cliente:</strong> <?php echo texto_mostrar($cli_txt); ?></p>
                                <?php } ?>
                                <p><i class="fa fa-user"></i> <?php echo texto_mostrar(tarea_texto_responsables($tarea)); ?></p>
                                <p><strong>Fecha de inicio:</strong> <?php echo fecha_mostrar(isset($tarea['fecha_inicio']) ? $tarea['fecha_inicio'] : ''); ?><br>
                                   <strong>Fecha de finalización:</strong> <?php echo fecha_mostrar(isset($tarea['fecha_cierre']) ? $tarea['fecha_cierre'] : ''); ?></p>
                                <div class="text-right">
                                    <a href="<?php echo tareas_link('ver-tarea.php', array('id'=>$tarea['id'])); ?>" class="btn btn-white btn-cons">Ver</a>
                                </div>
