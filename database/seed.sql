-- ============================================================================
-- LEGISDATA (dipudata.com) — DATASET SEMILLA REALISTA DEL CONGRESO ARGENTINO
-- Etapa 1: Arquitectura y Modelo de Datos
-- Incluye:
--   - 7 años de Indicadores Económicos (2019-2025: IPC Interanual y Dólar MEP)
--   - 20 Legisladores Nacionales (Diputados y Senadores de 8 bloques y 8 distritos)
--   - 120 Declaraciones Juradas Patrimoniales (2020-2025, Oficina Anticorrupción)
--   - 15 Leyes y Expedientes emblemáticos distribuidos en los 5 ejes temáticos
--   - 300 Votos nominales coherentes con la huella legislativa de cada bloque
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE votos;
TRUNCATE TABLE leyes;
TRUNCATE TABLE patrimonio_ddjj;
TRUNCATE TABLE indicadores_economicos;
TRUNCATE TABLE legisladores;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 1. INDICADORES ECONÓMICOS (SERIE HISTÓRICA 2019 - 2025)
-- ============================================================================
INSERT INTO indicadores_economicos (anio, ipc_interanual, dolar_mep_cierre) VALUES
(2019,  53.80,   73.10),
(2020,  36.10,  140.30),
(2021,  50.90,  197.80),
(2022,  94.80,  328.50),
(2023, 211.40,  995.00),
(2024, 117.80, 1165.00),
(2025,  32.40, 1280.00);

-- ============================================================================
-- 2. LEGISLADORES NACIONALES (20 PERFILES REPRESENTATIVOS)
-- ============================================================================
INSERT INTO legisladores (
    id, id_congreso, cuit, wiki_slug, nombre_completo, camara, provincia,
    bloque_actual, estado, biografia, foto_url, asistencia_historica, lealtad_partidaria
) VALUES
(1, 'HCDN-1001', '20-21458912-3', 'Martin_Menem', 'Martín Alexis Menem', 'Diputados', 'La Rioja',
 'La Libertad Avanza', 'Activo',
 'Abogado y empresario argentino graduado en la Universidad de Belgrano. Diputado Nacional por la provincia de La Rioja y Presidente de la Honorable Cámara de Diputados de la Nación desde diciembre de 2023. Impulsor de las reformas de desregulación económica y equilibrio fiscal.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/86/Mart%C3%ADn_Menem_2023.jpg/400px-Mart%C3%ADn_Menem_2023.jpg',
 98.40, 99.10),

(2, 'HCDN-1002', '20-14785236-9', 'Jose_Luis_Espert', 'José Luis Espert', 'Diputados', 'Buenos Aires',
 'La Libertad Avanza', 'Activo',
 'Doctor en Economía (UCEMA), economista y docente universitario. Presidente de la Comisión de Presupuesto y Hacienda de la Cámara de Diputados. Especializado en comercio internacional, reforma tributaria y apertura de mercados.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3c/Jos%C3%A9_Luis_Espert_2021.jpg/400px-Jos%C3%A9_Luis_Espert_2021.jpg',
 95.20, 96.50),

(3, 'HCDN-1003', '20-23654789-4', 'Gabriel_Bornoroni', 'Gabriel Bornoroni', 'Diputados', 'Córdoba',
 'La Libertad Avanza', 'Activo',
 'Abogado y empresario estacionero cordobés. Presidente del bloque de La Libertad Avanza en la Cámara de Diputados de la Nación. Coordinador parlamentario de los acuerdos interbloques para la sanción de la Ley de Bases.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png',
 96.80, 98.70),

(4, 'HSN-2001', '20-28963147-8', 'Ezequiel_Atauche', 'Ezequiel Atauche', 'Senadores', 'Jujuy',
 'La Libertad Avanza', 'Activo',
 'Empresario e ingeniero industrial jujeño. Senador Nacional por la provincia de Jujuy y Presidente del bloque de La Libertad Avanza y de la Comisión de Presupuesto y Hacienda en el Honorable Senado de la Nación.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png',
 97.10, 98.90),

(5, 'HCDN-1004', '20-18236541-7', 'Cristian_Ritondo', 'Cristian Adrián Ritondo', 'Diputados', 'Buenos Aires',
 'PRO', 'Activo',
 'Licenciado en Ciencia Política y dirigente del PRO. Presidente del bloque PRO en la Cámara de Diputados de la Nación. Fue Ministro de Seguridad de la Provincia de Buenos Aires (2015-2019) y Vicepresidente primero de la Legislatura porteña.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/25/Cristian_Ritondo_2019.jpg/400px-Cristian_Ritondo_2019.jpg',
 92.60, 94.30),

(6, 'HCDN-1005', '27-26147852-4', 'Silvia_Lospennato', 'Silvia Gabriela Lospennato', 'Diputados', 'Buenos Aires',
 'PRO', 'Activo',
 'Licenciada en Ciencia Política (UBA) con posgrado en Economía Ambiental. Diputada Nacional por la Provincia de Buenos Aires e integrante de las comisiones de Presupuesto, Legislación General y Asuntos Constitucionales.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9e/Silvia_Lospennato_2018.jpg/400px-Silvia_Lospennato_2018.jpg',
 96.50, 91.80),

(7, 'HCDN-1006', '27-23589632-1', 'Maria_Eugenia_Vidal', 'María Eugenia Vidal', 'Diputados', 'Ciudad Autónoma de Buenos Aires',
 'PRO', 'Activo',
 'Licenciada en Ciencia Política por la Universidad Católica Argentina. Diputada Nacional por la Ciudad Autónoma de Buenos Aires. Se desempeñó como Gobernadora de la Provincia de Buenos Aires entre 2015 y 2019.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7c/Mar%C3%ADa_Eugenia_Vidal_en_2019.jpg/400px-Mar%C3%ADa_Eugenia_Vidal_en_2019.jpg',
 91.40, 93.20),

