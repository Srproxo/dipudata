import React from 'react';
import { EjeTematico, VectorIdeologico } from '../types/legisdata';
import { EJE_TEMATICO_META } from '../utils/formatters';

interface IdeologiaRadarChartProps {
  vectorPrincipal: VectorIdeologico;
  labelPrincipal?: string;
  vectorComparacion?: VectorIdeologico;
  labelComparacion?: string;
}

const AXES_ORDER: EjeTematico[] = [
  'Educacion',
  'Salud',
  'MercadoLibre',
  'Proteccionismo',
  'Laboral',
];

export const IdeologiaRadarChart: React.FC<IdeologiaRadarChartProps> = ({
  vectorPrincipal,
  labelPrincipal = 'Legislador',
  vectorComparacion,
  labelComparacion = 'Tu Perfil',
}) => {
  const size = 320;
  const center = size / 2;
  const radius = 104;
  const rings = [25, 50, 75, 100];

  const getCoord = (index: number, valuePct: number, customRadius = radius) => {
    const angle = (Math.PI * 2 * index) / AXES_ORDER.length - Math.PI / 2;
    const r = (Math.max(0, Math.min(100, valuePct)) / 100) * customRadius;
    return {
      x: center + r * Math.cos(angle),
      y: center + r * Math.sin(angle),
    };
  };

  const buildPolygonPoints = (vec: VectorIdeologico) =>
    AXES_ORDER.map((axis, idx) => {
      const pt = getCoord(idx, vec[axis] ?? 0);
      return `${pt.x.toFixed(1)},${pt.y.toFixed(1)}`;
    }).join(' ');

  return (
    <div className="flex flex-col items-center">
      <div className="w-full max-w-[330px]">
        <svg
          viewBox={`0 0 ${size} ${size}`}
          className="w-full h-auto overflow-visible select-none"
          role="img"
          aria-label="Gráfico de radar de posicionamiento temático"
        >
          {/* Anillos concéntricos de grilla stroke="#e2e8f0" */}
          {rings.map((ringPct) => {
            const pts = AXES_ORDER.map((_, idx) => {
              const pt = getCoord(idx, ringPct);
              return `${pt.x.toFixed(1)},${pt.y.toFixed(1)}`;
            }).join(' ');
            return (
              <polygon
                key={ringPct}
                points={pts}
                fill="none"
                stroke="#e2e8f0"
                strokeWidth="1.25"
              />
            );
          })}

          {/* Ejes radiales y etiquetas de porcentaje */}
          {AXES_ORDER.map((axis, idx) => {
            const outer = getCoord(idx, 100);
            const labelPos = getCoord(idx, 124);
            const meta = EJE_TEMATICO_META[axis];
            return (
              <g key={axis}>
                <line
                  x1={center}
                  y1={center}
                  x2={outer.x}
                  y2={outer.y}
                  stroke="#e2e8f0"
                  strokeWidth="1.25"
                />
                <text
                  x={labelPos.x}
                  y={labelPos.y}
                  textAnchor="middle"
                  dominantBaseline="middle"
                  className="fill-slate-700 text-[11px] font-semibold"
                >
                  {meta.shortLabel}
                </text>
              </g>
            );
          })}

          {/* Polígono secundario (si se compara en el Match Electoral) */}
          {vectorComparacion && (
            <g>
              <polygon
                points={buildPolygonPoints(vectorComparacion)}
                fill="#64748b"
                fillOpacity={0.22}
                stroke="#475569"
                strokeWidth="2"
                strokeDasharray="4 3"
              />
            </g>
          )}

          {/* Polígono principal con fillOpacity={0.4} */}
          <polygon
            points={buildPolygonPoints(vectorPrincipal)}
            fill="#3b82f6"
            fillOpacity={0.4}
            stroke="#2563eb"
            strokeWidth="2.5"
          />

          {/* Vértices coloreados según su eje categórico */}
          {AXES_ORDER.map((axis, idx) => {
            const pt = getCoord(idx, vectorPrincipal[axis] ?? 0);
            const meta = EJE_TEMATICO_META[axis];
            return (
              <circle
                key={axis}
                cx={pt.x}
                cy={pt.y}
                r="4.5"
                fill={meta.hex}
                stroke="#ffffff"
                strokeWidth="1.75"
              />
            );
          })}
        </svg>
      </div>

      {vectorComparacion && (
        <div className="flex items-center gap-5 text-xs text-slate-600 mt-1">
          <div className="flex items-center gap-1.5">
            <span className="w-3 h-3 rounded-sm bg-blue-500/60 border border-blue-600 inline-block" />
            <span className="font-medium text-slate-800">{labelPrincipal}</span>
          </div>
          <div className="flex items-center gap-1.5">
            <span className="w-3 h-3 rounded-sm bg-slate-400/40 border border-dashed border-slate-600 inline-block" />
            <span className="font-medium text-slate-700">{labelComparacion}</span>
          </div>
        </div>
      )}
    </div>
  );
};
