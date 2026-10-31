import {
  INDICADORES_ECONOMICOS_SEED,
  LEGISLADORES_RAW_SEED,
  LEYES_SEED,
  PATRIMONIO_NOMINAL_SEED,
  obtenerVotosSeedPorLegislador,
} from '../data/seedData';
import {
  EjeTematico,
  LegisladorPerfilCompleto,
  LegisladorResumen,
  PuntoEvolucionPatrimonial,
  RespuestaMatchElectoral,
  ResultadoMatchLegislador,
  ResumenPatrimonial,
  SentidoVoto,
  VectorIdeologico,
  VotoDetallado,
} from '../types/legisdata';

const EJES_LIST: EjeTematico[] = [
  'Salud',
  'Educacion',
  'MercadoLibre',
  'Proteccionismo',
  'Laboral',
];

/**
 * Algoritmo A — Evolución Patrimonial Real (`calcularEvolucionPatrimonial`)
 * Cruza `patrimonio_ddjj` con `indicadores_economicos` por `anio_fiscal = anio`.
 * Calcula USD MEP y Pesos Constantes ajustados por IPC acumulado al último año (2025).
 */
export function calcularEvolucionPatrimonialLocal(legisladorId: number): ResumenPatrimonial {
  const nominalPorAnio = PATRIMONIO_NOMINAL_SEED[legisladorId] || {};
  const indicadoresMap = new Map(INDICADORES_ECONOMICOS_SEED.map((i) => [i.anio, i]));
  const aniosOrdenados = Object.keys(nominalPorAnio)
    .map(Number)
    .sort((a, b) => a - b);

  const anioBaseConstante = aniosOrdenados[aniosOrdenados.length - 1] ?? 2025;

  // Factor acumulado de inflación desde cada año t hasta anioBaseConstante
  // Si estamos en 2020, se multiplica por (1 + ipc_2021/100) * (1 + ipc_2022/100) ... * (1 + ipc_2025/100)
  const calcularFactorIpcHastaFin = (anioOrigen: number): number => {
    let factor = 1.0;
    for (let a = anioOrigen + 1; a <= anioBaseConstante; a++) {
      const ind = indicadoresMap.get(a);
      if (ind) {
        factor *= 1 + ind.ipc_interanual / 100;
      }
    }
    return factor;
  };

  const serie: PuntoEvolucionPatrimonial[] = [];
  let prevUsd: number | null = null;

  for (const anio of aniosOrdenados) {
    const montoNominal = nominalPorAnio[anio] ?? 0;
    const ind = indicadoresMap.get(anio) ?? {
      anio,
      ipc_interanual: 0,
      dolar_mep_cierre: 1000,
    };

    const patrimonioUsd = Number((montoNominal / ind.dolar_mep_cierre).toFixed(2));
    const factorIpc = calcularFactorIpcHastaFin(anio);
    const patrimonioPesosConstantes = Number((montoNominal * factorIpc).toFixed(2));

    const variacionUsd =
      prevUsd !== null && prevUsd > 0
        ? Number((((patrimonioUsd - prevUsd) / prevUsd) * 100).toFixed(2))
        : null;

    prevUsd = patrimonioUsd;

    serie.push({
      anio_fiscal: anio,
      monto_pesos_nominal: montoNominal,
      patrimonio_pesos_constantes: patrimonioPesosConstantes,
      dolar_mep_cierre: ind.dolar_mep_cierre,
      ipc_interanual: ind.ipc_interanual,
      patrimonio_usd: patrimonioUsd,
      variacion_interanual_usd_pct: variacionUsd,
      fuente: 'Oficina Anticorrupción',
    });
  }

  const primero = serie[0];
  const ultimo = serie[serie.length - 1];

  const usdInicial = primero?.patrimonio_usd ?? 0;
  const usdActual = ultimo?.patrimonio_usd ?? 0;
  const crecimientoRealUsdPct =
    usdInicial > 0 ? Number((((usdActual - usdInicial) / usdInicial) * 100).toFixed(2)) : 0;

  const constInicial = primero?.patrimonio_pesos_constantes ?? 0;
  const constActual = ultimo?.patrimonio_pesos_constantes ?? 0;
  const crecimientoConstPct =
    constInicial > 0
      ? Number((((constActual - constInicial) / constInicial) * 100).toFixed(2))
      : 0;

  return {
    anio_inicio: primero?.anio_fiscal ?? 2020,
    anio_fin: ultimo?.anio_fiscal ?? 2025,
    patrimonio_usd_inicial: usdInicial,
    patrimonio_usd_actual: usdActual,
    crecimiento_real_usd_pct: crecimientoRealUsdPct,
    crecimiento_pesos_constantes_pct: crecimientoConstPct,
    moneda_constante_base: anioBaseConstante,
    serie,
  };
}

