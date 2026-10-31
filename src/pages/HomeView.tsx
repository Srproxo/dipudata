import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  Search,
  Sparkles,
  Filter,
  RotateCcw,
  Users,
  TrendingUp,
  FileSpreadsheet,
  ArrowRight,
  X,
} from 'lucide-react';
import { fetchLegisladores } from '../services/api';
import { LegisladorResumen } from '../types/legisdata';
import { LegisladorCard } from '../components/LegisladorCard';
import { SelectorMapaProvincias } from '../components/SelectorMapaProvincias';
import { AvatarLegislador } from '../components/AvatarLegislador';
import { actualizarMetaSeo } from '../utils/seo';
import { formatPct, getBloqueBadgeStyle, getLegisladorUrl } from '../utils/formatters';

type FiltroCamara = 'Todos' | 'Diputados' | 'Senadores';

export const HomeView: React.FC = () => {
  const [legisladores, setLegisladores] = useState<LegisladorResumen[]>([]);
  const [loading, setLoading] = useState<boolean>(true);

  const [busqueda, setBusqueda] = useState<string>('');
  const [mostrarSugerencias, setMostrarSugerencias] = useState<boolean>(false);
  const [filtroCamara, setFiltroCamara] = useState<FiltroCamara>('Todos');
  const [filtroProvincia, setFiltroProvincia] = useState<string>('Todas');
  const [filtroBloque, setFiltroBloque] = useState<string>('Todos');

  const searchContainerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    actualizarMetaSeo({
      title: 'LegisData | DipuData — Conocé a tus representantes, Votaciones y DDJJ en USD MEP',
      description:
        'Buscador ciudadano de Diputados y Senadores de la Nación Argentina. Auditá votaciones nominales, asistencia histórica, lealtad partidaria y evolución patrimonial real en USD MEP.',
      canonicalPath: '/',
      ogImage: 'https://dipudata.com/api/og?type=home',
    });

    fetchLegisladores().then((data) => {
      setLegisladores(data);
      setLoading(false);
    });
  }, []);

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (
        searchContainerRef.current &&
        !searchContainerRef.current.contains(e.target as Node)
      ) {
        setMostrarSugerencias(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const provinciasDisponibles = useMemo(() => {
    const setProv = new Set(legisladores.map((l) => l.provincia));
    return ['Todas', ...Array.from(setProv).sort()];
  }, [legisladores]);

  const bloquesDisponibles = useMemo(() => {
    const setBloq = new Set(legisladores.map((l) => l.bloque_actual));
    return ['Todos', ...Array.from(setBloq).sort()];
  }, [legisladores]);

  const sugerenciasAutocompletado = useMemo(() => {
    const q = busqueda.trim().toLowerCase();
    if (!q) return [];
    return legisladores
      .filter(
        (l) =>
          l.nombre_completo.toLowerCase().includes(q) ||
          l.bloque_actual.toLowerCase().includes(q) ||
          l.provincia.toLowerCase().includes(q)
      )
      .slice(0, 6);
  }, [legisladores, busqueda]);

  const legisladoresFiltrados = useMemo(() => {
    const q = busqueda.trim().toLowerCase();
    return legisladores.filter((leg) => {
      if (filtroCamara !== 'Todos' && leg.camara !== filtroCamara) {
        return false;
      }
      if (filtroProvincia !== 'Todas' && leg.provincia !== filtroProvincia) {
        return false;
      }
      if (filtroBloque !== 'Todos' && leg.bloque_actual !== filtroBloque) {
        return false;
      }
      if (q) {
        const matchText =
          leg.nombre_completo.toLowerCase().includes(q) ||
          leg.bloque_actual.toLowerCase().includes(q) ||
          leg.provincia.toLowerCase().includes(q) ||
          leg.cuit.toLowerCase().includes(q);
        if (!matchText) return false;
      }
      return true;
    });
  }, [legisladores, busqueda, filtroCamara, filtroProvincia, filtroBloque]);

  const promedioAsistencia = useMemo(() => {
    if (legisladoresFiltrados.length === 0) return 0;
    const sum = legisladoresFiltrados.reduce(
      (acc, l) => acc + l.asistencia_historica,
      0
    );
    return sum / legisladoresFiltrados.length;
  }, [legisladoresFiltrados]);

  const limpiarFiltros = () => {
    setBusqueda('');
    setFiltroCamara('Todos');
    setFiltroProvincia('Todas');
    setFiltroBloque('Todos');
  };

  const hayFiltrosActivos =
    busqueda.trim() !== '' ||
    filtroCamara !== 'Todos' ||
    filtroProvincia !== 'Todas' ||
    filtroBloque !== 'Todos';

  return (
    <div className="space-y-8">
      {/* HERO SECTION */}
      <section className="bg-white border-b border-slate-200/80 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
          <div className="max-w-3xl">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-xs font-medium text-slate-700 mb-4">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
              <span>Monitor Legislativo &amp; Patrimonial Abierto</span>
            </div>

            <h1 className="text-3xl sm:text-5xl font-extrabold text-slate-900 tracking-[-0.02em] leading-tight">
              Conocé a tus representantes
            </h1>

            <p className="mt-3 text-base sm:text-lg text-slate-500 font-normal leading-relaxed">
              Auditá con neutralidad visual cómo votan los Diputados y Senadores de la Nación,
              su asistencia histórica y la evolución real de sus Declaraciones Juradas deflactadas
              en <span className="font-mono font-medium text-slate-800">USD MEP</span> e{' '}
              <span className="font-mono font-medium text-slate-800">IPC</span>.
            </p>
          </div>

          {/* BUSCADOR PRINCIPAL CON AUTOCOMPLETADO */}
          <div ref={searchContainerRef} className="relative mt-7 max-w-3xl">
            <div className="relative flex items-center">
              <Search className="w-5 h-5 text-slate-400 absolute left-4 pointer-events-none" />
              <input
                type="search"
                value={busqueda}
                onFocus={() => setMostrarSugerencias(true)}
                onChange={(e) => {
                  setBusqueda(e.target.value);
                  setMostrarSugerencias(true);
                }}
                placeholder="Buscar por nombre (ej. Menem, Lousteau, Santoro), bloque o provincia..."
                className="w-full pl-12 pr-12 py-4 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 placeholder:text-slate-400 text-base focus:outline-none focus:bg-white focus:ring-2 focus:ring-slate-900 focus:border-transparent shadow-sm transition-all"
                aria-label="Buscar legislador por nombre, provincia o bloque"
              />
              {busqueda && (
                <button
                  type="button"
                  onClick={() => setBusqueda('')}
                  className="absolute right-4 p-1 text-slate-400 hover:text-slate-700"
                  aria-label="Limpiar búsqueda"
                >
                  <X className="w-4 h-4" />
                </button>
              )}
            </div>

            {/* Dropdown de Autocompletado Instantáneo */}
            {mostrarSugerencias && sugerenciasAutocompletado.length > 0 && (
              <div className="absolute left-0 right-0 mt-2 bg-white rounded-xl border border-slate-200 shadow-lg z-30 overflow-hidden divide-y divide-slate-100">
                <div className="px-3.5 py-2 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-500 flex items-center justify-between">
                  <span>Acceso rápido a radiografía</span>
                  <span className="font-mono tabular-nums">
                    {sugerenciasAutocompletado.length} coincidencias
                  </span>
                </div>
                {sugerenciasAutocompletado.map((sug) => (
                  <button
                    key={sug.id}
                    type="button"
                    onClick={() => {
                      setMostrarSugerencias(false);
                      window.location.href = getLegisladorUrl(sug);
                    }}
                    className="w-full px-4 py-3 text-left hover:bg-slate-50 flex items-center justify-between gap-3 transition-colors"
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      <AvatarLegislador
                        src={sug.foto_url}
                        nombre={sug.nombre_completo}
                        size="sm"
                      />
                      <div className="min-w-0">
                        <div className="text-sm font-bold text-slate-900 truncate">
                          {sug.nombre_completo}
                        </div>
                        <div className="text-xs text-slate-500 truncate">
                          {sug.camara} · {sug.provincia}
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                      <span
                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${getBloqueBadgeStyle(
                          sug.bloque_actual
                        )}`}
                      >
                        {sug.bloque_actual}
                      </span>
                      <ArrowRight className="w-4 h-4 text-slate-400" />
                    </div>
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* FILTROS RÁPIDOS (Toggles Todos / Diputados / Senadores + Selectores de Provincia y Bloque) */}
          <div className="mt-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4 pt-6 border-t border-slate-100">
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-xs font-semibold uppercase tracking-wider text-slate-400 mr-1 flex items-center gap-1">
                <Filter className="w-3.5 h-3.5" />
                Cámara:
              </span>
              {(['Todos', 'Diputados', 'Senadores'] as FiltroCamara[]).map((cam) => {
                const active = filtroCamara === cam;
                return (
                  <button
                    key={cam}
                    type="button"
                    onClick={() => setFiltroCamara(cam)}
                    className={`px-4 py-2 rounded-lg text-sm font-semibold transition-all ${
                      active
                        ? 'bg-slate-900 text-white shadow-sm'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900'
                    }`}
                  >
                    {cam}
                  </button>
                );
              })}
            </div>

            <div className="flex flex-wrap items-center gap-3">
              {/* Selector de Provincia */}
              <div className="flex items-center gap-2">
                <label
                  htmlFor="select-provincia"
                  className="text-xs font-semibold text-slate-500 uppercase"
                >
                  Provincia:
                </label>
                <select
                  id="select-provincia"
                  value={filtroProvincia}
                  onChange={(e) => setFiltroProvincia(e.target.value)}
                  className="rounded-lg bg-slate-50 border border-slate-300 px-3 py-2 text-sm text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-slate-900"
                >
                  {provinciasDisponibles.map((prov) => (
                    <option key={prov} value={prov}>
                      {prov}
                    </option>
                  ))}
                </select>
              </div>

              {/* Selector de Bloque */}
              <div className="flex items-center gap-2">
                <label
                  htmlFor="select-bloque"
                  className="text-xs font-semibold text-slate-500 uppercase"
                >
                  Bloque:
                </label>
                <select
                  id="select-bloque"
                  value={filtroBloque}
                  onChange={(e) => setFiltroBloque(e.target.value)}
                  className="rounded-lg bg-slate-50 border border-slate-300 px-3 py-2 text-sm text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-slate-900"
                >
                  {bloquesDisponibles.map((bloq) => (
                    <option key={bloq} value={bloq}>
                      {bloq}
                    </option>
                  ))}
                </select>
              </div>

              {hayFiltrosActivos && (
                <button
                  type="button"
                  onClick={limpiarFiltros}
                  className="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors"
                >
                  <RotateCcw className="w-3.5 h-3.5" />
                  <span>Restablecer</span>
                </button>
              )}
            </div>
          </div>
        </div>
      </section>

      {/* CONTENIDO PRINCIPAL */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        {/* Banner CTA hacia Match Electoral */}
        <div className="bg-white rounded-xl border border-slate-200/80 shadow-sm p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div className="flex items-start sm:items-center gap-3.5">
            <div className="w-10 h-10 rounded-xl bg-blue-50 border border-blue-200 text-blue-600 flex items-center justify-center shrink-0">
              <Sparkles className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-sm sm:text-base font-bold text-slate-900 tracking-[-0.02em]">
                ¿Con qué legislador tenés mayor coincidencia real de voto?
              </h2>
              <p className="text-xs sm:text-sm text-slate-500">
                Respondé 8 preguntas sobre leyes reales sancionadas y calculá tu Similitud de
                Coseno con todos los bloques.
              </p>
            </div>
          </div>

          <a
            href="/match-360"
            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800 transition-colors shrink-0"
          >
            <span>Iniciar Match Electoral 360</span>
            <ArrowRight className="w-4 h-4" />
          </a>
        </div>

        {/* Selector Visual Interactivo por Regiones y Provincias */}
        <SelectorMapaProvincias
          legisladores={legisladores}
          provinciaSeleccionada={filtroProvincia}
          onSelectProvincia={setFiltroProvincia}
        />

        {/* Barra de resumen estadístico de la consulta actual */}
        <div className="flex flex-wrap items-center justify-between gap-4 pt-2">
          <div className="flex items-center gap-2">
            <Users className="w-4 h-4 text-slate-500" />
            <span className="text-sm font-semibold text-slate-900">
              Mostrando{' '}
              <span className="font-mono tabular-nums font-bold">
                {legisladoresFiltrados.length}
              </span>{' '}
              de{' '}
              <span className="font-mono tabular-nums">
                {legisladores.length}
              </span>{' '}
              legisladores auditados
            </span>
          </div>

          <div className="flex flex-wrap items-center gap-4 text-xs text-slate-500">
            <span className="inline-flex items-center gap-1.5">
              <TrendingUp className="w-3.5 h-3.5 text-emerald-600" />
              Asistencia media del filtro:{' '}
              <strong className="font-mono tabular-nums text-slate-800">
                {formatPct(promedioAsistencia)}
              </strong>
            </span>
            <span className="hidden sm:inline">·</span>
            <span className="inline-flex items-center gap-1.5">
              <FileSpreadsheet className="w-3.5 h-3.5 text-slate-500" />
              DDJJ deflactadas a{' '}
              <strong className="font-mono tabular-nums text-slate-800">
                USD MEP 2020–2025
              </strong>
            </span>
          </div>
        </div>

        {/* GRILLA DE TARJETAS DE LEGISLADOR */}
        {loading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            {Array.from({ length: 6 }).map((_, idx) => (
              <div
                key={idx}
                className="h-56 rounded-xl bg-white border border-slate-200/80 p-5 animate-pulse flex flex-col justify-between"
              >
                <div className="flex items-center gap-4">
                  <div className="w-16 h-16 rounded-full bg-slate-200" />
                  <div className="space-y-2 flex-1">
                    <div className="h-4 bg-slate-200 rounded w-3/4" />
                    <div className="h-3 bg-slate-100 rounded w-1/2" />
                  </div>
                </div>
                <div className="h-12 bg-slate-100 rounded-lg" />
              </div>
            ))}
          </div>
        ) : legisladoresFiltrados.length === 0 ? (
          <div className="bg-white rounded-xl border border-slate-200/80 shadow-sm p-12 text-center max-w-lg mx-auto">
            <p className="text-base font-bold text-slate-900">
              No se encontraron legisladores con esos criterios
            </p>
            <p className="text-sm text-slate-500 mt-1">
              Probá limpiando los filtros de provincia, bloque o término de búsqueda.
            </p>
            <button
              type="button"
              onClick={limpiarFiltros}
              className="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800 transition-colors"
            >
              <RotateCcw className="w-4 h-4" />
              <span>Ver todos los legisladores</span>
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            {legisladoresFiltrados.map((leg) => (
              <LegisladorCard key={leg.id} legislador={leg} />
            ))}
          </div>
        )}
      </div>
    </div>
  );
};
