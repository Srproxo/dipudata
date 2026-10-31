import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  Search,
  Sparkles,
  Filter,
  RotateCcw,
  Users,
  TrendingUp,
  ArrowRight,
  X,
  LayoutGrid,
  List,
  Vote,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
} from 'lucide-react';
import { fetchLegisladores } from '../services/api';
import { LegisladorResumen } from '../types/legisdata';
import { LEYES_SEED, TODAS_PROVINCIAS_ARGENTINA } from '../data/seedData';
import { LegisladorCard } from '../components/LegisladorCard';
import { SelectorMapaProvincias } from '../components/SelectorMapaProvincias';
import { AvatarLegislador } from '../components/AvatarLegislador';
import { actualizarMetaSeo } from '../utils/seo';
import {
  formatPct,
  formatUSD,
  getBloqueBadgeStyle,
  getLegisladorUrl,
} from '../utils/formatters';

type FiltroCamara = 'Todos' | 'Diputados' | 'Senadores';
type FiltroEstado = 'Todos' | 'Activo' | 'Mandato Cumplido';
type FiltroPresentismo = 'Todos' | 'excelente' | 'alta' | 'baja';
type FiltroCapital = 'Todos' | 'alto' | 'medio' | 'inicial';
type FiltroMandatos = 'Todos' | '1' | '2' | '3+';
type OrdenCriterio =
  | 'nombre-asc'
  | 'nombre-desc'
  | 'asistencia-desc'
  | 'asistencia-asc'
  | 'patrimonio-desc'
  | 'patrimonio-asc'
  | 'mandatos-desc'
  | 'mandatos-asc'
  | 'lealtad-desc'
  | 'crecimiento-desc';

const ESPACIOS_POLITICOS: Record<string, string[]> = {
  'Oficialismo y Aliados': ['La Libertad Avanza', 'PRO'],
  'Oposición Dialoguista': [
    'UCR',
    'Encuentro Federal',
    'Coalición Cívica',
    'Innovación Federal',
  ],
  'Oposición Fuerte': ['Unión por la Patria', 'Frente de Izquierda'],
};

const EJES_TEMATICOS = [
  { id: 'Todos', label: 'Todos los ejes temáticos' },
  { id: 'MercadoLibre', label: 'Mercado Libre y Desregulación' },
  { id: 'Educacion', label: 'Educación y Universidades' },
  { id: 'Salud', label: 'Salud Pública y Sanidad' },
  { id: 'Proteccionismo', label: 'Proteccionismo e Impuestos' },
  { id: 'Laboral', label: 'Leyes Laborales y Sindicales' },
];

