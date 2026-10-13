<?php
// Cuerpo de Ver tarea: avances, partes (subtareas), baja y acciones.
$enlaces=isset($tarea['enlaces']) && is_array($tarea['enlaces']) ? $tarea['enlaces'] : array();
$comentarios=isset($tarea['comentarios']) && is_array($tarea['comentarios']) ? $tarea['comentarios'] : array();
$estado_txt=tarea_texto_estado($tarea);
$eliminada=($estado_txt=='Cancelada' || $estado_txt=='Eliminada' || (isset($tarea['estado']) && (strtolower($tarea['estado'])=='cancelada' || $tarea['estado']=='Cancelada')));
if($eliminada)
{
	$estado_txt='Eliminada';
}
$sin_iniciar=tarea_nombres_sin_iniciar($tarea);
$ver_url=tareas_link('ver-tarea.php', array('id'=>$tarea['id']));
$id_tar=intval($tarea['id']);
if($id_tar>0 && function_exists('crm_notificacion_marcar_vista_por_entidad'))
{
	crm_notificacion_marcar_vista_por_entidad('tarea', $id_tar);
}
$fila_db=function_exists('crm_tarea_fila') ? crm_tarea_fila($id_tar) : null;
if($fila_db && isset($fila_db['estado']) && $fila_db['estado']=='cancelada')
{
	$eliminada=true;
	$estado_txt='Eliminada';
}
$id_padre=($fila_db && isset($fila_db['id_tarea_padre'])) ? intval($fila_db['id_tarea_padre']) : 0;
$hijas=(!$eliminada && function_exists('crm_tarea_hijas')) ? crm_tarea_hijas($id_tar) : array();
$tiene_partes=(count($hijas)>0);
$puede_desglosar=(!$eliminada && function_exists('crm_tarea_puede_desglosar') && crm_tarea_puede_desglosar($tarea));
$sol_baja=(!$eliminada && function_exists('crm_tarea_solicitud_activa')) ? crm_tarea_solicitud_activa($id_tar) : null;
$equipo=array();
if($puede_desglosar && function_exists('crm_tarea_equipo_ids'))
{
	$area_eq=($fila_db && !empty($fila_db['area_clave'])) ? $fila_db['area_clave'] : '';
	$equipo=crm_tarea_equipo_ids($area_eq);
}
$avances=function_exists('crm_tarea_avances_juntos') ? crm_tarea_avances_juntos($enlaces, $comentarios) : array();
$yo_ses=isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : '';
$pide_iniciar_al_avance=(!$eliminada && $yo_ses!='' && tarea_puede_cambiar_estado($tarea)
	&& $estado_txt!='Terminada' && $estado_txt!='Pausada'
	&& !tarea_persona_inicio($tarea, $yo_ses));
