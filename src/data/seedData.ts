import {
  IndicadorEconomico,
  LeyParlamentaria,
  PreguntaMatch,
  SentidoVoto,
} from '../types/legisdata';

export const INDICADORES_ECONOMICOS_SEED: IndicadorEconomico[] = [
  { anio: 2019, ipc_interanual: 53.8, dolar_mep_cierre: 73.1 },
  { anio: 2020, ipc_interanual: 36.1, dolar_mep_cierre: 140.3 },
  { anio: 2021, ipc_interanual: 50.9, dolar_mep_cierre: 197.8 },
  { anio: 2022, ipc_interanual: 94.8, dolar_mep_cierre: 328.5 },
  { anio: 2023, ipc_interanual: 211.4, dolar_mep_cierre: 995.0 },
  { anio: 2024, ipc_interanual: 117.8, dolar_mep_cierre: 1165.0 },
  { anio: 2025, ipc_interanual: 32.4, dolar_mep_cierre: 1280.0 },
];

export interface LegisladorRawSeed {
  id: number;
  id_congreso: string;
  cuit: string;
  wiki_slug: string;
  nombre_completo: string;
  camara: 'Diputados' | 'Senadores';
  provincia: string;
  bloque_actual: string;
  estado: 'Activo' | 'Inactivo' | 'Mandato Cumplido' | 'Candidato';
  biografia: string;
  foto_url: string;
  asistencia_historica: number;
  lealtad_partidaria: number;
}

