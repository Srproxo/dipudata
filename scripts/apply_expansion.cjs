const fs = require('fs');
const path = require('path');
const { todosNuevos } = require('./build_expansion_data.cjs');

const baseDir = path.resolve(__dirname, '..');

// 1. Update src/data/seedData.ts
const seedDataPath = path.join(baseDir, 'src/data/seedData.ts');
let seedContent = fs.readFileSync(seedDataPath, 'utf8');

// Build TypeScript snippet for new legislators
const newLegisladoresTs = todosNuevos.map(leg => `  {
    id: ${leg.id},
    id_congreso: '${leg.id_congreso}',
    cuit: '${leg.cuit}',
    wiki_slug: '${leg.wiki_slug}',
    nombre_completo: '${leg.nombre_completo}',
    camara: '${leg.camara}',
    provincia: '${leg.provincia}',
    bloque_actual: '${leg.bloque_actual}',
    estado: '${leg.estado}',
    biografia:
      '${leg.biografia.replace(/'/g, "\\'")}',
    foto_url:
      '${leg.foto_url}',
    asistencia_historica: ${leg.asistencia_historica.toFixed(1)},
    lealtad_partidaria: ${leg.lealtad_partidaria.toFixed(1)},
    mandatos: ${leg.mandatos},
  },`).join('\n');

// Build TypeScript snippet for patrimonio
const newPatrimonioTs = todosNuevos.map(leg => {
  const p = leg.patrimonio;
  return `  ${leg.id}: { 2020: ${p[2020]}, 2021: ${p[2021]}, 2022: ${p[2022]}, 2023: ${p[2023]}, 2024: ${p[2024]}, 2025: ${p[2025]} },`;
}).join('\n');

// Build TypeScript snippet for votos
const newVotosTs = todosNuevos.map(leg => {
  const v = leg.votos.map(x => `'${x}'`).join(', ');
  return `  ${leg.id}: [${v}],`;
}).join('\n');

