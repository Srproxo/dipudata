-- ============================================================================
-- DIPUDATA / MASTER SEED DATA (MySQL 8.0+)
-- Sincronización de 8 legisladores clave en las 10 tablas normalizadas
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. legisladores_master
INSERT INTO legisladores_master
(id, nombre, slug, camara, provincia, bloque, estado, id_recinto, cuit, nombre_wiki, biografia_extracto, foto_url, asistencia, indice_lealtad) VALUES
(1, 'Martín Alexis Menem', 'martin-alexis-menem', 'Diputados', 'La Rioja', 'La Libertad Avanza', 'Activo', 'HCDN-1001', '20-21458912-6', 'Martín_Menem', 'Abogado y empresario argentino. Presidente de la H. Cámara de Diputados de la Nación. Promotor de la desregulación económica y el equilibrio fiscal.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/86/Mart%C3%ADn_Menem_2023.jpg/400px-Mart%C3%ADn_Menem_2023.jpg', 97.20, 98.50),
(2, 'Cristian Ritondo', 'cristian-ritondo', 'Diputados', 'Buenos Aires', 'PRO', 'Activo', 'HCDN-1002', '20-18234901-2', 'Cristian_Ritondo', 'Presidente del bloque PRO en Diputados. Ex Ministro de Seguridad bonaerense. Impulsor de leyes penales y de seguridad.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/25/Cristian_Ritondo_2019.jpg/400px-Cristian_Ritondo_2019.jpg', 93.80, 94.20),
(3, 'Rodrigo de Loredo', 'rodrigo-de-loredo', 'Diputados', 'Córdoba', 'UCR', 'Activo', 'HCDN-1003', '20-27981234-5', 'Rodrigo_de_Loredo', 'Diputado Nacional por Córdoba. Presidente del bloque de la Unión Cívica Radical en Diputados, enfocado en educación y desarrollo productivo.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1a/Rodrigo_de_Loredo_2021.jpg/400px-Rodrigo_de_Loredo_2021.jpg', 95.10, 89.40),
(4, 'Germán Martínez', 'german-martinez', 'Diputados', 'Santa Fe', 'Unión por la Patria', 'Activo', 'HCDN-1004', '20-24112890-4', 'Germán_Martínez_(político)', 'Licenciado en Ciencia Política. Presidente del bloque Unión por la Patria en la Cámara de Diputados.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/36/Germ%C3%A1n_Pedro_Mart%C3%ADnez.jpg/400px-Germ%C3%A1n_Pedro_Mart%C3%ADnez.jpg', 98.00, 96.80),
(5, 'Miguel Ángel Pichetto', 'miguel-angel-pichetto', 'Diputados', 'Buenos Aires', 'Encuentro Federal', 'Activo', 'HCDN-1005', '20-10456789-5', 'Miguel_Ángel_Pichetto', 'Abogado y legislador de extensa trayectoria. Presidente del bloque Encuentro Federal en Diputados.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a2/Miguel_Pichetto_2019.jpg/400px-Miguel_Pichetto_2019.jpg', 91.40, 78.50),
(6, 'Myriam Bregman', 'myriam-bregman', 'Diputados', 'Ciudad Autónoma de Buenos Aires', 'Frente de Izquierda', 'Activo', 'HCDN-1006', '27-22654321-5', 'Myriam_Bregman', 'Abogada laboralista y de derechos humanos. Dirigente del Partido de los Trabajadores Socialistas (PTS) en el Frente de Izquierda.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9f/Myriam_Bregman_2021.jpg/400px-Myriam_Bregman_2021.jpg', 94.60, 99.10),
(7, 'Martín Lousteau', 'martin-lousteau', 'Senadores', 'Ciudad Autónoma de Buenos Aires', 'UCR', 'Activo', 'HSN-2001', '20-21789654-2', 'Martín_Lousteau', 'Economista y Senador Nacional por la Ciudad de Buenos Aires. Especializado en macroeconomía, finanzas y financiamiento universitario.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/98/Mart%C3%ADn_Lousteau_2023.jpg/400px-Mart%C3%ADn_Lousteau_2023.jpg', 96.50, 82.30),
(8, 'José Mayans', 'jose-mayans', 'Senadores', 'Formosa', 'Unión por la Patria', 'Activo', 'HSN-2002', '20-12876543-4', 'José_Mayans', 'Senador Nacional por la provincia de Formosa. Presidente del interbloque de Unión por la Patria en el Senado.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/30/Jos%C3%A9_Mayans_2023.jpg/400px-Jos%C3%A9_Mayans_2023.jpg', 95.00, 97.40)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    bloque = VALUES(bloque),
    asistencia = VALUES(asistencia),
    indice_lealtad = VALUES(indice_lealtad);

