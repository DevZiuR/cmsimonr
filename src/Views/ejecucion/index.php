<?php
/**
 * src/Views/ejecucion/index.php
 * Ejecución del Presupuesto de Gastos — Reporte mensual
 * PHP 5.6 compatible — sin ??
 */
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');

require_once '../../../config/conexion.php';
require_once __DIR__ . '/../../Models/catalogo_partidas.php';

/* ── Auto-crear tabla si no existe ── */
mysqli_query(
    $conn,
    "CREATE TABLE IF NOT EXISTS ejecucion_presupuestaria (
        id INT(11) NOT NULL AUTO_INCREMENT,
        codificacion VARCHAR(50) NOT NULL,
        denominacion VARCHAR(300) NOT NULL,
        credito_aprobado DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        aumentos DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        disminuciones DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        mes TINYINT(2) NOT NULL,
        anio SMALLINT(4) NOT NULL,
        credito_actualizado_override DECIMAL(18,2) NULL,
        compromiso_mensual_override DECIMAL(18,2) NULL,
        compromiso_acumulado_override DECIMAL(18,2) NULL,
        gastos_causados_override DECIMAL(18,2) NULL,
        pago_acumulado_override DECIMAL(18,2) NULL,
        compromisos_pagar_override DECIMAL(18,2) NULL,
        disponibilidad_override DECIMAL(18,2) NULL,
        credito_adicional_override DECIMAL(18,2) NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_codificacion_mes_anio (codificacion, mes, anio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
);

/* ── Filtros mes/año ── */
$mes_sel = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
$anio_sel = isset($_GET['anio']) ? (int) $_GET['anio'] : (int) date('Y');
if ($mes_sel < 1 || $mes_sel > 12)
    $mes_sel = (int) date('n');
if ($anio_sel < 2000 || $anio_sel > 2100)
    $anio_sel = (int) date('Y');

/* ── Acción explícita para precargar catálogo para el mes/año seleccionado ── */
if ($es_admin && isset($_GET['precargar']) && $_GET['precargar'] === '1') {
    $cat = get_catalogo_partidas($conn);
    foreach ($cat as $ci) {
        $c_cod = mysqli_real_escape_string($conn, $ci['cod']);
        $c_den = mysqli_real_escape_string($conn, $ci['denom']);
        mysqli_query($conn, "INSERT IGNORE INTO ejecucion_presupuestaria 
            (codificacion, denominacion, credito_aprobado, aumentos, disminuciones, mes, anio)
            VALUES ('$c_cod', '$c_den', 0.00, 0.00, 0.00, $mes_sel, $anio_sel)");
    }
    header("Location: index.php?mes=$mes_sel&anio=$anio_sel&precargado=1");
    exit;
}

$anio_inicio_str = $anio_sel . '-01-01';
$mes_inicio_str = sprintf('%04d-%02d-01', $anio_sel, $mes_sel);
$mes_fin_str = date('Y-m-t', strtotime($mes_inicio_str));

/* ── Catálogo completo para el selector de partidas ── */
$catalogo_maestro = get_catalogo_partidas($conn);
$catalogo_json = json_encode($catalogo_maestro, JSON_UNESCAPED_UNICODE);

/* ── Obtener partidas del mes/año con JOIN a partidas (fuente de verdad del crédito) ── */
$sql_partidas = "SELECT ep.*,
                        COALESCE(p.credito_original, ep.credito_aprobado) AS credito_original_real
                 FROM ejecucion_presupuestaria ep
                 LEFT JOIN partidas p ON p.codigo = ep.codificacion
                 WHERE ep.mes = $mes_sel AND ep.anio = $anio_sel
                 ORDER BY ep.codificacion ASC";
$res_partidas = mysqli_query($conn, $sql_partidas);
$partidas = array();
if ($res_partidas) {
    while ($p = mysqli_fetch_assoc($res_partidas)) {
        $partidas[] = $p;
    }
}

/* ── Pre-cargar todas las OPs del año (cont_json + op_retenciones) en memoria ── */
$ops_all = array();

/* Función para parsear un monto que puede venir como "5,453.42" o "5453.42" o "5.453,42" */
function parse_seg_total($val)
{
    if (!isset($val) || $val === '' || $val === null)
        return 0.0;
    $str = trim((string) $val);
    if (strpos($str, ',') !== false && strpos($str, '.') !== false) {
        $lastDot = strrpos($str, '.');
        $lastComma = strrpos($str, ',');
        if ($lastComma > $lastDot) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } else {
            $str = str_replace(',', '', $str);
        }
    } elseif (strpos($str, ',') !== false) {
        $parts = explode(',', $str);
        if (count($parts) === 2 && strlen($parts[1]) === 3) {
            $str = str_replace(',', '', $str);
        } else {
            $str = str_replace(',', '.', $str);
        }
    }
    return (float) $str;
}

/* ── Cargar overrides de matriz_numeros_registro para el año ── */
$matriz_overrides = array();
$res_nr = mysqli_query($conn, "SELECT codificacion, op_id, mes, monto_compromiso_custom, monto_pago_custom FROM matriz_numeros_registro WHERE anio = $anio_sel");
if ($res_nr) {
    while ($nr = mysqli_fetch_assoc($res_nr)) {
        $c_esc = strtolower(trim($nr['codificacion']));
        $oid = (int) $nr['op_id'];
        $m = (int) $nr['mes'];
        if ($nr['monto_compromiso_custom'] !== null) {
            $matriz_overrides['comp'][$c_esc][$oid][$m] = (float) $nr['monto_compromiso_custom'];
        }
        if ($nr['monto_pago_custom'] !== null) {
            $matriz_overrides['pago'][$c_esc][$oid][$m] = (float) $nr['monto_pago_custom'];
        }
    }
}

if (!function_exists('get_matriz_override_val')) {
    function get_matriz_override_val($matriz_overrides, $type, $variants, $op_id, $mes = null)
    {
        if (!isset($matriz_overrides[$type]))
            return null;
        foreach ($variants as $v) {
            $vl = strtolower(trim($v));
            if (isset($matriz_overrides[$type][$vl][$op_id])) {
                if ($mes !== null && isset($matriz_overrides[$type][$vl][$op_id][$mes])) {
                    return $matriz_overrides[$type][$vl][$op_id][$mes];
                } elseif ($mes === null) {
                    return reset($matriz_overrides[$type][$vl][$op_id]);
                }
            }
        }
        return null;
    }
}

if (!function_exists('iva_tipo_proveedor_rep')) {
    function iva_tipo_proveedor_rep($nombre)
    {
        $n = strtoupper(trim($nombre));
        if (strpos($n, 'SENIAT') !== false)
            return 'seniat';
        if (strpos($n, 'CORPOELEC') !== false || strpos($n, 'CANTV') !== false)
            return 'estatal';
        return 'privado';
    }
}

$res_ops = mysqli_query(
    $conn,
    "SELECT op.id, op.fecha, op.monto_bruto, op.monto_neto_pagar, op.cont_json,
            COALESCE(p.razon_social, poc.razon_social, pos.razon_social, '') AS proveedor_nombre
     FROM ordenes_pago op
     LEFT JOIN proveedores p ON p.id = op.proveedor_id
     LEFT JOIN ordenes_compra oc ON oc.id = op.oc_id
     LEFT JOIN proveedores poc ON poc.id = oc.proveedor_id
     LEFT JOIN ordenes_servicio os ON os.id = op.os_id
     LEFT JOIN proveedores pos ON pos.id = os.proveedor_id
     WHERE op.fecha BETWEEN '$anio_inicio_str' AND '$mes_fin_str'
       AND op.deleted_at IS NULL"
);
if ($res_ops) {
    while ($op_row = mysqli_fetch_assoc($res_ops)) {
        $oid = (int) $op_row['id'];
        $op_bruto = (float) $op_row['monto_bruto'];
        $op_neto = (float) $op_row['monto_neto_pagar'];
        $op_codes = array();

        /* 1. Renglones de op_retenciones (fuente de verdad para retenciones IVA) */
        $ret_codes_in_op = array();
        $res_rets = mysqli_query(
            $conn,
            "SELECT codigo_presupuestario, monto_comision
             FROM op_retenciones
             WHERE op_id = $oid AND monto_comision > 0
               AND (codigo_presupuestario LIKE '%403-18-01%' OR descripcion LIKE '%IVA%')"
        );
        if ($res_rets && mysqli_num_rows($res_rets) > 0) {
            while ($r_row = mysqli_fetch_assoc($res_rets)) {
                $code_raw = trim($r_row['codigo_presupuestario']);
                $m_com = (float) $r_row['monto_comision'];
                if (empty($code_raw) || $m_com <= 0)
                    continue;

                /* Generar variantes para este código */
                $vars = get_matching_code_variants($code_raw);
                $short = isset($vars[0]) ? $vars[0] : $code_raw;
                $long = isset($vars[1]) ? $vars[1] : $code_raw;
                $dot = isset($vars[2]) ? $vars[2] : $code_raw;

                foreach ($vars as $v) {
                    $ret_codes_in_op[strtolower($v)] = true;
                }

                $op_codes[] = array(
                    'short' => $short,
                    'long' => $long,
                    'dot' => $dot,
                    'monto_bruto' => $m_com,
                    'monto_neto' => $m_com,
                );
            }
        }

        /* 2. Renglones de cont_json (imputaciones de gasto base) */
        if (!empty($op_row['cont_json'])) {
            $segs = json_decode($op_row['cont_json'], true);
            if (is_array($segs) && !empty($segs)) {
                $sum_seg_totals = 0.0;
                $has_explicit_total = false;
                foreach ($segs as $seg) {
                    $t_val = parse_seg_total(isset($seg['total']) ? $seg['total'] : '');
                    $sum_seg_totals += $t_val;
                    if (isset($seg['total']) && trim((string) $seg['total']) !== '' && trim((string) $seg['total']) !== '0' && trim((string) $seg['total']) !== '0.00' && trim((string) $seg['total']) !== '0,00') {
                        $has_explicit_total = true;
                    }
                }

                // Contar segmentos de gasto principal (no retenciones)
                $n_non_ret = 0;
                foreach ($segs as $seg) {
                    $obra = isset($seg['obra']) ? trim($seg['obra']) : '';
                    $partid = isset($seg['partid']) ? trim($seg['partid']) : '';
                    $gen = isset($seg['gen']) ? trim($seg['gen']) : '';
                    $espec = (isset($seg['espec']) && trim($seg['espec']) !== '') ? trim($seg['espec']) : '00';
                    $short_c = strtolower($obra . '-' . $partid . '-' . $gen . '-' . $espec);
                    $long_c = strtolower('01-08-00-00-51-' . $short_c);
                    $is_ret_c = isset($ret_codes_in_op[$short_c]) || isset($ret_codes_in_op[$long_c])
                        || (strpos($short_c, '403-18-01') !== false || strpos($short_c, '403-18-99') !== false || strpos($short_c, '4.03.18') !== false);
                    if (!$is_ret_c) {
                        $n_non_ret++;
                    }
                }

                foreach ($segs as $seg) {
                    $obra = isset($seg['obra']) ? trim($seg['obra']) : '';
                    $partid = isset($seg['partid']) ? trim($seg['partid']) : '';
                    $gen = isset($seg['gen']) ? trim($seg['gen']) : '';
                    $espec = (isset($seg['espec']) && trim($seg['espec']) !== '') ? trim($seg['espec']) : '00';

                    $short = $obra . '-' . $partid . '-' . $gen . '-' . $espec;
                    $long = '01-08-00-00-51-' . $short;
                    if (strlen($obra) === 3) {
                        $dot = substr($obra, 0, 1) . '.' . substr($obra, 1) . '.' . $partid . '.' . $gen . '.' . $espec;
                    } else {
                        $dot = $obra . '.' . $partid . '.' . $gen . '.' . $espec;
                    }

                    $short_l = strtolower($short);
                    $long_l = strtolower($long);
                    $dot_l = strtolower($dot);
                    $is_ret_code = isset($ret_codes_in_op[$short_l]) || isset($ret_codes_in_op[$long_l]) || isset($ret_codes_in_op[$dot_l])
                        || (strpos($short_l, '403-18-01') !== false || strpos($short_l, '403-18-99') !== false || strpos($short_l, '4.03.18') !== false);

                    $seg_total_raw = isset($seg['total']) ? $seg['total'] : '';
                    $seg_bruto = parse_seg_total($seg_total_raw);
                    if ($seg_bruto <= 0) {
                        if ($has_explicit_total || $is_ret_code) {
                            $seg_bruto = 0.0;
                        } else {
                            $divisor = ($n_non_ret > 0) ? $n_non_ret : count($segs);
                            $seg_bruto = $op_bruto / $divisor;
                        }
                    }

                    $seg_neto = ($sum_seg_totals > 0 && $seg_bruto > 0)
                        ? $op_neto * ($seg_bruto / $sum_seg_totals)
                        : ($seg_bruto > 0 ? $seg_bruto : 0.0);

                    if ($seg_bruto > 0) {
                        $op_codes[] = array(
                            'short' => $short,
                            'long' => $long,
                            'dot' => $dot,
                            'monto_bruto' => $seg_bruto,
                            'monto_neto' => $seg_neto,
                        );
                    }
                }
            }
        }

        if (!empty($op_codes)) {
            $ops_all[] = array(
                'id' => $oid,
                'fecha' => $op_row['fecha'],
                'proveedor_nombre' => $op_row['proveedor_nombre'],
                'codes' => $op_codes,
            );
        }
    }
}

/**
 * Calcula compromisos, causados y pagos para una partida específica respetando:
 * - Overrides de matriz_numeros_registro (monto_compromiso_custom, monto_pago_custom)
 * - Reglas de IVA si la partida es 403-18-01-00 (25% / 100% / SENIAT)
 */
function calcular_ejecucion_partida_rep($ops_all, $variants, $matriz_overrides, $es_iva, $date_month_from, $date_month_to, $date_year_from)
{
    $comp_mensual = 0.0;
    $comp_acumulado = 0.0;
    $gc_acumulado = 0.0;
    $pago_acumulado = 0.0;

    $variants_lower = array_map('strtolower', $variants);

    foreach ($ops_all as $op) {
        $op_fecha = $op['fecha'];
        if ($op_fecha < $date_year_from || $op_fecha > $date_month_to)
            continue;

        $oid = $op['id'];
        $op_mes = (int) date('n', strtotime($op_fecha));
        $prov = isset($op['proveedor_nombre']) ? $op['proveedor_nombre'] : '';

        $op_monto_bruto = 0.0;
        $op_monto_neto = 0.0;
        $matched = false;

        foreach ($op['codes'] as $oc) {
            $s = strtolower($oc['short']);
            $l = strtolower($oc['long']);
            $d = strtolower($oc['dot']);
            foreach ($variants_lower as $v) {
                if ($s === $v || $l === $v || $d === $v) {
                    $matched = true;
                    $op_monto_bruto += $oc['monto_bruto'];
                    $op_monto_neto += $oc['monto_neto'];
                    break;
                }
            }
        }

        if ($matched) {
            $custom_comp = get_matriz_override_val($matriz_overrides, 'comp', $variants, $oid, $op_mes);
            $custom_pago = get_matriz_override_val($matriz_overrides, 'pago', $variants, $oid, $op_mes);

            $m_comp = ($custom_comp !== null) ? $custom_comp : $op_monto_bruto;
            $m_pago = ($custom_pago !== null) ? $custom_pago : ($es_iva ? $m_comp : $op_monto_neto);

            if ($op_fecha >= $date_month_from && $op_fecha <= $date_month_to) {
                $comp_mensual += $m_comp;
            }
            $comp_acumulado += $m_comp;
            $pago_acumulado += $m_pago;

            if ($es_iva) {
                $tipo_prov = iva_tipo_proveedor_rep($prov);
                if ($tipo_prov === 'estatal') {
                    $gc = $m_comp;
                } elseif ($tipo_prov === 'seniat') {
                    $gc = $m_pago;
                } else {
                    $gc = $m_comp * 0.25;
                }
                $gc_acumulado += $gc;
            } else {
                $gc_acumulado += $m_comp;
            }
        }
    }

    return array(
        'comp_mensual' => $comp_mensual,
        'comp_acumulado' => $comp_acumulado,
        'gastos_causados' => $gc_acumulado,
        'pago_acumulado' => $pago_acumulado,
    );
}

/* ── Para cada partida, calcular valores dinámicos ── */
$rows = array();
foreach ($partidas as $p) {
    $code_variants = get_matching_code_variants($p['codificacion']);
    $es_iva_p = (strpos($p['codificacion'], '403-18-01') !== false);

    /* Crédito Original — fuente única: partidas.credito_original */
    $credito_aprobado = (float) $p['credito_original_real'];

    /* Crédito Adicional (único override que se mantiene editable) */
    $credito_adicional = ($p['credito_adicional_override'] !== null)
        ? (float) $p['credito_adicional_override']
        : 0.0;

    /* Crédito Actualizado — calculado siempre desde los datos reales */
    $credito_actualizado = $credito_aprobado + $credito_adicional
        + (float) $p['aumentos'] - (float) $p['disminuciones'];

    /* ── Cálculo unificado con overrides de matriz_numeros_registro y reglas IVA ── */
    $calc_res = calcular_ejecucion_partida_rep(
        $ops_all,
        $code_variants,
        $matriz_overrides,
        $es_iva_p,
        $mes_inicio_str,
        $mes_fin_str,
        $anio_inicio_str
    );

    $comp_mensual = $calc_res['comp_mensual'];
    $comp_acumulado = $calc_res['comp_acumulado'];
    $gastos_causados = $calc_res['gastos_causados'];
    $pago_acumulado = $calc_res['pago_acumulado'];

    /* % Gastos Causados */
    $pct_gastos = ($credito_actualizado != 0)
        ? ($gastos_causados / $credito_actualizado) * 100
        : 0.0;

    /* Compromisos por Pagar Acumulado (Compromiso Acumulado − Gastos Causados Acumulado)
       Solo aplica con saldo en:
       - 01-08-00-00-51-403-18-01-00 (IVA)
       - 01-08-00-00-51-403-18-99-00 (SAT / Otros Impuestos Indirectos)
       Cualquier otra partida es 0.00 */
    $es_partida_con_comp_pagar = (strpos($p['codificacion'], '403-18-01') !== false || strpos($p['codificacion'], '403-18-99') !== false);
    if ($es_partida_con_comp_pagar) {
        if (isset($ret_ovr_map[$p['codificacion']])) {
            $comp_pagar = $ret_ovr_map[$p['codificacion']];
        } else {
            $comp_pagar = max(0.0, $comp_acumulado - $gastos_causados);
        }
    } else {
        $comp_pagar = 0.0;
    }

    /* Disponibilidad */
    $disponibilidad = $credito_actualizado - $comp_acumulado;

    /* % Disponible */
    $pct_disponible = ($credito_actualizado != 0)
        ? ($disponibilidad / $credito_actualizado) * 100
        : 0.0;

    $grupo = get_grupo_partida($p['codificacion']);

    $rows[] = array(
        'id' => $p['id'],
        'codificacion' => $p['codificacion'],
        'denominacion' => $p['denominacion'],
        'grupo' => $grupo,
        'credito_aprobado' => $credito_aprobado,
        'credito_adicional' => $credito_adicional,
        'aumentos' => (float) $p['aumentos'],
        'disminuciones' => (float) $p['disminuciones'],
        'credito_actualizado' => $credito_actualizado,
        'compromiso_mensual' => $comp_mensual,
        'compromiso_acumulado' => $comp_acumulado,
        'gastos_causados' => $gastos_causados,
        'pct_gastos' => $pct_gastos,
        'pago_acumulado' => $pago_acumulado,
        'comp_pagar' => $comp_pagar,
        'disponibilidad' => $disponibilidad,
        'pct_disponible' => $pct_disponible,
        /* Solo los overrides editables */
        'credito_adicional_override' => $p['credito_adicional_override'],
    );
}


/* ── Agrupar filas por categoría ── */
$grouped_rows = array();
$group_subtotals = array();
$group_labels = array(
    'GASTOS DE PERSONAL' => 'TOTAL GASTOS DE PERSONAL',
    'MATERIALES Y SUMINISTROS' => 'TOTAL GTOS. MAT. Y SUMINISTROS',
    'SERVICIOS NO PERSONALES' => 'TOTAL SERVICIOS. NO PERSONALES',
    'ACTIVOS REALES' => 'TOTAL ACTIVOS REALES',
    'TRANSFERENCIAS Y DONACIONES' => 'TOTAL TRANSFERENCIAS Y DONACIONES',
    'OTRAS PARTIDAS' => 'TOTAL OTRAS PARTIDAS',
);

$grand_total = array(
    'credito_aprobado' => 0,
    'credito_adicional' => 0,
    'aumentos' => 0,
    'disminuciones' => 0,
    'credito_actualizado' => 0,
    'compromiso_mensual' => 0,
    'compromiso_acumulado' => 0,
    'gastos_causados' => 0,
    'pago_acumulado' => 0,
    'comp_pagar' => 0,
    'disponibilidad' => 0,
);

foreach ($rows as $r) {
    $grp = $r['grupo'];
    if (!isset($grouped_rows[$grp])) {
        $grouped_rows[$grp] = array();
        $group_subtotals[$grp] = array(
            'credito_aprobado' => 0,
            'credito_adicional' => 0,
            'aumentos' => 0,
            'disminuciones' => 0,
            'credito_actualizado' => 0,
            'compromiso_mensual' => 0,
            'compromiso_acumulado' => 0,
            'gastos_causados' => 0,
            'pago_acumulado' => 0,
            'comp_pagar' => 0,
            'disponibilidad' => 0,
        );
    }
    $grouped_rows[$grp][] = $r;
    $group_subtotals[$grp]['credito_aprobado'] += $r['credito_aprobado'];
    $group_subtotals[$grp]['credito_adicional'] += $r['credito_adicional'];
    $group_subtotals[$grp]['aumentos'] += $r['aumentos'];
    $group_subtotals[$grp]['disminuciones'] += $r['disminuciones'];
    $group_subtotals[$grp]['credito_actualizado'] += $r['credito_actualizado'];
    $group_subtotals[$grp]['compromiso_mensual'] += $r['compromiso_mensual'];
    $group_subtotals[$grp]['compromiso_acumulado'] += $r['compromiso_acumulado'];
    $group_subtotals[$grp]['gastos_causados'] += $r['gastos_causados'];
    $group_subtotals[$grp]['pago_acumulado'] += $r['pago_acumulado'];
    $group_subtotals[$grp]['comp_pagar'] += $r['comp_pagar'];
    $group_subtotals[$grp]['disponibilidad'] += $r['disponibilidad'];

    $grand_total['credito_aprobado'] += $r['credito_aprobado'];
    $grand_total['credito_adicional'] += $r['credito_adicional'];
    $grand_total['aumentos'] += $r['aumentos'];
    $grand_total['disminuciones'] += $r['disminuciones'];
    $grand_total['credito_actualizado'] += $r['credito_actualizado'];
    $grand_total['compromiso_mensual'] += $r['compromiso_mensual'];
    $grand_total['compromiso_acumulado'] += $r['compromiso_acumulado'];
    $grand_total['gastos_causados'] += $r['gastos_causados'];
    $grand_total['pago_acumulado'] += $r['pago_acumulado'];
    $grand_total['comp_pagar'] += $r['comp_pagar'];
    $grand_total['disponibilidad'] += $r['disponibilidad'];
}

/* ── Nombres de meses en español ── */
$meses_es = array(
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
);
$nombre_mes = isset($meses_es[$mes_sel]) ? $meses_es[$mes_sel] : $mes_sel;

$active = 'ejecucion';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Ejecución del Presupuesto de Gastos — Contraloría del Municipio Simón Rodríguez">
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Ejecución Presupuestaria — Contraloría MSR</title>
    <style>
        /* ── Typography ─────────────────────────────────────────────── */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .page-title,
        .section-title,
        .card-title,
        .panel-title,
        .hdr-title,
        .brand-title,
        .brand-sub,
        .sb-section-label,
        .sb-parent-label,
        .sb-user-name,
        .sb-user-badge {
            font-family: 'IBM Plex Sans', sans-serif !important;
        }

        /* ── Reset ───────────────────────────────────────────────────── */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            background: #f0f2f5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ── Header ─────────────────────────────────────────────────── */
        .site-header {
            background: #070707;
            color: white;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 8px 20px;
            border-bottom: 3px solid #0d47a1;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .site-header img {
            height: 52px;
            width: auto;
            display: block;
        }

        .site-header .brand {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .site-header .brand-title {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: .7px;
            text-transform: uppercase;
        }

        .site-header .brand-sub {
            font-size: 12.5px;
            color: #bbdefb;
            letter-spacing: .2px;
        }

        .site-header .header-right {
            margin-left: auto;
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .site-header .hdr-date {
            font-size: 13px;
            font-weight: 700;
            color: #fff;
        }

        .site-header .hdr-sub {
            font-size: 10.5px;
            color: #94a3b8;
        }

        /* ── Layout ──────────────────────────────────────────────────── */
        .layout {
            display: -ms-flexbox;
            display: flex;
            -ms-flex: 1 1 auto;
            flex: 1;
            /* IE11: prevent flex children from collapsing */
            min-height: 0;
        }

        .main-content {
            -ms-flex: 1 1 auto;
            flex: 1;
            padding: 32px 36px;
            /* IE11: overflow-x on a flex child without explicit height collapses
               the height — use overflow:visible here and let .table-scroll-wrap
               handle horizontal scroll instead */
            overflow-x: visible;
            min-height: 0;
            min-width: 0;
        }

        /* ── Barra de título / Banner (Top Bar) ────────────────────────── */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-image: url('/sistema/assets/img/banner.png');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 18px 24px;
            margin-bottom: 24px;
            border-radius: 14px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.25);
            position: relative;
            overflow: hidden;
            min-height: 80px;
            flex-wrap: wrap;
            gap: 14px;
        }

        .top-bar::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(15, 50, 150, 0.82) 0%, rgba(10, 30, 100, 0.55) 100%);
            border-radius: inherit;
            pointer-events: none;
        }

        .top-bar>* {
            position: relative;
            z-index: 1;
        }

        .top-bar h1 {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            color: #ffffff;
            margin: 0;
        }

        .top-bar p {
            font-size: 11.5px;
            opacity: 0.85;
            margin-top: 3px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 0;
        }

        .btn-nueva {
            background: rgba(255, 255, 255, 0.95);
            color: #1e3a8a;
            border: 1px solid rgba(255, 255, 255, 0.6);
            padding: 7px 15px;
            font-size: 11.5px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
            transition: all 0.15s ease;
            white-space: nowrap;
            font-family: inherit;
        }

        .btn-nueva:hover {
            background: #ffffff;
            color: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .btn-nueva-secondary {
            background: rgba(15, 23, 42, 0.45);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .btn-nueva-secondary:hover {
            background: rgba(15, 23, 42, 0.7);
            color: #38bdf8;
            border-color: #38bdf8;
        }

        /* ── Barra de Herramientas y Filtros Unificada ────────────────────── */
        .ejec-toolbar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        }

        .ejec-filter-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 0;
        }

        .ejec-filter-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .ejec-select-wrap {
            position: relative;
        }

        .ejec-select {
            padding: 6px 12px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            border: 1.5px solid #cbd5e1;
            border-radius: 7px;
            background: #f8fafc;
            color: #0f172a;
            outline: none;
            transition: all 0.15s ease;
            cursor: pointer;
        }

        .ejec-select:focus {
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .ejec-btn-apply {
            background: #0f172a;
            color: #ffffff;
            border: 1px solid #1e293b;
            padding: 6px 12px;
            font-size: 11.5px;
            font-weight: 600;
            border-radius: 7px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: inherit;
            transition: all 0.15s ease;
        }

        .ejec-btn-apply:hover {
            background: #1e293b;
            color: #38bdf8;
            transform: translateY(-1px);
        }

        .ejec-tools-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .ejec-tool-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            font-size: 11.5px;
            font-weight: 600;
            border-radius: 7px;
            cursor: pointer;
            text-decoration: none;
            color: #334155;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            transition: all 0.15s ease;
            font-family: inherit;
            white-space: nowrap;
        }

        .ejec-tool-btn:hover {
            background: #e2e8f0;
            color: #0f172a;
            border-color: #94a3b8;
            transform: translateY(-1px);
        }

        .ejec-tool-btn-print {
            background: #fef2f2;
            color: #b91c1c;
            border-color: #fecaca;
        }

        .ejec-tool-btn-print:hover {
            background: #dc2626;
            color: #ffffff;
            border-color: #b91c1c;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
        }

        .ejec-tool-btn-seed {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .ejec-tool-btn-seed:hover {
            background: #2563eb;
            color: #ffffff;
            border-color: #1d4ed8;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
        }

        /* ── Table wrapper ───────────────────────────────────────────── */
        .table-wrapper {
            background: #ffffff;
            border: 1px solid #334155;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, .08);
            overflow: hidden;
            margin-bottom: 32px;
        }

        .table-panel-head {
            padding: 14px 22px;
            border-bottom: 1px solid #334155;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #0f172a;
            color: #f8fafc;
            gap: 16px;
            flex-wrap: wrap;
            width: 100%;
            box-sizing: border-box;
        }

        .table-panel-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-panel-head h2 {
            font-size: 13.5px;
            font-weight: 700;
            color: #f8fafc;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .table-panel-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .table-meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
            color: #94a3b8;
            background: #1e293b;
            border: 1px solid #334155;
        }

        .table-meta-pill strong {
            color: #f8fafc;
            font-weight: 700;
        }

        .table-scroll-wrap {
            overflow-x: auto;
            width: 100%;
            background: #ffffff;
            -webkit-overflow-scrolling: touch;
        }

        /* ── Report table ────────────────────────────────────────────── */
        .ep-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
            min-width: 1950px;
        }

        .ep-table thead tr {
            background: #1e293b;
            color: #fff;
        }

        .ep-table thead th {
            padding: 9px 8px;
            text-align: center;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
            border: 1px solid #334155;
            white-space: nowrap;
        }

        .ep-table thead th.col-codif {
            text-align: left;
            min-width: 220px;
        }

        .ep-table thead th.col-denom {
            text-align: left;
            min-width: 260px;
        }

        /* Sub-header row */
        .ep-table thead tr.sub-head {
            background: #0f172a;
        }

        .ep-table thead tr.sub-head th {
            font-size: 8.5px;
            padding: 6px 8px;
            font-style: italic;
            color: #94a3b8;
        }

        /* ── Group header row ── */
        .ep-table tbody tr.group-row td {
            background: #1e293b;
            color: #38bdf8;
            font-weight: 700;
            font-size: 10.5px;
            padding: 8px 12px;
            text-transform: uppercase;
            letter-spacing: .6px;
            border: 1px solid #334155;
        }

        /* ── Group subtotal row ── */
        .ep-table tbody tr.subtotal-row td {
            background: #ffff00 !important;
            color: #000000 !important;
            font-weight: 700;
            font-size: 10px;
            padding: 8px 8px;
            border-top: 1.5px solid #000000;
            border-bottom: 2px solid #000000;
            border-left: 1px solid #d4d400;
            border-right: 1px solid #d4d400;
            white-space: nowrap;
        }

        .ep-table tbody tr.subtotal-row td.lbl {
            text-align: left;
            font-weight: 800;
            color: #000000 !important;
            letter-spacing: .3px;
        }

        /* ── Data rows ── */
        .ep-table tbody tr.data-row td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            color: #334155;
        }

        .ep-table tbody tr.data-row:nth-child(odd) td {
            background: #ffffff;
        }

        .ep-table tbody tr.data-row:nth-child(even) td {
            background: #f8fafc;
        }

        .ep-table tbody tr.data-row:hover td {
            background: #eff6ff !important;
        }

        .ep-table td.col-codif {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            color: #0f172a;
            font-weight: 800;
            white-space: nowrap;
            letter-spacing: .2px;
        }

        .ep-table td.col-denom,
        .ep-table td.col-denom .denom-label {
            max-width: 280px;
            word-break: break-word;
            font-size: 10.5px;
            color: #0f172a;
            font-weight: 700;
        }

        .ep-table td.num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .ep-table td.pct {
            text-align: center;
            white-space: nowrap;
        }

        .pct-value {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9.5px;
            font-weight: 600;
        }

        .pct-ok {
            background: #ecfdf5;
            color: #047857;
        }

        .pct-warn {
            background: #fffbeb;
            color: #b45309;
        }

        .pct-bad {
            background: #fef2f2;
            color: #b91c1c;
        }

        /* ── Code Picker Trigger Button (Displays ONLY the number) ── */
        .code-picker-btn {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            width: 100%;
            padding: 4px 7px;
            background: #ffffff;
            border: 1px solid #94a3b8;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            font-weight: 800;
            color: #0f172a;
            text-align: left;
            transition: all 0.15s ease;
        }

        .code-picker-btn:hover {
            border-color: #2563eb;
            background: #f8fafc;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, .12);
        }

        .code-picker-btn .picker-chevron {
            color: #64748b;
            flex-shrink: 0;
        }

        /* ── Floating Dropdown Popover ── */
        #global-partida-popover {
            position: fixed;
            display: none;
            z-index: 10002;
            width: 390px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2), 0 8px 10px -6px rgba(15, 23, 42, 0.1);
            overflow: hidden;
        }

        #global-partida-popover.open {
            display: flex;
            flex-direction: column;
        }

        .popover-search-wrap {
            padding: 8px 10px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .popover-search-input {
            width: 100%;
            padding: 7px 10px;
            font-size: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            outline: none;
            font-family: 'Inter', sans-serif;
        }

        .popover-search-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
        }

        .popover-list {
            max-height: 280px;
            overflow-y: auto;
            padding: 4px 0;
        }

        .popover-item {
            padding: 7px 12px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 2px;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.1s ease;
        }

        .popover-item:last-child {
            border-bottom: none;
        }

        .popover-item:hover {
            background: #eff6ff;
        }

        .popover-item-code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            font-weight: 700;
            color: #1e40af;
        }

        .popover-item-denom {
            font-size: 10px;
            color: #475569;
        }

        /* ── Editable inputs in table ── */
        .ovr-input {
            width: 86px;
            padding: 4px 5px;
            font-family: inherit;
            font-size: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #f8fafc;
            color: #1e293b;
            text-align: right;
            transition: border-color .15s, box-shadow .15s;
        }

        .ovr-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, .15);
            outline: none;
            background: #fff;
        }

        .ovr-input.has-override {
            background: #fef9c3;
            border-color: #f59e0b;
            font-weight: 600;
        }

        .ovr-input.num-main {
            font-weight: 600;
            background: #fff;
            border-color: #93c5fd;
        }

        .save-row-btn {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 4px 8px;
            font-size: 9.5px;
            font-weight: 600;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            background: #2563eb;
            color: #fff;
            font-family: inherit;
            transition: background .15s;
            white-space: nowrap;
        }

        .save-row-btn:hover {
            background: #1d4ed8;
        }

        .delete-row-btn {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 4px 7px;
            font-size: 9.5px;
            font-weight: 600;
            border: 1px solid #fecaca;
            border-radius: 5px;
            cursor: pointer;
            background: #b91c1c;
            color: #f9f3f3ff;
            font-family: inherit;
            transition: all .15s;
            white-space: nowrap;
            margin-left: 3px;
        }

        .delete-row-btn:hover {
            background: #fee2e2;
            border-color: #f87171;
        }

        /* ── Add new row button bar ── */
        .add-row-bar {
            padding: 12px 18px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .btn-add-row {
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 7px 16px;
            font-size: 11.5px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            transition: background .15s;
        }

        .btn-add-row:hover {
            background: #0369a1;
        }

        /* ── Footer ──────────────────────────────────────────────────── */
        .site-footer {
            background: #080616;
            color: #90a4ae;
            text-align: center;
            padding: 10px 20px;
            font-size: 10px;
            border-top: 3px solid #1565c0;
            line-height: 1.8;
        }

        .site-footer strong {
            color: #e3f2fd;
        }

        /* ── No datos ────────────────────────────────────────────────── */
        .sin-datos {
            text-align: center;
            color: #94a3b8;
            padding: 56px 20px;
            font-size: 13px;
        }

        .sin-datos svg {
            margin: 0 auto 16px;
            display: block;
            color: #cbd5e1;
        }

        /* ── Totals row ──────────────────────────────────────────────── */
        .ep-table tfoot tr td {
            background: #ffffff !important;
            color: #000000 !important;
            font-weight: 800 !important;
            font-size: 10.5px !important;
            padding: 8px 8px !important;
            border: 1.5px solid #000000 !important;
            text-align: right;
            white-space: nowrap;
        }

        .ep-table tfoot tr td.lbl {
            text-align: left !important;
            font-size: 10.5px !important;
            font-weight: 800 !important;
            color: #000000 !important;
            letter-spacing: .4px !important;
        }

        .ep-table tfoot tr.total-gastos-row td {
            background: #ffffff !important;
            color: #000000 !important;
            font-weight: 800 !important;
            border-top: 2px solid #000000 !important;
        }

        .ep-table tfoot tr.total-cancelado-row td {
            background: #ffffff !important;
            color: #000000 !important;
            font-weight: 800 !important;
            border-bottom: 2px solid #000000 !important;
        }

        /* ── Info badge ──────────────────────────────────────────────── */
        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 10px;
            font-weight: 600;
            background: #eff6ff;
            color: #1e40af;
        }

        /* ── Print suppression & Color Preservation ───────────────────── */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .no-print {
                display: none !important;
            }

            .site-header {
                display: none !important;
            }

            #sidebar,
            .sidebar {
                display: none !important;
            }

            .layout {
                display: block !important;
            }

            .main-content {
                padding: 0 !important;
            }
        }

        /* ── Modo pantalla completa para llenar los campos ── */
        body.fullscreen-mode {
            overflow: hidden;
        }

        body.fullscreen-mode .site-header,
        body.fullscreen-mode .layout .sidebar,
        body.fullscreen-mode .page-title,
        body.fullscreen-mode .page-subtitle,
        body.fullscreen-mode .filter-card,
        body.fullscreen-mode .action-row,
        body.fullscreen-mode .top-bar,
        body.fullscreen-mode .ejec-header,
        body.fullscreen-mode .ejec-toolbar,
        body.fullscreen-mode .site-footer {
            display: none !important;
        }

        body.fullscreen-mode .layout {
            display: block;
        }

        body.fullscreen-mode .main-content {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 10000;
            background: #ffffff;
            padding: 12px;
            overflow: auto;
        }

        body.fullscreen-mode .table-wrapper {
            margin-bottom: 0;
            box-shadow: none;
            border: 1px solid #e2e8f0;
        }

        body.fullscreen-mode .ep-table {
            min-width: 1750px;
        }

        #fullscreen-actions {
            display: none;
            position: fixed;
            top: 14px;
            right: 16px;
            z-index: 10001;
            gap: 8px;
        }

        body.fullscreen-mode #fullscreen-actions {
            display: flex;
            align-items: center;
        }

        #btn-salir-fullscreen,
        .fs-btn {
            background: #0f172a;
            color: #fff;
            border: 1px solid #1e293b;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            font-family: inherit;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        #btn-salir-fullscreen:hover,
        .fs-btn:hover {
            background: #1e293b;
        }

        /* ── Flechas flotantes de navegación horizontal (Solo 2 flechas) ── */
        .table-sticky-nav {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 999;
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(15, 23, 42, 0.88);
            border: 1px solid rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 5px 8px;
            border-radius: 40px;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
        }

        .nav-btn-arrow {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.16);
            color: #ffffff;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
            outline: none;
        }

        .nav-btn-arrow:hover {
            background: #2563eb;
            border-color: #3b82f6;
            color: #ffffff;
            transform: scale(1.08);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
        }

        .nav-btn-arrow:active {
            transform: scale(0.94);
        }
    </style>
