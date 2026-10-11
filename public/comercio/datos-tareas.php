<?php
// ============================================================
// DATOS-TAREAS — Capa de datos para el modulo de Comercio Exterior
// ============================================================
// Este archivo sigue el contrato del equipo:
//   - No redefine funciones que acceso.php ya define.
//   - Define las funciones CONTRATO que los archivos compartidos esperan:
//     tareas_tipos_de_area, tareas_puede_cargar_tipo,
//     tareas_fecha_cierre_obligatoria, tareas_aplicar_carga_tipo,
//     tareas_datos_guardar_nueva, tareas_datos_actualizar,
//     tareas_datos_guardar_extras, tareas_datos_procesar_accion.
// Los datos de tareas, actividades, comentarios, enlaces y avisos
// viven exclusivamente en MySQL (via tareas_repositorio.php).
// Los avisos de bandeja los maneja acceso.php (JSON compartido).
// ============================================================

require_once BASE_PATH.'/includes/tareas_repositorio.php';

// --- CONTEXTO DEL MODULO ---
// El ID del area de Comercio Exterior. Se obtiene de la tabla area.
// Se usa como filtro en TODAS las consultas para aislar este modulo.

function comercio_id_area() {
    // Buscar el ID del area "comercio" en la base
    static $cache = null;
    if ($cache === null) {
        $cache = repo_id_area_por_clave('comercio');
    }
    return $cache;
}

// --- INICIALIZACION DEL CONTEXTO ---
// Mantiene la logica de sesion para el rol efectivo (tareas_rol).
// Ya NO crea arrays en sesion para tareas.

function tareas_iniciar_contexto() {
    // Mapear roles especiales a roles de tarea
    if (function_exists('acceso_area') && acceso_area() == 'todas') {
        $_SESSION['tareas_rol'] = 'coordinador';
    } else if (isset($_SESSION['rol_area']) && $_SESSION['rol_area'] != '') {
        $_SESSION['tareas_rol'] = $_SESSION['rol_area'];
    }
    if (isset($_SESSION['persona_tareas']) && $_SESSION['persona_tareas'] != '') {
        $_SESSION['tareas_persona'] = $_SESSION['persona_tareas'];
    }
    // Normalizar: trabajador -> empleado
    if (isset($_SESSION['tareas_rol']) && $_SESSION['tareas_rol'] == 'trabajador') {
        $_SESSION['tareas_rol'] = 'empleado';
    }
    // coordinador_general y admin se comportan como coordinador
    if (isset($_SESSION['tareas_rol']) && ($_SESSION['tareas_rol'] == 'coordinador_general' || $_SESSION['tareas_rol'] == 'admin')) {
        $_SESSION['tareas_rol'] = 'coordinador';
    }
    if (!isset($_SESSION['tareas_rol'])) {
        $_SESSION['tareas_rol'] = 'coordinador';
    }
    // Asegurar que persona_tareas tenga un valor desde MySQL si no esta en sesion
    if (!isset($_SESSION['tareas_persona']) || $_SESSION['tareas_persona'] == '') {
        $_SESSION['tareas_persona'] = repo_nombre_logueado();
    }
    // Limpieza de cualquier residuo de tareas demo/sesion de la arquitectura previa
    unset($_SESSION['com_tareas_demo']);
    unset($_SESSION['com_tareas_actividad']);
    unset($_SESSION['com_tareas_demo_v']);
}

// --- FUNCIONES DE ACCESO A DATOS ---
// Todas estas funciones ahora consultan MySQL en vez de leer sesiones.

function tareas_rol() {
    return isset($_SESSION['tareas_rol']) ? $_SESSION['tareas_rol'] : 'coordinador';
}

function tareas_persona() {
    return isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : repo_nombre_logueado();
}

