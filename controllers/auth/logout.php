<?php
session_start();
require_once dirname(__DIR__) . '/../src/Models/audit_log.php';
if (isset($_SESSION['usuario_id'])) {
    log_activity((int) $_SESSION['usuario_id'], 'LOGOUT', 'auth', 'Cierre de sesión');
}
session_unset();
session_destroy();
$reason = isset($_GET['reason']) ? '?reason=' . urlencode($_GET['reason']) : '';
header('Location: /sistema/public/login.php' . $reason);
exit;

