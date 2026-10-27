<?php
require_once __DIR__ . '/../_init.php';
require_once BASE_PATH . '/includes/sistemas_dominios.php';

crm_proteger_staff(null, true);
$dominios_area_activa=function_exists('acceso_area_activa') ? acceso_area_activa() : '';
if(!function_exists('acceso_puede_ver_dominios') || !acceso_puede_ver_dominios($dominios_area_activa))
{
	http_response_code(403);
	exit('El módulo de Dominios está deshabilitado para esta área.');
}
$csrf_token = crm_tarea_extra_csrf_token();
$menu_activo = 'dominios';
$etiquetas_area_dominios=acceso_etiquetas_area();
$nombre_area=isset($etiquetas_area_dominios[$dominios_area_activa]) ? $etiquetas_area_dominios[$dominios_area_activa] : $dominios_area_activa;
$puede_gestionar = ($dominios_area_activa==='sistemas' && acceso_puede_dominios() && acceso_puede_gestionar('sistemas'));

// -------------------------------------------------------------
// AJAX ENDPOINT: DOMAIN DETAILS & RENEWAL HISTORY (READ-ONLY)
// -------------------------------------------------------------
if (isset($_GET['ajax_ver']) && $_GET['ajax_ver'] == '1')
{
	if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
	$id_dom = isset($_GET['id']) ? intval($_GET['id']) : 0;
	$info = crm_dominio_traer($id_dom);
	if (!$info)
	{
		echo json_encode(array('ok' => false, 'error' => 'Dominio no encontrado'));
		exit;
	}
	echo json_encode(array('ok' => true, 'dominio' => $info));
	exit;
}

// -------------------------------------------------------------
// AJAX ENDPOINT: MAIN CARDS LIST & METRICS
// -------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] == '1')
{
	if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');

	$filtros = array(
		'q' => isset($_GET['q']) ? trim($_GET['q']) : '',
		'fecha' => isset($_GET['fecha']) ? trim($_GET['fecha']) : '',
		'estado' => isset($_GET['estado']) ? trim($_GET['estado']) : 'todos',
		'anio' => isset($_GET['anio']) ? trim($_GET['anio']) : 'todos',
		'proveedor' => isset($_GET['proveedor']) ? trim($_GET['proveedor']) : 'todos',
		'responsable' => isset($_GET['responsable']) ? trim($_GET['responsable']) : 'todos'
	);

	$dominios = crm_dominios_listar($filtros);
	$stats = crm_dominios_estadisticas();
	
	// Count domains expiring in <= 30 days for alert banner
	$alert_count = 0;
	foreach ($dominios as $d)
	{
		if ($d['dias_restantes'] <= 30)
		{
			$alert_count++;
		}
	}

	ob_start();
	if (empty($dominios))
	{
		?>
		<div class="grid simple" style="margin-top:10px;">
			<div class="grid-body text-center" style="padding:25px;">
				<p class="text-muted" style="margin:0; font-weight:600; font-size:14px;">No se encontraron dominios con los filtros aplicados.</p>
			</div>
		</div>
		<?php
	}
	else
	{
		foreach ($dominios as $dom)
		{
			$st = $dom['estado_info'];
			$dias = $dom['dias_restantes'];
			$progreso = $dom['estado_info']['progreso'];
			$badge_cls = $st['badge'];

			$bar_color = '#0088cc';
			if ($st['clave'] === 'vencido') $bar_color = '#f35958';
			else if ($st['clave'] === 'proximo') $bar_color = '#f8a900';
			else $bar_color = '#0aa699';

			$resp_nombre = !empty(trim((string)$dom['responsable'])) ? trim($dom['responsable']) : 'Sin asignar';
			$prov_nombre = !empty(trim((string)$dom['proveedor'])) ? trim($dom['proveedor']) : 'Sin asignar';
			?>
			<div class="grid simple dom-card-simple" style="margin-bottom:12px;">
				<div class="grid-body" style="padding:16px 20px;">
					<div class="row" style="display:flex; align-items:center; flex-wrap:wrap;">
						
						<!-- Domain Info -->
						<div class="col-md-5 col-sm-6">
							<h4 style="margin:0 0 6px 0; font-weight:700; font-size:16px; color:#2c3e50;">
								<?php echo htmlspecialchars($dom['nombre']); ?>
								<?php if (!empty($dom['url'])) { ?>
									<a href="<?php echo htmlspecialchars($dom['url']); ?>" target="_blank" class="small text-info" style="margin-left:6px;" title="Visitar sitio web"><i class="fa fa-external-link"></i></a>
								<?php } ?>
							</h4>
							<div style="font-size:13px; color:#505458;">
								<span><i class="fa fa-server text-muted"></i> Proveedor: <strong><?php echo htmlspecialchars($prov_nombre); ?></strong></span>
								<span style="margin:0 8px; color:#cbd5e1;">|</span>
								<span><i class="fa fa-user text-muted"></i> Responsable: <strong><?php echo htmlspecialchars($resp_nombre); ?></strong></span>
							</div>
						</div>

						<!-- Dates & Progress Bar -->
						<div class="col-md-4 col-sm-6" style="border-left:1px solid #f1f5f9; border-right:1px solid #f1f5f9; padding:0 15px;">
							<div style="font-size:13px; color:#505458; margin-bottom:4px;">
								<i class="fa fa-calendar text-muted"></i> Vencimiento: <strong><?php echo date('d/m/Y', strtotime($dom['fecha_vencimiento'])); ?></strong>
							</div>
							<div style="font-size:13px; color:#505458;">
								<i class="fa fa-clock-o text-muted"></i> 
								<?php if ($dias < 0) { ?>
									<span class="text-error bold"><?php echo abs($dias); ?> días vencido</span>
								<?php } else if ($dias === 0) { ?>
									<span class="text-warning bold">Vence hoy</span>
								<?php } else { ?>
									<span><strong><?php echo $dias; ?></strong> días restantes</span>
								<?php } ?>
							</div>
							<div class="progress progress-small" style="height:6px; margin-top:6px; margin-bottom:0; background:#e5e9ec; border-radius:3px;">
								<div class="progress-bar" style="width:<?php echo $progreso; ?>%; background-color:<?php echo $bar_color; ?>; border-radius:3px;"></div>
							</div>
						</div>

						<!-- Status Badge & Actions -->
						<div class="col-md-3 col-sm-12 text-right" style="margin-top:4px;">
							<span class="label <?php echo $badge_cls; ?>" style="margin-bottom:8px; display:inline-block; font-size:11px; padding:4px 10px; font-weight:600;"><?php echo htmlspecialchars($st['etiqueta']); ?></span>
							<div>
								<button type="button" class="btn btn-white btn-xs" onclick="crmVerDominio(<?php echo $dom['id_dominio']; ?>)" title="Ver detalles y renovaciones">
									<i class="fa fa-eye"></i> Ver
								</button>
								<?php if ($puede_gestionar) { ?>
									<button type="button" class="btn btn-primary btn-xs" onclick="crmEditarDominio(<?php echo $dom['id_dominio']; ?>)" title="Editar datos">
										<i class="fa fa-pencil"></i> Editar
									</button>
									<button type="button" class="btn btn-info btn-xs" onclick="crmRenovarDominioModal(<?php echo $dom['id_dominio']; ?>)" title="Renovar dominio">
										<i class="fa fa-refresh"></i> Renovar
									</button>
									<button type="button" class="btn btn-danger btn-xs" onclick="crmBajaDominio(<?php echo $dom['id_dominio']; ?>)" title="Dar de baja">
										<i class="fa fa-trash-o"></i>
									</button>
								<?php } ?>
							</div>
						</div>

					</div>
				</div>
			</div>
			<?php
		}
	}
	$html_cards = ob_get_clean();

	echo json_encode(array(
		'ok' => true,
		'cards_html' => $html_cards,
		'stats' => $stats,
		'alert_count' => $alert_count
	));
	exit;
}