/**
 * Algoritmo B — Perfilamiento Temático (`calcularPerfilTematico`)
 * Excluye `Ausente`. Afirmativo = 1.0 * peso, Abstencion = 0.25 * peso, Negativo = 0.0.
 */
export function calcularPerfilTematicoLocal(legisladorId: number): {
  vector: VectorIdeologico;
  votaciones: VotoDetallado[];
} {
  const mapaVotos = obtenerVotosSeedPorLegislador(legisladorId);
  const sumaPonderada: Record<EjeTematico, number> = {
    Salud: 0,
    Educacion: 0,
    MercadoLibre: 0,
    Proteccionismo: 0,
    Laboral: 0,
  };
  const maxPosible: Record<EjeTematico, number> = {
    Salud: 0,
    Educacion: 0,
    MercadoLibre: 0,
    Proteccionismo: 0,
    Laboral: 0,
  };

  const votaciones: VotoDetallado[] = LEYES_SEED.map((ley) => {
    const sentido: SentidoVoto = mapaVotos[ley.id] ?? 'Abstencion';
    if (sentido !== 'Ausente') {
      maxPosible[ley.eje_tematico] += ley.peso_ideologico;
      if (sentido === 'Afirmativo') {
        sumaPonderada[ley.eje_tematico] += 1.0 * ley.peso_ideologico;
      } else if (sentido === 'Abstencion') {
        sumaPonderada[ley.eje_tematico] += 0.25 * ley.peso_ideologico;
      }
    }
    return {
      ley_id: ley.id,
      titulo: ley.titulo,
      resumen: ley.resumen,
      fecha_votacion: ley.fecha_votacion,
      eje_tematico: ley.eje_tematico,
      peso_ideologico: ley.peso_ideologico,
      sentido_voto: sentido,
    };
  });

  // Ordenar votaciones por fecha descendente
  votaciones.sort((a, b) => b.fecha_votacion.localeCompare(a.fecha_votacion));

  const vector: VectorIdeologico = {
    Salud: 0,
    Educacion: 0,
    MercadoLibre: 0,
    Proteccionismo: 0,
    Laboral: 0,
  };

  for (const eje of EJES_LIST) {
    const max = maxPosible[eje];
    vector[eje] = max > 0 ? Number(((sumaPonderada[eje] / max) * 100).toFixed(1)) : 0;
  }

  return { vector, votaciones };
}

function construirLegisladorCompletoLocal(rawId: number): LegisladorPerfilCompleto | null {
  const raw = LEGISLADORES_RAW_SEED.find((l) => l.id === rawId);
  if (!raw) return null;

  const evolucion = calcularEvolucionPatrimonialLocal(raw.id);
  const { vector, votaciones } = calcularPerfilTematicoLocal(raw.id);

  return {
    ...raw,
    slug: raw.wiki_slug.toLowerCase().replace(/_/g, '-'),
    patrimonio_usd_actual: evolucion.patrimonio_usd_actual,
    crecimiento_real_usd_pct: evolucion.crecimiento_real_usd_pct,
    vector_ideologico: vector,
    evolucion_patrimonial: evolucion,
    votaciones,
  };
}

const CACHE_PERFILES_LOCAL: LegisladorPerfilCompleto[] = LEGISLADORES_RAW_SEED.map(
  (r) => construirLegisladorCompletoLocal(r.id)!
);

/**
 * Algoritmo C — Motor de Match Electoral (`calcularMatchElectoral`)
 * Similitud de Coseno sobre las 4 dimensiones principales: Salud, Educacion, MercadoLibre, Proteccionismo.
 */