(8, 'HSN-2002', '27-24123698-5', 'Guadalupe_Tagliaferri', 'Guadalupe Tagliaferri', 'Senadores', 'Ciudad Autónoma de Buenos Aires',
 'PRO', 'Activo',
 'Licenciada en Ciencia Política (UBA). Senadora Nacional por la Ciudad Autónoma de Buenos Aires y presidenta de la Comisión de Acuerdos del Senado. Ex Ministra de Desarrollo Humano y Hábitat porteña.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4e/Guadalupe_Tagliaferri.jpg/400px-Guadalupe_Tagliaferri.jpg',
 95.80, 86.40),

(9, 'HCDN-1007', '20-27852147-3', 'Rodrigo_de_Loredo', 'Rodrigo de Loredo', 'Diputados', 'Córdoba',
 'UCR', 'Activo',
 'Abogado graduado en la Universidad Nacional de Córdoba con maestría en Derecho Empresario. Presidente del bloque de la Unión Cívica Radical en la Cámara de Diputados de la Nación.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/59/Rodrigo_de_Loredo.jpg/400px-Rodrigo_de_Loredo.jpg',
 93.80, 89.50),

(10, 'HCDN-1008', '27-22369852-9', 'Karina_Banfi', 'Karina Banfi', 'Diputados', 'Buenos Aires',
 'UCR', 'Activo',
 'Abogada egresada de la UBA y especialista en políticas de transparencia, acceso a la información pública y derechos humanos. Vicepresidenta del bloque de la Unión Cívica Radical en Diputados.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/18/Karina_Banfi.jpg/400px-Karina_Banfi.jpg',
 97.40, 91.20),

(11, 'HSN-2003', '20-21789456-2', 'Martin_Lousteau', 'Martín Lousteau', 'Senadores', 'Ciudad Autónoma de Buenos Aires',
 'UCR', 'Activo',
 'Licenciado en Economía (UdeSA) y Magister en Ciencias Económicas por la London School of Economics. Senador Nacional por CABA y Presidente del Comité Nacional de la Unión Cívica Radical.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/07/Mart%C3%ADn_Lousteau_2020.jpg/400px-Mart%C3%ADn_Lousteau_2020.jpg',
 94.10, 82.60),

(12, 'HCDN-1009', '20-24563214-5', 'German_Martinez', 'Germán Pedro Martínez', 'Diputados', 'Santa Fe',
 'Unión por la Patria', 'Activo',
 'Licenciado en Ciencia Política por la Universidad Nacional de Rosario. Diputado Nacional por la provincia de Santa Fe y Presidente del bloque Unión por la Patria en la Cámara de Diputados de la Nación.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c8/Germ%C3%A1n_Mart%C3%ADnez_Diputado.jpg/400px-Germ%C3%A1n_Mart%C3%ADnez_Diputado.jpg',
 97.90, 98.40),

(13, 'HCDN-1010', '20-25147896-1', 'Leandro_Santoro', 'Leandro Santoro', 'Diputados', 'Ciudad Autónoma de Buenos Aires',
 'Unión por la Patria', 'Activo',
 'Politólogo graduado en la Universidad de Buenos Aires y docente universitario. Diputado Nacional por la Ciudad Autónoma de Buenos Aires e integrante de las comisiones de Presupuesto y Relaciones Exteriores.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/Leandro_Santoro_2021.jpg/400px-Leandro_Santoro_2021.jpg',
 94.60, 95.80),

(14, 'HCDN-1011', '27-23145698-7', 'Victoria_Tolosa_Paz', 'Victoria Tolosa Paz', 'Diputados', 'Buenos Aires',
 'Unión por la Patria', 'Activo',
 'Contadora Pública Nacional egresada de la Universidad Católica de La Plata. Diputada Nacional por la Provincia de Buenos Aires y ex Ministra de Desarrollo Social de la Nación (2022-2023).',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/87/Victoria_Tolosa_Paz_2021.jpg/400px-Victoria_Tolosa_Paz_2021.jpg',
 93.20, 96.90),

(15, 'HSN-2004', '20-12456987-0', 'Jose_Mayans', 'José Miguel Ángel Mayans', 'Senadores', 'Formosa',
 'Unión por la Patria', 'Activo',
 'Senador Nacional por la provincia de Formosa desde 2001 y Presidente del interbloque de Unión por la Patria en el Honorable Senado de la Nación. Referente parlamentario del peronismo federal.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/62/Jos%C3%A9_Mayans_2018.jpg/400px-Jos%C3%A9_Mayans_2018.jpg',
 96.20, 97.50),

(16, 'HSN-2005', '27-21852963-3', 'Juliana_Di_Tullio', 'Juliana Di Tullio', 'Senadores', 'Buenos Aires',
 'Unión por la Patria', 'Activo',
 'Psicóloga social y dirigente política bonaerense. Senadora Nacional por la Provincia de Buenos Aires y presidenta del bloque Unidad Ciudadana / Unión por la Patria en la Cámara Alta.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b2/Juliana_Di_Tullio_2021.jpg/400px-Juliana_Di_Tullio_2021.jpg',
 95.40, 98.80),

(17, 'HCDN-1012', '20-08456321-6', 'Miguel_Angel_Pichetto', 'Miguel Ángel Pichetto', 'Diputados', 'Buenos Aires',
 'Encuentro Federal', 'Activo',
 'Abogado graduado en la Universidad Nacional de La Plata. Presidente del bloque Encuentro Federal en la Cámara de Diputados. Fue Senador Nacional durante tres mandatos e integrante de la Auditoría General de la Nación.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/44/Miguel_%C3%81ngel_Pichetto_2019.jpg/400px-Miguel_%C3%81ngel_Pichetto_2019.jpg',
 94.90, 88.20),

(18, 'HCDN-1013', '20-29654123-8', 'Juan_Manuel_Lopez', 'Juan Manuel López', 'Diputados', 'Buenos Aires',
 'Coalición Cívica', 'Activo',
 'Abogado constitucionalista egresado de la Universidad de Buenos Aires. Presidente del bloque de la Coalición Cívica ARI en la Cámara de Diputados de la Nación, especializado en institucionalidad y control republicano.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d3/Juan_Manuel_L%C3%B3pez_diputado.jpg/400px-Juan_Manuel_L%C3%B3pez_diputado.jpg',
 98.10, 94.70),