// Opciones de tipo de tarea desde la tabla tipo_tarea (DB).
// Reemplaza los hardcoded en tareas_tipos_por_area() de acceso.php para este modulo.
function comercio_tipo_tarea_opciones() {
    global $con;
    if (!$con) return array();
    $id_area = comercio_id_area();
    if ($id_area <= 0) return array();
    $sql = "SELECT nombre FROM tipo_tarea WHERE id_area=? ORDER BY nombre";
    $stmt = @mysqli_prepare($con, $sql);
    if (!$stmt) return array();
    @mysqli_stmt_bind_param($stmt, 'i', $id_area);
    @mysqli_stmt_execute($stmt);
    $result = @mysqli_stmt_get_result($stmt);
    $opciones = array();
    if ($result) {
        while ($row = @mysqli_fetch_assoc($result)) {
            $opciones[] = $row['nombre'];
        }
    }
    @mysqli_stmt_close($stmt);
    return $opciones;
}

/**
 * Carga manualmente un tipo de tarea en MySQL para Comercio Exterior.
 */
function comercio_tipo_cargar($nombre) {
    $id_area = comercio_id_area();
    return repo_tipo_cargar($id_area, $nombre);
}

/**
 * Procesa la accion de 'Cargar tipo' enviada desde el formulario,
 * preserva los datos ingresados hasta el momento y selecciona el nuevo tipo.
 */
function comercio_aplicar_carga_tipo($tarea = array()) {
    if (!isset($_POST['cargar_tipo'])) {
        return $tarea;
    }
    if (!is_array($tarea)) {
        $tarea = array();
    }
    $campos = array('titulo', 'descripcion', 'tipo', 'prioridad', 'fecha_inicio', 'fecha_cierre', 'cliente_nombre');
    foreach ($campos as $campo) {
        if (isset($_POST[$campo])) {
            $tarea[$campo] = $_POST[$campo];
        }
    }
    if (isset($_POST['cliente_id'])) {
        $tarea['cliente_id'] = intval($_POST['cliente_id']);
    }
    if (isset($_POST['responsables']) && is_array($_POST['responsables'])) {
        $tarea['responsables'] = $_POST['responsables'];
    }
    $tipo_nuevo = isset($_POST['tipo_nuevo']) ? trim($_POST['tipo_nuevo']) : '';
    $cargado = comercio_tipo_cargar($tipo_nuevo);
    if ($cargado !== '') {
        $tarea['tipo'] = $cargado;
    }
    return $tarea;
}

// ============================================================
// CONTRATO: Funciones que los archivos compartidos esperan.
// tareas-campos-form.php, acceso.php y las pantallas las llaman.
// ============================================================

// 1) Tipos de tarea del area (array de nombres).
function tareas_tipos_de_area($area)
{
    if ($area !== 'comercio') {
        return array();
    }
    return comercio_tipo_tarea_opciones();
}

// 2) Mostrar boton "Cargar tipo" en el formulario.
function tareas_puede_cargar_tipo()
{
    return true;
}

// 3) Fecha de cierre obligatoria en el formulario.
function tareas_fecha_cierre_obligatoria()
{
    return true;
}

// 4) Procesar "Cargar tipo": guardar el tipo nuevo y preservar el formulario.
function tareas_aplicar_carga_tipo($tarea)
{
    return comercio_aplicar_carga_tipo($tarea);
}

