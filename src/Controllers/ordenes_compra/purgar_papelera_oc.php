<?php
/*
 * purgar_papelera_oc.php
 * Elimina permanentemente todas las OC cuyo deleted_at tenga más de 14 días.
 * Borra en orden: oc_partidas → oc_renglones → ordenes_compra (respeta FK).
 * Puede llamarse manualmente o via cron.
 * PHP 5.6 — sin ??
 */
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    header('Location: ../../Views/ordenes_compra/index.php');
    exit;
}

require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';

/* IDs de OC candidatas a eliminación definitiva (>14 días en papelera) */
$res = mysqli_query($conn,
    "SELECT id FROM ordenes_compra
     WHERE deleted_at IS NOT NULL
       AND deleted_at <= NOW() - INTERVAL 14 DAY"
);

$eliminadas = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $oc_id = (int) $row['id'];

    /* 1. Eliminar partidas */
    $s1 = mysqli_prepare($conn, "DELETE FROM oc_partidas WHERE oc_id = ?");
    mysqli_stmt_bind_param($s1, 'i', $oc_id);
    mysqli_stmt_execute($s1);

    /* 2. Eliminar renglones */
    $s2 = mysqli_prepare($conn, "DELETE FROM oc_renglones WHERE oc_id = ?");
    mysqli_stmt_bind_param($s2, 'i', $oc_id);
    mysqli_stmt_execute($s2);

    /* 3. Eliminar la orden */
    $s3 = mysqli_prepare($conn, "DELETE FROM ordenes_compra WHERE id = ?");
    mysqli_stmt_bind_param($s3, 'i', $oc_id);
    mysqli_stmt_execute($s3);

    $eliminadas++;
}

header("Location: ../../Views/ordenes_compra/index.php?papelera=1&msg=purgado&n=" . $eliminadas);
exit();
?>
