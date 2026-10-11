<?php
// Campos del formulario de tareas (alta y edicion).
// Primero area (define responsables y tipo), despues el resto.
// No pongas if de un área acá. Cada área responde con funciones en su datos-tareas.php.
if(!isset($modo)) { $modo='nueva'; }
if(!isset($tarea) || !is_array($tarea)) { $tarea=array(); }
// Si falló el guardado, mantener lo que el usuario escribió.
if(isset($_POST['guardar']) || isset($_POST['cargar_tipo']))
{
	foreach(array('titulo','descripcion','tipo','prioridad','fecha_inicio','fecha_cierre','cliente_nombre') as $campo)
	{
		if(isset($_POST[$campo])) { $tarea[$campo]=$_POST[$campo]; }
	}
	if(isset($_POST['cliente_id'])) { $tarea['cliente_id']=intval($_POST['cliente_id']); }
	if(isset($_POST['responsables']) && is_array($_POST['responsables'])) { $tarea['responsables']=$_POST['responsables']; }
}
$area_tipos=($modo=='editar' && isset($tarea['area_clave']) && $tarea['area_clave']!='') ? $tarea['area_clave'] : (isset($_POST['area_crm']) && $_POST['area_crm']!='' ? $_POST['area_crm'] : acceso_area_pantalla());
$tipo_actual=isset($tarea['tipo']) ? $tarea['tipo'] : '';
$prioridad_actual=isset($tarea['prioridad']) ? $tarea['prioridad'] : 'Media';
$responsables_actuales=tarea_lista_responsables($tarea);
if($modo=='nueva' && count($responsables_actuales)==0)
{
	$yo=tareas_persona();
	if($yo!='') { $responsables_actuales=array($yo); }
}
if(count($responsables_actuales)==0) { $responsables_actuales=array(''); }
// En edición, el combo debe ser del área de la tarea (puede ser otra área delegada).
$area_equipo=acceso_area_pantalla();
if($modo=='editar' && isset($tarea['area_clave']) && $tarea['area_clave']!='')
{
	$area_equipo=$tarea['area_clave'];
}
else if(isset($_POST['area_crm']) && $_POST['area_crm']!='')
{
	$area_equipo=$_POST['area_crm'];
}
$nombres_equipo=acceso_nombres_equipo($area_equipo);
// Si el responsable actual no está en el equipo (otra área), lo agregamos para que no desaparezca.
foreach($responsables_actuales as $nom_act)
{
	if($nom_act!='' && !in_array($nom_act, $nombres_equipo, true))
	{
		$nombres_equipo[]=$nom_act;
	}
}
$cliente_id=isset($tarea['cliente_id']) ? intval($tarea['cliente_id']) : (isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0);
$cliente_nombre=isset($tarea['cliente_nombre']) ? $tarea['cliente_nombre'] : (isset($_GET['cliente_nombre']) ? $_GET['cliente_nombre'] : '');
$id_atencion_form=isset($_POST['id_atencion']) ? intval($_POST['id_atencion']) : (isset($_GET['id_atencion']) ? intval($_GET['id_atencion']) : 0);
$msg_form=function_exists('tareas_mensaje_form_tomar') ? tareas_mensaje_form_tomar() : '';
?>
                                <?php if($msg_form!='') { ?>
                                <div class="alert alert-warning"><?php echo texto_mostrar($msg_form); ?></div>
                                <?php } ?>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Área</label>
                                    <div class="col-md-6">
                                        <?php
                                        $areas_alta=($modo=='nueva') ? tareas_areas_alta_permitidas() : array();
                                        if(count($areas_alta)>0) { ?>
                                        <select name="area_crm" id="area_crm" class="form-control">
                                            <?php foreach($areas_alta as $clave=>$titulo) { ?>
                                            <option value="<?php echo texto_mostrar($clave); ?>" <?php if($clave==acceso_area_pantalla()) echo 'selected'; ?>><?php echo texto_mostrar($titulo); ?></option>
                                            <?php } ?>
                                        </select>
                                        <?php } else {
                                        $etq=acceso_etiquetas_area();
                                        if($modo=='editar')
                                        {
                                            $clave_area_form=(isset($tarea['area_clave']) && $tarea['area_clave']!='') ? $tarea['area_clave'] : (isset($_POST['area_crm']) && $_POST['area_crm']!='' ? $_POST['area_crm'] : acceso_area_pantalla());
                                            $nombre_area_form=(isset($tarea['area_nombre']) && $tarea['area_nombre']!='') ? $tarea['area_nombre'] : (isset($etq[$clave_area_form]) ? $etq[$clave_area_form] : $clave_area_form);
                                        }
                                        else
                                        {
                                            $clave_area_form=acceso_area_pantalla();
                                            $nombre_area_form=isset($etq[$clave_area_form]) ? $etq[$clave_area_form] : $clave_area_form;
                                        }
                                        ?>
                                        <input type="hidden" name="area_crm" id="area_crm" value="<?php echo texto_mostrar($clave_area_form); ?>">
                                        <p class="form-control-static"><?php echo texto_mostrar($nombre_area_form); ?></p>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Responsable</label>
                                    <div class="col-md-6">
                                        <?php if(acceso_es_empleado()) { ?>
                                        <?php if($modo=='nueva') { ?>
                                        <input type="hidden" name="responsables[]" value="<?php echo texto_mostrar(tareas_persona()); ?>">
                                        <input type="text" class="form-control" value="<?php echo texto_mostrar(tareas_persona()); ?>" readonly>
                                        <p class="muted">Un empleado solo puede cargar la tarea a su nombre. Si hace falta otro, lo agrega el coordinador.</p>
                                        <?php } else { ?>
                                        <p class="form-control-static"><?php echo texto_mostrar(implode(', ', $responsables_actuales)); ?></p>
                                        <p class="muted">No podés agregar ni quitar responsables. Eso lo gestiona el coordinador.</p>
                                        <?php } ?>
                                        <?php } else { ?>
                                        <div id="caja_responsables">
                                            <?php foreach($responsables_actuales as $i=>$elegido) { ?>
                                            <div class="fila-responsable" style="margin-bottom:6px;">
                                                <select name="responsables[]" class="form-control sel-responsable" style="width:70%; display:inline-block;" <?php if($i==0) echo 'required'; ?>>
                                                    <option value="">Elegir responsable</option>
                                                    <?php foreach($nombres_equipo as $nom) { ?>
                                                    <option value="<?php echo texto_mostrar($nom); ?>" <?php if($nom==$elegido) echo 'selected'; ?>><?php echo texto_mostrar($nom); ?></option>
                                                    <?php } ?>
                                                </select>
                                                <button type="button" class="btn btn-white btn-quitar-resp" onclick="quitarSelectResponsable(this)">Quitar</button>
                                            </div>
                                            <?php } ?>
                                        </div>
                                        <p><button type="button" class="btn btn-white" id="btn_agregar_resp" onclick="agregarSelectResponsable()">Agregar otro responsable</button></p>
                                        <p class="muted">Siempre tiene que haber al menos un responsable. El que ya está elegido no aparece para agregar de nuevo.</p>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Cliente</label>
                                    <div class="col-md-6">
                                        <input type="hidden" name="id_atencion" value="<?php echo intval($id_atencion_form); ?>">
                                        <input type="hidden" name="cliente_id" id="cliente_id" value="<?php echo intval($cliente_id); ?>">
                                        <input type="text" name="cliente_nombre" id="cliente_nombre" class="form-control" value="<?php echo texto_mostrar($cliente_nombre); ?>" readonly placeholder="Todavía no hay un cliente elegido">
                                        <p style="margin-top:8px;">
                                            <input type="text" id="buscar_cliente" class="form-control" placeholder="Nombre, DNI, CUIL/CUIT o localidad…" autocomplete="off">
                                        </p>
                                        <div id="resultados_cliente" style="display:none; border:1px solid #ddd; max-height:180px; overflow:auto; background:#fff; margin-bottom:8px;"></div>
                                        <p><button type="button" class="btn btn-white" id="btn_quitar_cliente">Quitar cliente</button></p>
                                        <p class="muted">Si no va con un cliente, dejalo vacío. Links y comentarios se cargan después, al ver la tarea.</p>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-3 control-label" id="etiqueta_tipo_tarea">Objetivo</label>
                                    <div class="col-md-6">
                                        <select name="tipo" id="tipo_tarea" class="form-control">
                                            <?php
                                            $ops_tipo=tareas_opciones_tipo($area_tipos);
                                            if(count($ops_tipo)==0) { ?>
                                            <option value="">Todavía no hay objetivos cargados</option>
                                            <?php }
                                            foreach($ops_tipo as $op) { ?>
                                            <option value="<?php echo texto_mostrar($op); ?>" <?php if($tipo_actual==$op) echo 'selected'; ?>><?php echo texto_mostrar($op); ?></option>
                                            <?php } ?>
                                        </select>
                                        <?php if(function_exists('tareas_usuario_puede_cargar_objetivo') ? tareas_usuario_puede_cargar_objetivo() : ((!function_exists('tareas_puede_cargar_tipo') || tareas_puede_cargar_tipo()) && !acceso_es_empleado())) { ?>
                                        <p style="margin-top:8px;">
                                            <input type="text" name="tipo_nuevo" class="form-control" placeholder="Nombre del objetivo" style="width:60%; display:inline-block;">
                                            <button type="submit" name="cargar_tipo" value="1" class="btn btn-white" formnovalidate>Cargar objetivo</button>
                                        </p>
                                        <p class="muted">Son los mismos objetivos que en Programas. Si falta uno para esta área, cargalo acá (solo coordinación).</p>
                                        <?php } else if(acceso_es_empleado()) { ?>
                                        <p class="muted">Los objetivos los administra la coordinación (también desde Programas).</p>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Título</label>
                                    <div class="col-md-6">
                                        <input type="text" name="titulo" required class="form-control" value="<?php echo texto_mostrar(isset($tarea['titulo']) ? $tarea['titulo'] : ''); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Descripción</label>
                                    <div class="col-md-6">
                                        <textarea name="descripcion" class="form-control" rows="3"><?php echo texto_mostrar(isset($tarea['descripcion']) ? $tarea['descripcion'] : ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Prioridad</label>
                                    <div class="col-md-6">
                                        <select name="prioridad" class="form-control">
                                            <?php foreach(tareas_opciones_prioridad() as $op) { ?>
                                            <option value="<?php echo texto_mostrar($op); ?>" <?php if($prioridad_actual==$op) echo 'selected'; ?>><?php echo texto_mostrar($op); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Fecha de inicio</label>
                                    <div class="col-md-6">
                                        <?php
                                        $min_fi = date('Y-m-d');
                                        if($modo == 'editar' && !empty($tarea['fecha_inicio'])) {
                                            $fi_actual = substr($tarea['fecha_inicio'], 0, 10);
                                            if($fi_actual < $min_fi) {
                                                $min_fi = $fi_actual;
                                            }
                                        }
                                        ?>
                                        <input type="date" name="fecha_inicio" id="fecha_inicio" min="<?php echo $min_fi; ?>" value="<?php echo texto_mostrar(isset($tarea['fecha_inicio']) && $tarea['fecha_inicio']!='' ? $tarea['fecha_inicio'] : ($modo=='nueva' ? date('Y-m-d') : '')); ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Fecha estimada</label>
                                    <div class="col-md-6">
                                        <?php
                                        $min_fc = date('Y-m-d');
                                        if($modo == 'editar' && !empty($tarea['fecha_cierre'])) {
                                            $fc_actual = substr($tarea['fecha_cierre'], 0, 10);
                                            if($fc_actual < $min_fc) {
                                                $min_fc = $fc_actual;
                                            }
                                        }
                                        ?>
                                        <input type="date" name="fecha_cierre" id="fecha_cierre" min="<?php echo $min_fc; ?>" class="form-control" <?php if(function_exists('tareas_fecha_cierre_obligatoria') && tareas_fecha_cierre_obligatoria()) echo 'required'; ?> value="<?php echo texto_mostrar(isset($tarea['fecha_cierre']) ? $tarea['fecha_cierre'] : ''); ?>">
                                        <p class="muted" style="margin-top:4px;">No puede ser anterior a la fecha actual ni a la fecha de inicio.</p>
                                    </div>
                                </div>
                                <?php if($modo=='editar' && !acceso_es_coord_general() && !acceso_es_super()) { ?>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Motivo del cambio</label>
                                    <div class="col-md-6">
                                        <textarea name="motivo_cambio" class="form-control" rows="2" placeholder="Obligatorio si cambiás fecha, prioridad, título o responsables"><?php echo texto_mostrar(isset($_POST['motivo_cambio']) ? $_POST['motivo_cambio'] : ''); ?></textarea>
                                        <p class="muted" style="margin-top:4px;">Se avisa al coordinador correspondiente con el detalle del cambio.</p>
                                    </div>
                                </div>
                                <?php } ?>