// Check if already applied
if (!seedContent.includes('Luis_Juez')) {
  // Insert new legislators before the end of LEGISLADORES_RAW_SEED
  const rawSeedMarker = `  {
    id: 20,
    id_congreso: 'HSN-2006',
    cuit: '20-16985214-2',
    wiki_slug: 'Carlos_Arce',
    nombre_completo: 'Carlos Omar Arce',
    camara: 'Senadores',
    provincia: 'Misiones',
    bloque_actual: 'Innovación Federal',
    estado: 'Activo',
    biografia:
      'Médico tocoginecólogo y político misionero. Senador Nacional por la provincia de Misiones en representación del Frente Renovador de la Concordia (Innovación Federal) y ex Vicegobernador provincial.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png',
    asistencia_historica: 94.3,
    lealtad_partidaria: 87.9,
    mandatos: 1,
  },
];`;

  if (!seedContent.includes(rawSeedMarker)) {
    throw new Error('Marker for rawSeedMarker not found in seedData.ts');
  }

  seedContent = seedContent.replace(
    rawSeedMarker,
    `  {
    id: 20,
    id_congreso: 'HSN-2006',
    cuit: '20-16985214-2',
    wiki_slug: 'Carlos_Arce',
    nombre_completo: 'Carlos Omar Arce',
    camara: 'Senadores',
    provincia: 'Misiones',
    bloque_actual: 'Innovación Federal',
    estado: 'Activo',
    biografia:
      'Médico tocoginecólogo y político misionero. Senador Nacional por la provincia de Misiones en representación del Frente Renovador de la Concordia (Innovación Federal) y ex Vicegobernador provincial.',
    foto_url:
      'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png',
    asistencia_historica: 94.3,
    lealtad_partidaria: 87.9,
    mandatos: 1,
  },
${newLegisladoresTs}
];`
  );

  // Insert new patrimonio
  const patMarker = `  20: { 2020: 21500000, 2021: 31200000, 2022: 55100000, 2023: 174000000, 2024: 206000000, 2025: 223800000 },
};`;
  if (!seedContent.includes(patMarker)) {
    throw new Error('Marker for patMarker not found in seedData.ts');
  }
  seedContent = seedContent.replace(
    patMarker,
    `  20: { 2020: 21500000, 2021: 31200000, 2022: 55100000, 2023: 174000000, 2024: 206000000, 2025: 223800000 },
${newPatrimonioTs}
};`
  );

  // Insert new votos
  const votosMarker = `  20: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion'],
};`;
  if (!seedContent.includes(votosMarker)) {
    throw new Error('Marker for votosMarker not found in seedData.ts');
  }
  seedContent = seedContent.replace(
    votosMarker,
    `  20: ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion'],
${newVotosTs}
};`
  );

  // Update REGIONES_PROVINCIAS_ARGENTINA and add TODAS_PROVINCIAS_ARGENTINA
  const regionesOldRegex = /export const REGIONES_PROVINCIAS_ARGENTINA:[\s\S]*?\];\s*$/;
  const regionesNew = `export const TODAS_PROVINCIAS_ARGENTINA = [
  'Buenos Aires',
  'Catamarca',
  'Chaco',
  'Chubut',
  'Ciudad Autónoma de Buenos Aires',
  'Córdoba',
  'Corrientes',
  'Entre Ríos',
  'Formosa',
  'Jujuy',
  'La Pampa',
  'La Rioja',
  'Mendoza',
  'Misiones',
  'Neuquén',
  'Río Negro',
  'Salta',
  'San Juan',
  'San Luis',
  'Santa Cruz',
  'Santa Fe',
  'Santiago del Estero',
  'Tierra del Fuego',
  'Tucumán',
] as const;

export type ProvinciaArgentina = (typeof TODAS_PROVINCIAS_ARGENTINA)[number];

export const REGIONES_PROVINCIAS_ARGENTINA: {
  region: string;
  codigo: string;
  provincias: { nombre: string; abreviatura: string }[];
}[] = [
  {
    region: 'AMBA y Centro',
    codigo: 'CENTRO',
    provincias: [
      { nombre: 'Ciudad Autónoma de Buenos Aires', abreviatura: 'CABA' },
      { nombre: 'Buenos Aires', abreviatura: 'PBA' },
      { nombre: 'Córdoba', abreviatura: 'CBA' },
      { nombre: 'Santa Fe', abreviatura: 'SFE' },
      { nombre: 'Entre Ríos', abreviatura: 'ER' },
    ],
  },
  {
    region: 'Noroeste Argentino (NOA)',
    codigo: 'NOA',
    provincias: [
      { nombre: 'Catamarca', abreviatura: 'CAT' },
      { nombre: 'Jujuy', abreviatura: 'JUJ' },
      { nombre: 'La Rioja', abreviatura: 'LR' },
      { nombre: 'Salta', abreviatura: 'SAL' },
      { nombre: 'Santiago del Estero', abreviatura: 'SDE' },
      { nombre: 'Tucumán', abreviatura: 'TUC' },
    ],
  },
  {
    region: 'Noreste Argentino (NEA)',
    codigo: 'NEA',
    provincias: [
      { nombre: 'Chaco', abreviatura: 'CHA' },
      { nombre: 'Corrientes', abreviatura: 'COR' },
      { nombre: 'Formosa', abreviatura: 'FOR' },
      { nombre: 'Misiones', abreviatura: 'MIS' },
    ],
  },
  {
    region: 'Cuyo y Pampa',
    codigo: 'CUYO',
    provincias: [
      { nombre: 'La Pampa', abreviatura: 'LPA' },
      { nombre: 'Mendoza', abreviatura: 'MZA' },
      { nombre: 'San Juan', abreviatura: 'SJN' },
      { nombre: 'San Luis', abreviatura: 'SLU' },
    ],
  },
  {
    region: 'Patagonia',
    codigo: 'SUR',
    provincias: [
      { nombre: 'Chubut', abreviatura: 'CHU' },
      { nombre: 'Neuquén', abreviatura: 'NQN' },
      { nombre: 'Río Negro', abreviatura: 'RN' },
      { nombre: 'Santa Cruz', abreviatura: 'SCZ' },
      { nombre: 'Tierra del Fuego', abreviatura: 'TDF' },
    ],
  },
];
`;

  seedContent = seedContent.replace(regionesOldRegex, regionesNew);

  fs.writeFileSync(seedDataPath, seedContent, 'utf8');
  console.log('src/data/seedData.ts actualizado con éxito.');
} else {
  console.log('src/data/seedData.ts ya contenía a Luis_Juez.');
}

