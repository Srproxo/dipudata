export type CamaraLegislativa = 'Diputados' | 'Senadores' | 'Diputado' | 'Senador';

export type EjeTematico =
  | 'Salud'
  | 'Educacion'
  | 'MercadoLibre'
  | 'Proteccionismo'
  | 'Laboral';

export type SentidoVoto = 'Afirmativo' | 'Negativo' | 'Abstencion' | 'Ausente';

export interface IndicadorEconomico {
  anio: number;
  ipc_interanual: number;
  dolar_mep_cierre: number;
}

export interface RegistroPatrimonioRaw {
  legislador_id: number;
  anio_fiscal: number;
  monto_pesos_nominal: number;
  fuente: string;
}

export interface PuntoEvolucionPatrimonial {
  anio_fiscal: number;
  monto_pesos_nominal: number;
  patrimonio_pesos_constantes: number;
  dolar_mep_cierre: number;
  ipc_interanual: number;
  patrimonio_usd: number;
  variacion_interanual_usd_pct: number | null;
  fuente: string;
}

export interface ResumenPatrimonial {
  anio_inicio: number;
  anio_fin: number;
  patrimonio_usd_inicial: number;
  patrimonio_usd_actual: number;
  crecimiento_real_usd_pct: number;
  crecimiento_pesos_constantes_pct: number;
  moneda_constante_base: number;
  serie: PuntoEvolucionPatrimonial[];
}

export interface VectorIdeologico {
  Salud: number;
  Educacion: number;
  MercadoLibre: number;
  Proteccionismo: number;
  Laboral: number;
}

export interface LeyParlamentaria {
  id: string;
  titulo: string;
  resumen: string;
  fecha_votacion: string;
  eje_tematico: EjeTematico;
  peso_ideologico: number;
}

export interface VotoDetallado {
  ley_id: string;
  titulo: string;
  resumen: string;
  fecha_votacion: string;
  eje_tematico: EjeTematico;
  peso_ideologico: number;
  sentido_voto: SentidoVoto;
}

export interface LegisladorResumen {
  id: number;
  id_congreso: string;
  cuit: string;
  wiki_slug: string;
  slug: string;
  nombre_completo: string;
  camara: 'Diputados' | 'Senadores';
  provincia: string;
  bloque_actual: string;
  estado: 'Activo' | 'Inactivo' | 'Mandato Cumplido' | 'Candidato';
  biografia: string;
  foto_url: string;
  asistencia_historica: number;
  lealtad_partidaria: number;
  patrimonio_usd_actual: number;
  crecimiento_real_usd_pct: number;
  vector_ideologico: VectorIdeologico;
  votos?: Record<string, SentidoVoto>;
}

export interface LegisladorPerfilCompleto extends LegisladorResumen {
  evolucion_patrimonial: ResumenPatrimonial;
  votaciones: VotoDetallado[];
}

export interface ResultadoMatchLegislador {
  legislador: LegisladorResumen;
  similitud_pct: number;
  coincidencias_clave: string[];
  diferencias_clave: string[];
}

export interface RespuestaMatchElectoral {
  vector_usuario: VectorIdeologico;
  top_3_afines: ResultadoMatchLegislador[];
  bottom_3_menos_afines: ResultadoMatchLegislador[];
  total_evaluados: number;
}

export interface PreguntaMatch {
  id: number;
  eje: 'Salud' | 'Educacion' | 'MercadoLibre' | 'Proteccionismo';
  ejeLabel: string;
  expedienteRef: string;
  pregunta: string;
  contexto: string;
  opciones: {
    id: string;
    label: string;
    descripcion: string;
    valorEje: number; // 0 a 100 aporte a ese eje
  }[];
}