export const LEGISLADORES_RAW_SEED: LegisladorRawSeed[] = [
  {
    id: 1,
    id_congreso: 'HCDN-1001',
    cuit: '20-21458912-3',
    wiki_slug: 'Martin_Menem',
    nombre_completo: 'Martín Alexis Menem',
    camara: 'Diputados',
    provincia: 'La Rioja',
    bloque_actual: 'La Libertad Avanza',
    estado: 'Activo',
    biografia:
      'Abogado y empresario argentino graduado en la Universidad de Belgrano. Diputado Nacional por la provincia de La Rioja y Presidente de la Honorable Cámara de Diputados de la Nación desde diciembre de 2023. Impulsor de las reformas de desregulación económica y equilibrio fiscal.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/3/35/MenemArmenia_%28cropped%29.jpg',
    asistencia_historica: 98.4,
    lealtad_partidaria: 99.1,
  },
  {
    id: 2,
    id_congreso: 'HCDN-1002',
    cuit: '20-14785236-9',
    wiki_slug: 'Jose_Luis_Espert',
    nombre_completo: 'José Luis Espert',
    camara: 'Diputados',
    provincia: 'Buenos Aires',
    bloque_actual: 'La Libertad Avanza',
    estado: 'Activo',
    biografia:
      'Doctor en Economía (UCEMA), economista y docente universitario. Presidente de la Comisión de Presupuesto y Hacienda de la Cámara de Diputados. Especializado en comercio internacional, reforma tributaria y apertura de mercados.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3c/Jos%C3%A9_Luis_Espert_2021.jpg/400px-Jos%C3%A9_Luis_Espert_2021.jpg',
    asistencia_historica: 95.2,
    lealtad_partidaria: 96.5,
  },
  {
    id: 3,
    id_congreso: 'HCDN-1003',
    cuit: '20-23654789-4',
    wiki_slug: 'Gabriel_Bornoroni',
    nombre_completo: 'Gabriel Bornoroni',
    camara: 'Diputados',
    provincia: 'Córdoba',
    bloque_actual: 'La Libertad Avanza',
    estado: 'Activo',
    biografia:
      'Abogado y empresario estacionero cordobés. Presidente del bloque de La Libertad Avanza en la Cámara de Diputados de la Nación. Coordinador parlamentario de los acuerdos interbloques para la sanción de la Ley de Bases.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png',
    asistencia_historica: 96.8,
    lealtad_partidaria: 98.7,
  },
  {
    id: 4,
    id_congreso: 'HSN-2001',
    cuit: '20-28963147-8',
    wiki_slug: 'Ezequiel_Atauche',
    nombre_completo: 'Ezequiel Atauche',
    camara: 'Senadores',
    provincia: 'Jujuy',
    bloque_actual: 'La Libertad Avanza',
    estado: 'Activo',
    biografia:
      'Empresario e ingeniero industrial jujeño. Senador Nacional por la provincia de Jujuy y Presidente del bloque de La Libertad Avanza y de la Comisión de Presupuesto y Hacienda en el Honorable Senado de la Nación.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png',
    asistencia_historica: 97.1,
    lealtad_partidaria: 98.9,
  },
  {
    id: 5,
    id_congreso: 'HCDN-1004',
    cuit: '20-18236541-7',
    wiki_slug: 'Cristian_Ritondo',
    nombre_completo: 'Cristian Adrián Ritondo',
    camara: 'Diputados',
    provincia: 'Buenos Aires',
    bloque_actual: 'PRO',
    estado: 'Activo',
    biografia:
      'Licenciado en Ciencia Política y dirigente del PRO. Presidente del bloque PRO en la Cámara de Diputados de la Nación. Fue Ministro de Seguridad de la Provincia de Buenos Aires (2015-2019) y Vicepresidente primero de la Legislatura porteña.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/2/25/Cristian_Ritondo_2019.jpg/400px-Cristian_Ritondo_2019.jpg',
    asistencia_historica: 92.6,
    lealtad_partidaria: 94.3,
  },
  {
    id: 6,
    id_congreso: 'HCDN-1005',
    cuit: '27-26147852-4',
    wiki_slug: 'Silvia_Lospennato',
    nombre_completo: 'Silvia Gabriela Lospennato',
    camara: 'Diputados',
    provincia: 'Buenos Aires',
    bloque_actual: 'PRO',
    estado: 'Activo',
    biografia:
      'Licenciada en Ciencia Política (UBA) con posgrado en Economía Ambiental. Diputada Nacional por la Provincia de Buenos Aires e integrante de las comisiones de Presupuesto, Legislación General y Asuntos Constitucionales.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9e/Silvia_Lospennato_2018.jpg/400px-Silvia_Lospennato_2018.jpg',
    asistencia_historica: 96.5,
    lealtad_partidaria: 91.8,
  },
  {
    id: 7,
    id_congreso: 'HCDN-1006',
    cuit: '27-23589632-1',
    wiki_slug: 'Maria_Eugenia_Vidal',
    nombre_completo: 'María Eugenia Vidal',
    camara: 'Diputados',
    provincia: 'Ciudad Autónoma de Buenos Aires',
    bloque_actual: 'PRO',
    estado: 'Activo',
    biografia:
      'Licenciada en Ciencia Política por la Universidad Católica Argentina. Diputada Nacional por la Ciudad Autónoma de Buenos Aires. Se desempeñó como Gobernadora de la Provincia de Buenos Aires entre 2015 y 2019.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7c/Mar%C3%ADa_Eugenia_Vidal_en_2019.jpg/400px-Mar%C3%ADa_Eugenia_Vidal_en_2019.jpg',
    asistencia_historica: 91.4,
    lealtad_partidaria: 93.2,
  },
  {
    id: 8,
    id_congreso: 'HSN-2002',
    cuit: '27-24123698-5',
    wiki_slug: 'Guadalupe_Tagliaferri',
    nombre_completo: 'Guadalupe Tagliaferri',
    camara: 'Senadores',
    provincia: 'Ciudad Autónoma de Buenos Aires',
    bloque_actual: 'PRO',
    estado: 'Activo',
    biografia:
      'Licenciada en Ciencia Política (UBA). Senadora Nacional por la Ciudad Autónoma de Buenos Aires y presidenta de la Comisión de Acuerdos del Senado. Ex Ministra de Desarrollo Humano y Hábitat porteña.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4e/Guadalupe_Tagliaferri.jpg/400px-Guadalupe_Tagliaferri.jpg',
    asistencia_historica: 95.8,
    lealtad_partidaria: 86.4,
  },
  {
    id: 9,
    id_congreso: 'HCDN-1007',
    cuit: '20-27852147-3',
    wiki_slug: 'Rodrigo_de_Loredo',
    nombre_completo: 'Rodrigo de Loredo',
    camara: 'Diputados',
    provincia: 'Córdoba',
    bloque_actual: 'UCR',
    estado: 'Activo',
    biografia:
      'Abogado graduado en la Universidad Nacional de Córdoba con maestría en Derecho Empresario. Presidente del bloque de la Unión Cívica Radical en la Cámara de Diputados de la Nación.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/5/59/Rodrigo_de_Loredo.jpg/400px-Rodrigo_de_Loredo.jpg',
    asistencia_historica: 93.8,
    lealtad_partidaria: 89.5,
  },
  {
    id: 10,
    id_congreso: 'HCDN-1008',
    cuit: '27-22369852-9',
    wiki_slug: 'Karina_Banfi',
    nombre_completo: 'Karina Banfi',
    camara: 'Diputados',
    provincia: 'Buenos Aires',
    bloque_actual: 'UCR',
    estado: 'Activo',
    biografia:
      'Abogada egresada de la UBA y especialista en políticas de transparencia, acceso a la información pública y derechos humanos. Vicepresidenta del bloque de la Unión Cívica Radical en Diputados.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/1/18/Karina_Banfi.jpg/400px-Karina_Banfi.jpg',
    asistencia_historica: 97.4,
    lealtad_partidaria: 91.2,
  },
  {
    id: 11,
    id_congreso: 'HSN-2003',
    cuit: '20-21789456-2',
    wiki_slug: 'Martin_Lousteau',
    nombre_completo: 'Martín Lousteau',
    camara: 'Senadores',
    provincia: 'Ciudad Autónoma de Buenos Aires',
    bloque_actual: 'UCR',
    estado: 'Activo',
    biografia:
      'Licenciado en Economía (UdeSA) y Magister en Ciencias Económicas por la London School of Economics. Senador Nacional por CABA y Presidente del Comité Nacional de la Unión Cívica Radical.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/0/07/Mart%C3%ADn_Lousteau_2020.jpg/400px-Mart%C3%ADn_Lousteau_2020.jpg',
    asistencia_historica: 94.1,
    lealtad_partidaria: 82.6,
  },
  {
    id: 12,
    id_congreso: 'HCDN-1009',
    cuit: '20-24563214-5',
    wiki_slug: 'German_Martinez',
    nombre_completo: 'Germán Pedro Martínez',
    camara: 'Diputados',
    provincia: 'Santa Fe',
    bloque_actual: 'Unión por la Patria',
    estado: 'Activo',
    biografia:
      'Licenciado en Ciencia Política por la Universidad Nacional de Rosario. Diputado Nacional por la provincia de Santa Fe y Presidente del bloque Unión por la Patria en la Cámara de Diputados de la Nación.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c8/Germ%C3%A1n_Mart%C3%ADnez_Diputado.jpg/400px-Germ%C3%A1n_Mart%C3%ADnez_Diputado.jpg',
    asistencia_historica: 97.9,
    lealtad_partidaria: 98.4,
  },
  {
    id: 13,
    id_congreso: 'HCDN-1010',
    cuit: '20-25147896-1',
    wiki_slug: 'Leandro_Santoro',
    nombre_completo: 'Leandro Santoro',
    camara: 'Diputados',
    provincia: 'Ciudad Autónoma de Buenos Aires',
    bloque_actual: 'Unión por la Patria',
    estado: 'Activo',
    biografia:
      'Politólogo graduado en la Universidad de Buenos Aires y docente universitario. Diputado Nacional por la Ciudad Autónoma de Buenos Aires e integrante de las comisiones de Presupuesto y Relaciones Exteriores.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/Leandro_Santoro_2021.jpg/400px-Leandro_Santoro_2021.jpg',
    asistencia_historica: 94.6,
    lealtad_partidaria: 95.8,
  },
  {
    id: 14,
    id_congreso: 'HCDN-1011',
    cuit: '27-23145698-7',
    wiki_slug: 'Victoria_Tolosa_Paz',
    nombre_completo: 'Victoria Tolosa Paz',
    camara: 'Diputados',
    provincia: 'Buenos Aires',
    bloque_actual: 'Unión por la Patria',
    estado: 'Activo',
    biografia:
      'Contadora Pública Nacional egresada de la Universidad Católica de La Plata. Diputada Nacional por la Provincia de Buenos Aires y ex Ministra de Desarrollo Social de la Nación (2022-2023).',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/8/87/Victoria_Tolosa_Paz_2021.jpg/400px-Victoria_Tolosa_Paz_2021.jpg',
    asistencia_historica: 93.2,
    lealtad_partidaria: 96.9,
  },
  {
    id: 15,
    id_congreso: 'HSN-2004',
    cuit: '20-12456987-0',
    wiki_slug: 'Jose_Mayans',
    nombre_completo: 'José Miguel Ángel Mayans',
    camara: 'Senadores',
    provincia: 'Formosa',
    bloque_actual: 'Unión por la Patria',
    estado: 'Activo',
    biografia:
      'Senador Nacional por la provincia de Formosa desde 2001 y Presidente del interbloque de Unión por la Patria en el Honorable Senado de la Nación. Referente parlamentario del peronismo federal.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/6/62/Jos%C3%A9_Mayans_2018.jpg/400px-Jos%C3%A9_Mayans_2018.jpg',
    asistencia_historica: 96.2,
    lealtad_partidaria: 97.5,
  },
  {
    id: 16,
    id_congreso: 'HSN-2005',
    cuit: '27-21852963-3',
    wiki_slug: 'Juliana_Di_Tullio',
    nombre_completo: 'Juliana Di Tullio',
    camara: 'Senadores',
    provincia: 'Buenos Aires',
    bloque_actual: 'Unión por la Patria',
    estado: 'Activo',
    biografia:
      'Psicóloga social y dirigente política bonaerense. Senadora Nacional por la Provincia de Buenos Aires y presidenta del bloque Unidad Ciudadana / Unión por la Patria en la Cámara Alta.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b2/Juliana_Di_Tullio_2021.jpg/400px-Juliana_Di_Tullio_2021.jpg',
    asistencia_historica: 95.4,
    lealtad_partidaria: 98.8,
  },
  {
    id: 17,
    id_congreso: 'HCDN-1012',
    cuit: '20-08456321-6',
    wiki_slug: 'Miguel_Angel_Pichetto',
    nombre_completo: 'Miguel Ángel Pichetto',
    camara: 'Diputados',
    provincia: 'Buenos Aires',
    bloque_actual: 'Encuentro Federal',
    estado: 'Activo',
    biografia:
      'Abogado graduado en la Universidad Nacional de La Plata. Presidente del bloque Encuentro Federal en la Cámara de Diputados. Fue Senador Nacional durante tres mandatos e integrante de la Auditoría General de la Nación.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/4/44/Miguel_%C3%81ngel_Pichetto_2019.jpg/400px-Miguel_%C3%81ngel_Pichetto_2019.jpg',
    asistencia_historica: 94.9,
    lealtad_partidaria: 88.2,
  },
  {
    id: 18,
    id_congreso: 'HCDN-1013',
    cuit: '20-29654123-8',
    wiki_slug: 'Juan_Manuel_Lopez',
    nombre_completo: 'Juan Manuel López',
    camara: 'Diputados',
    provincia: 'Buenos Aires',
    bloque_actual: 'Coalición Cívica',
    estado: 'Activo',
    biografia:
      'Abogado constitucionalista egresado de la Universidad de Buenos Aires. Presidente del bloque de la Coalición Cívica ARI en la Cámara de Diputados de la Nación, especializado en institucionalidad y control republicano.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d3/Juan_Manuel_L%C3%B3pez_diputado.jpg/400px-Juan_Manuel_L%C3%B3pez_diputado.jpg',
    asistencia_historica: 98.1,
    lealtad_partidaria: 94.7,
  },
  {
    id: 19,
    id_congreso: 'HCDN-1014',
    cuit: '27-22741852-6',
    wiki_slug: 'Myriam_Bregman',
    nombre_completo: 'Myriam Teresa Bregman',
    camara: 'Diputados',
    provincia: 'Ciudad Autónoma de Buenos Aires',
    bloque_actual: 'Frente de Izquierda',
    estado: 'Activo',
    biografia:
      'Abogada laboralista y de derechos humanos egresada de la UBA. Referente parlamentaria del Frente de Izquierda y de Trabajadores - Unidad (PTS) por la Ciudad Autónoma de Buenos Aires.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9f/Myriam_Bregman_2021.jpg/400px-Myriam_Bregman_2021.jpg',
    asistencia_historica: 93.5,
    lealtad_partidaria: 99.4,
  },
  {
    id: 20,
    id_congreso: 'HSN-2006',
    cuit: '20-16985214-2',
    wiki_slug: 'Carlos_Arce',
    nombre_completo: 'Carlos Omar Arce',
    camara: 'Senadores',
    provincia: 'Misiones',
    bloque_actual: 'Innovación Federal',
    estado: 'Activo',
    biografia:
      'Médico tocoginecólogo y político misionero. Senador Nacional por la provincia de Misiones en representación del Frente Renovador de la Concordia (Innovación Federal) y ex Vicegobernador provincial.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png',
    asistencia_historica: 94.3,
    lealtad_partidaria: 87.9,
  },
];