// 5) ALTA DE TAREA. acceso.php la llama desde tareas_guardar_nueva_desde_form().
// Devuelve el nombre del area (ej: "comercio").
function tareas_datos_guardar_nueva($area)
{
    global $con;
    $area_clave = ($area !== '' && $area !== null) ? $area : tareas_area_destino_alta();
    $id_area = repo_id_area_por_clave($area_clave);
    if ($id_area <= 0) return $area_clave;

    // Obtener responsables del POST y convertir nombres a IDs
    $responsables_display = tareas_leer_responsables_post($area_clave);
    $responsables_ids = array();
    foreach ($responsables_display as $nom) {
        $id_emp = repo_nombre_a_id($nom);
        if ($id_emp > 0) $responsables_ids[] = $id_emp;
    }
    // Si no hay responsables validos, usar el empleado logueado
    if (empty($responsables_ids)) {
        $mi_id = repo_empleado_logueado();
        if ($mi_id > 0) $responsables_ids[] = $mi_id;
    }

    // Obtener cliente del POST
    $cliente = tareas_leer_cliente_post();

    // Validar tipo de tarea pertenece al area
    $tipo_nombre = isset($_POST['tipo']) ? $_POST['tipo'] : '';
    $id_tipo = 0;
    if ($tipo_nombre !== '') {
        // Buscar id_tipo por nombre en el area
        $stmt = @mysqli_prepare($con, "SELECT id_tipo FROM tipo_tarea WHERE id_area=? AND nombre=? LIMIT 1");
        if ($stmt) {
            @mysqli_stmt_bind_param($stmt, 'is', $id_area, $tipo_nombre);
            @mysqli_stmt_execute($stmt);
            $result = @mysqli_stmt_get_result($stmt);
            $row = $result ? @mysqli_fetch_assoc($result) : null;
            @mysqli_stmt_close($stmt);
            if ($row) $id_tipo = intval($row['id_tipo']);
        }
    }
    // Si sigue en 0, tomar el primer tipo del area
    if ($id_tipo <= 0) {
        $stmt = @mysqli_prepare($con, "SELECT id_tipo FROM tipo_tarea WHERE id_area=? LIMIT 1");
        if ($stmt) {
            @mysqli_stmt_bind_param($stmt, 'i', $id_area);
            @mysqli_stmt_execute($stmt);
            $result = @mysqli_stmt_get_result($stmt);
            $row = $result ? @mysqli_fetch_assoc($result) : null;
            @mysqli_stmt_close($stmt);
            if ($row) $id_tipo = intval($row['id_tipo']);
        }
    }

    $fecha_cierre = isset($_POST['fecha_cierre']) ? trim($_POST['fecha_cierre']) : '';
    // fecha_cierre es OBLIGATORIA. Si viene vacia, rechazar el formulario.
    // La columna fecha_cierre en MySQL es NOT NULL, no se permite inventar fechas.
    if ($fecha_cierre === '') {
        return $area_clave;
    }

    $datos = array(
        'titulo'       => isset($_POST['titulo']) ? trim($_POST['titulo']) : '',
        'descripcion'  => isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '',
        'id_area'      => $id_area,
        'id_tipo'      => $id_tipo,
        'prioridad'    => isset($_POST['prioridad']) ? $_POST['prioridad'] : 'media',
        'fecha_inicio' => isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-d'),
        'fecha_cierre' => $fecha_cierre,
        'id_cliente'   => isset($cliente['id']) ? intval($cliente['id']) : 0
    );

    $id_empleado_creador = repo_empleado_logueado();
    $nuevo_id = repo_tarea_crear($datos, $responsables_ids, $id_empleado_creador);

    if ($nuevo_id > 0) {
        // Registrar avisos de bandeja para los responsables (JSON compartido de acceso.php)
        $titulo = isset($datos['titulo']) ? $datos['titulo'] : '';
        if (function_exists('tareas_avisos_registrar')) {
            tareas_avisos_registrar($responsables_display, $titulo, $nuevo_id, $area_clave);
        }
    }

    return $area_clave;
}

