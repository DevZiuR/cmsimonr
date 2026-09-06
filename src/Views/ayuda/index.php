<?php
session_start();
if (!isset($_SESSION['usuario']) && !isset($_SESSION['usuario_id'])) {
    header('Location: /sistema/public/login.php');
    exit;
}

require_once __DIR__ . '/../../../config/conexion.php';
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');
$es_supervisor = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'supervisor');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Centro de Ayuda – Contraloría MSR</title>
    <style>
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
        .sb-user-badge {
            font-family: 'Geist', sans-serif !important;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .layout {
            display: flex;
            flex: 1;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            overflow-x: auto;
        }

        .container {
            max-width: 980px;
            margin: 0 auto;
        }

        /* ── Banner ── */
        .page-banner {
            background-image: url('/sistema/assets/img/banner.png');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 26px 30px;
            border-radius: 16px;
            margin-bottom: 22px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.35);
            display: flex;
            align-items: center;
            gap: 18px;
            position: relative;
            overflow: hidden;
        }

        .page-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(8, 12, 40, 0.78) 0%, rgba(15, 30, 80, 0.5) 100%);
        }

        .page-banner>* {
            position: relative;
            z-index: 1;
        }

        .banner-icon {
            width: 58px;
            height: 58px;
            min-width: 58px;
            border-radius: 14px;
            background: rgba(99, 102, 241, 0.25);
            border: 1px solid rgba(129, 140, 248, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #c7d2fe;
        }

        .page-title {
            font-size: 21px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }

        .page-sub {
            font-size: 12.5px;
            color: #cbd5e1;
            margin: 0;
        }

        /* ── Guías rápidas ── */
        .section-label {
            font-size: 11px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            margin: 4px 0 12px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
            margin-bottom: 28px;
        }

        .help-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: all 0.18s ease;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .help-card:hover {
            border-color: #818cf8;
            box-shadow: 0 10px 26px rgba(79, 70, 229, 0.14);
            transform: translateY(-2px);
        }

        .help-card .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #eef2ff;
            color: #161617ff;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e0e7ff;
        }

        .help-card h3 {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
        }

        .help-card p {
            font-size: 11.5px;
            line-height: 1.55;
            color: #64748b;
        }

        .help-card .card-link {
            margin-top: auto;
            font-size: 11px;
            font-weight: 700;
            color: #181819ff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .help-card .card-link:hover {
            text-decoration: underline;
        }

        /* ── FAQ Accordion ── */
        .faq-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .faq-item {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
            transition: border-color 0.18s ease;
        }

        .faq-item.open {
            border-color: #818cf8;
            box-shadow: 0 6px 18px rgba(79, 70, 229, 0.10);
        }

        .faq-q {
            width: 100%;
            border: none;
            background: none;
            padding: 15px 18px;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: background 0.15s ease;
        }

        .faq-q:hover {
            background: #f8fafc;
        }

        .faq-q .q-icon {
            width: 26px;
            height: 26px;
            min-width: 26px;
            border-radius: 8px;
            background: #eef2ff;
            color: #4f46e5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
        }

        .faq-q .q-text {
            flex: 1;
        }

        .faq-chevron {
            color: #94a3b8;
            transition: transform 0.28s ease;
            display: flex;
            align-items: center;
        }

        .faq-item.open .faq-chevron {
            transform: rotate(180deg);
            color: #4f46e5;
        }

        .faq-body {
            max-height: 0;
            overflow: hidden;
            padding: 0 18px;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }

        .faq-body-inner {
            font-size: 12px;
            line-height: 1.7;
            color: #475569;
            border-top: 1px dashed #e2e8f0;
            padding-top: 12px;
            margin-bottom: 16px;
        }

        .faq-body-inner ul {
            margin: 6px 0 0 18px;
        }

        .faq-body-inner li {
            margin-bottom: 4px;
        }

        .faq-body-inner strong {
            color: #1e293b;
        }

        .faq-body-inner .mono {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
            color: #4f46e5;
        }

        .faq-tag {
            display: inline-block;
            font-size: 9.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2px 8px;
            border-radius: 100px;
            margin-left: 8px;
            vertical-align: middle;
        }

        .tag-admin {
            background: #e0e7ff;
            color: #3730a3;
            border: 1px solid #a5b4fc;
        }

        .tag-sup {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fcd34d;
        }

        .site-footer {
            background: #070707;
            color: #90a4ae;
            text-align: center;
            padding: 12px 20px;
            font-size: 10.5px;
            border-top: 3px solid #4f46e5;
            margin-top: auto;
        }

        /* ── Estado insigne de ayuda (feedback al usuario) ── */
        .contact-box {
            margin-top: 24px;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 11.5px;
            color: #151516ff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @media print {
            body {
                background: #fff !important;
            }

            .sidebar,
            .site-footer,
            .help-fab {
                display: none !important;
            }

            .layout {
                display: block !important;
            }

            .main-content {
                padding: 0 !important;
            }
        }
    </style>