// 2. Update src/pages/HomeView.tsx
const homeViewPath = path.join(baseDir, 'src/pages/HomeView.tsx');
let homeViewContent = fs.readFileSync(homeViewPath, 'utf8');

// Ensure TODAS_PROVINCIAS_ARGENTINA is imported
if (!homeViewContent.includes('TODAS_PROVINCIAS_ARGENTINA')) {
  homeViewContent = homeViewContent.replace(
    "import { LEYES_SEED, REGIONES_PROVINCIAS_ARGENTINA } from '../data/seedData';",
    "import { LEYES_SEED, REGIONES_PROVINCIAS_ARGENTINA, TODAS_PROVINCIAS_ARGENTINA } from '../data/seedData';"
  );
}

// Update provinciasDisponibles definition
const provDispOld = `  const provinciasDisponibles = useMemo(() => {
    const setProv = new Set(legisladores.map((l) => l.provincia));
    return ['Todas', ...Array.from(setProv).sort()];
  }, [legisladores]);`;

const provDispNew = `  const provinciasDisponibles = useMemo(() => {
    const setProv = new Set([
      ...TODAS_PROVINCIAS_ARGENTINA,
      ...legisladores.map((l) => l.provincia),
    ]);
    return ['Todas', ...Array.from(setProv).sort((a, b) => a.localeCompare('es'))];
  }, [legisladores]);`;

if (homeViewContent.includes(provDispOld)) {
  homeViewContent = homeViewContent.replace(provDispOld, provDispNew);
  fs.writeFileSync(homeViewPath, homeViewContent, 'utf8');
  console.log('src/pages/HomeView.tsx actualizado con éxito.');
} else {
  console.log('src/pages/HomeView.tsx ya estaba actualizado o el patrón cambió.');
}

// 3. Update api/config.php
const configPhpPath = path.join(baseDir, 'api/config.php');
let configPhpContent = fs.readFileSync(configPhpPath, 'utf8');

