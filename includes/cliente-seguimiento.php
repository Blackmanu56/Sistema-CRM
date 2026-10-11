<?php
// Seguimiento del cliente: turnos, programas y tareas (solo lectura + anotar programa si corresponde).
if(!isset($id))
{
	return;
}
$id_seg=intval($id);
$area_seg=acceso_area_pantalla();
$mostrar_nuevo_turno=isset($mostrar_nuevo_turno) ? (bool)$mostrar_nuevo_turno : ($area_seg=='recepcion');
$mostrar_anotar_prog=function_exists('crm_puede_anotar_programa') && crm_puede_anotar_programa();
$tab=isset($_GET['seg']) ? $_GET['seg'] : 'turnos';
if(!in_array($tab, array('turnos','programas','tareas'), true))
{
	$tab='turnos';
}
$turnos_seg=function_exists('crm_atenciones_de') ? crm_atenciones_de($id_seg) : array();
$progs_seg=function_exists('crm_programas_de_cliente') ? crm_programas_de_cliente($id_seg) : array();
$tareas_seg=function_exists('crm_tareas_de_cliente') ? crm_tareas_de_cliente($id_seg) : array();
$base_ficha='editar-cliente.php?id='.$id_seg;
?>
                        <div class="grid simple">
                        <div class="grid-title no-border">
                            <h4 style="margin:0 0 10px 0;">Seguimiento</h4>
                            <ul class="nav nav-tabs" style="margin-bottom:0;">
                                <li class="<?php echo $tab=='turnos' ? 'active' : ''; ?>"><a href="<?php echo $base_ficha; ?>&amp;seg=turnos">Atenciones (<?php echo count($turnos_seg); ?>)</a></li>
                                <li class="<?php echo $tab=='programas' ? 'active' : ''; ?>"><a href="<?php echo $base_ficha; ?>&amp;seg=programas">Programas (<?php echo count($progs_seg); ?>)</a></li>
                                <li class="<?php echo $tab=='tareas' ? 'active' : ''; ?>"><a href="<?php echo $base_ficha; ?>&amp;seg=tareas">Tareas (<?php echo count($tareas_seg); ?>)</a></li>
                            </ul>
                        </div>
                        <div class="grid-body no-border" style="padding-top:14px;">
                            <?php if($tab=='turnos') { ?>
                            <p class="muted">Áreas con acceso:
                            <?php
                            $nombres_a=array();
                            if(function_exists('crm_areas_del_cliente'))
                            {
                            	foreach(crm_areas_del_cliente($id_seg) as $a) { $nombres_a[]=$a['nombre']; }
                            }
                            echo count($nombres_a) ? texto_mostrar(implode(', ', $nombres_a)) : 'todavía no hay';
                            ?></p>
                            <?php if(count($turnos_seg)==0) { ?>
                            <p class="muted">Sin atenciones registradas.</p>
                            <?php } foreach($turnos_seg as $at) {
                            	$ft=isset($at['fecha_turno']) && $at['fecha_turno']!='' ? $at['fecha_turno'] : (isset($at['fecha']) ? $at['fecha'] : '');
                            ?>
                            <div style="border:1px solid #eee; padding:10px; margin-bottom:10px;">
                                <p style="margin-bottom:4px;"><strong><?php echo texto_mostrar(function_exists('crm_turno_fecha_texto') ? crm_turno_fecha_texto($ft) : $ft); ?></strong>
                                    · <?php echo texto_mostrar(isset($at['area_nombre']) ? $at['area_nombre'] : ''); ?>
                                    · <?php echo texto_mostrar(function_exists('crm_motivo_texto') ? crm_motivo_texto($at['motivo']) : $at['motivo']); ?>
                                    · <?php echo texto_mostrar(isset($at['estado']) && function_exists('crm_estado_texto') ? crm_estado_texto($at['estado']) : ''); ?>
                                    · <a href="atencion.php?id=<?php echo intval($at['id_atencion']); ?>">Abrir</a></p>
                                <?php if(isset($at['detalle']) && trim($at['detalle'])!='') { ?>
                                <p class="muted" style="margin:0;"><?php echo texto_mostrar($at['detalle']); ?></p>
                                <?php } ?>
                            </div>
                            <?php } ?>
                            <?php if($mostrar_nuevo_turno) { ?>
                            <p><a href="nueva-atencion.php?id=<?php echo intval($id_seg); ?>" class="btn btn-primary btn-mini">Dar turno</a></p>
                            <?php } ?>

                            <?php } else if($tab=='programas') { ?>
                            <?php if(count($progs_seg)==0) { ?>
                            <p class="muted">Todavía no está anotado en un programa.</p>
                            <?php } foreach($progs_seg as $pc) { ?>
                            <p><?php echo texto_mostrar($pc['nombre']); ?></p>
                            <?php } ?>
                            <?php if($mostrar_anotar_prog && function_exists('crm_programas_lista')) { ?>
                            <form method="post" action="<?php echo $base_ficha; ?>&amp;seg=programas" style="margin-top:12px;">
                                <input type="hidden" name="id_cliente" value="<?php echo intval($id_seg); ?>">
                                <select name="id_programa" class="form-control" required>
                                    <option value="">Anotar en programa…</option>
                                    <?php foreach(crm_programas_lista() as $pr) { ?>
                                    <option value="<?php echo intval($pr['id_programa']); ?>"><?php echo texto_mostrar($pr['nombre']); ?></option>
                                    <?php } ?>
                                </select>
                                <p style="margin-top:8px;"><button type="submit" name="anotar_programa" value="1" class="btn btn-white">Anotar y generar tareas</button></p>
                            </form>
                            <?php } else if(!$mostrar_anotar_prog) { ?>
                            <p class="muted" style="margin-top:8px;">Solo un coordinador de área puede anotar en un programa.</p>
                            <?php } ?>

                            <?php } else { /* tareas */
                            	$mapa_est=array(
                            		'no_iniciada'=>'No iniciada','en_proceso'=>'En proceso','pausada'=>'Pausada','terminada'=>'Terminada',
                            		'No iniciada'=>'No iniciada','En proceso'=>'En proceso','Pausada'=>'Pausada','Terminada'=>'Terminada'
                            	);
                            ?>
                            <?php if(count($tareas_seg)==0) { ?>
                            <p class="muted">Sin tareas para este cliente.</p>
                            <?php } foreach($tareas_seg as $ta) {
                            	$est=isset($ta['estado']) ? $ta['estado'] : '';
                            	if(isset($mapa_est[$est])) { $est=$mapa_est[$est]; }
                            ?>
                            <div style="border:1px solid #eee; padding:10px; margin-bottom:8px;">
                                <p style="margin-bottom:2px;"><a href="<?php echo url_crm($ta['area_clave'].'/ver-tarea.php?id='.intval($ta['id_tarea'])); ?>"><?php echo texto_mostrar($ta['titulo']); ?></a>
                                    · <?php echo texto_mostrar($ta['area_nombre']); ?>
                                    <?php if(isset($ta['tipo_nombre']) && $ta['tipo_nombre']!='') { ?> · <?php echo texto_mostrar($ta['tipo_nombre']); ?><?php } ?>
                                    · <?php echo texto_mostrar($est); ?>
                                    <?php if(isset($ta['avance'])) { ?> · <?php echo intval($ta['avance']); ?>%<?php } ?>
                                </p>
                                <?php if(isset($ta['id_atencion']) && intval($ta['id_atencion'])>0) { ?>
                                <p class="muted" style="margin:0;">Turno #<?php echo intval($ta['id_atencion']); ?></p>
                                <?php } ?>
                            </div>
                            <?php } ?>
                            <?php } ?>
                        </div>
                        </div>