-- 3. leyes
INSERT INTO leyes (id, expediente, titulo, eje_id, sector_vinculado, estado_tramite, fecha_tratamiento, es_ley_espejo, ley_espejo_id) VALUES
(1,  '0001-D-2024', 'Ley de Bases y Puntos de Partida para la Libertad de los Argentinos', 4, 'Bancario / Fintech', 'Sancionada', '2024-04-30', 0, NULL),
(2,  '0012-D-2024', 'Reforma del Régimen Penal Juvenil e Imputabilidad a 13 años', 7, 'Seguridad', 'Con Dictamen', '2024-07-15', 0, NULL),
(3,  '0045-D-2024', 'Ley de Financiamiento Integral de Universidades Nacionales', 2, 'Educación', 'Sancionada', '2024-09-12', 0, NULL),
(4,  '0078-D-2024', 'Emergencia Pediátrica y Equipamiento de Hospitales Nacionales', 1, 'Farmacéutico', 'Sancionada', '2024-10-02', 0, NULL),
(5,  '0102-D-2024', 'Movilidad Jubilatoria y Cobertura Integral de Medicamentos PAMI', 5, 'Previsión Social', 'Sancionada', '2024-06-05', 0, NULL),
(6,  '0145-D-2024', 'Protección de Glaciares y Cuencas Hídricas Estratégicas', 6, 'Energía / Minería', 'En Comisión', '2024-08-20', 0, NULL),
(7,  '0180-D-2024', 'Régimen de Promoción de la Industria Nacional y PyMEs Autopartistas', 3, 'Metalmecánico / PyME', 'En Trámite', '2024-05-18', 0, NULL),
(8,  '0210-D-2024', 'Régimen de Ficha Limpia e Inhabilitación por Delitos de Corrupción', 10, 'Institucional', 'Con Dictamen', '2024-11-20', 0, NULL),
(9,  '0240-D-2024', 'Reforma Tributaria Federal y Coparticipación de Aportes al Agro', 9, 'Agroindustria', 'En Comisión', '2024-09-28', 0, NULL),
(10, '0280-D-2024', 'Regulación de Plataformas Digitales y Empleo Tecnológico', 11, 'Telecomunicaciones / Tech', 'En Trámite', '2024-10-14', 0, NULL),
(11, '0310-D-2024', 'Sistema Federal de Cuidados y Ampliación de Licencias Familiares', 8, 'Salud / Género', 'En Comisión', '2024-08-05', 0, NULL),
(12, '0090-D-2020', 'Ley de Manejo del Fuego y Protección de Bosques Nativos (2020)', 6, 'Agroindustria / Inmobiliario', 'Sancionada', '2020-11-18', 1, NULL),
(13, '0091-D-2024', 'Modificación a la Ley de Manejo del Fuego (Desregulación Inmobiliaria)', 6, 'Agroindustria / Inmobiliario', 'En Trámite', '2024-09-10', 1, 12)
ON DUPLICATE KEY UPDATE
    titulo = VALUES(titulo),
    eje_id = VALUES(eje_id),
    sector_vinculado = VALUES(sector_vinculado);

