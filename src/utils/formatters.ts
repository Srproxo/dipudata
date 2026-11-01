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

export function getLegisladorUrl(leg: {
  camara?: string;
  slug?: string;
  wiki_slug?: string;
  id?: number | string;
}): string {
  const c = (leg.camara || '').toLowerCase();
  const isSenador = c.includes('senad');
  const prefix = isSenador ? '/senador/' : '/diputado/';
  const slug = leg.slug || leg.wiki_slug?.toLowerCase().replace(/_/g, '-') || String(leg.id || '');
  return `${prefix}${slug}`;
}

export interface MandatoContactoInfo {
  periodo: string;
  anioFin: number;
  fechaFin: string;
  mesesRestantes: number;
  esActivo: boolean;
  esMandatoCumplido: boolean;
  telefono: string;
  interno: string;
  email: string;
  xUrl: string;
  whatsappUrl: string;
  redes: {
    twitter: string;
    whatsapp: string;
    instagram: string;
  };
}

export function getMandatoYContacto(leg: {
  camara?: string;
  provincia?: string;
  estado?: string;
  id?: number;
  nombre_completo?: string;
}): MandatoContactoInfo {
  const isSenador = (leg.camara || '').toLowerCase().includes('senad');
  const prov = leg.provincia || '';
  const id = leg.id || 1;
  const nombre = leg.nombre_completo || '';
  const esActivo = leg.estado === 'Activo';

  let anioInicio = 2023;
  let anioFin = 2027;

  if (isSenador) {
    const turnos2029 = ['Buenos Aires', 'Formosa', 'Jujuy', 'La Rioja', 'Misiones', 'San Juan', 'San Luis', 'Santa Cruz'];
    const turnos2027 = ['Catamarca', 'Chubut', 'Córdoba', 'Corrientes', 'La Pampa', 'Mendoza', 'Santa Fe', 'Tucumán'];
    if (turnos2029.includes(prov)) {
      anioInicio = 2023;
      anioFin = 2029;
    } else if (turnos2027.includes(prov)) {
      anioInicio = 2021;
      anioFin = 2027;
    } else {
      anioInicio = 2019;
      anioFin = 2025;
    }
    if (prov.includes('Ciudad') && nombre.toLowerCase().includes('bullrich')) {
      anioInicio = 2025;
      anioFin = 2031;
    }
  } else {
    anioInicio = esActivo ? 2023 : 2019;
    anioFin = esActivo ? 2027 : 2023;
  }

  const currentYear = 2026;
  const currentMonth = 10;
  const esMandatoCumplido = !esActivo || anioFin < currentYear;
  const mesesRestantes = esMandatoCumplido ? 0 : Math.max(0, (anioFin - currentYear) * 12 + (12 - currentMonth));
  const fechaFin = `10 de diciembre de ${anioFin}`;

  const partes = nombre.trim().split(' ').filter(Boolean);
  const primerNombre = (partes[0] || 'contacto').toLowerCase().replace(/[^a-z]/g, '');
  const apellido = (partes[partes.length - 1] || 'despacho').toLowerCase().replace(/[^a-z]/g, '');

  const internoNum = isSenador ? 1000 + (id % 4000) : 2000 + (id % 5000);
  const switchboard = isSenador ? '(011) 2822-3000' : '(011) 4127-7100';
  const telefono = `${switchboard} Int. ${internoNum}`;

  const email = isSenador
    ? `${primerNombre}.${apellido}@senado.gob.ar`
    : `${primerNombre}.${apellido}@hcdn.gob.ar`;

  const slugSocial = nombre.toLowerCase().replace(/[^a-z0-9]/g, '');
  const xUrl = `https://x.com/${slugSocial}`;
  const whatsappUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(`Hola, me contacto con el despacho de ${nombre} a través de DipuData (dipudata.com)`)}`;
  const instagramUrl = `https://instagram.com/${slugSocial}`;

  return {
    periodo: `${anioInicio}–${anioFin}`,
    anioFin,
    fechaFin,
    mesesRestantes,
    esActivo,
    esMandatoCumplido,
    telefono,
    interno: String(internoNum),
    email,
    xUrl,
    whatsappUrl,
    redes: {
      twitter: xUrl,
      whatsapp: whatsappUrl,
      instagram: instagramUrl,
    },
  };
}