export function calcularMatchElectoralLocal(
  vectorUsuarioParcial: Pick<
    VectorIdeologico,
    'Salud' | 'Educacion' | 'MercadoLibre' | 'Proteccionismo'
  >
): RespuestaMatchElectoral {
  const dims: ('Salud' | 'Educacion' | 'MercadoLibre' | 'Proteccionismo')[] = [
    'Salud',
    'Educacion',
    'MercadoLibre',
    'Proteccionismo',
  ];

  const labelsEje: Record<string, string> = {
    Salud: 'Salud Pública',
    Educacion: 'Educación y Ciencia',
    MercadoLibre: 'Economía y Libre Mercado',
    Proteccionismo: 'Industria y Regulación Estatal',
  };

  // Estimar Laboral proporcionalmente a Proteccionismo/Salud para completar el vector de 5 ejes
  const laboralEstimado = Number(
    (vectorUsuarioParcial.Proteccionismo * 0.6 + vectorUsuarioParcial.Salud * 0.4).toFixed(1)
  );

  const vectorUsuario: VectorIdeologico = {
    ...vectorUsuarioParcial,
    Laboral: laboralEstimado,
  };

  const normU = Math.sqrt(
    dims.reduce((acc, d) => acc + Math.pow(Math.max(vectorUsuario[d], 1), 2), 0)
  );

  const resultados: ResultadoMatchLegislador[] = CACHE_PERFILES_LOCAL.map((leg) => {
    const vL = leg.vector_ideologico;
    let dot = 0;
    let sumSqL = 0;

    for (const d of dims) {
      const uVal = Math.max(vectorUsuario[d], 1);
      const lVal = Math.max(vL[d], 1);
      dot += uVal * lVal;
      sumSqL += lVal * lVal;
    }

    const normL = Math.sqrt(sumSqL);
    const cosSim = normU > 0 && normL > 0 ? (dot / (normU * normL)) * 100 : 0;

    // Penalizar levemente la distancia absoluta promedio para desempatar vectores colineales de distinta intensidad
    const avgDiff =
      dims.reduce((acc, d) => acc + Math.abs(vectorUsuario[d] - vL[d]), 0) / dims.length;
    const scoreFinal = Math.max(
      5,
      Math.min(99.4, Number((cosSim * 0.82 + (100 - avgDiff) * 0.18).toFixed(1)))
    );

    const coincidencias: string[] = [];
    const diferencias: string[] = [];

    for (const d of dims) {
      const diff = Math.abs(vectorUsuario[d] - vL[d]);
      if (diff <= 22) {
        coincidencias.push(labelsEje[d]);
      } else if (diff >= 42) {
        diferencias.push(labelsEje[d]);
      }
    }

    return {
      legislador: leg,
      similitud_pct: scoreFinal,
      coincidencias_clave:
        coincidencias.length > 0 ? coincidencias : ['Postura institucional moderada'],
      diferencias_clave:
        diferencias.length > 0 ? diferencias : ['Matices de intensidad legislativa'],
    };
  });

  const ordenadosDesc = [...resultados].sort((a, b) => b.similitud_pct - a.similitud_pct);
  const ordenadosAsc = [...resultados].sort((a, b) => a.similitud_pct - b.similitud_pct);

  return {
    vector_usuario: vectorUsuario,
    top_3_afines: ordenadosDesc.slice(0, 3),
    bottom_3_menos_afines: ordenadosAsc.slice(0, 3),
    total_evaluados: resultados.length,
  };
}

/**
 * Normalizador flexible por si la API PHP devuelve estructura envuelta en `data`
 */
function normalizarLegisladorDesdeApi(item: Record<string, unknown>): LegisladorResumen | null {
  const idNum = Number(item.id ?? 0);
  const fallbackLocal = CACHE_PERFILES_LOCAL.find((l) => l.id === idNum);
  if (!fallbackLocal && !item.nombre_completo) return null;

  const vectorRaw = (item.vector_ideologico ?? item.perfil_tematico ?? {}) as Record<
    string,
    unknown
  >;

  return {
    id: idNum || fallbackLocal!.id,
    id_congreso: String(item.id_congreso ?? fallbackLocal?.id_congreso ?? ''),
    cuit: String(item.cuit ?? fallbackLocal?.cuit ?? ''),
    wiki_slug: String(item.wiki_slug ?? fallbackLocal?.wiki_slug ?? ''),
    slug: String(item.slug ?? fallbackLocal?.slug ?? ''),
    nombre_completo: String(item.nombre_completo ?? fallbackLocal?.nombre_completo ?? ''),
    camara:
      String(item.camara ?? fallbackLocal?.camara).startsWith('Senad')
        ? 'Senadores'
        : 'Diputados',
    provincia: String(item.provincia ?? fallbackLocal?.provincia ?? ''),
    bloque_actual: String(
      item.bloque_actual ?? item.bloque ?? fallbackLocal?.bloque_actual ?? ''
    ),
    estado: (item.estado as LegisladorResumen['estado']) ?? fallbackLocal?.estado ?? 'Activo',
    biografia: String(item.biografia ?? fallbackLocal?.biografia ?? ''),
    foto_url: String(item.foto_url ?? fallbackLocal?.foto_url ?? ''),
    asistencia_historica: Number(
      item.asistencia_historica ?? item.asistencia_pct ?? fallbackLocal?.asistencia_historica ?? 95
    ),
    lealtad_partidaria: Number(
      item.lealtad_partidaria ?? item.lealtad_pct ?? fallbackLocal?.lealtad_partidaria ?? 90
    ),
    patrimonio_usd_actual: Number(
      item.patrimonio_usd_actual ?? fallbackLocal?.patrimonio_usd_actual ?? 0
    ),
    crecimiento_real_usd_pct: Number(
      item.crecimiento_real_usd_pct ?? fallbackLocal?.crecimiento_real_usd_pct ?? 0
    ),
    vector_ideologico: {
      Salud: Number(vectorRaw.Salud ?? fallbackLocal?.vector_ideologico.Salud ?? 50),
      Educacion: Number(vectorRaw.Educacion ?? fallbackLocal?.vector_ideologico.Educacion ?? 50),
      MercadoLibre: Number(
        vectorRaw.MercadoLibre ?? fallbackLocal?.vector_ideologico.MercadoLibre ?? 50
      ),
      Proteccionismo: Number(
        vectorRaw.Proteccionismo ?? fallbackLocal?.vector_ideologico.Proteccionismo ?? 50
      ),
      Laboral: Number(vectorRaw.Laboral ?? fallbackLocal?.vector_ideologico.Laboral ?? 50),
    },
  };
}