-- 4. votaciones
INSERT INTO votaciones (legislador_id, ley_id, voto, fecha) VALUES
-- Menem (LLA): Pro-mercado, disciplina partidaria
(1, 1, 'Afirmativo', '2024-04-30'),
(1, 2, 'Afirmativo', '2024-07-15'),
(1, 3, 'Negativo',   '2024-09-12'),
(1, 4, 'Negativo',   '2024-10-02'),
(1, 5, 'Negativo',   '2024-06-05'),
(1, 6, 'Negativo',   '2024-08-20'),
(1, 7, 'Negativo',   '2024-05-18'),
(1, 8, 'Afirmativo', '2024-11-20'),
(1, 9, 'Negativo',   '2024-09-28'),
(1, 10, 'Negativo',  '2024-10-14'),
(1, 11, 'Negativo',  '2024-08-05'),
(1, 13, 'Afirmativo', '2024-09-10'),

-- Ritondo (PRO): Seguridad, Afirmativo en desregulación, disidencia en salud/jubilados
(2, 1, 'Afirmativo', '2024-04-30'),
(2, 2, 'Afirmativo', '2024-07-15'),
(2, 3, 'Negativo',   '2024-09-12'),
(2, 4, 'Abstención', '2024-10-02'),
(2, 5, 'Afirmativo', '2024-06-05'),
(2, 6, 'Negativo',   '2024-08-20'),
(2, 7, 'Negativo',   '2024-05-18'),
(2, 8, 'Afirmativo', '2024-11-20'),
(2, 9, 'Negativo',   '2024-09-28'),
(2, 10, 'Negativo',  '2024-10-14'),
(2, 11, 'Negativo',  '2024-08-05'),

-- De Loredo (UCR): Educación universitaria, agro federal, conflicto provincial en Fuego
(3, 1, 'Afirmativo', '2024-04-30'),
(3, 2, 'Afirmativo', '2024-07-15'),
(3, 3, 'Afirmativo', '2024-09-12'),
(3, 4, 'Afirmativo', '2024-10-02'),
(3, 5, 'Afirmativo', '2024-06-05'),
(3, 6, 'Afirmativo', '2024-08-20'),
(3, 7, 'Afirmativo', '2024-05-18'),
(3, 8, 'Afirmativo', '2024-11-20'),
(3, 9, 'Afirmativo', '2024-09-28'),
(3, 10, 'Afirmativo', '2024-10-14'),
(3, 11, 'Afirmativo', '2024-08-05'),
(3, 12, 'Afirmativo', '2020-11-18'),
(3, 13, 'Afirmativo', '2024-09-10'),

-- Martínez (UxP): Oposición a reformas de mercado, defensa de salud y educación pública
(4, 1, 'Negativo',   '2024-04-30'),
(4, 2, 'Negativo',   '2024-07-15'),
(4, 3, 'Afirmativo', '2024-09-12'),
(4, 4, 'Afirmativo', '2024-10-02'),
(4, 5, 'Afirmativo', '2024-06-05'),
(4, 6, 'Afirmativo', '2024-08-20'),
(4, 7, 'Afirmativo', '2024-05-18'),
(4, 8, 'Negativo',   '2024-11-20'),
(4, 9, 'Afirmativo', '2024-09-28'),
(4, 10, 'Afirmativo', '2024-10-14'),
(4, 11, 'Afirmativo', '2024-08-05'),

-- Pichetto (Encuentro Federal): Alianzas pragmáticas, defensa industrial y jubilados
(5, 1, 'Afirmativo', '2024-04-30'),
(5, 2, 'Afirmativo', '2024-07-15'),
(5, 3, 'Afirmativo', '2024-09-12'),
(5, 4, 'Afirmativo', '2024-10-02'),
(5, 5, 'Afirmativo', '2024-06-05'),
(5, 6, 'Afirmativo', '2024-08-20'),
(5, 7, 'Afirmativo', '2024-05-18'),
(5, 8, 'Afirmativo', '2024-11-20'),
(5, 9, 'Afirmativo', '2024-09-28'),
(5, 10, 'Afirmativo', '2024-10-14'),
(5, 11, 'Afirmativo', '2024-08-05'),