// 6) EDICION DE TAREA. acceso.php la llama desde tareas_actualizar_desde_form().
// true si se guardo.
function tareas_datos_actualizar($id)
{
    global $con;
    $id = intval($id);
    if ($id <= 0) return false;
    $tarea = repo_tarea_por_id($id);
    if (!$tarea) return false;
    if (!tarea_puede_editar($tarea)) return false;

    $area_clave = (isset($tarea['area_clave']) && $tarea['area_clave']!='') ? $tarea['area_clave'] : acceso_area_pantalla();
    $id_area = (isset($tarea['id_area']) && intval($tarea['id_area'])>0) ? intval($tarea['id_area']) : repo_id_area_por_clave($area_clave);

    // Responsables del POST
    $responsables_display = tareas_leer_responsables_post($area_clave, $tarea);
    $responsables_ids = array();
    foreach ($responsables_display as $nom) {
        $id_emp = repo_nombre_a_id($nom);
        if ($id_emp > 0) $responsables_ids[] = $id_emp;
    }

    // Cliente del POST
    $cliente = tareas_leer_cliente_post();

    // Validar tipo
    $tipo_nombre = isset($_POST['tipo']) ? $_POST['tipo'] : '';
    $id_tipo = 0;
    if ($tipo_nombre !== '') {
        $stmt = @mysqli_prepare($con, "SELECT id_tipo FROM tipo_tarea WHERE id_area=? AND nombre=? LIMIT 1");
        if ($stmt) {
            @mysqli_stmt_bind_param($stmt, 'is', $id_area, $tipo_nombre);
            @mysqli_stmt_execute($stmt);
            $result = @mysqli_stmt_get_result($stmt);
            $row = $result ? @mysqli_fetch_assoc($result) : null;
            @mysqli_stmt_close($stmt);
            if ($row) $id_tipo = intval($row['id_tipo']);
        }
    }

    $fecha_cierre = isset($_POST['fecha_cierre']) ? trim($_POST['fecha_cierre']) : '';
    // fecha_cierre es OBLIGATORIA. Si viene vacia, rechazar la edicion.
    // La columna fecha_cierre en MySQL es NOT NULL, no se permite inventar fechas.
    if ($fecha_cierre === '') {
        return false;
    }

    $datos = array(
        'titulo'       => isset($_POST['titulo']) ? trim($_POST['titulo']) : $tarea['titulo'],
        'descripcion'  => isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '',
        'id_area'      => $id_area,
        'id_tipo'      => $id_tipo > 0 ? $id_tipo : (isset($tarea['id_tipo']) ? $tarea['id_tipo'] : 0),
        'prioridad'    => isset($_POST['prioridad']) ? $_POST['prioridad'] : 'media',
        'fecha_inicio' => isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '',
        'fecha_cierre' => $fecha_cierre,
        'id_cliente'   => isset($cliente['id']) ? intval($cliente['id']) : 0
    );

    return repo_tarea_actualizar($id, $datos, $responsables_ids);
}

// 7) GUARDAR EXTRAS (link o comentario) desde Ver tarea.
// acceso.php la llama desde tareas_guardar_extras_ver(). true si se guardo.
function tareas_datos_guardar_extras($id)
{
    $id_empleado = repo_empleado_logueado();
    $tarea = repo_tarea_por_id($id);
    if (!$tarea) return false;
    if (!tarea_puede_agregar_extras($tarea)) return false;
    // Agregar enlace
    if (isset($_POST['agregar_enlace'])) {
        $url = trim(isset($_POST['enlace_nuevo']) ? $_POST['enlace_nuevo'] : '');
        if ($url !== '') repo_tarea_agregar_enlace($id, $url);
    }
    // Agregar comentario
    if (isset($_POST['agregar_comentario'])) {
        $txt = trim(isset($_POST['comentario_nuevo']) ? $_POST['comentario_nuevo'] : '');
        if ($txt !== '' && $id_empleado > 0) repo_tarea_agregar_comentario($id, $id_empleado, $txt);
    }
    return true;
}