(19, 'HCDN-1014', '27-22741852-6', 'Myriam_Bregman', 'Myriam Teresa Bregman', 'Diputados', 'Ciudad Autónoma de Buenos Aires',
 'Frente de Izquierda', 'Activo',
 'Abogada laboralista y de derechos humanos egresada de la UBA. Referente parlamentaria del Frente de Izquierda y de Trabajadores - Unidad (PTS) por la Ciudad Autónoma de Buenos Aires.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9f/Myriam_Bregman_2021.jpg/400px-Myriam_Bregman_2021.jpg',
 93.50, 99.40),

(20, 'HSN-2006', '20-16985214-2', 'Carlos_Arce', 'Carlos Omar Arce', 'Senadores', 'Misiones',
 'Innovación Federal', 'Activo',
 'Médico tocoginecólogo y político misionero. Senador Nacional por la provincia de Misiones en representación del Frente Renovador de la Concordia (Innovación Federal) y ex Vicegobernador provincial.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png',
 94.30, 87.90);

-- ============================================================================
-- 3. DECLARACIONES JURADAS PATRIMONIALES (SERIE 2020 - 2025, 120 REGISTROS)
-- ============================================================================
INSERT INTO patrimonio_ddjj (legislador_id, anio_fiscal, monto_pesos_nominal, fuente) VALUES
-- 1. Martín Menem (Perfil empresario: alto patrimonio inicial y crecimiento moderado en USD)
(1, 2020,  48500000.00, 'Oficina Anticorrupción'),
(1, 2021,  72400000.00, 'Oficina Anticorrupción'),
(1, 2022, 134800000.00, 'Oficina Anticorrupción'),
(1, 2023, 445000000.00, 'Oficina Anticorrupción'),
(1, 2024, 568000000.00, 'Oficina Anticorrupción'),
(1, 2025, 652000000.00, 'Oficina Anticorrupción'),

-- 2. José Luis Espert (Productor agropecuario y consultor: incremento real USD ~22%)
(2, 2020,  39200000.00, 'Oficina Anticorrupción'),
(2, 2021,  58900000.00, 'Oficina Anticorrupción'),
(2, 2022, 104500000.00, 'Oficina Anticorrupción'),
(2, 2023, 338000000.00, 'Oficina Anticorrupción'),
(2, 2024, 412000000.00, 'Oficina Anticorrupción'),
(2, 2025, 436000000.00, 'Oficina Anticorrupción'),

-- 3. Gabriel Bornoroni (Empresario sector hidrocarburos: incremento real USD ~38%)
(3, 2020,  54000000.00, 'Oficina Anticorrupción'),
(3, 2021,  83100000.00, 'Oficina Anticorrupción'),
(3, 2022, 152000000.00, 'Oficina Anticorrupción'),
(3, 2023, 498000000.00, 'Oficina Anticorrupción'),
(3, 2024, 625000000.00, 'Oficina Anticorrupción'),
(3, 2025, 681000000.00, 'Oficina Anticorrupción'),

-- 4. Ezequiel Atauche (Empresario minero/inmobiliario: incremento real USD ~44%)
(4, 2020,  61500000.00, 'Oficina Anticorrupción'),
(4, 2021,  94200000.00, 'Oficina Anticorrupción'),
(4, 2022, 178000000.00, 'Oficina Anticorrupción'),
(4, 2023, 585000000.00, 'Oficina Anticorrupción'),
(4, 2024, 732000000.00, 'Oficina Anticorrupción'),
(4, 2025, 810000000.00, 'Oficina Anticorrupción'),

-- 5. Cristian Ritondo (Desarrollos inmobiliarios: patrimonio elevado y expansión real USD ~58%)
(5, 2020,  92000000.00, 'Oficina Anticorrupción'),
(5, 2021, 148000000.00, 'Oficina Anticorrupción'),
(5, 2022, 289000000.00, 'Oficina Anticorrupción'),
(5, 2023, 985000000.00, 'Oficina Anticorrupción'),
(5, 2024,1210000000.00, 'Oficina Anticorrupción'),
(5, 2025,1328000000.00, 'Oficina Anticorrupción'),

-- 6. Silvia Lospennato (Patrimonio profesional estable, variación real USD ~4%)
(6, 2020,  12800000.00, 'Oficina Anticorrupción'),
(6, 2021,  18100000.00, 'Oficina Anticorrupción'),
(6, 2022,  30900000.00, 'Oficina Anticorrupción'),
(6, 2023,  94500000.00, 'Oficina Anticorrupción'),
(6, 2024, 112000000.00, 'Oficina Anticorrupción'),
(6, 2025, 121800000.00, 'Oficina Anticorrupción'),

-- 7. María Eugenia Vidal (Patrimonio residencial/profesional, variación real USD ~11%)
(7, 2020,  24500000.00, 'Oficina Anticorrupción'),
(7, 2021,  35800000.00, 'Oficina Anticorrupción'),
(7, 2022,  61200000.00, 'Oficina Anticorrupción'),
(7, 2023, 192000000.00, 'Oficina Anticorrupción'),
(7, 2024, 228000000.00, 'Oficina Anticorrupción'),
(7, 2025, 249000000.00, 'Oficina Anticorrupción'),

-- 8. Guadalupe Tagliaferri (Patrimonio profesional, variación real USD -3%)
(8, 2020,  15400000.00, 'Oficina Anticorrupción'),
(8, 2021,  21200000.00, 'Oficina Anticorrupción'),
(8, 2022,  34800000.00, 'Oficina Anticorrupción'),
(8, 2023, 104000000.00, 'Oficina Anticorrupción'),
(8, 2024, 123000000.00, 'Oficina Anticorrupción'),
(8, 2025, 136200000.00, 'Oficina Anticorrupción'),

-- 9. Rodrigo de Loredo (Estudio jurídico y participaciones: variación real USD +19%)
(9, 2020,  28900000.00, 'Oficina Anticorrupción'),
(9, 2021,  42500000.00, 'Oficina Anticorrupción'),
(9, 2022,  76400000.00, 'Oficina Anticorrupción'),
(9, 2023, 241000000.00, 'Oficina Anticorrupción'),
(9, 2024, 289000000.00, 'Oficina Anticorrupción'),
(9, 2025, 314500000.00, 'Oficina Anticorrupción'),