-- Bregman (FIT): Oposición férrea al ajuste, derechos laborales y ambientales
(6, 1, 'Negativo',   '2024-04-30'),
(6, 2, 'Negativo',   '2024-07-15'),
(6, 3, 'Afirmativo', '2024-09-12'),
(6, 4, 'Afirmativo', '2024-10-02'),
(6, 5, 'Afirmativo', '2024-06-05'),
(6, 6, 'Afirmativo', '2024-08-20'),
(6, 7, 'Afirmativo', '2024-05-18'),
(6, 8, 'Negativo',   '2024-11-20'),
(6, 9, 'Negativo',   '2024-09-28'),
(6, 10, 'Afirmativo', '2024-10-14'),
(6, 11, 'Afirmativo', '2024-08-05'),

-- Lousteau (Senado UCR): Disidencia con oficialismo nacional en universidades y DNU
(7, 1, 'Negativo',   '2024-04-30'),
(7, 2, 'Afirmativo', '2024-07-15'),
(7, 3, 'Afirmativo', '2024-09-12'),
(7, 4, 'Afirmativo', '2024-10-02'),
(7, 5, 'Afirmativo', '2024-06-05'),
(7, 6, 'Afirmativo', '2024-08-20'),
(7, 7, 'Afirmativo', '2024-05-18'),
(7, 8, 'Afirmativo', '2024-11-20'),
(7, 9, 'Afirmativo', '2024-09-28'),
(7, 10, 'Afirmativo', '2024-10-14'),
(7, 11, 'Afirmativo', '2024-08-05'),

-- Mayans (Senado UxP): Cohesión peronista, defensa de coparticipación
(8, 1, 'Negativo',   '2024-04-30'),
(8, 2, 'Negativo',   '2024-07-15'),
(8, 3, 'Afirmativo', '2024-09-12'),
(8, 4, 'Afirmativo', '2024-10-02'),
(8, 5, 'Afirmativo', '2024-06-05'),
(8, 6, 'Afirmativo', '2024-08-20'),
(8, 7, 'Afirmativo', '2024-05-18'),
(8, 8, 'Negativo',   '2024-11-20'),
(8, 9, 'Afirmativo', '2024-09-28'),
(8, 10, 'Afirmativo', '2024-10-14'),
(8, 11, 'Afirmativo', '2024-08-05')
ON DUPLICATE KEY UPDATE voto = VALUES(voto);

-- 5. proyectos (Autoría y Co-autoría para Grafo de Alianzas)
INSERT INTO proyectos (ley_id, legislador_id, rol, anio) VALUES
(1, 1, 'Autor', 2024),
(1, 2, 'Co-autor', 2024),
(2, 2, 'Autor', 2024),
(2, 1, 'Co-autor', 2024),
(3, 3, 'Autor', 2024),
(3, 5, 'Co-autor', 2024), -- Alianza transversal UCR + Encuentro Federal
(3, 4, 'Co-autor', 2024), -- Alianza opositora transversal con UxP
(4, 4, 'Autor', 2024),
(4, 6, 'Co-autor', 2024), -- Alianza UxP + FIT
(5, 5, 'Autor', 2024),
(5, 3, 'Co-autor', 2024),
(6, 6, 'Autor', 2024),
(7, 5, 'Autor', 2024),
(8, 2, 'Autor', 2024),
(8, 3, 'Co-autor', 2024),
(9, 3, 'Autor', 2024),
(10, 7, 'Autor', 2024),
(11, 6, 'Autor', 2024),
(11, 4, 'Co-autor', 2024)
ON DUPLICATE KEY UPDATE rol = VALUES(rol);

