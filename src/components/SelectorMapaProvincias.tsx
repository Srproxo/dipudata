import React from 'react';
import { MapPin, X } from 'lucide-react';
import { REGIONES_PROVINCIAS_ARGENTINA } from '../data/seedData';
import { LegisladorResumen } from '../types/legisdata';

interface SelectorMapaProvinciasProps {
  legisladores: LegisladorResumen[];
  provinciaSeleccionada: string;
  onSelectProvincia: (prov: string) => void;
}

export const SelectorMapaProvincias: React.FC<SelectorMapaProvinciasProps> = ({
  legisladores,
  provinciaSeleccionada,
  onSelectProvincia,
}) => {
  // Contar legisladores por provincia
  const conteoPorProvincia = React.useMemo(() => {
    const map: Record<string, number> = {};
    for (const leg of legisladores) {
      map[leg.provincia] = (map[leg.provincia] ?? 0) + 1;
    }
    return map;
  }, [legisladores]);

  return (
    <div className="bg-white rounded-xl border border-slate-200/80 shadow-sm p-4 sm:p-5">
      <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div className="flex items-center gap-2">
          <MapPin className="w-4 h-4 text-slate-700" />
          <h2 className="text-xs font-bold uppercase tracking-wider text-slate-900">
            Mapa Federal por Regiones y Distritos
          </h2>
        </div>

        {provinciaSeleccionada !== 'Todas' && (
          <button
            type="button"
            onClick={() => onSelectProvincia('Todas')}
            className="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-800 bg-blue-50 px-2.5 py-1 rounded-full transition-colors"
          >
            <span>Filtro activo: {provinciaSeleccionada}</span>
            <X className="w-3.5 h-3.5" />
          </button>
        )}
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        {REGIONES_PROVINCIAS_ARGENTINA.map((reg) => (
          <div
            key={reg.codigo}
            className="rounded-lg bg-slate-50 border border-slate-200/70 p-3 flex flex-col justify-between"
          >
            <div className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-2">
              {reg.region}
            </div>
            <div className="flex flex-wrap gap-1.5">
              {reg.provincias.map((prov) => {
                const count = conteoPorProvincia[prov.nombre] ?? 0;
                const isSelected = provinciaSeleccionada === prov.nombre;

                return (
                  <button
                    key={prov.nombre}
                    type="button"
                    onClick={() =>
                      onSelectProvincia(isSelected ? 'Todas' : prov.nombre)
                    }
                    className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium transition-all ${
                      isSelected
                        ? 'bg-slate-900 text-white shadow-sm ring-2 ring-slate-900/20'
                        : count > 0
                        ? 'bg-white text-slate-800 border border-slate-200 hover:border-slate-400'
                        : 'bg-slate-100/80 text-slate-400 border border-slate-200/50 hover:text-slate-600'
                    }`}
                    title={`${prov.nombre} (${count} representantes en muestra activa)`}
                  >
                    <span>{prov.abreviatura}</span>
                    <span
                      className={`font-mono tabular-nums text-[10px] px-1 rounded ${
                        isSelected
                          ? 'bg-slate-700 text-white'
                          : count > 0
                          ? 'bg-slate-100 text-slate-700'
                          : 'text-slate-400'
                      }`}
                    >
                      {count}
                    </span>
                  </button>
                );
              })}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};
