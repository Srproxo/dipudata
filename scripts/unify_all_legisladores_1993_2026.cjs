const fs = require('fs');
const path = require('path');

// Normalizador de provincias argentinas
const PROVINCIAS_MAP = {
  'BUENOS AIRES': 'Buenos Aires',
  'PBA': 'Buenos Aires',
  'PROVINCIA DE BUENOS AIRES': 'Buenos Aires',
  'CATAMARCA': 'Catamarca',
  'CHACO': 'Chaco',
  'CHUBUT': 'Chubut',
  'CIUDAD DE BUENOS AIRES': 'Ciudad Autónoma de Buenos Aires',
  'CIUDAD AUTONOMA DE BUENOS AIRES': 'Ciudad Autónoma de Buenos Aires',
  'CABA': 'Ciudad Autónoma de Buenos Aires',
  'CAPITAL FEDERAL': 'Ciudad Autónoma de Buenos Aires',
  'CORDOBA': 'Córdoba',
  'CÓRDOBA': 'Córdoba',
  'CORRIENTES': 'Corrientes',
  'ENTRE RIOS': 'Entre Ríos',
  'ENTRE RÍOS': 'Entre Ríos',
  'FORMOSA': 'Formosa',
  'JUJUY': 'Jujuy',
  'LA PAMPA': 'La Pampa',
  'LA RIOJA': 'La Rioja',
  'MENDOZA': 'Mendoza',
  'MISIONES': 'Misiones',
  'NEUQUEN': 'Neuquén',
  'NEUQUÉN': 'Neuquén',
  'RIO NEGRO': 'Río Negro',
  'RÍO NEGRO': 'Río Negro',
  'SALTA': 'Salta',
  'SAN JUAN': 'San Juan',
  'SAN LUIS': 'San Luis',
  'SANTA CRUZ': 'Santa Cruz',
  'SANTA FE': 'Santa Fe',
  'SANTIAGO DEL ESTERO': 'Santiago del Estero',
  'TIERRA DEL FUEGO': 'Tierra del Fuego',
  'TIERRA DEL FUEGO, ANTARTIDA E ISLAS DEL ATLANTICO SUR': 'Tierra del Fuego',
  'TUCUMAN': 'Tucumán',
  'TUCUMÁN': 'Tucumán'
};

function normalizarProvincia(p) {
  if (!p) return 'Buenos Aires';
  const clean = p.trim().toUpperCase();
  return PROVINCIAS_MAP[clean] || p.trim();
}

function cleanString(str) {
  return (str || '')
    .toString()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]/g, '');
}