-- 6. patrimonio_ddjj
INSERT INTO patrimonio_ddjj (cuit, anio, patrimonio_declarado, ajuste_inflacion, variacion_real_porcentaje) VALUES
('20-21458912-6', 2022, 125000000.00, 94.80, 0.00),
('20-21458912-6', 2023, 320000000.00, 211.40, 15.20),
('20-21458912-6', 2024, 780000000.00, 118.00, 31.40),

('20-18234901-2', 2022, 98000000.00,  94.80, 0.00),
('20-18234901-2', 2023, 290000000.00, 211.40, 12.50),
('20-18234901-2', 2024, 680000000.00, 118.00, 22.80),

('20-27981234-5', 2022, 45000000.00,  94.80, 0.00),
('20-27981234-5', 2023, 110000000.00, 211.40, -5.20),
('20-27981234-5', 2024, 230000000.00, 118.00, -2.10)
ON DUPLICATE KEY UPDATE patrimonio_declarado = VALUES(patrimonio_declarado);

-- 7. leyes_impacto_provincial
INSERT INTO leyes_impacto_provincial (ley_id, provincia, voto_esperado_regional, nivel_criticidad, descripcion_impacto) VALUES
(13, 'Córdoba', 'Negativo', 3, 'La flexibilización de plazos para quemas en bosque nativo perjudica de forma directa las serranías cordobesas afectadas por incendios.'),
(9,  'Santa Fe', 'Afirmativo', 3, 'La coparticipación de derechos de exportación al agro beneficia de forma directa a la red productiva santafesina.'),
(9,  'Córdoba',  'Afirmativo', 3, 'Alivio fiscal a la cadena maicera y sojera provincial con reintegro directo a municipios.'),
(1,  'La Rioja', 'Negativo',   2, 'La eliminación de fondos fiduciarios para obras públicas provinciales reduce los ingresos coparticipables.'),
(3,  'Ciudad Autónoma de Buenos Aires', 'Afirmativo', 3, 'El presupuesto universitario sostiene la operatividad integral de la Universidad de Buenos Aires (UBA).')
ON DUPLICATE KEY UPDATE voto_esperado_regional = VALUES(voto_esperado_regional);

-- 8. despacho_costos
INSERT INTO despacho_costos (legislador_id, anio, cantidad_asesores, gasto_mensual, gasto_anual, alertas_nepotismo, proyectos_autor_anio, costo_por_proyecto, fuente) VALUES
(1, 2024, 14, 21500000.00, 279500000.00, 2, 5, 55900000.00, 'n8n.buceo.co'),
(2, 2024, 18, 27800000.00, 361400000.00, 1, 5, 72280000.00, 'n8n.buceo.co'),
(3, 2024, 11, 16500000.00, 214500000.00, 0, 5, 42900000.00, 'n8n.buceo.co'),
(4, 2024, 15, 23200000.00, 301600000.00, 0, 5, 60320000.00, 'n8n.buceo.co'),
(5, 2024, 12, 19000000.00, 247000000.00, 0, 5, 49400000.00, 'n8n.buceo.co'),
(6, 2024,  6,  8900000.00, 115700000.00, 0, 5, 23140000.00, 'n8n.buceo.co'),
(7, 2024, 22, 35600000.00, 462800000.00, 0, 5, 92560000.00, 'n8n.buceo.co'),
(8, 2024, 24, 38900000.00, 505700000.00, 1, 5, 101140000.00, 'n8n.buceo.co')
ON DUPLICATE KEY UPDATE cantidad_asesores = VALUES(cantidad_asesores);

