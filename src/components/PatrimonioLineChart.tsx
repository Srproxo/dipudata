import React, { useState } from 'react';
import { PuntoEvolucionPatrimonial } from '../types/legisdata';
import { formatARS, formatCompactARS, formatPct, formatUSD } from '../utils/formatters';

interface PatrimonioLineChartProps {
  serie: PuntoEvolucionPatrimonial[];
  monedaConstanteBase: number;
}

export const PatrimonioLineChart: React.FC<PatrimonioLineChartProps> = ({
  serie,
  monedaConstanteBase,
}) => {
  const [activeIndex, setActiveIndex] = useState<number>(
    serie.length > 0 ? serie.length - 1 : 0
  );

  if (!serie || serie.length === 0) {
    return (
      <div className="h-64 flex items-center justify-center bg-slate-50 rounded-xl border border-slate-200 text-sm text-slate-500">
        Sin declaraciones juradas registradas.
      </div>
    );
  }

  const width = 680;
  const height = 270;
  const padLeft = 72;
  const padRight = 28;
  const padTop = 26;
  const padBottom = 38;

  const plotW = width - padLeft - padRight;
  const plotH = height - padTop - padBottom;

  const usdValues = serie.map((d) => d.patrimonio_usd);
  const minVal = Math.min(...usdValues) * 0.88;
  const maxVal = Math.max(...usdValues) * 1.08;
  const valRange = Math.max(maxVal - minVal, 1);

  const getX = (idx: number) =>
    serie.length === 1
      ? padLeft + plotW / 2
      : padLeft + (idx / (serie.length - 1)) * plotW;

  const getY = (val: number) =>
    padTop + plotH - ((val - minVal) / valRange) * plotH;

  const points = serie.map((d, i) => ({
    x: getX(i),
    y: getY(d.patrimonio_usd),
    data: d,
  }));

  // Curva suavizada Catmull-Rom / Cúbica con tension = 0.4
  const tension = 0.4;
  let linePath = `M ${points[0].x.toFixed(1)} ${points[0].y.toFixed(1)}`;

  for (let i = 0; i < points.length - 1; i++) {
    const p0 = points[i === 0 ? 0 : i - 1];
    const p1 = points[i];
    const p2 = points[i + 1];
    const p3 = points[i + 2 < points.length ? i + 2 : i + 1];

    const cp1x = p1.x + ((p2.x - p0.x) / 6) * tension * 2.2;
    const cp1y = p1.y + ((p2.y - p0.y) / 6) * tension * 2.2;
    const cp2x = p2.x - ((p3.x - p1.x) / 6) * tension * 2.2;
    const cp2y = p2.y - ((p3.y - p1.y) / 6) * tension * 2.2;

    linePath += ` C ${cp1x.toFixed(1)} ${cp1y.toFixed(1)}, ${cp2x.toFixed(1)} ${cp2y.toFixed(1)}, ${p2.x.toFixed(1)} ${p2.y.toFixed(1)}`;
  }

  const areaPath = `${linePath} L ${points[points.length - 1].x.toFixed(1)} ${(
    padTop + plotH
  ).toFixed(1)} L ${points[0].x.toFixed(1)} ${(padTop + plotH).toFixed(1)} Z`;

  const yTicks = [0, 0.33, 0.66, 1].map((t) => minVal + t * valRange);
  const activePoint = points[activeIndex] ?? points[points.length - 1];

  const isPositiveTrend =
    serie[serie.length - 1].patrimonio_usd >= serie[0].patrimonio_usd;
  const strokeColor = isPositiveTrend ? '#059669' : '#e11d48';

  return (
    <div className="space-y-4">
      {/* SVG Responsive Chart */}
      <div className="relative w-full overflow-hidden rounded-xl border border-slate-200/80 bg-slate-50/60 p-2 sm:p-4">
        <svg
          viewBox={`0 0 ${width} ${height}`}
          className="w-full h-auto overflow-visible select-none"
          role="img"
          aria-label="Gráfico de evolución patrimonial en Dólares MEP"
        >
          <defs>
            <linearGradient id="patrimonioGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={strokeColor} stopOpacity="0.22" />
              <stop offset="100%" stopColor={strokeColor} stopOpacity="0.01" />
            </linearGradient>
          </defs>

          {/* Grilla horizontal */}
          {yTicks.map((tickVal, idx) => {
            const y = getY(tickVal);
            return (
              <g key={idx}>
                <line
                  x1={padLeft}
                  y1={y}
                  x2={width - padRight}
                  y2={y}
                  stroke="#e2e8f0"
                  strokeDasharray="4 4"
                  strokeWidth="1"
                />
                <text
                  x={padLeft - 10}
                  y={y + 4}
                  textAnchor="end"
                  className="fill-slate-400 text-[11px] font-mono tabular-nums"
                >
                  USD {Math.round(tickVal / 1000)}k
                </text>
              </g>
            );
          })}

          {/* Línea vertical activa */}
          {activePoint && (
            <line
              x1={activePoint.x}
              y1={padTop}
              x2={activePoint.x}
              y2={padTop + plotH}
              stroke="#94a3b8"
              strokeWidth="1.5"
              strokeDasharray="3 3"
            />
          )}

          {/* Área y curva suavizada */}
          <path d={areaPath} fill="url(#patrimonioGrad)" />
          <path
            d={linePath}
            fill="none"
            stroke={strokeColor}
            strokeWidth="3"
            strokeLinecap="round"
            strokeLinejoin="round"
          />

          {/* Puntos interactivos y etiquetas de Año Fiscal */}
          {points.map((pt, idx) => {
            const isSelected = idx === activeIndex;
            return (
              <g
                key={pt.data.anio_fiscal}
                className="cursor-pointer"
                onMouseEnter={() => setActiveIndex(idx)}
                onClick={() => setActiveIndex(idx)}
              >
                {/* Zona táctil amplia para móvil */}
                <rect
                  x={pt.x - plotW / (serie.length * 2)}
                  y={padTop}
                  width={plotW / serie.length}
                  height={plotH + padBottom}
                  fill="transparent"
                />

                <circle
                  cx={pt.x}
                  cy={pt.y}
                  r={isSelected ? 7 : 4.5}
                  fill={isSelected ? strokeColor : '#ffffff'}
                  stroke={strokeColor}
                  strokeWidth={isSelected ? 3 : 2.5}
                />

                <text
                  x={pt.x}
                  y={padTop + plotH + 24}
                  textAnchor="middle"
                  className={`text-xs font-mono tabular-nums ${
                    isSelected
                      ? 'fill-slate-900 font-bold'
                      : 'fill-slate-500 font-medium'
                  }`}
                >
                  {pt.data.anio_fiscal}
                </text>
              </g>
            );
          })}
        </svg>
      </div>

      {/* Tooltip / Inspector Interactivo con las 3 monedas (ARS Nominal, ARS Constante IPC y USD MEP) */}
      {activePoint && (
        <div className="bg-slate-900 text-white rounded-xl p-4 shadow-sm border border-slate-800">
          <div className="flex flex-wrap items-center justify-between gap-2 pb-3 mb-3 border-b border-slate-800">
            <div className="flex items-center gap-2">
              <span className="text-xs uppercase tracking-wider font-semibold text-slate-400">
                Declaración Jurada Año Fiscal
              </span>
              <span className="font-mono tabular-nums font-bold text-sm px-2 py-0.5 rounded bg-slate-800 text-white">
                {activePoint.data.anio_fiscal}
              </span>
            </div>
            <div className="flex items-center gap-3 text-xs text-slate-400 font-mono tabular-nums">
              <span>
                Dólar MEP (31/12):{' '}
                <strong className="text-slate-200">
                  {formatARS(activePoint.data.dolar_mep_cierre)}
                </strong>
              </span>
              <span>·</span>
              <span>
                IPC Anual:{' '}
                <strong className="text-slate-200">
                  {formatPct(activePoint.data.ipc_interanual)}
                </strong>
              </span>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div className="bg-slate-800/80 rounded-lg p-3 border border-slate-700/70">
              <div className="text-[11px] text-slate-400 uppercase font-medium">
                1. Pesos Nominales ({activePoint.data.anio_fiscal})
              </div>
              <div className="text-base font-mono tabular-nums font-bold text-slate-100 mt-0.5">
                {formatARS(activePoint.data.monto_pesos_nominal)}
              </div>
              <div className="text-[11px] font-mono tabular-nums text-slate-400 mt-0.5">
                Declarado en OA ({formatCompactARS(activePoint.data.monto_pesos_nominal)})
              </div>
            </div>

            <div className="bg-slate-800/80 rounded-lg p-3 border border-slate-700/70">
              <div className="text-[11px] text-slate-400 uppercase font-medium">
                2. Pesos Constantes (IPC {monedaConstanteBase})
              </div>
              <div className="text-base font-mono tabular-nums font-bold text-amber-300 mt-0.5">
                {formatARS(activePoint.data.patrimonio_pesos_constantes)}
              </div>
              <div className="text-[11px] font-mono tabular-nums text-slate-400 mt-0.5">
                Ajustado por inflación acumulada
              </div>
            </div>

            <div className="bg-slate-800/80 rounded-lg p-3 border border-emerald-500/30">
              <div className="text-[11px] text-emerald-300 uppercase font-medium">
                3. Equivalente en USD MEP
              </div>
              <div className="text-lg font-mono tabular-nums font-extrabold text-white mt-0.5">
                {formatUSD(activePoint.data.patrimonio_usd)}
              </div>
              <div className="text-[11px] font-mono tabular-nums text-slate-300 mt-0.5">
                {activePoint.data.variacion_interanual_usd_pct !== null ? (
                  <span>
                    Variación anual:{' '}
                    <strong
                      className={
                        activePoint.data.variacion_interanual_usd_pct >= 0
                          ? 'text-emerald-400'
                          : 'text-rose-400'
                      }
                    >
                      {formatPct(activePoint.data.variacion_interanual_usd_pct, true)}
                    </strong>
                  </span>
                ) : (
                  'Año base de la serie'
                )}
              </div>
            </div>
          </div>

          <div className="mt-3 flex items-center justify-between text-[11px] text-slate-400">
            <span>
              Tocá o pasá el cursor sobre cualquier año ({serie[0].anio_fiscal}–
              {serie[serie.length - 1].anio_fiscal}) para auditar la conversión.
            </span>
            <span className="font-mono">Fuente: {activePoint.data.fuente}</span>
          </div>
        </div>
      )}
    </div>
  );
};
