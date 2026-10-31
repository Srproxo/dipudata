import LEGISLADORES_MASTER_DATA from './legisladoresMaster.json';
import {
  DesgloseDdjjOficial,
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
  slug?: string;
  nombre_completo: string;
  camara: 'Diputados' | 'Senadores';
  provincia: string;
  bloque_actual: string;
  estado: 'Activo' | 'Inactivo' | 'Mandato Cumplido' | 'Candidato';
  biografia: string;
  foto_url: string;
  asistencia_historica: number;
  lealtad_partidaria: number;
  mandatos: number;
  url_hcdn?: string;
  url_comisiones?: string;
}

export const LEGISLADORES_RAW_SEED: LegisladorRawSeed[] = LEGISLADORES_MASTER_DATA as unknown as LegisladorRawSeed[];

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
  21: { 2020: 32000000, 2021: 49000000, 2022: 95000000, 2023: 310000000, 2024: 385000000, 2025: 418000000 },
  22: { 2020: 28500000, 2021: 42000000, 2022: 78000000, 2023: 255000000, 2024: 315000000, 2025: 345000000 },
  23: { 2020: 128000000, 2021: 185000000, 2022: 325000000, 2023: 1015000000, 2024: 2222824501.34, 2025: 2798181083.71 },
  24: { 2020: 19500000, 2021: 29800000, 2022: 55000000, 2023: 182000000, 2024: 224000000, 2025: 245000000 },
  25: { 2020: 31000000, 2021: 47000000, 2022: 89000000, 2023: 295000000, 2024: 365000000, 2025: 395000000 },
  26: { 2020: 22000000, 2021: 33500000, 2022: 62000000, 2023: 205000000, 2024: 252000000, 2025: 275000000 },
  27: { 2020: 26500000, 2021: 40500000, 2022: 74000000, 2023: 245000000, 2024: 305000000, 2025: 332000000 },
  28: { 2020: 34000000, 2021: 52000000, 2022: 98000000, 2023: 320000000, 2024: 395000000, 2025: 430000000 },
  29: { 2020: 8500000, 2021: 13000000, 2022: 24500000, 2023: 79000000, 2024: 98000000, 2025: 108000000 },
  30: { 2020: 16500000, 2021: 25000000, 2022: 47000000, 2023: 155000000, 2024: 192000000, 2025: 210000000 },
  31: { 2020: 24000000, 2021: 36000000, 2022: 67000000, 2023: 218000000, 2024: 268000000, 2025: 295000000 },
  32: { 2020: 17800000, 2021: 27000000, 2022: 50500000, 2023: 165000000, 2024: 205000000, 2025: 225000000 },
  33: { 2020: 38000000, 2021: 58000000, 2022: 108000000, 2023: 355000000, 2024: 440000000, 2025: 480000000 },
  34: { 2020: 35000000, 2021: 53500000, 2022: 102000000, 2023: 338000000, 2024: 420000000, 2025: 458000000 },
  35: { 2020: 21000000, 2021: 32000000, 2022: 60000000, 2023: 198000000, 2024: 245000000, 2025: 268000000 },
  36: { 2020: 18500000, 2021: 28000000, 2022: 52000000, 2023: 172000000, 2024: 215000000, 2025: 236000000 },
  37: { 2020: 11500000, 2021: 17200000, 2022: 31000000, 2023: 98000000, 2024: 120000000, 2025: 132000000 },
  38: { 2020: 12800000, 2021: 19200000, 2022: 34500000, 2023: 110000000, 2024: 135000000, 2025: 148000000 },
  39: { 2020: 14500000, 2021: 22000000, 2022: 41000000, 2023: 135000000, 2024: 168000000, 2025: 184000000 },
  40: { 2020: 21000000, 2021: 31500000, 2022: 58000000, 2023: 190000000, 2024: 235000000, 2025: 258000000 },
  41: { 2020: 16000000, 2021: 24500000, 2022: 45000000, 2023: 148000000, 2024: 182000000, 2025: 198000000 },
  42: { 2020: 25000000, 2021: 37500000, 2022: 69000000, 2023: 228000000, 2024: 282000000, 2025: 310000000 },
  43: { 2020: 23000000, 2021: 35000000, 2022: 66000000, 2023: 215000000, 2024: 268000000, 2025: 295000000 },
  44: { 2020: 19000000, 2021: 29000000, 2022: 54000000, 2023: 178000000, 2024: 220000000, 2025: 242000000 },
  45: { 2020: 15500000, 2021: 23500000, 2022: 44000000, 2023: 145000000, 2024: 180000000, 2025: 198000000 },
  46: { 2020: 42000000, 2021: 64000000, 2022: 120000000, 2023: 390000000, 2024: 485000000, 2025: 530000000 },
  47: { 2020: 55000000, 2021: 84000000, 2022: 158000000, 2023: 520000000, 2024: 650000000, 2025: 710000000 },
  48: { 2020: 22500000, 2021: 34000000, 2022: 63000000, 2023: 208000000, 2024: 258000000, 2025: 284000000 },
  49: { 2020: 20500000, 2021: 31000000, 2022: 57500000, 2023: 188000000, 2024: 232000000, 2025: 255000000 },
  50: { 2020: 24500000, 2021: 36800000, 2022: 68500000, 2023: 225000000, 2024: 278000000, 2025: 305000000 },
  51: { 2020: 29500000, 2021: 44500000, 2022: 83000000, 2023: 272000000, 2024: 338000000, 2025: 370000000 },
  52: { 2020: 36000000, 2021: 54000000, 2022: 101000000, 2023: 332000000, 2024: 412000000, 2025: 450000000 },
  53: { 2020: 17000000, 2021: 25500000, 2022: 48000000, 2023: 158000000, 2024: 195000000, 2025: 215000000 },
  54: { 2020: 41000000, 2021: 62000000, 2022: 116000000, 2023: 380000000, 2024: 472000000, 2025: 518000000 },
  55: { 2020: 28000000, 2021: 42500000, 2022: 79500000, 2023: 260000000, 2024: 322000000, 2025: 352000000 },
  56: { 2020: 19500000, 2021: 29800000, 2022: 56000000, 2023: 184000000, 2024: 228000000, 2025: 250000000 },
  57: { 2020: 10500000, 2021: 16000000, 2022: 30000000, 2023: 99000000, 2024: 122000000, 2025: 135000000 },
  58: { 2020: 89000000, 2021: 135000000, 2022: 255000000, 2023: 840000000, 2024: 1050000000, 2025: 1150000000 },
  59: { 2020: 17500000, 2021: 26500000, 2022: 49500000, 2023: 162000000, 2024: 202000000, 2025: 222000000 },
  60: { 2020: 21500000, 2021: 32500000, 2022: 61000000, 2023: 200000000, 2024: 248000000, 2025: 272000000 },
  61: { 2020: 31000000, 2021: 47000000, 2022: 88000000, 2023: 290000000, 2024: 360000000, 2025: 395000000 },
  62: { 2020: 19000000, 2021: 29000000, 2022: 54000000, 2023: 178000000, 2024: 222000000, 2025: 244000000 },
  63: { 2020: 27500000, 2021: 41500000, 2022: 78000000, 2023: 256000000, 2024: 318000000, 2025: 348000000 },
  64: { 2020: 38000000, 2021: 58000000, 2022: 109000000, 2023: 358000000, 2024: 445000000, 2025: 488000000 },
  65: { 2020: 16500000, 2021: 25000000, 2022: 47000000, 2023: 154000000, 2024: 191000000, 2025: 210000000 },
  66: { 2020: 33000000, 2021: 50000000, 2022: 94000000, 2023: 310000000, 2024: 385000000, 2025: 420000000 },
  67: { 2020: 9500000, 2021: 14500000, 2022: 27500000, 2023: 90000000, 2024: 112000000, 2025: 124000000 },
  68: { 2020: 31500000, 2021: 47500000, 2022: 89000000, 2023: 292000000, 2024: 362000000, 2025: 395000000 },
  69: { 2020: 21000000, 2021: 32000000, 2022: 60000000, 2023: 198000000, 2024: 245000000, 2025: 268000000 },
  70: { 2020: 27000000, 2021: 41000000, 2022: 77000000, 2023: 252000000, 2024: 312000000, 2025: 342000000 },
  71: { 2020: 38500000, 2021: 58000000, 2022: 109000000, 2023: 360000000, 2024: 448000000, 2025: 490000000 },
  72: { 2020: 38000000, 2021: 58000000, 2022: 110000000, 2023: 360000000, 2024: 450000000, 2025: 495000000 },
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
  21: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  22: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  23: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  24: ['Abstencion', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Negativo'],
  25: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  26: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  27: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  28: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  29: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  30: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  31: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  32: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  33: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  34: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  35: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  36: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  37: ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
  38: ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
  39: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  40: ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
  41: ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
  42: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  43: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  44: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  45: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  46: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  47: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  48: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  49: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  50: ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
  51: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  52: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  53: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  54: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  55: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  56: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  57: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  58: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  59: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  60: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  61: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  62: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  63: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  64: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  65: ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
  66: ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
  67: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
  68: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  69: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  70: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
  71: ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
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

export const DESGLOSES_DDJJ_OFICIALES: Record<number, DesgloseDdjjOficial> = {
  23: {
    legislador_id: 23,
    periodo_fiscal: 2025,
    organismo: 'Oficina Anticorrupción (Poder Ejecutivo Nacional)',
    formulario: 'F. 817 — Declaración Jurada Patrimonial Integral',
    fecha_presentacion: '03 de agosto de 2026',
    total_bienes_pesos: 2798181083.71,
    total_deudas_pesos: 0.0,
    patrimonio_neto_pesos: 2798181083.71,
    patrimonio_neto_usd: 2186078.97,
    patrimonio_neto_anterior_pesos: 2222824501.34,
    variacion_nominal_pct: 25.88,
    variacion_nominal_monto: 575356582.37,
    resumen_ejecutivo:
      'La Senadora Nacional cerró 2025 con un patrimonio neto de $ 2.798,2 millones sin deudas registradas. Más de la mitad de su patrimonio ($ 1.418,7 millones) se concentra en participaciones en las sociedades CAPDEL S.A. (administradora de Nuevocentro Shopping) y Quinto Centenario S.A. (complejo hotelero), además de 14 inmuebles y tenencia de U$S 135.170 en efectivo.',
    items: [
      {
        categoria: 'Participaciones Societarias',
        titulo: 'CAPDEL S.A. (Nuevocentro Shopping) — 4,62% del capital',
        detalle:
          'Sociedad administradora de Nuevocentro Shopping en Córdoba Capital. Valuada al cierre en $ 884,9 millones (reducción contable de $ 140 M frente a los $ 1.024,9 M al inicio).',
        monto_pesos: 884900000,
        monto_usd: 691328,
        porcentaje_patrimonio: 31.62,
        variacion_anual_texto: '-13.7% contable (ajuste balance)',
      },
      {
        categoria: 'Participaciones Societarias',
        titulo: 'Quinto Centenario S.A. (Complejo Hotelero) — 10,86% del capital',
        detalle:
          'Sociedad administradora del Hotel Quinto Centenario (ex Sheraton Córdoba). Valuada en $ 533,8 millones (suba de $ 223,9 M frente a los $ 309,9 M al inicio del ejercicio).',
        monto_pesos: 533800000,
        monto_usd: 417031,
        porcentaje_patrimonio: 19.08,
        variacion_anual_texto: '+72.2% incremento contable',
      },
      {
        categoria: 'Inversiones y Créditos',
        titulo: 'Crédito a favor contra Quinto Centenario S.A.',
        detalle:
          'Acreencia financiera declarada a su favor contra la firma hotelera por $ 276,3 millones (al inicio era de $ 201,1 millones).',
        monto_pesos: 276300000,
        monto_usd: 215859,
        porcentaje_patrimonio: 9.87,
        variacion_anual_texto: '+$ 75,2 millones en 2025',
      },
      {
        categoria: 'Inmuebles',
        titulo: '14 Propiedades Inmuebles (Residencia, Deptos y Cocheras)',
        detalle:
          'Residencia de 400 m² en Zona Sur de Córdoba (83% titularidad, $ 298 M), departamento con cochera de 183 m² en Nueva Córdoba ($ 109,9 M) y 12 unidades en Barrio San Martín (alquileres, con 4 adquisiciones el 30/06 por $ 30,3 M). Las valuaciones consignadas son fiscales.',
        monto_pesos: 438200000,
        monto_usd: 342343,
        porcentaje_patrimonio: 15.66,
        variacion_anual_texto: '14 unidades a valuación fiscal',
      },
      {
        categoria: 'Moneda Extranjera',
        titulo: 'Tenencia en Dólares Estadounidenses (U$S 135.170,72)',
        detalle:
          'U$S 134.970,72 en efectivo (fuera del sistema bancario) valuados a tipo de cambio oficial en $ 195,2 millones, más U$S 200 en caja de ahorro. Redujo tenencia en U$S 15.000 respecto al inicio ($ 150.170 USD).',
        monto_pesos: 195200000,
        monto_usd: 135171,
        porcentaje_patrimonio: 6.98,
        variacion_anual_texto: '-U$S 15.000 en 2025',
      },
      {
        categoria: 'Inversiones y Créditos',
        titulo: 'Fondo Común FIMA Ahorro Plus Clase A y Crédito AFIP',
        detalle:
          'Tenencia en fondo común FIMA por $ 294,0 millones (desde $ 83,2 M al inicio) y crédito fiscal a favor contra AFIP por $ 41,6 millones.',
        monto_pesos: 335600000,
        monto_usd: 262187,
        porcentaje_patrimonio: 11.99,
        variacion_anual_texto: 'FIMA aumentó de $ 83,2M a $ 294M',
      },
      {
        categoria: 'Vehículos',
        titulo: 'Parque Automotor (4 vehículos)',
        detalle:
          'Incorporó un Toyota Yaris XLS+ modelo 2025 por $ 31,46 millones. Conserva Chevrolet Spin 2015 ($ 7,23 M), Citroën C3 2012 ($ 0 contable) y Suzuki Grand Vitara 2008 ($ 0 contable).',
        monto_pesos: 38690000,
        monto_usd: 30226,
        porcentaje_patrimonio: 1.38,
        variacion_anual_texto: 'Alta: Toyota Yaris XLS+ 2025',
      },
      {
        categoria: 'Deudas y Pasivos',
        titulo: 'Deudas Registradas: $ 0 (Sin pasivos informados)',
        detalle:
          'La legisladora no declaró deudas comerciales, bancarias ni fiscales al cierre del ejercicio. El patrimonio neto coincide íntegramente con el activo total declarado.',
        monto_pesos: 0,
        monto_usd: 0,
        porcentaje_patrimonio: 0.0,
        variacion_anual_texto: '0% endeudamiento (sin pasivos)',
      },
      {
        categoria: 'Ingresos Anuales',
        titulo: 'Ingresos declarados en Ganancias ($ 411,8 millones brutos)',
        detalle:
          'Rentas de capital: $ 259,7 M. Trabajo personal: $ 112,6 M. Alquileres: $ 44,6 M. Total neto de gastos: $ 402,1 M. Gastos personales declarados: $ 204,3 M. Ingresos no gravados: $ 79 M.',
        monto_pesos: 402100000,
        monto_usd: 314140,
        porcentaje_patrimonio: 0.0,
        variacion_anual_texto: 'Rentas de capital representan el 63%',
      },
    ],
    fuente_periodistica: {
      medio: 'La Voz del Interior',
      titulo:
        'Carmen Álvarez Rivero declaró un patrimonio de $ 2.798 millones: 14 inmuebles y más de U$S 135 mil',
      fecha: '06 de septiembre de 2026',
      url: 'https://www.lavoz.com.ar/politica/carmen-alvarez-rivero-declaro-patrimonio-2798-millones-14-inmuebles-us-135-mil_0_dPyDS5XFod.html',
    },
  },
};

export const TODAS_PROVINCIAS_ARGENTINA = [
  'Buenos Aires',
  'Catamarca',
  'Chaco',
  'Chubut',
  'Ciudad Autónoma de Buenos Aires',
  'Córdoba',
  'Corrientes',
  'Entre Ríos',
  'Formosa',
  'Jujuy',
  'La Pampa',
  'La Rioja',
  'Mendoza',
  'Misiones',
  'Neuquén',
  'Río Negro',
  'Salta',
  'San Juan',
  'San Luis',
  'Santa Cruz',
  'Santa Fe',
  'Santiago del Estero',
  'Tierra del Fuego',
  'Tucumán',
] as const;

export type ProvinciaArgentina = (typeof TODAS_PROVINCIAS_ARGENTINA)[number];

export const REGIONES_PROVINCIAS_ARGENTINA: {
  region: string;
  codigo: string;
  provincias: { nombre: string; abreviatura: string }[];
}[] = [
  {
    region: 'AMBA y Centro',
    codigo: 'CENTRO',
    provincias: [
      { nombre: 'Ciudad Autónoma de Buenos Aires', abreviatura: 'CABA' },
      { nombre: 'Buenos Aires', abreviatura: 'PBA' },
      { nombre: 'Córdoba', abreviatura: 'CBA' },
      { nombre: 'Santa Fe', abreviatura: 'SFE' },
      { nombre: 'Entre Ríos', abreviatura: 'ER' },
    ],
  },
  {
    region: 'Noroeste Argentino (NOA)',
    codigo: 'NOA',
    provincias: [
      { nombre: 'Catamarca', abreviatura: 'CAT' },
      { nombre: 'Jujuy', abreviatura: 'JUJ' },
      { nombre: 'La Rioja', abreviatura: 'LR' },
      { nombre: 'Salta', abreviatura: 'SAL' },
      { nombre: 'Santiago del Estero', abreviatura: 'SDE' },
      { nombre: 'Tucumán', abreviatura: 'TUC' },
    ],
  },
  {
    region: 'Noreste Argentino (NEA)',
    codigo: 'NEA',
    provincias: [
      { nombre: 'Chaco', abreviatura: 'CHA' },
      { nombre: 'Corrientes', abreviatura: 'COR' },
      { nombre: 'Formosa', abreviatura: 'FOR' },
      { nombre: 'Misiones', abreviatura: 'MIS' },
    ],
  },
  {
    region: 'Cuyo y Pampa',
    codigo: 'CUYO',
    provincias: [
      { nombre: 'La Pampa', abreviatura: 'LPA' },
      { nombre: 'Mendoza', abreviatura: 'MZA' },
      { nombre: 'San Juan', abreviatura: 'SJN' },
      { nombre: 'San Luis', abreviatura: 'SLU' },
    ],
  },
  {
    region: 'Patagonia',
    codigo: 'SUR',
    provincias: [
      { nombre: 'Chubut', abreviatura: 'CHU' },
      { nombre: 'Neuquén', abreviatura: 'NQN' },
      { nombre: 'Río Negro', abreviatura: 'RN' },
      { nombre: 'Santa Cruz', abreviatura: 'SCZ' },
      { nombre: 'Tierra del Fuego', abreviatura: 'TDF' },
    ],
  },
];
