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
$id_area_equipo=acceso_id_por_clave('area', $area_equipo);
$equipo_responsables=($id_area_equipo>0 && function_exists('repo_empleados_por_area')) ? repo_empleados_por_area($id_area_equipo, true) : array();
$responsables_ids_actuales=array();
if($modo=='editar' && isset($tarea['id_tarea']) && function_exists('repo_tarea_responsable_ids'))
{
	$responsables_ids_actuales=repo_tarea_responsable_ids(intval($tarea['id_tarea']));
}
$responsables_ids_bd=$responsables_ids_actuales;
if($modo=='editar' && count($responsables_ids_actuales)==0)
{
	foreach($responsables_actuales as $nom_act)
	{
		$id_act=function_exists('repo_nombre_a_id') ? repo_nombre_a_id($nom_act) : 0;
		if($id_act>0 && !in_array($id_act, $responsables_ids_actuales, true)) { $responsables_ids_actuales[]=$id_act; }
	}
}
if($modo=='nueva' && isset($_POST['responsables']) && is_array($_POST['responsables']))
{
	foreach($_POST['responsables'] as $valor_post)
	{
		$valor_post=trim((string)$valor_post);
		$id_post=0;
		if(preg_match('/^id:(\\d+)$/', $valor_post, $m)) { $id_post=intval($m[1]); }
		else if(ctype_digit($valor_post)) { $id_post=intval($valor_post); }
		if($id_post>0 && function_exists('repo_responsable_autorizado_area') && repo_responsable_autorizado_area($id_post, $id_area_equipo) && !in_array($id_post, $responsables_ids_actuales, true))
		{
			$responsables_ids_actuales[]=$id_post;
		}
	}
}
if($modo=='editar' && isset($_POST['responsables']) && is_array($_POST['responsables']))
{
	$ids_post=array();
	foreach($_POST['responsables'] as $valor_post)
	{
		$valor_post=trim((string)$valor_post);
		$id_post=0;
		if(preg_match('/^id:(\\d+)$/', $valor_post, $m)) { $id_post=intval($m[1]); }
		else if(ctype_digit($valor_post)) { $id_post=intval($valor_post); }
		else if(function_exists('repo_nombre_a_id')) { $id_post=repo_nombre_a_id($valor_post); }
		if($id_post>0 && (in_array($id_post, $responsables_ids_bd, true) || (function_exists('repo_responsable_autorizado_area') && repo_responsable_autorizado_area($id_post, $id_area_equipo))) && !in_array($id_post, $ids_post, true))
		{
			$ids_post[]=$id_post;
		}
	}
	foreach($responsables_ids_bd as $id_previo)
	{
		if(!function_exists('repo_responsable_autorizado_area') || !repo_responsable_autorizado_area($id_previo, $id_area_equipo))
		{
			if(!in_array($id_previo, $ids_post, true)) { $ids_post[]=$id_previo; }
		}
	}
	if(count($ids_post)>0) { $responsables_ids_actuales=$ids_post; }
}
if($modo=='nueva' && count($responsables_ids_actuales)===0 && count($equipo_responsables)>0)
{
	$id_sesion=isset($_SESSION['id_empleado']) ? intval($_SESSION['id_empleado']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
	$asignado=($id_sesion>0 && function_exists('repo_responsable_autorizado_area') && repo_responsable_autorizado_area($id_sesion, $id_area_equipo));
	if($asignado) { $responsables_ids_actuales[]=$id_sesion; }
	if(!$asignado) { $responsables_ids_actuales[]=intval($equipo_responsables[0]['id']); }
}
// Mantener asignaciones históricas aunque el empleado ya no esté autorizado.
foreach($responsables_ids_actuales as $id_act)
{
	$en_lista=false;
	foreach($equipo_responsables as $persona) { if(intval($persona['id'])===intval($id_act)) { $en_lista=true; break; } }
	if(!$en_lista && function_exists('repo_responsable_etiqueta_area'))
	{
		$etiqueta=repo_responsable_etiqueta_area($id_act, $id_area_equipo);
		if($etiqueta!=='') { $equipo_responsables[]=array('id'=>intval($id_act), 'nombre'=>preg_replace('/\\s+-\\s+.+$/', '', $etiqueta), 'etiqueta'=>$etiqueta, 'historico'=>true); }
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
                                        <p class="muted">Un colaborador solo puede cargar la tarea a su nombre. Si hace falta otro, lo agrega el coordinador.</p>
                                        <?php } else { ?>
                                        <p class="form-control-static"><?php echo texto_mostrar(implode(', ', $responsables_actuales)); ?></p>
                                        <p class="muted">No podés agregar ni quitar responsables. Eso lo gestiona el coordinador.</p>
                                        <?php } ?>
                                        <?php } else { ?>
                                        <div id="caja_responsables">
                                            <?php foreach($responsables_ids_actuales as $i=>$elegido_id) { ?>
                                            <div class="fila-responsable" style="margin-bottom:6px;">
                                                <select name="responsables[]" class="form-control sel-responsable" style="width:70%; display:inline-block;" <?php if($i==0) echo 'required'; ?>>
                                                    <option value="">Elegir responsable</option>
                                                    <?php foreach($equipo_responsables as $persona) { ?>
                                                    <option value="id:<?php echo intval($persona['id']); ?>" <?php if(intval($persona['id'])===intval($elegido_id)) echo 'selected'; ?>><?php echo texto_mostrar($persona['etiqueta']); ?></option>
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
                                        <input type="text" name="cliente_nombre" id="cliente_nombre" class="form-control" value="<?php echo texto_mostrar($cliente_nombre); ?>" readonly placeholder="Seleccioná un cliente" style="<?php echo $cliente_id>0 ? 'border-color:#5cb85c;background:#f4fbf4;' : ''; ?>">
                                        <p style="margin-top:8px;">
                                            <input type="search" id="buscar_cliente" class="form-control" placeholder="Buscar por nombre, DNI o CUIT/CUIL" autocomplete="off">
                                        </p>
                                        <div id="resultados_cliente" style="display:none; border:1px solid #ddd; border-radius:4px; max-height:180px; overflow:auto; background:#fff; margin-bottom:8px;"></div>
                                        <p><button type="button" class="btn btn-white" id="btn_quitar_cliente" <?php if($cliente_id<=0) echo 'disabled'; ?>>Limpiar</button></p>
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
                                <div class="form-group <?php if(!empty($error_fecha_inicio)) echo 'has-error'; ?>">
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
										$fecha_inicio_valor=isset($tarea['fecha_inicio']) && $tarea['fecha_inicio']!='' ? $tarea['fecha_inicio'] : ($modo=='nueva' ? date('Y-m-d') : '');
										if($modo=='nueva' && !empty($fecha_inicio_obligatoria) && isset($_POST['guardar']))
										{
										$fecha_inicio_valor=array_key_exists('fecha_inicio', $_POST) ? (string)$_POST['fecha_inicio'] : '';
										}
                                        ?>
                                        <input type="date" name="fecha_inicio" id="fecha_inicio" min="<?php echo $min_fi; ?>" value="<?php echo texto_mostrar($fecha_inicio_valor); ?>" class="form-control" <?php if(!empty($fecha_inicio_obligatoria)) echo 'required aria-required="true"'; ?> <?php if(!empty($error_fecha_inicio)) echo 'aria-invalid="true"'; ?>>
                                        <?php if(!empty($error_fecha_inicio)) { ?><span class="help-block"><?php echo texto_mostrar($error_fecha_inicio); ?></span><?php } ?>
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