?>
                        <?php if(isset($_SESSION['msgtarea']) && $_SESSION['msgtarea']!='') { ?>
                        <div class="alert alert-warning"><?php echo texto_mostrar($_SESSION['msgtarea']); $_SESSION['msgtarea']=''; ?></div>
                        <?php } ?>

                        <?php if($eliminada) { ?>
                        <div class="alert alert-error" style="margin-bottom:14px;">
                            <strong>Eliminada.</strong> Solo se puede consultar. No se edita ni se agregan avances.
                        </div>
                        <?php } ?>

                        <?php if($id_padre>0 && $fila_db) {
                        	$padre=crm_tarea_fila($id_padre);
                        ?>
                        <p class="muted">Subtarea de: <?php if($padre) { ?><a href="<?php echo tareas_link('ver-tarea.php', array('id'=>$id_padre)); ?>"><?php echo texto_mostrar($padre['titulo']); ?></a><?php } else { ?>tarea #<?php echo $id_padre; ?><?php } ?></p>
                        <?php } ?>
                        <?php
                        $info_act=function_exists('crm_tarea_actividad_origen_info') ? crm_tarea_actividad_origen_info($id_tar) : null;
                        if($info_act) {
                        	$link_ev=($info_act['id_evento']>0) ? tareas_link('actividades.php', array('id'=>$info_act['id_evento'])) : '';
                        	$org_ev=!empty($info_act['evento_area_nombre']) ? ' (Organiza: '.$info_act['evento_area_nombre'].')' : '';
                        ?>
                        <p class="muted" style="margin-bottom:8px;"><i class="fa fa-calendar"></i> Actividad de origen: <?php if($link_ev!='') { ?><a href="<?php echo texto_mostrar($link_ev); ?>"><?php echo texto_mostrar($info_act['evento_titulo'].$org_ev); ?></a><?php } else { ?><?php echo texto_mostrar($info_act['evento_titulo'].$org_ev); ?><?php } ?></p>
                        <?php } ?>
                        <p><?php echo texto_mostrar($tarea['descripcion']); ?></p>
                        <p>
                            <span class="label label-info"><?php echo texto_mostrar(tareas_etiqueta_tipo()); ?>: <?php echo texto_mostrar($tarea['tipo']); ?></span>
                            <span class="label <?php echo label_prioridad($tarea['prioridad']); ?>">Prioridad: <?php echo texto_mostrar($tarea['prioridad']); ?></span>
                            <span class="label <?php echo $eliminada ? 'label-important' : label_estado($estado_txt); ?>"><?php echo texto_mostrar($estado_txt); ?></span>
                            <?php if(!$eliminada && tarea_esta_vencida($tarea)){ ?>
                            <span class="label label-important">Vencida</span>
                            <?php } ?>
                        </p>
                        <?php
                        $creada_por=isset($tarea['creada_por']) ? trim((string)$tarea['creada_por']) : '';
                        if($creada_por=='' && !empty($tarea['descripcion']) && preg_match('/Asignada por:\s*(.+?)(?:\.|$)/u', $tarea['descripcion'], $m_asig))
                        {
                        	$creada_por=trim($m_asig[1]);
                        }
                        if($creada_por!='') { ?>
                        <p><strong>Creada/asignada por:</strong> <?php echo texto_mostrar($creada_por); ?></p>
                        <?php } ?>
                        <?php $cli_txt=tarea_texto_cliente($tarea); if($cli_txt!='') { ?>
                        <p><strong>Cliente:</strong> <?php echo texto_mostrar($cli_txt); ?></p>
                        <?php } ?>
                        <p><strong>Responsable/s:</strong> <?php echo texto_mostrar(tarea_texto_responsables($tarea)); ?></p>
                        <?php
                        $sin_resp=true;
                        foreach(tarea_lista_responsables($tarea) as $rn)
                        {
                        	if(trim((string)$rn)!='') { $sin_resp=false; break; }
                        }
                        if(!$eliminada && $sin_resp && tarea_puede_editar($tarea)) {
                        	$area_eq=($fila_db && !empty($fila_db['area_clave'])) ? $fila_db['area_clave'] : acceso_area_pantalla();
                        	$equipo_asig=function_exists('crm_tarea_equipo_ids') ? crm_tarea_equipo_ids($area_eq) : array();
                        ?>
                        <div style="margin:8px 0 14px; padding:10px 12px; background:#fff8e6; border:1px solid #f0e0b2;">
                            <p style="margin:0 0 8px;"><strong>Sin responsable.</strong> No se puede iniciar ni cargar avances hasta asignar uno.</p>
                            <form method="post" action="<?php echo texto_mostrar($ver_url); ?>" class="form-inline">
                                <input type="hidden" name="id_tarea" value="<?php echo $id_tar; ?>">
                                <select name="id_responsable" class="form-control" required style="max-width:280px; display:inline-block;">
                                    <option value="">Elegir responsable…</option>
                                    <?php foreach($equipo_asig as $eq) { ?>
                                    <option value="<?php echo intval($eq['id_empleado']); ?>"><?php echo texto_mostrar($eq['nombre']); ?></option>
                                    <?php } ?>
                                </select>
                                <button type="submit" name="asignar_responsable_rapido" value="1" class="btn btn-primary">Asignar responsable</button>
                            </form>
                        </div>
                        <?php } ?>
                        <?php if(!$eliminada && count($sin_iniciar)>0 && $estado_txt!='Terminada') { ?>
                        <p class="muted">Sin iniciar: <?php echo texto_mostrar(implode(', ', $sin_iniciar)); ?></p>
                        <?php } ?>
                        <p><strong>Inicio:</strong> <?php echo fecha_mostrar($tarea['fecha_inicio']); ?>
                           &nbsp; · &nbsp; <strong>Estimada:</strong> <?php echo fecha_mostrar($tarea['fecha_cierre']); ?></p>
                        <?php if(!$eliminada) { ?>
                        <div class="progress">
                            <div class="progress-bar progress-bar-success" style="width:<?php echo intval($tarea['avance']); ?>%"></div>
                        </div>
                        <p>Progreso: <?php echo intval($tarea['avance']); ?><?php if($tiene_partes) { ?> % <span class="muted">(según las subtareas)</span><?php } else { ?> %<?php } ?></p>
                        <?php } ?>

                        <?php if($eliminada) { ?>
                        <hr>
                        <h5>Comentarios y links que tenía</h5>
                        <?php if(count($avances)==0) { ?>
                        <p class="muted">No tenía nada cargado.</p>
                        <?php } foreach($avances as $av) { ?>
                        <div class="crm-avance-row">
                            <div class="crm-avance-meta"><?php echo texto_mostrar(trim(($av['quien']!='' ? $av['quien'].' · ' : '').$av['cuando'])); ?></div>
                            <div class="crm-avance-texto"><?php echo $av['texto']!='' ? texto_mostrar($av['texto']) : '<span class="muted">—</span>'; ?></div>
                            <div class="crm-avance-link"><?php if($av['url']!='') { ?><a href="<?php echo texto_mostrar($av['url']); ?>" target="_blank" rel="noopener"><?php echo texto_mostrar($av['url']); ?></a><?php } else { ?><span class="muted">—</span><?php } ?></div>
                        </div>
                        <?php } ?>
                        <div class="form-actions" style="margin-top:16px;">
                            <?php if(isset($tarea['id_atencion']) && intval($tarea['id_atencion'])>0) { ?>
                            <a href="atencion.php?id=<?php echo intval($tarea['id_atencion']); ?>" class="btn btn-white">Volver a la atención</a>
                            <?php } else { ?>
                            <a href="<?php echo tareas_link('tareas.php'); ?>" class="btn btn-white">Volver</a>
                            <?php } ?>
                        </div>
                        <?php return; } ?>

                        <hr>
                        <?php if($id_padre<=0) { ?>
                        <h5>Subtareas</h5>
                        <?php if($tiene_partes) { ?>
                        <p class="muted">Esta tarea se completa con las subtareas de abajo.</p>
                        <?php foreach($hijas as $h) {
                        	$est_h=$h['estado'];
                        	$mapa=array('no_iniciada'=>'No iniciada','en_proceso'=>'En proceso','pausada'=>'Pausada','terminada'=>'Terminada','cancelada'=>'Eliminada');
                        	if(isset($mapa[$est_h])) { $est_h=$mapa[$est_h]; }
                        ?>
                        <div style="border:1px solid #eee; padding:8px 10px; margin-bottom:6px;">
                            <a href="<?php echo url_crm($h['area_clave'].'/ver-tarea.php?id='.intval($h['id_tarea'])); ?>"><?php echo texto_mostrar($h['titulo']); ?></a>
                            · <?php echo texto_mostrar($est_h); ?>
                            · <?php echo intval($h['avance']); ?>%
                            <?php if(isset($h['responsable']) && $h['responsable']!='') { ?> · <?php echo texto_mostrar($h['responsable']); ?><?php } ?>
                        </div>
                        <?php } ?>
                        <?php } else { ?>
                        <p class="muted">Sin subtareas. Se puede completar con avances o desglosarla.</p>
                        <?php } ?>

                        <?php if($puede_desglosar) { ?>
                        <p style="margin:12px 0 8px;">
                            <button type="button" class="btn btn-white" id="btn_mostrar_parte" onclick="var b=document.getElementById('caja_nueva_parte'); var x=b.style.display==='none'; b.style.display=x?'block':'none'; this.textContent=x?'Cancelar':'Agregar subtarea';">Agregar subtarea</button>
                        </p>
                        <div id="caja_nueva_parte" style="display:none; margin:0 0 20px; border:1px solid #eee; padding:12px; background:#fafafa;">
                        <form method="post" action="<?php echo texto_mostrar($ver_url); ?>">
                            <input type="hidden" name="id_tarea" value="<?php echo $id_tar; ?>">
                            <input type="hidden" name="crear_subtarea" value="1">
                            <?php if(count($equipo)==0) { ?>
                            <p class="muted">No hay colaboradores activos en esta área para asignar. Cargá alguien en RRHH y volvé.</p>
                            <?php } else { ?>
                            <div class="form-group">
                                <label>Qué hay que hacer</label>
                                <input type="text" name="sub_titulo" class="form-control" required placeholder="Ej: Pedir presupuesto">
                            </div>
                            <div class="form-group">
                                <label>Quién la hace</label>
                                <select name="sub_responsable" class="form-control" required>
                                    <option value="">Elegir…</option>
                                    <?php foreach($equipo as $e) { ?>
                                    <option value="<?php echo intval($e['id_empleado']); ?>"><?php echo texto_mostrar($e['nombre']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Fecha estimada</label>
                                <?php
                                $min_sub = date('Y-m-d');
                                if(!empty($tarea['creado_en']))
                                {
                                    $f_crea_sub = substr($tarea['creado_en'], 0, 10);
                                    if($f_crea_sub > $min_sub) { $min_sub = $f_crea_sub; }
                                }
                                if(!empty($tarea['fecha_inicio']))
                                {
                                    $fi_sub = substr($tarea['fecha_inicio'], 0, 10);
                                    if($fi_sub > $min_sub) { $min_sub = $fi_sub; }
                                }
                                $val_sub = isset($tarea['fecha_cierre']) && !empty($tarea['fecha_cierre']) ? substr($tarea['fecha_cierre'],0,10) : '';
                                if($val_sub === '' || $val_sub < $min_sub)
                                {
                                    $val_sub = $min_sub;
                                }
                                ?>
                                <input type="date" name="sub_fecha" class="form-control" min="<?php echo $min_sub; ?>" value="<?php echo texto_mostrar($val_sub); ?>" required oninput="if(this.value && this.value < this.min){ this.setCustomValidity('La fecha no puede ser anterior a ' + this.min); } else { this.setCustomValidity(''); }">
                                <p class="muted" style="margin-top:4px;">No puede ser anterior a la fecha de creación de la tarea ni a la fecha actual.</p>
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar subtarea</button>
                            <?php } ?>
                        </form>
                        </div>
                        <?php } ?>
                        <?php } ?>

                        <?php if(!$tiene_partes) { ?>
                        <hr>
                        <h5>Comentarios y links cargados (<?php echo count($avances); ?>)</h5>
                        <?php if(count($avances)==0) { ?>
                        <p class="muted">Todavía no se cargó nada.</p>
                        <?php } else { ?>
                        <div class="crm-avance-row crm-avance-head">
                            <div class="crm-avance-meta">Quién / cuándo</div>
                            <div class="crm-avance-texto">Comentario</div>
                            <div class="crm-avance-link">Link</div>
                        </div>
                        <?php } foreach($avances as $av) { ?>
                        <div class="crm-avance-row">
                            <div class="crm-avance-meta"><?php echo texto_mostrar(trim(($av['quien']!='' ? $av['quien'].' · ' : '').$av['cuando'])); ?></div>
                            <div class="crm-avance-texto"><?php echo $av['texto']!='' ? texto_mostrar($av['texto']) : '<span class="muted">—</span>'; ?></div>
                            <div class="crm-avance-link"><?php if($av['url']!='') { ?><a href="<?php echo texto_mostrar($av['url']); ?>" target="_blank" rel="noopener"><?php echo texto_mostrar($av['url']); ?></a><?php } else { ?><span class="muted">—</span><?php } ?></div>
                        </div>
                        <?php } ?>

                        <?php if(tarea_puede_agregar_extras($tarea)) { ?>
                        <h5 style="margin-top:18px;">Cargar comentario</h5>
                        <?php if($pide_iniciar_al_avance || $estado_txt=='No iniciada') { ?>
                        <p class="muted" style="margin:12px 0 20px;">Para cargar comentarios y links la tarea debe estar iniciada.</p>
                        <?php } else { ?>
                        <form method="post" action="<?php echo texto_mostrar($ver_url); ?>" class="crm-avance-form" style="margin:16px 0 24px;" onSubmit="return tareaAvanceOk();">
                            <input type="hidden" name="id_tarea" value="<?php echo $id_tar; ?>">
                            <input type="hidden" name="agregar_avance" value="1">
                            <div class="crm-avance-form-row">
                                <div class="form-group">
                                    <label>Comentario</label>
                                    <input type="text" name="comentario_nuevo" id="comentario_nuevo" class="form-control" required placeholder="Obligatorio">
                                </div>
                                <div class="form-group">
                                    <label>Link (opcional)</label>
                                    <input type="text" name="enlace_nuevo" id="enlace_nuevo" class="form-control" placeholder="https://">
                                </div>
                                <div class="form-group crm-avance-form-btn">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary">Guardar</button>
                                </div>
                            </div>
                        </form>
                        <script type="text/javascript">
                        function tareaAvanceOk()
                        {
                        	var com = document.getElementById('comentario_nuevo');
                        	var b = com ? com.value.replace(/^\s+|\s+$/g,'') : '';
                        	if(b=='')
                        	{
                        		alert('El comentario es obligatorio.');
                        		if(com) { com.focus(); }
                        		return false;
                        	}
                        	return true;
                        }
                        </script>
                        <?php } ?>
                        <?php } ?>
                        <?php } ?>

                        <?php if($sol_baja) { ?>
                        <hr>
                        <h5>Pedido de eliminación</h5>
                        <p>Estado: <?php
                        	$est_s=$sol_baja['estado'];
                        	if($est_s=='pendiente_coord') echo 'Esperando al coordinador de área';
                        	else if($est_s=='pendiente_general') echo 'Esperando al coordinador general';
                        	else echo texto_mostrar($est_s);
                        ?></p>
                        <p class="muted"><?php echo texto_mostrar($sol_baja['motivo']); ?></p>
                        <?php
                        $es_final=crm_tarea_puede_eliminar_directo();
                        $puedo_coord=($sol_baja['estado']=='pendiente_coord' && acceso_es_coord_de_area() && !$es_final);
                        $puedo_gen=$es_final;
                        if($puedo_coord || $puedo_gen) { ?>
                        <form method="post" action="<?php echo texto_mostrar($ver_url); ?>" style="margin-top:8px;">
                            <input type="hidden" name="id_solicitud_baja" value="<?php echo intval($sol_baja['id']); ?>">
                            <input type="hidden" name="id_tarea" value="<?php echo $id_tar; ?>">
                            <input type="hidden" name="baja_decidir" value="1">
                            <div class="form-group">
                                <label>Nota (opcional)</label>
                                <input type="text" name="nota_baja" class="form-control" placeholder="Comentario al aceptar o rechazar">
                            </div>
                            <button type="submit" name="baja_aceptar" value="1" class="btn btn-primary"><?php echo $puedo_gen ? 'Aceptar y eliminar' : 'Aceptar y enviar al general'; ?></button>
                            <button type="submit" name="baja_aceptar" value="0" class="btn btn-white">Rechazar</button>
                        </form>
                        <?php } ?>
                        <?php } else if(crm_tarea_puede_eliminar_directo()) { ?>
                        <hr>
                        <h5>Otras acciones</h5>
                        <div class="form-group" style="max-width:320px;">
                            <select id="accion_tarea_baja" class="form-control">
                                <option value="">Elegir…</option>
                                <option value="eliminar">Eliminar tarea…</option>
                            </select>
                        </div>
                        <div id="caja_eliminar_tarea" style="display:none; margin-top:10px; padding:12px; background:#fafafa; border:1px solid #e5e5e5;">
                            <p class="muted">Se elimina en el momento y se avisa a los responsables.</p>
                            <form method="post" action="<?php echo texto_mostrar($ver_url); ?>" onsubmit="return confirm('¿Eliminar esta tarea<?php echo $tiene_partes ? ' y sus subtareas' : ''; ?>? Esta acción no se puede deshacer.');">
                                <input type="hidden" name="id_tarea" value="<?php echo $id_tar; ?>">
                                <div class="form-group">
                                    <label>Motivo</label>
                                    <textarea name="motivo_baja" class="form-control" rows="2" required placeholder="Por qué se elimina esta tarea"></textarea>
                                </div>
                                <button type="submit" name="eliminar_tarea_directo" value="1" class="btn btn-danger">Confirmar eliminación</button>
                                <button type="button" class="btn btn-white" onclick="document.getElementById('accion_tarea_baja').value=''; document.getElementById('caja_eliminar_tarea').style.display='none';">Cancelar</button>
                            </form>
                        </div>
                        <script type="text/javascript">
                        (function(){
                          var sel=document.getElementById('accion_tarea_baja');
                          var caja=document.getElementById('caja_eliminar_tarea');
                          if(!sel||!caja) return;
                          sel.onchange=function(){
                            caja.style.display=(sel.value==='eliminar')?'block':'none';
                          };
                        })();
                        </script>
                        <?php } else if($estado_txt!='Terminada') { ?>
                        <hr>
                        <h5>Otras acciones</h5>
                        <div class="form-group" style="max-width:320px;">
                            <select id="accion_tarea_baja" class="form-control">
                                <option value="">Elegir…</option>
                                <option value="eliminar">Pedir eliminación…</option>
                            </select>
                        </div>
                        <div id="caja_eliminar_tarea" style="display:none; margin-top:10px; padding:12px; background:#fafafa; border:1px solid #e5e5e5;">
                            <p class="muted"><?php echo acceso_es_empleado()
                        	? 'Pedís la eliminación a tu coordinador de área. Si acepta, pasa al coordinador general.'
                        	: 'El pedido va al coordinador general para el OK final.'; ?></p>
                            <form method="post" action="<?php echo texto_mostrar($ver_url); ?>" onsubmit="return confirm('¿Enviar el pedido de eliminación?');">
                                <input type="hidden" name="id_tarea" value="<?php echo $id_tar; ?>">
                                <div class="form-group">
                                    <label>Motivo</label>
                                    <textarea name="motivo_baja" class="form-control" rows="2" required placeholder="Por qué se pide eliminar esta tarea"></textarea>
                                </div>
                                <button type="submit" name="pedir_baja_tarea" value="1" class="btn btn-white">Confirmar pedido</button>
                                <button type="button" class="btn btn-white" onclick="document.getElementById('accion_tarea_baja').value=''; document.getElementById('caja_eliminar_tarea').style.display='none';">Cancelar</button>
                            </form>
                        </div>
                        <script type="text/javascript">
                        (function(){
                          var sel=document.getElementById('accion_tarea_baja');
                          var caja=document.getElementById('caja_eliminar_tarea');
                          if(!sel||!caja) return;
                          sel.onchange=function(){
                            caja.style.display=(sel.value==='eliminar')?'block':'none';
                          };
                        })();
                        </script>
                        <?php } ?>

                        <div class="form-actions">
                            <?php if(isset($tarea['id_atencion']) && intval($tarea['id_atencion'])>0) { ?>
                            <a href="atencion.php?id=<?php echo intval($tarea['id_atencion']); ?>" class="btn btn-white">Volver a la atención</a>
                            <?php } else { ?>
                            <a href="<?php echo tareas_link('tareas.php'); ?>" class="btn btn-white">Volver</a>
                            <?php } ?>
                            <?php if(tarea_puede_editar($tarea)) { ?>
                            <a href="<?php echo tareas_link('editar-tarea.php', array('id'=>$tarea['id'])); ?>" class="btn btn-white">Editar</a>
                            <?php } ?>
                            <?php if(function_exists('tarea_puede_reabrir') && tarea_puede_reabrir($tarea)) { ?>
                            <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#modalReabrirTarea">Reabrir tarea</button>
                            <?php } ?>
                            <?php if(!$tiene_partes) { tarea_botones_avance($tarea); } ?>
                        </div>

                        <?php if(function_exists('tarea_puede_reabrir') && tarea_puede_reabrir($tarea)) { ?>
                        <div class="modal fade" id="modalReabrirTarea" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                                        <h4 class="modal-title">Confirmar reapertura</h4>
                                    </div>
                                    <form method="post" action="<?php echo texto_mostrar($ver_url); ?>" style="margin:0;">
                                        <input type="hidden" name="id_tarea" value="<?php echo $id_tar; ?>">
                                        <input type="hidden" name="reabrir_tarea" value="1">
                                        <input type="hidden" name="accion_tarea" value="reabrir">
                                        <div class="modal-body">
                                            <p>¿Estás seguro de que querés reabrir esta tarea? Su estado volverá a <strong>"En proceso"</strong> y se podrán agregar subtareas y avances de nuevo.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-white" data-dismiss="modal">Cancelar</button>
                                            <button type="submit" class="btn btn-warning">Sí, reabrir tarea</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
