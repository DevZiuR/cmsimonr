<?php
/**
 * src/Views/ejecucion/imprimir.php
 * Vista de impresión — Ejecución del Presupuesto de Gastos
 * Orientación horizontal (landscape), sin sidebar/header/botones
 * PHP 5.6 compatible — sin ??
 */
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}

require_once '../../../config/conexion.php';
require_once __DIR__ . '/../../Models/catalogo_partidas.php';

/* ── Filtros ── */
$mes_sel  = isset($_GET['mes'])  ? (int) $_GET['mes']  : (int) date('n');
$anio_sel = isset($_GET['anio']) ? (int) $_GET['anio'] : (int) date('Y');
if ($mes_sel < 1 || $mes_sel > 12) $mes_sel = (int) date('n');
if ($anio_sel < 2000 || $anio_sel > 2100) $anio_sel = (int) date('Y');

$anio_inicio_str = $anio_sel . '-01-01';
$mes_inicio_str  = sprintf('%04d-%02d-01', $anio_sel, $mes_sel);
$mes_fin_str     = date('Y-m-t', strtotime($mes_inicio_str));

$meses_es = array(
    1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',
    5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',
    9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
);
$nombre_mes = isset($meses_es[$mes_sel]) ? $meses_es[$mes_sel] : $mes_sel;

/* ── Obtener partidas con JOIN a partidas (fuente de verdad del crédito) ── */
$res = mysqli_query($conn,
    "SELECT ep.*, COALESCE(p.credito_original, ep.credito_aprobado) AS credito_original_real
     FROM ejecucion_presupuestaria ep
     LEFT JOIN partidas p ON p.codigo = ep.codificacion
     WHERE ep.mes = $mes_sel AND ep.anio = $anio_sel
     ORDER BY ep.codificacion ASC"
);
$partidas = array();
if ($res) {
    while ($p = mysqli_fetch_assoc($res)) $partidas[] = $p;
}

/* ── Pre-cargar todas las OPs del año en memoria ────────────────────────── */
$ops_all = array();