-- 10. Karina Banfi (Patrimonio profesional medio, variación real USD +1.5%)
(10, 2020,  11200000.00, 'Oficina Anticorrupción'),
(10, 2021,  15900000.00, 'Oficina Anticorrupción'),
(10, 2022,  26500000.00, 'Oficina Anticorrupción'),
(10, 2023,  81200000.00, 'Oficina Anticorrupción'),
(10, 2024,  94800000.00, 'Oficina Anticorrupción'),
(10, 2025, 103700000.00, 'Oficina Anticorrupción'),

-- 11. Martín Lousteau (Consultoría económica y activos financieros: variación real USD +16%)
(11, 2020,  44600000.00, 'Oficina Anticorrupción'),
(11, 2021,  66100000.00, 'Oficina Anticorrupción'),
(11, 2022, 114000000.00, 'Oficina Anticorrupción'),
(11, 2023, 358000000.00, 'Oficina Anticorrupción'),
(11, 2024, 431000000.00, 'Oficina Anticorrupción'),
(11, 2025, 472000000.00, 'Oficina Anticorrupción'),

-- 12. Germán Martínez (Patrimonio familiar y docente: variación real USD +28%)
(12, 2020,  13500000.00, 'Oficina Anticorrupción'),
(12, 2021,  20400000.00, 'Oficina Anticorrupción'),
(12, 2022,  36900000.00, 'Oficina Anticorrupción'),
(12, 2023, 121000000.00, 'Oficina Anticorrupción'),
(12, 2024, 146000000.00, 'Oficina Anticorrupción'),
(12, 2025, 158000000.00, 'Oficina Anticorrupción'),

-- 13. Leandro Santoro (Patrimonio docente y residencial CABA: variación real USD +6%)
(13, 2020,  14800000.00, 'Oficina Anticorrupción'),
(13, 2021,  21400000.00, 'Oficina Anticorrupción'),
(13, 2022,  36200000.00, 'Oficina Anticorrupción'),
(13, 2023, 111000000.00, 'Oficina Anticorrupción'),
(13, 2024, 131500000.00, 'Oficina Anticorrupción'),
(13, 2025, 143500000.00, 'Oficina Anticorrupción'),

-- 14. Victoria Tolosa Paz (Sociedades y bienes inmuebles: crecimiento real USD +64%)
(14, 2020,  36400000.00, 'Oficina Anticorrupción'),
(14, 2021,  59800000.00, 'Oficina Anticorrupción'),
(14, 2022, 118500000.00, 'Oficina Anticorrupción'),
(14, 2023, 412000000.00, 'Oficina Anticorrupción'),
(14, 2024, 502000000.00, 'Oficina Anticorrupción'),
(14, 2025, 545000000.00, 'Oficina Anticorrupción'),

-- 15. José Mayans (Inmuebles rurales y urbanos en Formosa: crecimiento real USD +78%)
(15, 2020,  27500000.00, 'Oficina Anticorrupción'),
(15, 2021,  46200000.00, 'Oficina Anticorrupción'),
(15, 2022,  94800000.00, 'Oficina Anticorrupción'),
(15, 2023, 336000000.00, 'Oficina Anticorrupción'),
(15, 2024, 410000000.00, 'Oficina Anticorrupción'),
(15, 2025, 447000000.00, 'Oficina Anticorrupción'),

-- 16. Juliana Di Tullio (Bienes inmuebles PBA: crecimiento real USD +34%)
(16, 2020,  18900000.00, 'Oficina Anticorrupción'),
(16, 2021,  29500000.00, 'Oficina Anticorrupción'),
(16, 2022,  54200000.00, 'Oficina Anticorrupción'),
(16, 2023, 179000000.00, 'Oficina Anticorrupción'),
(16, 2024, 214000000.00, 'Oficina Anticorrupción'),
(16, 2025, 231000000.00, 'Oficina Anticorrupción'),

-- 17. Miguel Ángel Pichetto (Estudio jurídico e inmuebles: crecimiento real USD +21%)
(17, 2020,  33800000.00, 'Oficina Anticorrupción'),
(17, 2021,  50400000.00, 'Oficina Anticorrupción'),
(17, 2022,  91200000.00, 'Oficina Anticorrupción'),
(17, 2023, 289000000.00, 'Oficina Anticorrupción'),
(17, 2024, 344000000.00, 'Oficina Anticorrupción'),
(17, 2025, 374000000.00, 'Oficina Anticorrupción'),

-- 18. Juan Manuel López (Patrimonio profesional austero, variación real USD -1.2%)
(18, 2020,  10600000.00, 'Oficina Anticorrupción'),
(18, 2021,  14800000.00, 'Oficina Anticorrupción'),
(18, 2022,  24500000.00, 'Oficina Anticorrupción'),
(18, 2023,  73800000.00, 'Oficina Anticorrupción'),
(18, 2024,  86200000.00, 'Oficina Anticorrupción'),
(18, 2025,  95600000.00, 'Oficina Anticorrupción'),

-- 19. Myriam Bregman (Dieta donada al partido/fondo de lucha: variación real USD -9.5%)
(19, 2020,   6800000.00, 'Oficina Anticorrupción'),
(19, 2021,   9100000.00, 'Oficina Anticorrupción'),
(19, 2022,  14600000.00, 'Oficina Anticorrupción'),
(19, 2023,  42900000.00, 'Oficina Anticorrupción'),
(19, 2024,  50100000.00, 'Oficina Anticorrupción'),
(19, 2025,  56100000.00, 'Oficina Anticorrupción'),

-- 20. Carlos Omar Arce (Consultorio médico e inmuebles en Misiones: variación real USD +14%)
(20, 2020,  21500000.00, 'Oficina Anticorrupción'),
(20, 2021,  31200000.00, 'Oficina Anticorrupción'),
(20, 2022,  55100000.00, 'Oficina Anticorrupción'),
(20, 2023, 174000000.00, 'Oficina Anticorrupción'),
(20, 2024, 206000000.00, 'Oficina Anticorrupción'),
(20, 2025, 223800000.00, 'Oficina Anticorrupción');

