import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Scale, Sparkles, ShieldCheck, Search } from 'lucide-react';

export const Navbar: React.FC = () => {
  const location = useLocation();
  const isHome = location.pathname === '/';
  const isMatch = location.pathname.startsWith('/match');

  return (
    <header className="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-sm">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        <Link
          to="/"
          className="flex items-center gap-2.5 group focus:outline-none"
          aria-label="Ir al inicio de LegisData DipuData"
        >
          <div className="w-9 h-9 rounded-lg bg-slate-900 text-white flex items-center justify-center shadow-sm group-hover:bg-slate-800 transition-colors">
            <Scale className="w-5 h-5" />
          </div>
          <div className="flex flex-col">
            <div className="flex items-center gap-1.5">
              <span className="font-extrabold text-lg tracking-[-0.02em] text-slate-900 leading-none">
                LegisData
              </span>
              <span className="text-xs font-mono font-medium px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">
                dipudata.com
              </span>
            </div>
            <span className="text-[11px] text-slate-500 hidden sm:inline">
              Auditoría Parlamentaria &amp; Patrimonial Argentina
            </span>
          </div>
        </Link>

        <div className="hidden lg:flex items-center gap-2 px-3 py-1 rounded-full bg-slate-50 border border-slate-200/80 text-xs text-slate-600">
          <ShieldCheck className="w-3.5 h-3.5 text-emerald-600 shrink-0" />
          <span>Datos Oficiales:</span>
          <span className="font-mono tabular-nums text-slate-800 font-medium">
            HCDN · HSN · OA DDJJ · USD MEP
          </span>
        </div>

        <nav className="flex items-center gap-2">
          <Link
            to="/"
            className={`inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-medium transition-all ${
              isHome
                ? 'bg-slate-900 text-white shadow-sm'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
            }`}
          >
            <Search className="w-4 h-4" />
            <span>Legisladores</span>
          </Link>

          <Link
            to="/match"
            className={`inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-semibold transition-all ${
              isMatch
                ? 'bg-blue-600 text-white shadow-sm ring-2 ring-blue-500/30'
                : 'bg-slate-100 text-slate-900 hover:bg-slate-200 border border-slate-200/80'
            }`}
          >
            <Sparkles className="w-4 h-4 text-blue-500" />
            <span>Match Electoral</span>
          </Link>
        </nav>
      </div>
    </header>
  );
};
