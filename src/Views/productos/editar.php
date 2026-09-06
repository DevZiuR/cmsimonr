<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');
if (!$es_admin) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../../config/conexion.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /sistema/src/Views/productos/index.php');
    exit;
}

$id_esc = mysqli_real_escape_string($conn, $id);
$res    = mysqli_query($conn, "SELECT * FROM productos WHERE id = '$id_esc' LIMIT 1");

if (!$res || mysqli_num_rows($res) === 0) {
    header('Location: /sistema/src/Views/productos/index.php?error=no_encontrado');
    exit;
}

$prod = mysqli_fetch_assoc($res);

$descripcion        = htmlspecialchars(isset($prod['descripcion'])        ? $prod['descripcion']        : '');
$imput_presupuestaria = htmlspecialchars(isset($prod['imput_presupuestaria']) ? $prod['imput_presupuestaria'] : '');

$error = isset($_GET['error']) ? $_GET['error'] : '';
$ok    = isset($_GET['ok'])    ? $_GET['ok']    : '';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Editar producto - Contraloria del Municipio Simon Rodriguez">
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Editar Producto – Contraloria MSR</title>
    <style>
    /* Premium typography: IBM Plex Sans headings, Inter body */
        h1, h2, h3, h4, h5, h6,
        .page-title, .section-title, .card-title, .panel-title,
        .hdr-title, .brand-title, .brand-sub,
        .title-cell, .title-cell h2,
        .sb-section-label, .sb-parent-label, .sb-user-name, .sb-user-badge {
            font-family: 'IBM Plex Sans', sans-serif !important;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            background: #f0f2f5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

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

        .site-header img { height: 52px; width: auto; display: block; }

        .site-header .brand { display: flex; flex-direction: column; gap: 2px; }

        .site-header .brand-title {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 0.7px;
            text-transform: uppercase;
        }

        .site-header .brand-sub { font-size: 12.5px; color: #bbdefb; letter-spacing: 0.2px; }

        .site-header .header-right {
            margin-left: auto;
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .layout { display: flex; flex: 1; }

        .main-content {
            flex: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .form-container {
            width: 100%;
            max-width: 700px;
            background: white;
            padding: 24px;
            border: 1px solid #ccc;
            margin-top: 10px;
            margin-bottom: 20px;
        }

        .inst-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            border-bottom: 2px solid #1565c0;
            padding-bottom: 12px;
        }

        .inst-header img { width: 75px; height: auto; display: block; }

        .inst-details { text-align: center; flex-grow: 1; padding: 0 15px; }

        .inst-details p { font-size: 10px; line-height: 1.5; color: #333; }

        .inst-details h2 {
            font-size: 13px;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #1565c0;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 14px;
        }

        .field label {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #444;
        }

        .field input {
            border: 1px solid #999;
            padding: 6px 8px;
            font-size: 12px;
            width: 100%;
            font-family: 'Inter', sans-serif;
            border-radius: 2px;
            outline: none;
            transition: border-color 0.15s;
        }

        .field input:focus {
            border-color: #1565c0;
            box-shadow: 0 0 3px rgba(21,101,192,0.2);
        }

        .alert {
            padding: 10px 12px;
            margin-bottom: 16px;
            font-size: 11px;
            font-weight: bold;
            border-radius: 2px;
        }

        .alert-danger  { background: #ffebee; color: #c62828; border: 1px solid #e53935; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #43a047; }

        .btns { margin-top: 15px; display: flex; gap: 10px; }

        .btn-guardar {
            background: #1565c0;
            color: white;
            border: none;
            padding: 8px 20px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            border-radius: 2px;
            transition: background 0.15s;
        }

        .btn-guardar:hover { background: #0d47a1; }

        .btn-cancelar {
            background: #888;
            color: white;
            text-decoration: none;
            padding: 8px 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            border-radius: 2px;
            transition: background 0.15s;
        }

        .btn-cancelar:hover { background: #666; }

        .site-footer {
            background: #080616;
            color: #90a4ae;
            text-align: center;
            padding: 10px 20px;
            font-size: 10px;
            border-top: 3px solid #1565c0;
            line-height: 1.8;
            margin-top: auto;
            width: 100%;
        }

        .site-footer strong { color: #e3f2fd; }
    </style>
</head>

<body>

    <!-- HEADER --><!-- LAYOUT: SIDEBAR + MAIN -->
    <div class="layout">

        <!-- SIDEBAR -->
        <?php $active = 'prod-lista'; require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="main-content">

            <div class="form-container">

                <!-- Institutional Header -->
                <div class="inst-header">
                    <div style="flex-shrink:0;">
                        <img src="/sistema/assets/img/logo.png" alt="Logo">
                    </div>
                    <div class="inst-details">
                        <p><strong>REPUBLICA BOLIVARIANA DE VENEZUELA</strong><br>
                        ESTADO ANZOATEGUI<br>
                        <strong>CONTRALORIA DEL MUNICIPIO SIMON RODRIGUEZ</strong></p>
                        <h2>Editar Producto</h2>
                    </div>
                    <div style="flex-shrink:0; width:75px;"></div>
                </div>

                <!-- Alerts -->
                <?php if ($ok === '1'): ?>
                    <div class="alert alert-success">Producto actualizado exitosamente.</div>
                <?php endif; ?>

                <?php if ($error === 'campos_requeridos'): ?>
                    <div class="alert alert-danger">Por favor, complete todos los campos obligatorios (*).</div>
                <?php endif; ?>

                <?php if ($error === 'error_actualizacion'): ?>
                    <div class="alert alert-danger">Ocurrio un error al actualizar el producto. Por favor, intentelo de nuevo.</div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" action="/sistema/src/Controllers/productos/actualizar.php" id="form-editar-producto">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">

                    <div class="field">
                        <label>Descripcion <span style="color:red;">*</span></label>
                        <input type="text"
                               name="descripcion"
                               id="campo-descripcion"
                               value="<?php echo $descripcion; ?>"
                               required
                               oninput="this.value = this.value.toUpperCase()"
                               placeholder="Ej: MATERIALES DE OFICINA...">
                    </div>

                    <div class="field">
                        <label>Imputacion Presupuestaria <span style="color:red;">*</span></label>
                        <input type="text"
                               name="imput_presupuestaria"
                               id="campo-imput"
                               value="<?php echo $imput_presupuestaria; ?>"
                               required
                               placeholder="Ej: 4.01.01.01.00">
                    </div>

                    <div class="btns">
                        <button type="submit" class="btn-guardar" id="btn-actualizar-producto">Actualizar Producto</button>
                        <a href="index.php" class="btn-cancelar" id="btn-cancelar-editar">Cancelar</a>
                    </div>
                </form>

            </div>

        </main>
    </div>

    <!-- FOOTER --><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?></body>

</html>