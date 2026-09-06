<?php
/**
 * src/Models/catalogo_partidas.php
 * Catálogo maestro de partidas presupuestarias para la Contraloría Municipal.
 * Prefijo siempre: 01-08-00-00-51
 */

function get_catalogo_estatico() {
    static $catalogo = null;
    if ($catalogo !== null) {
        return $catalogo;
    }

    $catalogo = array(
        /* ── 401: GASTOS DE PERSONAL ── */
        array('cod' => '01-08-00-00-51-401-01-01-00', 'denom' => 'Sueldos básicos personal fijo a tiempo completo', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-01-03-00', 'denom' => 'Suplencias al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-01-10-00', 'denom' => 'Salarios a Obreros en Puestos Permanente a tiempo completo', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-01-18-01', 'denom' => 'Remuneraciones al personal contratado a tiempo determinado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-01-19-00', 'denom' => 'Retribuciones por becas-salarios, bolsas de trabajo, pasantías y similares', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-01-35-00', 'denom' => 'Sueldo básico de los altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-01-36-00', 'denom' => 'Sueldo básico del personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-01-99-00', 'denom' => 'Otras Retribuciones', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-03-04-00', 'denom' => 'Primas por hijos e hijas al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-03-08-00', 'denom' => 'Primas de profesionalización al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-03-09-00', 'denom' => 'Primas por antigüedad al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-03-21-00', 'denom' => 'Primas por antigüedad al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-03-43-00', 'denom' => 'Primas de profesionalización de los altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-03-44-00', 'denom' => 'Primas por antigüedad de los altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-03-45-00', 'denom' => 'Primas por hijos e hijas al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-03-48-00', 'denom' => 'Primas de profesionalización al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-03-49-00', 'denom' => 'Primas de antigüedad al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-04-00', 'denom' => 'Complemento al personal empleado por gastos de transporte', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-08-00', 'denom' => 'Bono compensatorio de alimentación al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-09-00', 'denom' => 'Bono compensatorio de transporte al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-18-00', 'denom' => 'Bono compensatorio de alimentación al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-19-00', 'denom' => 'Bono compensatorio de transporte al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-43-00', 'denom' => 'Complemento a altos funcionarios y altas funcionarias del sector público y de elección popular por gastos de representación', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-46-00', 'denom' => 'Bono compensatorio de alimentación a altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-47-00', 'denom' => 'Bono compensatorio de transporte a altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-48-00', 'denom' => 'Complemento al personal de alto nivel y de dirección por gastos de representación', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-51-00', 'denom' => 'Bono compensatorio de alimentación al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-52-00', 'denom' => 'Bono compensatorio de transporte al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-94-00', 'denom' => 'Otros complementos a altos funcionarios y altas funcionarias del sector público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-95-00', 'denom' => 'Otros complementos al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-96-00', 'denom' => 'Otros complementos al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-04-97-00', 'denom' => 'Otros complementos al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-05-01-00', 'denom' => 'Aguinaldos al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-05-03-00', 'denom' => 'Bono vacacional al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-05-04-00', 'denom' => 'Aguinaldos al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-05-06-00', 'denom' => 'Bono vacacional al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-05-13-00', 'denom' => 'Aguinaldos a altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-05-15-00', 'denom' => 'Bono vacacional a altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-05-16-00', 'denom' => 'Aguinaldos al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-05-18-00', 'denom' => 'Bono vacacional al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-01-00', 'denom' => 'Aporte patronal al Instituto Venezolano de los Seguros Sociales (I.V.S.S.) al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-03-00', 'denom' => 'Aporte patronal al Fondo de Jubilaciones al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-04-00', 'denom' => 'Aporte patronal al Fondo Contributivo del Régimen Prestacional de Empleo al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-05-00', 'denom' => 'Aporte patronal al Fondo de Ahorro Obligatorio para la Vivienda al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-10-00', 'denom' => 'Aporte patronal al Instituto Venezolano de los Seguros Sociales (I.V.S.S.) al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-11-00', 'denom' => 'Aporte patronal al Fondo de Jubilaciones al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-12-00', 'denom' => 'Aporte patronal al Fondo Contributivo del Régimen Prestacional de Empleo al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-13-00', 'denom' => 'Aporte patronal al Fondo de Ahorro Obligatorio para la Vivienda al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-31-00', 'denom' => 'Aporte patronal al Instituto Venezolano de los Seguros Sociales (IVSS) por altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-33-00', 'denom' => 'Aporte Patronal al Fondo de Jubilaciones a altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-34-00', 'denom' => 'Aporte patronal al Fondo de Ahorro Obligatorio para la Vivienda por altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-35-00', 'denom' => 'Aportes Patronales al Fondo Contributivo del Régimen Prestacional a altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-39-00', 'denom' => 'Aporte patronal al Instituto Venezolano de los Seguros Sociales (IVSS) al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-41-00', 'denom' => 'Aporte Patronal al Fondo de Jubilaciones al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-42-00', 'denom' => 'Aporte patronal al Fondo de Ahorro Obligatorio para la Vivienda al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-43-00', 'denom' => 'Aporte patronal al Fondo Contributivo del Régimen Prestacional de Empleo al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-93-00', 'denom' => 'Otros aportes patronales por altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-94-00', 'denom' => 'Otros aportes patronales al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-96-00', 'denom' => 'Otros aportes patronales al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-06-97-00', 'denom' => 'Otros aportes patronales al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-01-00', 'denom' => 'Capacitación y adiestramiento al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-02-00', 'denom' => 'Becas al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-05-00', 'denom' => 'Ayudas por defunción al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-06-00', 'denom' => 'Ayudas para medicinas, gastos médicos, odontológicos y de hospitalización al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-07-00', 'denom' => 'Aporte patronal a cajas de ahorro al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-09-00', 'denom' => 'Ayudas al personal empleado para adquisición de uniformes y útiles escolares de sus hijos e hijas', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-10-00', 'denom' => 'Dotacion de Uniforme a Empleados', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-11-00', 'denom' => 'Aporte Patronal Para Gastos de Guarderias para Hijos de Empleados', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-12-00', 'denom' => 'Aportes para la adquisición de juguetes para los hijos e hijas del personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-21-00', 'denom' => 'Ayudas por defunción al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-22-00', 'denom' => 'Ayudas para medicinas, gastos médicos, odontológicos y de hospitalización al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-23-00', 'denom' => 'Aporte patronal a cajas de ahorro al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-26-00', 'denom' => 'Dotación de uniformes al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-52-00', 'denom' => 'Capacitación y adiestramiento a altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-55-00', 'denom' => 'Ayudas por defunción a altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-56-00', 'denom' => 'Ayudas para medicinas, gastos médicos, odontológicos y de hospitalización a altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-57-00', 'denom' => 'Aporte patronal a cajas de ahorro por altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-63-00', 'denom' => 'Capacitación y adiestramiento al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-66-00', 'denom' => 'Ayudas por defunción al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-67-00', 'denom' => 'Ayudas para medicinas, gastos médicos, odontológicos y de hospitalización al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-68-00', 'denom' => 'Aporte patronal a cajas de ahorro al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-70-00', 'denom' => 'Ayudas al personal de alto nivel y de dirección para adquisición de uniformes y útiles escolares de sus hijos e hijas', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-71-00', 'denom' => 'Aportes para la adquisición de juguetes para los hijos e hijas del personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-72-00', 'denom' => 'Becas al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-73-00', 'denom' => 'Aporte Patronal Para Gastos de Guarderias y preescolar para hijos e hijas del personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-94-00', 'denom' => 'Otras subvenciones a altos funcionarios y altas funcionarias del poder publico y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-95-00', 'denom' => 'Otras subvenciones al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-96-00', 'denom' => 'Otras subvenciones al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-07-97-00', 'denom' => 'Otras subvenciones al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-08-01-00', 'denom' => 'Prestaciones sociales e indemnizaciones al personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-08-02-00', 'denom' => 'Prestaciones sociales e indemnizaciones al personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-08-06-00', 'denom' => 'Prestaciones Sociales e Indemnizaciones a altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-08-07-00', 'denom' => 'Prestaciones Sociales e Indemnizaciones al personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-09-01-00', 'denom' => 'Capacitación y adiestramiento realizado por personal del organismo', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-94-01-00', 'denom' => 'Otros gastos de los altos funcionarios y altas funcionarias del poder público y de elección popular', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-95-01-00', 'denom' => 'Otros gastos del personal de alto nivel y de dirección', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-96-01-00', 'denom' => 'Otros gastos del personal empleado', 'grupo' => 'GASTOS DE PERSONAL'),
        array('cod' => '01-08-00-00-51-401-97-01-00', 'denom' => 'Otros gastos del personal obrero', 'grupo' => 'GASTOS DE PERSONAL'),

        /* ── 402: MATERIALES Y SUMINISTROS ── */
        array('cod' => '01-08-00-00-51-402-01-01-00', 'denom' => 'Alimentos y Bebidas para Personas', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-03-02-00', 'denom' => 'Prendas de vestir', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-04-03-00', 'denom' => 'Cauchos y Tripas para Vehiculos', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-05-01-00', 'denom' => 'Pulpa de Madera, Papel y Cartón', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-05-02-00', 'denom' => 'Envases y Cajas de Papel y Carton', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-05-03-00', 'denom' => 'Productos de papel y carton para Oficina', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-05-04-00', 'denom' => 'Libros, Revistas y Periódicos', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-06-03-00', 'denom' => 'Tintas Pinturas y Colorantes', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-06-04-00', 'denom' => 'Productos Farmaceuticos y Medicamentos', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-06-06-00', 'denom' => 'Combustibles y Lubricantes', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-06-08-00', 'denom' => 'Productos Plásticos', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-07-01-00', 'denom' => 'Producto de Barro,Loza y Porcelana', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-07-03-00', 'denom' => 'Producto de Arcilla para Construcción', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-07-04-00', 'denom' => 'Cemento, Cal y Yeso', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-08-03-00', 'denom' => 'Herramientas menores, cuchilleria y artículos generales de ferretería', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-08-04-00', 'denom' => 'Productos Metálicos Estructurales', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-08-09-00', 'denom' => 'Repuestos y Accesorios para Equipo de Transporte', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-10-01-00', 'denom' => 'Articulos de Deporte, recreacion y juguetes', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-10-02-00', 'denom' => 'Materiales y Utiles de Limpieza y Aseo', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-10-05-00', 'denom' => 'Utiles de Escritorios, Oficina y Materiales de Instrucción.', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-10-06-00', 'denom' => 'Condecoraciones, Ofrendas Y Similares', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-10-07-00', 'denom' => 'Productos de Seguridad en el Trabajo', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-10-08-00', 'denom' => 'Materiales Para Equipo de Computación', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-10-11-00', 'denom' => 'Materiales Eléctricos', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-10-12-00', 'denom' => 'Materiales Para Instalaciones Sanitarias', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-10-99-00', 'denom' => 'Otros Productos y Útiles Diversos', 'grupo' => 'MATERIALES Y SUMINISTROS'),
        array('cod' => '01-08-00-00-51-402-99-01-00', 'denom' => 'Otros materiales y suministros', 'grupo' => 'MATERIALES Y SUMINISTROS'),

        /* ── 403: SERVICIOS NO PERSONALES ── */
        array('cod' => '01-08-00-00-51-403-01-01-00', 'denom' => 'Alquileres de Edificio y Locales', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-04-01-00', 'denom' => 'Electricidad', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-04-03-00', 'denom' => 'Agua', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-04-04-01', 'denom' => 'Servicios de telefonia prestados por organismos publicos', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-04-05-00', 'denom' => 'Servicios de comunicaciones', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-04-06-00', 'denom' => 'Servicio de Aseo Urbano y Domiciliario', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-04-07-00', 'denom' => 'Servicio de Condominio', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-06-01-00', 'denom' => 'Fletes y embalajes', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-07-01-00', 'denom' => 'Publicidad y Propaganda', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-07-02-00', 'denom' => 'Imprenta y Reproducción', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-07-03-00', 'denom' => 'Relaciones Sociales', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-07-04-00', 'denom' => 'Avisos', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-08-01-00', 'denom' => 'Primas y Gastos de Seguros', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-08-02-00', 'denom' => 'Comisiones y Gastos Bancarios', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-09-01-00', 'denom' => 'Viaticos y Pasajes Dentro del País', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-10-03-00', 'denom' => 'Servicios de Procesamiento de Datos', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-10-05-00', 'denom' => 'Servicios medicos, odontologicos y otros servicios de sanidad', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-10-07-00', 'denom' => 'Servicios de Capacitacion y Adiestramiento', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-10-11-00', 'denom' => 'Servicios para la elaboración y suministro de comida', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-10-99-00', 'denom' => 'Otros Servicios Profesionales y Tecnicos', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-11-02-00', 'denom' => 'Conservación y Reparaciones Menores de Equipos de Trasporte, Tracción y Elevación', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-11-07-00', 'denom' => 'Conservación y Reparaciones Menores de Maquina, Muebles y Demás Equipos de Oficina y Alojamiento', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-16-01-00', 'denom' => 'Servicios de Diversion, Esparcimiento y Culturales', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-18-01-00', 'denom' => 'Impuesto al Valor Agregado', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-18-99-00', 'denom' => 'Otros Impuestos Indirectos', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-19-01-00', 'denom' => 'Comisiones por servicios para cumplir con los beneficios sociales', 'grupo' => 'SERVICIOS NO PERSONALES'),
        array('cod' => '01-08-00-00-51-403-99-01-00', 'denom' => 'Otros Servicio no Personales', 'grupo' => 'SERVICIOS NO PERSONALES'),

        /* ── 404: ACTIVOS REALES ── */
        array('cod' => '01-08-00-00-51-404-01-01-02', 'denom' => 'Repuestos Mayores para Equipos de Transporte, Tracción y Elevación', 'grupo' => 'ACTIVOS REALES'),
        array('cod' => '01-08-00-00-51-404-01-01-07', 'denom' => 'Repuestos mayores para maquinas, muebles y demas equipos de oficina  y alojamiento', 'grupo' => 'ACTIVOS REALES'),
        array('cod' => '01-08-00-00-51-404-01-02-02', 'denom' => 'Reparaciones, mejoras y adiciones mayores de equipos de transporte, traccion y elevacion.', 'grupo' => 'ACTIVOS REALES'),
        array('cod' => '01-08-00-00-51-404-05-01-00', 'denom' => 'Equipos de Telecomunicaciones', 'grupo' => 'ACTIVOS REALES'),
        array('cod' => '01-08-00-00-51-404-07-03-00', 'denom' => 'Obras de Arte', 'grupo' => 'ACTIVOS REALES'),
        array('cod' => '01-08-00-00-51-404-07-04-00', 'denom' => 'Libros Revistas y Otros Instrumentos de Enseñanza', 'grupo' => 'ACTIVOS REALES'),
        array('cod' => '01-08-00-00-51-404-09-01-00', 'denom' => 'Mobiliario y Equipos de Oficina', 'grupo' => 'ACTIVOS REALES'),
        array('cod' => '01-08-00-00-51-404-09-02-00', 'denom' => 'Equipos de Computacion', 'grupo' => 'ACTIVOS REALES'),
        array('cod' => '01-08-00-00-51-404-09-03-00', 'denom' => 'Mobiliario y Equipo de Alojamiento', 'grupo' => 'ACTIVOS REALES'),
        array('cod' => '01-08-00-00-51-404-99-01-00', 'denom' => 'Otros Activos Reales', 'grupo' => 'ACTIVOS REALES'),

        /* ── 407: TRANSFERENCIAS Y DONACIONES / JUBILACIONES ── */
        array('cod' => '01-08-00-00-51-407-01-01-02', 'denom' => 'Jubilaciones del Personal Empleado, Obrero y Militar', 'grupo' => 'TRANSFERENCIAS Y DONACIONES'),
        array('cod' => '01-08-00-00-51-407-01-01-13', 'denom' => 'Aguinaldos al Personal Empleado, Obrero y Militar', 'grupo' => 'TRANSFERENCIAS Y DONACIONES'),
        array('cod' => '01-08-00-00-51-407-01-01-16', 'denom' => 'Otras Subvenciones socio-Económicas del Personal Empleado, Obrero y Militar Jubilado.', 'grupo' => 'TRANSFERENCIAS Y DONACIONES'),
    );

    return $catalogo;
}

/**
 * Obtiene el catálogo de partidas presupuestarias.
 * Si se pasa una conexión ($conn) y la tabla `partidas` tiene registros activos,
 * usa la base de datos (fuente de verdad editable desde el módulo Partidas).
 * Si no hay conexión o la tabla está vacía, usa el catálogo estático (comportamiento original).
 */
function get_catalogo_partidas($conn = null) {
    static $catalogo_db = null;
    if ($conn && $catalogo_db === null) {
        $catalogo_db = array();
        $res = @mysqli_query($conn, "SELECT codigo, denominacion, grupo FROM partidas WHERE activa = 1 ORDER BY codigo ASC");
        if ($res !== false) {
            while ($row = mysqli_fetch_assoc($res)) {
                $catalogo_db[] = array('cod' => $row['codigo'], 'denom' => $row['denominacion'], 'grupo' => $row['grupo']);
            }
        }
        if (empty($catalogo_db)) {
            $catalogo_db = null;
        }
    }
    if ($catalogo_db !== null) {
        return $catalogo_db;
    }
    return get_catalogo_estatico();
}

/**
 * Obtiene el nombre del grupo según el código de la partida
 */
function get_grupo_partida($cod) {
    if (strpos($cod, '-401-') !== false || strpos($cod, '4.01.') === 0 || strpos($cod, '401-') === 0) {
        return 'GASTOS DE PERSONAL';
    }
    if (strpos($cod, '-402-') !== false || strpos($cod, '4.02.') === 0 || strpos($cod, '402-') === 0) {
        return 'MATERIALES Y SUMINISTROS';
    }
    if (strpos($cod, '-403-') !== false || strpos($cod, '4.03.') === 0 || strpos($cod, '403-') === 0) {
        return 'SERVICIOS NO PERSONALES';
    }
    if (strpos($cod, '-404-') !== false || strpos($cod, '4.04.') === 0 || strpos($cod, '404-') === 0) {
        return 'ACTIVOS REALES';
    }
    if (strpos($cod, '-407-') !== false || strpos($cod, '4.07.') === 0 || strpos($cod, '407-') === 0) {
        return 'TRANSFERENCIAS Y DONACIONES';
    }
    return 'OTRAS PARTIDAS';
}

/**
 * Genera variantes del código para búsquedas y cruces SQL
 */
function get_matching_code_variants($cod) {
    $variants = array();
    $raw = trim($cod);
    if ($raw === '') return $variants;
    
    $variants[] = $raw;
    $clean = str_replace(array('.', ' '), '-', $raw);

    $prefix = '01-08-00-00-51-';
    if (strpos($clean, $prefix) === 0) {
        $variants[] = $clean;
        $tail = substr($clean, strlen($prefix));
        $variants[] = $tail;

        $p = explode('-', $tail);
        if (count($p) >= 1 && strlen($p[0]) === 3) {
            $ramo = substr($p[0], 0, 1) . '.' . substr($p[0], 1);
            $rest = array_slice($p, 1);
            $dot_ver = $ramo . (count($rest) > 0 ? '.' . implode('.', $rest) : '');
            $variants[] = $dot_ver;
        }
    } else {
        $parts = explode('.', str_replace('-', '.', $raw));
        if (count($parts) >= 2 && strlen($parts[0]) === 1 && strlen($parts[1]) === 2) {
            $merged = $parts[0] . $parts[1];
            $rest = array_slice($parts, 2);
            $dash_tail = $merged . (count($rest) > 0 ? '-' . implode('-', $rest) : '');
            $variants[] = $prefix . $dash_tail;
            $variants[] = $dash_tail;
        } elseif (count(explode('-', $raw)) >= 2) {
            $variants[] = $prefix . $raw;
        }
    }

    return array_values(array_unique(array_filter($variants)));
}

/**
 * Función para precargar el catálogo en la base de datos para un mes/año determinado si está vacío
 */
function precargar_catalogo_si_vacio($conn, $mes, $anio) {
    $count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM ejecucion_presupuestaria WHERE mes = $mes AND anio = $anio");
    $count = $count_res ? (int)mysqli_fetch_assoc($count_res)['total'] : 0;
    if ($count > 0) {
        return;
    }

    $catalogo = get_catalogo_partidas();
    foreach ($catalogo as $item) {
        $cod = mysqli_real_escape_string($conn, $item['cod']);
        $denom = mysqli_real_escape_string($conn, $item['denom']);
        mysqli_query($conn, "INSERT IGNORE INTO ejecucion_presupuestaria (codificacion, denominacion, credito_aprobado, aumentos, disminuciones, mes, anio)
                             VALUES ('$cod', '$denom', 0.00, 0.00, 0.00, $mes, $anio)");
    }
}