-- ============================================================================
-- 4. LEYES Y EXPEDIENTES PARLAMENTARIOS (15 LEYES EN LOS 5 EJES TEMÁTICOS)
-- ============================================================================
INSERT INTO leyes (id, titulo, resumen, fecha_votacion, eje_tematico, peso_ideologico) VALUES
-- EJE 1: MercadoLibre (Apertura económica, desregulación, incentivos a la inversión privada)
('27742',
 'Ley de Bases y Puntos de Partida para la Libertad de los Argentinos',
 'Establece la emergencia pública administrativa y económica, la reforma del Estado, privatizaciones de empresas públicas y el Régimen de Incentivo para Grandes Inversiones (RIGI).',
 '2024-06-28', 'MercadoLibre', 1.00),

('DNU-70-2023',
 'DNU 70/2023 — Bases para la Reconstrucción de la Economía Argentina',
 'Desregulación integral del comercio interior y exterior, derogación de la Ley de Alquileres, Ley de Góndolas y Ley de Abastecimiento, y liberación de precios y contratos en moneda extranjera.',
 '2024-03-14', 'MercadoLibre', 0.95),

('27743',
 'Medidas Fiscales Paliativas y Relevantes (Paquete Fiscal)',
 'Régimen de Regularización de Activos (blanqueo de capitales), reducción progresiva del Impuesto sobre los Bienes Personales, moratoria fiscal y restitución de la cuarta categoría de Ganancias.',
 '2024-06-28', 'MercadoLibre', 0.85),

-- EJE 2: Proteccionismo (Intervención estatal, regulaciones de mercado, tributos extraordinarios)
('27642',
 'Ley de Promoción de la Alimentación Saludable (Etiquetado Frontal)',
 'Obliga a incorporar sellos de advertencia octogonales negros en alimentos y bebidas envasadas con exceso de azúcares, sodio, grasas saturadas y calorías, regulando su publicidad y comercialización.',
 '2021-10-26', 'Proteccionismo', 0.75),

('27551',
 'Ley de Alquileres — Reforma del Régimen de Locaciones Urbanas',
 'Extiende el plazo mínimo legal de los contratos de locación habitacional a tres años y establece un índice oficial único de actualización anual combinando IPC y RIPTE.',
 '2020-06-11', 'Proteccionismo', 0.85),

('27605',
 'Aporte Solidario y Extraordinario para Morigerar los Efectos de la Pandemia',
 'Crea un tributo extraordinario sobre los patrimonios de personas humanas superiores a los 200 millones de pesos con destino a equipamiento sanitario, subsidios a PyMEs y urbanización de barrios populares.',
 '2020-12-04', 'Proteccionismo', 0.95),

-- EJE 3: Salud (Inversión sanitaria pública, cobertura obligatoria y derechos reproductivos)
('27610',
 'Ley de Acceso a la Interrupción Voluntaria del Embarazo (IVE)',
 'Regula el acceso a la interrupción voluntaria del embarazo y a la atención posaborto en todo el sistema de salud público, privado y de obras sociales hasta la semana catorce de gestación.',
 '2020-12-30', 'Salud', 0.90),

('27678',
 'Ley Nacional de Cuidados Paliativos Integrales',
 'Asegura el acceso de los pacientes a las prestaciones integrales sobre cuidados paliativos en el ámbito público, privado y de la seguridad social, incluyendo el acompañamiento familiar.',
 '2022-07-05', 'Salud', 0.80),

('EXP-1420-D-2025',
 'Emergencia Sanitaria Pediátrica y Financiamiento del Hospital Garrahan',
 'Declara la emergencia sanitaria de la salud pediátrica y de las residencias médicas nacionales por dos años, garantizando recomposición presupuestaria y salarial de carácter intangible.',
 '2025-05-14', 'Salud', 0.95),

-- EJE 4: Educacion (Presupuesto universitario, ciencia y tecnología, servicio educativo)
('EXP-2810-D-2024',
 'Ley de Financiamiento de las Universidades Nacionales',
 'Establece la actualización bimestral automática por inflación (IPC INDEC) de los gastos de funcionamiento de las universidades públicas nacionales y la recomposición salarial docente y no docente.',
 '2024-09-12', 'Educacion', 1.00),

('27614',
 'Ley de Financiamiento del Sistema Nacional de Ciencia, Tecnología e Innovación',
 'Establece un cronograma de incremento progresivo y sostenido de la inversión pública nacional en ciencia y tecnología hasta alcanzar el 1% del Producto Bruto Interno (PBI) en 2032.',
 '2021-02-24', 'Educacion', 0.85),

('EXP-0915-D-2024',
 'Declaración de la Educación Obligatoria como Servicio Estratégico Esencial',
 'Garantiza el cumplimiento de los 180 días mínimos de clase estableciendo guardias educativas mínimas del 30% al 50% del personal escolar durante medidas de fuerza.',
 '2024-08-15', 'Educacion', 0.80),

-- EJE 5: Laboral (Regulación del trabajo, derechos sindicales, indemnizaciones)
('27555',
 'Régimen Legal del Contrato de Teletrabajo',
 'Regula los presupuestos legales mínimos para la modalidad de teletrabajo, consagrando el derecho a la desconexión digital, la reversibilidad contractual y la provisión de equipamiento por el empleador.',
 '2020-07-30', 'Laboral', 0.85),

('27718',
 'Exención del Impuesto a las Ganancias para Guardias Médicas y Horas Extras',
 'Exime del pago del Impuesto a las Ganancias a las remuneraciones percibidas en concepto de guardias obligatorias —activas o pasivas— y horas extras por profesionales y técnicos de la salud.',
 '2023-05-04', 'Laboral', 0.75),

