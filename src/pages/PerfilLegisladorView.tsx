import React, { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import {
  ArrowLeft,
  ExternalLink,
  TrendingUp,
  TrendingDown,
  CheckCircle2,
  Users,
  Landmark,
  FileText,
  ChevronLeft,
  ChevronRight,
  Share2,
  Check,
  MapPin,
  ShieldCheck,
} from 'lucide-react';
import { fetchLegisladorPerfil } from '../services/api';
import { EjeTematico, LegisladorPerfilCompleto } from '../types/legisdata';
import { AvatarLegislador } from '../components/AvatarLegislador';
import { PatrimonioLineChart } from '../components/PatrimonioLineChart';
import { IdeologiaRadarChart } from '../components/IdeologiaRadarChart';
import { BarrasTematicas } from '../components/BarrasTematicas';
import { DesgloseDdjjCard } from '../components/DesgloseDdjjCard';
import {
  EJE_TEMATICO_META,
  formatARS,
  formatPct,
  formatUSD,
  getBloqueBadgeStyle,
  getLegisladorUrl,
  getVotoBadgeClasses,
  getVotoLabel,
} from '../utils/formatters';

const MAX_LEYES_POR_PAGINA = 10;

export const PerfilLegisladorView: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const [perfil, setPerfil] = useState<LegisladorPerfilCompleto | null>(null);
  const [loading, setLoading] = useState<boolean>(true);

  const [filtroEjeVotos, setFiltroEjeVotos] = useState<'Todos' | EjeTematico>('Todos');
  const [paginaVotos, setPaginaVotos] = useState<number>(1);
  const [copiado, setCopiado] = useState<boolean>(false);

  useEffect(() => {
    setLoading(true);
    setPaginaVotos(1);
    setFiltroEjeVotos('Todos');

    fetchLegisladorPerfil(id ?? '1').then((data) => {
      if (data) {
        const canonical = getLegisladorUrl(data);
        window.location.replace(canonical);
        return;
      }
      setPerfil(data);
      setLoading(false);
    });
  }, [id]);

  const votacionesFiltradas = useMemo(() => {
    if (!perfil) return [];
    if (filtroEjeVotos === 'Todos') return perfil.votaciones;
    return perfil.votaciones.filter((v) => v.eje_tematico === filtroEjeVotos);
  }, [perfil, filtroEjeVotos]);

  const totalPaginas = Math.max(
    1,
    Math.ceil(votacionesFiltradas.length / MAX_LEYES_POR_PAGINA)
  );

  const votacionesPaginadas = useMemo(() => {
    const start = (paginaVotos - 1) * MAX_LEYES_POR_PAGINA;
    return votacionesFiltradas.slice(start, start + MAX_LEYES_POR_PAGINA);
  }, [votacionesFiltradas, paginaVotos]);

  const handleCambiarFiltroEje = (nuevoEje: 'Todos' | EjeTematico) => {
    setFiltroEjeVotos(nuevoEje);
    setPaginaVotos(1);
  };

  const handleCompartirPerfil = async () => {
    const shareUrl = window.location.href;
    if (navigator.share && perfil) {
      try {
        await navigator.share({
          title: `Radiografía de ${perfil.nombre_completo} en LegisData`,
          text: `Mirate las votaciones y evolución patrimonial en USD MEP de ${perfil.nombre_completo} (${perfil.bloque_actual}).`,
          url: shareUrl,
        });
        return;
      } catch {
        // continuar a portapapeles
      }
    }
    try {
      await navigator.clipboard.writeText(shareUrl);
      setCopiado(true);
      setTimeout(() => setCopiado(false), 2500);
    } catch {
      // ignore
    }
  };

  if (loading) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">
        <div className="h-48 rounded-2xl bg-white border border-slate-200/80 animate-pulse p-6" />
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          <div className="lg:col-span-7 h-96 rounded-2xl bg-white border border-slate-200/80 animate-pulse" />
          <div className="lg:col-span-5 h-96 rounded-2xl bg-white border border-slate-200/80 animate-pulse" />
        </div>
      </div>
    );
  }

  if (!perfil) {
    return (
      <div className="max-w-3xl mx-auto px-4 py-16 text-center">
        <div className="bg-white rounded-2xl border border-slate-200 p-10 shadow-sm">
          <h1 className="text-2xl font-bold text-slate-900">
            Legislador no encontrado
          </h1>
          <p className="text-slate-500 mt-2">
            El identificador solicitado no corresponde a ningún expediente activo.
          </p>
          <Link
            to="/"
            className="mt-6 inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Volver al buscador general</span>
          </Link>
        </div>
      </div>
    );
  }

  const evo = perfil.evolucion_patrimonial;
  const isPatrimonioCrecimiento = evo.crecimiento_real_usd_pct >= 0;
  const cargoLabel =
    perfil.camara === 'Senadores' ? 'Senador/a de la Nación' : 'Diputado/a de la Nación';
  const wikiUrl = `https://es.wikipedia.org/wiki/${encodeURIComponent(perfil.wiki_slug)}`;

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      {/* Navegación Breadcrumb */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <Link
          to="/"
          className="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-900 transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Volver al listado de representantes</span>
        </Link>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={handleCompartirPerfil}
            className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-sm transition-colors"
          >
            {copiado ? (
              <>
                <Check className="w-3.5 h-3.5 text-emerald-600" />
                <span className="text-emerald-700">Enlace copiado</span>
              </>
            ) : (
              <>
                <Share2 className="w-3.5 h-3.5 text-slate-500" />
                <span>Compartir Radiografía</span>
              </>
            )}
          </button>
        </div>
      </div>

      {/* 1. HEADER BIO + TARJETA DESTACADA KPI */}
      <section className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
          {/* Bio & Foto */}
          <div className="lg:col-span-8 flex flex-col sm:flex-row items-start gap-6">
            <AvatarLegislador
              src={perfil.foto_url}
              nombre={perfil.nombre_completo}
              size="xl"
            />

            <div className="space-y-3 flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <span className="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-900 text-white">
                  <Landmark className="w-3 h-3" />
                  {cargoLabel}
                </span>

                <span className="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
                  <MapPin className="w-3 h-3 text-slate-500" />
                  {perfil.provincia}
                </span>

                <span
                  className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${getBloqueBadgeStyle(
                    perfil.bloque_actual
                  )}`}
                >
                  Bloque {perfil.bloque_actual}
                </span>

                <span
                  className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-mono tabular-nums border ${
                    perfil.estado === 'Mandato Cumplido'
                      ? 'bg-amber-50 text-amber-800 border-amber-200'
                      : 'bg-emerald-50 text-emerald-700 border-emerald-200'
                  }`}
                >
                  <ShieldCheck className="w-3 h-3" />
                  {perfil.estado}
                </span>
              </div>

              <h1 className="text-2xl sm:text-4xl font-extrabold text-slate-900 tracking-[-0.02em]">
                {perfil.nombre_completo}
              </h1>

              <p className="text-base text-slate-600 font-normal leading-relaxed">
                {perfil.biografia}
              </p>

              <div className="flex flex-wrap items-center gap-4 pt-1 text-xs text-slate-500">
                <div>
                  Legajo Congreso:{' '}
                  <span className="font-mono tabular-nums font-semibold text-slate-800">
                    {perfil.id_congreso}
                  </span>
                </div>
                <span>·</span>
                <div>
                  CUIT Oficial:{' '}
                  <span className="font-mono tabular-nums font-semibold text-slate-800">
                    {perfil.cuit}
                  </span>
                </div>
                <span>·</span>
                <a
                  href={wikiUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1 font-medium text-blue-600 hover:text-blue-800"
                >
                  <span>Fuente Biográfica (Wikipedia)</span>
                  <ExternalLink className="w-3 h-3" />
                </a>
                {(perfil.url_comisiones || perfil.url_hcdn) && (
                  <>
                    <span>·</span>
                    <a
                      href={perfil.url_comisiones || perfil.url_hcdn}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-1 font-medium text-slate-700 hover:text-slate-900"
                    >
                      <Landmark className="w-3 h-3 text-slate-500" />
                      <span>Ficha Oficial HCDN · Comisiones</span>
                      <ExternalLink className="w-3 h-3" />
                    </a>
                  </>
                )}
              </div>
            </div>
          </div>

          {/* Tarjeta Destacada (KPIs de Asistencia Histórica y Lealtad Partidaria) */}
          <div className="lg:col-span-4 bg-slate-50 rounded-xl border border-slate-200/90 p-5 space-y-4">
            <div className="text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200 pb-2">
              Indicadores de Desempeño en Recinto
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="bg-white rounded-xl p-4 border border-slate-200/80 shadow-sm">
                <div className="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                  <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                  <span>Asistencia Histórica</span>
                </div>
                <div className="text-2xl sm:text-3xl font-mono tabular-nums font-extrabold text-slate-900 mt-1">
                  {formatPct(perfil.asistencia_historica)}
                </div>
                <div className="w-full h-1.5 rounded-full bg-slate-100 mt-2 overflow-hidden">
                  <div
                    className="h-1.5 rounded-full bg-emerald-500"
                    style={{ width: `${perfil.asistencia_historica}%` }}
                  />
                </div>
              </div>

              <div className="bg-white rounded-xl p-4 border border-slate-200/80 shadow-sm">
                <div className="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                  <Users className="w-4 h-4 text-blue-600" />
                  <span>Lealtad al Bloque</span>
                </div>
                <div className="text-2xl sm:text-3xl font-mono tabular-nums font-extrabold text-slate-900 mt-1">
                  {formatPct(perfil.lealtad_partidaria)}
                </div>
                <div className="w-full h-1.5 rounded-full bg-slate-100 mt-2 overflow-hidden">
                  <div
                    className="h-1.5 rounded-full bg-blue-500"
                    style={{ width: `${perfil.lealtad_partidaria}%` }}
                  />
                </div>
              </div>
            </div>

            <p className="text-[11px] text-slate-500 leading-snug">
              Métricas calculadas sobre el registro nominal de sesiones ordinarias y
              extraordinarias en {perfil.camara}.
            </p>
          </div>
        </div>
      </section>

      {/* GRILLA PRINCIPAL: MÓDULO FINANCIERO + MÓDULO IDEOLÓGICO */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {/* 2. MÓDULO FINANCIERO (EVOLUCIÓN PATRIMONIAL REAL EN USD MEP E IPC) */}
        <section className="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-7 flex flex-col justify-between">
          <div>
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100">
              <div>
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
                  Módulo Financiero · Oficina Anticorrupción
                </span>
                <h2 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-[-0.02em] mt-0.5">
                  Evolución Patrimonial Real (USD MEP)
                </h2>
              </div>

              {/* Indicador con flecha verde o roja que resalta el Crecimiento real en USD durante su mandato */}
              <div
                className={`inline-flex items-center gap-2 px-3.5 py-2 rounded-xl font-mono tabular-nums text-sm font-bold self-start sm:self-auto ${
                  isPatrimonioCrecimiento
                    ? 'text-emerald-700 bg-emerald-100 border border-emerald-200'
                    : 'text-rose-700 bg-rose-100 border border-rose-200'
                }`}
              >
                {isPatrimonioCrecimiento ? (
                  <TrendingUp className="w-4 h-4 shrink-0" />
                ) : (
                  <TrendingDown className="w-4 h-4 shrink-0" />
                )}
                <div>
                  <div className="text-[10px] font-sans uppercase tracking-wider opacity-80">
                    Crecimiento real en USD ({evo.anio_inicio}–{evo.anio_fin})
                  </div>
                  <div className="text-base leading-tight">
                    {formatPct(evo.crecimiento_real_usd_pct, true)}
                  </div>
                </div>
              </div>
            </div>

            {/* Tarjetas resumen del período */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 my-5">
              <div className="rounded-xl bg-slate-50 border border-slate-200/70 p-3.5">
                <div className="text-xs text-slate-500">
                  Patrimonio Inicial ({evo.anio_inicio})
                </div>
                <div className="text-lg font-mono tabular-nums font-bold text-slate-900 mt-0.5">
                  {formatUSD(evo.patrimonio_usd_inicial)}
                </div>
                <div className="text-[11px] font-mono tabular-nums text-slate-400">
                  ARS nominal:{' '}
                  {formatARS(evo.serie[0]?.monto_pesos_nominal ?? 0)}
                </div>
              </div>

              <div className="rounded-xl bg-slate-50 border border-slate-200/70 p-3.5">
                <div className="text-xs text-slate-500">
                  Última DDJJ ({evo.anio_fin})
                </div>
                <div className="text-lg font-mono tabular-nums font-bold text-slate-900 mt-0.5">
                  {formatUSD(evo.patrimonio_usd_actual)}
                </div>
                <div className="text-[11px] font-mono tabular-nums text-slate-400">
                  ARS nominal:{' '}
                  {formatARS(
                    evo.serie[evo.serie.length - 1]?.monto_pesos_nominal ?? 0
                  )}
                </div>
              </div>

              <div className="rounded-xl bg-slate-50 border border-slate-200/70 p-3.5">
                <div className="text-xs text-slate-500">
                  Variación en ARS Constantes (IPC)
                </div>
                <div
                  className={`text-lg font-mono tabular-nums font-bold mt-0.5 ${
                    evo.crecimiento_pesos_constantes_pct >= 0
                      ? 'text-emerald-700'
                      : 'text-rose-700'
                  }`}
                >
                  {formatPct(evo.crecimiento_pesos_constantes_pct, true)}
                </div>
                <div className="text-[11px] text-slate-400">
                  Deflactado por IPC INDEC base {evo.moneda_constante_base}
                </div>
              </div>
            </div>

            {/* Gráfico de Líneas Suavizado (tension: 0.4) con Tooltip Interactivo */}
            <PatrimonioLineChart
              serie={evo.serie}
              monedaConstanteBase={evo.moneda_constante_base}
            />
          </div>
        </section>

        {/* 3. MÓDULO IDEOLÓGICO (RADAR + BARRAS DE PROGRESO TEMÁTICAS) */}
        <section className="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-7 flex flex-col justify-between">
          <div>
            <div className="pb-4 border-b border-slate-100">
              <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
                Módulo Ideológico · Huella de Votación
              </span>
              <h2 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-[-0.02em] mt-0.5">
                Posicionamiento por Ejes Temáticos
              </h2>
              <p className="text-xs text-slate-500 mt-1">
                Calculado ponderando cada voto por la relevancia de la ley y excluyendo ausencias.
              </p>
            </div>

            <div className="my-4">
              <IdeologiaRadarChart
                vectorPrincipal={perfil.vector_ideologico}
                labelPrincipal={perfil.nombre_completo}
              />
            </div>

            <div className="pt-4 border-t border-slate-100">
              <BarrasTematicas vector={perfil.vector_ideologico} mostrarLaboral={true} />
            </div>
          </div>
        </section>
      </div>

      {/* DESGLOSE DETALLADO DE DECLARACIÓN JURADA (OFICINA ANTICORRUPCIÓN) */}
      {perfil.desglose_ddjj && (
        <DesgloseDdjjCard
          desglose={perfil.desglose_ddjj}
          nombreLegislador={perfil.nombre_completo}
        />
      )}

      {/* TABLA INTERACTIVA DE VOTACIONES (PAGINACIÓN MÁXIMO 10 LEYES POR PÁGINA) */}
      <section className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div className="p-6 sm:p-7 border-b border-slate-200/80 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-400">
              <FileText className="w-3.5 h-3.5" />
              <span>Registro Nominal Auditable</span>
            </div>
            <h2 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-[-0.02em] mt-0.5">
              Historial de Votaciones en Leyes Clave
            </h2>
          </div>

          {/* Filtro rápido por eje temático */}
          <div className="flex flex-wrap items-center gap-1.5">
            {(
              [
                'Todos',
                'Educacion',
                'Salud',
                'MercadoLibre',
                'Proteccionismo',
                'Laboral',
              ] as ('Todos' | EjeTematico)[]
            ).map((eje) => {
              const active = filtroEjeVotos === eje;
              const label =
                eje === 'Todos' ? 'Todos los ejes' : EJE_TEMATICO_META[eje].shortLabel;
              return (
                <button
                  key={eje}
                  type="button"
                  onClick={() => handleCambiarFiltroEje(eje)}
                  className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                    active
                      ? 'bg-slate-900 text-white shadow-sm'
                      : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                  }`}
                >
                  {label}
                </button>
              );
            })}
          </div>
        </div>

        {/* Tabla Mobile-First con desplazamiento horizontal fluido */}
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse min-w-[680px]">
            <thead className="bg-slate-50 uppercase text-xs font-semibold text-slate-500 border-b border-slate-200/80">
              <tr>
                <th className="py-3.5 px-5">Expediente / Ley</th>
                <th className="py-3.5 px-5">Proyecto y Síntesis Parlamentaria</th>
                <th className="py-3.5 px-5">Eje Temático</th>
                <th className="py-3.5 px-5">Fecha</th>
                <th className="py-3.5 px-5 text-right">Sentido del Voto</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-sm">
              {votacionesPaginadas.map((voto) => {
                const ejeMeta = EJE_TEMATICO_META[voto.eje_tematico];
                return (
                  <tr
                    key={voto.ley_id}
                    className="border-b border-slate-100 hover:bg-slate-50 transition-colors"
                  >
                    <td className="py-4 px-5 align-top whitespace-nowrap">
                      <span className="font-mono tabular-nums font-bold text-xs px-2 py-1 rounded bg-slate-100 text-slate-800 border border-slate-200">
                        {voto.ley_id}
                      </span>
                      <div className="text-[11px] font-mono tabular-nums text-slate-400 mt-1">
                        Peso: {voto.peso_ideologico.toFixed(2)}
                      </div>
                    </td>

                    <td className="py-4 px-5 align-top max-w-md">
                      <div className="font-bold text-slate-900 leading-snug">
                        {voto.titulo}
                      </div>
                      <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                        {voto.resumen}
                      </p>
                    </td>

                    <td className="py-4 px-5 align-top whitespace-nowrap">
                      <span
                        className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ${ejeMeta.badgeClass}`}
                      >
                        <span className={`w-2 h-2 rounded-full ${ejeMeta.barClass}`} />
                        {ejeMeta.label}
                      </span>
                    </td>

                    <td className="py-4 px-5 align-top whitespace-nowrap font-mono tabular-nums text-xs text-slate-600">
                      {voto.fecha_votacion}
                    </td>

                    <td className="py-4 px-5 align-top whitespace-nowrap text-right">
                      <span
                        className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-mono tabular-nums font-bold ${getVotoBadgeClasses(
                          voto.sentido_voto
                        )}`}
                      >
                        {getVotoLabel(voto.sentido_voto)}
                      </span>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* Controles de Paginación (Máximo 10 leyes por página) */}
        <div className="px-6 py-4 bg-slate-50 border-t border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600">
          <div>
            Mostrando{' '}
            <span className="font-mono tabular-nums font-bold text-slate-900">
              {(paginaVotos - 1) * MAX_LEYES_POR_PAGINA + 1}
            </span>{' '}
            a{' '}
            <span className="font-mono tabular-nums font-bold text-slate-900">
              {Math.min(
                paginaVotos * MAX_LEYES_POR_PAGINA,
                votacionesFiltradas.length
              )}
            </span>{' '}
            de{' '}
            <span className="font-mono tabular-nums font-bold text-slate-900">
              {votacionesFiltradas.length}
            </span>{' '}
            expedientes (máx. {MAX_LEYES_POR_PAGINA} por página)
          </div>

          <div className="flex items-center gap-2">
            <button
              type="button"
              disabled={paginaVotos <= 1}
              onClick={() => setPaginaVotos((p) => Math.max(1, p - 1))}
              className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:pointer-events-none transition-colors"
            >
              <ChevronLeft className="w-4 h-4" />
              <span>Anterior</span>
            </button>

            <span className="font-mono tabular-nums font-semibold px-2">
              Página {paginaVotos} de {totalPaginas}
            </span>

            <button
              type="button"
              disabled={paginaVotos >= totalPaginas}
              onClick={() => setPaginaVotos((p) => Math.min(totalPaginas, p + 1))}
              className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:pointer-events-none transition-colors"
            >
              <span>Siguiente</span>
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      </section>
    </div>
  );
};
