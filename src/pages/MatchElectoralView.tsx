import React, { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import {
  ArrowLeft,
  ArrowRight,
  RotateCcw,
  Share2,
  Check,
  Sparkles,
  CheckCircle2,
  AlertTriangle,
  ExternalLink,
  Award,
} from 'lucide-react';
import { PREGUNTAS_MATCH_ELECTORAL } from '../data/seedData';
import { enviarMatchElectoral } from '../services/api';
import { RespuestaMatchElectoral, VectorIdeologico } from '../types/legisdata';
import { IdeologiaRadarChart } from '../components/IdeologiaRadarChart';
import { BarrasTematicas } from '../components/BarrasTematicas';
import { AvatarLegislador } from '../components/AvatarLegislador';
import {
  EJE_TEMATICO_META,
  formatPct,
  formatUSD,
  getBloqueBadgeStyle,
  getLegisladorUrl,
} from '../utils/formatters';
import { actualizarMetaSeo } from '../utils/seo';

export const MatchElectoralView: React.FC = () => {
  const [, setSearchParams] = useSearchParams();
  const totalPreguntas = PREGUNTAS_MATCH_ELECTORAL.length; // 8 preguntas

  const [pasoActual, setPasoActual] = useState<number>(0);
  const [respuestas, setRespuestas] = useState<Record<number, string>>({});
  const [calculando, setCalculando] = useState<boolean>(false);
  const [resultadoMatch, setResultadoMatch] = useState<RespuestaMatchElectoral | null>(
    null
  );
  const [legisladorComparadoIdx, setLegisladorComparadoIdx] = useState<number>(0);
  const [copiadoShare, setCopiadoShare] = useState<boolean>(false);

  useEffect(() => {
    window.location.replace('/match-360');
  }, []);

  // Actualizar metadatos SEO cuando se obtienen los resultados del Match
  useEffect(() => {
    if (resultadoMatch && resultadoMatch.top_3_afines[0]) {
      const top1 = resultadoMatch.top_3_afines[0];
      actualizarMetaSeo({
        title: `Mi Match Legislativo: ${formatPct(top1.similitud_pct)} con ${top1.legislador.nombre_completo} | LegisData DipuData`,
        description: `Hice el test de afinidad parlamentaria por Similitud de Coseno en DipuData. Mi mayor coincidencia es ${top1.legislador.nombre_completo} (${top1.legislador.bloque_actual}).`,
        canonicalPath: '/match',
        ogImage: `https://dipudata.com/api/og?type=match&top=${encodeURIComponent(
          top1.legislador.nombre_completo
        )}&score=${top1.similitud_pct}`,
      });
    }
  }, [resultadoMatch]);

  const preguntaActual = PREGUNTAS_MATCH_ELECTORAL[pasoActual];
  const progresoPct = ((pasoActual + 1) / totalPreguntas) * 100;

  const calcularVectorDesdeRespuestas = (
    mapaRespuestas: Record<number, string>
  ): Pick<
    VectorIdeologico,
    'Salud' | 'Educacion' | 'MercadoLibre' | 'Proteccionismo'
  > => {
    const acumulador: Record<
      'Salud' | 'Educacion' | 'MercadoLibre' | 'Proteccionismo',
      number[]
    > = {
      Salud: [],
      Educacion: [],
      MercadoLibre: [],
      Proteccionismo: [],
    };

    for (const preg of PREGUNTAS_MATCH_ELECTORAL) {
      const opId = mapaRespuestas[preg.id];
      const opcion = preg.opciones.find((o) => o.id === opId);
      const valor = opcion ? opcion.valorEje : 50;
      acumulador[preg.eje].push(valor);
    }

    const avg = (arr: number[]) =>
      arr.length > 0
        ? Number((arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(1))
        : 50;

    return {
      Salud: avg(acumulador.Salud),
      Educacion: avg(acumulador.Educacion),
      MercadoLibre: avg(acumulador.MercadoLibre),
      Proteccionismo: avg(acumulador.Proteccionismo),
    };
  };

  const handleSeleccionarOpcion = (opcionId: string) => {
    const nuevasRespuestas = {
      ...respuestas,
      [preguntaActual.id]: opcionId,
    };
    setRespuestas(nuevasRespuestas);

    if (pasoActual < totalPreguntas - 1) {
      setTimeout(() => {
        setPasoActual((prev) => Math.min(totalPreguntas - 1, prev + 1));
      }, 240);
    } else {
      // Última pregunta respondida -> Ejecutar Algoritmo C (Match Electoral)
      const vectorCalculado = calcularVectorDesdeRespuestas(nuevasRespuestas);
      setCalculando(true);
      enviarMatchElectoral(vectorCalculado).then((res) => {
        setResultadoMatch(res);
        setLegisladorComparadoIdx(0);
        setCalculando(false);
        setSearchParams(
          {
            s: String(Math.round(vectorCalculado.Salud)),
            e: String(Math.round(vectorCalculado.Educacion)),
            m: String(Math.round(vectorCalculado.MercadoLibre)),
            p: String(Math.round(vectorCalculado.Proteccionismo)),
          },
          { replace: true }
        );
      });
    }
  };

  const reiniciarCuestionario = () => {
    setResultadoMatch(null);
    setRespuestas({});
    setPasoActual(0);
    setLegisladorComparadoIdx(0);
    setSearchParams({}, { replace: true });
  };

  const urlCompartirResultados = useMemo(() => {
    if (!resultadoMatch) return window.location.href;
    const v = resultadoMatch.vector_usuario;
    const base = `${window.location.origin}/match`;
    return `${base}?s=${Math.round(v.Salud)}&e=${Math.round(
      v.Educacion
    )}&m=${Math.round(v.MercadoLibre)}&p=${Math.round(v.Proteccionismo)}`;
  }, [resultadoMatch]);

  const handleCompartirMatch = async () => {
    if (!resultadoMatch) return;
    const top1 = resultadoMatch.top_3_afines[0];
    const textoShare = `Hice mi Match Electoral en LegisData (dipudata.com) con leyes reales del Congreso Argentino. Mi mayor coincidencia fue ${top1.legislador.nombre_completo} (${formatPct(
      top1.similitud_pct
    )}). Descubrí el tuyo:`;

    if (navigator.share) {
      try {
        await navigator.share({
          title: 'Mi Match Electoral en LegisData (dipudata.com)',
          text: textoShare,
          url: urlCompartirResultados,
        });
        return;
      } catch {
        // continuar a portapapeles
      }
    }

    try {
      await navigator.clipboard.writeText(`${textoShare} ${urlCompartirResultados}`);
      setCopiadoShare(true);
      setTimeout(() => setCopiadoShare(false), 2600);
    } catch {
      // ignore
    }
  };

  // ============================================================================
  // VISTA 4: PANTALLA DE RESULTADOS DEL MATCH (SPLIT SCREEN)
  // ============================================================================
  if (resultadoMatch) {
    const topSeleccionado =
      resultadoMatch.top_3_afines[legisladorComparadoIdx] ??
      resultadoMatch.top_3_afines[0];
    const top1 = resultadoMatch.top_3_afines[0];

    const ogPreviewUrl = `/api/og?type=match&top=${encodeURIComponent(
      top1.legislador.nombre_completo
    )}&bloque=${encodeURIComponent(
      top1.legislador.bloque_actual
    )}&score=${Math.round(top1.similitud_pct)}`;

    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        {/* Header de Resultados */}
        <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          <div className="space-y-2 max-w-2xl">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-700">
              <Sparkles className="w-3.5 h-3.5" />
              <span>Similitud de Coseno Calculada sobre {resultadoMatch.total_evaluados} Legisladores</span>
            </div>
            <h1 className="text-2xl sm:text-4xl font-extrabold text-slate-900 tracking-[-0.02em]">
              Resultados de tu Match Electoral
            </h1>
            <p className="text-sm sm:text-base text-slate-500">
              Comparamos tu vector ciudadano en los 4 ejes estratégicos contra el historial real
              de votación nominal en la Cámara de Diputados y el Senado.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <button
              type="button"
              onClick={reiniciarCuestionario}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 text-slate-700 text-sm font-semibold hover:bg-slate-200 transition-colors"
            >
              <RotateCcw className="w-4 h-4" />
              <span>Repetir Cuestionario</span>
            </button>

            {/* Botón Share destacado para redes sociales con OpenGraph */}
            <button
              type="button"
              onClick={handleCompartirMatch}
              className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-bold hover:bg-blue-700 shadow-sm ring-2 ring-blue-500/20 transition-all"
            >
              {copiadoShare ? (
                <>
                  <Check className="w-4 h-4" />
                  <span>¡Resultado copiado para compartir!</span>
                </>
              ) : (
                <>
                  <Share2 className="w-4 h-4" />
                  <span>Compartir mi Match</span>
                </>
              )}
            </button>
          </div>
        </div>

        {/* SPLIT SCREEN (Izquierda/Arriba: Tu perfil como Votante | Derecha/Abajo: Tus mejores coincidencias Top 3 + Bottom 3) */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          {/* COLUMNA IZQUIERDA: "Tu perfil como Votante" */}
          <section className="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-7 space-y-6 lg:sticky lg:top-24">
            <div className="border-b border-slate-100 pb-4">
              <span className="text-xs font-bold uppercase tracking-wider text-blue-600">
                Huella Ciudadana
              </span>
              <h2 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-[-0.02em] mt-0.5">
                Tu perfil como Votante
              </h2>
              <p className="text-xs text-slate-500 mt-1">
                Polígono azul: Tu postura · Polígono punteado:{' '}
                <strong className="text-slate-700">
                  {topSeleccionado.legislador.nombre_completo}
                </strong>
              </p>
            </div>

            <IdeologiaRadarChart
              vectorPrincipal={resultadoMatch.vector_usuario}
              labelPrincipal="Tu Perfil como Votante"
              vectorComparacion={topSeleccionado.legislador.vector_ideologico}
              labelComparacion={topSeleccionado.legislador.nombre_completo}
            />

            <div className="pt-4 border-t border-slate-100">
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">
                Tus puntuaciones por dimensión
              </h3>
              <BarrasTematicas
                vector={resultadoMatch.vector_usuario}
                mostrarLaboral={false}
              />
            </div>

            {/* Tarjeta de Compartir en X / WhatsApp + OpenGraph */}
            <div className="rounded-xl bg-slate-900 text-white p-4 space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
                  Compartir Tarjeta OpenGraph
                </span>
                <span className="text-[11px] font-mono text-emerald-400">
                  1200×630 Dinámica
                </span>
              </div>
              <p className="text-xs text-slate-300 leading-relaxed">
                Tu enlace incluye tus coordenadas temáticas y genera una imagen previa automática
                al compartirlo en X (Twitter), WhatsApp o LinkedIn.
              </p>
              <div className="flex flex-wrap gap-2 pt-1">
                <a
                  href={`https://twitter.com/intent/tweet?text=${encodeURIComponent(
                    `Mi mayor coincidencia legislativa en @DipuData es ${top1.legislador.nombre_completo} (${formatPct(
                      top1.similitud_pct
                    )} de afinidad real de voto). Calculá tu Match Electoral:`
                  )}&url=${encodeURIComponent(urlCompartirResultados)}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex-1 text-center px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold transition-colors"
                >
                  Compartir en X / Twitter
                </a>
                <a
                  href={`https://wa.me/?text=${encodeURIComponent(
                    `Hice el Match Electoral del Congreso en DipuData. Mi mayor afinidad es ${top1.legislador.nombre_completo} (${formatPct(
                      top1.similitud_pct
                    )}): ${urlCompartirResultados}`
                  )}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex-1 text-center px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-xs font-semibold transition-colors"
                >
                  Enviar por WhatsApp
                </a>
              </div>
              <div className="pt-1 flex items-center justify-between text-[11px] text-slate-400 font-mono truncate">
                <span className="truncate">OG Endpoint: {ogPreviewUrl}</span>
              </div>
            </div>
          </section>

          {/* COLUMNA DERECHA: "Tus mejores coincidencias" (Top 3 + Bottom 3) */}
          <div className="lg:col-span-7 space-y-8">
            <section className="space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <span className="text-xs font-bold uppercase tracking-wider text-emerald-700">
                    Ranking por Similitud de Coseno
                  </span>
                  <h2 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-[-0.02em]">
                    Tus mejores coincidencias (Top 3)
                  </h2>
                </div>
                <span className="text-xs text-slate-500 hidden sm:inline">
                  Hacé clic en una tarjeta para superponer su radar
                </span>
              </div>

              <div className="space-y-4">
                {resultadoMatch.top_3_afines.map((item, index) => {
                  const leg = item.legislador;
                  const isSelectedOnRadar = legisladorComparadoIdx === index;

                  return (
                    <div
                      key={leg.id}
                      onClick={() => setLegisladorComparadoIdx(index)}
                      className={`cursor-pointer bg-white rounded-2xl border transition-all p-5 sm:p-6 ${
                        isSelectedOnRadar
                          ? 'border-blue-500 ring-2 ring-blue-500/20 shadow-md'
                          : 'border-slate-200/80 shadow-sm hover:border-slate-300'
                      }`}
                    >
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="flex items-start gap-4">
                          <div className="relative">
                            <AvatarLegislador
                              src={leg.foto_url}
                              nombre={leg.nombre_completo}
                              size="lg"
                            />
                            <span className="absolute -top-1.5 -left-1.5 w-6 h-6 rounded-full bg-slate-900 text-white font-mono tabular-nums text-xs font-bold flex items-center justify-center shadow">
                              #{index + 1}
                            </span>
                          </div>

                          <div>
                            <div className="flex flex-wrap items-center gap-2">
                              <h3 className="text-lg font-bold text-slate-900 tracking-[-0.02em]">
                                {leg.nombre_completo}
                              </h3>
                              {index === 0 && (
                                <span className="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                  <Award className="w-3 h-3" />
                                  Mayor Coincidencia
                                </span>
                              )}
                            </div>

                            <p className="text-xs text-slate-500 mt-0.5">
                              {leg.camara === 'Senadores'
                                ? 'Senador/a Nacional'
                                : 'Diputado/a Nacional'}{' '}
                              · {leg.provincia}
                            </p>

                            <div className="flex flex-wrap items-center gap-1.5 mt-2">
                              <span
                                className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${getBloqueBadgeStyle(
                                  leg.bloque_actual
                                )}`}
                              >
                                {leg.bloque_actual}
                              </span>
                              <span className="rounded-full px-2.5 py-0.5 text-xs font-mono tabular-nums bg-slate-100 text-slate-700">
                                Asistencia: {formatPct(leg.asistencia_historica)}
                              </span>
                              <span className="rounded-full px-2.5 py-0.5 text-xs font-mono tabular-nums bg-slate-100 text-slate-700">
                                DDJJ: {formatUSD(leg.patrimonio_usd_actual)}
                              </span>
                            </div>
                          </div>
                        </div>

                        {/* Badge grande de porcentaje de afinidad */}
                        <div className="flex sm:flex-col items-center sm:items-end justify-between border-t sm:border-t-0 pt-3 sm:pt-0 border-slate-100 shrink-0">
                          <span className="text-[11px] uppercase tracking-wider font-semibold text-slate-400">
                            Afinidad de Voto
                          </span>
                          <span className="text-2xl sm:text-3xl font-mono tabular-nums font-extrabold text-emerald-700 bg-emerald-100 px-3 py-1 rounded-xl mt-1">
                            {formatPct(item.similitud_pct)}
                          </span>
                        </div>
                      </div>

                      {/* Coincidencias clave y Botón "Ver radiografía completa" */}
                      <div className="mt-4 pt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="flex flex-wrap items-center gap-1.5 text-xs">
                          <span className="text-slate-500 font-medium inline-flex items-center gap-1">
                            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                            Coincidencia en:
                          </span>
                          {item.coincidencias_clave.map((c) => (
                            <span
                              key={c}
                              className="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium"
                            >
                              {c}
                            </span>
                          ))}
                        </div>

                        <a
                          href={getLegisladorUrl(leg)}
                          onClick={(e) => e.stopPropagation()}
                          className="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition-colors shrink-0"
                        >
                          <span>Ver radiografía completa</span>
                          <ExternalLink className="w-3.5 h-3.5" />
                        </a>
                      </div>
                    </div>
                  );
                })}
              </div>
            </section>

            {/* SECCIÓN SECUNDARIA: LOS MENOS AFINES (BOTTOM 3) */}
            <section className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-4">
              <div>
                <span className="text-xs font-bold uppercase tracking-wider text-rose-700">
                  Contraste Parlamentario
                </span>
                <h3 className="text-lg font-bold text-slate-900 tracking-[-0.02em]">
                  Menor coincidencia con tus respuestas (Bottom 3)
                </h3>
                <p className="text-xs text-slate-500">
                  Representantes cuyos votos nominales presentan la mayor distancia angular con tu
                  perfil.
                </p>
              </div>

              <div className="divide-y divide-slate-100">
                {resultadoMatch.bottom_3_menos_afines.map((item) => {
                  const leg = item.legislador;
                  return (
                    <div
                      key={leg.id}
                      className="py-3.5 first:pt-1 last:pb-1 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                    >
                      <div className="flex items-center gap-3">
                        <AvatarLegislador
                          src={leg.foto_url}
                          nombre={leg.nombre_completo}
                          size="md"
                        />
                        <div>
                          <div className="text-sm font-bold text-slate-900">
                            {leg.nombre_completo}
                          </div>
                          <div className="flex flex-wrap items-center gap-1.5 text-xs text-slate-500 mt-0.5">
                            <span>{leg.camara}</span>
                            <span>·</span>
                            <span>{leg.provincia}</span>
                            <span>·</span>
                            <span
                              className={`rounded-full px-2 py-0.2 text-[11px] font-medium ${getBloqueBadgeStyle(
                                leg.bloque_actual
                              )}`}
                            >
                              {leg.bloque_actual}
                            </span>
                          </div>
                          <div className="flex items-center gap-1 text-[11px] text-slate-500 mt-1">
                            <AlertTriangle className="w-3 h-3 text-rose-500 shrink-0" />
                            <span>
                              Diferencia principal: {item.diferencias_clave.join(', ')}
                            </span>
                          </div>
                        </div>
                      </div>

                      <div className="flex items-center justify-between sm:justify-end gap-3">
                        <span className="font-mono tabular-nums text-xs font-bold px-2.5 py-1 rounded-lg text-rose-700 bg-rose-100">
                          {formatPct(item.similitud_pct)} afinidad
                        </span>
                        <a
                          href={getLegisladorUrl(leg)}
                          className="text-xs font-semibold text-slate-700 hover:text-slate-900 underline underline-offset-2"
                        >
                          Ver radiografía
                        </a>
                      </div>
                    </div>
                  );
                })}
              </div>
            </section>
          </div>
        </div>
      </div>
    );
  }

  // ============================================================================
  // VISTA 3: CUESTIONARIO "MATCH ELECTORAL" (WIZARD INTERACTIVO TIPO TYPEFORM)
  // ============================================================================
  const metaEjeActual = EJE_TEMATICO_META[preguntaActual.eje];
  const opcionSeleccionadaActual = respuestas[preguntaActual.id];

  return (
    <div className="min-h-[calc(100vh-4rem)] flex flex-col justify-between bg-slate-50">
      {/* Barra superior muy fina que indica el progreso (Pregunta X de 8) */}
      <div className="w-full bg-white border-b border-slate-200/80 sticky top-16 z-30">
        <div className="w-full h-1.5 bg-slate-200 overflow-hidden">
          <div
            className="h-full bg-blue-600 transition-all duration-300 ease-out"
            style={{ width: `${progresoPct}%` }}
          />
        </div>
        <div className="max-w-4xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between text-xs">
          <div className="flex items-center gap-2">
            <span className="font-mono tabular-nums font-bold text-slate-900 bg-slate-100 px-2.5 py-1 rounded-md border border-slate-200">
              Pregunta {pasoActual + 1} de {totalPreguntas}
            </span>
            <span
              className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 font-semibold ${metaEjeActual.badgeClass}`}
            >
              <span className={`w-2 h-2 rounded-full ${metaEjeActual.barClass}`} />
              {preguntaActual.ejeLabel}
            </span>
          </div>

          <span className="font-mono tabular-nums text-slate-500 hidden sm:inline">
            Ref: {preguntaActual.expedienteRef}
          </span>
        </div>
      </div>

      {/* Contenedor central tipo Typeform: una pregunta por pantalla, tipografía muy grande y clara */}
      <div className="max-w-3xl w-full mx-auto px-4 sm:px-6 py-10 sm:py-14 flex-1 flex flex-col justify-center">
        {calculando ? (
          <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-12 text-center space-y-4">
            <div className="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto animate-pulse">
              <Sparkles className="w-6 h-6" />
            </div>
            <h2 className="text-2xl font-extrabold text-slate-900 tracking-[-0.02em]">
              Calculando Similitud de Coseno...
            </h2>
            <p className="text-sm text-slate-500 max-w-md mx-auto">
              Contrastando tus 8 respuestas con las 300 votaciones nominales del Congreso de la
              Nación Argentina.
            </p>
          </div>
        ) : (
          <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-10 space-y-8 transition-all">
            <div className="space-y-3">
              <div className="inline-flex items-center gap-2 text-xs font-mono tabular-nums font-semibold text-slate-500 bg-slate-100 px-2.5 py-1 rounded">
                Expediente Parlamentario: {preguntaActual.expedienteRef}
              </div>

              <h1 className="text-2xl sm:text-4xl font-extrabold text-slate-900 tracking-[-0.02em] leading-tight">
                {preguntaActual.pregunta}
              </h1>

              <p className="text-sm sm:text-base text-slate-500 leading-relaxed">
                {preguntaActual.contexto}
              </p>
            </div>

            {/* Opciones: Botones grandes de bloque que cambian a ring-2 ring-blue-500 bg-blue-50 al seleccionarlos */}
            <div className="space-y-3.5" role="radiogroup" aria-label={preguntaActual.pregunta}>
              {preguntaActual.opciones.map((opcion, idx) => {
                const isSelected = opcionSeleccionadaActual === opcion.id;
                const letra = String.fromCharCode(65 + idx); // A, B, C

                return (
                  <button
                    key={opcion.id}
                    type="button"
                    onClick={() => handleSeleccionarOpcion(opcion.id)}
                    className={`w-full text-left p-5 rounded-xl border transition-all flex items-start gap-4 group ${
                      isSelected
                        ? 'ring-2 ring-blue-500 bg-blue-50 border-blue-500 shadow-sm'
                        : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50/70'
                    }`}
                  >
                    <span
                      className={`w-8 h-8 rounded-lg font-mono text-sm font-bold flex items-center justify-center shrink-0 mt-0.5 transition-colors ${
                        isSelected
                          ? 'bg-blue-600 text-white'
                          : 'bg-slate-100 text-slate-700 group-hover:bg-slate-200'
                      }`}
                    >
                      {letra}
                    </span>

                    <div className="space-y-1 flex-1">
                      <div
                        className={`text-base sm:text-lg font-bold tracking-[-0.01em] ${
                          isSelected ? 'text-blue-950' : 'text-slate-900'
                        }`}
                      >
                        {opcion.label}
                      </div>
                      <p
                        className={`text-xs sm:text-sm leading-relaxed ${
                          isSelected ? 'text-blue-800' : 'text-slate-500'
                        }`}
                      >
                        {opcion.descripcion}
                      </p>
                    </div>
                  </button>
                );
              })}
            </div>

            {/* Navegación inferior del Wizard */}
            <div className="pt-4 border-t border-slate-100 flex items-center justify-between gap-4">
              <button
                type="button"
                disabled={pasoActual === 0}
                onClick={() => setPasoActual((p) => Math.max(0, p - 1))}
                className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:pointer-events-none transition-colors"
              >
                <ArrowLeft className="w-4 h-4" />
                <span>Pregunta anterior</span>
              </button>

              <div className="flex items-center gap-1.5">
                {PREGUNTAS_MATCH_ELECTORAL.map((p, idx) => {
                  const respondida = Boolean(respuestas[p.id]);
                  const actual = idx === pasoActual;
                  return (
                    <button
                      key={p.id}
                      type="button"
                      onClick={() => setPasoActual(idx)}
                      aria-label={`Ir a pregunta ${idx + 1}`}
                      className={`h-2 rounded-full transition-all ${
                        actual
                          ? 'w-6 bg-blue-600'
                          : respondida
                          ? 'w-2 bg-slate-400'
                          : 'w-2 bg-slate-200'
                      }`}
                    />
                  );
                })}
              </div>

              {opcionSeleccionadaActual && pasoActual < totalPreguntas - 1 ? (
                <button
                  type="button"
                  onClick={() => setPasoActual((p) => Math.min(totalPreguntas - 1, p + 1))}
                  className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold bg-slate-900 text-white hover:bg-slate-800 transition-colors"
                >
                  <span>Siguiente</span>
                  <ArrowRight className="w-4 h-4" />
                </button>
              ) : (
                <span className="text-xs text-slate-400 font-medium">
                  Seleccioná una opción
                </span>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
