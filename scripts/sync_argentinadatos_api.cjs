const fs = require('fs');
const path = require('path');
const https = require('https');

function fetchJson(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { headers: { 'User-Agent': 'DipuData-Sync/1.0' } }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          resolve(JSON.parse(data));
        } catch (e) {
          reject(e);
        }
      });
    }).on('error', reject);
  });
}

function norm(s) {
  return (s || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]/g, '');
}

async function main() {
  console.log('=== SINCRONIZANDO DATOS CON ARGENTINADATOS API ===');

  const masterPath = path.join(__dirname, '../storage/legisladores_master.json');
  const srcMasterPath = path.join(__dirname, '../src/data/legisladoresMaster.json');

  const master = JSON.parse(fs.readFileSync(masterPath, 'utf8'));
  console.log(`Padrón maestro local: ${master.length} legisladores`);

  console.log('1. Descargando catálogo de Diputados...');
  const dipRes = await fetchJson('https://diputados.argentinadatos.com/api/members');
  const diputadosApi = dipRes.members || [];
  console.log(`   Diputados recibidos: ${diputadosApi.length}`);

  console.log('2. Descargando catálogo de Senadores...');
  const senRes = await fetchJson('https://senadores.argentinadatos.com/api/members');
  const senadoresApi = senRes.members || [];
  console.log(`   Senadores recibidos: ${senadoresApi.length}`);

  // Mapas de indexación rápida para búsqueda inteligente
  const dipByName = new Map();
  const dipById = new Map();
  diputadosApi.forEach(d => {
    if (d.id) dipById.set(d.id.toUpperCase(), d);
    const k1 = norm(`${d.nombre} ${d.apellido}`);
    const k2 = norm(`${d.apellido} ${d.nombre}`);
    const k3 = norm(d.nombreCompleto);
    dipByName.set(k1, d);
    dipByName.set(k2, d);
    dipByName.set(k3, d);
  });

  const senByName = new Map();
  senadoresApi.forEach(s => {
    const k1 = norm(`${s.nombreDePila || s.nombre} ${s.apellido}`);
    const k2 = norm(`${s.apellido} ${s.nombreDePila || s.nombre}`);
    const k3 = norm(s.nombreCompleto);
    senByName.set(k1, s);
    senByName.set(k2, s);
    senByName.set(k3, s);
  });

  let enriquecidosDip = 0;
  let enriquecidosSen = 0;

  master.forEach(leg => {
    const n = norm(leg.nombre_completo);
    const isDip = leg.camara === 'Diputados';

    if (isDip) {
      let match = dipByName.get(n);
      if (!match && leg.id_congreso) {
        match = dipById.get(leg.id_congreso.toUpperCase());
      }
      if (!match) {
        // Intento por partes de nombre
        const parts = leg.nombre_completo.split(/\s+/);
        const lastName = parts[parts.length - 1];
        match = diputadosApi.find(d => {
          const dk = norm(d.nombreCompleto);
          return dk.includes(norm(lastName)) && dk.includes(norm(parts[0]));
        });
      }

      if (match) {
        enriquecidosDip++;
        // Presentismo real
        if (match.estadisticas && match.estadisticas.presentismo > 0) {
          leg.asistencia_historica = Number(match.estadisticas.presentismo.toFixed(1));
        }
        // Estadísticas de votación detalladas
        if (match.estadisticas) {
          leg.votaciones_metricas = {
            total: match.estadisticas.totalVotaciones || 0,
            afirmativos: match.estadisticas.votosAfirmativos || 0,
            negativos: match.estadisticas.votosNegativos || 0,
            abstenciones: match.estadisticas.abstenciones || 0,
            ausencias: match.estadisticas.ausencias || 0
          };
          if (match.estadisticas.totalVotaciones > 0 && match.estadisticas.votosAfirmativos > 0) {
            const emitidos = match.estadisticas.votosAfirmativos + match.estadisticas.votosNegativos + match.estadisticas.abstenciones;
            if (emitidos > 0) {
              leg.lealtad_partidaria = Number(Math.min(99.0, Math.max(65.0, (match.estadisticas.votosAfirmativos / emitidos) * 100)).toFixed(1));
            }
          }
        }
        // Comisiones asignadas
        if (match.meta && Array.isArray(match.meta.comisiones) && match.meta.comisiones.length > 0) {
          leg.comisiones = match.meta.comisiones.map(c => ({
            id: c.id,
            nombre: c.nombre,
            cargo: c.cargo || 'Vocal'
          }));
        }
        // Viajes oficiales
        if (typeof match.viajesUltimos12Meses === 'number') {
          leg.viajes_ultimos_12_meses = match.viajesUltimos12Meses;
        }
        // Banca asignada
        if (match.banca) {
          leg.banca = match.banca;
        }
        // Foto oficial en alta resolución
        if (match.foto && !leg.foto_url.includes('Wikimedia')) {
          leg.foto_url = match.foto;
        }
      }
    } else {
      // Senador
      let match = senByName.get(n);
      if (!match) {
        const parts = leg.nombre_completo.split(/\s+/);
        const lastName = parts[parts.length - 1];
        match = senadoresApi.find(s => {
          const sk = norm(s.nombreCompleto);
          return sk.includes(norm(lastName)) && (sk.includes(norm(parts[0])) || norm(s.nombre).includes(norm(parts[0])));
        });
      }

      if (match) {
        enriquecidosSen++;
        // Presentismo real
        if (match.estadisticas && match.estadisticas.presentismo > 0) {
          leg.asistencia_historica = Number(match.estadisticas.presentismo.toFixed(1));
        }
        // Métricas de votación
        if (match.estadisticas) {
          leg.votaciones_metricas = {
            total: match.estadisticas.totalVotaciones || 0,
            afirmativos: match.estadisticas.votosAfirmativos || 0,
            negativos: match.estadisticas.votosNegativos || 0,
            abstenciones: match.estadisticas.abstenciones || 0,
            ausencias: match.estadisticas.ausencias || 0
          };
          if (match.estadisticas.totalVotaciones > 0 && match.estadisticas.votosAfirmativos > 0) {
            const emitidos = match.estadisticas.votosAfirmativos + match.estadisticas.votosNegativos + match.estadisticas.abstenciones;
            if (emitidos > 0) {
              leg.lealtad_partidaria = Number(Math.min(99.0, Math.max(65.0, (match.estadisticas.votosAfirmativos / emitidos) * 100)).toFixed(1));
            }
          }
        }
        // Viajes oficiales
        if (typeof match.viajesUltimos12Meses === 'number') {
          leg.viajes_ultimos_12_meses = match.viajesUltimos12Meses;
        }
        // Foto oficial
        if (match.foto && match.foto.startsWith('http')) {
          leg.foto_url = match.foto;
        }
        // Datos de transparencia: Personal y Dieta
        if (match.meta && match.meta.personal) {
          leg.personal_despacho = {
            agentes: match.meta.personal.agentes || 0,
            gasto_mensual: match.meta.personal.brutoMensualEstimado || 0,
            gasto_con_adicional: match.meta.personal.brutoMensualConAdicionalFuncion || 0
          };
        }
        if (match.meta && match.meta.dietaEstimada) {
          leg.dieta = {
            bruto_mensual: match.meta.dietaEstimada.brutoEstimado || 0,
            renuncia_aumento: !!(match.meta.dieta && match.meta.dieta.renunciaAlAumento),
            donacion: !!(match.meta.dieta && match.meta.dieta.donacion)
          };
        }
      }
    }
  });

  console.log(`✅ Diputados enriquecidos con datos reales: ${enriquecidosDip}`);
  console.log(`✅ Senadores enriquecidos con datos reales: ${enriquecidosSen}`);

  // Guardar en storage y src
  const jsonContent = JSON.stringify(master, null, 2);
  fs.writeFileSync(masterPath, jsonContent, 'utf8');
  fs.writeFileSync(srcMasterPath, jsonContent, 'utf8');
  console.log('✅ Archivos master JSON actualizados.');
}

main().catch(err => {
  console.error('Error durante la sincronización:', err);
  process.exit(1);
});
