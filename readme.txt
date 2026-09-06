================================================================================
   SISTEMA DE CONTROL Y GESTIÓN DE CONTRALORÍA MUNICIPAL (SIMÓN RODRÍGUEZ)
================================================================================
Documentación Oficial del Sistema | Versión 2.0
Fecha de Actualización: Septiembre 2026
Ubicación: Municipio Simón Rodríguez, El Tigre, Estado Anzoátegui, Venezuela.

--------------------------------------------------------------------------------
1. DESCRIPCIÓN GENERAL DEL SISTEMA
--------------------------------------------------------------------------------
El Sistema de Control y Gestión de Contraloría es una plataforma web integral
diseñada para la administración, emisión, seguimiento y auditoría de los procesos
administrativos y financieros de la Contraloría Municipal de Simón Rodríguez.

El sistema centraliza y automatiza el flujo completo de:
- Órdenes de Compra (OC): Regulares y de Productos Farmacéuticos (Exentas de IVA).
- Órdenes de Servicio (OS): Con generación directa a Órdenes de Pago.
- Órdenes de Pago (OP): Generales, Banavih e IVSS.
- Ejecución Presupuestaria: Matriz mensual y consolidada por partidas.
- Directorio de Proveedores: Con RIF validado y registro rápido interactivo.
- Catálogo de Partidas Presupuestarias y Productos/Insumos vinculados.
- Reportes Generales y Estadísticas por mes y estatus unificado (PENDIENTE / PAGADO).
- Trazabilidad y Auditoría Continua de operaciones del sistema.
- Control de Acceso y Gestión de Usuarios según roles jerárquicos.

--------------------------------------------------------------------------------
2. ARQUITECTURA Y ESTRUCTURA DE DIRECTORIOS
--------------------------------------------------------------------------------
El proyecto sigue un patrón MVC (Modelo-Vista-Controlador) ligero, limpio y
modular, sin dependencias externas pesadas:

sistema/
│
├── config/
│   └── conexion.php               # Configuración de base de datos (MySQLi) y zona horaria
│
├── public/
│   ├── index.php                  # Dashboard principal y métricas institucionales
│   ├── login.php                  # Pantalla de autenticación y acceso seguro
│   ├── logout.php                 # Cierre de sesión y limpieza de estado
│   └── recuperar.php              # Recuperación de credenciales
│
├── src/
│   ├── Controllers/               # Controladores de lógica de negocio y acciones
│   │   ├── auth/                  # Inicio de sesión, perfil y reseteo de claves
│   │   ├── cambiar_status.php     # Controlador unificado de estatus (Pendiente / Pagado)
│   │   ├── ordenes_compra/        # Guardado, anulación, restauración y papelera OC
│   │   ├── ordenes_servicio/      # Guardado, anulación, restauración y papelera OS
│   │   ├── ordenes_pago/          # Guardado, cálculo de retenciones y papelera OP
│   │   ├── ejecucion/             # Registro y actualización de ejecución presupuestaria
│   │   ├── partidas/              # Gestión de partidas presupuestarias
│   │   ├── productos/             # Búsqueda AJAX predictiva y catálogo de productos
│   │   └── proveedores/           # Creación rápida AJAX y edición de proveedores
│   │
│   ├── Models/                    # Modelos de datos y registros auxiliares
│   │   └── audit_log.php          # Motor de registro de auditoría (log_activity)
│   │
│   └── Views/                     # Vistas organizadas por módulos
│       ├── ordenes_compra/        # Listado (index.php), nueva OC regular, farmacia y ver
│       ├── ordenes_servicio/      # Listado (index.php), nueva OS y ver detalle
│       ├── ordenes_pago/          # Listado (index.php), nueva OP, Banavih e IVSS
│       ├── ejecucion/             # Listado de ejecución, matriz y formato de impresión
│       ├── proveedores/           # Catálogo, registro y edición de proveedores
│       ├── partidas/              # Catálogo maestro de partidas presupuestarias
│       ├── productos/             # Listado y mantenimiento de productos e insumos
│       ├── reportes/              # Reportes por rango de fechas, estatus y tipo de orden
│       ├── auditoria/             # Historial cronológico de actividades y eventos
│       ├── perfil/                # Perfil personal y Gestión de Usuarios (usuarios.php)
│       └── ayuda/                 # Manuales, glosario y preguntas frecuentes
│
├── includes/
│   ├── sidebar.php                # Menú lateral colapsable institucional con persistencia
│   └── footer.php                 # Pie de página institucional y copyright
│
├── assets/
│   ├── css/                       # Hojas de estilo generales y de componentes
│   ├── fonts/                     # Tipografías locales (IBM Plex Sans, Inter, Geist)
│   └── img/                       # Logotipos, membretes oficiales y avatares
│
├── backups/                       # Respaldos y copias de seguridad de la base de datos
├── scripts/                       # Scripts SQL y migraciones de mantenimiento
└── README.txt                     # Este manual de documentación y configuración

