<?php
/**
 * sidebar.php — Barra lateral compartida del sistema con acordeón colapsable y persistencia
 * Consolidada, adaptable y compatible con PHP 5.6
 */

if (session_id() === '') {
    session_start();
}

$sb_current = '';
if (isset($active) && $active !== '') {
    $sb_current = $active;
} elseif (isset($active_page) && $active_page !== '') {
    $sb_current = $active_page;
}

if ($sb_current === '') {
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (strpos($uri, '/public/index.php') !== false || $uri === '/sistema/' || $uri === '/sistema/public/' || $uri === '/sistema/public/index.php') {
        $sb_current = 'inicio';
    } elseif (strpos($uri, '/ordenes_pago/nueva_op_ivss.php') !== false) {
        $sb_current = 'op-ivss';
    } elseif (strpos($uri, '/ordenes_pago/nueva_op_banavih.php') !== false) {
        $sb_current = 'op-banavih';
    } elseif (strpos($uri, '/ordenes_pago/nueva_op.php') !== false) {
        $sb_current = 'op-nueva';
    } elseif (strpos($uri, '/ordenes_pago/') !== false) {
        $sb_current = 'op-lista';
    } elseif (strpos($uri, '/ordenes_compra/nueva_oc.php') !== false) {
        $sb_current = 'oc-nueva';
    } elseif (strpos($uri, '/ordenes_compra/') !== false) {
        $sb_current = 'oc-lista';
    } elseif (strpos($uri, '/ordenes_servicio/nueva_os.php') !== false) {
        $sb_current = 'os-nueva';
    } elseif (strpos($uri, '/ordenes_servicio/') !== false) {
        $sb_current = 'os-lista';
    } elseif (strpos($uri, '/ejecucion/listado.php') !== false) {
        $sb_current = 'ejecucion-lista';
    } elseif (strpos($uri, '/ejecucion/matriz.php') !== false) {
        $sb_current = 'ejecucion-individual';
    } elseif (strpos($uri, '/ejecucion/') !== false) {
        $sb_current = 'ejecucion';
    } elseif (strpos($uri, '/reportes/') !== false) {
        $sb_current = 'reportes';
    } elseif (strpos($uri, '/perfil/') !== false) {
        $sb_current = 'perfil';
    } elseif (strpos($uri, '/ayuda/') !== false) {
        $sb_current = 'ayuda';
    }
}

if (!isset($es_admin)) {
    $es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');
}

if (!function_exists('sb_active')) {
    function sb_active($id, $current)
    {
        return ($id === $current) ? ' active' : '';
    }
}

// Resolver información del usuario en sesión con nombre completo y foto
$sb_usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Usuario';
$sb_nombre = '';
if (!empty($_SESSION['usuario_nombre'])) {
    $sb_nombre = $_SESSION['usuario_nombre'];
} elseif (!empty($_SESSION['nombre'])) {
    $sb_nombre = $_SESSION['nombre'];
}

$sb_foto = isset($_SESSION['foto']) ? $_SESSION['foto'] : (isset($_SESSION['usuario_foto']) ? $_SESSION['usuario_foto'] : '');

// Si sigue vacío o es igual al username, buscar en BD si hay conexión disponible
if (empty($sb_nombre) || strtolower(trim($sb_nombre)) === strtolower(trim($sb_usuario)) || empty($sb_foto)) {
    $cx_path = dirname(__DIR__) . '/config/conexion.php';
    if (!isset($conn) && file_exists($cx_path)) {
        @include_once $cx_path;
    }
    if (isset($conn) && $conn) {
        $u_id = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : 0;
        if ($u_id > 0) {
            $st = @mysqli_prepare($conn, "SELECT nombre, foto FROM usuarios WHERE id = ? LIMIT 1");
            if ($st) {
                @mysqli_stmt_bind_param($st, 'i', $u_id);
                if (@mysqli_stmt_execute($st)) {
                    $rs = @mysqli_stmt_get_result($st);
                    if ($rs && $rw = @mysqli_fetch_assoc($rs)) {
                        if (!empty($rw['nombre'])) {
                            $sb_nombre = $rw['nombre'];
                            $_SESSION['usuario_nombre'] = $rw['nombre'];
                        }
                        if (!empty($rw['foto'])) {
                            $sb_foto = $rw['foto'];
                            $_SESSION['foto'] = $sb_foto;
                            $_SESSION['usuario_foto'] = $sb_foto;
                        }
                    }
                }
                @mysqli_stmt_close($st);
            }
        } elseif (!empty($sb_usuario)) {
            $st = @mysqli_prepare($conn, "SELECT nombre, foto FROM usuarios WHERE usuario = ? LIMIT 1");
            if ($st) {
                @mysqli_stmt_bind_param($st, 's', $sb_usuario);
                if (@mysqli_stmt_execute($st)) {
                    $rs = @mysqli_stmt_get_result($st);
                    if ($rs && $rw = @mysqli_fetch_assoc($rs)) {
                        if (!empty($rw['nombre'])) {
                            $sb_nombre = $rw['nombre'];
                            $_SESSION['usuario_nombre'] = $rw['nombre'];
                        }
                        if (!empty($rw['foto'])) {
                            $sb_foto = $rw['foto'];
                            $_SESSION['foto'] = $sb_foto;
                            $_SESSION['usuario_foto'] = $sb_foto;
                        }
                    }
                }
                @mysqli_stmt_close($st);
            }
        }
    }
}

if (empty($sb_nombre)) {
    $sb_nombre = $sb_usuario;
}

$sb_rol_raw = isset($_SESSION['rol']) ? $_SESSION['rol'] : 'usuario';
$sb_rol_label = ($sb_rol_raw === 'admin') ? 'ADMINISTRADOR' : strtoupper($sb_rol_raw);

// Calcular iniciales
$words = preg_split('/\s+/', trim($sb_nombre));
if (count($words) >= 2 && !empty($words[0]) && !empty($words[1])) {
    $sb_inicial = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
} else {
    $sb_inicial = strtoupper(mb_substr($sb_nombre, 0, 2));
}