// 8) PROCESAR ACCIONES (iniciar/pausar/reanudar/terminar, y extras).
// acceso.php la llama desde tareas_procesar_accion_si_vino().
// true si hizo algo.
function tareas_datos_procesar_accion()
{
    // Agregar enlace o comentario desde ver-tarea.php
    if (isset($_POST['id_tarea']) && (isset($_POST['agregar_comentario']) || isset($_POST['agregar_enlace']))) {
        return tareas_datos_guardar_extras(intval($_POST['id_tarea']));
    }
    // Cambiar estado (iniciar/pausar/reanudar/terminar)
    if (!isset($_POST['accion_tarea']) || !isset($_POST['id_tarea'])) return false;
    $id = intval($_POST['id_tarea']);
    $accion = $_POST['accion_tarea'];
    $id_empleado = repo_empleado_logueado();
    // Mapear accion a estado
    $estado_map = array(
        'iniciar'  => 'en_proceso',
        'pausar'   => 'pausada',
        'reanudar' => 'en_proceso',
        'terminar' => 'terminada'
    );
    if (!isset($estado_map[$accion])) return false;
    // Verificar permisos: solo el responsable puede cambiar estado
    $tarea = repo_tarea_por_id($id);
    if (!$tarea) return false;
    if (!tarea_puede_cambiar_estado($tarea)) return false;
    // Ejecutar el cambio en MySQL
    return repo_tarea_cambiar_estado($id, $estado_map[$accion], $id_empleado);
}

// --- LISTADO Y CONSULTAS ---

// tareas_lista() — Retorna tareas visibles para el usuario actual
function tareas_lista() {
    $id_area = comercio_id_area();
    $rol = tareas_rol();
    $id_empleado = repo_empleado_logueado();
    return repo_tareas_listar($id_area, array(), $rol, $id_empleado);
}

// tareas_visibles() — Alias de tareas_lista (misma logica ahora)
function tareas_visibles() {
    return tareas_lista();
}

// tareas_filtradas() — Aplica filtros GET sobre las tareas de MySQL
function tareas_filtradas() {
    $id_area = comercio_id_area();
    $rol = tareas_rol();
    $id_empleado = repo_empleado_logueado();
    // Armar array de filtros desde GET
    $filtros = array();
    if (isset($_GET['mes']) && $_GET['mes'] !== '') $filtros['mes'] = $_GET['mes'];
    if (isset($_GET['area']) && $_GET['area'] !== '') $filtros['area'] = $_GET['area'];
    if (isset($_GET['prioridad']) && $_GET['prioridad'] !== '') $filtros['prioridad'] = $_GET['prioridad'];
    if (isset($_GET['tipo']) && $_GET['tipo'] !== '') $filtros['tipo'] = $_GET['tipo'];
    if (isset($_GET['responsable']) && $_GET['responsable'] !== '') $filtros['responsable'] = $_GET['responsable'];
    if (isset($_GET['estado']) && $_GET['estado'] !== '') $filtros['estado'] = $_GET['estado'];
    if (isset($_GET['cliente']) && $_GET['cliente'] !== '') $filtros['cliente'] = $_GET['cliente'];
    return repo_tareas_listar($id_area, $filtros, $rol, $id_empleado);
}

// tareas_contadores() — Contadores para las tarjetas de resumen
function tareas_contadores($lista = null) {
    $id_area = comercio_id_area();
    $rol = tareas_rol();
    $id_empleado = repo_empleado_logueado();
    return repo_tareas_contadores($id_area, $rol, $id_empleado);
}

// tarea_por_id() — Busca una tarea especifica
function tarea_por_id($id) {
    $id = intval($id);
    if ($id <= 0) return null;
    $rol = tareas_rol();
    $id_empleado = repo_empleado_logueado();
    return repo_tarea_por_id($id, $rol, $id_empleado);
}

// tarea_esta_vencida() — Se calcula, nunca se guarda como estado
function tarea_esta_vencida($tarea) {
    if (!is_array($tarea)) return false;
    $estado = isset($tarea['estado']) ? $tarea['estado'] : '';
    if ($estado == 'Terminada') return false;
    $fc = isset($tarea['fecha_cierre']) ? $tarea['fecha_cierre'] : '';
    if ($fc == '') return false;
    return (strtotime($fc) < strtotime(date('Y-m-d')));
}

// tareas_siguiente_id() — Ya no se usa (MySQL genera el ID con auto_increment)
function tareas_siguiente_id() { return 0; }