// -------------------------------------------------------------
// AJAX ENDPOINT: SIDEBAR COMPACT CALENDAR & UPCOMING LIST
// -------------------------------------------------------------
if (isset($_GET['ajax_cal']) && $_GET['ajax_cal'] == '1')
{
	if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');

	$cal_anio = isset($_GET['cal_anio']) ? intval($_GET['cal_anio']) : intval(date('Y'));
	$cal_mes = isset($_GET['cal_mes']) ? intval($_GET['cal_mes']) : intval(date('m'));
	$dia_sel = isset($_GET['dia_sel']) ? trim($_GET['dia_sel']) : '';

	if ($cal_anio <= 0) $cal_anio = intval(date('Y'));
	if ($cal_mes <= 0 || $cal_mes > 12) $cal_mes = intval(date('m'));

	$eventos_mes = crm_dominios_calendario_eventos($cal_anio, $cal_mes, array());
	$proximos = crm_dominios_proximos_vencimientos(6);

	// Render Calendar Grid HTML
	ob_start();
	$timestamp_base = strtotime(sprintf('%04d-%02d-01', $cal_anio, $cal_mes));
	$primer_dia_semana = intval(date('N', $timestamp_base));
	$dias_del_mes = intval(date('t', $timestamp_base));
	$hoy = date('Y-m-d');
	?>
	<table class="cal-compact-grid-mini">
		<thead>
			<tr><th>Lu</th><th>Ma</th><th>Mi</th><th>Ju</th><th>Vi</th><th>Sá</th><th>Do</th></tr>
		</thead>
		<tbody>
		<?php
		$dia_contador = 1;
		$celda_contador = 1;

		echo '<tr>';
		while ($celda_contador < $primer_dia_semana)
		{
			echo '<td class="cal-mini-empty"></td>';
			$celda_contador++;
		}

		while ($dia_contador <= $dias_del_mes)
		{
			if ($celda_contador === 1 && $dia_contador > 1) echo '<tr>';

			$fecha_celda = sprintf('%04d-%02d-%02d', $cal_anio, $cal_mes, $dia_contador);
			$es_hoy = ($fecha_celda === $hoy);
			$es_sel = ($fecha_celda === $dia_sel);

			$has_events = isset($eventos_mes[$dia_contador]) && count($eventos_mes[$dia_contador]) > 0;
			$ev_list = $has_events ? $eventos_mes[$dia_contador] : array();

			// Determine dot color indicators (red, orange, green)
			$dot_colors = array();
			if ($has_events)
			{
				foreach ($ev_list as $ev)
				{
					$st_c = $ev['estado_clave'];
					if ($st_c === 'vencido') $dot_colors['red'] = true;
					else if ($st_c === 'proximo') $dot_colors['orange'] = true;
					else $dot_colors['green'] = true;
				}
			}

			$cls = array('cal-mini-day');
			if ($es_hoy) $cls[] = 'cal-mini-hoy';
			if ($es_sel) $cls[] = 'cal-mini-sel';
			if ($has_events) $cls[] = 'cal-mini-has-events';

			echo '<td class="' . implode(' ', $cls) . '" onclick="crmSeleccionarDiaMini(\'' . $fecha_celda . '\')" title="' . ($has_events ? count($ev_list) . ' vencimiento(s)' : '') . '">';
			echo '<span class="cal-mini-num">' . $dia_contador . '</span>';
			if ($has_events)
			{
				echo '<div class="cal-mini-dots">';
				if (isset($dot_colors['red'])) echo '<span class="dot dot-red"></span>';
				if (isset($dot_colors['orange'])) echo '<span class="dot dot-orange"></span>';
				if (isset($dot_colors['green'])) echo '<span class="dot dot-green"></span>';
				echo '</div>';
			}
			echo '</td>';

			if ($celda_contador === 7)
			{
				echo '</tr>';
				$celda_contador = 0;
			}
			$dia_contador++;
			$celda_contador++;
		}

		if ($celda_contador > 1)
		{
			while ($celda_contador <= 7)
			{
				echo '<td class="cal-mini-empty"></td>';
				$celda_contador++;
			}
			echo '</tr>';
		}
		?>
		</tbody>
	</table>
	<?php
	$grid_html = ob_get_clean();

	// Render Upcoming List HTML
	ob_start();
	if (empty($proximos))
	{
		echo '<div class="text-muted small" style="padding:10px 0; text-align:center;">No hay vencimientos próximos.</div>';
	}
	else
	{
		foreach ($proximos as $p)
		{
			$st_p = $p['estado_info'];
			$icon_cls = 'dot-blue';
			if ($st_p['clave'] === 'vencido') $icon_cls = 'dot-red';
			else if ($st_p['clave'] === 'proximo') $icon_cls = 'dot-orange';
			else $icon_cls = 'dot-green';

			$dia_str = date('d M', strtotime($p['fecha_vencimiento']));
			?>
			<div class="proximo-item" onclick="crmVerDominio(<?php echo $p['id_dominio']; ?>)">
				<div class="proximo-left">
					<span class="proximo-dot <?php echo $icon_cls; ?>"></span>
					<div class="proximo-info">
						<strong><?php echo htmlspecialchars($p['nombre']); ?></strong>
						<span class="text-muted small" style="display:block;"><?php echo htmlspecialchars(!empty($p['proveedor']) ? $p['proveedor'] : 'Dominio'); ?></span>
					</div>
				</div>
				<div class="proximo-right">
					<span class="badge <?php echo $st_p['badge']; ?>"><?php echo $dia_str; ?></span>
				</div>
			</div>
			<?php
		}
	}
	$proximos_html = ob_get_clean();

	$meses_nombres = array(1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre');
	$cal_mes_titulo = $meses_nombres[$cal_mes] . ' ' . $cal_anio;

	echo json_encode(array(
		'ok' => true,
		'grid_html' => $grid_html,
		'proximos_html' => $proximos_html,
		'cal_mes_titulo' => $cal_mes_titulo,
		'cal_anio' => $cal_anio,
		'cal_mes' => $cal_mes
	));
	exit;
}

// -------------------------------------------------------------
// POST ACTIONS: GUARDAR, RENOVAR, BAJA
// -------------------------------------------------------------
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']))
{
	if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');

	if (!$puede_gestionar)
	{
		echo json_encode(array('ok' => false, 'error' => 'No tenés permisos para realizar esta acción.'));
		exit;
	}

	$accion = trim($_POST['accion']);

	if ($accion === 'guardar')
	{
		$id_dom = isset($_POST['id_dominio']) ? intval($_POST['id_dominio']) : 0;
		$datos = array(
			'nombre' => isset($_POST['nombre']) ? $_POST['nombre'] : '',
			'url' => isset($_POST['url']) ? $_POST['url'] : '',
			'proveedor' => isset($_POST['proveedor']) ? $_POST['proveedor'] : '',
			'responsable' => isset($_POST['responsable']) ? $_POST['responsable'] : '',
			'fecha_inicio' => isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '',
			'fecha_vencimiento' => isset($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : '',
			'notas' => isset($_POST['notas']) ? $_POST['notas'] : ''
		);

		$err = crm_dominio_guardar($datos, $id_dom);
		if ($err !== '')
		{
			echo json_encode(array('ok' => false, 'error' => $err));
		}
		else
		{
			echo json_encode(array('ok' => true, 'mensaje' => $id_dom > 0 ? 'Dominio actualizado correctamente.' : 'Dominio registrado correctamente.'));
		}
		exit;
	}

	if ($accion === 'renovar')
	{
		$id_dom = isset($_POST['id_dominio']) ? intval($_POST['id_dominio']) : 0;
		$fecha_renov = isset($_POST['fecha_renovacion']) ? $_POST['fecha_renovacion'] : '';
		$fecha_nueva = isset($_POST['fecha_vencimiento_nueva']) ? $_POST['fecha_vencimiento_nueva'] : '';
		$notas = isset($_POST['notas_renovacion']) ? $_POST['notas_renovacion'] : '';

		$err = crm_dominio_renovar($id_dom, $fecha_renov, $fecha_nueva, $notas);
		if ($err !== '')
		{
			echo json_encode(array('ok' => false, 'error' => $err));
		}
		else
		{
			echo json_encode(array('ok' => true, 'mensaje' => 'Dominio renovado con éxito.'));
		}
		exit;
	}

	if ($accion === 'baja')
	{
		$id_dom = isset($_POST['id_dominio']) ? intval($_POST['id_dominio']) : 0;
		$err = crm_dominio_baja($id_dom);
		if ($err !== '')
		{
			echo json_encode(array('ok' => false, 'error' => $err));
		}
		else
		{
			echo json_encode(array('ok' => true, 'mensaje' => 'Dominio dado de baja correctamente.'));
		}
		exit;
	}

	echo json_encode(array('ok' => false, 'error' => 'Acción no válida.'));
	exit;
}

// Fetch filter options for selects
$opciones_filtros = crm_dominios_opciones_filtros();
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Sistemas - Dominios y Vencimientos</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<meta content="" name="description" />
<meta content="" name="author" />
<link href="../assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/animate.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/jquery-scrollbar/jquery.scrollbar.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>

<style>
/* Custom Layout Styles for ADEMI CRM Domains Module */

/* Filters Bar */
.dom-filters-box {
	background: #ffffff;
	border: 1px solid #e5e9ec;
	border-radius: 4px;
	padding: 15px;
	margin-bottom: 15px;
}
.dom-filters-row {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 12px;
	margin-bottom: 12px;
}
.dom-filter-group label {
	display: block;
	font-size: 11px;
	font-weight: 600;
	color: #505458;
	text-transform: uppercase;
	margin-bottom: 4px;
}
.dom-filter-group select {
	width: 100%;
	border-radius: 3px;
	border: 1px solid #dce1e5;
	padding: 6px 10px;
	font-size: 12px;
	color: #2c3e50;
	background-color: #fff;
	height: 34px;
}
.dom-search-bar {
	display: flex;
	gap: 10px;
	align-items: center;
}
.dom-search-input-wrap {
	flex: 1;
}
.dom-search-input-wrap input {
	padding: 6px 12px !important;
	width: 100%;
	border-radius: 3px;
	border: 1px solid #dce1e5;
	height: 34px;
	font-size: 13px;
	box-sizing: border-box;
}

/* Compact Calendar Widget */
.cal-compact-grid-mini {
	width: 100%;
	border-collapse: collapse;
	table-layout: fixed;
}
.cal-compact-grid-mini th {
	text-align: center;
	font-size: 12px;
	color: #64748b;
	padding: 8px 0;
	font-weight: 700;
	text-transform: uppercase;
}
.cal-compact-grid-mini td {
	text-align: center;
	padding: 4px 0;
	height: 40px;
	vertical-align: top;
	border-radius: 6px;
	cursor: pointer;
	position: relative;
	transition: background-color 0.15s ease;
}
.cal-compact-grid-mini td:hover {
	background-color: #f1f5f9;
}
.cal-mini-num {
	font-size: 13px;
	color: #334155;
	font-weight: 600;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 24px;
	height: 24px;
	border-radius: 50%;
}
.cal-mini-hoy .cal-mini-num {
	background-color: #0088cc;
	color: #ffffff;
	font-weight: 700;
	box-shadow: 0 2px 4px rgba(0, 136, 204, 0.25);
}
.cal-mini-sel {
	background-color: #d1fae5 !important;
	border: 1px solid #10b981 !important;
}
.cal-mini-dots {
	display: flex;
	justify-content: center;
	gap: 3px;
	margin-top: 2px;
}
.dot {
	width: 6px;
	height: 6px;
	border-radius: 50%;
	display: inline-block;
}
.dot-red { background-color: #f35958; }
.dot-orange { background-color: #f8a900; }
.dot-green { background-color: #0aa699; }
.dot-blue { background-color: #0088cc; }

.cal-legend {
	display: flex;
	justify-content: space-around;
	margin-top: 14px;
	padding-top: 12px;
	border-top: 1px solid #f1f5f9;
	font-size: 12px;
	color: #64748b;
	font-weight: 500;
}

/* Upcoming Expirations List */
.proximo-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 8px 0;
	border-bottom: 1px solid #f1f5f9;
	cursor: pointer;
}
.proximo-item:last-child {
	border-bottom: none;
}
.proximo-item:hover {
	background-color: #f8fafc;
}
.proximo-left {
	display: flex;
	align-items: center;
	gap: 10px;
}
.proximo-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
}
.proximo-info strong {
	display: block;
	font-size: 12px;
	color: #2c3e50;
}

/* Ver Dominio Modal Styling */
.dom-detail-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 6px;
	padding: 16px 18px;
	margin-bottom: 15px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}
.dom-detail-section-title {
	margin: 0 0 12px 0;
	font-size: 13px;
	font-weight: 700;
	color: #1e293b;
	border-bottom: 1px solid #f1f5f9;
	padding-bottom: 8px;
	display: flex;
	align-items: center;
	gap: 8px;
}
.dom-detail-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 9px 0;
	border-bottom: 1px solid #f8fafc;
	font-size: 13px;
}
.dom-detail-item:last-child {
	border-bottom: none;
	padding-bottom: 0;
}
.dom-detail-label {
	color: #64748b;
	font-weight: 500;
	display: inline-flex;
	align-items: center;
	gap: 8px;
}
.dom-detail-value {
	color: #0f172a;
	font-weight: 600;
	text-align: right;
	max-width: 60%;
	word-break: break-word;
}

/* Timeline in Ver modal */
.timeline-renovaciones {
	list-style: none;
	padding: 0;
	margin: 12px 0 0 0;
	position: relative;
}
.timeline-renovaciones:before {
	content: '';
	position: absolute;
	top: 0;
	bottom: 0;
	left: 14px;
	width: 2px;
	background: #e2e8f0;
}
.timeline-item {
	position: relative;
	padding-left: 36px;
	margin-bottom: 12px;
}
.timeline-item:last-child {
	margin-bottom: 0;
}
.timeline-icon {
	position: absolute;
	left: 4px;
	top: 2px;
	width: 22px;
	height: 22px;
	border-radius: 50%;
	background: #0088cc;
	color: #fff;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 11px;
}
.timeline-content {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 4px;
	padding: 10px 14px;
	font-size: 12px;
}

/* Page-level spacing only; keep domain cards and detail/form layouts unchanged. */
body.crm-dominios-page .page-title.crm-compact-heading {
	margin-bottom: 6px !important;
}
body.crm-dominios-page .page-title.crm-compact-heading + p {
	margin: 0 0 12px;
	line-height: 1.45;
}
body.crm-dominios-page .dominios-metrics-row > .spacing-bottom {
	margin-bottom: 12px;
}
body.crm-dominios-page .dominios-main-layout .dom-filters-box {
	padding: 12px;
	margin-bottom: 12px;
}
body.crm-dominios-page .dominios-main-layout .dom-filters-row {
	gap: 10px;
	margin-bottom: 8px;
}
body.crm-dominios-page .dominios-main-layout .cal-legend {
	margin-top: 10px;
	padding-top: 10px;
}
body.crm-dominios-page .dominios-main-layout .dom-calendar-panel,
body.crm-dominios-page .dominios-main-layout .dom-upcoming-panel {
	margin-bottom: 12px;
}
@media (max-width: 767px) {
	body.crm-dominios-page .dominios-metrics-row > .spacing-bottom {
		margin-bottom: 10px;
	}
	body.crm-dominios-page .dominios-main-layout > [class*="col-"] + [class*="col-"] {
		margin-top: 8px;
	}
	body.crm-dominios-page .dominios-main-layout .dom-filters-box {
		padding: 10px;
	}
}
</style>
</head>
<body class="crm-dominios-page">

<?php crm_header_staff(); ?>

<div class="page-container row">
	<?php crm_menu_staff(); ?>
	<div class="clearfix"></div>
	</div>
</div>

<div class="page-content">
	<div class="clearfix"></div>
	<div class="content">
		
		<!-- Breadcrumbs -->
		<ul class="breadcrumb">
			<li><p>ESTÁS AQUÍ</p></li>
			<li><a href="<?php echo url_crm('sistemas/tareas.php'); ?>"><?php echo htmlspecialchars($nombre_area); ?></a></li>
			<li><a href="#" class="active">Dominios</a></li>
		</ul>

		<!-- Sub-navigation section menu for Sistemas -->
		<?php include("menu-seccion.php"); ?>

		<!-- Page Header Title & Action Button -->
		<div class="page-title crm-compact-heading" style="margin-bottom:15px;">
			<h3>Dominios y Vencimientos</h3>
			<?php if ($puede_gestionar) { ?>
				<button type="button" class="btn btn-primary btn-cons pull-right" onclick="crmNuevoDominioModal()">
					<i class="fa fa-plus"></i> Nuevo dominio
				</button>
			<?php } ?>
		</div>
		<p class="muted">Gestión de dominios, proveedores, renovaciones e historial de vencimientos de Sistemas.</p>

		<!-- Metric Tiles Matching ADEMI Tareas Palette -->
		<div class="row dominios-metrics-row">
			<div class="col-md-3 col-sm-6 spacing-bottom">
				<div class="tiles blue added-margin">
					<div class="tiles-body">
						<div class="tiles-title">Dominios activos</div>
						<div class="heading" id="statTotal">-</div>
					</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6 spacing-bottom">
				<div class="tiles warning added-margin">
					<div class="tiles-body">
						<div class="tiles-title">Por vencer</div>
						<div class="heading" id="statProximo">-</div>
					</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6 spacing-bottom">
				<div class="tiles red added-margin">
					<div class="tiles-body">
						<div class="tiles-title">Vencen este mes</div>
						<div class="heading" id="statVencenMes">-</div>
					</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6 spacing-bottom">
				<div class="tiles purple added-margin">
					<div class="tiles-body">
						<div class="tiles-title">Vencidos</div>
						<div class="heading" id="statVencido">-</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Main Page Grid Layout: 70% Left, 30% Right -->
		<div class="row dominios-main-layout">
			
			<!-- LEFT COLUMN (~70%): Filters, Day Filter Pill, Cards -->
			<div class="col-md-8 col-sm-12">
				
				<!-- Filters Box -->
				<div class="dom-filters-box">
					<div class="dom-filters-row">
						<div class="dom-filter-group">
							<label>Año</label>
							<select id="filtro_anio" onchange="crmFiltrarDominios()">
								<option value="todos">Todos</option>
								<?php foreach ($opciones_filtros['anios'] as $a) { ?>
									<option value="<?php echo $a; ?>"><?php echo $a; ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="dom-filter-group">
							<label>Proveedor / Registrador</label>
							<select id="filtro_proveedor" onchange="crmFiltrarDominios()">
								<option value="todos">Todos</option>
								<?php foreach ($opciones_filtros['proveedores'] as $p) { ?>
									<option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="dom-filter-group">
							<label>Estado</label>
							<select id="filtro_estado" onchange="crmFiltrarDominios()">
								<option value="todos">Todos</option>
								<option value="vigente">Vencimiento lejano</option>
								<option value="proximo">Por vencer</option>
								<option value="vencido">Vencido</option>
							</select>
						</div>
						<div class="dom-filter-group">
							<label>Responsable</label>
							<select id="filtro_responsable" onchange="crmFiltrarDominios()">
								<option value="todos">Todos</option>
								<?php foreach ($opciones_filtros['responsables'] as $r) { ?>
									<option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($r); ?></option>
								<?php } ?>
							</select>
						</div>
					</div>
					<div class="dom-search-bar">
						<div class="dom-search-input-wrap">
							<input type="text" id="filtro_q" placeholder="Buscar por dominio, proveedor o responsable..." oninput="crmDebounceFiltrar()">
						</div>
						<button type="button" id="btnLimpiarDominios" class="btn btn-white" style="display:none;" onclick="crmLimpiarFiltros()">
							<i class="fa fa-times"></i> Limpiar
						</button>
					</div>
				</div>

				<!-- Day Selection Filter Pill -->
				<div id="boxFiltroDia" class="alert alert-info" style="display:none; padding:8px 15px; margin-bottom:15px; align-items:center; justify-content:space-between;">
					<span><i class="fa fa-calendar"></i> Filtro por fecha de vencimiento: <strong id="lblFechaSeleccionada">-</strong></span>
					<button type="button" class="btn btn-xs btn-white pull-right" onclick="crmQuitarFiltroDia()"><i class="fa fa-times"></i> Ver todos los días</button>
				</div>

				<!-- Alert Banner for Expirations -->
				<div id="alertBannerDominios" class="alert alert-warning" style="display:none; margin-bottom:15px;">
					<i class="fa fa-exclamation-triangle"></i> <span id="alertBannerText">Hay dominios próximos a vencer en los próximos 30 días.</span>
				</div>

				<!-- Cards List Container -->
				<div id="contenedorTarjetasDominios">
					<div style="text-align:center; padding:30px;"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
				</div>

			</div>

			<!-- RIGHT COLUMN (~30%): Compact Mini Calendar & Next Expirations -->
			<div class="col-md-4 col-sm-12">
				
				<!-- Compact Calendar Box -->
				<div class="grid simple dom-calendar-panel">
					<div class="grid-title" style="padding:14px 16px; border-bottom:1px solid #e5e9ec; background:#fafbfc;">
						<h4 style="margin:0 0 10px 0; font-size:14px; font-weight:700; color:#1e293b; display:flex; align-items:center; gap:8px;">
							<i class="fa fa-calendar text-info" style="font-size:15px;"></i> Calendario de vencimientos
						</h4>
						<div style="display:flex; gap:6px; align-items:center; width:100%; box-sizing:border-box;">
							<button type="button" class="btn btn-white" onclick="crmCalMesPrev()" title="Mes anterior" style="height:32px; width:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; border:1px solid #dce1e5; border-radius:4px; flex-shrink:0;">
								<i class="fa fa-chevron-left"></i>
							</button>
							<select id="cal_mes_sel" onchange="crmCalCambiarFecha()" style="height:32px; flex:1; min-width:0; padding:4px 8px; font-size:12px; font-weight:600; border:1px solid #dce1e5; border-radius:4px; color:#1e293b; background:#fff; cursor:pointer;">
								<option value="1">Enero</option><option value="2">Febrero</option><option value="3">Marzo</option>
								<option value="4">Abril</option><option value="5">Mayo</option><option value="6">Junio</option>
								<option value="7">Julio</option><option value="8">Agosto</option><option value="9">Septiembre</option>
								<option value="10">Octubre</option><option value="11">Noviembre</option><option value="12">Diciembre</option>
							</select>
							<select id="cal_anio_sel" onchange="crmCalCambiarFecha()" style="height:32px; width:72px; flex-shrink:0; padding:4px 6px; font-size:12px; font-weight:600; border:1px solid #dce1e5; border-radius:4px; color:#1e293b; background:#fff; cursor:pointer;">
								<?php
								$anio_cur = intval(date('Y'));
								for ($y = $anio_cur - 2; $y <= $anio_cur + 5; $y++) {
									echo '<option value="' . $y . '"' . ($y === $anio_cur ? ' selected' : '') . '>' . $y . '</option>';
								}
								?>
							</select>
							<button type="button" class="btn btn-white" id="btnLimpiarCalControls" onclick="crmLimpiarCalendarioControls()" title="Volver al mes actual y desmarcar día seleccionado" style="height:32px; padding:0 10px; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:5px; border:1px solid #dce1e5; border-radius:4px; flex-shrink:0;">
								<i class="fa fa-refresh"></i> Limpiar
							</button>
							<button type="button" class="btn btn-white" onclick="crmCalMesSig()" title="Mes siguiente" style="height:32px; width:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; border:1px solid #dce1e5; border-radius:4px; flex-shrink:0;">
								<i class="fa fa-chevron-right"></i>
							</button>
						</div>
					</div>
					<div class="grid-body" style="padding:10px 15px;">
						<div id="calMiniGridContainer">
							<div style="text-align:center; padding:15px;"><i class="fa fa-spinner fa-spin text-muted"></i></div>
						</div>
						<div class="cal-legend">
							<span><span class="dot dot-red"></span> Vencido</span>
							<span><span class="dot dot-orange"></span> Próximo</span>
							<span><span class="dot dot-green"></span> Lejano</span>
						</div>
					</div>
				</div>

				<!-- Upcoming Expirations Box -->
				<div class="grid simple dom-upcoming-panel">
					<div class="grid-title">
						<h4><i class="fa fa-clock-o text-warning"></i> Próximos vencimientos</h4>
					</div>
					<div class="grid-body">
						<div id="proximosListContainer">
							<div style="text-align:center; padding:15px;"><i class="fa fa-spinner fa-spin text-muted"></i></div>
						</div>
					</div>
				</div>

			</div>
		</div>

	</div>
</div>

<!-- ========================================================= -->
<!-- MODAL 1: NUEVO / EDITAR DOMINIO                            -->
<!-- ========================================================= -->
<div class="modal fade" id="modalGuardarDominio" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
				<h4 class="modal-title" id="modalGuardarTitulo" style="font-weight:700;">Nuevo dominio</h4>
			</div>
			<form id="formGuardarDominio" onsubmit="crmSubmitGuardarDominio(event)">
				<input type="hidden" id="guardar_id_dominio" name="id_dominio" value="0">
				<input type="hidden" name="accion" value="guardar">
				<div class="modal-body">
					<div id="alertGuardarDominio" class="alert alert-danger" style="display:none;"></div>
					<div class="form-group">
						<label class="form-label">Nombre del dominio <span class="text-error">*</span></label>
						<input type="text" id="guardar_nombre" name="nombre" class="form-control" placeholder="ej. miempresa.com.ar" required>
					</div>
					<div class="form-group">
						<label class="form-label">URL del sitio web</label>
						<input type="text" id="guardar_url" name="url" class="form-control" placeholder="https://miempresa.com.ar">
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="form-label">Proveedor / Registrador</label>
								<input type="text" id="guardar_proveedor" name="proveedor" class="form-control" placeholder="ej. DonDominio, NIC Argentina">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="form-label">Responsable</label>
								<input type="text" id="guardar_responsable" name="responsable" class="form-control" placeholder="ej. Carlos Ruiz">
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="form-label">Fecha de inicio</label>
								<input type="date" id="guardar_fecha_inicio" name="fecha_inicio" class="form-control">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="form-label">Fecha de vencimiento <span class="text-error">*</span></label>
								<input type="date" id="guardar_fecha_vencimiento" name="fecha_vencimiento" class="form-control" required>
							</div>
						</div>
					</div>
					<div class="form-group">
						<label class="form-label">Notas / Observaciones</label>
						<textarea id="guardar_notas" name="notas" class="form-control" rows="3" placeholder="Información adicional o credenciales de gestión..."></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
					<button type="submit" id="btnSubmitGuardar" class="btn btn-primary">Guardar dominio</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- ========================================================= -->
<!-- MODAL 2: VER DOMINIO (READ ONLY & RENEWAL HISTORY)        -->
<!-- ========================================================= -->
<div class="modal fade" id="modalVerDominio" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content" style="border-radius:6px; overflow:hidden; border:none; box-shadow:0 10px 30px rgba(0,0,0,0.18);">
			<div class="modal-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:16px 20px;">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true" style="margin-top:2px; font-size:20px;">&times;</button>
				<div style="display:flex; align-items:center; gap:12px;">
					<div style="width:40px; height:40px; border-radius:50%; background:#e0f2fe; color:#0284c7; display:inline-flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0;">
						<i class="fa fa-globe"></i>
					</div>
					<div>
						<h4 class="modal-title" style="margin:0; font-weight:700; font-size:17px; color:#0f172a; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
							<span>Detalles del dominio:</span>
							<span id="verNombreDominio" style="color:#0088cc; font-weight:800;">-</span>
							<span id="verHeaderEstadoBadge"></span>
						</h4>
						<div id="verSubtituloDominio" style="font-size:12px; color:#64748b; margin-top:3px;">Información técnica, vigencia y renovaciones</div>
					</div>
				</div>
			</div>
			<div class="modal-body" style="padding:20px; background:#f1f5f9;">
				
				<!-- Attribute Cards: 2 Columns -->
				<div class="row">
					<div class="col-md-6">
						<div class="dom-detail-card">
							<h5 class="dom-detail-section-title">
								<i class="fa fa-info-circle text-info"></i> Información del Dominio
							</h5>
							<div class="dom-detail-item">
								<span class="dom-detail-label"><i class="fa fa-globe text-muted"></i> Nombre:</span>
								<span class="dom-detail-value" id="verDomNombre">-</span>
							</div>
							<div class="dom-detail-item">
								<span class="dom-detail-label"><i class="fa fa-link text-muted"></i> URL web:</span>
								<span class="dom-detail-value" id="verDomUrl">-</span>
							</div>
							<div class="dom-detail-item">
								<span class="dom-detail-label"><i class="fa fa-server text-muted"></i> Proveedor:</span>
								<span class="dom-detail-value" id="verDomProveedor">-</span>
							</div>
							<div class="dom-detail-item">
								<span class="dom-detail-label"><i class="fa fa-user text-muted"></i> Responsable:</span>
								<span class="dom-detail-value" id="verDomResponsable">-</span>
							</div>
						</div>
					</div>

					<div class="col-md-6">
						<div class="dom-detail-card">
							<h5 class="dom-detail-section-title">
								<i class="fa fa-calendar text-info"></i> Vigencia y Vencimiento
							</h5>
							<div class="dom-detail-item">
								<span class="dom-detail-label"><i class="fa fa-calendar-plus-o text-muted"></i> Fecha inicio:</span>
								<span class="dom-detail-value" id="verDomFechaInicio">-</span>
							</div>
							<div class="dom-detail-item">
								<span class="dom-detail-label"><i class="fa fa-calendar text-muted"></i> Vencimiento:</span>
								<span class="dom-detail-value" id="verDomFechaVencimiento" style="font-weight:700;">-</span>
							</div>
							<div class="dom-detail-item">
								<span class="dom-detail-label"><i class="fa fa-clock-o text-muted"></i> Días restantes:</span>
								<span class="dom-detail-value" id="verDomDiasRestantes">-</span>
							</div>
							<div class="dom-detail-item">
								<span class="dom-detail-label"><i class="fa fa-shield text-muted"></i> Estado:</span>
								<span class="dom-detail-value" id="verDomEstado">-</span>
							</div>
						</div>
					</div>
				</div>

				<!-- Notes Section Card -->
				<div class="dom-detail-card">
					<h5 class="dom-detail-section-title">
						<i class="fa fa-file-text-o text-info"></i> Notas / Observaciones
					</h5>
					<div id="verDomNotas" style="color:#334155; font-size:13px; line-height:1.5; min-height:24px;">-</div>
				</div>

				<!-- Renewal History Card -->
				<div class="dom-detail-card" style="margin-bottom:0;">
					<h5 class="dom-detail-section-title">
						<i class="fa fa-history text-info"></i> Historial de Renovaciones
					</h5>
					<div id="verDomRenovacionesHistorial">
						<div class="text-muted small" style="text-align:center; padding:10px;">Cargando historial...</div>
					</div>
				</div>

			</div>
			<div class="modal-footer" style="background:#f8fafc; border-top:1px solid #e2e8f0; padding:12px 20px;">
				<button type="button" class="btn btn-white" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ========================================================= -->
<!-- MODAL 3: RENOVAR DOMINIO                                  -->
<!-- ========================================================= -->
<div class="modal fade" id="modalRenovarDominio" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
				<h4 class="modal-title" style="font-weight:700;"><i class="fa fa-refresh text-info"></i> Renovar dominio</h4>
			</div>
			<form id="formRenovarDominio" onsubmit="crmSubmitRenovarDominio(event)">
				<input type="hidden" id="renovar_id_dominio" name="id_dominio" value="0">
				<input type="hidden" name="accion" value="renovar">
				<div class="modal-body">
					<div id="alertRenovarDominio" class="alert alert-danger" style="display:none;"></div>
					
					<div class="alert alert-info">
						Dominio: <strong id="renovarNombreDom">-</strong><br>
						Vencimiento actual: <span id="renovarVencActualDom" class="bold">-</span>
					</div>

					<div class="form-group">
						<label class="form-label">Fecha de renovación <span class="text-error">*</span></label>
						<input type="date" id="renovar_fecha_renovacion" name="fecha_renovacion" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
					</div>

					<div class="form-group">
						<label class="form-label">Nueva fecha de vencimiento <span class="text-error">*</span></label>
						<input type="date" id="renovar_fecha_vencimiento_nueva" name="fecha_vencimiento_nueva" class="form-control" required>
					</div>

					<div class="form-group">
						<label class="form-label">Notas / Observaciones de la renovación</label>
						<textarea id="renovar_notas" name="notas_renovacion" class="form-control" rows="2" placeholder="ej. Renovado por 1 año en DonDominio"></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
					<button type="submit" id="btnSubmitRenovar" class="btn btn-info">Confirmar renovación</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-ui/jquery-ui-1.10.1.custom.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/plugins/breakpoints.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-unveil/jquery.unveil.min.js" type="text/javascript"></script>
<script src="../assets/plugins/pace/pace.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-scrollbar/jquery.scrollbar.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-block-ui/jqueryblockui.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
<script src="../assets/js/chat.js" type="text/javascript"></script>
<script src="../assets/js/demo.js" type="text/javascript"></script>

<script>
var crmDebounceTimer = null;
var crmCalAnioHoy = <?php echo date('Y'); ?>;
var crmCalMesHoy = <?php echo date('m'); ?>;
var crmCalAnioActual = crmCalAnioHoy;
var crmCalMesActual = crmCalMesHoy;
var crmCalDiaSeleccionado = '';

document.addEventListener('DOMContentLoaded', function() {
	document.getElementById('cal_mes_sel').value = crmCalMesActual;
	document.getElementById('cal_anio_sel').value = crmCalAnioActual;

	crmFiltrarDominios();
	crmCargarCalendarioMini();
});

function crmDebounceFiltrar() {
	if (crmDebounceTimer) clearTimeout(crmDebounceTimer);
	crmDebounceTimer = setTimeout(function() {
		crmFiltrarDominios();
	}, 350);
}

function crmFiltrarDominios() {
	var q = document.getElementById('filtro_q').value.trim();
	var anio = document.getElementById('filtro_anio').value;
	var proveedor = document.getElementById('filtro_proveedor').value;
	var estado = document.getElementById('filtro_estado').value;
	var responsable = document.getElementById('filtro_responsable').value;

	var hasFilter = (q !== '' || anio !== 'todos' || proveedor !== 'todos' || estado !== 'todos' || responsable !== 'todos' || crmCalDiaSeleccionado !== '');
	document.getElementById('btnLimpiarDominios').style.display = hasFilter ? 'inline-block' : 'none';

	// Toggle Day Filter Pill
	var boxDia = document.getElementById('boxFiltroDia');
	if (crmCalDiaSeleccionado !== '') {
		var parts = crmCalDiaSeleccionado.split('-');
		var fmtDia = parts[2] + '/' + parts[1] + '/' + parts[0];
		document.getElementById('lblFechaSeleccionada').innerText = fmtDia;
		boxDia.style.display = 'flex';
	} else {
		boxDia.style.display = 'none';
	}

	var url = 'dominios.php?ajax=1&q=' + encodeURIComponent(q) +
	          '&fecha=' + encodeURIComponent(crmCalDiaSeleccionado) +
	          '&anio=' + encodeURIComponent(anio) +
	          '&proveedor=' + encodeURIComponent(proveedor) +
	          '&estado=' + encodeURIComponent(estado) +
	          '&responsable=' + encodeURIComponent(responsable);

	fetch(url)
		.then(function(res) { return res.json(); })
		.then(function(data) {
			if (data.ok) {
				document.getElementById('contenedorTarjetasDominios').innerHTML = data.cards_html;
				document.getElementById('statTotal').innerText = data.stats.total;
				document.getElementById('statProximo').innerText = data.stats.proximo;
				document.getElementById('statVencenMes').innerText = data.stats.vencen_mes;
				document.getElementById('statVencido').innerText = data.stats.vencido;

				var banner = document.getElementById('alertBannerDominios');
				if (data.alert_count > 0) {
					document.getElementById('alertBannerText').innerText = data.alert_count + ' dominio(s) vencen en los próximos 30 días o se encuentran vencidos.';
					banner.style.display = 'block';
				} else {
					banner.style.display = 'none';
				}
			}
		})
		.catch(function(err) {
			console.error('Error cargando tarjetas de dominios:', err);
		});
}

function crmQuitarFiltroDia() {
	crmCalDiaSeleccionado = '';
	crmFiltrarDominios();
	crmCargarCalendarioMini();
}

function crmLimpiarCalendarioControls() {
	crmCalAnioActual = crmCalAnioHoy;
	crmCalMesActual = crmCalMesHoy;
	crmCalDiaSeleccionado = '';
	document.getElementById('cal_mes_sel').value = crmCalMesActual;
	document.getElementById('cal_anio_sel').value = crmCalAnioActual;
	crmFiltrarDominios();
	crmCargarCalendarioMini();
}

function crmLimpiarFiltros() {
	document.getElementById('filtro_q').value = '';
	document.getElementById('filtro_anio').value = 'todos';
	document.getElementById('filtro_proveedor').value = 'todos';
	document.getElementById('filtro_estado').value = 'todos';
	document.getElementById('filtro_responsable').value = 'todos';
	crmCalDiaSeleccionado = '';
	crmCalAnioActual = crmCalAnioHoy;
	crmCalMesActual = crmCalMesHoy;
	document.getElementById('cal_mes_sel').value = crmCalMesActual;
	document.getElementById('cal_anio_sel').value = crmCalAnioActual;
	crmFiltrarDominios();
	crmCargarCalendarioMini();
}

function crmCargarCalendarioMini() {
	var url = 'dominios.php?ajax_cal=1&cal_anio=' + crmCalAnioActual + '&cal_mes=' + crmCalMesActual + '&dia_sel=' + encodeURIComponent(crmCalDiaSeleccionado);
	fetch(url)
		.then(function(res) { return res.json(); })
		.then(function(data) {
			if (data.ok) {
				document.getElementById('calMiniGridContainer').innerHTML = data.grid_html;
				document.getElementById('proximosListContainer').innerHTML = data.proximos_html;
				document.getElementById('cal_mes_sel').value = data.cal_mes;
				document.getElementById('cal_anio_sel').value = data.cal_anio;
			}
		})
		.catch(function(err) {
			console.error('Error cargando mini calendario:', err);
		});
}

function crmCalMesPrev() {
	crmCalMesActual--;
	if (crmCalMesActual < 1) {
		crmCalMesActual = 12;
		crmCalAnioActual--;
	}
	crmCargarCalendarioMini();
}

function crmCalMesSig() {
	crmCalMesActual++;
	if (crmCalMesActual > 12) {
		crmCalMesActual = 1;
		crmCalAnioActual++;
	}
	crmCargarCalendarioMini();
}

function crmCalCambiarFecha() {
	crmCalMesActual = parseInt(document.getElementById('cal_mes_sel').value);
	crmCalAnioActual = parseInt(document.getElementById('cal_anio_sel').value);
	crmCargarCalendarioMini();
}

function crmSeleccionarDiaMini(fecha) {
	if (crmCalDiaSeleccionado === fecha) {
		crmCalDiaSeleccionado = '';
	} else {
		crmCalDiaSeleccionado = fecha;
	}
	crmFiltrarDominios();
	crmCargarCalendarioMini();
}

function crmNuevoDominioModal() {
	document.getElementById('formGuardarDominio').reset();
	document.getElementById('guardar_id_dominio').value = '0';
	document.getElementById('modalGuardarTitulo').innerText = 'Nuevo dominio';
	document.getElementById('alertGuardarDominio').style.display = 'none';
	$('#modalGuardarDominio').modal('show');
}

function crmEditarDominio(id) {
	fetch('dominios.php?ajax_ver=1&id=' + id)
		.then(function(res) { return res.json(); })
		.then(function(data) {
			if (data.ok) {
				var d = data.dominio;
				document.getElementById('guardar_id_dominio').value = d.id_dominio;
				document.getElementById('guardar_nombre').value = d.nombre || '';
				document.getElementById('guardar_url').value = d.url || '';
				document.getElementById('guardar_proveedor').value = d.proveedor || '';
				document.getElementById('guardar_responsable').value = d.responsable || '';
				document.getElementById('guardar_fecha_inicio').value = d.fecha_inicio || '';
				document.getElementById('guardar_fecha_vencimiento').value = d.fecha_vencimiento || '';
				document.getElementById('guardar_notas').value = d.notas || '';
				
				document.getElementById('modalGuardarTitulo').innerText = 'Editar dominio';
				document.getElementById('alertGuardarDominio').style.display = 'none';
				$('#modalGuardarDominio').modal('show');
			}
		});
}

function crmVerDominio(id) {
	fetch('dominios.php?ajax_ver=1&id=' + id)
		.then(function(res) { return res.json(); })
		.then(function(data) {
			if (data.ok) {
				var d = data.dominio;
				var resp_text = d.responsable && d.responsable.trim() ? d.responsable.trim() : 'Sin asignar';
				var prov_text = d.proveedor && d.proveedor.trim() ? d.proveedor.trim() : 'Sin asignar';
				
				document.getElementById('verNombreDominio').innerText = d.nombre;
				document.getElementById('verSubtituloDominio').innerText = 'Información técnica, vigencia y renovaciones';
				document.getElementById('verHeaderEstadoBadge').innerHTML = '<span class="label ' + d.estado_info.badge + '" style="font-size:11px; padding:3px 8px; vertical-align:middle;">' + d.estado_info.etiqueta + '</span>';
				
				document.getElementById('verDomNombre').innerText = d.nombre;
				
				if (d.url && d.url.trim()) {
					var rawUrl = d.url.trim();
					var displayUrl = rawUrl.length > 36 ? rawUrl.substring(0, 33) + '...' : rawUrl;
					document.getElementById('verDomUrl').innerHTML = '<a href="' + rawUrl + '" target="_blank" class="text-info" style="font-weight:600; text-decoration:none;" title="' + rawUrl + '">' + displayUrl + ' <i class="fa fa-external-link" style="font-size:11px; margin-left:3px;"></i></a>';
				} else {
					document.getElementById('verDomUrl').innerHTML = '<span class="text-muted">No especificada</span>';
				}
				
				document.getElementById('verDomProveedor').innerText = prov_text;
				document.getElementById('verDomResponsable').innerText = resp_text;
				document.getElementById('verDomFechaInicio').innerText = d.fecha_inicio ? d.fecha_inicio.split('-').reverse().join('/') : '-';
				document.getElementById('verDomFechaVencimiento').innerText = d.fecha_vencimiento ? d.fecha_vencimiento.split('-').reverse().join('/') : '-';
				
				if (d.dias_restantes < 0) {
					document.getElementById('verDomDiasRestantes').innerHTML = '<span class="text-error bold"><i class="fa fa-exclamation-triangle"></i> ' + Math.abs(d.dias_restantes) + ' días vencido</span>';
				} else if (d.dias_restantes === 0) {
					document.getElementById('verDomDiasRestantes').innerHTML = '<span class="text-warning bold"><i class="fa fa-clock-o"></i> Vence hoy</span>';
				} else {
					document.getElementById('verDomDiasRestantes').innerHTML = '<span class="text-success bold">' + d.dias_restantes + ' días restantes</span>';
				}
				
				document.getElementById('verDomEstado').innerHTML = '<span class="label ' + d.estado_info.badge + '" style="font-size:11px; padding:3px 8px;">' + d.estado_info.etiqueta + '</span>';
				
				if (d.notas && d.notas.trim()) {
					document.getElementById('verDomNotas').innerText = d.notas;
				} else {
					document.getElementById('verDomNotas').innerHTML = '<span class="text-muted" style="font-style:italic;"><i class="fa fa-info-circle"></i> Sin notas u observaciones registradas para este dominio.</span>';
				}

				var histContainer = document.getElementById('verDomRenovacionesHistorial');
				if (d.renovaciones && d.renovaciones.length > 0) {
					var html = '<ul class="timeline-renovaciones">';
					d.renovaciones.forEach(function(r) {
						var f_renov = r.fecha_renovacion ? r.fecha_renovacion.split('-').reverse().join('/') : '-';
						var f_ant = r.fecha_vencimiento_anterior ? r.fecha_vencimiento_anterior.split('-').reverse().join('/') : '-';
						var f_nue = r.fecha_vencimiento_nueva ? r.fecha_vencimiento_nueva.split('-').reverse().join('/') : '-';
						html += '<li class="timeline-item">';
						html += '<div class="timeline-icon"><i class="fa fa-refresh"></i></div>';
						html += '<div class="timeline-content">';
						html += '<strong>Renovado el ' + f_renov + '</strong> por ' + (r.usuario_nombre || 'Sistema') + '<br>';
						html += '<span class="text-muted small">Vencimiento anterior: ' + f_ant + ' &rarr; <strong>Nuevo vencimiento: ' + f_nue + '</strong></span>';
						if (r.notas) {
							html += '<div style="margin-top:4px; font-style:italic;" class="text-muted small">' + r.notas + '</div>';
						}
						html += '</div></li>';
					});
					html += '</ul>';
					histContainer.innerHTML = html;
				} else {
					histContainer.innerHTML = '<div style="text-align:center; padding:18px 10px; color:#94a3b8;"><i class="fa fa-calendar-check-o" style="font-size:24px; display:block; margin-bottom:6px; color:#cbd5e1;"></i>No se registraron renovaciones anteriores para este dominio.</div>';
				}

				$('#modalVerDominio').modal('show');
			}
		});
}

function crmRenovarDominioModal(id) {
	fetch('dominios.php?ajax_ver=1&id=' + id)
		.then(function(res) { return res.json(); })
		.then(function(data) {
			if (data.ok) {
				var d = data.dominio;
				document.getElementById('renovar_id_dominio').value = d.id_dominio;
				document.getElementById('renovarNombreDom').innerText = d.nombre;
				document.getElementById('renovarVencActualDom').innerText = d.fecha_vencimiento ? d.fecha_vencimiento.split('-').reverse().join('/') : '-';
				
				if (d.fecha_vencimiento) {
					var parts = d.fecha_vencimiento.split('-');
					var nextYr = parseInt(parts[0]) + 1;
					document.getElementById('renovar_fecha_vencimiento_nueva').value = nextYr + '-' + parts[1] + '-' + parts[2];
				} else {
					document.getElementById('renovar_fecha_vencimiento_nueva').value = '';
				}

				document.getElementById('alertRenovarDominio').style.display = 'none';
				document.getElementById('renovar_notas').value = '';
				$('#modalRenovarDominio').modal('show');
			}
		});
}

function crmSubmitGuardarDominio(e) {
	e.preventDefault();
	var form = document.getElementById('formGuardarDominio');
	var formData = new FormData(form);

	fetch('dominios.php', { method: 'POST', body: formData })
		.then(function(res) { return res.json(); })
		.then(function(data) {
			if (data.ok) {
				$('#modalGuardarDominio').modal('hide');
				crmFiltrarDominios();
				crmCargarCalendarioMini();
			} else {
				var errBox = document.getElementById('alertGuardarDominio');
				errBox.innerText = data.error || 'Ocurrió un error al guardar';
				errBox.style.display = 'block';
			}
		});
}

function crmSubmitRenovarDominio(e) {
	e.preventDefault();
	var form = document.getElementById('formRenovarDominio');
	var formData = new FormData(form);

	fetch('dominios.php', { method: 'POST', body: formData })
		.then(function(res) { return res.json(); })
		.then(function(data) {
			if (data.ok) {
				$('#modalRenovarDominio').modal('hide');
				crmFiltrarDominios();
				crmCargarCalendarioMini();
			} else {
				var errBox = document.getElementById('alertRenovarDominio');
				errBox.innerText = data.error || 'Ocurrió un error al renovar';
				errBox.style.display = 'block';
			}
		});
}

function crmBajaDominio(id) {
	if (!confirm('¿Estás seguro de dar de baja este dominio?')) return;

	var formData = new FormData();
	formData.append('accion', 'baja');
	formData.append('id_dominio', id);

	fetch('dominios.php', { method: 'POST', body: formData })
		.then(function(res) { return res.json(); })
		.then(function(data) {
			if (data.ok) {
				crmFiltrarDominios();
				crmCargarCalendarioMini();
			} else {
				alert(data.error || 'No se pudo dar de baja el dominio');
			}
		});
}
</script>

</body>
</html>