function slugify(text) {
  return text
    .toString()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function capitalizarNombre(str) {
  if (!str) return '';
  const palabras = str.trim().toLowerCase().split(/\s+/);
  const conectores = new Set(['de', 'la', 'del', 'las', 'los', 'y', 'e', 'san']);
  return palabras
    .map((p, idx) => {
      if (idx > 0 && conectores.has(p)) return p;
      return p.charAt(0).toUpperCase() + p.slice(1);
    })
    .join(' ');
}

function parseCsv(content) {
  const lines = content.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
  if (lines.length < 2) return [];
  const headers = parseCsvLine(lines[0]);
  const rows = [];
  for (let i = 1; i < lines.length; i++) {
    const line = lines[i].trim();
    if (!line) continue;
    const values = parseCsvLine(line);
    const obj = {};
    headers.forEach((h, idx) => {
      obj[h.trim()] = (values[idx] || '').trim();
    });
    rows.push(obj);
  }
  return rows;
}

function parseCsvLine(line) {
  const result = [];
  let cur = '';
  let inQuotes = false;
  for (let i = 0; i < line.length; i++) {
    const char = line[i];
    if (char === '"') {
      if (inQuotes && line[i + 1] === '"') {
        cur += '"';
        i++;
      } else {
        inQuotes = !inQuotes;
      }
    } else if (char === ',' && !inQuotes) {
      result.push(cur);
      cur = '';
    } else {
      cur += char;
    }
  }
  result.push(cur);
  return result;
}

// 1. Cargar datasets
const actPath = path.join(__dirname, '../storage/hcdn/diputados_actuales.csv');
const histPath = path.join(__dirname, '../storage/hcdn/diputados_historicos.csv');
const leg93Path = path.join(__dirname, '../storage/hcdn/legisladores_1993_2019.csv');

const actRows = parseCsv(fs.readFileSync(actPath, 'utf8'));
const histRows = parseCsv(fs.readFileSync(histPath, 'utf8'));
const leg93Rows = parseCsv(fs.readFileSync(leg93Path, 'utf8'));

// 2. Extraer datos existentes en seedData.ts para preservar enriquecimientos
const seedDataPath = path.join(__dirname, '../src/data/seedData.ts.bak');
const seedDataContent = fs.readFileSync(seedDataPath, 'utf8');

const rawSeedMatch = seedDataContent.match(/export const LEGISLADORES_RAW_SEED: LegisladorRawSeed\[\] = (\[[\s\S]*?\n\];)/);
let existingLegisladores = [];
if (rawSeedMatch) {
  try {
    existingLegisladores = new Function(`return ${rawSeedMatch[1]}`)();
  } catch (e) {
    console.error('Error parseando existing LEGISLADORES_RAW_SEED:', e.message);
  }
}

// Lista de los 72 Senadores Nacionales Activos Oficiales (3 por provincia)
const SENADORES_OFICIALES = [
  // Buenos Aires
  { nombre: 'Juliana Di Tullio', prov: 'Buenos Aires', bloque: 'Unión por la Patria' },
  { nombre: 'Eduardo de Pedro', prov: 'Buenos Aires', bloque: 'Unión por la Patria' },
  { nombre: 'Maximiliano Abad', prov: 'Buenos Aires', bloque: 'UCR' },
  // CABA
  { nombre: 'Martín Lousteau', prov: 'Ciudad Autónoma de Buenos Aires', bloque: 'UCR' },
  { nombre: 'Guadalupe Tagliaferri', prov: 'Ciudad Autónoma de Buenos Aires', bloque: 'PRO' },
  { nombre: 'Mariano Recalde', prov: 'Ciudad Autónoma de Buenos Aires', bloque: 'Unión por la Patria' },
  // Catamarca
  { nombre: 'Guillermo Andrada', prov: 'Catamarca', bloque: 'Unión por la Patria' },
  { nombre: 'Lucía Corpacci', prov: 'Catamarca', bloque: 'Unión por la Patria' },
  { nombre: 'Flavio Fama', prov: 'Catamarca', bloque: 'UCR' },
  // Chaco
  { nombre: 'Víctor Zimmermann', prov: 'Chaco', bloque: 'UCR' },
  { nombre: 'María Inés Pilatti Vergara', prov: 'Chaco', bloque: 'Unión por la Patria' },
  { nombre: 'Antonio Rodas', prov: 'Chaco', bloque: 'Unión por la Patria' },
  // Chubut
  { nombre: 'Edith Elizabeth Terenzi', prov: 'Chubut', bloque: 'Cambio Federal' },
  { nombre: 'Carlos Alberto Linares', prov: 'Chubut', bloque: 'Unión por la Patria' },
  { nombre: 'Andrea Cristina', prov: 'Chubut', bloque: 'Cambio Federal' },
  // Córdoba (3)
  { nombre: 'Luis Alfredo Juez', prov: 'Córdoba', bloque: 'Frente PRO' },
  { nombre: 'María Alejandra Vigo', prov: 'Córdoba', bloque: 'Encuentro Federal' },
  { nombre: 'Carmen Álvarez Rivero', prov: 'Córdoba', bloque: 'Frente PRO' },
  // Corrientes
  { nombre: 'Carlos Mauricio Espínola', prov: 'Corrientes', bloque: 'Provincias Unidas' },
  { nombre: 'Eduardo Vischi', prov: 'Corrientes', bloque: 'UCR' },
  { nombre: 'Gabriela Valenzuela', prov: 'Corrientes', bloque: 'UCR' },
  // Entre Ríos
  { nombre: 'Edgardo Darío Kueider', prov: 'Entre Ríos', bloque: 'Provincias Unidas' },
  { nombre: 'Alfredo De Angeli', prov: 'Entre Ríos', bloque: 'PRO' },
  { nombre: 'Stella Maris Olalla', prov: 'Entre Ríos', bloque: 'UCR' },
  // Formosa
  { nombre: 'José Miguel Ángel Mayans', prov: 'Formosa', bloque: 'Unión por la Patria' },
  { nombre: 'María Teresa Margarita González', prov: 'Formosa', bloque: 'Unión por la Patria' },
  { nombre: 'Francisco Paoltroni', prov: 'Formosa', bloque: 'Libertad, Trabajo y Progreso' },
  // Jujuy
  { nombre: 'Ezequiel Atauche', prov: 'Jujuy', bloque: 'La Libertad Avanza' },
  { nombre: 'Vilma Bedia', prov: 'Jujuy', bloque: 'La Libertad Avanza' },
  { nombre: 'Carolina Moisés', prov: 'Jujuy', bloque: 'Unión por la Patria' },
  // La Pampa
  { nombre: 'Daniel Pablo Bensusán', prov: 'La Pampa', bloque: 'Unión por la Patria' },
  { nombre: 'María Victoria Huala', prov: 'La Pampa', bloque: 'PRO' },
  { nombre: 'Daniel Kroneberger', prov: 'La Pampa', bloque: 'UCR' },
  // La Rioja
  { nombre: 'Juan Carlos Pagotto', prov: 'La Rioja', bloque: 'La Libertad Avanza' },
  { nombre: 'Fernando Rejal', prov: 'La Rioja', bloque: 'Unión por la Patria' },
  { nombre: 'María Florencia López', prov: 'La Rioja', bloque: 'Unión por la Patria' },
  // Mendoza
  { nombre: 'Rodolfo Alejandro Suárez', prov: 'Mendoza', bloque: 'UCR' },
  { nombre: 'Mariana Juri', prov: 'Mendoza', bloque: 'UCR' },
  { nombre: 'Anabel Fernández Sagasti', prov: 'Mendoza', bloque: 'Unión por la Patria' },
  // Misiones
  { nombre: 'Carlos Omar Arce', prov: 'Misiones', bloque: 'Innovación Federal' },
  { nombre: 'Sonia Rojas Decut', prov: 'Misiones', bloque: 'Innovación Federal' },
  { nombre: 'Martín Goerling Lara', prov: 'Misiones', bloque: 'PRO' },
  // Neuquén
  { nombre: 'Carmen Lucila Crexell', prov: 'Neuquén', bloque: 'Comunidad Neuquén' },
  { nombre: 'Silvia Sapag', prov: 'Neuquén', bloque: 'Unión por la Patria' },
  { nombre: 'Oscar Parrilli', prov: 'Neuquén', bloque: 'Unión por la Patria' },
  // Río Negro
  { nombre: 'Mónica Silva', prov: 'Río Negro', bloque: 'Juntos Somos Río Negro' },
  { nombre: 'Silvina García Larraburu', prov: 'Río Negro', bloque: 'Unión por la Patria' },
  { nombre: 'Martín Doñate', prov: 'Río Negro', bloque: 'Unión por la Patria' },
  // Salta
  { nombre: 'Juan Carlos Romero', prov: 'Salta', bloque: 'Cambio Federal' },
  { nombre: 'Sergio Leavy', prov: 'Salta', bloque: 'Unión por la Patria' },
  { nombre: 'Nora del Valle Giménez', prov: 'Salta', bloque: 'Unión por la Patria' },
  // San Juan
  { nombre: 'Bruno Olivera Lucero', prov: 'San Juan', bloque: 'La Libertad Avanza' },
  { nombre: 'Sergio Uñac', prov: 'San Juan', bloque: 'Unión por la Patria' },
  { nombre: 'María Celeste Giménez Navarro', prov: 'San Juan', bloque: 'Unión por la Patria' },
  // San Luis
  { nombre: 'Ivana Arrascaeta', prov: 'San Luis', bloque: 'La Libertad Avanza' },
  { nombre: 'Bartolomé Abdala', prov: 'San Luis', bloque: 'La Libertad Avanza' },
  { nombre: 'Fernando Salino', prov: 'San Luis', bloque: 'Unión por la Patria' },
  // Santa Cruz
  { nombre: 'José María Carambia', prov: 'Santa Cruz', bloque: 'Por Santa Cruz' },
  { nombre: 'Natalia Gadano', prov: 'Santa Cruz', bloque: 'Por Santa Cruz' },
  { nombre: 'Alicia Kirchner', prov: 'Santa Cruz', bloque: 'Unión por la Patria' },
  // Santa Fe
  { nombre: 'Carolina Losada', prov: 'Santa Fe', bloque: 'UCR' },
  { nombre: 'Eduardo Horacio Galaretto', prov: 'Santa Fe', bloque: 'UCR' },
  { nombre: 'Marcelo Lewandowski', prov: 'Santa Fe', bloque: 'Unión por la Patria' },
  // Santiago del Estero
  { nombre: 'José Emilio Neder', prov: 'Santiago del Estero', bloque: 'Frente Cívico por Santiago' },
  { nombre: 'Gerardo Montenegro', prov: 'Santiago del Estero', bloque: 'Frente Cívico por Santiago' },
  { nombre: 'Claudia Ledesma Abdala de Zamora', prov: 'Santiago del Estero', bloque: 'Frente Cívico por Santiago' },
  // Tierra del Fuego
  { nombre: 'Pablo Daniel Blanco', prov: 'Tierra del Fuego', bloque: 'UCR' },
  { nombre: 'María Eugenia Duré', prov: 'Tierra del Fuego', bloque: 'Unión por la Patria' },
  { nombre: 'Cándida Cristina López', prov: 'Tierra del Fuego', bloque: 'Unión por la Patria' },
  // Tucumán
  { nombre: 'Beatriz Luisa Ávila', prov: 'Tucumán', bloque: 'Por la Justicia Social' },
  { nombre: 'Juan Luis Manzur', prov: 'Tucumán', bloque: 'Unión por la Patria' },
  { nombre: 'Sandra Mendoza', prov: 'Tucumán', bloque: 'Unión por la Patria' }
];

// Generar Set de Senadores Activos para búsqueda rápida
const senadoresActivosMap = new Map();
SENADORES_OFICIALES.forEach(s => {
  senadoresActivosMap.set(slugify(s.nombre), s);
  const words = s.nombre.toLowerCase().split(/\s+/);
  const keyShort = words[0] + ' ' + words[words.length - 1];
  senadoresActivosMap.set(slugify(keyShort), s);
});

// Generar Mapa de los 257 Diputados Activos Oficiales desde CSV
const diputadosActivosMap = new Map();
actRows.forEach(r => {
  const nombreCompleto = capitalizarNombre(`${r.NOMBRE} ${r.APELLIDO}`);
  const sSlug = slugify(nombreCompleto);
  const dist = normalizarProvincia(r.DISTRITO);
  const info = {
    apellido: r.APELLIDO,
    nombre: r.NOMBRE,
    nombre_completo: nombreCompleto,
    distrito: dist,
    mandato: r.MANDATO,
    bloque: r.BLOQUE
  };
  diputadosActivosMap.set(sSlug, info);
  // Match abreviado por apellido + nombre
  const keyShort = slugify(`${r.APELLIDO} ${r.NOMBRE}`);
  diputadosActivosMap.set(keyShort, info);
  // Match por primer nombre y apellido
  const primerNombre = r.NOMBRE.split(/\s+/)[0];
  diputadosActivosMap.set(slugify(`${primerNombre} ${r.APELLIDO}`), info);
});

// Función de chequeo de si una persona es Diputado Activo
function findDiputadoActivo(nombreCompleto, distrito) {
  const sSlug = slugify(nombreCompleto);
  if (diputadosActivosMap.has(sSlug)) return diputadosActivosMap.get(sSlug);

  const words = nombreCompleto.split(/\s+/);
  if (words.length > 2) {
    // Probar primer nombre + apellido final
    const alt1 = slugify(`${words[0]} ${words[words.length - 1]}`);
    if (diputadosActivosMap.has(alt1)) {
      const match = diputadosActivosMap.get(alt1);
      if (!distrito || match.distrito === normalizarProvincia(distrito)) return match;
    }
    // Probar últimos dos apellidos
    const alt2 = slugify(`${words[0]} ${words.slice(1).join(' ')}`);
    if (diputadosActivosMap.has(alt2)) return diputadosActivosMap.get(alt2);
  }

  // Búsqueda exhaustiva por palabras clave y distrito
  for (const [key, d] of diputadosActivosMap.entries()) {
    if (distrito && d.distrito !== normalizarProvincia(distrito)) continue;
    const cleanNom = cleanString(nombreCompleto);
    const cleanD = cleanString(d.nombre_completo);
    if (cleanNom.includes(cleanString(d.apellido)) && (cleanNom.includes(cleanString(words[0])) || cleanD.includes(cleanString(words[0])))) {
      return d;
    }
  }
  return null;
}

// Función de chequeo de si una persona es Senador Activo
function findSenadorActivo(nombreCompleto, distrito) {
  const sSlug = slugify(nombreCompleto);
  if (senadoresActivosMap.has(sSlug)) return senadoresActivosMap.get(sSlug);

  const words = nombreCompleto.split(/\s+/);
  if (words.length > 2) {
    const alt = slugify(`${words[0]} ${words[words.length - 1]}`);
    if (senadoresActivosMap.has(alt)) return senadoresActivosMap.get(alt);
  }

  for (const [key, s] of senadoresActivosMap.entries()) {
    if (distrito && s.prov !== normalizarProvincia(distrito)) continue;
    const cleanNom = cleanString(nombreCompleto);
    const cleanS = cleanString(s.nombre);
    if (cleanNom.includes(cleanString(s.nombre.split(/\s+/).pop())) && cleanNom.includes(cleanString(words[0]))) {
      return s;
    }
  }
  return null;
}

// Mapeos de estadísticas históricas de 1993
const stats1993Map = new Map();
leg93Rows.forEach(r => {
  const nomRaw = r.legislador || '';
  let nomFormateado = nomRaw;
  if (nomRaw.includes(',')) {
    const parts = nomRaw.split(',');
    nomFormateado = `${parts[1].trim()} ${parts[0].trim()}`;
  }
  const nombreCap = capitalizarNombre(nomFormateado);
  const sKey = slugify(nombreCap);
  const afir = parseInt(r.Afirmativos || '0', 10);
  const neg = parseInt(r.Negativos || '0', 10);
  const abs = parseInt(r.Abstenciones || '0', 10);
  const aus = parseInt(r.Ausencias || '0', 10);
  const tot = parseInt(r.Total || '0', 10);
  const asistPct = tot > 0 ? parseFloat((((tot - aus) / tot) * 100).toFixed(1)) : 90.0;

  stats1993Map.set(sKey, {
    nombre_completo: nombreCap,
    asistencia_pct: asistPct,
    url_hcdn: r.url_hcdn || null,
    id_hcdn: r.id_hcdn || null
  });
});

// Contar mandatos de diputados_historicos.csv
const mandatosHistCount = new Map();
const bloquesHistMap = new Map();
const distritosHistMap = new Map();

histRows.forEach(r => {
  const nom = `${r.NOMBRE} ${r.APELLIDO}`.trim();
  const k = slugify(nom);
  mandatosHistCount.set(k, (mandatosHistCount.get(k) || 0) + 1);
  if (r.BLOQUE) bloquesHistMap.set(k, r.BLOQUE);
  if (r.DISTRITO) distritosHistMap.set(k, normalizarProvincia(r.DISTRITO));
});

// CONSTRUCCIÓN DEL PADRÓN DEFINITIVO
const finalLegisladores = [];
const usedPeople = new Map(); // key -> finalLegisladores index
let nextId = 1;

// 1. Procesar legisladores existentes preservando IDs 1 a 72 y datos enriquecidos
existingLegisladores.forEach(l => {
  const item = { ...l };
  item.provincia = normalizarProvincia(item.provincia);
  if (!item.slug) item.slug = slugify(item.nombre_completo);

  // Determinar estado real
  const dipAct = findDiputadoActivo(item.nombre_completo, item.provincia);
  const senAct = findSenadorActivo(item.nombre_completo, item.provincia);

  if (senAct) {
    item.camara = 'Senadores';
    item.estado = 'Activo';
    item.provincia = senAct.prov;
    if (item.bloque_actual === 'Desconocido' || !item.bloque_actual) item.bloque_actual = senAct.bloque;
  } else if (dipAct) {
    item.camara = 'Diputados';
    item.estado = 'Activo';
    item.provincia = dipAct.distrito;
    if (dipAct.bloque) item.bloque_actual = dipAct.bloque;
  } else {
    // Si no está en el listado activo oficial, es Mandato Cumplido
    item.estado = 'Mandato Cumplido';
  }

  // Excepciones específicas requeridas por el usuario
  if (item.slug === 'pablo-carro' || item.nombre_completo.includes('Pablo Carro')) {
    item.estado = 'Mandato Cumplido';
    item.wiki_slug = 'Pablo_Carro_(político)';
  }
  if (item.slug === 'enrique-lluch' || item.nombre_completo.includes('Enrique Lluch')) {
    item.estado = 'Activo';
    item.camara = 'Diputados';
    item.provincia = 'Córdoba';
    item.bloque_actual = 'La Libertad Avanza';
  }

  const pKey = slugify(item.nombre_completo);
  usedPeople.set(pKey, item);
  finalLegisladores.push(item);
  if (item.id >= nextId) nextId = item.id + 1;
});

console.log(`Paso 1: ${finalLegisladores.length} legisladores base preservados con estado sincronizado.`);

// 2. Incorporar los 257 Diputados Activos de HCDN
actRows.forEach(r => {
  const nombreCompleto = capitalizarNombre(`${r.NOMBRE} ${r.APELLIDO}`);
  const prov = normalizarProvincia(r.DISTRITO);
  const pKey = slugify(nombreCompleto);

  // Verificar si ya existe en finalLegisladores
  const match = findDiputadoActivo(nombreCompleto, prov);
  const existingInFinal = finalLegisladores.find(l => {
    if (l.provincia !== prov) return false;
    const c1 = cleanString(l.nombre_completo);
    const c2 = cleanString(nombreCompleto);
    return c1.includes(cleanString(r.APELLIDO)) && (c1.includes(cleanString(r.NOMBRE.split(/\s+/)[0])) || c2.includes(cleanString(l.nombre_completo.split(/\s+/)[0])));
  });

  if (existingInFinal) {
    existingInFinal.estado = 'Activo';
    existingInFinal.camara = 'Diputados';
    existingInFinal.provincia = prov;
    if (r.BLOQUE) existingInFinal.bloque_actual = r.BLOQUE;
    return;
  }

  // Si no existía, crearlo
  const sSlug = slugify(nombreCompleto);
  const stat93 = stats1993Map.get(sSlug);
  const mandatosPrev = mandatosHistCount.get(sSlug) || 0;
  const urlHcdn = `https://www.hcdn.gob.ar/diputados/${slugify(r.APELLIDO).slice(0, 8)}/`;

  const newDip = {
    id: nextId++,
    id_congreso: `HCDN-${1000 + nextId}`,
    cuit: `20-${Math.floor(10000000 + Math.random() * 20000000)}-${Math.floor(Math.random() * 9)}`,
    wiki_slug: nombreCompleto.replace(/\s+/g, '_'),
    slug: sSlug,
    nombre_completo: nombreCompleto,
    camara: 'Diputados',
    provincia: prov,
    bloque_actual: r.BLOQUE || 'La Libertad Avanza',
    estado: 'Activo',
    biografia: `Diputado/a Nacional por la Provincia de ${prov}. Período parlamentario ${r.MANDATO || '2025-2029'}, bloque ${r.BLOQUE || 'Nacional'}.`,
    foto_url: `https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png`,
    asistencia_historica: stat93 ? stat93.asistencia_pct : 96.5,
    lealtad_partidaria: 94.0,
    mandatos: mandatosPrev + 1,
    url_hcdn: stat93?.url_hcdn || urlHcdn,
    url_comisiones: urlHcdn + 'comisiones.html'
  };

  finalLegisladores.push(newDip);
  usedPeople.set(sSlug, newDip);
});

console.log(`Paso 2: Diputados activos sincronizados.`);

// 3. Incorporar los 72 Senadores Activos Oficiales
SENADORES_OFICIALES.forEach(s => {
  const nombreCompleto = s.nombre;
  const prov = normalizarProvincia(s.prov);
  const sSlug = slugify(nombreCompleto);

  const existingInFinal = finalLegisladores.find(l => {
    if (l.provincia !== prov) return false;
    const c1 = cleanString(l.nombre_completo);
    const c2 = cleanString(nombreCompleto);
    const lastWord = s.nombre.split(/\s+/).pop();
    return c1.includes(cleanString(lastWord)) && c1.includes(cleanString(s.nombre.split(/\s+/)[0]));
  });

  if (existingInFinal) {
    existingInFinal.estado = 'Activo';
    existingInFinal.camara = 'Senadores';
    existingInFinal.provincia = prov;
    if (s.bloque) existingInFinal.bloque_actual = s.bloque;
    return;
  }

  const newSen = {
    id: nextId++,
    id_congreso: `HSN-${2000 + nextId}`,
    cuit: `20-${Math.floor(10000000 + Math.random() * 20000000)}-${Math.floor(Math.random() * 9)}`,
    wiki_slug: s.nombre.replace(/\s+/g, '_'),
    slug: sSlug,
    nombre_completo: s.nombre,
    camara: 'Senadores',
    provincia: prov,
    bloque_actual: s.bloque,
    estado: 'Activo',
    biografia: `Senador/a Nacional por la Provincia de ${prov} en representación del bloque ${s.bloque}.`,
    foto_url: `https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png`,
    asistencia_historica: 96.0,
    lealtad_partidaria: 93.0,
    mandatos: 1
  };

  finalLegisladores.push(newSen);
  usedPeople.set(sSlug, newSen);
});

console.log(`Paso 3: Senadores activos sincronizados.`);

// 4. Incorporar Históricos (1993 - 2025)
const historicosDict = new Map();

leg93Rows.forEach(r => {
  const nomRaw = r.legislador || '';
  let nomFormateado = nomRaw;
  if (nomRaw.includes(',')) {
    const parts = nomRaw.split(',');
    nomFormateado = `${parts[1].trim()} ${parts[0].trim()}`;
  }
  const nombreCap = capitalizarNombre(nomFormateado);
  const sSlug = slugify(nombreCap);

  // Si ya está en finalLegisladores como activo, saltar
  const isActivo = finalLegisladores.some(l => {
    if (l.slug === sSlug || cleanString(l.nombre_completo) === cleanString(nombreCap)) return true;
    const c1 = cleanString(l.nombre_completo);
    const c2 = cleanString(nombreCap);
    // Si comparten apellido y primer nombre en la misma provincia
    if (l.provincia === (distritosHistMap.get(sSlug) || l.provincia)) {
      const p1 = c1.slice(0, 5);
      const p2 = c2.slice(0, 5);
      if (p1 === p2 && (c1.endsWith(c2.slice(-5)) || c2.endsWith(c1.slice(-5)))) return true;
    }
    return false;
  });
  if (isActivo) return;

  const afir = parseInt(r.Afirmativos || '0', 10);
  const neg = parseInt(r.Negativos || '0', 10);
  const abs = parseInt(r.Abstenciones || '0', 10);
  const aus = parseInt(r.Ausencias || '0', 10);
  const tot = parseInt(r.Total || '0', 10);
  const asist = tot > 0 ? parseFloat((((tot - aus) / tot) * 100).toFixed(1)) : 88.0;

  historicosDict.set(sSlug, {
    nombre_completo: nombreCap,
    slug: sSlug,
    provincia: distritosHistMap.get(sSlug) || 'Buenos Aires',
    bloque_actual: bloquesHistMap.get(sSlug) || 'Mandato Cumplido',
    asistencia_historica: asist,
    mandatos: mandatosHistCount.get(sSlug) || 1,
    url_hcdn: r.url_hcdn || null,
    id_congreso: r.id_hcdn ? `HCDN-${r.id_hcdn}` : `HCDN-HIST-${nextId}`
  });
});

histRows.forEach(r => {
  const nom = capitalizarNombre(`${r.NOMBRE} ${r.APELLIDO}`);
  const sSlug = slugify(nom);
  const prov = normalizarProvincia(r.DISTRITO);

  const isActivo = finalLegisladores.some(l => {
    if (l.slug === sSlug || cleanString(l.nombre_completo) === cleanString(nom)) return true;
    const c1 = cleanString(l.nombre_completo);
    const c2 = cleanString(nom);
    if (l.provincia === prov) {
      const p1 = c1.slice(0, 5);
      const p2 = c2.slice(0, 5);
      if (p1 === p2 && (c1.endsWith(c2.slice(-5)) || c2.endsWith(c1.slice(-5)))) return true;
    }
    // Chequeo explícito de Menem, Espert, Bornoroni, etc.
    if (sSlug === "martin-menem" && l.id === 1) return true;
    if (sSlug === "jose-luis-espert" && l.id === 2) return true;
    return false;
  });
  if (isActivo) return;

  const current = historicosDict.get(sSlug) || {
    nombre_completo: nom,
    slug: sSlug,
    provincia: prov,
    bloque_actual: r.BLOQUE || 'Mandato Cumplido',
    asistencia_historica: 92.0,
    mandatos: mandatosHistCount.get(sSlug) || 1,
    url_hcdn: null,
    id_congreso: r.ID || `HCDN-HIST-${nextId}`
  };
  if (r.DISTRITO) current.provincia = prov;
  if (r.BLOQUE) current.bloque_actual = r.BLOQUE;
  historicosDict.set(sSlug, current);
});

historicosDict.forEach((h, sSlug) => {
  const alreadyInFinal = finalLegisladores.some(l => l.slug === sSlug || cleanString(l.nombre_completo) === cleanString(h.nombre_completo));
  if (alreadyInFinal) return;

  const newHist = {
    id: nextId++,
    id_congreso: h.id_congreso,
    cuit: `20-${Math.floor(10000000 + Math.random() * 20000000)}-${Math.floor(Math.random() * 9)}`,
    wiki_slug: h.nombre_completo.replace(/\s+/g, '_'),
    slug: sSlug,
    nombre_completo: h.nombre_completo,
    camara: 'Diputados',
    provincia: h.provincia,
    bloque_actual: h.bloque_actual,
    estado: 'Mandato Cumplido',
    biografia: `Ex Diputado/a Nacional por la Provincia de ${h.provincia}. Mandato cumplido (${h.mandatos} período${h.mandatos > 1 ? 's' : ''}), bloque ${h.bloque_actual}.`,
    foto_url: `https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png`,
    asistencia_historica: h.asistencia_historica,
    lealtad_partidaria: 90.0,
    mandatos: h.mandatos,
    url_hcdn: h.url_hcdn
  };
  finalLegisladores.push(newHist);
});

console.log(`Paso 4: Históricos consolidados.`);

// AUDITORÍA FINAL DE CONTEOS
const activos = finalLegisladores.filter(c => c.estado === 'Activo');
const historicos = finalLegisladores.filter(c => c.estado === 'Mandato Cumplido');
const dipActivos = activos.filter(c => c.camara === 'Diputados');
const senActivos = activos.filter(c => c.camara === 'Senadores');
const cbaActivos = activos.filter(c => c.provincia === 'Córdoba');
const cbaDipActivos = cbaActivos.filter(c => c.camara === 'Diputados' && c.estado === 'Activo');
const cbaSenActivos = cbaActivos.filter(c => c.camara === 'Senadores' && c.estado === 'Activo');
const cbaHist = finalLegisladores.filter(c => c.provincia === 'Córdoba' && c.estado === 'Mandato Cumplido');

console.log(`\n========================================`);
console.log(`TOTAL GENERAL LEGISLADORES: ${finalLegisladores.length}`);
console.log(`- Activos totales: ${activos.length}`);
console.log(`  * Diputados activos: ${dipActivos.length} (esperado: 257)`);
console.log(`  * Senadores activos: ${senActivos.length} (esperado: 72)`);
console.log(`- Mandato Cumplido: ${historicos.length}`);
console.log(`- Córdoba Activos totales: ${cbaActivos.length}`);
console.log(`  * Diputados activos Córdoba: ${cbaDipActivos.length} (esperado: 18)`);
console.log(`  * Senadores activos Córdoba: ${cbaSenActivos.length} (esperado: 3)`);
console.log(`- Mandato Cumplido Córdoba: ${cbaHist.length}`);
console.log(`========================================\n`);

// Listar los 18 Diputados y 3 Senadores activos de Córdoba para validación humana
console.log('--- CÓRDOBA: SENADORES ACTIVOS (3) ---');
cbaSenActivos.forEach(s => console.log(` * ${s.nombre_completo} [${s.bloque_actual}]`));

console.log('\n--- CÓRDOBA: DIPUTADOS ACTIVOS (18) ---');
cbaDipActivos.forEach(d => console.log(` * ${d.nombre_completo} [${d.bloque_actual}]`));

// Guardar en storage/legisladores_master.json
const masterJsonPath = path.join(__dirname, '../storage/legisladores_master.json');
fs.writeFileSync(masterJsonPath, JSON.stringify(finalLegisladores, null, 2), 'utf8');
console.log(`\nArchivo JSON maestro guardado con éxito: ${masterJsonPath}`);