// Determinar qué grupo debe estar abierto según $sb_current
$is_oc = in_array($sb_current, array('oc-nueva', 'oc-lista'));
$is_op = in_array($sb_current, array('op-nueva', 'op-banavih', 'op-ivss', 'op-lista'));
$is_os = in_array($sb_current, array('os-nueva', 'os-lista'));
$is_prov = in_array($sb_current, array('prov-nueva', 'prov-nuevo', 'prov-lista', 'proveedores'));
$is_prod = in_array($sb_current, array('prod-nuevo', 'prod-lista'));
$is_ejec = in_array($sb_current, array('ejecucion', 'ejecucion-lista', 'ejecucion-nueva', 'ejecucion-individual'));
$is_adm = in_array($sb_current, array('partidas', 'auditoria', 'usuarios'));
?>
<style>
    /* Premium typography — Geist globally across the entire site */
    @import url('/sistema/assets/fonts/fonts.css');

    html,
    body,
    body *,
    input,
    button,
    select,
    textarea,
    table,
    th,
    td,
    p,
    span,
    label,
    a,
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
    .title-cell,
    .title-cell h2,
    .sb-section-label,
    .sb-parent-label,
    .sb-user-name,
    .sb-user-badge,
    .sb-brand-title {
        font-family: 'Geist', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
    }

    code,
    pre,
    kbd,
    samp,
    .mono,
    .font-mono {
        font-family: 'Geist Mono', 'JetBrains Mono', monospace !important;
    }

    /* Ocultar el antiguo header superior en todas las páginas */
    .site-header {
        display: none !important;
    }

    .layout {
        display: flex !important;
        min-height: 100vh !important;
    }

    /* ── Estructura de la barra lateral ── */
    .sidebar,
    #sidebar.sidebar {
        width: 250px !important;
        min-width: 250px !important;
        max-width: 250px !important;
        flex-shrink: 0 !important;
        background: #000000 !important;
        color: #e2e8f0 !important;
        height: 100vh !important;
        position: relative !important;
        position: -webkit-sticky !important;
        position: sticky !important;
        top: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        padding: 0 !important;
        box-sizing: border-box !important;
        font-family: 'Geist', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        border-right: 1px solid #111115 !important;
        transition: width 0.22s cubic-bezier(0.4, 0, 0.2, 1), min-width 0.22s cubic-bezier(0.4, 0, 0.2, 1), max-width 0.22s cubic-bezier(0.4, 0, 0.2, 1) !important;
        z-index: 100 !important;
    }

    /* ── Sidebar Header: Logo Institucional + Botón Toggle ── */
    .sb-top-header {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        padding: 12px 16px !important;
        border-bottom: 1px solid #18181b !important;
        background: #000000 !important;
        gap: 8px !important;
        min-height: 58px !important;
        box-sizing: border-box !important;
    }

    .sb-brand-wrap {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        text-decoration: none !important;
        color: inherit !important;
        overflow: hidden !important;
        flex: 1 !important;
    }

    .sb-brand-logo {
        height: 32px !important;
        width: 32px !important;
        min-width: 32px !important;
        object-fit: contain !important;
        display: block !important;
    }

    .sb-brand-text {
        display: flex !important;
        flex-direction: column !important;
        gap: 1px !important;
        overflow: hidden !important;
        transition: opacity 0.2s ease, width 0.2s ease !important;
    }

    .sb-brand-title {
        font-size: 14px !important;
        font-weight: 700 !important;
        color: #ffffff !important;
        letter-spacing: 0.3px !important;
        white-space: nowrap !important;
        line-height: 1.2 !important;
    }

    .sb-brand-sub {
        font-size: 10px !important;
        color: #94a3b8 !important;
        white-space: nowrap !important;
        letter-spacing: 0.2px !important;
    }

    .sb-toggle-btn {
        background: #111114 !important;
        border: 1px solid #27272a !important;
        color: #a1a1aa !important;
        width: 28px !important;
        height: 28px !important;
        min-width: 28px !important;
        border-radius: 6px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        cursor: pointer !important;
        transition: all 0.15s ease !important;
        padding: 0 !important;
        flex-shrink: 0 !important;
    }

    .sb-toggle-btn:hover {
        background: #1e293b !important;
        color: #ffffff !important;
        border-color: #3b82f6 !important;
    }

    .sb-toggle-btn svg {
        transition: transform 0.22s ease !important;
    }

    /* ── Contenedor scrollable de navegación ── */
    .sidebar .sb-nav {
        display: flex !important;
        flex-direction: column !important;
        gap: 2px !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        padding: 10px 16px 10px 10px !important;
        flex: 1 !important;
    }

    .sidebar .sb-nav::-webkit-scrollbar {
        width: 4px !important;
    }

    .sidebar .sb-nav::-webkit-scrollbar-thumb {
        background: #27272a !important;
        border-radius: 4px !important;
    }

    .sidebar .sb-section-label {
        padding: 13px 10px 6px 10px !important;
        font-size: 9.5px !important;
        font-weight: 700 !important;
        color: #52525b !important;
        letter-spacing: 1.2px !important;
        text-transform: uppercase !important;
        margin: 0 !important;
    }

    .sidebar .sb-group {
        display: flex !important;
        flex-direction: column !important;
        position: relative !important;
        border-radius: 8px !important;
    }

    .sidebar .sb-parent-label {
        padding: 8px 10px !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        color: #a1a1aa !important;
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        margin: 0 !important;
        cursor: pointer !important;
        user-select: none !important;
        transition: background 0.15s ease, color 0.15s ease !important;
        border-radius: 8px !important;
    }

    .sidebar .sb-parent-label:hover {
        background: #121216 !important;
        color: #f4f4f5 !important;
    }

    .sidebar .sb-group.open>.sb-parent-label {
        color: #ffffff !important;
    }

    .sidebar .sb-parent-link {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        flex: 1 !important;
        color: inherit !important;
        text-decoration: none !important;
        min-width: 0 !important;
    }

    .sidebar .sb-parent-link:hover {
        color: #818cf8 !important;
    }

    .sidebar .sb-arrow {
        margin-left: auto !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        transition: transform 0.2s ease !important;
        color: #71717a !important;
        width: 16px !important;
        height: 16px !important;
        flex-shrink: 0 !important;
    }

    .sidebar .sb-group.open>.sb-parent-label .sb-arrow {
        transform: rotate(90deg) !important;
        color: #cbd5e1 !important;
    }

    .sidebar .sb-submenu {
        display: none !important;
        flex-direction: column !important;
        gap: 2px !important;
        padding-left: 6px !important;
    }

    .sidebar .sb-group.open>.sb-submenu {
        display: flex !important;
    }

    .sidebar a.sb-link,
    .sidebar .sb-link {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        padding: 8px 10px !important;
        color: #a1a1aa !important;
        text-decoration: none !important;
        font-size: 14px !important;
        font-weight: 400 !important;
        border-radius: 8px !important;
        transition: background 0.15s ease, color 0.15s ease !important;
        background: transparent !important;
        margin: 0 !important;
        position: relative !important;
        box-sizing: border-box !important;
    }

    .sidebar a.sb-link.sub-link,
    .sidebar .sb-link.sub-link {
        padding-left: 32px !important;
        font-size: 13px !important;
        color: #94a3b8 !important;
        position: relative !important;
    }

    .sidebar a.sb-link.sub-link::before,
    .sidebar .sb-link.sub-link::before {
        content: '' !important;
        position: absolute !important;
        left: 18px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        width: 4px !important;
        height: 4px !important;
        border-radius: 50% !important;
        background: #52525b !important;
        transition: all 0.15s ease !important;
    }

    .sidebar a.sb-link.sub-link:hover::before,
    .sidebar .sb-link.sub-link:hover::before {
        background: #38bdf8 !important;
        box-shadow: 0 0 6px rgba(56, 189, 248, 0.6) !important;
    }

    .sidebar a.sb-link:hover,
    .sidebar .sb-link:hover {
        background: #121216 !important;
        color: #ffffff !important;
    }

    .sidebar a.sb-link.active,
    .sidebar .sb-link.active,
    .sidebar a.sb-link.active>span,
    .sidebar .sb-link.active>span {
        background: #18181b !important;
        color: #ffffff !important;
        font-weight: 700 !important;
    }

    .sidebar a.sb-link.active,
    .sidebar .sb-link.active {
        border-left: 3px solid #ffffff !important;
    }

    .sidebar a.sb-link.sub-link.active,
    .sidebar .sb-link.sub-link.active,
    .sidebar a.sb-link.sub-link.active>span,
    .sidebar .sb-link.sub-link.active>span {
        background: #18181b !important;
        color: #ffffff !important;
        font-weight: 700 !important;
        border-left: 3px solid #ffffff !important;
    }

    .sidebar a.sb-link.sub-link.active::before,
    .sidebar .sb-link.sub-link.active::before {
        background: #ffffff !important;
        box-shadow: 0 0 6px rgba(255, 255, 255, 0.4) !important;
        transform: translateY(-50%) scale(1.3) !important;
    }

    .sidebar .sb-icon {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 20px !important;
        height: 20px !important;
        min-width: 20px !important;
        color: #71717a !important;
        flex-shrink: 0 !important;
    }

    .sidebar a.sb-link:hover .sb-icon,
    .sidebar a.sb-link.active .sb-icon,
    .sidebar .sb-parent-label:hover .sb-icon,
    .sidebar .sb-group.open>.sb-parent-label .sb-icon {
        color: #ffffff !important;
    }

    /* ── Flex-gap fallback (browsers sin gap en flex): márgenes por defecto,
       se anulan donde gap sí funciona. Sin cambio visual en navegadores modernos. ── */
    .sidebar .sb-top-header>*+*,
    .sidebar .sb-brand-wrap>*+*,
    .sidebar .sb-user-card>*+* {
        margin-left: 8px !important;
    }

    .sidebar .sb-user-card>*+* {
        margin-left: 10px !important;
    }

    .sidebar .sb-user-info>*+* {
        margin-top: 3px !important;
    }

    @supports (gap: 8px) {
        .sidebar .sb-top-header>*+*,
        .sidebar .sb-brand-wrap>*+*,
        .sidebar .sb-user-card>*+* {
            margin-left: 0 !important;
        }

        .sidebar .sb-user-info>*+* {
            margin-top: 0 !important;
        }
    }

    /* ── User Card inferior ── */
    .sidebar .sb-user-card {
        margin: 10px 10px 14px 10px !important;
        padding: 9px 11px !important;
        background: #000000 !important;
        border: 1px solid #1e293b !important;
        border-radius: 12px !important;
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        position: relative !important;
        text-decoration: none !important;
        cursor: pointer !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25) !important;
    }

    .sidebar .sb-user-card:hover {
        background: #0c1524 !important;
        border-color: #38bdf8 !important;
        box-shadow: 0 6px 16px rgba(56, 189, 248, 0.15) !important;
        transform: translateY(-1px) !important;
    }

    .sidebar .sb-user-avatar-wrap {
        position: relative !important;
        flex-shrink: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .sidebar .sb-user-avatar {
        width: 36px !important;
        height: 36px !important;
        min-width: 36px !important;
        border-radius: 10px !important;
        background: linear-gradient(135deg, #0284c7 0%, #0369a1 50%, #075985 100%) !important;
        color: #ffffff !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
        border: 1px solid rgba(56, 189, 248, 0.4) !important;
        box-shadow: 0 2px 8px rgba(2, 132, 199, 0.3) !important;
        transition: transform 0.2s ease, border-color 0.2s ease !important;
    }

    .sidebar .sb-user-card:hover .sb-user-avatar {
        transform: scale(1.05) !important;
        border-color: #38bdf8 !important;
    }

    .sidebar .sb-user-status-dot {
        position: absolute !important;
        bottom: -2px !important;
        right: -2px !important;
        width: 9px !important;
        height: 9px !important;
        background: #22c55e !important;
        border: 2px solid #000000 !important;
        border-radius: 50% !important;
        box-shadow: 0 0 6px #22c55e !important;
    }

    .sidebar .sb-user-info {
        display: flex !important;
        flex-direction: column !important;
        gap: 3px !important;
        overflow: hidden !important;
        flex: 1 !important;
    }

    .sidebar .sb-user-name {
        font-size: 12px !important;
        font-weight: 700 !important;
        color: #f8fafc !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        letter-spacing: -0.1px !important;
    }

    .sidebar .sb-user-sub {
        display: flex !important;
        align-items: center !important;
    }

    .sidebar .sb-user-badge {
        display: inline-flex !important;
        align-items: center !important;
        padding: 2px 7px !important;
        font-size: 8.5px !important;
        font-weight: 700 !important;
        background: rgba(56, 189, 248, 0.12) !important;
        color: #38bdf8 !important;
        border: 1px solid rgba(56, 189, 248, 0.25) !important;
        border-radius: 4px !important;
        align-self: flex-start !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }

    .sidebar .sb-user-arrow {
        color: #475569 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        transition: color 0.2s ease, transform 0.2s ease !important;
    }

    .sidebar .sb-user-card:hover .sb-user-arrow {
        color: #38bdf8 !important;
        transform: translateX(2px) !important;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       MODO COLAPSADO (COLLAPSED STATE) - ICONOS PERFECTAMENTE CENTRADOS
       ═══════════════════════════════════════════════════════════════════════ */
    /* ═══════════════════════════════════════════════════════════════════════
       MODO COLAPSADO (COLLAPSED STATE) - ICONOS PERFECTAMENTE CENTRADOS
       ═══════════════════════════════════════════════════════════════════════ */
    .sidebar.collapsed,
    #sidebar.sidebar.collapsed {
        width: 64px !important;
        min-width: 64px !important;
        max-width: 64px !important;
        overflow: visible !important;
    }

    .sidebar.collapsed .sb-top-header {
        padding: 10px 0 8px 0 !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        min-height: auto !important;
    }

    .sidebar.collapsed .sb-brand-wrap {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 100% !important;
        flex: 0 0 auto !important;
        margin: 0 !important;
    }

    .sidebar.collapsed .sb-brand-logo {
        height: 30px !important;
        width: 30px !important;
        min-width: 30px !important;
        margin: 0 auto !important;
    }

    .sidebar.collapsed .sb-brand-text,
    .sidebar.collapsed .sb-arrow,
    .sidebar.collapsed .sb-user-info,
    .sidebar.collapsed a.sb-link>span:not(.sb-icon):not(.sb-flyout-menu),
    .sidebar.collapsed .sb-parent-label>span:not(.sb-icon):not(.sb-flyout-menu),
    .sidebar.collapsed .sb-parent-link>span:not(.sb-icon):not(.sb-flyout-menu) {
        display: none !important;
    }

    /* Ocultar ABSOLUTAMENTE todos los submenús y sub-links en modo colapsado */
    .sidebar.collapsed .sb-submenu,
    .sidebar.collapsed .sb-group>.sb-submenu,
    .sidebar.collapsed .sb-group.open>.sb-submenu,
    .sidebar.collapsed a.sb-link.sub-link,
    .sidebar.collapsed .sb-link.sub-link {
        display: none !important;
        visibility: hidden !important;
        width: 0 !important;
        height: 0 !important;
        min-width: 0 !important;
        min-height: 0 !important;
        max-width: 0 !important;
        max-height: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        border: none !important;
        overflow: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }

    .sidebar.collapsed .sb-toggle-btn {
        width: 30px !important;
        height: 24px !important;
        min-width: 30px !important;
        margin: 0 auto !important;
    }

    .sidebar.collapsed .sb-toggle-btn svg {
        transform: rotate(180deg) !important;
    }

    .sidebar.collapsed .sb-nav {
        padding: 8px 0 !important;
        gap: 4px !important;
        align-items: center !important;
        overflow-x: visible !important;
    }

    /* Divisores sutiles en modo colapsado */
    .sidebar.collapsed .sb-section-label {
        font-size: 0 !important;
        padding: 0 !important;
        margin: 6px auto !important;
        width: 28px !important;
        height: 1px !important;
        background: #1c1c20 !important;
        display: block !important;
        border: none !important;
    }

    /* Botón de icono perfectamente centrado en modo colapsado (excluyendo sub-links) */
    .sidebar.collapsed a.sb-link:not(.sub-link),
    .sidebar.collapsed .sb-link:not(.sub-link),
    .sidebar.collapsed .sb-group,
    .sidebar.collapsed .sb-parent-label {
        width: 42px !important;
        height: 42px !important;
        min-width: 42px !important;
        max-width: 42px !important;
        min-height: 42px !important;
        max-height: 42px !important;
        padding: 0 !important;
        margin: 0 auto !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 8px !important;
        border: none !important;
        box-sizing: border-box !important;
        position: relative !important;
    }

    .sidebar.collapsed .sb-parent-link {
        width: 100% !important;
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .sidebar.collapsed .sb-icon {
        width: 20px !important;
        height: 20px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        margin: 0 !important;
        color: #94a3b8 !important;
    }

    .sidebar.collapsed a.sb-link:not(.sub-link):hover,
    .sidebar.collapsed .sb-parent-label:hover {
        background: #141418 !important;
        color: #ffffff !important;
    }

    .sidebar.collapsed a.sb-link:not(.sub-link):hover .sb-icon,
    .sidebar.collapsed .sb-parent-label:hover .sb-icon {
        color: #60a5fa !important;
    }

    .sidebar.collapsed a.sb-link:not(.sub-link).active,
    .sidebar.collapsed .sb-group.is-active>.sb-parent-label,
    .sidebar.collapsed .sb-group.open>.sb-parent-label {
        background: #18181b !important;
        border: 1px solid #3f3f46 !important;
    }

    .sidebar.collapsed a.sb-link:not(.sub-link).active .sb-icon,
    .sidebar.collapsed .sb-group.is-active>.sb-parent-label .sb-icon,
    .sidebar.collapsed .sb-group.open>.sb-parent-label .sb-icon {
        color: #ffffff !important;
    }

    .sidebar.collapsed .sb-user-card {
        margin: 8px auto 12px auto !important;
        width: 42px !important;
        height: 42px !important;
        padding: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        position: relative !important;
    }

    .sidebar.collapsed .sb-user-avatar {
        width: 36px !important;
        height: 36px !important;
        margin: 0 auto !important;
    }

    .sidebar.collapsed .sb-user-info,
    .sidebar.collapsed .sb-user-arrow {
        display: none !important;
    }

    /* ── Submenús flotantes en hover (Flyout on Hover) ── */
    .sidebar .sb-flyout-menu {
        display: none;
    }

    /* Transición deliberada y suave para subelementos flotantes en modo colapsado */
    @keyframes sbFlyoutSlideIn {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Flyout para grupos en modo colapsado */
    .sidebar.collapsed .sb-group:hover>.sb-flyout-menu {
        display: flex !important;
        flex-direction: column !important;
        position: absolute !important;
        left: 52px !important;
        top: 0 !important;
        min-width: 220px !important;
        background: #09090b !important;
        border: 1px solid #27272a !important;
        border-radius: 10px !important;
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.85) !important;
        padding: 6px !important;
        z-index: 99999 !important;
        animation: sbFlyoutSlideIn 0.15s cubic-bezier(0.4, 0, 0.2, 1) 0.15s both !important;
    }

    /* Tooltip para enlaces directos en modo colapsado */
    .sidebar.collapsed a.sb-link:not(.sub-link):hover>.sb-flyout-menu,
    .sidebar.collapsed .sb-user-card:hover>.sb-flyout-menu {
        display: flex !important;
        flex-direction: column !important;
        position: absolute !important;
        left: 52px !important;
        top: 2px !important;
        white-space: nowrap !important;
        background: #09090b !important;
        border: 1px solid #27272a !important;
        border-radius: 8px !important;
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.85) !important;
        padding: 8px 14px !important;
        z-index: 99999 !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        color: #f4f4f5 !important;
        animation: sbFlyoutSlideIn 0.38s cubic-bezier(0.4, 0, 0.2, 1) 0.15s both !important;
    }

    .sb-flyout-title {
        padding: 5px 10px 4px 10px !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        color: #818cf8 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        border-bottom: 1px solid #18181b !important;
        margin-bottom: 3px !important;
    }

    .sb-flyout-desc {
        font-size: 10.5px !important;
        font-weight: 400 !important;
        color: #94a3b8 !important;
        margin-top: 2px !important;
        display: block !important;
        white-space: normal !important;
        max-width: 220px !important;
        line-height: 1.35 !important;
    }

    .sb-flyout-item {
        padding: 7px 10px !important;
        font-size: 12px !important;
        color: #d4d4d8 !important;
        text-decoration: none !important;
        border-radius: 6px !important;
        transition: background-color 0.25s cubic-bezier(0.4, 0, 0.2, 1), color 0.25s cubic-bezier(0.4, 0, 0.2, 1), transform 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
    }

    .sb-flyout-item:hover {
        background: #18181b !important;
        color: #ffffff !important;
        transform: translateX(3px) !important;
    }



    /* ── Suppress sidebar and all no-print elements on print ── */
    @media print {

        html body .sidebar,
        html body #sidebar,
        html body nav#sidebar,
        html body nav.sidebar,
        html body #sidebar.sidebar,
        html body nav#sidebar.sidebar,
        html body .no-print {
            display: none !important;
            visibility: hidden !important;
            width: 0 !important;
            min-width: 0 !important;
            max-width: 0 !important;
            height: 0 !important;
            min-height: 0 !important;
            overflow: hidden !important;
            position: absolute !important;
            left: -9999px !important;
        }

        html body .layout {
            display: block !important;
        }

        html body .main-content {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        html body .help-fab {
            display: none !important;
        }
    }

    /* ── Botón flotante de Ayuda (esquina superior derecha) ── */
    .help-fab {
        position: fixed !important;
        top: 18px !important;
        right: 18px !important;
        z-index: 9999 !important;
        width: 42px !important;
        height: 42px !important;
        border-radius: 50% !important;
        background: #101a36 !important;
        color: #c7d2fe !important;
        border: 1px solid #1e3a8a !important;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        text-decoration: none !important;
        transition: all 0.18s ease !important;
        font-family: 'Inter', sans-serif !important;
    }

    .help-fab:hover {
        background: #1e1e38 !important;
        color: #ffffff !important;
        border-color: #6366f1 !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 10px 24px rgba(99, 102, 241, 0.4) !important;
    }

    .help-fab .fab-tip {
        position: absolute !important;
        right: 50px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        background: #0c0c0f !important;
        color: #e4e4e7 !important;
        border: 1px solid #27272a !important;
        border-radius: 8px !important;
        padding: 5px 10px !important;
        font-size: 11px !important;
        font-weight: 600 !important;
        white-space: nowrap !important;
        opacity: 0 !important;
        pointer-events: none !important;
        transition: opacity 0.15s ease !important;
        box-shadow: 0 12px 26px rgba(0, 0, 0, 0.6) !important;
    }

    .help-fab:hover .fab-tip {
        opacity: 1 !important;
    }
</style>

<!-- ═══ GLOBAL PREMIUM ANIMATIONS (injected by sidebar) ══════════════════════ -->
<style id="gbl-anim">
    /* ─── Keyframes ──────────────────────────────────────────────────────── */
    @-webkit-keyframes gbl-row-in {
        from {
            opacity: 0;
            -webkit-transform: translateY(10px);
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            -webkit-transform: translateY(0);
            transform: translateY(0);
        }
    }

    @keyframes gbl-row-in {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @-webkit-keyframes gbl-card-in {
        from {
            opacity: 0;
            -webkit-transform: translateY(14px);
            transform: translateY(14px);
        }

        to {
            opacity: 1;
            -webkit-transform: translateY(0);
            transform: translateY(0);
        }
    }

    @keyframes gbl-card-in {
        from {
            opacity: 0;
            transform: translateY(14px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @-webkit-keyframes gbl-btn-press {
        0% {
            -webkit-transform: scale(1);
            transform: scale(1);
        }

        45% {
            -webkit-transform: scale(0.96);
            transform: scale(0.96);
        }

        100% {
            -webkit-transform: scale(1);
            transform: scale(1);
        }
    }

    @keyframes gbl-btn-press {
        0% {
            transform: scale(1);
        }

        45% {
            transform: scale(0.96);
        }

        100% {
            transform: scale(1);
        }
    }

    /* ─── Table rows: stagger handled via JS ─────────────────────────────── */
    tbody tr.gbl-row-ready {
        opacity: 0;
    }

    tbody tr.gbl-row-visible {
        -webkit-animation: gbl-row-in 0.38s cubic-bezier(0.22, 1, 0.36, 1) both;
        animation: gbl-row-in 0.38s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    /* ─── Panels / Stat Cards ────────────────────────────────────────────── */
    .panel.gbl-card-ready,
    .stat-card.gbl-card-ready {
        opacity: 0;
    }

    .panel.gbl-card-visible,
    .stat-card.gbl-card-visible {
        -webkit-animation: gbl-card-in 0.42s cubic-bezier(0.22, 1, 0.36, 1) both;
        animation: gbl-card-in 0.42s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    /* ─── Input / Select focus glow ──────────────────────────────────────── */
    input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]):not([type="range"]):focus,
    select:focus,
    textarea:focus {
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.16), 0 0 14px rgba(99, 102, 241, 0.08) !important;
        border-color: #818cf8 !important;
        outline: none !important;
        transition: box-shadow 0.18s ease, border-color 0.18s ease !important;
    }

    /* ─── Button active press micro-feedback ─────────────────────────────── */
    button:active,
    a.btn-primary:active,
    a.btn-secondary:active,
    .btn-primary:active,
    .btn-secondary:active,
    .btn-seed:active,
    .btn-print:active,
    .quick-btn:active {
        -webkit-animation: gbl-btn-press 0.18s ease both !important;
        animation: gbl-btn-press 0.18s ease both !important;
    }

    /* ─── Row hover lift (all data tables) ───────────────────────────────── */
    tbody tr {
        transition: background 0.12s ease, box-shadow 0.12s ease !important;
    }

    tbody tr:hover {
        box-shadow: inset 3px 0 0 #6366f1 !important;
    }

    /* ─── Movimiento reducido: contenido siempre visible, sin animación ─── */
    @media (prefers-reduced-motion: reduce) {
        tbody tr.gbl-row-ready,
        .panel.gbl-card-ready,
        .stat-card.gbl-card-ready {
            opacity: 1 !important;
        }

        tbody tr.gbl-row-visible,
        .panel.gbl-card-visible,
        .stat-card.gbl-card-visible,
        button:active,
        a.btn-primary:active,
        a.btn-secondary:active,
        .btn-primary:active,
        .btn-secondary:active,
        .btn-seed:active,
        .btn-print:active,
        .quick-btn:active {
            -webkit-animation: none !important;
            animation: none !important;
        }
    }
</style>

<nav class="sidebar" id="sidebar">
    <script>
        (function () {
            try {
                if (localStorage.getItem('sidebar_collapsed') === '1') {
                    document.getElementById('sidebar').classList.add('collapsed');
                }
            } catch (e) { }
        })();
    </script>

    <!-- Top Header -->
    <div class="sb-top-header">
        <a href="/sistema/public/index.php" class="sb-brand-wrap" title="Contraloría MSR">
            <img src="/sistema/assets/img/logo.png" alt="Logo" class="sb-brand-logo">
            <div class="sb-brand-text">
                <span class="sb-brand-title">Contraloría MSR</span>
                <span class="sb-brand-sub">Gestión Interna.</span>
            </div>
        </a>
        <button type="button" class="sb-toggle-btn" onclick="toggleSidebarCollapse()" title="Colapsar / Expandir menú"
            aria-label="Toggle Sidebar">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                stroke-linecap="round" stroke-linejoin="round">
                <polyline points="11 17 6 12 11 7"></polyline>
                <polyline points="18 17 13 12 18 7"></polyline>
            </svg>
        </button>
    </div>

    <div class="sb-nav">
        <!-- ── PRINCIPAL ── -->
        <div class="sb-section-label">PRINCIPAL</div>
        <a href="/sistema/public/index.php"
            class="sb-link<?= sb_active('inicio', $sb_current) ?><?= sb_active('dashboard', $sb_current) ?>"
            id="nav-dashboard" title="Dashboard">
            <span class="sb-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7" />
                    <rect x="14" y="3" width="7" height="7" />
                    <rect x="14" y="14" width="7" height="7" />
                    <rect x="3" y="14" width="7" height="7" />
                </svg>
            </span>
            <span>Panel Principal</span>
            <div class="sb-flyout-menu">
                <div
                    style="font-weight: 700; color: #818cf8; text-transform: uppercase; font-size: 10.5px; letter-spacing: 0.5px;">
                    Dashboard</div>
                <span class="sb-flyout-desc">Panel principal y métricas generales</span>
            </div>
        </a>

        <!-- ── ÓRDENES ── -->
        <div class="sb-section-label">ÓRDENES</div>

        <!-- Órdenes de Compra -->
        <div class="sb-group<?= $is_oc ? ' open is-active' : '' ?>">
            <div class="sb-parent-label" onclick="toggleSbGroup(this)">
                <a href="/sistema/src/Views/ordenes_compra/index.php" class="sb-parent-link"
                    onclick="event.stopPropagation()" title="Órdenes de Compra">
                    <span class="sb-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <path d="M16 10a4 4 0 0 1-8 0" />
                        </svg>
                    </span>
                    <span>Órdenes de Compra</span>
                </a>
                <span class="sb-arrow">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </span>
            </div>
            <div class="sb-submenu">
                <?php if ($es_admin): ?>
                    <a href="/sistema/src/Views/ordenes_compra/nueva_oc.php"
                        class="sb-link sub-link<?= sb_active('oc-nueva', $sb_current) ?>" id="nav-oc-nueva">
                        <span>Nueva OC</span>
                    </a>
                <?php endif; ?>
                <a href="/sistema/src/Views/ordenes_compra/index.php"
                    class="sb-link sub-link<?= sb_active('oc-lista', $sb_current) ?>" id="nav-oc-lista">
                    <span>Listado</span>
                </a>
            </div>
            <!-- Flyout for Collapsed Hover -->
            <div class="sb-flyout-menu">
                <div class="sb-flyout-title">Órdenes de Compra</div>
                <span class="sb-flyout-desc"
                    style="padding: 0 10px 4px 10px; border-bottom: 1px solid #18181b; margin-bottom: 4px;">Gestión de
                    adquisiciones y compras</span>
                <a href="/sistema/src/Views/ordenes_compra/index.php" class="sb-flyout-item">Listado de OC</a>
                <?php if ($es_admin): ?>
                    <a href="/sistema/src/Views/ordenes_compra/nueva_oc.php" class="sb-flyout-item">Nueva OC</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Órdenes de Pago -->
        <div class="sb-group<?= $is_op ? ' open is-active' : '' ?>">
            <div class="sb-parent-label" onclick="toggleSbGroup(this)">
                <a href="/sistema/src/Views/ordenes_pago/index.php" class="sb-parent-link"
                    onclick="event.stopPropagation()" title="Órdenes de Pago">
                    <span class="sb-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2" />
                            <line x1="2" y1="10" x2="22" y2="10" />
                        </svg>
                    </span>
                    <span>Órdenes de Pago</span>
                </a>
                <span class="sb-arrow">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </span>
            </div>
            <div class="sb-submenu">
                <?php if ($es_admin): ?>
                    <a href="/sistema/src/Views/ordenes_pago/nueva_op.php"
                        class="sb-link sub-link<?= ($sb_current === 'op-nueva' || $sb_current === 'op-banavih' || $sb_current === 'op-ivss') ? ' active' : '' ?>" id="nav-op-nueva">
                        <span>Nueva Orden</span>
                    </a>
                <?php endif; ?>
                <a href="/sistema/src/Views/ordenes_pago/index.php"
                    class="sb-link sub-link<?= sb_active('op-lista', $sb_current) ?>" id="nav-op-lista">
                    <span>Listado</span>
                </a>
            </div>
            <!-- Flyout for Collapsed Hover -->
            <div class="sb-flyout-menu">
                <div class="sb-flyout-title">Órdenes de Pago</div>
                <span class="sb-flyout-desc"
                    style="padding: 0 10px 4px 10px; border-bottom: 1px solid #18181b; margin-bottom: 4px;">Emisión de
                    pagos y retenciones</span>
                <a href="/sistema/src/Views/ordenes_pago/index.php" class="sb-flyout-item">Listado de OP</a>
                <?php if ($es_admin): ?>
                    <a href="/sistema/src/Views/ordenes_pago/nueva_op.php" class="sb-flyout-item">Nueva OP</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Órdenes de Servicio -->
        <div class="sb-group<?= $is_os ? ' open' : '' ?>">
            <div class="sb-parent-label" onclick="toggleSbGroup(this)">
                <a href="/sistema/src/Views/ordenes_servicio/index.php" class="sb-parent-link"
                    onclick="event.stopPropagation()" title="Órdenes de Servicio">
                    <span class="sb-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
                        </svg>
                    </span>
                    <span>Órdenes de Servicio</span>
                </a>
                <span class="sb-arrow">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </span>
            </div>
            <div class="sb-submenu">
                <?php if ($es_admin): ?>
                    <a href="/sistema/src/Views/ordenes_servicio/nueva_os.php"
                        class="sb-link sub-link<?= sb_active('os-nueva', $sb_current) ?>" id="nav-os-nueva">
                        <span>Nueva OS</span>
                    </a>
                <?php endif; ?>
                <a href="/sistema/src/Views/ordenes_servicio/index.php"
                    class="sb-link sub-link<?= sb_active('os-lista', $sb_current) ?>" id="nav-os-lista">
                    <span>Listado</span>
                </a>
            </div>
            <!-- Flyout for Collapsed Hover -->
            <div class="sb-flyout-menu">
                <div class="sb-flyout-title">Órdenes de Servicio</div>
                <span class="sb-flyout-desc"
                    style="padding: 0 10px 4px 10px; border-bottom: 1px solid #18181b; margin-bottom: 4px;">Servicios
                    técnicos y contratos</span>
                <a href="/sistema/src/Views/ordenes_servicio/index.php" class="sb-flyout-item">Listado de OS</a>
                <?php if ($es_admin): ?>
                    <a href="/sistema/src/Views/ordenes_servicio/nueva_os.php" class="sb-flyout-item">Nueva OS</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── GESTIÓN ── -->
        <div class="sb-section-label">GESTIÓN</div>

        <!-- Proveedores -->
        <div class="sb-group<?= $is_prov ? ' open' : '' ?>">
            <div class="sb-parent-label" onclick="toggleSbGroup(this)">
                <a href="/sistema/src/Views/proveedores/index.php" class="sb-parent-link"
                    onclick="event.stopPropagation()" title="Proveedores">
                    <span class="sb-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </span>
                    <span>Proveedores</span>
                </a>
                <span class="sb-arrow">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </span>
            </div>
            <div class="sb-submenu">
                <?php if ($es_admin): ?>
                    <a href="/sistema/src/Views/proveedores/nuevo.php"
                        class="sb-link sub-link<?= sb_active('prov-nueva', $sb_current) ?><?= sb_active('prov-nuevo', $sb_current) ?>"
                        id="nav-prov-nueva">
                        <span>Nuevo Proveedor</span>
                    </a>
                <?php endif; ?>
                <a href="/sistema/src/Views/proveedores/index.php"
                    class="sb-link sub-link<?= sb_active('prov-lista', $sb_current) ?><?= sb_active('proveedores', $sb_current) ?>"
                    id="nav-prov-lista">
                    <span>Listado</span>
                </a>
            </div>
            <!-- Flyout for Collapsed Hover -->
            <div class="sb-flyout-menu">
                <div class="sb-flyout-title">Proveedores</div>
                <span class="sb-flyout-desc"
                    style="padding: 0 10px 4px 10px; border-bottom: 1px solid #18181b; margin-bottom: 4px;">Registro de
                    proveedores y RIF</span>
                <a href="/sistema/src/Views/proveedores/index.php" class="sb-flyout-item">Listado de Proveedores</a>
                <?php if ($es_admin): ?>
                    <a href="/sistema/src/Views/proveedores/nuevo.php" class="sb-flyout-item">Nuevo Proveedor</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Productos (admin only) -->
        <?php if ($es_admin): ?>
            <div class="sb-group<?= $is_prod ? ' open' : '' ?>">
                <div class="sb-parent-label" onclick="toggleSbGroup(this)">
                    <a href="/sistema/src/Views/productos/index.php" class="sb-parent-link"
                        onclick="event.stopPropagation()" title="Productos">
                        <span class="sb-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                <line x1="12" y1="22.08" x2="12" y2="12" />
                            </svg>
                        </span>
                        <span>Productos</span>
                    </a>
                    <span class="sb-arrow">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </span>
                </div>
                <div class="sb-submenu">
                    <a href="/sistema/src/Views/productos/nuevo.php"
                        class="sb-link sub-link<?= sb_active('prod-nuevo', $sb_current) ?>" id="nav-prod-nuevo">
                        <span>Nuevo Producto</span>
                    </a>
                    <a href="/sistema/src/Views/productos/index.php"
                        class="sb-link sub-link<?= sb_active('prod-lista', $sb_current) ?>" id="nav-prod-lista">
                        <span>Listado</span>
                    </a>
                </div>
                <!-- Flyout for Collapsed Hover -->
                <div class="sb-flyout-menu">
                    <div class="sb-flyout-title">Productos</div>
                    <span class="sb-flyout-desc"
                        style="padding: 0 10px 4px 10px; border-bottom: 1px solid #18181b; margin-bottom: 4px;">Catálogo de
                        bienes y suministros</span>
                    <a href="/sistema/src/Views/productos/index.php" class="sb-flyout-item">Listado de Productos</a>
                    <a href="/sistema/src/Views/productos/nuevo.php" class="sb-flyout-item">Nuevo Producto</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── SISTEMA ── -->
        <div class="sb-section-label">SISTEMA</div>

        <!-- Ejecución Presupuestaria -->
        <div class="sb-group<?= $is_ejec ? ' open' : '' ?>">
            <div class="sb-parent-label" onclick="toggleSbGroup(this)">
                <a href="/sistema/src/Views/ejecucion/listado.php" class="sb-parent-link"
                    onclick="event.stopPropagation()" title="Ejecución Presupuestaria">
                    <span class="sb-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <line x1="3" y1="9" x2="21" y2="9" />
                            <line x1="3" y1="15" x2="21" y2="15" />
                            <line x1="9" y1="3" x2="9" y2="21" />
                            <line x1="15" y1="3" x2="15" y2="21" />
                        </svg>
                    </span>
                    <span>Ejec. Presupuestaria</span>
                </a>
                <span class="sb-arrow">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </span>
            </div>
            <div class="sb-submenu">
                <a href="/sistema/src/Views/ejecucion/listado.php"
                    class="sb-link sub-link<?= sb_active('ejecucion-lista', $sb_current) ?>" id="nav-ejecucion-listado">
                    <span>Listado General</span>
                </a>
                <a href="/sistema/src/Views/ejecucion/index.php"
                    class="sb-link sub-link<?= sb_active('ejecucion', $sb_current) ?>" id="nav-ejecucion-matriz">
                    <span>Reporte Mes Actual</span>
                </a>
                <a href="/sistema/src/Views/ejecucion/matriz.php"
                    class="sb-link sub-link<?= sb_active('ejecucion-individual', $sb_current) ?>"
                    id="nav-ejecucion-individual">
                    <span>Vista Individual</span>
                </a>
                <?php if ($es_admin): ?>
                    <a href="/sistema/src/Views/ejecucion/nuevo.php"
                        class="sb-link sub-link<?= sb_active('ejecucion-nueva', $sb_current) ?>" id="nav-ejecucion-nueva">
                        <span>Nueva Partida</span>
                    </a>
                <?php endif; ?>
            </div>
            <!-- Flyout for Collapsed Hover -->
            <div class="sb-flyout-menu">
                <div class="sb-flyout-title">Ejecución Presupuestaria</div>
                <span class="sb-flyout-desc"
                    style="padding: 0 10px 4px 10px; border-bottom: 1px solid #18181b; margin-bottom: 4px;">Control
                    presupuestario y partidas</span>
                <a href="/sistema/src/Views/ejecucion/listado.php" class="sb-flyout-item">Listado General</a>
                <a href="/sistema/src/Views/ejecucion/index.php" class="sb-flyout-item">Matriz del Mes</a>
                <a href="/sistema/src/Views/ejecucion/matriz.php" class="sb-flyout-item">Vista Individual</a>
                <?php if ($es_admin): ?>
                    <a href="/sistema/src/Views/ejecucion/nuevo.php" class="sb-flyout-item">Nueva Partida</a>
                <?php endif; ?>
            </div>
        </div>

        <a href="/sistema/src/Views/reportes/index.php" class="sb-link<?= sb_active('reportes', $sb_current) ?>"
            id="nav-reportes" title="Reportes">
            <span class="sb-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="20" x2="18" y2="10" />
                    <line x1="12" y1="20" x2="12" y2="4" />
                    <line x1="6" y1="20" x2="6" y2="14" />
                </svg>
            </span>
            <span>Reportes</span>
            <div class="sb-flyout-menu">
                <div
                    style="font-weight: 700; color: #818cf8; text-transform: uppercase; font-size: 10.5px; letter-spacing: 0.5px;">
                    Reportes</div>
                <span class="sb-flyout-desc">Generación de informes de gestión</span>
            </div>
        </a>

        <a href="/sistema/src/Views/perfil/index.php" class="sb-link<?= sb_active('perfil', $sb_current) ?>"
            id="nav-perfil" title="Mi Perfil">
            <span class="sb-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                </svg>
            </span>
            <span>Mi Perfil</span>
            <div class="sb-flyout-menu">
                <div
                    style="font-weight: 700; color: #818cf8; text-transform: uppercase; font-size: 10.5px; letter-spacing: 0.5px;">
                    Mi Perfil</div>
                <span class="sb-flyout-desc">Configuración de usuario y sesión</span>
            </div>
        </a>

        <?php if ($es_admin): ?>
            <!-- Administración (grupo admin) -->
            <div class="sb-group<?= $is_adm ? ' open is-active' : '' ?>">
                <div class="sb-parent-label" onclick="toggleSbGroup(this)">
                    <a href="/sistema/src/Views/partidas/index.php" class="sb-parent-link" onclick="event.stopPropagation()"
                        title="Administración">
                        <span class="sb-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                <polyline points="9 12 11 14 15 10" />
                            </svg>
                        </span>
                        <span>Administración</span>
                    </a>
                    <span class="sb-arrow">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </span>
                </div>
                <div class="sb-submenu">
                    <a href="/sistema/src/Views/partidas/index.php"
                        class="sb-link sub-link<?= sb_active('partidas', $sb_current) ?>" id="nav-partidas">
                        <span>Partidas</span>
                    </a>
                    <a href="/sistema/src/Views/auditoria/index.php"
                        class="sb-link sub-link<?= sb_active('auditoria', $sb_current) ?>" id="nav-auditoria">
                        <span>Auditoría</span>
                    </a>
                    <a href="/sistema/src/Views/perfil/usuarios.php"
                        class="sb-link sub-link<?= sb_active('usuarios', $sb_current) ?>" id="nav-usuarios">
                        <span>Usuarios</span>
                    </a>
                </div>
                <!-- Flyout for Collapsed Hover -->
                <div class="sb-flyout-menu">
                    <div class="sb-flyout-title">Administración</div>
                    <span class="sb-flyout-desc"
                        style="padding: 0 10px 4px 10px; border-bottom: 1px solid #18181b; margin-bottom: 4px;">Herramientas
                        de configuración del sistema</span>
                    <a href="/sistema/src/Views/partidas/index.php" class="sb-flyout-item">Partidas</a>
                    <a href="/sistema/src/Views/auditoria/index.php" class="sb-flyout-item">Auditoría</a>
                    <a href="/sistema/src/Views/perfil/usuarios.php" class="sb-flyout-item">Usuarios</a>
                </div>
            </div>
        <?php endif; ?>

        <a href="/sistema/src/Views/ayuda/index.php" class="sb-link<?= sb_active('ayuda', $sb_current) ?>"
            id="nav-ayuda" title="Centro de Ayuda">
            <span class="sb-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
            </span>
            <span>Ayuda</span>
            <div class="sb-flyout-menu">
                <div
                    style="font-weight: 700; color: #818cf8; text-transform: uppercase; font-size: 10.5px; letter-spacing: 0.5px;">
                    Ayuda</div>
                <span class="sb-flyout-desc">Centro de ayuda y guías de uso</span>
            </div>
        </a>


        <a href="/sistema/controllers/auth/logout.php" class="sb-link<?= sb_active('logout', $sb_current) ?>"
            id="nav-logout" style="color: #f87171 !important;" title="Cerrar Sesión">
            <span class="sb-icon" style="color: #f87171 !important;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
            </span>
            <span>Cerrar Sesión</span>
            <div class="sb-flyout-menu">Cerrar Sesión</div>
        </a>

    </div><!-- /.sb-nav -->

    <!-- User Card -->
    <a href="/sistema/src/Views/perfil/index.php" class="sb-user-card"
        title="Mi Perfil — <?= htmlspecialchars($sb_nombre) ?> (<?= htmlspecialchars($sb_rol_label) ?>)">
        <div class="sb-user-avatar-wrap">
            <div class="sb-user-avatar" title="<?= htmlspecialchars($sb_nombre) ?>" style="overflow: hidden; padding: 0;">
                <?php if (!empty($sb_foto) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/sistema/assets/img/avatars/' . $sb_foto)): ?>
                    <img src="/sistema/assets/img/avatars/<?= htmlspecialchars($sb_foto) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                <?php else: ?>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                <?php endif; ?>
            </div>
            <span class="sb-user-status-dot" title="En línea"></span>
        </div>
        <div class="sb-user-info">
            <span class="sb-user-name"
                title="<?= htmlspecialchars($sb_nombre) ?>"><?= htmlspecialchars($sb_nombre) ?></span>
            <div class="sb-user-sub">
                <span class="sb-user-badge"><?= htmlspecialchars($sb_rol_label) ?></span>
            </div>
        </div>
        <div class="sb-user-arrow" title="Ver Perfil">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </div>
        <div class="sb-flyout-menu">
            <div style="font-weight: 700; color: #fff; margin-bottom: 2px; font-size: 12.5px;">
                <?= htmlspecialchars($sb_nombre) ?>
            </div>
            <div
                style="font-size: 9.5px; color: #38bdf8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                <?= htmlspecialchars($sb_rol_label) ?>
            </div>
            <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Clic para ver tu perfil</div>
        </div>
    </a>
    <?php if ($sb_current !== 'ayuda'): ?>
        <a href="/sistema/src/Views/ayuda/index.php" class="help-fab" id="help-fab" title="Centro de Ayuda"
            aria-label="Centro de Ayuda">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <span class="fab-tip">Centro de Ayuda</span>
        </a>
    <?php endif; ?>

</nav>

<script>
    function toggleSbGroup(headerEl) {
        var sb = document.getElementById('sidebar');
        if (sb && sb.classList.contains('collapsed')) {
            return;
        }
        var group = headerEl.parentElement;
        if (group && group.classList.contains('sb-group')) {
            group.classList.toggle('open');
        }
    }

    function toggleSidebarCollapse() {
        var sb = document.getElementById('sidebar');
        if (!sb) return;
        var isCollapsed = sb.classList.toggle('collapsed');
        try {
            localStorage.setItem('sidebar_collapsed', isCollapsed ? '1' : '0');
        } catch (e) { }
    }
</script>

<script>
    /* ═══ GLOBAL PREMIUM ANIMATIONS — IntersectionObserver ══════════════════════ */
    (function () {
        if (typeof IntersectionObserver === 'undefined') return;

        /* ── 1. Table row stagger-in ────────────────────────────────────────── */
        var rowObs = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var row = entry.target;
                var delay = (parseInt(row.getAttribute('data-row-i') || 0, 10)) * 28;
                row.style.animationDelay = delay + 'ms';
                row.classList.remove('gbl-row-ready');
                row.classList.add('gbl-row-visible');
                rowObs.unobserve(row);
            });
        }, { threshold: 0.05 });

        function initRows() {
            if (window.location.pathname.indexOf('/ejecucion/index.php') !== -1) {
                return;
            }
            var tables = document.querySelectorAll('table tbody');
            for (var t = 0; t < tables.length; t++) {
                var rows = tables[t].querySelectorAll('tr');
                for (var i = 0; i < rows.length; i++) {
                    var row = rows[i];
                    /* Skip group/subtotal rows that have distinct bg — keep them instant */
                    if (row.classList.contains('group-row') || row.classList.contains('subtotal-row')) continue;
                    row.setAttribute('data-row-i', i);
                    row.classList.add('gbl-row-ready');
                    rowObs.observe(row);
                }
            }
        }

        /* ── 2. Panel / Stat Card slide-up ─────────────────────────────────── */
        var cardObs = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                var delay = (parseInt(el.getAttribute('data-card-i') || 0, 10)) * 60;
                el.style.animationDelay = delay + 'ms';
                el.classList.remove('gbl-card-ready');
                el.classList.add('gbl-card-visible');
                cardObs.unobserve(el);
            });
        }, { threshold: 0.08 });

        function initCards() {
            var cards = document.querySelectorAll('.panel, .stat-card');
            for (var i = 0; i < cards.length; i++) {
                cards[i].setAttribute('data-card-i', i);
                cards[i].classList.add('gbl-card-ready');
                cardObs.observe(cards[i]);
            }
        }

        /* Run after DOM ready */
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                initRows();
                initCards();
            });
        } else {
            initRows();
            initCards();
        }
    }());
</script>
<script src="/sistema/assets/js/session-autologout.js"></script>