export const HomeView: React.FC = () => {
  const [legisladores, setLegisladores] = useState<LegisladorResumen[]>([]);
  const [loading, setLoading] = useState<boolean>(true);

  // Estados de Búsqueda y Navegación
  const [busqueda, setBusqueda] = useState<string>('');
  const [mostrarSugerencias, setMostrarSugerencias] = useState<boolean>(false);
  const [modoVista, setModoVista] = useState<'grid' | 'tabla'>('grid');
  const [mostrarFiltrosAvanzados, setMostrarFiltrosAvanzados] = useState<boolean>(false);

  // Filtros Básicos
  const [filtroCamara, setFiltroCamara] = useState<FiltroCamara>('Todos');
  const [filtroEstado, setFiltroEstado] = useState<FiltroEstado>('Todos');
  const [filtroProvincia, setFiltroProvincia] = useState<string>('Todas');
  const [filtroBloque, setFiltroBloque] = useState<string>('Todos');

  // Filtros Potentes: Presentismo, Capital y Mandatos
  const [filtroPresentismo, setFiltroPresentismo] = useState<FiltroPresentismo>('Todos');
  const [filtroCapital, setFiltroCapital] = useState<FiltroCapital>('Todos');
  const [filtroMandatos, setFiltroMandatos] = useState<FiltroMandatos>('Todos');

  // Filtros Avanzados (Votaciones, Leyes, Partidos y Ejes)
  const [filtroEspacio, setFiltroEspacio] = useState<string>('Todos');
  const [filtroLey, setFiltroLey] = useState<string>('Todas');
  const [filtroSentidoVoto, setFiltroSentidoVoto] = useState<string>('Todos');
  const [filtroEje, setFiltroEje] = useState<string>('Todos');
  const [filtroPosturaEje, setFiltroPosturaEje] = useState<string>('Todos');

  // Ordenamiento
  const [orden, setOrden] = useState<OrdenCriterio>('nombre-asc');

  const searchContainerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    actualizarMetaSeo({
      title: 'LegisData | DipuData — Buscador y Auditoría de Diputados y Senadores',
      description:
        'Auditá cómo votan los Diputados y Senadores de la Nación por ley, bancada, provincia y estado. Asistencia histórica y evolución patrimonial real.',
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
    const setProv = new Set([
      ...TODAS_PROVINCIAS_ARGENTINA,
      ...legisladores.map((l) => l.provincia),
    ]);
    return ['Todas', ...Array.from(setProv).sort((a, b) => a.localeCompare(b, 'es'))];
  }, [legisladores]);

  const bloquesDisponibles = useMemo(() => {
    const setBloq = new Set(legisladores.map((l) => l.bloque_actual));
    return ['Todos', ...Array.from(setBloq).sort()];
  }, [legisladores]);

  const leySeleccionada = useMemo(() => {
    if (filtroLey === 'Todas') return null;
    return LEYES_SEED.find((l) => l.id === filtroLey) ?? null;
  }, [filtroLey]);

  const sugerenciasAutocompletado = useMemo(() => {
    const q = busqueda.trim().toLowerCase();
    if (!q) return [];
    return legisladores
      .filter(
        (l) =>
          l.nombre_completo.toLowerCase().includes(q) ||
          l.bloque_actual.toLowerCase().includes(q) ||
          l.provincia.toLowerCase().includes(q) ||
          l.cuit.includes(q)
      )
      .slice(0, 6);
  }, [legisladores, busqueda]);

  // FILTRADO Y ORDENAMIENTO EN MEMORIA
  const legisladoresFiltrados = useMemo(() => {
    const q = busqueda.trim().toLowerCase();

    const filtrados = legisladores.filter((leg) => {
      // 1. Cámara
      if (filtroCamara !== 'Todos') {
        const esSenadorLeg = (leg.camara || '').toLowerCase().startsWith('senad');
        const esSenadorFiltro = filtroCamara.toLowerCase().startsWith('senad');
        if (esSenadorLeg !== esSenadorFiltro) {
          return false;
        }
      }

      // 2. Estado (Activos vs. Mandato Cumplido)
      if (filtroEstado === 'Activo' && leg.estado !== 'Activo') {
        return false;
      }
      if (filtroEstado === 'Mandato Cumplido' && leg.estado === 'Activo') {
        return false;
      }

      // 3. Provincia
      if (filtroProvincia !== 'Todas' && leg.provincia !== filtroProvincia) {
        return false;
      }

      // 4. Bloque
      if (filtroBloque !== 'Todos' && leg.bloque_actual !== filtroBloque) {
        return false;
      }

      // 5. Espacio Político / Tendencia
      if (filtroEspacio !== 'Todos') {
        const bloquesPermitidos = ESPACIOS_POLITICOS[filtroEspacio] || [];
        if (!bloquesPermitidos.includes(leg.bloque_actual)) {
          return false;
        }
      }

      // 6. Voto en Ley Específica
      if (filtroLey !== 'Todas') {
        const voto = leg.votos ? leg.votos[filtroLey] : undefined;
        if (filtroSentidoVoto !== 'Todos') {
          if (voto !== filtroSentidoVoto) return false;
        } else {
          if (!voto) return false;
        }
      }

      // 7. Eje Temático y Postura
      if (filtroEje !== 'Todos') {
        const score =
          leg.vector_ideologico[filtroEje as keyof typeof leg.vector_ideologico] ?? 50;
        if (filtroPosturaEje === 'Favor' && score < 50) return false;
        if (filtroPosturaEje === 'Contra' && score >= 50) return false;
        if (filtroPosturaEje === 'Alta' && score < 75) return false;
      }

      // 8. Filtro de Presentismo / Asistencia
      if (filtroPresentismo === 'excelente' && leg.asistencia_historica < 96) {
        return false;
      }
      if (filtroPresentismo === 'alta' && (leg.asistencia_historica < 93 || leg.asistencia_historica >= 96)) {
        return false;
      }
      if (filtroPresentismo === 'baja' && leg.asistencia_historica >= 93) {
        return false;
      }

      // 9. Filtro de Capital / Patrimonio (USD MEP)
      const capUsd = leg.patrimonio_usd_actual || 0;
      if (filtroCapital === 'alto' && capUsd < 400000) {
        return false;
      }
      if (filtroCapital === 'medio' && (capUsd < 100000 || capUsd >= 400000)) {
        return false;
      }
      if (filtroCapital === 'inicial' && capUsd >= 100000) {
        return false;
      }

      // 10. Filtro de Mandatos Parlamentarios
      const cantMandatos = leg.mandatos || 1;
      if (filtroMandatos === '1' && cantMandatos !== 1) {
        return false;
      }
      if (filtroMandatos === '2' && cantMandatos !== 2) {
        return false;
      }
      if (filtroMandatos === '3+' && cantMandatos < 3) {
        return false;
      }

      // 11. Búsqueda por texto
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

    // Ordenamiento
    return [...filtrados].sort((a, b) => {
      switch (orden) {
        case 'nombre-asc':
          return a.nombre_completo.localeCompare(b.nombre_completo);
        case 'nombre-desc':
          return b.nombre_completo.localeCompare(a.nombre_completo);
        case 'asistencia-desc':
          return b.asistencia_historica - a.asistencia_historica;
        case 'asistencia-asc':
          return a.asistencia_historica - b.asistencia_historica;
        case 'lealtad-desc':
          return b.lealtad_partidaria - a.lealtad_partidaria;
        case 'patrimonio-desc':
          return (b.patrimonio_usd_actual || 0) - (a.patrimonio_usd_actual || 0);
        case 'patrimonio-asc':
          return (a.patrimonio_usd_actual || 0) - (b.patrimonio_usd_actual || 0);
        case 'mandatos-desc':
          return (b.mandatos || 1) - (a.mandatos || 1);
        case 'mandatos-asc':
          return (a.mandatos || 1) - (b.mandatos || 1);
        case 'crecimiento-desc':
          return (b.crecimiento_real_usd_pct || 0) - (a.crecimiento_real_usd_pct || 0);
        default:
          return 0;
      }
    });
  }, [
    legisladores,
    busqueda,
    filtroCamara,
    filtroEstado,
    filtroProvincia,
    filtroBloque,
    filtroPresentismo,
    filtroCapital,
    filtroMandatos,
    filtroEspacio,
    filtroLey,
    filtroSentidoVoto,
    filtroEje,
    filtroPosturaEje,
    orden,
  ]);

  const promedioAsistencia = useMemo(() => {
    if (legisladoresFiltrados.length === 0) return 0;
    const sum = legisladoresFiltrados.reduce(
      (acc, l) => acc + l.asistencia_historica,
      0
    );
    return sum / legisladoresFiltrados.length;
  }, [legisladoresFiltrados]);

  const promedioLealtad = useMemo(() => {
    if (legisladoresFiltrados.length === 0) return 0;
    const sum = legisladoresFiltrados.reduce(
      (acc, l) => acc + l.lealtad_partidaria,
      0
    );
    return sum / legisladoresFiltrados.length;
  }, [legisladoresFiltrados]);

  const cantidadFiltrosAvanzadosActivos = useMemo(() => {
    let count = 0;
    if (filtroEspacio !== 'Todos') count++;
    if (filtroLey !== 'Todas') count++;
    if (filtroSentidoVoto !== 'Todos') count++;
    if (filtroEje !== 'Todos') count++;
    if (filtroPosturaEje !== 'Todos') count++;
    return count;
  }, [filtroEspacio, filtroLey, filtroSentidoVoto, filtroEje, filtroPosturaEje]);

  const hayFiltrosActivos =
    busqueda.trim() !== '' ||
    filtroCamara !== 'Todos' ||
    filtroEstado !== 'Todos' ||
    filtroProvincia !== 'Todas' ||
    filtroBloque !== 'Todos' ||
    filtroPresentismo !== 'Todos' ||
    filtroCapital !== 'Todos' ||
    filtroMandatos !== 'Todos' ||
    cantidadFiltrosAvanzadosActivos > 0 ||
    orden !== 'nombre-asc';

  const limpiarFiltros = () => {
    setBusqueda('');
    setFiltroCamara('Todos');
    setFiltroEstado('Todos');
    setFiltroProvincia('Todas');
    setFiltroBloque('Todos');
    setFiltroPresentismo('Todos');
    setFiltroCapital('Todos');
    setFiltroMandatos('Todos');
    setFiltroEspacio('Todos');
    setFiltroLey('Todas');
    setFiltroSentidoVoto('Todos');
    setFiltroEje('Todos');
    setFiltroPosturaEje('Todos');
    setOrden('nombre-asc');
  };

  return (
    <div className="space-y-8">
      {/* HERO SECTION */}
      <section className="bg-white border-b border-slate-200/80 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
          <div className="max-w-3xl">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-xs font-medium text-slate-700 mb-4">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
              <span>Directorio y Auditoría Parlamentaria Completa</span>
            </div>

            <h1 className="text-3xl sm:text-5xl font-extrabold text-slate-900 tracking-[-0.02em] leading-tight">
              Diputados y Senadores de la Nación
            </h1>

            <p className="mt-3 text-base sm:text-lg text-slate-500 font-normal leading-relaxed">
              Explorá y filtrá a los legisladores por{' '}
              <strong className="text-slate-700">estado (activos o con mandato cumplido)</strong>,{' '}
              <strong className="text-slate-700">bancada</strong>,{' '}
              <strong className="text-slate-700">provincia</strong> y{' '}
              <strong className="text-slate-700">votos en leyes clave</strong>. Datos oficiales de la
              HCDN, el Senado, la OA y el INDEC.
            </p>
          </div>

          {/* BUSCADOR PRINCIPAL */}
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
                placeholder="Buscar por nombre (Menem, Ritondo, Bregman, Lousteau), CUIT, bloque o provincia..."
                className="w-full pl-12 pr-12 py-3.5 sm:py-4 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 placeholder:text-slate-400 text-base focus:outline-none focus:bg-white focus:ring-2 focus:ring-slate-900 focus:border-transparent shadow-sm transition-all"
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
              <div className="absolute left-0 right-0 mt-2 bg-white rounded-xl border border-slate-200 shadow-xl z-30 overflow-hidden divide-y divide-slate-100">
                <div className="px-3.5 py-2 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-500 flex items-center justify-between">
                  <span>Acceso rápido a ficha de auditoría</span>
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
                          {sug.camara} · {sug.provincia} ·{' '}
                          <span className={sug.estado === 'Activo' ? 'text-emerald-600' : 'text-slate-500'}>
                            {sug.estado}
                          </span>
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
            {/* FILTROS RÁPIDOS DESTACADOS: Mayor Presentismo, Mayor Capital, Más Mandatos */}
            <div className="flex flex-wrap items-center gap-2 mt-3.5">
              <span className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mr-0.5 flex items-center gap-1">
                <Sparkles className="w-3.5 h-3.5 text-blue-600" />
                Filtros destacados:
              </span>

              <button
                type="button"
                onClick={() => {
                  if (filtroPresentismo === 'excelente') {
                    setFiltroPresentismo('Todos');
                  } else {
                    setFiltroPresentismo('excelente');
                    setOrden('asistencia-desc');
                  }
                }}
                className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all shadow-sm ${
                  filtroPresentismo === 'excelente'
                    ? 'bg-emerald-700 text-white ring-2 ring-emerald-500/30'
                    : 'bg-white hover:bg-emerald-50 text-slate-700 border border-slate-200 hover:border-emerald-300'
                }`}
              >
                <span>🏆 Mayor Presentismo (≥96%)</span>
              </button>

              <button
                type="button"
                onClick={() => {
                  if (filtroCapital === 'alto') {
                    setFiltroCapital('Todos');
                  } else {
                    setFiltroCapital('alto');
                    setOrden('patrimonio-desc');
                  }
                }}
                className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all shadow-sm ${
                  filtroCapital === 'alto'
                    ? 'bg-amber-600 text-white ring-2 ring-amber-500/30'
                    : 'bg-white hover:bg-amber-50 text-slate-700 border border-slate-200 hover:border-amber-300'
                }`}
              >
                <span>💰 Mayor Capital (&gt;US$ 400k)</span>
              </button>

              <button
                type="button"
                onClick={() => {
                  if (filtroMandatos === '3+') {
                    setFiltroMandatos('Todos');
                  } else {
                    setFiltroMandatos('3+');
                    setOrden('mandatos-desc');
                  }
                }}
                className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all shadow-sm ${
                  filtroMandatos === '3+'
                    ? 'bg-indigo-700 text-white ring-2 ring-indigo-500/30'
                    : 'bg-white hover:bg-indigo-50 text-slate-700 border border-slate-200 hover:border-indigo-300'
                }`}
              >
                <span>🏛️ Más Mandatos (+3 Trayectoria)</span>
              </button>

              <button
                type="button"
                onClick={() => {
                  if (filtroMandatos === '1') {
                    setFiltroMandatos('Todos');
                  } else {
                    setFiltroMandatos('1');
                    setOrden('mandatos-asc');
                  }
                }}
                className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all shadow-sm ${
                  filtroMandatos === '1'
                    ? 'bg-slate-900 text-white ring-2 ring-slate-700/30'
                    : 'bg-white hover:bg-slate-100 text-slate-700 border border-slate-200'
                }`}
              >
                <span>🆕 1er Mandato (Nuevos)</span>
              </button>
            </div>
          </div>

          {/* BARRA DE FILTROS PRINCIPALES */}
          <div className="mt-6 pt-6 border-t border-slate-100 space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
              {/* Toggles de Cámara */}
              <div className="flex items-center gap-2">
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
                      className={`px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition-all ${
                        active
                          ? 'bg-slate-900 text-white shadow-sm'
                          : 'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-900'
                      }`}
                    >
                      {cam}
                    </button>
                  );
                })}
              </div>

              {/* Toggles de Estado (Activos vs Mandato Cumplido) */}
              <div className="flex items-center gap-2">
                <span className="text-xs font-semibold uppercase tracking-wider text-slate-400 mr-1 flex items-center gap-1">
                  <CheckCircle2 className="w-3.5 h-3.5" />
                  Estado:
                </span>
                {[
                  { id: 'Todos', label: 'Todos' },
                  { id: 'Activo', label: 'Activos' },
                  { id: 'Mandato Cumplido', label: 'Mandato Cumplido' },
                ].map((st) => {
                  const active = filtroEstado === st.id;
                  return (
                    <button
                      key={st.id}
                      type="button"
                      onClick={() => setFiltroEstado(st.id as FiltroEstado)}
                      className={`px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition-all ${
                        active
                          ? 'bg-emerald-700 text-white shadow-sm'
                          : 'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-900'
                      }`}
                    >
                      {st.label}
                    </button>
                  );
                })}
              </div>

              {/* Toggles de Mandatos Parlamentarios */}
              <div className="flex items-center gap-2">
                <span className="text-xs font-semibold uppercase tracking-wider text-slate-400 mr-1 flex items-center gap-1">
                  <span>🏛️</span>
                  Mandatos:
                </span>
                {[
                  { id: 'Todos', label: 'Todos' },
                  { id: '1', label: '1er Mandato' },
                  { id: '2', label: '2 Mandatos' },
                  { id: '3+', label: '+3 Mandatos' },
                ].map((m) => {
                  const active = filtroMandatos === m.id;
                  return (
                    <button
                      key={m.id}
                      type="button"
                      onClick={() => setFiltroMandatos(m.id as FiltroMandatos)}
                      className={`px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition-all ${
                        active
                          ? 'bg-indigo-700 text-white shadow-sm'
                          : 'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-900'
                      }`}
                    >
                      {m.label}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Selectores de Provincia, Bloque, Presentismo, Capital y Ordenamiento */}
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 pt-2">
              {/* Selector de Provincia */}
              <div>
                <label
                  htmlFor="select-provincia"
                  className="block text-xs font-semibold text-slate-500 uppercase mb-1"
                >
                  Provincia:
                </label>
                <select
                  id="select-provincia"
                  value={filtroProvincia}
                  onChange={(e) => setFiltroProvincia(e.target.value)}
                  className="w-full rounded-lg bg-slate-50 border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-slate-900"
                >
                  {provinciasDisponibles.map((prov) => (
                    <option key={prov} value={prov}>
                      {prov}
                    </option>
                  ))}
                </select>
              </div>

              {/* Selector de Bloque */}
              <div>
                <label
                  htmlFor="select-bloque"
                  className="block text-xs font-semibold text-slate-500 uppercase mb-1"
                >
                  Bancada:
                </label>
                <select
                  id="select-bloque"
                  value={filtroBloque}
                  onChange={(e) => setFiltroBloque(e.target.value)}
                  className="w-full rounded-lg bg-slate-50 border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-slate-900"
                >
                  {bloquesDisponibles.map((bloq) => (
                    <option key={bloq} value={bloq}>
                      {bloq}
                    </option>
                  ))}
                </select>
              </div>

              {/* Selector de Presentismo */}
              <div>
                <label
                  htmlFor="select-presentismo"
                  className="block text-xs font-semibold text-slate-500 uppercase mb-1"
                >
                  Presentismo:
                </label>
                <select
                  id="select-presentismo"
                  value={filtroPresentismo}
                  onChange={(e) => setFiltroPresentismo(e.target.value as FiltroPresentismo)}
                  className="w-full rounded-lg bg-slate-50 border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-slate-900"
                >
                  <option value="Todos">Cualquier asistencia</option>
                  <option value="excelente">🏆 Sobresaliente (≥ 96%)</option>
                  <option value="alta">Alta (93% - 96%)</option>
                  <option value="baja">Baja (&lt; 93%)</option>
                </select>
              </div>

              {/* Selector de Capital / Patrimonio */}
              <div>
                <label
                  htmlFor="select-capital"
                  className="block text-xs font-semibold text-slate-500 uppercase mb-1"
                >
                  Capital DDJJ:
                </label>
                <select
                  id="select-capital"
                  value={filtroCapital}
                  onChange={(e) => setFiltroCapital(e.target.value as FiltroCapital)}
                  className="w-full rounded-lg bg-slate-50 border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-slate-900"
                >
                  <option value="Todos">Cualquier capital</option>
                  <option value="alto">💰 Mayor Capital (&gt;US$ 400k)</option>
                  <option value="medio">Medio (US$ 100k - 400k)</option>
                  <option value="inicial">Inicial (&lt; US$ 100k)</option>
                </select>
              </div>

              {/* Ordenamiento */}
              <div>
                <label
                  htmlFor="select-orden"
                  className="block text-xs font-semibold text-slate-500 uppercase mb-1"
                >
                  Ordenar por:
                </label>
                <select
                  id="select-orden"
                  value={orden}
                  onChange={(e) => setOrden(e.target.value as OrdenCriterio)}
                  className="w-full rounded-lg bg-slate-50 border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-slate-900"
                >
                  <option value="asistencia-desc">🏆 Mayor Presentismo (%)</option>
                  <option value="asistencia-asc">⚠️ Menor Presentismo (%)</option>
                  <option value="patrimonio-desc">💰 Mayor Capital (USD MEP)</option>
                  <option value="patrimonio-asc">📉 Menor Capital (USD MEP)</option>
                  <option value="mandatos-desc">🏛️ Más Mandatos (Trayectoria)</option>
                  <option value="mandatos-asc">🆕 Menos Mandatos (Nuevos)</option>
                  <option value="crecimiento-desc">📈 Mayor Crecimiento Real (%)</option>
                  <option value="lealtad-desc">Mayor Lealtad de Bloque (%)</option>
                  <option value="nombre-asc">Nombre (A → Z)</option>
                  <option value="nombre-desc">Nombre (Z → A)</option>
                </select>
              </div>

              {/* Botón Filtros Avanzados (Votaciones / Leyes) */}
              <div className="flex items-end">
                <button
                  type="button"
                  onClick={() => setMostrarFiltrosAvanzados(!mostrarFiltrosAvanzados)}
                  className={`w-full py-2 px-3 rounded-lg text-sm font-semibold flex items-center justify-between border transition-all ${
                    mostrarFiltrosAvanzados || cantidadFiltrosAvanzadosActivos > 0
                      ? 'bg-blue-50 border-blue-300 text-blue-900'
                      : 'bg-slate-50 border-slate-300 text-slate-700 hover:bg-slate-100'
                  }`}
                >
                  <span className="flex items-center gap-1.5">
                    <Vote className="w-4 h-4 text-blue-600" />
                    <span>Filtro de Votaciones</span>
                    {cantidadFiltrosAvanzadosActivos > 0 && (
                      <span className="w-5 h-5 rounded-full bg-blue-600 text-white text-[11px] font-bold flex items-center justify-center">
                        {cantidadFiltrosAvanzadosActivos}
                      </span>
                    )}
                  </span>
                  {mostrarFiltrosAvanzados ? (
                    <ChevronUp className="w-4 h-4" />
                  ) : (
                    <ChevronDown className="w-4 h-4" />
                  )}
                </button>
              </div>
            </div>

            {/* PANEL DE FILTROS AVANZADOS (Votaciones, Leyes y Ejes) */}
            {mostrarFiltrosAvanzados && (
              <div className="bg-slate-50 border border-slate-200 rounded-xl p-4 sm:p-5 mt-4 space-y-4 animate-in fade-in duration-200">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <Vote className="w-4 h-4 text-blue-600" />
                    <span>Filtrar por cómo votó cada legislador en leyes y categorías</span>
                  </h3>
                  <button
                    type="button"
                    onClick={() => {
                      setFiltroLey('Todas');
                      setFiltroSentidoVoto('Todos');
                      setFiltroEje('Todos');
                      setFiltroPosturaEje('Todos');
                      setFiltroEspacio('Todos');
                    }}
                    className="text-xs text-slate-500 hover:text-slate-800 underline"
                  >
                    Restablecer votaciones
                  </button>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                  {/* Selector de Ley Clave */}
                  <div>
                    <label
                      htmlFor="select-ley"
                      className="block text-xs font-semibold text-slate-600 mb-1"
                    >
                      1. Ley o Expediente Específico:
                    </label>
                    <select
                      id="select-ley"
                      value={filtroLey}
                      onChange={(e) => setFiltroLey(e.target.value)}
                      className="w-full rounded-lg bg-white border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-blue-600"
                    >
                      <option value="Todas">Todas las leyes</option>
                      {LEYES_SEED.map((l) => (
                        <option key={l.id} value={l.id}>
                          {l.titulo}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Selector de Sentido de Voto en la Ley */}
                  <div>
                    <label
                      htmlFor="select-voto"
                      className="block text-xs font-semibold text-slate-600 mb-1"
                    >
                      2. Sentido del Voto en la Ley:
                    </label>
                    <select
                      id="select-voto"
                      value={filtroSentidoVoto}
                      onChange={(e) => setFiltroSentidoVoto(e.target.value)}
                      disabled={filtroLey === 'Todas'}
                      className="w-full rounded-lg bg-white border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <option value="Todos">Cualquier sentido de voto</option>
                      <option value="Afirmativo">Votó A Favor (Afirmativo)</option>
                      <option value="Negativo">Votó En Contra (Negativo)</option>
                      <option value="Abstencion">Se Abstuvo</option>
                      <option value="Ausente">Estuvo Ausente</option>
                    </select>
                  </div>

                  {/* Selector de Espacio Político / Tendencia */}
                  <div>
                    <label
                      htmlFor="select-espacio"
                      className="block text-xs font-semibold text-slate-600 mb-1"
                    >
                      3. Espacio Político / Alianza:
                    </label>
                    <select
                      id="select-espacio"
                      value={filtroEspacio}
                      onChange={(e) => setFiltroEspacio(e.target.value)}
                      className="w-full rounded-lg bg-white border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-blue-600"
                    >
                      <option value="Todos">Todos los espacios</option>
                      <option value="Oficialismo y Aliados">Oficialismo y Aliados (LLA + PRO)</option>
                      <option value="Oposición Dialoguista">
                        Oposición Dialoguista (UCR, EF, CC, IF)
                      </option>
                      <option value="Oposición Fuerte">
                        Oposición (Unión por la Patria + FIT-U)
                      </option>
                    </select>
                  </div>

                  {/* Selector de Eje Temático */}
                  <div>
                    <label
                      htmlFor="select-eje"
                      className="block text-xs font-semibold text-slate-600 mb-1"
                    >
                      4. Categoría / Eje Temático:
                    </label>
                    <select
                      id="select-eje"
                      value={filtroEje}
                      onChange={(e) => setFiltroEje(e.target.value)}
                      className="w-full rounded-lg bg-white border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-blue-600"
                    >
                      {EJES_TEMATICOS.map((eje) => (
                        <option key={eje.id} value={eje.id}>
                          {eje.label}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Selector de Postura en el Eje */}
                  <div>
                    <label
                      htmlFor="select-postura"
                      className="block text-xs font-semibold text-slate-600 mb-1"
                    >
                      5. Postura en el Eje:
                    </label>
                    <select
                      id="select-postura"
                      value={filtroPosturaEje}
                      onChange={(e) => setFiltroPosturaEje(e.target.value)}
                      disabled={filtroEje === 'Todos'}
                      className="w-full rounded-lg bg-white border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <option value="Todos">Cualquier postura</option>
                      <option value="Favor">A favor de reformas del eje (Score ≥ 50%)</option>
                      <option value="Contra">En contra de reformas del eje (Score &lt; 50%)</option>
                      <option value="Alta">Alta afinidad en el eje (Score ≥ 75%)</option>
                    </select>
                  </div>

                  {/* Info contextual */}
                  <div className="flex items-center text-xs text-slate-500 bg-white p-3 rounded-lg border border-slate-200">
                    <p>
                      Los votos se auditan a partir de las actas oficiales nominales de la Cámara de
                      Diputados y el Senado de la Nación.
                    </p>
                  </div>
                </div>
              </div>
            )}

            {/* CHIPS DE FILTROS ACTIVOS */}
            {hayFiltrosActivos && (
              <div className="flex flex-wrap items-center gap-2 pt-2">
                <span className="text-xs font-medium text-slate-400">Filtros aplicados:</span>

                {filtroCamara !== 'Todos' && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-900 text-white">
                    <span>Cámara: {filtroCamara}</span>
                    <button
                      type="button"
                      onClick={() => setFiltroCamara('Todos')}
                      className="hover:text-slate-300"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                )}

                {filtroEstado !== 'Todos' && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-800 text-white">
                    <span>Estado: {filtroEstado}</span>
                    <button
                      type="button"
                      onClick={() => setFiltroEstado('Todos')}
                      className="hover:text-emerald-200"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                )}

                {filtroProvincia !== 'Todas' && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-800 border border-slate-200">
                    <span>Provincia: {filtroProvincia}</span>
                    <button
                      type="button"
                      onClick={() => setFiltroProvincia('Todas')}
                      className="hover:text-slate-500"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                )}

                {filtroBloque !== 'Todos' && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-800 border border-slate-200">
                    <span>Bloque: {filtroBloque}</span>
                    <button
                      type="button"
                      onClick={() => setFiltroBloque('Todos')}
                      className="hover:text-slate-500"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                )}

                {filtroEspacio !== 'Todos' && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                    <span>Espacio: {filtroEspacio}</span>
                    <button
                      type="button"
                      onClick={() => setFiltroEspacio('Todos')}
                      className="hover:text-blue-600"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                )}

                {filtroLey !== 'Todas' && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-700 text-white">
                    <span>
                      Ley: {leySeleccionada?.titulo.slice(0, 30)}...{' '}
                      {filtroSentidoVoto !== 'Todos' ? `(${filtroSentidoVoto})` : ''}
                    </span>
                    <button
                      type="button"
                      onClick={() => {
                        setFiltroLey('Todas');
                        setFiltroSentidoVoto('Todos');
                      }}
                      className="hover:text-blue-200"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                )}

                {filtroEje !== 'Todos' && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">
                    <span>Eje: {filtroEje} ({filtroPosturaEje})</span>
                    <button
                      type="button"
                      onClick={() => {
                        setFiltroEje('Todos');
                        setFiltroPosturaEje('Todos');
                      }}
                      className="hover:text-amber-600"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                )}

                {filtroPresentismo !== 'Todos' && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-200">
                    <span>
                      Presentismo:{' '}
                      {filtroPresentismo === 'excelente'
                        ? 'Sobresaliente (≥96%)'
                        : filtroPresentismo === 'alta'
                        ? 'Alta (93%-96%)'
                        : 'Baja (<93%)'}
                    </span>
                    <button
                      type="button"
                      onClick={() => setFiltroPresentismo('Todos')}
                      className="hover:text-emerald-950"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                )}

                {filtroCapital !== 'Todos' && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">
                    <span>
                      Capital:{' '}
                      {filtroCapital === 'alto'
                        ? 'Mayor Capital (> US$ 400k)'
                        : filtroCapital === 'medio'
                        ? 'Medio (US$ 100k-400k)'
                        : 'Inicial (< US$ 100k)'}
                    </span>
                    <button
                      type="button"
                      onClick={() => setFiltroCapital('Todos')}
                      className="hover:text-amber-950"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                )}

                {filtroMandatos !== 'Todos' && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 border border-indigo-200">
                    <span>
                      Mandatos:{' '}
                      {filtroMandatos === '1'
                        ? '1er Mandato (Nuevos)'
                        : filtroMandatos === '2'
                        ? '2 Mandatos'
                        : '+3 Mandatos (Veteranos)'}
                    </span>
                    <button
                      type="button"
                      onClick={() => setFiltroMandatos('Todos')}
                      className="hover:text-indigo-950"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                )}

                {busqueda && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-800 border border-slate-200">
                    <span>Texto: &ldquo;{busqueda}&rdquo;</span>
                    <button
                      type="button"
                      onClick={() => setBusqueda('')}
                      className="hover:text-slate-500"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                )}

                <button
                  type="button"
                  onClick={limpiarFiltros}
                  className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition"
                >
                  <RotateCcw className="w-3 h-3" />
                  <span>Limpiar todo</span>
                </button>
              </div>
            )}
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
                Respondé el test de 8 preguntas sobre leyes reales y calculá tu Similitud de
                Coseno con todos los bloques del Congreso.
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

        {/* BARRA DE HERRAMIENTAS: CONTADOR, PROMEDIOS Y TOGGLE GRID / TABLA */}
        <div className="bg-white rounded-xl border border-slate-200 p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="flex flex-wrap items-center gap-3">
            <div className="flex items-center gap-2">
              <Users className="w-4 h-4 text-slate-600" />
              <span className="text-sm font-bold text-slate-900">
                Mostrando{' '}
                <span className="font-mono tabular-nums text-blue-700">
                  {legisladoresFiltrados.length}
                </span>{' '}
                de <span className="font-mono tabular-nums">{legisladores.length}</span> legisladores
              </span>
            </div>

            <span className="hidden sm:inline text-slate-300">|</span>

            <div className="flex items-center gap-3 text-xs text-slate-500">
              <span className="inline-flex items-center gap-1">
                <TrendingUp className="w-3.5 h-3.5 text-emerald-600" />
                Asistencia media:{' '}
                <strong className="font-mono text-slate-800">
                  {formatPct(promedioAsistencia)}
                </strong>
              </span>
              <span>·</span>
              <span>
                Lealtad media:{' '}
                <strong className="font-mono text-slate-800">
                  {formatPct(promedioLealtad)}
                </strong>
              </span>
            </div>
          </div>

          {/* Selector de Modo de Vista (Grid vs Tabla) */}
          <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-lg self-start md:self-auto">
            <button
              type="button"
              onClick={() => setModoVista('grid')}
              className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition ${
                modoVista === 'grid'
                  ? 'bg-white text-slate-900 shadow-sm'
                  : 'text-slate-500 hover:text-slate-900'
              }`}
              title="Vista en tarjetas"
            >
              <LayoutGrid className="w-3.5 h-3.5" />
              <span>Tarjetas</span>
            </button>
            <button
              type="button"
              onClick={() => setModoVista('tabla')}
              className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition ${
                modoVista === 'tabla'
                  ? 'bg-white text-slate-900 shadow-sm'
                  : 'text-slate-500 hover:text-slate-900'
              }`}
              title="Vista compacta en tabla"
            >
              <List className="w-3.5 h-3.5" />
              <span>Tabla Compacta</span>
            </button>
          </div>
        </div>

        {/* LISTADO DE RESULTADOS */}
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
            <div className="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
              <Filter className="w-6 h-6" />
            </div>
            <h3 className="text-base font-bold text-slate-900">
              No se encontraron legisladores con esos filtros
            </h3>
            <p className="text-sm text-slate-500 mt-1">
              Probá restableciendo los filtros de voto, estado, bancada o término de búsqueda.
            </p>
            <button
              type="button"
              onClick={limpiarFiltros}
              className="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800 transition-colors"
            >
              <RotateCcw className="w-4 h-4" />
              <span>Restablecer todos los filtros</span>
            </button>
          </div>
        ) : modoVista === 'grid' ? (
          /* MODO CUADRÍCULA DE TARJETAS */
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            {legisladoresFiltrados.map((leg) => (
              <LegisladorCard
                key={leg.id}
                legislador={leg}
                filtroLey={filtroLey !== 'Todas' ? filtroLey : undefined}
                tituloLey={leySeleccionada?.titulo}
              />
            ))}
          </div>
        ) : (
          /* MODO TABLA COMPACTA */
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                  <tr>
                    <th scope="col" className="px-4 py-3">
                      Legislador
                    </th>
                    <th scope="col" className="px-3 py-3">
                      Cámara
                    </th>
                    <th scope="col" className="px-3 py-3">
                      Estado
                    </th>
                    <th scope="col" className="px-3 py-3">
                      Bancada
                    </th>
                    <th scope="col" className="px-3 py-3">
                      Provincia
                    </th>
                    <th scope="col" className="px-3 py-3 text-center">
                      Mandatos
                    </th>
                    {filtroLey !== 'Todas' && (
                      <th scope="col" className="px-3 py-3 text-center">
                        Voto en Ley
                      </th>
                    )}
                    <th scope="col" className="px-3 py-3 text-right">
                      Presentismo
                    </th>
                    <th scope="col" className="px-3 py-3 text-right">
                      Lealtad
                    </th>
                    <th scope="col" className="px-3 py-3 text-right">
                      Capital USD
                    </th>
                    <th scope="col" className="px-4 py-3 text-center">
                      Ficha
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 font-normal">
                  {legisladoresFiltrados.map((leg) => {
                    const perfilUrl = getLegisladorUrl(leg);
                    const votoLey =
                      filtroLey !== 'Todas' && leg.votos ? leg.votos[filtroLey] : null;

                    return (
                      <tr
                        key={leg.id}
                        className="hover:bg-slate-50/80 transition-colors"
                      >
                        <td className="px-4 py-3">
                          <a
                            href={perfilUrl}
                            className="flex items-center gap-3 font-bold text-slate-900 hover:text-blue-600 truncate"
                          >
                            <AvatarLegislador
                              src={leg.foto_url}
                              nombre={leg.nombre_completo}
                              size="sm"
                            />
                            <span className="truncate">{leg.nombre_completo}</span>
                          </a>
                        </td>
                        <td className="px-3 py-3 whitespace-nowrap text-xs text-slate-600">
                          {leg.camara}
                        </td>
                        <td className="px-3 py-3 whitespace-nowrap">
                          <span
                            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                              leg.estado === 'Activo'
                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                : 'bg-slate-100 text-slate-600 border border-slate-200'
                            }`}
                          >
                            {leg.estado === 'Activo' && (
                              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                            )}
                            {leg.estado}
                          </span>
                        </td>
                        <td className="px-3 py-3 whitespace-nowrap">
                          <span
                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${getBloqueBadgeStyle(
                              leg.bloque_actual
                            )}`}
                          >
                            {leg.bloque_actual}
                          </span>
                        </td>
                        <td className="px-3 py-3 whitespace-nowrap text-xs text-slate-600">
                          {leg.provincia === 'Ciudad Autónoma de Buenos Aires'
                            ? 'CABA'
                            : leg.provincia}
                        </td>
                        <td className="px-3 py-3 whitespace-nowrap text-center text-xs">
                          <span
                            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-bold text-[11px] ${
                              (leg.mandatos || 1) >= 3
                                ? 'bg-amber-100 text-amber-800 border border-amber-200'
                                : (leg.mandatos || 1) === 2
                                ? 'bg-indigo-100 text-indigo-800 border border-indigo-200'
                                : 'bg-slate-100 text-slate-700 border border-slate-200'
                            }`}
                            title={`${leg.mandatos || 1} ${leg.mandatos === 1 ? 'mandato' : 'mandatos'}`}
                          >
                            🏛️ {leg.mandatos || 1} {(leg.mandatos || 1) === 1 ? 'mandato' : 'mandatos'}
                          </span>
                        </td>
                        {filtroLey !== 'Todas' && (
                          <td className="px-3 py-3 whitespace-nowrap text-center">
                            {votoLey ? (
                              <span
                                className={`font-bold text-[11px] px-2.5 py-0.5 rounded-full ${
                                  votoLey === 'Afirmativo'
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : votoLey === 'Negativo'
                                    ? 'bg-rose-100 text-rose-800'
                                    : votoLey === 'Abstencion'
                                    ? 'bg-amber-100 text-amber-800'
                                    : 'bg-slate-200 text-slate-700'
                                }`}
                              >
                                {votoLey}
                              </span>
                            ) : (
                              <span className="text-slate-400 text-xs">—</span>
                            )}
                          </td>
                        )}
                        <td className="px-3 py-3 whitespace-nowrap text-right font-mono font-bold text-xs">
                          <span className={leg.asistencia_historica >= 96 ? 'text-emerald-700' : 'text-slate-800'}>
                            {leg.asistencia_historica >= 96 && <span className="mr-1">🏆</span>}
                            {formatPct(leg.asistencia_historica)}
                          </span>
                        </td>
                        <td className="px-3 py-3 whitespace-nowrap text-right font-mono font-bold text-slate-800 text-xs">
                          {formatPct(leg.lealtad_partidaria)}
                        </td>
                        <td className="px-3 py-3 whitespace-nowrap text-right font-mono text-xs">
                          <span className={`font-bold ${(leg.patrimonio_usd_actual || 0) >= 400000 ? 'text-amber-700' : 'text-slate-900'}`}>
                            {(leg.patrimonio_usd_actual || 0) >= 400000 && <span className="mr-1">💰</span>}
                            {formatUSD(leg.patrimonio_usd_actual)}
                          </span>
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap text-center">
                          <a
                            href={perfilUrl}
                            className="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:text-blue-900"
                          >
                            <span>Ver</span>
                            <ArrowRight className="w-3 h-3" />
                          </a>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
