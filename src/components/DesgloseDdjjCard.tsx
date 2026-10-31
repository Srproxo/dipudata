import React from 'react';
import {
  FileText,
  Building2,
  Home,
  DollarSign,
  TrendingUp,
  Car,
  ExternalLink,
  ShieldCheck,
  CheckCircle,
} from 'lucide-react';
import { DesgloseDdjjOficial, DesgloseItemDdjj } from '../types/legisdata';
import { formatARS, formatUSD } from '../utils/formatters';

interface DesgloseDdjjCardProps {
  desglose: DesgloseDdjjOficial;
  nombreLegislador: string;
}

export const DesgloseDdjjCard: React.FC<DesgloseDdjjCardProps> = ({
  desglose,
  nombreLegislador,
}) => {
  const getIconoCategoria = (cat: DesgloseItemDdjj['categoria']) => {
    switch (cat) {
      case 'Participaciones Societarias':
        return <Building2 className="w-4 h-4 text-indigo-600" />;
      case 'Inmuebles':
        return <Home className="w-4 h-4 text-emerald-600" />;
      case 'Moneda Extranjera':
        return <DollarSign className="w-4 h-4 text-amber-600" />;
      case 'Inversiones y Créditos':
        return <TrendingUp className="w-4 h-4 text-blue-600" />;
      case 'Vehículos':
        return <Car className="w-4 h-4 text-slate-600" />;
      default:
        return <FileText className="w-4 h-4 text-slate-500" />;
    }
  };

  return (
    <div className="bg-white rounded-2xl border border-slate-200/90 shadow-sm p-6 sm:p-7 space-y-6">
      {/* Encabezado */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
        <div>
          <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200/80 mb-1.5">
            <ShieldCheck className="w-3.5 h-3.5" />
            <span>Auditoría de DDJJ · Datos Oficiales de la OA</span>
          </div>
          <h3 className="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">
            Desglose de Bienes y Participaciones Societarias ({desglose.periodo_fiscal})
          </h3>
          <p className="text-xs text-slate-500 mt-0.5">
            {desglose.formulario} · Presentada ante la {desglose.organismo} el {desglose.fecha_presentacion}.
          </p>
        </div>

        <div className="text-left sm:text-right font-mono tabular-nums shrink-0">
          <div className="text-xs text-slate-400 font-sans uppercase">Patrimonio Neto Auditado</div>
          <div className="text-xl sm:text-2xl font-black text-slate-900">
            {formatARS(desglose.patrimonio_neto_pesos)}
          </div>
          <div className="text-xs font-semibold text-emerald-700">
            ~ {formatUSD(desglose.patrimonio_neto_usd)} MEP
          </div>
        </div>
      </div>

      {/* KPIs del Ejercicio */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
          <div className="text-xs text-slate-500 font-medium">Variación Nominal Anual</div>
          <div className="text-lg font-mono font-bold text-emerald-700 mt-0.5">
            +{desglose.variacion_nominal_pct.toFixed(1)}%
          </div>
          <div className="text-[11px] font-mono text-slate-400">
            +${(desglose.variacion_nominal_monto / 1000000).toFixed(1)}M vs. año anterior
          </div>
        </div>

        <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
          <div className="text-xs text-slate-500 font-medium">Deudas Declaradas</div>
          <div className="text-lg font-mono font-bold text-slate-900 mt-0.5">
            $ 0 (Sin pasivos)
          </div>
          <div className="text-[11px] text-slate-400">
            100% patrimonio neto sin acreencias
          </div>
        </div>

        <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
          <div className="text-xs text-slate-500 font-medium">Concentración Societaria</div>
          <div className="text-lg font-mono font-bold text-indigo-700 mt-0.5">
            &gt; 50% en 2 empresas
          </div>
          <div className="text-[11px] text-slate-400">
            CAPDEL S.A. y Quinto Centenario S.A.
          </div>
        </div>
      </div>

      {/* Resumen Ejecutivo */}
      <div className="p-4 rounded-xl bg-indigo-50/50 border border-indigo-100 text-xs sm:text-sm text-slate-700 leading-relaxed">
        <div className="font-bold text-slate-900 text-xs uppercase tracking-wider mb-1 flex items-center gap-1.5">
          <CheckCircle className="w-4 h-4 text-indigo-600" />
          <span>Resumen de la Declaración Patrimonial ({nombreLegislador})</span>
        </div>
        {desglose.resumen_ejecutivo}
      </div>

      {/* Listado de Rubros e Ítems Declarados */}
      <div className="space-y-3">
        <div className="text-xs font-bold uppercase tracking-wider text-slate-400">
          Rubros Detallados en la Declaración Jurada
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          {desglose.items.map((item, idx) => (
            <div
              key={idx}
              className="p-4 rounded-xl bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 transition-colors flex flex-col justify-between space-y-2"
            >
              <div>
                <div className="flex items-center justify-between gap-2 mb-1.5">
                  <div className="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-600 bg-white px-2 py-0.5 rounded border border-slate-200">
                    {getIconoCategoria(item.categoria)}
                    <span>{item.categoria}</span>
                  </div>

                  {item.porcentaje_patrimonio && item.porcentaje_patrimonio > 0 && (
                    <span className="text-[11px] font-mono font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded">
                      {item.porcentaje_patrimonio.toFixed(1)}% del total
                    </span>
                  )}
                </div>

                <h4 className="text-sm font-bold text-slate-900 leading-snug">
                  {item.titulo}
                </h4>

                <p className="text-xs text-slate-600 leading-relaxed mt-1">
                  {item.detalle}
                </p>
              </div>

              {(item.monto_pesos !== undefined || item.variacion_anual_texto) && (
                <div className="pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs">
                  {item.monto_pesos !== undefined && item.monto_pesos > 0 ? (
                    <span className="font-mono font-bold text-slate-900">
                      {formatARS(item.monto_pesos)}
                      {item.monto_usd ? (
                        <span className="text-slate-400 font-normal ml-1">
                          (~{formatUSD(item.monto_usd)})
                        </span>
                      ) : null}
                    </span>
                  ) : (
                    <span className="text-slate-400 italic">Sin monto valorizable</span>
                  )}

                  {item.variacion_anual_texto && (
                    <span className="font-mono text-[11px] text-slate-500">
                      {item.variacion_anual_texto}
                    </span>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Nota técnica sobre valuaciones fiscales y Fuente */}
      <div className="pt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-500">
        <p className="max-w-xl">
          <strong className="text-slate-700">Nota técnica sobre valuaciones:</strong> Las cifras
          declaradas responden a valuaciones fiscales normadas por la Ley de Ética Pública y la
          Oficina Anticorrupción, las cuales suelen ser significativamente inferiores a los precios de
          mercado de los inmuebles y sociedades privadas.
        </p>

        {desglose.fuente_periodistica && (
          <a
            href={desglose.fuente_periodistica.url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 font-medium transition-colors shrink-0"
          >
            <span>Fuente: {desglose.fuente_periodistica.medio}</span>
            <ExternalLink className="w-3.5 h-3.5" />
          </a>
        )}
      </div>
    </div>
  );
};
