import React from 'react';
import { Link } from 'react-router-dom';
import { Scale, Database, Lock, ExternalLink } from 'lucide-react';

export const Footer: React.FC = () => {
  return (
    <footer className="bg-white border-t border-slate-200/80 mt-16">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div>
            <div className="flex items-center gap-2 mb-3">
              <div className="w-7 h-7 rounded-md bg-slate-900 text-white flex items-center justify-center">
                <Scale className="w-4 h-4" />
              </div>
              <span className="font-bold text-slate-900 tracking-[-0.02em]">
                LegisData · dipudata.com
              </span>
            </div>
            <p className="text-sm text-slate-500 leading-relaxed">
              Sistema de inteligencia legislativa y transparencia pública sin sesgo partidario.
              Unificamos votaciones nominales del Congreso, biografía parlamentaria y declaraciones
              juradas deflactadas por inflación (IPC) y tipo de cambio financiero (USD MEP).
            </p>
          </div>

          <div>
            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-900 mb-3 flex items-center gap-1.5">
              <Database className="w-3.5 h-3.5 text-slate-500" />
              Fuentes Primarias Verificadas
            </h3>
            <ul className="space-y-2 text-xs text-slate-500">
              <li className="flex items-center justify-between border-b border-slate-100 pb-1.5">
                <span>Votaciones Nominales</span>
                <span className="font-mono tabular-nums text-slate-700">
                  datos.hcdn.gob.ar / senado.gob.ar
                </span>
              </li>
              <li className="flex items-center justify-between border-b border-slate-100 pb-1.5">
                <span>Declaraciones Juradas (DDJJ)</span>
                <span className="font-mono tabular-nums text-slate-700">
                  Oficina Anticorrupción (OA)
                </span>
              </li>
              <li className="flex items-center justify-between border-b border-slate-100 pb-1.5">
                <span>Deflactación Monetaria</span>
                <span className="font-mono tabular-nums text-slate-700">
                  IPC INDEC &amp; USD MEP Cierre 31/12
                </span>
              </li>
              <li className="flex items-center justify-between">
                <span>Síntesis Biográfica</span>
                <span className="font-mono tabular-nums text-slate-700">
                  MediaWiki API (es.wikipedia.org)
                </span>
              </li>
            </ul>
          </div>

          <div>
            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-900 mb-3 flex items-center gap-1.5">
              <Lock className="w-3.5 h-3.5 text-slate-500" />
              Metodología y Accesos
            </h3>
            <p className="text-xs text-slate-500 leading-relaxed mb-4">
              El perfilamiento ideológico pondera cada voto nominal según el peso del expediente,
              excluyendo ausencias para no distorsionar la huella temática. El Match Electoral
              utiliza similitud de coseno tetradimensional.
            </p>
            <div className="flex flex-wrap gap-2">
              <Link
                to="/"
                className="text-xs font-medium px-3 py-1.5 rounded-md bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors"
              >
                Buscador de Representantes
              </Link>
              <Link
                to="/match"
                className="text-xs font-medium px-3 py-1.5 rounded-md bg-slate-900 text-white hover:bg-slate-800 transition-colors inline-flex items-center gap-1"
              >
                <span>Test Match Electoral</span>
                <ExternalLink className="w-3 h-3" />
              </Link>
            </div>
          </div>
        </div>

        <div className="mt-8 pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
          <span>
            © {new Date().getFullYear()} LegisData (dipudata.com) — Datos Públicos Abiertos de la
            República Argentina.
          </span>
          <span className="font-mono tabular-nums">
            Neutralidad Visual · Data-First · OWASP ASVS L3
          </span>
        </div>
      </div>
    </footer>
  );
};
