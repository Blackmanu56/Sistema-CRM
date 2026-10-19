<?php

/**
 * Checks whether the domain report is available in the active CRM context.
 * Global roles still need to be viewing the Systems area to access this report.
 */
function crm_informes_dominios_puede_consultar($area_activa, $acceso_global, $puede_acceder_sistemas)
{
	return trim((string)$area_activa) === 'sistemas' && ((bool)$acceso_global || (bool)$puede_acceder_sistemas);
}