export const PATRIMONIO_NOMINAL_SEED: Record<number, Record<number, number>> = {
  1: { 2020: 48500000, 2021: 72400000, 2022: 134800000, 2023: 445000000, 2024: 568000000, 2025: 652000000 },
  2: { 2020: 39200000, 2021: 58900000, 2022: 104500000, 2023: 338000000, 2024: 412000000, 2025: 436000000 },
  3: { 2020: 54000000, 2021: 83100000, 2022: 152000000, 2023: 498000000, 2024: 625000000, 2025: 681000000 },
  4: { 2020: 61500000, 2021: 94200000, 2022: 178000000, 2023: 585000000, 2024: 732000000, 2025: 810000000 },
  5: { 2020: 92000000, 2021: 148000000, 2022: 289000000, 2023: 985000000, 2024: 1210000000, 2025: 1328000000 },
  6: { 2020: 12800000, 2021: 18100000, 2022: 30900000, 2023: 94500000, 2024: 112000000, 2025: 121800000 },
  7: { 2020: 24500000, 2021: 35800000, 2022: 61200000, 2023: 192000000, 2024: 228000000, 2025: 249000000 },
  8: { 2020: 15400000, 2021: 21200000, 2022: 34800000, 2023: 104000000, 2024: 123000000, 2025: 136200000 },
  9: { 2020: 28900000, 2021: 42500000, 2022: 76400000, 2023: 241000000, 2024: 289000000, 2025: 314500000 },
  10: { 2020: 11200000, 2021: 15900000, 2022: 26500000, 2023: 81200000, 2024: 94800000, 2025: 103700000 },
  11: { 2020: 44600000, 2021: 66100000, 2022: 114000000, 2023: 358000000, 2024: 431000000, 2025: 472000000 },
  12: { 2020: 13500000, 2021: 20400000, 2022: 36900000, 2023: 121000000, 2024: 146000000, 2025: 158000000 },
  13: { 2020: 14800000, 2021: 21400000, 2022: 36200000, 2023: 111000000, 2024: 131500000, 2025: 143500000 },
  14: { 2020: 36400000, 2021: 59800000, 2022: 118500000, 2023: 412000000, 2024: 502000000, 2025: 545000000 },
  15: { 2020: 27500000, 2021: 46200000, 2022: 94800000, 2023: 336000000, 2024: 410000000, 2025: 447000000 },
  16: { 2020: 18900000, 2021: 29500000, 2022: 54200000, 2023: 179000000, 2024: 214000000, 2025: 231000000 },
  17: { 2020: 33800000, 2021: 50400000, 2022: 91200000, 2023: 289000000, 2024: 344000000, 2025: 374000000 },
  18: { 2020: 10600000, 2021: 14800000, 2022: 24500000, 2023: 73800000, 2024: 86200000, 2025: 95600000 },
  19: { 2020: 6800000, 2021: 9100000, 2022: 14600000, 2023: 42900000, 2024: 50100000, 2025: 56100000 },
  20: { 2020: 21500000, 2021: 31200000, 2022: 55100000, 2023: 174000000, 2024: 206000000, 2025: 223800000 },
};