('EXP-3105-D-2024',
 'Régimen de Tutela Sindical y Recálculo de Indemnizaciones por Despido',
 'Proyecto de fortalecimiento de las indemnizaciones agravadas por trabajo no registrado y actualización de créditos laborales por IPC más tasa pura del 6% anual.',
 '2024-04-30', 'Laboral', 0.95);

-- ============================================================================
-- 5. MATRIZ DE VOTOS NOMINALES (20 LEGISLADORES x 15 LEYES = 300 REGISTROS)
-- ============================================================================
INSERT INTO votos (legislador_id, ley_id, sentido_voto) VALUES
-- 1. Martín Menem (LLA - Alto MercadoLibre, Bajo Proteccionismo/Laboral tradicional)
(1, '27742', 'Afirmativo'), (1, 'DNU-70-2023', 'Afirmativo'), (1, '27743', 'Afirmativo'),
(1, '27642', 'Negativo'),   (1, '27551', 'Negativo'),         (1, '27605', 'Negativo'),
(1, '27610', 'Negativo'),   (1, '27678', 'Abstencion'),       (1, 'EXP-1420-D-2025', 'Negativo'),
(1, 'EXP-2810-D-2024', 'Negativo'), (1, '27614', 'Negativo'), (1, 'EXP-0915-D-2024', 'Afirmativo'),
(1, '27555', 'Negativo'),   (1, '27718', 'Afirmativo'),       (1, 'EXP-3105-D-2024', 'Negativo'),

-- 2. José Luis Espert (LLA - Ortodoxia liberal pro-mercado)
(2, '27742', 'Afirmativo'), (2, 'DNU-70-2023', 'Afirmativo'), (2, '27743', 'Afirmativo'),
(2, '27642', 'Negativo'),   (2, '27551', 'Negativo'),         (2, '27605', 'Negativo'),
(2, '27610', 'Abstencion'), (2, '27678', 'Afirmativo'),       (2, 'EXP-1420-D-2025', 'Negativo'),
(2, 'EXP-2810-D-2024', 'Negativo'), (2, '27614', 'Abstencion'), (2, 'EXP-0915-D-2024', 'Afirmativo'),
(2, '27555', 'Negativo'),   (2, '27718', 'Afirmativo'),       (2, 'EXP-3105-D-2024', 'Negativo'),

-- 3. Gabriel Bornoroni (LLA - Jefe de bloque oficialista)
(3, '27742', 'Afirmativo'), (3, 'DNU-70-2023', 'Afirmativo'), (3, '27743', 'Afirmativo'),
(3, '27642', 'Negativo'),   (3, '27551', 'Negativo'),         (3, '27605', 'Negativo'),
(3, '27610', 'Negativo'),   (3, '27678', 'Afirmativo'),       (3, 'EXP-1420-D-2025', 'Negativo'),
(3, 'EXP-2810-D-2024', 'Negativo'), (3, '27614', 'Negativo'), (3, 'EXP-0915-D-2024', 'Afirmativo'),
(3, '27555', 'Negativo'),   (3, '27718', 'Abstencion'),       (3, 'EXP-3105-D-2024', 'Negativo'),

-- 4. Ezequiel Atauche (LLA Senado)
(4, '27742', 'Afirmativo'), (4, 'DNU-70-2023', 'Afirmativo'), (4, '27743', 'Afirmativo'),
(4, '27642', 'Negativo'),   (4, '27551', 'Negativo'),         (4, '27605', 'Negativo'),
(4, '27610', 'Negativo'),   (4, '27678', 'Abstencion'),       (4, 'EXP-1420-D-2025', 'Negativo'),
(4, 'EXP-2810-D-2024', 'Negativo'), (4, '27614', 'Negativo'), (4, 'EXP-0915-D-2024', 'Afirmativo'),
(4, '27555', 'Negativo'),   (4, '27718', 'Afirmativo'),       (4, 'EXP-3105-D-2024', 'Negativo'),

-- 5. Cristian Ritondo (PRO - Pro-mercado con respaldo parcial a salud/educación)
(5, '27742', 'Afirmativo'), (5, 'DNU-70-2023', 'Afirmativo'), (5, '27743', 'Afirmativo'),
(5, '27642', 'Abstencion'), (5, '27551', 'Afirmativo'),       (5, '27605', 'Negativo'),
(5, '27610', 'Negativo'),   (5, '27678', 'Afirmativo'),       (5, 'EXP-1420-D-2025', 'Abstencion'),
(5, 'EXP-2810-D-2024', 'Negativo'), (5, '27614', 'Afirmativo'), (5, 'EXP-0915-D-2024', 'Afirmativo'),
(5, '27555', 'Negativo'),   (5, '27718', 'Afirmativo'),       (5, 'EXP-3105-D-2024', 'Negativo'),

-- 6. Silvia Lospennato (PRO - Institucionalista, pro-mercado y agenda de salud/derechos)
(6, '27742', 'Afirmativo'), (6, 'DNU-70-2023', 'Afirmativo'), (6, '27743', 'Afirmativo'),
(6, '27642', 'Afirmativo'), (6, '27551', 'Afirmativo'),       (6, '27605', 'Negativo'),
(6, '27610', 'Afirmativo'), (6, '27678', 'Afirmativo'),       (6, 'EXP-1420-D-2025', 'Abstencion'),
(6, 'EXP-2810-D-2024', 'Abstencion'), (6, '27614', 'Afirmativo'), (6, 'EXP-0915-D-2024', 'Afirmativo'),
(6, '27555', 'Abstencion'), (6, '27718', 'Afirmativo'),       (6, 'EXP-3105-D-2024', 'Negativo'),

-- 7. María Eugenia Vidal (PRO - Centro-derecha republicana)
(7, '27742', 'Afirmativo'), (7, 'DNU-70-2023', 'Afirmativo'), (7, '27743', 'Afirmativo'),
(7, '27642', 'Negativo'),   (7, '27551', 'Ausente'),          (7, '27605', 'Negativo'),
(7, '27610', 'Negativo'),   (7, '27678', 'Afirmativo'),       (7, 'EXP-1420-D-2025', 'Abstencion'),
(7, 'EXP-2810-D-2024', 'Negativo'), (7, '27614', 'Afirmativo'), (7, 'EXP-0915-D-2024', 'Afirmativo'),
(7, '27555', 'Negativo'),   (7, '27718', 'Afirmativo'),       (7, 'EXP-3105-D-2024', 'Negativo'),