if (!configPhpContent.includes('Luis_Juez')) {
  // Build PHP arrays
  const phpRawLegs = todosNuevos.map(leg => {
    const bio = leg.biografia.replace(/'/g, "\\'");
    return `            [${leg.id}, '${leg.id_congreso}', '${leg.cuit}', '${leg.wiki_slug}', '${leg.nombre_completo}', '${leg.camara}', '${leg.provincia}', '${leg.bloque_actual}', '${leg.estado}', '${bio}', '${leg.foto_url}', ${leg.asistencia_historica.toFixed(2)}, ${leg.lealtad_partidaria.toFixed(2)}],`;
  }).join('\n');

  const phpPatrimonio = todosNuevos.map(leg => {
    const p = leg.patrimonio;
    return `            ${leg.id} => [2020 => ${p[2020]}.00, 2021 => ${p[2021]}.00, 2022 => ${p[2022]}.00, 2023 => ${p[2023]}.00, 2024 => ${p[2024]}.00, 2025 => ${p[2025]}.00],`;
  }).join('\n');

  const phpVotos = todosNuevos.map(leg => {
    const v = leg.votos.map(x => `'${x}'`).join(', ');
    return `            ${leg.id} => [${v}],`;
  }).join('\n');

  // Insert into $rawLegisladores
  const rawPhpMarker = `            [20, 'HSN-2006', '20-16985214-2', 'Carlos_Arce', 'Carlos Omar Arce', 'Senadores', 'Misiones', 'Innovación Federal', 'Activo', 'Médico tocoginecólogo y político misionero. Senador Nacional por la provincia de Misiones en representación del Frente Renovador de la Concordia (Innovación Federal) y ex Vicegobernador provincial.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png', 94.30, 87.90],
        ];`;

  if (!configPhpContent.includes(rawPhpMarker)) {
    throw new Error('rawPhpMarker not found in api/config.php');
  }

  configPhpContent = configPhpContent.replace(
    rawPhpMarker,
    `            [20, 'HSN-2006', '20-16985214-2', 'Carlos_Arce', 'Carlos Omar Arce', 'Senadores', 'Misiones', 'Innovación Federal', 'Activo', 'Médico tocoginecólogo y político misionero. Senador Nacional por la provincia de Misiones en representación del Frente Renovador de la Concordia (Innovación Federal) y ex Vicegobernador provincial.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png', 94.30, 87.90],
${phpRawLegs}
        ];`
  );

  // Insert into $patrimonioSeries
  const patPhpMarker = `            20 => [2020 => 21500000.00, 2021 => 31200000.00, 2022 => 55100000.00, 2023 => 174000000.00, 2024 => 206000000.00, 2025 => 223800000.00],
        ];`;

  if (!configPhpContent.includes(patPhpMarker)) {
    throw new Error('patPhpMarker not found in api/config.php');
  }

  configPhpContent = configPhpContent.replace(
    patPhpMarker,
    `            20 => [2020 => 21500000.00, 2021 => 31200000.00, 2022 => 55100000.00, 2023 => 174000000.00, 2024 => 206000000.00, 2025 => 223800000.00],
${phpPatrimonio}
        ];`
  );

  // Insert into $matrizVotos
  const votosPhpMarker = `            20 => ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion'],
        ];`;

  if (!configPhpContent.includes(votosPhpMarker)) {
    throw new Error('votosPhpMarker not found in api/config.php');
  }

  configPhpContent = configPhpContent.replace(
    votosPhpMarker,
    `            20 => ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion'],
${phpVotos}
        ];`
  );

  // Update mandatos map
  const mandatosOld = "'mandatos'             => [1=>1,2=>2,3=>1,4=>1,5=>3,6=>3,7=>1,8=>1,9=>1,10=>3,11=>2,12=>2,13=>1,14=>1,15=>4,16=>4,17=>6,18=>2,19=>2,20=>1][$id] ?? 1,";
  const mandatosEntries = [
    '1=>1,2=>2,3=>1,4=>1,5=>3,6=>3,7=>1,8=>1,9=>1,10=>3,11=>2,12=>2,13=>1,14=>1,15=>4,16=>4,17=>6,18=>2,19=>2,20=>1',
    ...todosNuevos.map(leg => `${leg.id}=>${leg.mandatos}`)
  ].join(',');

  const mandatosNew = `'mandatos'             => [${mandatosEntries}][$id] ?? 1,`;
  configPhpContent = configPhpContent.replace(mandatosOld, mandatosNew);

  fs.writeFileSync(configPhpPath, configPhpContent, 'utf8');
  console.log('api/config.php actualizado con éxito.');
} else {
  console.log('api/config.php ya contenía a Luis_Juez.');
}

// 4. Update database/seed.sql
const seedSqlPath = path.join(baseDir, 'database/seed.sql');
let seedSqlContent = fs.readFileSync(seedSqlPath, 'utf8');

if (!seedSqlContent.includes('Luis_Juez')) {
  const sqlLegInserts = todosNuevos.map(leg => {
    const bio = leg.biografia.replace(/'/g, "''");
    return `(${leg.id}, '${leg.id_congreso}', '${leg.cuit}', '${leg.wiki_slug}', '${leg.nombre_completo}', '${leg.camara}', '${leg.provincia}', '${leg.bloque_actual}', '${leg.estado}', '${bio}', '${leg.foto_url}', ${leg.asistencia_historica.toFixed(2)}, ${leg.lealtad_partidaria.toFixed(2)})`;
  }).join(',\n');

  const lastSqlLeg = `(20, 'HSN-2006', '20-16985214-2', 'Carlos_Arce', 'Carlos Omar Arce', 'Senadores', 'Misiones',
 'Innovación Federal', 'Activo',
 'Médico tocoginecólogo y político misionero. Senador Nacional por la provincia de Misiones en representación del Frente Renovador de la Concordia (Innovación Federal) y ex Vicegobernador provincial.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png',
 94.30, 87.90);`;

  if (seedSqlContent.includes(lastSqlLeg)) {
    seedSqlContent = seedSqlContent.replace(
      lastSqlLeg,
      `(20, 'HSN-2006', '20-16985214-2', 'Carlos_Arce', 'Carlos Omar Arce', 'Senadores', 'Misiones',
 'Innovación Federal', 'Activo',
 'Médico tocoginecólogo y político misionero. Senador Nacional por la provincia de Misiones en representación del Frente Renovador de la Concordia (Innovación Federal) y ex Vicegobernador provincial.',
 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png',
 94.30, 87.90),
${sqlLegInserts};`
    );
    fs.writeFileSync(seedSqlPath, seedSqlContent, 'utf8');
    console.log('database/seed.sql actualizado con éxito.');
  }
}