</head>

<body>

    <div class="layout">
        <?php $active = 'ayuda';
        require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="container">

                <div class="page-banner">
                    <div class="banner-icon">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                    </div>
                    <div>
                        <h1 class="page-title">Centro de Ayuda</h1>
                        <p class="page-sub">Guías rápidas y preguntas frecuentes para usar el Sistema de Gestión Interna
                            de la Contraloría.</p>
                    </div>
                </div>

                <!-- ── Guías rápidas ── -->
                <div class="section-label">Guías rápidas</div>
                <div class="cards-grid">

                    <div class="help-card">
                        <div class="card-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                            </svg>
                        </div>
                        <h3>Crear una Orden de Compra</h3>
                        <p>Vaya a Órdenes de Compra → Nueva OC. Elija el proveedor, agregue los renglones y el sistema
                            calcula la base imponible e IVA (16%) automáticamente.</p>
                        <a href="../../Views/ordenes_compra/nueva_oc.php" class="card-link">Ir a Nueva OC
                            <span>→</span></a>
                    </div>

                    <div class="help-card">
                        <div class="card-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z">
                                </path>
                            </svg>
                        </div>
                        <h3>Vincular una Orden de Pago</h3>
                        <p>En Nueva OP seleccione la OC u OS asociada: el beneficiario y los montos se arrastran
                            automáticamente para evitar discrepancias.</p>
                        <a href="../../Views/ordenes_pago/nueva_op.php" class="card-link">Ir a Nueva OP
                            <span>→</span></a>
                    </div>

                    <div class="help-card">
                        <div class="card-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2">
                                </path>
                                <rect x="6" y="14" width="12" height="8"></rect>
                            </svg>
                        </div>
                        <h3>Imprimir y Exportar</h3>
                        <p>En el detalle de cualquier orden use el botón Imprimir (PDF). En los listados y Reportes
                            puede exportar a Excel con un clic.</p>
                        <a href="../../Views/reportes/index.php" class="card-link">Ir a Reportes <span>→</span></a>
                    </div>

                    <div class="help-card">
                        <div class="card-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                        </div>
                        <h3>Entender los Estados</h3>
                        <p>Pendiente → Aprobada → Pagada. También puede Rechazada o Anulada. El verde indica aprobado,
                            el ámbar pendiente y el rojo rechazo o anulación.</p>
                        <a href="#preguntas" class="card-link">Ver preguntas frecuentes <span>↓</span></a>
                    </div>

                    <div class="help-card">
                        <div class="card-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="8.5" cy="7" r="4"></circle>
                                <line x1="20" y1="8" x2="20" y2="14"></line>
                                <line x1="23" y1="11" x2="17" y2="11"></line>
                            </svg>
                        </div>
                        <h3>Registrar un Proveedor</h3>
                        <p>Gestión → Proveedores → Nuevo Proveedor. Registre RIF, razón social, dirección y teléfono
                            antes de crear las órdenes.</p>
                        <a href="../../Views/proveedores/nuevo.php" class="card-link">Ir a Nuevo Proveedor
                            <span>→</span></a>
                    </div>

                    <div class="help-card">
                        <div class="card-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                <path d="M9 12l2 2 4-4"></path>
                            </svg>
                        </div>
                        <h3>¿Quién Aprueba?</h3>
                        <p>El Administrador gestiona todos los estados. Los Supervisores solo pueden aprobar o rechazar.
                            Los Visualizadores únicamente consultan.</p>
                        <a href="#quien-aprueba" class="card-link">Ver detalle <span>↓</span></a>
                    </div>

                </div>

                <!-- ── Preguntas frecuentes ── -->
                <div class="section-label" id="preguntas">Preguntas frecuentes</div>
                <div class="faq-list">

                    <div class="faq-item open">
                        <button type="button" class="faq-q" onclick="toggleFaq(this)">
                            <span class="q-icon">1</span>
                            <span class="q-text">¿Cómo creo una Orden de Compra (OC)?</span>
                            <span class="faq-chevron">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </button>
                        <div class="faq-body">
                            <div class="faq-body-inner">
                                <ul>
                                    <li>Vaya a <span class="mono">Órdenes de Compra → Nueva OC</span>.</li>
                                    <li>Seleccione el proveedor y la fecha, luego agregue los renglones con cantidad y
                                        precio unitario.</li>
                                    <li>El sistema calcula automáticamente la base imponible, el IVA (16%) y el total
                                        general.</li>
                                    <li>Guarde la orden. Quedará en estado <strong>Pendiente</strong> hasta que un
                                        supervisor la apruebe o rechace.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-q" onclick="toggleFaq(this)">
                            <span class="q-icon">2</span>
                            <span class="q-text">¿Cómo vinculo una Orden de Pago (OP) a una compra o servicio?</span>
                            <span class="faq-chevron">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </button>
                        <div class="faq-body">
                            <div class="faq-body-inner">
                                <ul>
                                    <li>Abra <span class="mono">Órdenes de Pago → Nueva OP</span>.</li>
                                    <li>En el campo de la orden asociada elija la OC u OS correspondiente: el
                                        beneficiario, el concepto y los montos se cargan automáticamente.</li>
                                    <li>Complete los montos de retención si aplica; el sistema calcula el <strong>monto
                                            neto a pagar</strong>.</li>
                                    <li>Existen variantes específicas: <strong>BANAVIH</strong> e <strong>IVSS y
                                            PF</strong> en el mismo módulo.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-q" onclick="toggleFaq(this)">
                            <span class="q-icon">3</span>
                            <span class="q-text">¿Qué significa cada estado de una orden?</span>
                            <span class="faq-chevron">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </button>
                        <div class="faq-body">
                            <div class="faq-body-inner">
                                <ul>
                                    <li><strong>Pendiente</strong> (ámbar): creada y a la espera de aprobación.</li>
                                    <li><strong>Aprobada</strong> (verde): autorizada para ejecutar o pagar.</li>
                                    <li><strong>Pagada / Pagado</strong> (verde): la OP fue pagada o el compromiso
                                        saldado.</li>
                                    <li><strong>Rechazada</strong> (rojo): no fue aprobada; revise los motivos y
                                        corríjala.</li>
                                    <li><strong>Anulada</strong> (rojo): eliminada del flujo sin efecto presupuestario.
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item" id="quien-aprueba">
                        <button type="button" class="faq-q" onclick="toggleFaq(this)">
                            <span class="q-icon">4</span>
                            <span class="q-text">¿Quién puede aprobar o rechazar una orden?</span>
                            <span class="faq-tag tag-admin">Admin</span><span class="faq-tag tag-sup">Supervisor</span>
                            <span class="faq-chevron">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </button>
                        <div class="faq-body">
                            <div class="faq-body-inner">
                                <ul>
                                    <li><strong>Administrador:</strong> puede cambiar cualquier estado (pendiente,
                                        aprobada, rechazada, anulada, pagada).</li>
                                    <li><strong>Supervisor:</strong> solo puede <strong>aprobar</strong> o
                                        <strong>rechazar</strong> órdenes.
                                    </li>
                                    <li><strong>Visualizador:</strong> consulta e imprime, sin modificar estados.</li>
                                </ul>
                                <p style="margin-top:8px;">Los cambios de estado quedan registrados (usuario y fecha)
                                    para el seguimiento interno.</p>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-q" onclick="toggleFaq(this)">
                            <span class="q-icon">5</span>
                            <span class="q-text">¿Cómo imprimo una orden o exporto un reporte?</span>
                            <span class="faq-chevron">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </button>
                        <div class="faq-body">
                            <div class="faq-body-inner">
                                <ul>
                                    <li><strong>PDF / Imprimir:</strong> abra la orden (botón <strong>Ver</strong>) y
                                        use el botón Imprimir; la hoja sale con membrete institucional.</li>
                                    <li><strong>Excel:</strong> en los listados de OC, OP, OS y en el módulo de Reportes
                                        pulse el botón verde <span class="mono">Exportar Excel</span> para descargar un
                                        CSV compatible con Excel.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-q" onclick="toggleFaq(this)">
                            <span class="q-icon">6</span>
                            <span class="q-text">¿Cómo agrego un proveedor?</span>
                            <span class="faq-tag tag-admin">Admin</span>
                            <span class="faq-chevron">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </button>
                        <div class="faq-body">
                            <div class="faq-body-inner">
                                <ul>
                                    <li>Vaya a <span class="mono">Gestión → Proveedores → Nuevo Proveedor</span>.</li>
                                    <li>Complete RIF, razón social, dirección y teléfono de contacto.</li>
                                    <li>Guarde. El proveedor quedará disponible en todas las órdenes.</li>
                                </ul>
                                <p style="margin-top:8px;">Registrar primero los proveedores evita tener que crearlos a
                                    mitad de una orden.</p>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-q" onclick="toggleFaq(this)">
                            <span class="q-icon">7</span>
                            <span class="q-text">¿Qué diferencia hay entre una OC normal y una de farmacia?</span>
                            <span class="faq-chevron">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </button>
                        <div class="faq-body">
                            <div class="faq-body-inner">
                                <p>La <strong>OC Farmacia</strong> se usa para la compra de insumos farmacéuticos. Su
                                    principal diferencia es que está <strong>exenta de IVA</strong>, por lo que el total
                                    general coincide con la base imponible. El resto del flujo (aprobación, pago,
                                    papelera) es idéntico al de una OC normal.</p>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-q" onclick="toggleFaq(this)">
                            <span class="q-icon">8</span>
                            <span class="q-text">¿Cómo restauro una orden eliminada o de la papelera?</span>
                            <span class="faq-chevron">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </button>
                        <div class="faq-body">
                            <div class="faq-body-inner">
                                <ul>
                                    <li>Las órdenes eliminadas van a la <strong>papelera</strong> (pestaña en cada
                                        listado).</li>
                                    <li>Use el botón <strong>Restaurar</strong> para recuperarla; vuelve a su estado
                                        anterior.</li>
                                    <li>Las órdenes en papelera se eliminan definitivamente de forma automática a los
                                        <strong>14 días</strong>.
                                    </li>
                                    <li>También puede eliminarlas definitivamente de forma manual si lo requiere.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-q" onclick="toggleFaq(this)">
                            <span class="q-icon">9</span>
                            <span class="q-text">¿Cómo funciona la Ejecución Presupuestaria?</span>
                            <span class="faq-chevron">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </button>
                        <div class="faq-body">
                            <div class="faq-body-inner">
                                <ul>
                                    <li><span class="mono">SISTEMA → Ejec. Presupuestaria → Listado General</span>: vea
                                        todas las partidas con su asignación, comprometido, causado y pagado.</li>
                                    <li><span class="mono">Matriz del Mes</span>: vista resumida por mes.</li>
                                    <li><span class="mono">Nueva Partida</span> (solo administradores): registra la
                                        asignación de una partida presupuestaria.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-q" onclick="toggleFaq(this)">
                            <span class="q-icon">10</span>
                            <span class="q-text">¿Cómo cambio mi contraseña o datos de usuario?</span>
                            <span class="faq-chevron">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </button>
                        <div class="faq-body">
                            <div class="faq-body-inner">
                                <ul>
                                    <li>Entre en <span class="mono">SISTEMA → Mi Perfil</span>.</li>
                                    <li>Actualice su nombre o contraseña y pulse <strong>Guardar</strong>.</li>
                                    <li>Al cambiar la contraseña deberá volver a iniciar sesión con la nueva clave.</li>
                                </ul>
                                <p style="margin-top:8px;">La administración de usuarios (crear, activar, cambiar rol)
                                    está en <span class="mono">SISTEMA → Usuarios</span>, disponible solo para
                                    administradores.</p>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-q" onclick="toggleFaq(this)">
                            <span class="q-icon">11</span>
                            <span class="q-text">¿Qué hago si olvidé mi contraseña?</span>
                            <span class="faq-chevron">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </button>
                        <div class="faq-body">
                            <div class="faq-body-inner">
                                <p>El sistema no dispone de recuperación automática de contraseña por seguridad.
                                    Contacte al <strong>administrador del sistema</strong>, quien puede restablecer su
                                    clave desde <span class="mono">SISTEMA → Usuarios</span>.</p>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-q" onclick="toggleFaq(this)">
                            <span class="q-icon">12</span>
                            <span class="q-text">¿Qué son las retenciones en una Orden de Pago?</span>
                            <span class="faq-chevron">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </button>
                        <div class="faq-body">
                            <div class="faq-body-inner">
                                <p>En una OP se distinguen tres montos: el <strong>monto bruto</strong> (valor total
                                    facturado), el <strong>monto de retención</strong> (descuentos aplicados por ley,
                                    según el tipo de orden BANAVIH o IVSS/PF) y el <strong>monto neto a pagar</strong>,
                                    que es la diferencia y corresponde al cheque o transferencia final.</p>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="contact-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <span>¿No encontró la respuesta? Comuníquese con el administrador del sistema para soporte técnico o
                        dudas sobre el uso de los módulos.</span>
                </div>

            </div>
        </main>
    </div><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?>
    <script>
        function toggleFaq(btn) {
            var item = btn.parentElement;
            var wasOpen = item.classList.contains('open');
            var items = document.querySelectorAll('.faq-item');
            for (var i = 0; i < items.length; i++) {
                items[i].classList.remove('open');
                var body = items[i].querySelector('.faq-body');
                if (body) {
                    body.style.maxHeight = '0px';
                    body.style.padding = '0 18px';
                }
            }
            if (!wasOpen) {
                item.classList.add('open');
                var b = item.querySelector('.faq-body');
                if (b) {
                    b.style.maxHeight = b.scrollHeight + 'px';
                    b.style.padding = '4px 18px 6px 18px';
                }
            }
        }
    </script>

</body>

</html>