--------------------------------------------------------------------------------
3. STACK TECNOLÓGICO
--------------------------------------------------------------------------------
- Lenguaje Backend: PHP (Totalmente compatible desde PHP 5.4 hasta PHP 8.x).
- Gestor de Base de Datos: MySQL 5.7+ / MariaDB 10.x (Motor InnoDB, UTF-8).
- Servidor Web: Apache 2.4 (entorno recomendado: XAMPP para Windows o LAMP en Linux).
- Frontend: HTML5 Semántico + CSS3 Vanilla (variables CSS, flexbox, grid, glassmorphism).
- Tipografía: IBM Plex Sans, Inter y Geist alojadas localmente (sin conexión a CDN externa).
- Scripting Cliente: JavaScript nativo (Vanilla ES5/ES6) para autocompletado en vivo,
  cálculo automático de subtotales, IVA, SAT, totales en letras y modales.
- Formatos de Salida e Impresión:
  * CSS `@media print` calibrado para hojas tipo Carta y Oficio con membrete institucional.
  * Exportación de tablas a Excel nativo (.xls) y formato CSV.

--------------------------------------------------------------------------------
4. REQUISITOS E INSTALACIÓN (XAMPP EN WINDOWS)
--------------------------------------------------------------------------------
Paso 1: Descargar e instalar XAMPP
   - Asegurarse de tener habilitados los módulos de Apache y MySQL en el Panel de XAMPP.

Paso 2: Ubicar el proyecto
   - Clonar o descomprimir la carpeta del sistema en la ruta:
     C:\xampp\htdocs\sistema

Paso 3: Configurar la Base de Datos
   - Abrir phpMyAdmin en el navegador: http://localhost/phpmyadmin/
   - Crear una base de datos llamada: contraloria_db
   - Cotejamiento recomendado: utf8mb4_unicode_ci
   - Importar el respaldo SQL ubicado en la carpeta: /backups/

Paso 4: Verificar la Conexión
   - El archivo de configuración principal se encuentra en:
     c:\xampp\htdocs\sistema\config\conexion.php
   - Parámetros por defecto:
     * Servidor: localhost
     * Usuario: root
     * Contraseña: (vacía)
     * Base de datos: contraloria_db
     * Zona horaria: America/Caracas (-04:00)

Paso 5: Acceso al Sistema
   - Iniciar los servicios Apache y MySQL en XAMPP.
   - Ingresar a través del navegador web:
     http://localhost/sistema/
     o directamente:
     http://localhost/sistema/public/login.php

--------------------------------------------------------------------------------
5. ROLES DE USUARIO Y PERMISOS
--------------------------------------------------------------------------------
El sistema implementa control de acceso basado en tres niveles jerárquicos:

1. Administrador ('admin'):
   - Acceso total a todos los módulos.
   - Creación, modificación, anulación y eliminación de órdenes (OC, OS, OP).
   - Cambio de estatus entre PENDIENTE y PAGADO.
   - Administración completa de usuarios (crear, editar datos, asignar roles,
     cambiar o resetear contraseñas y eliminar usuarios).
   - Acceso al módulo de auditoría del sistema.

2. Supervisor ('supervisor'):
   - Creación y edición de órdenes de compra, servicio y pago.
   - Cambio de estatus de órdenes (Pendiente / Pagado).
   - Consulta de reportes y catálogo de proveedores y partidas.
   - Sin acceso a administración de usuarios ni vaciado de papeleras definitivas.

3. Visualizador ('visualizador'):
   - Acceso de solo lectura para consulta y descarga/impresión de órdenes y reportes.
   - No puede crear órdenes, modificar montos ni alterar estatus.

--------------------------------------------------------------------------------
6. ESTATUS DE ÓRDENES Y SELECTOR MENSUAL
--------------------------------------------------------------------------------
- Estatus Unificados:
  Todas las órdenes (OC, OS, OP) manejan estrictamente dos estados en todo el sistema:
  * PENDIENTE: Orden en proceso administrativo o pendiente por cancelación.
  * PAGADO: Orden liquidada y cancelada satisfactoriamente.
  (El cambio a Pagado en una Orden de Pago actualiza automáticamente la orden de
   compra o de servicio vinculada).

- Selector de Mes:
  Todas las pantallas de listado cuentan con un selector `<input type="month">`
  junto al panel de "Estadísticas del mes: [Nombre Mes] [Año]", permitiendo auditar
  y exportar de forma precisa el histórico de cualquier mes seleccionado.

--------------------------------------------------------------------------------
7. SEGURIDAD Y BUENAS PRÁCTICAS
--------------------------------------------------------------------------------
- Contraseñas protegidas mediante algoritmos de hash y reseteo criptográfico.
- Consultas parametrizadas con Prepared Statements (mysqli_prepare / bind_param)
  para prevenir ataques de inyección SQL.
- Sanitización y escape de salidas mediante htmlspecialchars() contra XSS.
- Verificación de sesión activa y validación estricta de permisos por rol en cada vista.
- Módulo de auditoría (audit_log) que registra la dirección IP, usuario, acción y detalle.

================================================================================
Desarrollado para la Contraloría Municipal de Simón Rodríguez.
================================================================================