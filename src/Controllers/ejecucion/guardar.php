<?php
/**
 * src/Controllers/ejecucion/guardar.php
 * Guarda (INSERT o UPDATE) una partida de ejecución presupuestaria
 * Admin only — PHP 5.6 compatible — sin ??
 */
if (session_id() === '') { session_start(); }
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: /sistema/src/Views/ejecucion/index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /sistema/src/Views/ejecucion/index.php');
    exit;
}

require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';
require_once dirname(dirname(__DIR__)) . '/Models/catalogo_partidas.php';

/* ── Auto-crear tabla si no existe ── */
mysqli_query($conn,
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

/* ── Recoger datos POST ── */
$partida_id     = isset($_POST['partida_id'])     ? (int)   $_POST['partida_id']                                        : 0;
$raw_cod        = isset($_POST['codificacion'])   ? trim($_POST['codificacion'])                                        : '';
$denominacion   = isset($_POST['denominacion'])   ? trim(   mysqli_real_escape_string($conn, $_POST['denominacion']))   : '';
$credito_aprobado = isset($_POST['credito_aprobado']) ? (float) str_replace(',', '.', $_POST['credito_aprobado'])       : 0.0;
$aumentos       = isset($_POST['aumentos'])       ? (float) str_replace(',', '.', $_POST['aumentos'])                   : 0.0;
$disminuciones  = isset($_POST['disminuciones'])  ? (float) str_replace(',', '.', $_POST['disminuciones'])              : 0.0;
$mes            = isset($_POST['mes'])            ? (int)   $_POST['mes']                                               : (int)date('n');
$anio           = isset($_POST['anio'])           ? (int)   $_POST['anio']                                              : (int)date('Y');

// Normalizar código con prefijo 01-08-00-00-51- si viene en formato corto
$prefix = '01-08-00-00-51-';
if ($raw_cod !== '' && strpos($raw_cod, $prefix) !== 0) {
    // Si viene como 4.02.10.02.00 o 402-10-02-00
    $clean = str_replace('.', '-', $raw_cod);
    $parts = explode('-', $clean);
    if (count($parts) >= 2 && strlen($parts[0]) === 1 && strlen($parts[1]) === 2) {
        $merged = $parts[0] . $parts[1];
        $rest = array_slice($parts, 2);
        $raw_cod = $prefix . $merged . (count($rest) > 0 ? '-' . implode('-', $rest) : '');
    } else {
        $raw_cod = $prefix . $clean;
    }
}
$codificacion = mysqli_real_escape_string($conn, $raw_cod);

/* ── Validación básica ── */
if ($codificacion === '' || $denominacion === '') {
    header('Location: /sistema/src/Views/ejecucion/nuevo.php?error=campos_requeridos&mes=' . $mes . '&anio=' . $anio);
    exit;
}
if ($mes < 1 || $mes > 12) {
    header('Location: /sistema/src/Views/ejecucion/nuevo.php?error=mes_invalido&mes=' . $mes . '&anio=' . $anio);
    exit;
}
if ($anio < 2000 || $anio > 2100) {
    header('Location: /sistema/src/Views/ejecucion/nuevo.php?error=anio_invalido&mes=' . $mes . '&anio=' . $anio);
    exit;
}

if ($partida_id > 0) {
    /* ── UPDATE ── */
    $sql = "UPDATE ejecucion_presupuestaria SET
                codificacion = '$codificacion',
                denominacion = '$denominacion',
                credito_aprobado = $credito_aprobado,
                aumentos = $aumentos,
                disminuciones = $disminuciones,
                mes = $mes,
                anio = $anio
            WHERE id = $partida_id";
    $ok = mysqli_query($conn, $sql);
    if (!$ok) {
        $err = urlencode(mysqli_error($conn));
        header("Location: /sistema/src/Views/ejecucion/nuevo.php?editar=$partida_id&error=$err&mes=$mes&anio=$anio");
        exit;
    }
} else {
    /* ── INSERT con ON DUPLICATE KEY UPDATE ── */
    $sql = "INSERT INTO ejecucion_presupuestaria
                (codificacion, denominacion, credito_aprobado, aumentos, disminuciones, mes, anio)
            VALUES
                ('$codificacion', '$denominacion', $credito_aprobado, $aumentos, $disminuciones, $mes, $anio)
            ON DUPLICATE KEY UPDATE
                denominacion = VALUES(denominacion),
                credito_aprobado = VALUES(credito_aprobado),
                aumentos = VALUES(aumentos),
                disminuciones = VALUES(disminuciones)";
    $ok = mysqli_query($conn, $sql);
    if (!$ok) {
        $err = urlencode(mysqli_error($conn));
        header("Location: /sistema/src/Views/ejecucion/nuevo.php?error=$err&mes=$mes&anio=$anio");
        exit;
    }
}

header("Location: /sistema/src/Views/ejecucion/index.php?mes=$mes&anio=$anio&guardado=1");
exit;