export const LEYES_SEED: LeyParlamentaria[] = [
  {
    id: '27742',
    titulo: 'Ley de Bases y Puntos de Partida para la Libertad de los Argentinos',
    resumen:
      'Establece la emergencia pública administrativa y económica, la reforma del Estado, privatizaciones de empresas públicas y el Régimen de Incentivo para Grandes Inversiones (RIGI).',
    fecha_votacion: '2024-06-28',
    eje_tematico: 'MercadoLibre',
    peso_ideologico: 1.0,
  },
  {
    id: 'DNU-70-2023',
    titulo: 'DNU 70/2023 — Bases para la Reconstrucción de la Economía Argentina',
    resumen:
      'Desregulación integral del comercio interior y exterior, derogación de la Ley de Alquileres, Ley de Góndolas y Ley de Abastecimiento, y liberación de precios y contratos en moneda extranjera.',
    fecha_votacion: '2024-03-14',
    eje_tematico: 'MercadoLibre',
    peso_ideologico: 0.95,
  },
  {
    id: '27743',
    titulo: 'Medidas Fiscales Paliativas y Relevantes (Paquete Fiscal)',
    resumen:
      'Régimen de Regularización de Activos (blanqueo de capitales), reducción progresiva del Impuesto sobre los Bienes Personales, moratoria fiscal y restitución de la cuarta categoría de Ganancias.',
    fecha_votacion: '2024-06-28',
    eje_tematico: 'MercadoLibre',
    peso_ideologico: 0.85,
  },
  {
    id: '27642',
    titulo: 'Ley de Promoción de la Alimentación Saludable (Etiquetado Frontal)',
    resumen:
      'Obliga a incorporar sellos de advertencia octogonales negros en alimentos y bebidas envasadas con exceso de azúcares, sodio, grasas saturadas y calorías, regulando su publicidad y comercialización.',
    fecha_votacion: '2021-10-26',
    eje_tematico: 'Proteccionismo',
    peso_ideologico: 0.75,
  },
  {
    id: '27551',
    titulo: 'Ley de Alquileres — Reforma del Régimen de Locaciones Urbanas',
    resumen:
      'Extiende el plazo mínimo legal de los contratos de locación habitacional a tres años y establece un índice oficial único de actualización anual combinando IPC y RIPTE.',
    fecha_votacion: '2020-06-11',
    eje_tematico: 'Proteccionismo',
    peso_ideologico: 0.85,
  },
  {
    id: '27605',
    titulo: 'Aporte Solidario y Extraordinario para Morigerar los Efectos de la Pandemia',
    resumen:
      'Crea un tributo extraordinario sobre los patrimonios de personas humanas superiores a los 200 millones de pesos con destino a equipamiento sanitario, subsidios a PyMEs y urbanización de barrios populares.',
    fecha_votacion: '2020-12-04',
    eje_tematico: 'Proteccionismo',
    peso_ideologico: 0.95,
  },
  {
    id: '27610',
    titulo: 'Ley de Acceso a la Interrupción Voluntaria del Embarazo (IVE)',
    resumen:
      'Regula el acceso a la interrupción voluntaria del embarazo y a la atención posaborto en todo el sistema de salud público, privado y de obras sociales hasta la semana catorce de gestación.',
    fecha_votacion: '2020-12-30',
    eje_tematico: 'Salud',
    peso_ideologico: 0.9,
  },
  {
    id: '27678',
    titulo: 'Ley Nacional de Cuidados Paliativos Integrales',
    resumen:
      'Asegura el acceso de los pacientes a las prestaciones integrales sobre cuidados paliativos en el ámbito público, privado y de la seguridad social, incluyendo el acompañamiento familiar.',
    fecha_votacion: '2022-07-05',
    eje_tematico: 'Salud',
    peso_ideologico: 0.8,
  },
  {
    id: 'EXP-1420-D-2025',
    titulo: 'Emergencia Sanitaria Pediátrica y Financiamiento del Hospital Garrahan',
    resumen:
      'Declara la emergencia sanitaria de la salud pediátrica y de las residencias médicas nacionales por dos años, garantizando recomposición presupuestaria y salarial de carácter intangible.',
    fecha_votacion: '2025-05-14',
    eje_tematico: 'Salud',
    peso_ideologico: 0.95,
  },
  {
    id: 'EXP-2810-D-2024',
    titulo: 'Ley de Financiamiento de las Universidades Nacionales',
    resumen:
      'Establece la actualización bimestral automática por inflación (IPC INDEC) de los gastos de funcionamiento de las universidades públicas nacionales y la recomposición salarial docente y no docente.',
    fecha_votacion: '2024-09-12',
    eje_tematico: 'Educacion',
    peso_ideologico: 1.0,
  },
  {
    id: '27614',
    titulo: 'Ley de Financiamiento del Sistema Nacional de Ciencia, Tecnología e Innovación',
    resumen:
      'Establece un cronograma de incremento progresivo y sostenido de la inversión pública nacional en ciencia y tecnología hasta alcanzar el 1% del Producto Bruto Interno (PBI) en 2032.',
    fecha_votacion: '2021-02-24',
    eje_tematico: 'Educacion',
    peso_ideologico: 0.85,
  },
  {
    id: 'EXP-0915-D-2024',
    titulo: 'Declaración de la Educación Obligatoria como Servicio Estratégico Esencial',
    resumen:
      'Garantiza el cumplimiento de los 180 días mínimos de clase estableciendo guardias educativas mínimas del 30% al 50% del personal escolar durante medidas de fuerza.',
    fecha_votacion: '2024-08-15',
    eje_tematico: 'Educacion',
    peso_ideologico: 0.8,
  },
  {
    id: '27555',
    titulo: 'Régimen Legal del Contrato de Teletrabajo',
    resumen:
      'Regula los presupuestos legales mínimos para la modalidad de teletrabajo, consagrando el derecho a la desconexión digital, la reversibilidad contractual y la provisión de equipamiento por el empleador.',
    fecha_votacion: '2020-07-30',
    eje_tematico: 'Laboral',
    peso_ideologico: 0.85,
  },
  {
    id: '27718',
    titulo: 'Exención del Impuesto a las Ganancias para Guardias Médicas y Horas Extras',
    resumen:
      'Exime del pago del Impuesto a las Ganancias a las remuneraciones percibidas en concepto de guardias obligatorias —activas o pasivas— y horas extras por profesionales y técnicos de la salud.',
    fecha_votacion: '2023-05-04',
    eje_tematico: 'Laboral',
    peso_ideologico: 0.75,
  },
  {
    id: 'EXP-3105-D-2024',
    titulo: 'Régimen de Tutela Sindical y Recálculo de Indemnizaciones por Despido',
    resumen:
      'Proyecto de fortalecimiento de las indemnizaciones agravadas por trabajo no registrado y actualización de créditos laborales por IPC más tasa pura del 6% anual.',
    fecha_votacion: '2024-04-30',
    eje_tematico: 'Laboral',
    peso_ideologico: 0.95,
  },
];