</head>

<body>

    <!-- ═══ HEADER ═══════════════════════════════════════════════════════════ --><!-- ═══ LAYOUT ════════════════════════════════════════════════════════════ -->
    <div class="layout">

        <!-- SIDEBAR -->
        <?php require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <!-- MAIN -->
        <main class="main-content">

            <!-- ══ FLOATING EXIT BUTTON FOR FULLSCREEN MODE ═══════════════════════ -->
            <div id="fullscreen-actions">
                <button type="button" id="btn-salir-fullscreen" onclick="toggleFullscreenMode()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    Salir de Pantalla Completa (Esc)
                </button>
            </div>

            <!-- ══ BARRA DE TÍTULO Y BOTÓN DE ACCIÓN ═════════════════════════════ -->
            <div class="top-bar no-print">
                <div>
                    <h1>EJECUCIÓN DEL PRESUPUESTO DE GASTOS</h1>
                    <p>Reporte Mensual &mdash; Mes de <?php echo htmlspecialchars($nombre_mes . ' de ' . $anio_sel); ?></p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                    <a href="listado.php" class="btn-nueva btn-nueva-secondary" title="Volver al Listado General">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                        LISTADO DE EJECUCIONES
                    </a>
                    <?php if ($es_admin): ?>
                        <button type="button" class="btn-nueva" onclick="addNewTableRow()" title="Agregar nueva partida">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            + AGREGAR PARTIDA
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── BARRA DE HERRAMIENTAS Y ACCIONES UNIFICADA ── -->
            <div class="ejec-toolbar no-print">
                <!-- Lado Izquierdo: Filtro Mes / Año -->
                <form class="ejec-filter-form" method="GET" action="index.php">
                    <div class="ejec-filter-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                        <span>Período:</span>
                    </div>
                    <div class="ejec-select-wrap">
                        <select name="mes" id="sel-mes" class="ejec-select" onchange="this.form.submit()">
                            <?php foreach ($meses_es as $n => $nm): ?>
                                <option value="<?php echo $n; ?>" <?php echo ($n == $mes_sel ? ' selected' : ''); ?>>
                                    <?php echo htmlspecialchars($nm); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ejec-select-wrap">
                        <select name="anio" id="sel-anio" class="ejec-select" onchange="this.form.submit()">
                            <?php for ($y = 2024; $y <= 2030; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo ($y == $anio_sel ? ' selected' : ''); ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="ejec-btn-apply" id="btn-aplicar-filtro" title="Actualizar vista con el período seleccionado">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        Filtrar
                    </button>
                </form>

                <!-- Lado Derecho: Acciones y Herramientas del Reporte -->
                <div class="ejec-tools-group">
                    <a href="matriz.php?mes=<?php echo $mes_sel; ?>&anio=<?php echo $anio_sel; ?>" class="ejec-tool-btn" id="btn-vista-individual" title="Ver registro detallado por partida individual">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="16" y1="13" x2="8" y2="13" />
                            <line x1="16" y1="17" x2="8" y2="17" />
                        </svg>
                        <span>Vista Individual</span>
                    </a>

                    <button type="button" class="ejec-tool-btn" id="btn-pantalla-completa" onclick="toggleFullscreenMode()" title="Ampliar tabla a pantalla completa para edición rápida">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 3H5a2 2 0 0 0-2 2v3" />
                            <path d="M21 8V5a2 2 0 0 0-2-2h-3" />
                            <path d="M3 16v3a2 2 0 0 0 2 2h3" />
                            <path d="M16 21h3a2 2 0 0 0 2-2v-3" />
                        </svg>
                        <span>Pantalla Completa</span>
                    </button>

                    <a href="imprimir.php?mes=<?php echo $mes_sel; ?>&anio=<?php echo $anio_sel; ?>" class="ejec-tool-btn ejec-tool-btn-print" id="btn-imprimir" target="_blank" title="Imprimir o Exportar PDF oficial">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 6 2 18 2 18 9" />
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                            <rect x="6" y="14" width="12" height="8" />
                        </svg>
                        <span>Imprimir / PDF</span>
                    </a>

                    <?php if ($es_admin): ?>
                        <a href="index.php?mes=<?php echo $mes_sel; ?>&anio=<?php echo $anio_sel; ?>&precargar=1"
                            class="ejec-tool-btn ejec-tool-btn-seed" id="btn-precargar-catalogo"
                            onclick="return confirm('¿Desea precargar las 165 partidas del catálogo presupuestario para <?php echo htmlspecialchars($nombre_mes . ' ' . $anio_sel); ?>?');"
                            title="Precargar automáticamente las 165 partidas del catálogo oficial">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                <polyline points="7 10 12 15 17 10" />
                                <line x1="12" y1="15" x2="12" y2="3" />
                            </svg>
                            <span>Precargar (165)</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Tabla principal ── -->
            <div class="table-wrapper">
                <div class="table-panel-head no-print">
                    <div class="table-panel-left">
                        <h2>
                            <svg style="display:inline;vertical-align:-3px;color:#38bdf8;" width="16" height="16"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <line x1="3" y1="9" x2="21" y2="9" />
                                <line x1="3" y1="15" x2="21" y2="15" />
                                <line x1="9" y1="3" x2="9" y2="21" />
                                <line x1="15" y1="3" x2="15" y2="21" />
                            </svg>
                            &nbsp;Ejecución del Presupuesto de Gastos — Mes de
                            <?php echo htmlspecialchars($nombre_mes . ' de ' . $anio_sel); ?>
                        </h2>
                    </div>
                    <div class="table-panel-right">
                        <span class="table-meta-pill">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                            Partidas: <strong><?php echo count($rows); ?></strong>
                        </span>
                        <span class="table-meta-pill">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            Año Fiscal: <strong><?php echo $anio_sel; ?></strong>
                        </span>
                    </div>
                </div>

                <div class="table-scroll-wrap" id="table-scroll-wrap">

                <?php if (count($rows) === 0): ?>
                    <div class="sin-datos" id="empty-state-box">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <line x1="3" y1="9" x2="21" y2="9" />
                            <line x1="3" y1="15" x2="21" y2="15" />
                            <line x1="9" y1="3" x2="9" y2="21" />
                            <line x1="15" y1="3" x2="15" y2="21" />
                        </svg>
                        No hay partidas registradas para
                        <strong><?php echo htmlspecialchars($nombre_mes . ' ' . $anio_sel); ?></strong>.<br>
                        <?php if ($es_admin): ?>
                            <div style="margin-top:16px; display:inline-flex; gap:10px;">
                                <button type="button" onclick="addNewTableRow()" class="btn-primary">
                                    + Agregar Primera Partida
                                </button>
                                <a href="index.php?mes=<?php echo $mes_sel; ?>&anio=<?php echo $anio_sel; ?>&precargar=1"
                                    class="btn-seed">
                                    Precargar Catálogo Completo (165)
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php
                function fmt($v)
                {
                    return number_format((float) $v, 2, '.', ',');
                }
                function pct_class($pct)
                {
                    if ($pct >= 80)
                        return 'pct-bad';
                    if ($pct >= 50)
                        return 'pct-warn';
                    return 'pct-ok';
                }
                ?>

                <table class="ep-table" id="ep-main-table"
                    style="<?php echo count($rows) === 0 ? 'display:none;' : ''; ?>">
                    <thead>
                        <tr>
                            <th class="col-codif" rowspan="2">Codificación<br>Presupuestaria</th>
                            <th class="col-denom" rowspan="2">Denominación</th>
                            <th rowspan="2">Crédito
                                Aprobado<br><?php echo htmlspecialchars($nombre_mes . ' ' . $anio_sel); ?></th>
                            <th rowspan="2">Crédito<br>Adicional</th>
                            <th colspan="2">Traspasos de Partidas</th>
                            <th rowspan="2">Crédito<br>Actualizado</th>
                            <th rowspan="2">Compromiso<br>Mensual</th>
                            <th rowspan="2">Compromiso<br>Acumulado</th>
                            <th rowspan="2">Gastos Causados<br>Acumulado</th>
                            <th rowspan="2">% Gastos<br>Causados</th>
                            <th rowspan="2">Pago<br>Acumulado</th>
                            <th rowspan="2">Compromisos<br>por Pagar<br>Acumulado</th>
                            <th rowspan="2">Disponibilidad</th>
                            <th rowspan="2">% Disponible</th>
                            <?php if ($es_admin): ?>
                                <th rowspan="2" class="no-print">Acciones</th>
                            <?php endif; ?>
                        </tr>
                        <tr class="sub-head">
                            <th>Aumentos</th>
                            <th>Disminuciones</th>
                        </tr>
                    </thead>
                    <tbody id="ep-table-body">
                        <?php
                        /* Groups whose group-header label row is hidden; data rows and subtotal still show */
                        $hidden_groups = array('GASTOS DE PERSONAL', 'MATERIALES Y SUMINISTROS', 'SERVICIOS NO PERSONALES', 'TRANSFERENCIAS Y DONACIONES', 'ACTIVOS REALES');
                        ?>
                        <?php foreach ($grouped_rows as $grp_name => $grp_items): ?>
                            <?php $is_hidden_group = in_array($grp_name, $hidden_groups); ?>
                            <!-- Grupo Header Row (skip for hidden groups) -->
                            <?php if (!$is_hidden_group): ?>
                                <tr class="group-row" data-group-header="<?php echo htmlspecialchars($grp_name); ?>">
                                    <td colspan="<?php echo $es_admin ? 16 : 15; ?>">
                                        <?php echo htmlspecialchars($grp_name); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($grp_items as $r): ?>
                                <tr class="data-row" id="row-<?php echo $r['id']; ?>" data-row-id="<?php echo $r['id']; ?>">
                                    <!-- Codificación (Solo el número + Trigger de cambio de partida) -->
                                    <td class="col-codif">
                                        <?php if ($es_admin): ?>
                                            <button type="button" class="code-picker-btn" id="code-btn-<?php echo $r['id']; ?>"
                                                onclick="openPartidaPicker(this, <?php echo $r['id']; ?>)"
                                                title="Haz clic para seleccionar otra partida">
                                                <span class="code-val"
                                                    id="code-display-<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['codificacion']); ?></span>
                                                <svg class="picker-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <polyline points="6 9 12 15 18 9" />
                                                </svg>
                                            </button>
                                            <input type="hidden" data-row-id="<?php echo $r['id']; ?>" data-field="codificacion"
                                                value="<?php echo htmlspecialchars($r['codificacion']); ?>">
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($r['codificacion']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Denominación -->
                                    <td class="col-denom" id="denom-text-<?php echo $r['id']; ?>">
                                        <?php if ($es_admin): ?>
                                            <input type="hidden" data-row-id="<?php echo $r['id']; ?>" data-field="denominacion"
                                                value="<?php echo htmlspecialchars($r['denominacion']); ?>">
                                            <span class="denom-label"><?php echo htmlspecialchars($r['denominacion']); ?></span>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($r['denominacion']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Crédito Aprobado (Editable) -->
                                    <td class="num">
                                        <?php if ($es_admin): ?>
                                            <input type="text" inputmode="decimal" name="credito_aprobado"
                                                class="ovr-input num-main auto-money" data-row-id="<?php echo $r['id']; ?>"
                                                data-field="credito_aprobado" value="<?php echo fmt($r['credito_aprobado']); ?>">
                                        <?php else: ?>
                                            <?php echo fmt($r['credito_aprobado']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Crédito Adicional -->
                                    <td class="num">
                                        <?php if ($es_admin): ?>
                                            <input type="text" inputmode="decimal" name="credito_adicional_override"
                                                class="ovr-input auto-money<?php echo ($r['credito_adicional_override'] !== null ? ' has-override' : ''); ?>"
                                                data-row-id="<?php echo $r['id']; ?>" data-field="credito_adicional_override"
                                                value="<?php echo ($r['credito_adicional_override'] !== null ? fmt($r['credito_adicional_override']) : ''); ?>"
                                                placeholder="<?php echo fmt($r['credito_adicional']); ?>">
                                        <?php else: ?>
                                            <?php echo fmt($r['credito_adicional']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Aumentos (Editable) -->
                                    <td class="num">
                                        <?php if ($es_admin): ?>
                                            <input type="text" inputmode="decimal" name="aumentos" class="ovr-input auto-money"
                                                data-row-id="<?php echo $r['id']; ?>" data-field="aumentos"
                                                value="<?php echo fmt($r['aumentos']); ?>">
                                        <?php else: ?>
                                            <?php echo fmt($r['aumentos']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Disminuciones (Editable) -->
                                    <td class="num">
                                        <?php if ($es_admin): ?>
                                            <input type="text" inputmode="decimal" name="disminuciones" class="ovr-input auto-money"
                                                data-row-id="<?php echo $r['id']; ?>" data-field="disminuciones"
                                                value="<?php echo fmt($r['disminuciones']); ?>">
                                        <?php else: ?>
                                            <?php echo fmt($r['disminuciones']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Crédito Actualizado -->
                                    <!-- Crédito Actualizado (read-only, calculado) -->
                                    <td class="num"><?php echo fmt($r['credito_actualizado']); ?></td>
                                    <!-- Compromiso Mensual (read-only, desde OPs) -->
                                    <td class="num"><?php echo fmt($r['compromiso_mensual']); ?></td>
                                    <!-- Compromiso Acumulado (read-only, desde OPs YTD) -->
                                    <td class="num"><?php echo fmt($r['compromiso_acumulado']); ?></td>
                                    <!-- Gastos Causados Acumulado (= Compromiso Acumulado) -->
                                    <td class="num"><?php echo fmt($r['gastos_causados']); ?></td>
                                    <!-- % Gastos Causados -->
                                    <td class="pct" style="text-align:center;">0.00%</td>
                                    <!-- Pago Acumulado (read-only, monto_neto_pagar YTD) -->
                                    <td class="num"><?php echo fmt($r['pago_acumulado']); ?></td>
                                    <!-- Compromisos por Pagar -->
                                    <td class="num"><?php echo fmt($r['comp_pagar']); ?></td>
                                    <!-- Disponibilidad -->
                                    <td class="num"><?php echo fmt($r['disponibilidad']); ?></td>
                                    <!-- % Disponible -->
                                    <td class="pct">
                                        <span class="pct-value <?php echo pct_class(100 - $r['pct_disponible']); ?>">
                                            <?php echo number_format($r['pct_disponible'], 2, '.', ',') . '%'; ?>
                                        </span>
                                    </td>
                                    <!-- Acciones (admin) -->
                                    <?php if ($es_admin): ?>
                                        <td class="no-print" style="white-space:nowrap;">
                                            <a href="matriz.php?cod=<?php echo urlencode($r['codificacion']); ?>&amp;mes=<?php echo $mes_sel; ?>&amp;anio=<?php echo $anio_sel; ?>"
                                                class="save-row-btn"
                                                style="background:#0f172a; text-decoration:none; margin-right:3px;"
                                                title="Ver Registro de Ejecución Individual">
                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                            </a>
                                            <button type="button" class="save-row-btn" data-row-id="<?php echo $r['id']; ?>"
                                                id="save-btn-<?php echo $r['id']; ?>"
                                                onclick="saveRowOverrides(<?php echo $r['id']; ?>)">
                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                                                    <polyline points="17 21 17 13 7 13 7 21" />
                                                    <polyline points="7 3 7 8 15 8" />
                                                </svg>
                                                Guardar
                                            </button>
                                            <button type="button" class="delete-row-btn"
                                                onclick="deleteTableRow(<?php echo $r['id']; ?>)" title="Eliminar partida">
                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6" />
                                                    <path
                                                        d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                </svg>
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Subtotal de Grupo -->
                            <?php
                            $sub = $group_subtotals[$grp_name];
                            $sub_pct_gastos = ($sub['credito_actualizado'] != 0) ? ($sub['gastos_causados'] / $sub['credito_actualizado'] * 100) : 0;
                            $sub_pct_dispon = ($sub['credito_actualizado'] != 0) ? ($sub['disponibilidad'] / $sub['credito_actualizado'] * 100) : 0;
                            $lbl_subtotal = isset($group_labels[$grp_name]) ? $group_labels[$grp_name] : ('TOTAL ' . $grp_name);
                            /* For hidden groups, only show subtotal when there are associated partidas */
                            $show_subtotal = !$is_hidden_group || !empty($grp_items);
                            ?>
                            <?php if ($show_subtotal): ?>
                                <tr class="subtotal-row" data-subtotal-for="<?php echo htmlspecialchars($grp_name); ?>">
                                    <td class="lbl" colspan="2"><?php echo htmlspecialchars($lbl_subtotal); ?></td>
                                    <td class="num"><?php echo fmt($sub['credito_aprobado']); ?></td>
                                    <td class="num"><?php echo fmt($sub['credito_adicional']); ?></td>
                                    <td class="num"><?php echo fmt($sub['aumentos']); ?></td>
                                    <td class="num"><?php echo fmt($sub['disminuciones']); ?></td>
                                    <td class="num"><?php echo fmt($sub['credito_actualizado']); ?></td>
                                    <td class="num"><?php echo fmt($sub['compromiso_mensual']); ?></td>
                                    <td class="num"><?php echo fmt($sub['compromiso_acumulado']); ?></td>
                                    <td class="num"><?php echo fmt($sub['gastos_causados']); ?></td>
                                    <td style="text-align:center;">0.00%</td>
                                    <td class="num"><?php echo fmt($sub['pago_acumulado']); ?></td>
                                    <td class="num"><?php echo fmt($sub['comp_pagar']); ?></td>
                                    <td class="num"><?php echo fmt($sub['disponibilidad']); ?></td>
                                    <td style="text-align:center;">
                                        <?php echo number_format($sub_pct_dispon, 2, '.', ',') . '%'; ?>
                                    </td>
                                    <?php if ($es_admin): ?>
                                        <td class="no-print"></td><?php endif; ?>
                                </tr>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    </tbody>
                    <tfoot id="ep-table-foot">
                        <tr class="total-gastos-row">
                            <td class="lbl" colspan="2">TOTAL DE GASTOS</td>
                            <td class="num"><?php echo fmt($grand_total['credito_aprobado']); ?></td>
                            <td class="num"><?php echo fmt($grand_total['credito_adicional']); ?></td>
                            <td class="num"><?php echo fmt($grand_total['aumentos']); ?></td>
                            <td class="num"><?php echo fmt($grand_total['disminuciones']); ?></td>
                            <td class="num"><?php echo fmt($grand_total['credito_actualizado']); ?></td>
                            <td class="num"><?php echo fmt($grand_total['compromiso_mensual']); ?></td>
                            <td class="num"><?php echo fmt($grand_total['compromiso_acumulado']); ?></td>
                            <td class="num"><?php echo fmt($grand_total['gastos_causados']); ?></td>
                            <td style="text-align:center;"></td>
                            <td class="num"><?php echo fmt($grand_total['pago_acumulado']); ?></td>
                            <td class="num"><?php echo fmt($grand_total['comp_pagar']); ?></td>
                            <td class="num"><?php echo fmt($grand_total['disponibilidad']); ?></td>
                            <td style="text-align:center;"></td>
                            <?php if ($es_admin): ?>
                                <td class="no-print"></td><?php endif; ?>
                        </tr>
                        <tr class="total-cancelado-row">
                            <td class="lbl" colspan="2">TOTAL CANCELADO</td>
                            <td class="num"></td>
                            <td class="num"></td>
                            <td class="num"></td>
                            <td class="num"></td>
                            <td class="num"><?php echo fmt($grand_total['credito_actualizado']); ?></td>
                            <td class="num"><?php echo fmt($grand_total['compromiso_mensual']); ?></td>
                            <td class="num"><?php echo fmt($grand_total['compromiso_acumulado']); ?></td>
                            <td class="num"><?php echo fmt($grand_total['gastos_causados']); ?></td>
                            <td style="text-align:center;"></td>
                            <td class="num"><?php echo fmt($grand_total['pago_acumulado']); ?></td>
                            <td class="num"><?php echo fmt($grand_total['comp_pagar']); ?></td>
                            <td class="num"><?php echo fmt($grand_total['disponibilidad']); ?></td>
                            <td style="text-align:center;"></td>
                            <?php if ($es_admin): ?>
                                <td class="no-print"></td><?php endif; ?>
                        </tr>
                    </tfoot>
                </table>

                <?php if ($es_admin): ?>
                    <!-- ── Barra inferior para agregar fila ── -->
                    <div class="add-row-bar no-print">
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <button type="button" class="btn-add-row" onclick="addNewTableRow()">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19" />
                                    <line x1="5" y1="12" x2="19" y2="12" />
                                </svg>
                                Agregar Fila / Partida
                            </button>
                            <button type="button" id="btn-eliminar-todas" onclick="deleteAllRows()" style="
                        display:inline-flex;align-items:center;gap:6px;
                        padding:7px 16px;font-size:11.5px;font-weight:600;
                        border-radius:8px;cursor:pointer;font-family:inherit;
                        background:#991b1b;color:#fff;border:none;
                        transition:background .15s;
                    ">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6" />
                                    <path
                                        d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                    <line x1="10" y1="11" x2="10" y2="17" />
                                    <line x1="14" y1="11" x2="14" y2="17" />
                                </svg>
                                Eliminar Todas las Partidas
                            </button>
                        </div>
                        <span style="font-size:11px;color:#64748b;">
                            Selecciona una partida del catálogo o introduce los valores y haz clic en Guardar.
                        </span>
                    </div>
                <?php endif; ?>
                </div><!-- /.table-scroll-wrap -->
            </div><!-- /.table-wrapper -->
            
            <!-- ══ FLECHAS FLOTANTES DE NAVEGACIÓN HORIZONTAL (SOLO FLECHAS) ══════ -->
            <div id="table-horizontal-navigator" class="table-sticky-nav no-print">
                <button type="button" id="nav-scroll-left" class="nav-btn-arrow" title="Izquierda" aria-label="Desplazar a la izquierda">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <button type="button" id="nav-scroll-right" class="nav-btn-arrow" title="Derecha" aria-label="Desplazar a la derecha">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>

        </main>
    </div><!-- /.layout -->

    <!-- ═══ FLOATING POPOVER PARA SELECCIONAR PARTIDA (Searchable) ══════════ -->
    <?php if ($es_admin): ?>
        <div id="global-partida-popover">
            <div class="popover-search-wrap">
                <input type="text" id="popover-search-input" class="popover-search-input"
                    placeholder="Buscar código o nombre de partida..." autocomplete="off"
                    oninput="renderPopoverList(this.value)">
            </div>
            <div class="popover-list" id="popover-list">
                <!-- Rendered by JS -->
            </div>
        </div>
    <?php endif; ?>

    <!-- ═══ FOOTER ══════════════════════════════════════════════════════════ --><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?><!-- ═══ BOTONES PANTALLA COMPLETA (visibles solo en modo pantalla completa) ═══ -->
    <div id="fullscreen-actions">
        <a href="imprimir.php?mes=<?php echo $mes_sel; ?>&anio=<?php echo $anio_sel; ?>" class="fs-btn" target="_blank"
            title="Imprimir">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 6 2 18 2 18 9" />
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                <rect x="6" y="14" width="12" height="8" />
            </svg>
            Imprimir
        </a>
        <button type="button" id="btn-salir-fullscreen" onclick="toggleFullscreenMode()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 10V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2" />
                <path d="M15 14h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2z" />
                <line x1="10" y1="14" x2="21" y2="3" />
                <path d="M21 3l-6 9" />
            </svg>
            Salir de Pantalla Completa
        </button>
    </div>

    <!-- ═══ JS: Guardar, Agregar y Eliminar filas ═══════════════════════════ -->
    <?php if ($es_admin): ?>
        <script>
            var CATALOGO = <?php echo $catalogo_json; ?>;
            var CURRENT_MES = <?php echo $mes_sel; ?>;
            var CURRENT_ANIO = <?php echo $anio_sel; ?>;
            var newRowCounter = 0;
            var activePickerRowId = null;

            /* ── Abrir Popover para seleccionar partida ── */
            function openPartidaPicker(btn, rowId) {
                activePickerRowId = rowId;
                var popover = document.getElementById('global-partida-popover');
                var rect = btn.getBoundingClientRect();

                popover.classList.add('open');
                var popHeight = popover.offsetHeight || 320;
                var spaceBelow = window.innerHeight - rect.bottom;
                if (spaceBelow < popHeight + 8) {
                    popover.style.top = Math.max(8, rect.top - popHeight - 4) + 'px';
                } else {
                    popover.style.top = (rect.bottom + 4) + 'px';
                }
                popover.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - 398)) + 'px';
                popover.classList.add('open');

                var searchInput = document.getElementById('popover-search-input');
                searchInput.value = '';
                renderPopoverList('');
                setTimeout(function () { searchInput.focus(); }, 60);
            }

            function closePartidaPicker() {
                var popover = document.getElementById('global-partida-popover');
                if (popover) popover.classList.remove('open');
                activePickerRowId = null;
            }

            function renderPopoverList(filter) {
                filter = (filter || '').toLowerCase().trim();
                var container = document.getElementById('popover-list');
                container.innerHTML = '';
                var count = 0;

                for (var i = 0; i < CATALOGO.length; i++) {
                    var it = CATALOGO[i];
                    var matchCod = it.cod.toLowerCase().indexOf(filter) !== -1;
                    var matchDen = (it.denom || '').toLowerCase().indexOf(filter) !== -1;
                    if (filter && !matchCod && !matchDen) continue;

                    count++;
                    var div = document.createElement('div');
                    div.className = 'popover-item';
                    div.setAttribute('data-cod', it.cod);
                    div.setAttribute('data-denom', it.denom);
                    div.innerHTML = '<span class="popover-item-code">' + it.cod + '</span>' +
                        '<span class="popover-item-denom">' + it.denom + '</span>';

                    div.addEventListener('click', function () {
                        var cod = this.getAttribute('data-cod');
                        var denom = this.getAttribute('data-denom');
                        selectPartidaForActiveRow(cod, denom);
                    });
                    container.appendChild(div);
                }
                if (count === 0) {
                    container.innerHTML = '<div style="padding:16px;text-align:center;color:#94a3b8;font-size:11px;">Sin coincidencias</div>';
                }
            }

            function selectPartidaForActiveRow(cod, denom) {
                if (!activePickerRowId) return;
                var rowId = activePickerRowId;

                // Update code display in button (SOLO EL CODIGO)
                var btnText = document.getElementById('code-display-' + rowId);
                if (btnText) btnText.textContent = cod;

                // Update hidden inputs
                var codInput = document.querySelector('input[data-row-id="' + rowId + '"][data-field="codificacion"]');
                var denInput = document.querySelector('input[data-row-id="' + rowId + '"][data-field="denominacion"]');
                var denLabel = document.querySelector('#denom-text-' + rowId + ' .denom-label') || document.getElementById('denom-text-' + rowId);

                if (codInput) codInput.value = cod;
                if (denInput) denInput.value = denom;
                if (denLabel) denLabel.textContent = denom;

                // Highlight row save button
                var saveBtn = document.getElementById('save-btn-' + rowId);
                if (saveBtn) {
                    saveBtn.style.background = '#d97706';
                    saveBtn.textContent = '● Guardar';
                }

                closePartidaPicker();
            }

            // Close when clicking outside
            document.addEventListener('click', function (e) {
                var popover = document.getElementById('global-partida-popover');
                if (popover && popover.classList.contains('open')) {
                    /* e.target.closest() not available in IE11 — use manual DOM walk */
                    var isPickerBtn = false;
                    var node = e.target;
                    while (node && node !== document.body) {
                        if (node.className && typeof node.className === 'string' && node.className.indexOf('code-picker-btn') !== -1) {
                            isPickerBtn = true;
                            break;
                        }
                        node = node.parentNode;
                    }
                    if (!popover.contains(e.target) && !isPickerBtn) {
                        closePartidaPicker();
                    }
                }
            });

            /* ── Formateador automático de moneda (1,488,000.00) ── */
            function formatNumberVal(raw) {
                if (raw === null || raw === undefined) return '';
                var str = raw.toString().trim();
                if (str === '') return '';
                if (str.indexOf(',') !== -1 && str.indexOf('.') !== -1) {
                    str = str.replace(/,/g, '');
                } else if (str.indexOf(',') !== -1) {
                    str = str.replace(/,/g, '.');
                }
                var num = parseFloat(str);
                if (isNaN(num)) return '';
                return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function attachAutoMoneyListeners(container) {
                var scope = container || document;
                var inputs = scope.querySelectorAll('.auto-money');
                for (var i = 0; i < inputs.length; i++) {
                    (function (inp) {
                        if (inp._hasMoneyListener) return;
                        inp._hasMoneyListener = true;

                        inp.addEventListener('focus', function () {
                            setTimeout(function () { inp.select(); }, 50);
                        });

                        inp.addEventListener('blur', function () {
                            var v = inp.value.trim();
                            if (v !== '') {
                                inp.value = formatNumberVal(v);
                            }
                        });

                        inp.addEventListener('keydown', function (e) {
                            /* e.key is undefined in IE11; fall back to keyCode */
                            if (e.key === 'Enter' || e.keyCode === 13) {
                                inp.blur();
                            }
                        });
                    })(inputs[i]);
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                attachAutoMoneyListeners(document);
            });

            /* ── Guardar fila vía AJAX ── */
            function saveRowOverrides(rowId) {
                var btn = document.getElementById('save-btn-' + rowId);
                if (btn) { btn.textContent = 'Guardando...'; btn.disabled = true; }

                var inputs = document.querySelectorAll('[data-row-id="' + rowId + '"]');
                var data = 'row_id=' + encodeURIComponent(rowId) +
                    '&mes=' + encodeURIComponent(CURRENT_MES) +
                    '&anio=' + encodeURIComponent(CURRENT_ANIO);
                var i;
                for (i = 0; i < inputs.length; i++) {
                    var inp = inputs[i];
                    var field = inp.getAttribute('data-field');
                    if (field) {
                        var val = inp.value.trim();
                        if (inp.classList.contains('auto-money')) {
                            val = val.replace(/,/g, '');
                        }
                        data += '&' + encodeURIComponent(field) + '=' + encodeURIComponent(val);
                    }
                }

                var xhr = new XMLHttpRequest();
                xhr.open('POST', '/sistema/src/Controllers/ejecucion/guardar_overrides.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                var resp = JSON.parse(xhr.responseText);
                                if (resp.success) {
                                    if (resp.is_new && resp.id) {
                                        window.location.reload();
                                        return;
                                    }

                                    // Re-colour inputs
                                    for (i = 0; i < inputs.length; i++) {
                                        var fName = inputs[i].getAttribute('data-field');
                                        if (fName && fName.indexOf('_override') !== -1) {
                                            if (inputs[i].value.trim() !== '') {
                                                inputs[i].classList.add('has-override');
                                            } else {
                                                inputs[i].classList.remove('has-override');
                                            }
                                        }
                                    }
                                    if (btn) {
                                        btn.textContent = '✓ Guardado';
                                        btn.style.background = '#059669';
                                        setTimeout(function () {
                                            btn.textContent = 'Guardar';
                                            btn.style.background = '';
                                            btn.disabled = false;
                                        }, 1800);
                                    }
                                } else {
                                    alert('Error al guardar: ' + (resp.error || 'desconocido'));
                                    if (btn) { btn.textContent = 'Guardar'; btn.disabled = false; }
                                }
                            } catch (e) {
                                alert('Error de respuesta del servidor.');
                                if (btn) { btn.textContent = 'Guardar'; btn.disabled = false; }
                            }
                        } else {
                            alert('Error HTTP ' + xhr.status);
                            if (btn) { btn.textContent = 'Guardar'; btn.disabled = false; }
                        }
                    }
                };
                xhr.send(data);
            }

            /* ── Eliminar fila vía AJAX ── */
            function deleteTableRow(rowId) {
                if (!confirm('¿Está seguro de eliminar esta partida de la ejecución de este mes?')) {
                    return;
                }
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '/sistema/src/Controllers/ejecucion/eliminar.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                var resp = JSON.parse(xhr.responseText);
                                if (resp.success) {
                                    var tr = document.getElementById('row-' + rowId);
                                    if (tr) tr.remove();
                                    window.location.reload();
                                } else {
                                    alert('Error: ' + (resp.error || 'No se pudo eliminar'));
                                }
                            } catch (e) {
                                alert('Error al procesar la eliminación.');
                            }
                        }
                    }
                };
                xhr.send('id=' + encodeURIComponent(rowId));
            }

            /* ── Agregar nueva fila a la tabla ── */
            function addNewTableRow() {
                var table = document.getElementById('ep-main-table');
                var tbody = document.getElementById('ep-table-body');
                var emptyBox = document.getElementById('empty-state-box');

                if (emptyBox) emptyBox.style.display = 'none';
                if (table) table.style.display = '';

                newRowCounter++;
                var tempId = 'new_' + newRowCounter;

                var tr = document.createElement('tr');
                tr.className = 'data-row';
                tr.id = 'row-' + tempId;
                tr.setAttribute('data-row-id', tempId);
                tr.style.background = '#fefce8';

                tr.innerHTML =
                    '<td class="col-codif">' +
                    '<button type="button" class="code-picker-btn" id="code-btn-' + tempId + '" onclick="openPartidaPicker(this, \'' + tempId + '\')">' +
                    '<span class="code-val" id="code-display-' + tempId + '">Seleccionar código...</span>' +
                    '<svg class="picker-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>' +
                    '</button>' +
                    '<input type="hidden" data-row-id="' + tempId + '" data-field="codificacion" value="">' +
                    '</td>' +
                    '<td class="col-denom" id="denom-text-' + tempId + '">' +
                    '<input type="hidden" data-row-id="' + tempId + '" data-field="denominacion" value="">' +
                    '<span class="denom-label" style="color:#64748b;font-style:italic;">(Seleccione partida)</span>' +
                    '</td>' +
                    '<td class="num">' +
                    '<input type="text" inputmode="decimal" class="ovr-input num-main auto-money" data-row-id="' + tempId + '" data-field="credito_aprobado" value="0.00">' +
                    '</td>' +
                    '<td class="num">' +
                    '<input type="text" inputmode="decimal" class="ovr-input auto-money" data-row-id="' + tempId + '" data-field="credito_adicional_override" value="" placeholder="0.00">' +
                    '</td>' +
                    '<td class="num">' +
                    '<input type="text" inputmode="decimal" class="ovr-input auto-money" data-row-id="' + tempId + '" data-field="aumentos" value="0.00">' +
                    '</td>' +
                    '<td class="num">' +
                    '<input type="text" inputmode="decimal" class="ovr-input auto-money" data-row-id="' + tempId + '" data-field="disminuciones" value="0.00">' +
                    '</td>' +
                    '<td class="num">' +
                    '<input type="text" inputmode="decimal" class="ovr-input auto-money" data-row-id="' + tempId + '" data-field="credito_actualizado_override" value="" placeholder="0.00">' +
                    '</td>' +
                    '<td class="num">' +
                    '<input type="text" inputmode="decimal" class="ovr-input auto-money" data-row-id="' + tempId + '" data-field="compromiso_mensual_override" value="" placeholder="0.00">' +
                    '</td>' +
                    '<td class="num">' +
                    '<input type="text" inputmode="decimal" class="ovr-input auto-money" data-row-id="' + tempId + '" data-field="compromiso_acumulado_override" value="" placeholder="0.00">' +
                    '</td>' +
                    '<td class="num">' +
                    '<input type="text" inputmode="decimal" class="ovr-input auto-money" data-row-id="' + tempId + '" data-field="gastos_causados_override" value="" placeholder="0.00">' +
                    '</td>' +
                    '<td class="pct"><span class="pct-value pct-ok">0.00%</span></td>' +
                    '<td class="num">' +
                    '<input type="text" inputmode="decimal" class="ovr-input auto-money" data-row-id="' + tempId + '" data-field="pago_acumulado_override" value="" placeholder="0.00">' +
                    '</td>' +
                    '<td class="num">' +
                    '<input type="text" inputmode="decimal" class="ovr-input auto-money" data-row-id="' + tempId + '" data-field="compromisos_pagar_override" value="" placeholder="0.00">' +
                    '</td>' +
                    '<td class="num">' +
                    '<input type="text" inputmode="decimal" class="ovr-input auto-money" data-row-id="' + tempId + '" data-field="disponibilidad_override" value="" placeholder="0.00">' +
                    '</td>' +
                    '<td class="pct"><span class="pct-value pct-ok">100.00%</span></td>' +
                    '<td class="no-print" style="white-space:nowrap;">' +
                    '<button type="button" class="save-row-btn" id="save-btn-' + tempId + '" onclick="saveRowOverrides(\'' + tempId + '\')">' +
                    '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>' +
                    ' Guardar' +
                    '</button>' +
                    '<button type="button" class="delete-row-btn" onclick="removeTempRow(\'' + tempId + '\')" title="Quitar">' +
                    '✕' +
                    '</button>' +
                    '</td>';

                tbody.appendChild(tr);
                attachAutoMoneyListeners(tr);

                // Automatically open picker for the new row
                var btn = tr.querySelector('.code-picker-btn');
                if (btn) openPartidaPicker(btn, tempId);
            }

            function removeTempRow(tempId) {
                var tr = document.getElementById('row-' + tempId);
                if (tr) tr.remove();
            }

            /* ── Eliminar TODAS las partidas del mes/año actual ── */
            function deleteAllRows() {
                var total = document.querySelectorAll('#ep-table-body tr.data-row').length;
                if (total === 0) {
                    alert('No hay partidas para eliminar.');
                    return;
                }
                var confirmed = confirm(
                    '¿Eliminar TODAS las ' + total + ' partida(s) de <?php echo htmlspecialchars($nombre_mes . ' ' . $anio_sel); ?>?\n\nEsta acción no se puede deshacer.'
                );
                if (!confirmed) return;

                var btn = document.getElementById('btn-eliminar-todas');
                if (btn) { btn.disabled = true; btn.textContent = 'Eliminando...'; }

                var fd = new FormData();
                fd.append('mes', CURRENT_MES);
                fd.append('anio', CURRENT_ANIO);

                fetch('/sistema/src/Controllers/ejecucion/eliminar_todo.php', {
                    method: 'POST',
                    body: fd
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'desconocido'));
                            if (btn) { btn.disabled = false; btn.innerHTML = '🗑 Eliminar Todas las Partidas'; }
                        }
                    })
                    .catch(function (err) {
                        alert('Error de red: ' + err);
                        if (btn) { btn.disabled = false; btn.innerHTML = '🗑 Eliminar Todas las Partidas'; }
                    });
            }

        </script>
    <?php endif; ?>

    <!-- ═══ SCRIPT UNIVERSAL: NAVEGADOR HORIZONTAL Y PANTALLA COMPLETA ═══════ -->
    <script>
        /* ── Modo pantalla completa universal ── */
        function toggleFullscreenMode() {
            var body = document.body;
            if (body.classList.contains('fullscreen-mode')) {
                body.classList.remove('fullscreen-mode');
            } else {
                body.classList.add('fullscreen-mode');
            }
        }

        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && document.body.classList.contains('fullscreen-mode')) {
                document.body.classList.remove('fullscreen-mode');
            }
        });

        /* ── Controlador de Barra Sticky de Navegación Horizontal ── */
        (function () {
            var scrollWrap = document.getElementById('table-scroll-wrap');
            var navBar = document.getElementById('table-horizontal-navigator');
            if (!scrollWrap || !navBar) return;

            var btnLeft = document.getElementById('nav-scroll-left');
            var btnRight = document.getElementById('nav-scroll-right');

            function updateNavState() {
                var maxScroll = scrollWrap.scrollWidth - scrollWrap.clientWidth;
                if (maxScroll <= 15) {
                    navBar.style.display = 'none';
                    return;
                } else {
                    navBar.style.display = 'flex';
                }

                var current = scrollWrap.scrollLeft;
                if (btnLeft) {
                    btnLeft.style.opacity = current <= 8 ? '0.3' : '1';
                    btnLeft.style.pointerEvents = current <= 8 ? 'none' : 'auto';
                }
                if (btnRight) {
                    btnRight.style.opacity = current >= maxScroll - 8 ? '0.3' : '1';
                    btnRight.style.pointerEvents = current >= maxScroll - 8 ? 'none' : 'auto';
                }
            }

            /* { passive: true } throws in IE11 which only accepts boolean as 3rd arg */
            scrollWrap.addEventListener('scroll', updateNavState, false);
            window.addEventListener('resize', updateNavState);
            setTimeout(updateNavState, 150);

            /* scrollBy with options object not supported in IE11 — use scrollLeft directly */
            function doScroll(offset) {
                try {
                    scrollWrap.scrollBy({ left: offset, behavior: 'smooth' });
                } catch (ex) {
                    scrollWrap.scrollLeft += offset;
                }
            }

            var holdTimer = null;
            var holdInterval = null;

            function startHold(offset) {
                doScroll(offset);
                holdTimer = setTimeout(function () {
                    holdInterval = setInterval(function () {
                        scrollWrap.scrollLeft += (offset > 0 ? 35 : -35);
                    }, 30);
                }, 250);
            }

            function stopHold() {
                if (holdTimer) clearTimeout(holdTimer);
                if (holdInterval) clearInterval(holdInterval);
                holdTimer = null;
                holdInterval = null;
            }

            if (btnLeft) {
                btnLeft.addEventListener('click', function () { doScroll(-380); });
                btnLeft.addEventListener('mousedown', function () { startHold(-380); });
                btnLeft.addEventListener('mouseup', stopHold);
                btnLeft.addEventListener('mouseleave', stopHold);
            }

            if (btnRight) {
                btnRight.addEventListener('click', function () { doScroll(380); });
                btnRight.addEventListener('mousedown', function () { startHold(380); });
                btnRight.addEventListener('mouseup', stopHold);
                btnRight.addEventListener('mouseleave', stopHold);
            }
        })();
    </script>

</body>

</html>