-- 8. Guadalupe Tagliaferri (PRO Senado - Perfil social/institucional moderado)
(8, '27742', 'Afirmativo'), (8, 'DNU-70-2023', 'Abstencion'), (8, '27743', 'Afirmativo'),
(8, '27642', 'Afirmativo'), (8, '27551', 'Afirmativo'),       (8, '27605', 'Negativo'),
(8, '27610', 'Afirmativo'), (8, '27678', 'Afirmativo'),       (8, 'EXP-1420-D-2025', 'Afirmativo'),
(8, 'EXP-2810-D-2024', 'Afirmativo'), (8, '27614', 'Afirmativo'), (8, 'EXP-0915-D-2024', 'Afirmativo'),
(8, '27555', 'Abstencion'), (8, '27718', 'Afirmativo'),       (8, 'EXP-3105-D-2024', 'Negativo'),

-- 9. Rodrigo de Loredo (UCR - Reformista con fuerte defensa de Educación Pública)
(9, '27742', 'Afirmativo'), (9, 'DNU-70-2023', 'Abstencion'), (9, '27743', 'Afirmativo'),
(9, '27642', 'Afirmativo'), (9, '27551', 'Afirmativo'),       (9, '27605', 'Negativo'),
(9, '27610', 'Abstencion'), (9, '27678', 'Afirmativo'),       (9, 'EXP-1420-D-2025', 'Afirmativo'),
(9, 'EXP-2810-D-2024', 'Afirmativo'), (9, '27614', 'Afirmativo'), (9, 'EXP-0915-D-2024', 'Afirmativo'),
(9, '27555', 'Abstencion'), (9, '27718', 'Afirmativo'),       (9, 'EXP-3105-D-2024', 'Negativo'),

-- 10. Karina Banfi (UCR - Institucionalista, pro Educación, Salud y Mercado regulado)
(10, '27742', 'Afirmativo'), (10, 'DNU-70-2023', 'Abstencion'), (10, '27743', 'Afirmativo'),
(10, '27642', 'Afirmativo'), (10, '27551', 'Afirmativo'),       (10, '27605', 'Negativo'),
(10, '27610', 'Afirmativo'), (10, '27678', 'Afirmativo'),       (10, 'EXP-1420-D-2025', 'Afirmativo'),
(10, 'EXP-2810-D-2024', 'Afirmativo'), (10, '27614', 'Afirmativo'), (10, 'EXP-0915-D-2024', 'Afirmativo'),
(10, '27555', 'Afirmativo'), (10, '27718', 'Afirmativo'),       (10, 'EXP-3105-D-2024', 'Abstencion'),

-- 11. Martín Lousteau (UCR Senado - Socialdemócrata, alto Educación/Salud, crítico del DNU)
(11, '27742', 'Abstencion'), (11, 'DNU-70-2023', 'Negativo'),   (11, '27743', 'Negativo'),
(11, '27642', 'Afirmativo'), (11, '27551', 'Afirmativo'),       (11, '27605', 'Negativo'),
(11, '27610', 'Afirmativo'), (11, '27678', 'Afirmativo'),       (11, 'EXP-1420-D-2025', 'Afirmativo'),
(11, 'EXP-2810-D-2024', 'Afirmativo'), (11, '27614', 'Afirmativo'), (11, 'EXP-0915-D-2024', 'Afirmativo'),
(11, '27555', 'Afirmativo'), (11, '27718', 'Afirmativo'),       (11, 'EXP-3105-D-2024', 'Abstencion'),

-- 12. Germán Martínez (Unión por la Patria - Jefe de bloque, alto Proteccionismo/Salud/Educacion/Laboral)
(12, '27742', 'Negativo'),   (12, 'DNU-70-2023', 'Negativo'),   (12, '27743', 'Negativo'),
(12, '27642', 'Afirmativo'), (12, '27551', 'Afirmativo'),       (12, '27605', 'Afirmativo'),
(12, '27610', 'Afirmativo'), (12, '27678', 'Afirmativo'),       (12, 'EXP-1420-D-2025', 'Afirmativo'),
(12, 'EXP-2810-D-2024', 'Afirmativo'), (12, '27614', 'Afirmativo'), (12, 'EXP-0915-D-2024', 'Negativo'),
(12, '27555', 'Afirmativo'), (12, '27718', 'Afirmativo'),       (12, 'EXP-3105-D-2024', 'Afirmativo'),

-- 13. Leandro Santoro (Unión por la Patria - Progresista urbano)
(13, '27742', 'Negativo'),   (13, 'DNU-70-2023', 'Negativo'),   (13, '27743', 'Negativo'),
(13, '27642', 'Afirmativo'), (13, '27551', 'Afirmativo'),       (13, '27605', 'Afirmativo'),
(13, '27610', 'Afirmativo'), (13, '27678', 'Afirmativo'),       (13, 'EXP-1420-D-2025', 'Afirmativo'),
(13, 'EXP-2810-D-2024', 'Afirmativo'), (13, '27614', 'Afirmativo'), (13, 'EXP-0915-D-2024', 'Negativo'),
(13, '27555', 'Afirmativo'), (13, '27718', 'Afirmativo'),       (13, 'EXP-3105-D-2024', 'Afirmativo'),

-- 14. Victoria Tolosa Paz (Unión por la Patria - Peronismo bonaerense)
(14, '27742', 'Negativo'),   (14, 'DNU-70-2023', 'Negativo'),   (14, '27743', 'Negativo'),
(14, '27642', 'Afirmativo'), (14, '27551', 'Afirmativo'),       (14, '27605', 'Afirmativo'),
(14, '27610', 'Afirmativo'), (14, '27678', 'Afirmativo'),       (14, 'EXP-1420-D-2025', 'Afirmativo'),
(14, 'EXP-2810-D-2024', 'Afirmativo'), (14, '27614', 'Afirmativo'), (14, 'EXP-0915-D-2024', 'Abstencion'),
(14, '27555', 'Afirmativo'), (14, '27718', 'Afirmativo'),       (14, 'EXP-3105-D-2024', 'Afirmativo'),

