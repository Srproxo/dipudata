import React from 'react';
import { EjeTematico, VectorIdeologico } from '../types/legisdata';
import { EJE_TEMATICO_META, formatPct } from '../utils/formatters';

interface BarrasTematicasProps {
  vector: VectorIdeologico;
  mostrarLaboral?: boolean;
}

export const BarrasTematicas: React.FC<BarrasTematicasProps> = ({
  vector,
  mostrarLaboral = true,
}) => {
  const ejes: EjeTematico[] = mostrarLaboral
    ? ['Educacion', 'Salud', 'MercadoLibre', 'Proteccionismo', 'Laboral']
    : ['Educacion', 'Salud', 'MercadoLibre', 'Proteccionismo'];

  return (
    <div className="space-y-4">
      {ejes.map((eje) => {
        const meta = EJE_TEMATICO_META[eje];
        const valor = Math.max(0, Math.min(100, vector[eje] ?? 0));

        return (
          <div key={eje} className="space-y-1.5">
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center gap-2">
                <span className={`w-2.5 h-2.5 rounded-full ${meta.barClass}`} />
                <span className="text-sm font-semibold text-slate-900">
                  {meta.label}
                </span>
              </div>
              <span className="font-mono tabular-nums text-sm font-bold text-slate-900">
                {formatPct(valor)}
              </span>
            </div>

            <div className="w-full h-2.5 rounded-full bg-slate-200 overflow-hidden">
              <div
                className={`h-2.5 rounded-full ${meta.barClass} transition-all duration-500`}
                style={{ width: `${valor}%` }}
              />
            </div>

            <p className="text-xs text-slate-500 leading-snug">{meta.descripcion}</p>
          </div>
        );
      })}
    </div>
  );
};
