<?php
// Persistencia exclusiva de Emprendedores; no depende del guardado compartido.
function emp_consulta($sql, $tipos='', $valores=array())
{
    global $con;
    if (!$con) { throw new RuntimeException('No hay conexión con la base.'); }
    $stmt=mysqli_prepare($con, $sql);
    if (!$stmt) { throw new RuntimeException('No se pudo preparar la consulta.'); }
    try {
        if ($tipos!=='' && !mysqli_stmt_bind_param($stmt, $tipos, ...$valores)) {
            throw new RuntimeException('No se pudieron preparar los datos.');
        }
        if (!mysqli_stmt_execute($stmt)) { throw new RuntimeException('No se pudo guardar la operación.'); }
        $resultado=mysqli_stmt_get_result($stmt);
        return $resultado ? mysqli_fetch_all($resultado, MYSQLI_ASSOC) : mysqli_stmt_insert_id($stmt);
    } finally { mysqli_stmt_close($stmt); }
}

function emp_transaccion($operacion)
{
    global $con;
    try {
        if (!$con || !mysqli_begin_transaction($con)) { throw new RuntimeException('Sin conexión con la base de datos.'); }
        $resultado=$operacion();
        if (!mysqli_commit($con)) { throw new RuntimeException('No se pudo confirmar el guardado.'); }
        return $resultado;
    } catch (Throwable $e) {
        if ($con) { mysqli_rollback($con); }
        error_log('Emprendedores: '.$e->getMessage());
        $msg=trim($e->getMessage());
        if($msg==='')
        {
            $msg='No se pudo guardar. Revisá los datos e intentá nuevamente.';
        }
        $GLOBALS['emp_error']=$msg;
        return false;
    }
}

function emp_fecha_valida($fecha)
{
    $d=DateTime::createFromFormat('!Y-m-d', $fecha);
    return $d && $d->format('Y-m-d')===$fecha;
}

function emp_datos_form($actual=null, $area='emprendedores')
{
    $datos=is_array($actual) ? $actual : array();
    foreach (array('titulo','descripcion','tipo','prioridad','fecha_inicio','fecha_cierre') as $campo) {
        $datos[$campo]=isset($_POST[$campo]) && is_string($_POST[$campo]) ? trim($_POST[$campo]) : '';
    }
    $datos['responsables']=tareas_leer_responsables_post($area, $actual);
    $cliente=tareas_leer_cliente_post();
    $datos['cliente_id']=$cliente['id'];
    $datos['cliente_nombre']=$cliente['nombre'];
    return $datos;
}

function emp_validar_form($datos, $area='emprendedores')
{
    if ($datos['titulo']==='' || strlen($datos['titulo'])>200) {
        throw new RuntimeException('El título es obligatorio (máximo 200 caracteres).');
    }
    if (!in_array($datos['prioridad'], array('Urgente','Alta','Media','Baja'), true)) {
        throw new RuntimeException('Elegí una prioridad válida.');
    }
    if (!$datos['responsables']) {
        throw new RuntimeException('Indicá al menos un responsable.');
    }
    if (!emp_fecha_valida($datos['fecha_inicio']) || !emp_fecha_valida($datos['fecha_cierre'])) {
        throw new RuntimeException('Revisá las fechas (formato AAAA-MM-DD).');
    }
    if ($datos['fecha_cierre'] < date('Y-m-d')) {
        throw new RuntimeException('La fecha estimada no puede ser anterior a la fecha actual.');
    }
    if ($datos['fecha_cierre'] < $datos['fecha_inicio']) {
        throw new RuntimeException('La fecha estimada de finalización no puede ser anterior a la fecha de inicio.');
    }
    $tipos=emp_consulta('SELECT tt.id_tipo, tt.id_area FROM tipo_tarea tt INNER JOIN area a ON a.id_area=tt.id_area WHERE a.clave=? AND tt.nombre=?', 'ss', array($area, $datos['tipo']));
    if (!$tipos) { throw new RuntimeException('Elegí un objetivo válido para el área.'); }
    return $tipos[0];
}

function emp_responsables_ids($nombres)
{
    $ids=array();
    foreach ($nombres as $nombre) {
        $encontrado=false;
        foreach (acceso_personal_completo() as $persona) {
            if ($persona['persona']===$nombre) { $ids[]=intval($persona['id']); $encontrado=true; break; }
        }
        if (!$encontrado) { throw new RuntimeException('Responsable inexistente.'); }
    }
    return array_values(array_unique($ids));
}

function emp_guardar_form($datos, $id=0, $area='emprendedores')
{
    $tipo=emp_validar_form($datos, $area);
    $ids=emp_responsables_ids($datos['responsables']);
    $cliente=intval($datos['cliente_id']);
    if ($cliente>0 && !emp_consulta('SELECT id_cliente FROM cliente WHERE id_cliente=?', 'i', array($cliente))) {
        throw new RuntimeException('Cliente inexistente.');
    }
    $valores=array($datos['titulo'], $datos['descripcion'], $tipo['id_tipo'], strtolower($datos['prioridad']), $datos['fecha_inicio'], $datos['fecha_cierre'], $cliente ?: null);
    if ($id>0) {
        $valores[]=$id;
        $valores[]=$tipo['id_area'];
        emp_consulta('UPDATE tarea SET titulo=?, descripcion=?, id_tipo=?, prioridad=?, fecha_inicio=?, fecha_cierre=?, id_cliente=? WHERE id_tarea=? AND id_area=?', 'ssisssiii', $valores);
    } else {
        $valores[]=$tipo['id_area'];
        $valores[]=bin2hex(random_bytes(10));
        $id=emp_consulta("INSERT INTO tarea (titulo, descripcion, id_tipo, prioridad, fecha_inicio, fecha_cierre, id_cliente, id_area, uid, estado, avance) VALUES (?,?,?,?,?,?,?,?,?,'no_iniciada',0)", 'ssisssiis', $valores);
        if ($id<=0) { throw new RuntimeException('No se obtuvo el ID de la tarea.'); }
        emp_actividad($id, 'creada', $datos['titulo']);
    }
    emp_consulta('DELETE FROM tarea_responsable WHERE id_tarea=?', 'i', array($id));
    foreach ($ids as $n=>$empleado) {
        emp_consulta('INSERT INTO tarea_responsable (id_tarea,id_empleado,es_principal) VALUES (?,?,?)', 'iii', array($id,$empleado,$n===0 ? 1 : 0));
    }
    emp_consulta('DELETE FROM tarea_inicio WHERE id_tarea=? AND id_empleado NOT IN ('.implode(',', array_fill(0,count($ids),'?')).')', str_repeat('i',count($ids)+1), array_merge(array($id),$ids));
    return $id;
}

function emp_actividad($id, $tipo, $texto)
{
    $empleado=isset($_SESSION['id_empleado']) ? intval($_SESSION['id_empleado']) : 0;
    emp_consulta('INSERT INTO actividad (id_tarea,id_empleado,tipo,descripcion) VALUES (?,?,?,?)', 'iiss', array($id,$empleado ?: null,$tipo,$texto));
}

function emp_mostrar_error()
{
    if (!empty($GLOBALS['emp_error'])) {
        echo '<div class="alert alert-danger">'.htmlspecialchars($GLOBALS['emp_error'], ENT_QUOTES, 'UTF-8').'</div>';
    }
}