if (!function_exists('parse_seg_total')) {
    function parse_seg_total($val) {
        if (!isset($val) || $val === '' || $val === null) return 0.0;
        $str = trim((string)$val);
        if (strpos($str, ',') !== false && strpos($str, '.') !== false) {
            $lastDot   = strrpos($str, '.');
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
        return (float)$str;
    }
}

/* ── Cargar overrides de matriz_numeros_registro para el año ── */
$matriz_overrides = array();
$res_nr = mysqli_query($conn, "SELECT codificacion, op_id, mes, monto_compromiso_custom, monto_pago_custom FROM matriz_numeros_registro WHERE anio = $anio_sel");
if ($res_nr) {
    while ($nr = mysqli_fetch_assoc($res_nr)) {
        $c_esc = strtolower(trim($nr['codificacion']));
        $oid   = (int)$nr['op_id'];
        $m     = (int)$nr['mes'];
        if ($nr['monto_compromiso_custom'] !== null) {
            $matriz_overrides['comp'][$c_esc][$oid][$m] = (float)$nr['monto_compromiso_custom'];
        }
        if ($nr['monto_pago_custom'] !== null) {
            $matriz_overrides['pago'][$c_esc][$oid][$m] = (float)$nr['monto_pago_custom'];
        }
    }
}

if (!function_exists('get_matriz_override_val')) {
    function get_matriz_override_val($matriz_overrides, $type, $variants, $op_id, $mes = null) {
        if (!isset($matriz_overrides[$type])) return null;
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
    function iva_tipo_proveedor_rep($nombre) {
        $n = strtoupper(trim($nombre));
        if (strpos($n, 'SENIAT') !== false) return 'seniat';
        if (strpos($n, 'CORPOELEC') !== false || strpos($n, 'CANTV') !== false) return 'estatal';
        return 'privado';
    }
}

$res_ops = mysqli_query($conn,
    "SELECT op.id, op.fecha, op.monto_bruto, op.monto_neto_pagar, op.cont_json,
            COALESCE(p.razon_social, poc.razon_social, pos.razon_social, '') AS proveedor_nombre
     FROM ordenes_pago op
     LEFT JOIN proveedores p ON p.id = op.proveedor_id
     LEFT JOIN ordenes_compra oc ON oc.id = op.oc_id
     LEFT JOIN proveedores poc ON poc.id = oc.proveedor_id
     LEFT JOIN ordenes_servicio os ON os.id = op.os_id
     LEFT JOIN proveedores pos ON pos.id = os.proveedor_id
     WHERE op.fecha BETWEEN '$anio_inicio_str' AND '$mes_fin_str'
       AND op.deleted_at IS NULL");
if ($res_ops) {
    while ($op_row = mysqli_fetch_assoc($res_ops)) {
        $oid       = (int)$op_row['id'];
        $op_bruto  = (float)$op_row['monto_bruto'];
        $op_neto   = (float)$op_row['monto_neto_pagar'];
        $op_codes  = array();

        /* 1. Renglones de op_retenciones (fuente de verdad para retenciones IVA) */
        $ret_codes_in_op = array();
        $res_rets = mysqli_query($conn,
            "SELECT codigo_presupuestario, monto_comision
             FROM op_retenciones
             WHERE op_id = $oid AND monto_comision > 0
               AND (codigo_presupuestario LIKE '%403-18-01%' OR descripcion LIKE '%IVA%')"
        );
        if ($res_rets && mysqli_num_rows($res_rets) > 0) {
            while ($r_row = mysqli_fetch_assoc($res_rets)) {
                $code_raw = trim($r_row['codigo_presupuestario']);
                $m_com    = (float)$r_row['monto_comision'];
                if (empty($code_raw) || $m_com <= 0) continue;

                /* Generar variantes para este código */
                $vars = get_matching_code_variants($code_raw);
                $short = isset($vars[0]) ? $vars[0] : $code_raw;
                $long  = isset($vars[1]) ? $vars[1] : $code_raw;
                $dot   = isset($vars[2]) ? $vars[2] : $code_raw;

                foreach ($vars as $v) {
                    $ret_codes_in_op[strtolower($v)] = true;
                }

                $op_codes[] = array(
                    'short'      => $short,
                    'long'       => $long,
                    'dot'        => $dot,
                    'monto_bruto'=> $m_com,
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
                    if (isset($seg['total']) && trim((string)$seg['total']) !== '' && trim((string)$seg['total']) !== '0' && trim((string)$seg['total']) !== '0.00' && trim((string)$seg['total']) !== '0,00') {
                        $has_explicit_total = true;
                    }
                }

                // Contar segmentos de gasto principal (no retenciones)
                $n_non_ret = 0;
                foreach ($segs as $seg) {
                    $obra   = isset($seg['obra'])   ? trim($seg['obra'])   : '';
                    $partid = isset($seg['partid']) ? trim($seg['partid']) : '';
                    $gen    = isset($seg['gen'])    ? trim($seg['gen'])    : '';
                    $espec  = (isset($seg['espec']) && trim($seg['espec']) !== '') ? trim($seg['espec']) : '00';
                    $short_c = strtolower($obra . '-' . $partid . '-' . $gen . '-' . $espec);
                    $long_c  = strtolower('01-08-00-00-51-' . $short_c);
                    $is_ret_c = isset($ret_codes_in_op[$short_c]) || isset($ret_codes_in_op[$long_c])
                                || (strpos($short_c, '403-18-01') !== false || strpos($short_c, '403-18-99') !== false || strpos($short_c, '4.03.18') !== false);
                    if (!$is_ret_c) {
                        $n_non_ret++;
                    }
                }

                foreach ($segs as $seg) {
                    $obra   = isset($seg['obra'])   ? trim($seg['obra'])   : '';
                    $partid = isset($seg['partid']) ? trim($seg['partid']) : '';
                    $gen    = isset($seg['gen'])    ? trim($seg['gen'])    : '';
                    $espec  = (isset($seg['espec']) && trim($seg['espec']) !== '') ? trim($seg['espec']) : '00';

                    $short = $obra . '-' . $partid . '-' . $gen . '-' . $espec;
                    $long  = '01-08-00-00-51-' . $short;
                    if (strlen($obra) === 3) {
                        $dot = substr($obra,0,1) . '.' . substr($obra,1) . '.' . $partid . '.' . $gen . '.' . $espec;
                    } else {
                        $dot = $obra . '.' . $partid . '.' . $gen . '.' . $espec;
                    }

                    $short_l = strtolower($short);
                    $long_l  = strtolower($long);
                    $dot_l   = strtolower($dot);
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
                            'short'      => $short,
                            'long'       => $long,
                            'dot'        => $dot,
                            'monto_bruto'=> $seg_bruto,
                            'monto_neto' => $seg_neto,
                        );
                    }
                }
            }
        }

        if (!empty($op_codes)) {
            $ops_all[] = array(
                'id'               => $oid,
                'fecha'            => $op_row['fecha'],
                'proveedor_nombre' => $op_row['proveedor_nombre'],
                'codes'            => $op_codes,
            );
        }
    }
}

if (!function_exists('calcular_ejecucion_partida_rep')) {
    function calcular_ejecucion_partida_rep($ops_all, $variants, $matriz_overrides, $es_iva, $date_month_from, $date_month_to, $date_year_from) {
        $comp_mensual   = 0.0;
        $comp_acumulado = 0.0;
        $gc_acumulado   = 0.0;
        $pago_acumulado = 0.0;

        $variants_lower = array_map('strtolower', $variants);

        foreach ($ops_all as $op) {
            $op_fecha = $op['fecha'];
            if ($op_fecha < $date_year_from || $op_fecha > $date_month_to) continue;

            $oid    = $op['id'];
            $op_mes = (int)date('n', strtotime($op_fecha));
            $prov   = isset($op['proveedor_nombre']) ? $op['proveedor_nombre'] : '';

            $op_monto_bruto = 0.0;
            $op_monto_neto  = 0.0;
            $matched = false;

            foreach ($op['codes'] as $oc) {
                $s = strtolower($oc['short']);
                $l = strtolower($oc['long']);
                $d = strtolower($oc['dot']);
                foreach ($variants_lower as $v) {
                    if ($s === $v || $l === $v || $d === $v) {
                        $matched = true;
                        $op_monto_bruto += $oc['monto_bruto'];
                        $op_monto_neto  += $oc['monto_neto'];
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
            'comp_mensual'   => $comp_mensual,
            'comp_acumulado' => $comp_acumulado,
            'gastos_causados'=> $gc_acumulado,
            'pago_acumulado' => $pago_acumulado,
        );
    }
}

/* ── Calcular valores por fila ── */
$rows = array();
foreach ($partidas as $p) {
    $code_variants = get_matching_code_variants($p['codificacion']);
    $es_iva_p = (strpos($p['codificacion'], '403-18-01') !== false);

    $credito_aprobado = (float)$p['credito_original_real'];

    $credito_adicional = ($p['credito_adicional_override'] !== null)
        ? (float)$p['credito_adicional_override']
        : 0.0;

    $credito_actualizado = $credito_aprobado + $credito_adicional + (float)$p['aumentos'] - (float)$p['disminuciones'];

    $calc_res = calcular_ejecucion_partida_rep(
        $ops_all,
        $code_variants,
        $matriz_overrides,
        $es_iva_p,
        $mes_inicio_str,
        $mes_fin_str,
        $anio_inicio_str
    );

    $comp_mensual    = $calc_res['comp_mensual'];
    $comp_acumulado  = $calc_res['comp_acumulado'];
    $gastos_causados = $calc_res['gastos_causados'];
    $pago_acumulado  = $calc_res['pago_acumulado'];

    $pct_gastos = ($credito_actualizado != 0) ? ($gastos_causados / $credito_actualizado * 100) : 0.0;

    /* Compromisos por Pagar Acumulado (Compromiso Acumulado − Gastos Causados Acumulado)
       Solo aplica con saldo en:
       - 01-08-00-00-51-403-18-01-00 (IVA)
       - 01-08-00-00-51-403-18-99-00 (SAT / Otros Impuestos Indirectos)
       Cualquier otra partida es 0.00 */
    $es_partida_con_comp_pagar = (strpos($p['codificacion'], '403-18-01') !== false || strpos($p['codificacion'], '403-18-99') !== false);
    if ($es_partida_con_comp_pagar) {
        $comp_pagar = max(0.0, $comp_acumulado - $gastos_causados);
    } else {
        $comp_pagar = 0.0;
    }

    $disponibilidad = $credito_actualizado - $comp_acumulado;
    $pct_disponible = ($credito_actualizado != 0) ? ($disponibilidad / $credito_actualizado * 100) : 0.0;

    $grupo = get_grupo_partida($p['codificacion']);

    $rows[] = array(
        'codificacion'        => $p['codificacion'],
        'denominacion'        => $p['denominacion'],
        'grupo'               => $grupo,
        'credito_aprobado'    => $credito_aprobado,
        'credito_adicional'   => $credito_adicional,
        'aumentos'            => (float)$p['aumentos'],
        'disminuciones'       => (float)$p['disminuciones'],
        'credito_actualizado' => $credito_actualizado,
        'compromiso_mensual'  => $comp_mensual,
        'compromiso_acumulado'=> $comp_acumulado,
        'gastos_causados'     => $gastos_causados,
        'pct_gastos'          => $pct_gastos,
        'pago_acumulado'      => $pago_acumulado,
        'comp_pagar'          => $comp_pagar,
        'disponibilidad'      => $disponibilidad,
        'pct_disponible'      => $pct_disponible,
    );
}

/* ── Agrupar filas para impresión ── */
$grouped_rows = array();
$group_subtotals = array();
$group_labels = array(
    'GASTOS DE PERSONAL'           => 'TOTAL GASTOS DE PERSONAL',
    'MATERIALES Y SUMINISTROS'     => 'TOTAL GTOS. MAT. Y SUMINISTROS',
    'SERVICIOS NO PERSONALES'      => 'TOTAL SERVICIOS. NO PERSONALES',
    'ACTIVOS REALES'               => 'TOTAL ACTIVOS REALES',
    'TRANSFERENCIAS Y DONACIONES'  => 'TOTAL TRANSFERENCIAS Y DONACIONES',
    'OTRAS PARTIDAS'               => 'TOTAL OTRAS PARTIDAS',
);

foreach ($rows as $r) {
    $grp = $r['grupo'];
    if (!isset($grouped_rows[$grp])) {
        $grouped_rows[$grp] = array();
        $group_subtotals[$grp] = array(
            'credito_aprobado'     => 0,
            'credito_adicional'    => 0,
            'aumentos'             => 0,
            'disminuciones'        => 0,
            'credito_actualizado'  => 0,
            'compromiso_mensual'   => 0,
            'compromiso_acumulado' => 0,
            'gastos_causados'      => 0,
            'pago_acumulado'       => 0,
            'comp_pagar'           => 0,
            'disponibilidad'       => 0,
        );
    }
    $grouped_rows[$grp][] = $r;
    $group_subtotals[$grp]['credito_aprobado']     += $r['credito_aprobado'];
    $group_subtotals[$grp]['credito_adicional']    += $r['credito_adicional'];
    $group_subtotals[$grp]['aumentos']             += $r['aumentos'];
    $group_subtotals[$grp]['disminuciones']        += $r['disminuciones'];
    $group_subtotals[$grp]['credito_actualizado']  += $r['credito_actualizado'];
    $group_subtotals[$grp]['compromiso_mensual']   += $r['compromiso_mensual'];
    $group_subtotals[$grp]['compromiso_acumulado'] += $r['compromiso_acumulado'];
    $group_subtotals[$grp]['gastos_causados']      += $r['gastos_causados'];
    $group_subtotals[$grp]['pago_acumulado']       += $r['pago_acumulado'];
    $group_subtotals[$grp]['comp_pagar']           += $r['comp_pagar'];
    $group_subtotals[$grp]['disponibilidad']       += $r['disponibilidad'];
}

/* ── Totales Generales ── */
$tot_aprobado=$tot_adicional=$tot_aumentos=$tot_disminucion=$tot_actualizado=0;
$tot_comp_mens=$tot_comp_acum=$tot_gastos=$tot_pago=$tot_comp_pagar=$tot_dispon=0;
foreach ($rows as $r) {
    $tot_aprobado    += $r['credito_aprobado'];
    $tot_adicional   += $r['credito_adicional'];
    $tot_aumentos    += $r['aumentos'];
    $tot_disminucion += $r['disminuciones'];
    $tot_actualizado += $r['credito_actualizado'];
    $tot_comp_mens   += $r['compromiso_mensual'];
    $tot_comp_acum   += $r['compromiso_acumulado'];
    $tot_gastos      += $r['gastos_causados'];
    $tot_pago        += $r['pago_acumulado'];
    $tot_comp_pagar  += $r['comp_pagar'];
    $tot_dispon      += $r['disponibilidad'];
}
$tot_pct_gastos = ($tot_actualizado != 0) ? ($tot_gastos / $tot_actualizado * 100) : 0;
$tot_pct_dispon = ($tot_actualizado != 0) ? ($tot_dispon / $tot_actualizado * 100) : 0;

function fmt_p($v) {
    return number_format((float)$v, 2, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ejecución Presupuestaria <?php echo htmlspecialchars($nombre_mes . ' ' . $anio_sel); ?></title>
    <style>
        @page { size: A4 landscape; margin: 6mm 5mm 6mm 5mm; }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7pt;
            color: #000;
            background: #fff;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* ── Header del documento ── */
        .doc-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 3mm;
            padding-bottom: 2mm;
            border-bottom: 1.5pt solid #000;
        }
        .doc-header-left { flex: 1; }
        .doc-header-center { flex: 2; text-align: center; }
        .doc-header-right { flex: 1; text-align: right; display: flex; justify-content: flex-end; align-items: center; }

        .doc-institution {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.3;
        }
        .doc-title {
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 2mm;
            letter-spacing: .2pt;
        }
        .doc-logo {
            width: 24mm;
            height: auto;
        }

        /* ── Table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6pt;
        }
        thead tr th {
            background: #d0d0d0;
            border: 0.5pt solid #555;
            padding: 3pt 2pt;
            text-align: center;
            font-size: 5.5pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.15;
            vertical-align: bottom;
        }
        thead tr th.col-codif { text-align: left; width: 44mm; }
        thead tr th.col-denom { text-align: left; width: 55mm; }
        thead tr.sub-head th {
            background: #bebebe;
            font-size: 5pt;
            font-style: italic;
        }

        tbody tr td {
            border: 0.4pt solid #bbb;
            padding: 1.8pt 2pt;
            vertical-align: middle;
        }
        tbody tr:nth-child(odd) td  { background: #fff; }
        tbody tr:nth-child(even) td { background: #fbfbfb; }

        /* Group rows */
        tr.group-row td {
            background: #1e293b !important;
            color: #fff !important;
            font-weight: bold;
            font-size: 6pt;
            padding: 2.5pt 3pt;
            text-transform: uppercase;
            letter-spacing: .3pt;
        }

        /* Subtotal rows */
        tr.subtotal-row td {
            background: #ffff00 !important;
            color: #000000 !important;
            font-weight: bold;
            font-size: 5.8pt;
            padding: 2.2pt 2pt;
            border-top: 1pt solid #000000;
            border-bottom: 1.2pt solid #000000;
        }
        tr.subtotal-row td.lbl {
            text-align: left;
            font-weight: 800;
            color: #000000 !important;
        }

        td.col-codif { font-family: 'Courier New', monospace; font-size: 5.5pt; font-weight: 800; white-space: nowrap; color: #000000 !important; }
        td.col-denom { font-size: 5.8pt; max-width: 55mm; word-break: break-word; line-height: 1.1; font-weight: 700; color: #000000 !important; }
        td.num { text-align: right; white-space: nowrap; }
        td.pct { text-align: center; white-space: nowrap; }

        tfoot tr td {
            border: 1pt solid #000;
            background: #ffffff !important;
            color: #000000 !important;
            font-weight: 900;
            font-size: 6.2pt;
            padding: 3pt 2pt;
            text-align: right;
        }
        tfoot tr td.lbl { text-align: left; letter-spacing: .3pt; font-weight: 900; color: #000000 !important; }
        tfoot tr.total-gastos-row td {
            border-top: 1.5pt solid #000000 !important;
            background: #ffffff !important;
            color: #000000 !important;
        }
        tfoot tr.total-cancelado-row td {
            border-bottom: 1.5pt solid #000000 !important;
            background: #ffffff !important;
            color: #000000 !important;
        }

        /* ── Print button (screen only) ── */
        .print-btn-bar {
            text-align: center;
            padding: 10px;
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
            font-family: sans-serif;
        }
        .print-btn {
            display: inline-flex; align-items: center; gap: 6px;
            background: #065f46; color: #fff; border: none;
            padding: 8px 20px; font-size: 12px; font-weight: 600;
            border-radius: 8px; cursor: pointer; font-family: inherit;
            margin-right: 8px;
        }
        .back-btn {
            display: inline-flex; align-items: center; gap: 6px;
            background: #f8fafc; color: #475569;
            border: 1px solid #e2e8f0;
            padding: 8px 20px; font-size: 12px; font-weight: 600;
            border-radius: 8px; cursor: pointer; font-family: inherit;
            text-decoration: none;
        }
        @media print {
            .print-btn-bar { display: none !important; }
        }
    </style>
</head>
<body>

<!-- Screen-only print controls -->
<div class="print-btn-bar">
    <button class="print-btn" onclick="window.print()">🖨 Imprimir / Guardar PDF</button>
    <a href="index.php?mes=<?php echo $mes_sel; ?>&anio=<?php echo $anio_sel; ?>" class="back-btn">← Volver</a>
</div>

<!-- ═══ ENCABEZADO DEL DOCUMENTO ══════════════════════════════════════ -->
<div class="doc-header">
    <div class="doc-header-left">
        <img src="/sistema/assets/img/logo.png" alt="Logo" style="height:15mm;width:auto;">
    </div>
    <div class="doc-header-center">
        <div class="doc-institution">
            República Bolivariana de Venezuela<br>
            Estado Anzoátegui<br>
            Contraloría del Municipio Simón Rodríguez
        </div>
        <div class="doc-title">
            Ejecución del Presupuesto de Gastos<br>
            Mes de <?php echo strtoupper($nombre_mes); ?> de <?php echo $anio_sel; ?>
        </div>
    </div>
    <div class="doc-header-right">
        <img src="/sistema/assets/img/sncf.png" alt="SNCF" class="doc-logo"
             style="max-height:15mm;width:auto;">
    </div>
</div>

<!-- ═══ TABLA DE EJECUCIÓN ════════════════════════════════════════════ -->
<table>
    <thead>
        <tr>
            <th class="col-codif" rowspan="2">Codificación<br>Presupuestaria</th>
            <th class="col-denom" rowspan="2">Denominación</th>
            <th rowspan="2">Crédito Aprobado<br><?php echo htmlspecialchars($nombre_mes . ' ' . $anio_sel); ?></th>
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
            <th rowspan="2">%<br>Disponible</th>
        </tr>
        <tr class="sub-head">
            <th>Aumentos</th>
            <th>Disminuciones</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="15" style="text-align:center;padding:8pt;color:#888;">
            Sin datos para <?php echo htmlspecialchars($nombre_mes . ' ' . $anio_sel); ?>
        </td></tr>
        <?php else: ?>
        <?php
        /* Groups whose group-header label row is hidden; data rows and subtotal still show */
        $hidden_groups = array('GASTOS DE PERSONAL', 'MATERIALES Y SUMINISTROS');
        ?>
        <?php foreach ($grouped_rows as $grp_name => $grp_items): ?>
            <?php $is_hidden_group = in_array($grp_name, $hidden_groups); ?>
            <!-- Grupo Header -->
            <?php if (!$is_hidden_group): ?>
            <tr class="group-row">
                <td colspan="15"><?php echo htmlspecialchars($grp_name); ?></td>
            </tr>
            <?php endif; ?>

            <?php foreach ($grp_items as $r): ?>
            <tr>
                <td class="col-codif"><?php echo htmlspecialchars($r['codificacion']); ?></td>
                <td class="col-denom"><?php echo htmlspecialchars($r['denominacion']); ?></td>
                <td class="num"><?php echo fmt_p($r['credito_aprobado']); ?></td>
                <td class="num"><?php echo fmt_p($r['credito_adicional']); ?></td>
                <td class="num"><?php echo fmt_p($r['aumentos']); ?></td>
                <td class="num"><?php echo fmt_p($r['disminuciones']); ?></td>
                <td class="num"><?php echo fmt_p($r['credito_actualizado']); ?></td>
                <td class="num"><?php echo fmt_p($r['compromiso_mensual']); ?></td>
                <td class="num"><?php echo fmt_p($r['compromiso_acumulado']); ?></td>
                <td class="num"><?php echo fmt_p($r['gastos_causados']); ?></td>
                <td class="pct">0.00%</td>
                <td class="num"><?php echo fmt_p($r['pago_acumulado']); ?></td>
                <td class="num"><?php echo fmt_p($r['comp_pagar']); ?></td>
                <td class="num"><?php echo fmt_p($r['disponibilidad']); ?></td>
                <td class="pct"><?php echo number_format($r['pct_disponible'], 2, '.', ',') . '%'; ?></td>
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
            <tr class="subtotal-row">
                <td class="lbl" colspan="2"><?php echo htmlspecialchars($lbl_subtotal); ?></td>
                <td class="num"><?php echo fmt_p($sub['credito_aprobado']); ?></td>
                <td class="num"><?php echo fmt_p($sub['credito_adicional']); ?></td>
                <td class="num"><?php echo fmt_p($sub['aumentos']); ?></td>
                <td class="num"><?php echo fmt_p($sub['disminuciones']); ?></td>
                <td class="num"><?php echo fmt_p($sub['credito_actualizado']); ?></td>
                <td class="num"><?php echo fmt_p($sub['compromiso_mensual']); ?></td>
                <td class="num"><?php echo fmt_p($sub['compromiso_acumulado']); ?></td>
                <td class="num"><?php echo fmt_p($sub['gastos_causados']); ?></td>
                <td class="pct">0.00%</td>
                <td class="num"><?php echo fmt_p($sub['pago_acumulado']); ?></td>
                <td class="num"><?php echo fmt_p($sub['comp_pagar']); ?></td>
                <td class="num"><?php echo fmt_p($sub['disponibilidad']); ?></td>
                <td class="pct"><?php echo number_format($sub_pct_dispon, 2, '.', ',') . '%'; ?></td>
            </tr>
            <?php endif; ?>

        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr class="total-gastos-row">
            <td class="lbl" colspan="2">TOTAL DE GASTOS</td>
            <td class="num"><?php echo fmt_p($tot_aprobado); ?></td>
            <td class="num"><?php echo fmt_p($tot_adicional); ?></td>
            <td class="num"><?php echo fmt_p($tot_aumentos); ?></td>
            <td class="num"><?php echo fmt_p($tot_disminucion); ?></td>
            <td class="num"><?php echo fmt_p($tot_actualizado); ?></td>
            <td class="num"><?php echo fmt_p($tot_comp_mens); ?></td>
            <td class="num"><?php echo fmt_p($tot_comp_acum); ?></td>
            <td class="num"><?php echo fmt_p($tot_gastos); ?></td>
            <td class="pct"></td>
            <td class="num"><?php echo fmt_p($tot_pago); ?></td>
            <td class="num"><?php echo fmt_p($tot_comp_pagar); ?></td>
            <td class="num"><?php echo fmt_p($tot_dispon); ?></td>
            <td class="pct"></td>
        </tr>
        <tr class="total-cancelado-row">
            <td class="lbl" colspan="2">TOTAL CANCELADO</td>
            <td class="num"></td>
            <td class="num"></td>
            <td class="num"></td>
            <td class="num"></td>
            <td class="num"><?php echo fmt_p($tot_actualizado); ?></td>
            <td class="num"><?php echo fmt_p($tot_comp_mens); ?></td>
            <td class="num"><?php echo fmt_p($tot_comp_acum); ?></td>
            <td class="num"><?php echo fmt_p($tot_gastos); ?></td>
            <td class="pct"></td>
            <td class="num"><?php echo fmt_p($tot_pago); ?></td>
            <td class="num"><?php echo fmt_p($tot_comp_pagar); ?></td>
            <td class="num"><?php echo fmt_p($tot_dispon); ?></td>
            <td class="pct"></td>
        </tr>
    </tfoot>
</table>

</body>
</html>
