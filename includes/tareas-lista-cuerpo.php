<?php
// Cuerpo de cada tarjeta en la lista de tareas.
$estado_txt=tarea_texto_estado($tarea);
?>
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
