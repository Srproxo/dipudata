import { EjeTematico, SentidoVoto } from '../types/legisdata';

export function formatUSD(amount: number): string {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
  }).format(amount);
}

export function formatARS(amount: number): string {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    maximumFractionDigits: 0,
  }).format(amount);
}

export function formatCompactARS(amount: number): string {
  if (amount >= 1_000_000_000) {
    return `$ ${(amount / 1_000_000_000).toFixed(2).replace('.', ',')} mil M`;
  }
  if (amount >= 1_000_000) {
    return `$ ${(amount / 1_000_000).toFixed(1).replace('.', ',')} M`;
  }
  return formatARS(amount);
}

export function formatPct(val: number, includeSign = false): string {
  const formatted = `${Math.abs(val).toFixed(1).replace('.', ',')}%`;
  if (!includeSign) return `${val.toFixed(1).replace('.', ',')}%`;
  return val >= 0 ? `+${formatted}` : `-${formatted}`;
}

export function getVotoBadgeClasses(sentido: SentidoVoto): string {
  switch (sentido) {
    case 'Afirmativo':
      return 'text-emerald-700 bg-emerald-100 border border-emerald-200/80';
    case 'Negativo':
      return 'text-rose-700 bg-rose-100 border border-rose-200/80';
    case 'Abstencion':
    case 'Ausente':
    default:
      return 'text-slate-600 bg-slate-200 border border-slate-300/80';
  }
}

export function getVotoLabel(sentido: SentidoVoto): string {
  if (sentido === 'Abstencion') return 'Abstención';
  return sentido;
}

export const EJE_TEMATICO_META: Record<
  EjeTematico,
  {
    label: string;
    shortLabel: string;
    barClass: string;
    badgeClass: string;
    hex: string;
    descripcion: string;
  }
> = {
  Educacion: {
    label: 'Educación / Ciencia',
    shortLabel: 'Educación',
    barClass: 'bg-blue-500',
    badgeClass: 'bg-blue-50 text-blue-700 border border-blue-200',
    hex: '#3b82f6',
    descripcion: 'Financiamiento universitario, CONICET y servicio educativo estratégico.',
  },
  Salud: {
    label: 'Salud Pública',
    shortLabel: 'Salud',
    barClass: 'bg-teal-500',
    badgeClass: 'bg-teal-50 text-teal-700 border border-teal-200',
    hex: '#14b8a6',
    descripcion: 'Cobertura sanitaria obligatoria, emergencia pediátrica y cuidados paliativos.',
  },
  MercadoLibre: {
    label: 'Economía / Libre Mercado',
    shortLabel: 'Libre Mercado',
    barClass: 'bg-amber-500',
    badgeClass: 'bg-amber-50 text-amber-700 border border-amber-200',
    hex: '#f59e0b',
    descripcion: 'Desregulación comercial, RIGI, apertura económica y alivio fiscal.',
  },
  Proteccionismo: {
    label: 'Industria / Proteccionismo',
    shortLabel: 'Proteccionismo',
    barClass: 'bg-indigo-500',
    badgeClass: 'bg-indigo-50 text-indigo-700 border border-indigo-200',
    hex: '#6366f1',
    descripcion: 'Regulación estatal de mercados internos, alquileres y tributos progresivos.',
  },
  Laboral: {
    label: 'Derecho Laboral',
    shortLabel: 'Laboral',
    barClass: 'bg-violet-500',
    badgeClass: 'bg-violet-50 text-violet-700 border border-violet-200',
    hex: '#8b5cf6',
    descripcion: 'Regulación del teletrabajo, tutela sindical y exención de Ganancias en horas extras.',
  },
};

export function getBloqueBadgeStyle(bloque: string): string {
  const b = bloque.toLowerCase();
  if (b.includes('libertad')) {
    return 'bg-purple-50 text-purple-800 border border-purple-200';
  }
  if (b === 'pro') {
    return 'bg-amber-50 text-amber-800 border border-amber-200';
  }
  if (b.includes('ucr') || b.includes('radical')) {
    return 'bg-rose-50 text-rose-800 border border-rose-200';
  }
  if (b.includes('patria')) {
    return 'bg-sky-50 text-sky-800 border border-sky-200';
  }
  if (b.includes('izquierda')) {
    return 'bg-red-50 text-red-800 border border-red-200';
  }
  return 'bg-slate-100 text-slate-700 border border-slate-200';
}

export function getInitials(nombreCompleto: string): string {
  return nombreCompleto
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((n) => n[0]?.toUpperCase() ?? '')
    .join('');
}