-- 15. José Mayans (Unión por la Patria Senado - Peronismo tradicional conservador en IVE)
(15, '27742', 'Negativo'),   (15, 'DNU-70-2023', 'Negativo'),   (15, '27743', 'Negativo'),
(15, '27642', 'Afirmativo'), (15, '27551', 'Afirmativo'),       (15, '27605', 'Afirmativo'),
(15, '27610', 'Negativo'),   (15, '27678', 'Afirmativo'),       (15, 'EXP-1420-D-2025', 'Afirmativo'),
(15, 'EXP-2810-D-2024', 'Afirmativo'), (15, '27614', 'Afirmativo'), (15, 'EXP-0915-D-2024', 'Negativo'),
(15, '27555', 'Afirmativo'), (15, '27718', 'Afirmativo'),       (15, 'EXP-3105-D-2024', 'Afirmativo'),

-- 16. Juliana Di Tullio (Unión por la Patria Senado - Kirchnerismo)
(16, '27742', 'Negativo'),   (16, 'DNU-70-2023', 'Negativo'),   (16, '27743', 'Negativo'),
(16, '27642', 'Afirmativo'), (16, '27551', 'Afirmativo'),       (16, '27605', 'Afirmativo'),
(16, '27610', 'Afirmativo'), (16, '27678', 'Afirmativo'),       (16, 'EXP-1420-D-2025', 'Afirmativo'),
(16, 'EXP-2810-D-2024', 'Afirmativo'), (16, '27614', 'Afirmativo'), (16, 'EXP-0915-D-2024', 'Negativo'),
(16, '27555', 'Afirmativo'), (16, '27718', 'Afirmativo'),       (16, 'EXP-3105-D-2024', 'Afirmativo'),

-- 17. Miguel Ángel Pichetto (Encuentro Federal - Centro pragmático federal)
(17, '27742', 'Afirmativo'), (17, 'DNU-70-2023', 'Abstencion'), (17, '27743', 'Afirmativo'),
(17, '27642', 'Negativo'),   (17, '27551', 'Abstencion'),       (17, '27605', 'Negativo'),
(17, '27610', 'Afirmativo'), (17, '27678', 'Afirmativo'),       (17, 'EXP-1420-D-2025', 'Afirmativo'),
(17, 'EXP-2810-D-2024', 'Afirmativo'), (17, '27614', 'Afirmativo'), (17, 'EXP-0915-D-2024', 'Afirmativo'),
(17, '27555', 'Abstencion'), (17, '27718', 'Afirmativo'),       (17, 'EXP-3105-D-2024', 'Negativo'),

-- 18. Juan Manuel López (Coalición Cívica - Republicano centrista, alto Salud/Educacion, moderado MercadoLibre)
(18, '27742', 'Afirmativo'), (18, 'DNU-70-2023', 'Negativo'),   (18, '27743', 'Abstencion'),
(18, '27642', 'Afirmativo'), (18, '27551', 'Afirmativo'),       (18, '27605', 'Negativo'),
(18, '27610', 'Abstencion'), (18, '27678', 'Afirmativo'),       (18, 'EXP-1420-D-2025', 'Afirmativo'),
(18, 'EXP-2810-D-2024', 'Afirmativo'), (18, '27614', 'Afirmativo'), (18, 'EXP-0915-D-2024', 'Afirmativo'),
(18, '27555', 'Afirmativo'), (18, '27718', 'Afirmativo'),       (18, 'EXP-3105-D-2024', 'Negativo'),

-- 19. Myriam Bregman (Frente de Izquierda - Izquierda trotskista, abstención en leyes con matices patronales, voto afirmativo en derechos laborales/salud/educación)
(19, '27742', 'Negativo'),   (19, 'DNU-70-2023', 'Negativo'),   (19, '27743', 'Negativo'),
(19, '27642', 'Afirmativo'), (19, '27551', 'Abstencion'),       (19, '27605', 'Abstencion'),
(19, '27610', 'Afirmativo'), (19, '27678', 'Afirmativo'),       (19, 'EXP-1420-D-2025', 'Afirmativo'),
(19, 'EXP-2810-D-2024', 'Afirmativo'), (19, '27614', 'Afirmativo'), (19, 'EXP-0915-D-2024', 'Negativo'),
(19, '27555', 'Afirmativo'), (19, '27718', 'Afirmativo'),       (19, 'EXP-3105-D-2024', 'Afirmativo'),

-- 20. Carlos Omar Arce (Innovación Federal - Provincialismo misionero dialoguista)
(20, '27742', 'Afirmativo'), (20, 'DNU-70-2023', 'Abstencion'), (20, '27743', 'Afirmativo'),
(20, '27642', 'Afirmativo'), (20, '27551', 'Afirmativo'),       (20, '27605', 'Afirmativo'),
(20, '27610', 'Negativo'),   (20, '27678', 'Afirmativo'),       (20, 'EXP-1420-D-2025', 'Afirmativo'),
(20, 'EXP-2810-D-2024', 'Afirmativo'), (20, '27614', 'Afirmativo'), (20, 'EXP-0915-D-2024', 'Afirmativo'),
(20, '27555', 'Afirmativo'), (20, '27718', 'Afirmativo'),       (20, 'EXP-3105-D-2024', 'Abstencion');

-- ============================================================================
-- 6. REGISTRO INICIAL DE AUDITORÍA INMUTABLE (BOOTSTRAPPING)
-- ============================================================================
INSERT INTO audit_logs (event_type, actor_ip_hash, endpoint, payload_hash, severity, details) VALUES
('SCHEMA_AND_SEED_INIT',
 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'CLI://database/seed.sql',
 '8f434346648f6b96df89dda901c5176b10a6d83961dd3c1ac88b59b2dc327aa4',
 'INFO',
 JSON_OBJECT('stage', 1, 'legisladores', 20, 'indicadores', 7, 'ddjj', 120, 'leyes', 15, 'votos', 300));
