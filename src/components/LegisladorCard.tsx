import React from 'react';
import {
  ArrowUpRight,
  TrendingUp,
  TrendingDown,
  MapPin,
  CheckCircle2,
  Users,
} from 'lucide-react';
import { LegisladorResumen } from '../types/legisdata';
import { AvatarLegislador } from './AvatarLegislador';
import {
  formatPct,
  formatUSD,
  getBloqueBadgeStyle,
  getLegisladorUrl,
} from '../utils/formatters';

interface LegisladorCardProps {
  legislador: LegisladorResumen;
  filtroLey?: string;
  tituloLey?: string;
}

export const LegisladorCard: React.FC<LegisladorCardProps> = ({
  legislador,
  filtroLey,
  tituloLey,
}) => {
  const isPatrimonioPositivo = legislador.crecimiento_real_usd_pct >= 0;
  const cargoLabel =
    legislador.camara === 'Senadores' ? 'Senador/a Nacional' : 'Diputado/a Nacional';
  const perfilUrl = getLegisladorUrl(legislador);
  const esActivo = legislador.estado === 'Activo';
  const votoFiltrado = filtroLey && legislador.votos ? legislador.votos[filtroLey] : null;

  return (
    <a
      href={perfilUrl}
      className="group bg-white rounded-xl border border-slate-200/80 shadow-sm hover:shadow-md hover:border-slate-300 transition-all p-5 flex flex-col justify-between gap-4"
    >
      {/* Header Flexbox: Avatar circular 64x64 a la izquierda, Nombre y Cargo a la derecha */}
      <div className="flex items-start gap-4">
        <AvatarLegislador
          src={legislador.foto_url}
          nombre={legislador.nombre_completo}
          size="lg"
        />

        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-2">
            <h3 className="text-base font-bold text-slate-900 tracking-[-0.02em] group-hover:text-blue-600 transition-colors truncate">
              {legislador.nombre_completo}
            </h3>
            <ArrowUpRight className="w-4 h-4 text-slate-400 group-hover:text-blue-600 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform shrink-0 mt-0.5" />
          </div>

          <div className="flex items-center gap-2 mt-0.5">
            <p className="text-xs font-medium text-slate-500">{cargoLabel}</p>
            <span
              className={`inline-flex items-center gap-1 rounded-full px-1.5 py-0.2 text-[10px] font-semibold ${
                esActivo
                  ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                  : 'bg-slate-100 text-slate-600 border border-slate-200'
              }`}
            >
              {esActivo && <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />}
              {legislador.estado}
            </span>
          </div>

          {/* Badges: Provincia y Bloque Político */}
          <div className="flex flex-wrap items-center gap-1.5 mt-2.5">
            <span className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
              <MapPin className="w-3 h-3 text-slate-500" />
              {legislador.provincia === 'Ciudad Autónoma de Buenos Aires'
                ? 'CABA'
                : legislador.provincia}
            </span>

            <span
              className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${getBloqueBadgeStyle(
                legislador.bloque_actual
              )}`}
            >
              {legislador.bloque_actual}
            </span>
          </div>
        </div>
      </div>

      {/* Voto en Ley Filtrada (si está activo el filtro de ley) */}
      {votoFiltrado && (
        <div className="flex items-center justify-between text-xs px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 gap-2">
          <span className="text-slate-600 font-medium truncate text-[11px]">
            {tituloLey || 'Voto registrado'}:
          </span>
          <span
            className={`font-bold text-[11px] px-2.5 py-0.5 rounded-full shrink-0 ${
              votoFiltrado === 'Afirmativo'
                ? 'bg-emerald-100 text-emerald-800'
                : votoFiltrado === 'Negativo'
                ? 'bg-rose-100 text-rose-800'
                : votoFiltrado === 'Abstencion'
                ? 'bg-amber-100 text-amber-800'
                : 'bg-slate-200 text-slate-700'
            }`}
          >
            {votoFiltrado}
          </span>
        </div>
      )}

      {/* Mini barras de huella temática */}
      <div className="grid grid-cols-4 gap-1.5 pt-2 border-t border-slate-100">
        <div>
          <div className="flex items-center justify-between text-[10px] text-slate-500 mb-1">
            <span>Educ.</span>
            <span className="font-mono tabular-nums font-semibold text-slate-700">
              {Math.round(legislador.vector_ideologico.Educacion)}%
            </span>
          </div>
          <div className="h-1.5 w-full rounded-full bg-slate-200 overflow-hidden">
            <div
              className="h-1.5 rounded-full bg-blue-500"
              style={{ width: `${legislador.vector_ideologico.Educacion}%` }}
            />
          </div>
        </div>

        <div>
          <div className="flex items-center justify-between text-[10px] text-slate-500 mb-1">
            <span>Salud</span>
            <span className="font-mono tabular-nums font-semibold text-slate-700">
              {Math.round(legislador.vector_ideologico.Salud)}%
            </span>
          </div>
          <div className="h-1.5 w-full rounded-full bg-slate-200 overflow-hidden">
            <div
              className="h-1.5 rounded-full bg-teal-500"
              style={{ width: `${legislador.vector_ideologico.Salud}%` }}
            />
          </div>
        </div>

        <div>
          <div className="flex items-center justify-between text-[10px] text-slate-500 mb-1">
            <span>Mercado</span>
            <span className="font-mono tabular-nums font-semibold text-slate-700">
              {Math.round(legislador.vector_ideologico.MercadoLibre)}%
            </span>
          </div>
          <div className="h-1.5 w-full rounded-full bg-slate-200 overflow-hidden">
            <div
              className="h-1.5 rounded-full bg-amber-500"
              style={{ width: `${legislador.vector_ideologico.MercadoLibre}%` }}
            />
          </div>
        </div>

        <div>
          <div className="flex items-center justify-between text-[10px] text-slate-500 mb-1">
            <span>Protec.</span>
            <span className="font-mono tabular-nums font-semibold text-slate-700">
              {Math.round(legislador.vector_ideologico.Proteccionismo)}%
            </span>
          </div>
          <div className="h-1.5 w-full rounded-full bg-slate-200 overflow-hidden">
            <div
              className="h-1.5 rounded-full bg-indigo-500"
              style={{ width: `${legislador.vector_ideologico.Proteccionismo}%` }}
            />
          </div>
        </div>
      </div>

      {/* Footer de KPIs Tabulares (Asistencia, Lealtad y DDJJ en USD MEP) */}
      <div className="grid grid-cols-3 gap-2 pt-3 border-t border-slate-100 text-xs">
        <div className="bg-slate-50 rounded-lg px-2.5 py-1.5 border border-slate-100">
          <div className="text-[10px] text-slate-500 flex items-center gap-1">
            <CheckCircle2 className="w-3 h-3 text-slate-400" />
            <span>Asistencia</span>
          </div>
          <div className="font-mono tabular-nums font-bold text-slate-900 mt-0.5">
            {formatPct(legislador.asistencia_historica)}
          </div>
        </div>

        <div className="bg-slate-50 rounded-lg px-2.5 py-1.5 border border-slate-100">
          <div className="text-[10px] text-slate-500 flex items-center gap-1">
            <Users className="w-3 h-3 text-slate-400" />
            <span>Lealtad</span>
          </div>
          <div className="font-mono tabular-nums font-bold text-slate-900 mt-0.5">
            {formatPct(legislador.lealtad_partidaria)}
          </div>
        </div>

        <div className="bg-slate-50 rounded-lg px-2.5 py-1.5 border border-slate-100">
          <div className="text-[10px] text-slate-500 truncate">DDJJ USD MEP</div>
          <div className="flex items-center justify-between gap-1 mt-0.5">
            <span className="font-mono tabular-nums font-bold text-slate-900 text-[11px] truncate">
              {formatUSD(legislador.patrimonio_usd_actual)}
            </span>
            <span
              className={`inline-flex items-center font-mono tabular-nums text-[10px] font-semibold px-1 py-0.2 rounded ${
                isPatrimonioPositivo
                  ? 'text-emerald-700 bg-emerald-100'
                  : 'text-rose-700 bg-rose-100'
              }`}
            >
              {isPatrimonioPositivo ? (
                <TrendingUp className="w-2.5 h-2.5 mr-0.5" />
              ) : (
                <TrendingDown className="w-2.5 h-2.5 mr-0.5" />
              )}
              {formatPct(legislador.crecimiento_real_usd_pct, true)}
            </span>
          </div>
        </div>
      </div>
    </a>
  );
};