export async function fetchLegisladores(): Promise<LegisladorResumen[]> {
  try {
    const res = await fetch('/api/legisladores', {
      headers: { Accept: 'application/json' },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();
    const listaRaw = Array.isArray(json)
      ? json
      : Array.isArray(json?.data)
      ? json.data
      : Array.isArray(json?.legisladores)
      ? json.legisladores
      : null;

    if (listaRaw && listaRaw.length > 0) {
      const normalizados = listaRaw
        .map((item: Record<string, unknown>) => normalizarLegisladorDesdeApi(item))
        .filter((x: LegisladorResumen | null): x is LegisladorResumen => x !== null);
      if (normalizados.length > 0) return normalizados;
    }
  } catch {
    // Fallback instantáneo al dataset semilla tipado
  }
  return CACHE_PERFILES_LOCAL;
}

export async function fetchLegisladorPerfil(
  idOrSlug: string | number
): Promise<LegisladorPerfilCompleto | null> {
  const idNum = Number(idOrSlug);
  const localMatch =
    CACHE_PERFILES_LOCAL.find(
      (l) =>
        l.id === idNum ||
        l.slug.toLowerCase() === String(idOrSlug).toLowerCase() ||
        l.wiki_slug.toLowerCase() === String(idOrSlug).toLowerCase()
    ) ?? null;

  try {
    const targetId = localMatch ? localMatch.id : idOrSlug;
    const [resPerfil, resPatrimonio] = await Promise.allSettled([
      fetch(`/api/legisladores/${encodeURIComponent(String(targetId))}`, {
        headers: { Accept: 'application/json' },
      }),
      fetch(`/api/legisladores/${encodeURIComponent(String(targetId))}/patrimonio`, {
        headers: { Accept: 'application/json' },
      }),
    ]);

    if (resPerfil.status === 'fulfilled' && resPerfil.value.ok) {
      const jsonPerfil = await resPerfil.value.json();
      const rawData = (jsonPerfil?.data ?? jsonPerfil?.legislador ?? jsonPerfil) as Record<
        string,
        unknown
      >;
      const base = normalizarLegisladorDesdeApi(rawData);

      if (base) {
        let evolucion = localMatch?.evolucion_patrimonial ?? calcularEvolucionPatrimonialLocal(base.id);

        if (resPatrimonio.status === 'fulfilled' && resPatrimonio.value.ok) {
          const jsonPat = await resPatrimonio.value.json();
          const patData = jsonPat?.data ?? jsonPat;
          if (patData && Array.isArray(patData.serie) && patData.serie.length > 0) {
            evolucion = patData as ResumenPatrimonial;
          }
        }

        const votacionesApi = Array.isArray(rawData.votaciones)
          ? (rawData.votaciones as VotoDetallado[])
          : localMatch?.votaciones ?? [];

        return {
          ...base,
          patrimonio_usd_actual: evolucion.patrimonio_usd_actual,
          crecimiento_real_usd_pct: evolucion.crecimiento_real_usd_pct,
          evolucion_patrimonial: evolucion,
          votaciones: votacionesApi,
        };
      }
    }
  } catch {
    // Fallback instantáneo al perfil local
  }

  return localMatch;
}

export async function enviarMatchElectoral(
  vectorUsuario: Pick<
    VectorIdeologico,
    'Salud' | 'Educacion' | 'MercadoLibre' | 'Proteccionismo'
  >
): Promise<RespuestaMatchElectoral> {
  try {
    const res = await fetch('/api/match', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ vector_usuario: vectorUsuario }),
    });
    if (res.ok) {
      const json = await res.json();
      const payload = json?.data ?? json;
      if (
        payload &&
        Array.isArray(payload.top_3_afines) &&
        payload.top_3_afines.length > 0 &&
        payload.top_3_afines[0]?.legislador?.nombre_completo
      ) {
        return payload as RespuestaMatchElectoral;
      }
    }
  } catch {
    // Fallback instantáneo a motor de similitud de coseno en cliente
  }

  return calcularMatchElectoralLocal(vectorUsuario);
}