// --- CONTADORES Y FILTROS AUXILIARES ---

function fecha_mostrar($fecha) {
    if ($fecha == '') return '';
    return date('d/m/Y', strtotime($fecha));
}

function texto_mostrar($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function tareas_link($pagina, $extra = array()) {
    $datos = $extra;
    foreach ($datos as $clave => $valor) {
        if ($valor === '' || $valor === null) unset($datos[$clave]);
    }
    $q = http_build_query($datos);
    return $pagina . ($q !== '' ? ('?' . $q) : '');
}

function tareas_link_filtro($cambios) {
    $actual = array(
        'mes' => isset($_GET['mes']) ? $_GET['mes'] : '',
        'area' => isset($_GET['area']) ? $_GET['area'] : '',
        'prioridad' => isset($_GET['prioridad']) ? $_GET['prioridad'] : '',
        'tipo' => isset($_GET['tipo']) ? $_GET['tipo'] : '',
        'responsable' => isset($_GET['responsable']) ? $_GET['responsable'] : '',
        'estado' => isset($_GET['estado']) ? $_GET['estado'] : '',
        'cliente' => isset($_GET['cliente']) ? $_GET['cliente'] : '',
        'orden' => isset($_GET['orden']) ? $_GET['orden'] : '',
        'cal' => isset($_GET['cal']) ? $_GET['cal'] : ''
    );
    return tareas_link('tareas.php', array_merge($actual, $cambios));
}

function mes_nombre($numero) {
    $nombres = array(1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre');
    $n = intval($numero);
    return isset($nombres[$n]) ? $nombres[$n] : '';
}

function tareas_meses_disponibles() {
    return repo_tareas_meses_disponibles(comercio_id_area());
}

function tareas_valores_unicos($campo) {
    if ($campo == 'responsable') return repo_tareas_responsables_disponibles(comercio_id_area());
    if ($campo == 'tipo') return repo_tareas_tipos_disponibles(comercio_id_area());
    if ($campo == 'prioridad') return array('Urgente', 'Alta', 'Media', 'Baja');
    if ($campo == 'area') return array(acceso_etiqueta_corta_area('comercio'));
    return array();
}

function label_prioridad($prioridad) {
    if ($prioridad == 'Urgente') return 'label-important';
    if ($prioridad == 'Alta' || $prioridad == 'Media') return 'label-warning';
    return 'label-success';
}

function label_estado($estado) {
    if ($estado == 'Terminada') return 'label-success';
    if ($estado == 'En proceso') return 'label-info';
    if ($estado == 'Pendiente' || $estado == 'No iniciada') return 'label-warning';
    if ($estado == 'Pausada') return 'label-inverse';
    return 'label-important';
}

function icono_actividad($tipo) {
    if ($tipo == 'completada') return 'fa-check-circle text-success';
    if ($tipo == 'proceso') return 'fa-clock-o text-warning';
    if ($tipo == 'creada') return 'fa-plus-circle text-info';
    return 'fa-exclamation-circle text-danger';
}

// El calendario ahora consulta MySQL directamente. El parámetro $lista se ignora
// porque la visibilidad se calcula en el repositorio con los parámetros de sesión.
function calendario_dias_con_tareas($anio, $mes, $lista=null) {
    return repo_calendario_dias_con_tareas($anio, $mes, comercio_id_area(), tareas_rol(), repo_empleado_logueado());
}

// --- ACTIVIDADES ---
function actividades_lista() {
    return repo_tarea_actividad(comercio_id_area());
}

function actividades_ejemplo() { return array(); }

// tareas_ejemplo() — Ya no genera datos de prueba (la fuente es MySQL)
function tareas_ejemplo() { return array(); }

// tareas_guardar() — Ya no guarda en sesion (era para compatibilidad).
// El guardado real va por tareas_datos_guardar_nueva / tareas_datos_actualizar.
function tareas_guardar($lista) { /* No-op: los datos viven en MySQL */ }