const LEY_ORDER = [
  '27742',
  'DNU-70-2023',
  '27743',
  '27642',
  '27551',
  '27605',
  '27610',
  '27678',
  'EXP-1420-D-2025',
  'EXP-2810-D-2024',
  '27614',
  'EXP-0915-D-2024',
  '27555',
  '27718',
  'EXP-3105-D-2024',
];

const VOTOS_MATRIX: Record<number, SentidoVoto[]> = {
  1: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  2: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  3: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Negativo'],
  4: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  5: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Negativo', 'Afirmativo', 'Abstencion', 'Negativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  6: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  7: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Ausente', 'Negativo', 'Negativo', 'Afirmativo', 'Abstencion', 'Negativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  8: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  9: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  10: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion'],
  11: ['Abstencion', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion'],
  12: ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
  13: ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
  14: ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
  15: ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
  16: ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
  17: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Abstencion', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  18: ['Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo'],
  19: ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Abstencion', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
  20: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion'],
};

export function obtenerVotosSeedPorLegislador(legisladorId: number): Record<string, SentidoVoto> {
  const arr = VOTOS_MATRIX[legisladorId] || [];
  const map: Record<string, SentidoVoto> = {};
  LEY_ORDER.forEach((leyId, idx) => {
    map[leyId] = arr[idx] ?? 'Abstencion';
  });
  return map;
}

export const PREGUNTAS_MATCH_ELECTORAL: PreguntaMatch[] = [
  {
    id: 1,
    eje: 'MercadoLibre',
    ejeLabel: 'Economía / Libre Mercado',
    expedienteRef: 'Ley 27.742 — Ley de Bases y RIGI',
    pregunta:
      '¿El Estado debe privatizar empresas públicas deficitarias y otorgar estabilidad fiscal por 30 años a grandes inversiones privadas (RIGI)?',
    contexto:
      'Debate parlamentario sobre el rol empresarial del Estado, concesión de infraestructura y atracción de capitales en energía, minería y agroindustria.',
    opciones: [
      {
        id: 'a',
        label: 'A favor: Privatizar y desregular para atraer inversión',
        descripcion: 'El sector privado asigna recursos con mayor eficiencia y sin déficit fiscal.',
        valorEje: 100,
      },
      {
        id: 'b',
        label: 'Postura intermedia: Concesiones mixtas y RIGI acotado a sectores estratégicos',
        descripcion: 'Exigir encadenamientos productivos con PyMEs locales y auditoría parlamentaria.',
        valorEje: 50,
      },
      {
        id: 'c',
        label: 'En contra: Mantener empresas estatales estratégicas y régimen tributario general',
        descripcion: 'Evitar beneficios impositivos extraordinarios que asimetrían la industria local.',
        valorEje: 5,
      },
    ],
  },
  {
    id: 2,
    eje: 'MercadoLibre',
    ejeLabel: 'Economía / Libre Mercado',
    expedienteRef: 'DNU 70/2023 — Desregulación Económica',
    pregunta:
      '¿Deben eliminarse los controles de precios, cupos de exportación y regulaciones en contratos de alquiler y comercio interior?',
    contexto:
      'Discusión sobre libertad contractual entre partes en pesos o moneda extranjera frente a marcos regulatorios del Estado.',
    opciones: [
      {
        id: 'a',
        label: 'A favor: Libertad total de precios, contratos y comercio exterior',
        descripcion: 'Derogar leyes de Alquileres, Góndolas y Abastecimiento para ampliar la oferta.',
        valorEje: 100,
      },
      {
        id: 'b',
        label: 'Postura intermedia: Desregular comercio pero con defensa de la competencia',
        descripcion: 'Liberar mercados evitando abusos de posición dominante o cartelización.',
        valorEje: 55,
      },
      {
        id: 'c',
        label: 'En contra: El Estado debe regular alquileres y precios de la canasta básica',
        descripcion: 'Proteger a inquilinos y consumidores frente a shocks inflacionarios.',
        valorEje: 0,
      },
    ],
  },
  {
    id: 3,
    eje: 'Educacion',
    ejeLabel: 'Educación / Ciencia',
    expedienteRef: 'EXP-2810-D-2024 — Financiamiento Universitario',
    pregunta:
      '¿Debe garantizarse por ley la actualización bimestral automática por inflación (IPC) del presupuesto de las Universidades Nacionales?',
    contexto:
      'Tensión entre la intangibilidad presupuestaria de la educación superior pública y la meta de superávit fiscal primario.',
    opciones: [
      {
        id: 'a',
        label: 'A favor: Indexación automática por inflación de salarios y gastos universitarios',
        descripcion: 'La universidad pública y la movilidad social ascendente son prioritarias.',
        valorEje: 100,
      },
      {
        id: 'b',
        label: 'Postura intermedia: Actualización atada a auditorías externas de la AGN',
        descripcion: 'Sostener el financiamiento real exigiendo métricas de egreso y transparencia.',
        valorEje: 65,
      },
      {
        id: 'c',
        label: 'En contra: Todo aumento debe supeditarse al equilibrio de las cuentas públicas',
        descripcion: 'Rechazar gastos indexados que no cuenten con partida de financiamiento explícita.',
        valorEje: 10,
      },
    ],
  },
  {
    id: 4,
    eje: 'Educacion',
    ejeLabel: 'Educación / Ciencia',
    expedienteRef: 'Ley 27.614 — Sistema Nacional de Ciencia y Tecnología',
    pregunta:
      '¿Qué política debe adoptar el Congreso respecto al financiamiento estatal del CONICET y la inversión pública en Ciencia y Tecnología?',
    contexto:
      'La Ley 27.614 fijó una meta gradual hasta alcanzar el 1% del PBI en inversión científico-tecnológica.',
    opciones: [
      {
        id: 'a',
        label: 'A favor: Blindar el cronograma creciente de inversión estatal en I+D hasta el 1% del PBI',
        descripcion: 'El desarrollo soberano requiere financiamiento público sostenido en ciencia básica y aplicada.',
        valorEje: 100,
      },
      {
        id: 'b',
        label: 'Postura intermedia: Cofinanciamiento público-privado orientado a sectores productivos',
        descripcion: 'Priorizar becas y proyectos vinculados a biotecnología, energía, agro y economía del conocimiento.',
        valorEje: 60,
      },
      {
        id: 'c',
        label: 'En contra: Reducir estructuras estatales y migrar a financiamiento privado competitivo',
        descripcion: 'Priorizar la reducción del gasto público corriente y aranceles a la importación tecnológica.',
        valorEje: 10,
      },
    ],
  },
  {
    id: 5,
    eje: 'Salud',
    ejeLabel: 'Salud Pública',
    expedienteRef: 'EXP-1420-D-2025 — Emergencia Sanitaria Pediátrica',
    pregunta:
      '¿El Congreso debe declarar la emergencia sanitaria y asegurar fondos intangibles para hospitales nacionales de alta complejidad (como el Garrahan)?',
    contexto:
      'Debate legislativo sobre recomposición salarial de residencias médicas e insumos hospitalarios críticos.',
    opciones: [
      {
        id: 'a',
        label: 'A favor: Garantizar presupuesto intangible y exenciones impositivas a guardias médicas',
        descripcion: 'La salud pública de alta complejidad constituye una red federal insustituible.',
        valorEje: 100,
      },
      {
        id: 'b',
        label: 'Postura intermedia: Reforzar partidas críticas descentralizando la gestión en las provincias',
        descripcion: 'Focalizar recursos nacionales en patologías complejas y recupero de costos a obras sociales.',
        valorEje: 60,
      },
      {
        id: 'c',
        label: 'En contra: La salud debe ser financiada por cada jurisdicción provincial sin gasto extra nacional',
        descripcion: 'Evitar leyes de emergencia que alteren las metas fiscales del Tesoro Nacional.',
        valorEje: 5,
      },
    ],
  },
  {
    id: 6,
    eje: 'Salud',
    ejeLabel: 'Salud Pública',
    expedienteRef: 'Leyes 27.610 y 27.678 — Cobertura Sanitaria Obligatoria',
    pregunta:
      '¿El Estado debe ampliar las prestaciones obligatorias gratuitas (PMO) en hospitales públicos, obras sociales y prepagas?',
    contexto:
      'Incluye leyes como Cuidados Paliativos Integrales, Salud Reproductiva (IVE) y provisión federal de medicamentos.',
    opciones: [
      {
        id: 'a',
        label: 'A favor: Cobertura universal y obligatoria en todo el sistema público y privado',
        descripcion: 'Garantizar equidad territorial en el acceso a tratamientos, medicamentos y derechos sanitarios.',
        valorEje: 100,
      },
      {
        id: 'b',
        label: 'Postura intermedia: Cobertura garantizada en cuidados paliativos y patologías severas con evaluación de impacto',
        descripcion: 'Equilibrar la ampliación de derechos con la sustentabilidad financiera del sistema de salud.',
        valorEje: 55,
      },
      {
        id: 'c',
        label: 'En contra: Desregular planes de salud y limitar las imposiciones al sector privado',
        descripcion: 'Permitir que cada afiliado contrate libremente las coberturas médicas de su preferencia.',
        valorEje: 5,
      },
    ],
  },
  {
    id: 7,
    eje: 'Proteccionismo',
    ejeLabel: 'Industria / Proteccionismo',
    expedienteRef: 'Ley 27.605 — Tributación sobre Grandes Patrimonios',
    pregunta:
      '¿Estás de acuerdo con aplicar tributos progresivos sobre grandes fortunas para financiar infraestructura social, salud y PyMEs?',
    contexto:
      'Debate tributario entre impuestos progresivos al patrimonio (Aporte Extraordinario / Bienes Personales) frente a reducciones impositivas a la oferta.',
    opciones: [
      {
        id: 'a',
        label: 'A favor: Mayor progresividad tributaria sobre altos patrimonios y rentas financieras',
        descripcion: 'Fortalecer la recaudación fiscal sobre sectores de mayor capacidad contributiva.',
        valorEje: 100,
      },
      {
        id: 'b',
        label: 'Postura intermedia: Simplificación tributaria sin impuestos extraordinarios retroactivos',
        descripcion: 'Sostener Bienes Personales moderado reduciendo impuestos distorsivos a la producción (IIBB/Cheque).',
        valorEje: 45,
      },
      {
        id: 'c',
        label: 'En contra: Reducir al mínimo Bienes Personales y eliminar tributos al capital',
        descripcion: 'Los impuestos patrimoniales desalientan el ahorro local y fomentan la salida de capitales.',
        valorEje: 0,
      },
    ],
  },
  {
    id: 8,
    eje: 'Proteccionismo',
    ejeLabel: 'Industria / Proteccionismo',
    expedienteRef: 'Leyes 27.642 y 27.551 — Regulación Estatal e Industria',
    pregunta:
      '¿Debe el Estado intervenir activamente regulando mercados internos (etiquetado comercial, alquileres, protección industrial)?',
    contexto:
      'Contraste entre el modelo de regulación estatal protectora del mercado interno y el modelo de apertura comercial sin trabas.',
    opciones: [
      {
        id: 'a',
        label: 'A favor: Estado regulador activo que proteja la industria nacional y al consumidor',
        descripcion: 'El mercado librado a sí mismo genera asimetrías que requieren normas de orden público.',
        valorEje: 100,
      },
      {
        id: 'b',
        label: 'Postura intermedia: Regulación en salud/consumidor pero agilidad comercial para empresas',
        descripcion: 'Mantener estándares de información al consumidor sin controles rígidos de precios ni plazos.',
        valorEje: 55,
      },
      {
        id: 'c',
        label: 'En contra: Apertura comercial plena y eliminación de regulaciones sobre productos y contratos',
        descripcion: 'La competencia internacional y la desregulación reducen costos para toda la economía.',
        valorEje: 0,
      },
    ],
  },
];

export const REGIONES_PROVINCIAS_ARGENTINA: {
  region: string;
  codigo: string;
  provincias: { nombre: string; abreviatura: string }[];
}[] = [
  {
    region: 'AMBA y Interior Bonaerense',
    codigo: 'AMBA',
    provincias: [
      { nombre: 'Ciudad Autónoma de Buenos Aires', abreviatura: 'CABA' },
      { nombre: 'Buenos Aires', abreviatura: 'PBA' },
    ],
  },
  {
    region: 'Región Centro',
    codigo: 'CENTRO',
    provincias: [
      { nombre: 'Córdoba', abreviatura: 'CBA' },
      { nombre: 'Santa Fe', abreviatura: 'SFE' },
      { nombre: 'Entre Ríos', abreviatura: 'ER' },
    ],
  },
  {
    region: 'Noroeste Argentino (NOA)',
    codigo: 'NOA',
    provincias: [
      { nombre: 'La Rioja', abreviatura: 'LR' },
      { nombre: 'Jujuy', abreviatura: 'JUJ' },
      { nombre: 'Tucumán', abreviatura: 'TUC' },
      { nombre: 'Salta', abreviatura: 'SAL' },
    ],
  },
  {
    region: 'Noreste Argentino (NEA)',
    codigo: 'NEA',
    provincias: [
      { nombre: 'Formosa', abreviatura: 'FOR' },
      { nombre: 'Misiones', abreviatura: 'MIS' },
      { nombre: 'Corrientes', abreviatura: 'COR' },
      { nombre: 'Chaco', abreviatura: 'CHA' },
    ],
  },
  {
    region: 'Cuyo y Patagonia',
    codigo: 'SUR_CUYO',
    provincias: [
      { nombre: 'Mendoza', abreviatura: 'MZA' },
      { nombre: 'Neuquén', abreviatura: 'NQN' },
      { nombre: 'Río Negro', abreviatura: 'RN' },
      { nombre: 'Chubut', abreviatura: 'CHU' },
    ],
  },
];
