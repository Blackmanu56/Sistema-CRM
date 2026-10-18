<?php
// Cuerpo de cada tarjeta en la lista de tareas.
$estado_txt=tarea_texto_estado($tarea);
$info_act=function_exists('crm_tarea_actividad_origen_info') ? crm_tarea_actividad_origen_info($tarea['id']) : null;
$fecha_inicio_raw=substr(isset($tarea['fecha_inicio']) ? (string)$tarea['fecha_inicio'] : '',0,10);
$fecha_cierre_raw=substr(isset($tarea['fecha_cierre']) ? (string)$tarea['fecha_cierre'] : '',0,10);
$fecha_inicio_txt=function_exists('crm_tarea_fecha_filtro_valida') && crm_tarea_fecha_filtro_valida($fecha_inicio_raw) ? date('d/m/Y',strtotime($fecha_inicio_raw)) : 'Sin fecha';
$fecha_cierre_txt=function_exists('crm_tarea_fecha_filtro_valida') && crm_tarea_fecha_filtro_valida($fecha_cierre_raw) ? date('d/m/Y',strtotime($fecha_cierre_raw)) : 'Sin fecha';
$estado_tarea_txt=trim($estado_txt);
$fecha_hoy_txt=date('Y-m-d');
$proxima_vencer=($fecha_cierre_txt!=='Sin fecha' && $fecha_cierre_raw>=$fecha_hoy_txt && $fecha_cierre_raw<=date('Y-m-d',strtotime('+7 days')) && $estado_tarea_txt!=='Terminada' && $estado_tarea_txt!=='Eliminada');
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
                                    <?php if($proxima_vencer) { ?><span class="label label-warning">Próxima a vencer</span><?php } ?>
                                    <?php if(acceso_area_pantalla()=='general' && !empty($tarea['area_nombre'])) { ?>
                                    <span class="label"><?php echo texto_mostrar($tarea['area_nombre']); ?></span>
                                    <?php } ?>
                                </p>
                                <?php $cli_txt=tarea_texto_cliente($tarea); if($cli_txt!='') { ?>
                                <p><strong>Cliente:</strong> <?php echo texto_mostrar($cli_txt); ?></p>
                                <?php } ?>
                                <?php
                                $responsables_tarea_lista=tarea_lista_responsables($tarea);
                                $area_tarea_clave=isset($tarea['area_clave']) ? (string)$tarea['area_clave'] : (isset($tarea['clave_area']) ? (string)$tarea['clave_area'] : '');
                                $area_tarea_etiqueta=($area_tarea_clave!=='' && function_exists('acceso_etiqueta_corta_area')) ? trim((string)acceso_etiqueta_corta_area($area_tarea_clave)) : '';
                                if($area_tarea_etiqueta!=='') {
                                    foreach($responsables_tarea_lista as &$responsable_tarea_item) {
                                        $responsable_tarea_item=preg_replace('/\\s+-\\s+'.preg_quote($area_tarea_etiqueta,'/').'$/u','',trim((string)$responsable_tarea_item));
                                    }
                                    unset($responsable_tarea_item);
                                }
                                $responsables_tarea=trim(implode(', ',array_filter($responsables_tarea_lista,'strlen')));
                                ?>
                                <?php $area_tarea_txt=trim((string)(isset($tarea['area_nombre']) ? $tarea['area_nombre'] : (isset($tarea['area']) ? $tarea['area'] : ''))); ?>
                                <div class="crm-tarea-card-meta">
                                    <div class="crm-tarea-card-persona">
                                        <span class="crm-tarea-card-responsable"><i class="fa fa-user" aria-hidden="true"></i><span><?php echo texto_mostrar($responsables_tarea!=='' ? $responsables_tarea : 'Sin asignar'); ?></span></span>
                                        <?php if($area_tarea_txt!=='') { ?><span class="crm-tarea-card-area"><i class="fa fa-folder-o" aria-hidden="true"></i><span><?php echo texto_mostrar($area_tarea_txt); ?></span></span><?php } ?>
                                    </div>
                                    <div class="crm-tarea-card-fechas" aria-label="Fechas de la tarea">
                                        <span class="crm-tarea-card-fecha"><i class="fa fa-calendar-o" aria-hidden="true"></i><span><strong>Inicio</strong><span><?php echo texto_mostrar($fecha_inicio_txt); ?></span></span></span>
                                        <span class="crm-tarea-card-fecha-separador" aria-hidden="true"></span>
                                        <span class="crm-tarea-card-fecha"><i class="fa fa-calendar-o" aria-hidden="true"></i><span><strong>Vencimiento</strong><span><?php echo texto_mostrar($fecha_cierre_txt); ?></span></span></span>
                                    </div>
                                    <div class="crm-tarea-card-accion">
                                        <a href="<?php echo tareas_link('ver-tarea.php', array('id'=>$tarea['id'])); ?>" class="btn btn-white btn-cons">Ver</a>
                                    </div>
                                </div>