-- 9. financiamiento_campana (Cruce de Conflicto de Interés)
INSERT INTO financiamiento_campana (legislador_id, aportante_cuit, aportante_nombre, sector_productivo, monto_ars, anio) VALUES
(1, '30-68912345-9', 'Fintech Inversiones del Sur S.A.', 'Bancario / Fintech', 18500000.00, 2023),
(1, '30-71234567-8', 'Agropecuaria Central Río Cuarto', 'Agroindustria', 14000000.00, 2023),
(2, '30-58471239-1', 'Desarrollos Urbanísticos Metropolitanos', 'Construcción / Inmobiliario', 25000000.00, 2023),
(2, '30-69852147-3', 'Laboratorios Andinos de Argentina', 'Farmacéutico', 16800000.00, 2023),
(3, '30-70891234-4', 'Cooperativa Agroindustrial de Córdoba', 'Agroindustria', 12000000.00, 2023),
(4, '30-54789632-1', 'Sindicato Unificado de Metalúrgicos', 'Metalmecánico / PyME', 9500000.00, 2023),
(5, '30-62145897-5', 'Cámara Federal de Empresas Logísticas', 'Transporte / Logística', 15000000.00, 2023),
(7, '30-71458963-2', 'Banca PyME y Desarrollo Bursátil', 'Bancario / Fintech', 17500000.00, 2023)
ON DUPLICATE KEY UPDATE monto_ars = VALUES(monto_ars);

-- 10. promesas_campana (Tracking con Check / Cruz)
INSERT INTO promesas_campana (legislador_id, promesa_texto, ley_id_vinculada, estado, descripcion_verificacion) VALUES
(1, 'Votar a favor de la desregulación total de la economía y la derogación de trabas cambiarias', 1, 'Cumplida', 'Votó AFIRMATIVO en la Ley de Bases (Expediente 0001-D-2024).'),
(1, 'No acompañar ningún aumento o financiamiento adicional sin partida fiscal previa', 3, 'Cumplida', 'Votó NEGATIVO en el Financiamiento Integral de Universidades (Expediente 0045-D-2024).'),
(2, 'Endurecer las penas para delitos graves y bajar la edad de imputabilidad juvenil a 13 años', 2, 'Cumplida', 'Autor principal del proyecto y votó AFIRMATIVO en el Régimen Penal Juvenil (Expediente 0012-D-2024).'),
(2, 'Defender el poder adquisitivo de los jubilados de la provincia de Buenos Aires', 5, 'Cumplida', 'Votó AFIRMATIVO en la Movilidad Jubilatoria (Expediente 0102-D-2024) marcando disidencia con LLA.'),
(3, 'Garantizar el financiamiento de las universidades públicas nacionales', 3, 'Cumplida', 'Autor principal y votó AFIRMATIVO en la Ley de Financiamiento Universitario (Expediente 0045-D-2024).'),
(3, 'Defender de forma irrestricta los bosques nativos y serranías de Córdoba contra incendios intencionales', 13, 'Incumplida', 'Votó AFIRMATIVO en la desregulación de plazos de la Ley de Manejo del Fuego contra el interés provincial.'),
(4, 'Rechazar la delegación de facultades extraordinarias y desregulaciones arancelarias', 1, 'Cumplida', 'Votó NEGATIVO en la Ley de Bases (Expediente 0001-D-2024) en bloque con UxP.'),
(4, 'Votar en contra de la Ficha Limpia por considerarla proscripción judicial', 8, 'Cumplida', 'Votó NEGATIVO en el proyecto de Ficha Limpia (Expediente 0210-D-2024).'),
(6, 'Votar siempre a favor de los derechos laborales, los jubilados y el medio ambiente', 5, 'Cumplida', 'Votó AFIRMATIVO en Movilidad Jubilatoria (Expediente 0102-D-2024).'),
(7, 'Respaldar las partidas para investigación y educación superior universitaria', 3, 'Cumplida', 'Votó AFIRMATIVO en el Senado garantizando la mayoría especial para la ley de financiamiento universitario.')
ON DUPLICATE KEY UPDATE estado = VALUES(estado);

SET FOREIGN_KEY_CHECKS = 1;
