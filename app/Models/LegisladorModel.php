<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class LegisladorModel
{
    private ?PDO $pdo;
    private ?array $csvCache = null;
    private ?array $ejesCache = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Retorna todos los ejes temáticos activos desde la base de datos relacional (Fase 2)
     * con fallback automático a la matriz predeterminada de 11 ejes.
     * @return array<int, array{id:int, nombre_eje:string, descripcion:string}>
     */
    public function ejes(): array
    {
        if ($this->ejesCache !== null) {
            return $this->ejesCache;
        }

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query("SELECT id, nombre_eje, descripcion FROM ejes_tematicos WHERE activo = 1 ORDER BY id ASC");
                $rows = $stmt ? $stmt->fetchAll() : [];
                if (!empty($rows)) {
                    return $this->ejesCache = $rows;
                }
            } catch (\Throwable) {
            }
        }

        return $this->ejesCache = array_values(Database::getDefaultEjes());
    }

    /**
     * Lista plana de nombres de ejes temáticos activos.
     * @return list<string>
     */
    public function nombresEjes(): array
    {
        return array_column($this->ejes(), 'nombre_eje');
    }

    public function all(): array
    {
        if ($this->pdo) {
            return $this->pdo->query("SELECT * FROM legisladores_master WHERE estado = 'Activo' ORDER BY nombre")->fetchAll();
        }
        return array_values($this->csv()['legisladores']);
    }

    public function findBySlug(string $slug): ?array
    {
        if ($this->pdo) {
            $stmt = $this->pdo->prepare("SELECT * FROM legisladores_master WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            return $stmt->fetch() ?: null;
        }
        foreach ($this->csv()['legisladores'] as $leg) {
            if ($leg['slug'] === $slug) {
                return $leg;
            }
        }
        return null;
    }

    public function votaciones(string $idRecinto): array
    {
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT v.ley, COALESCE(e.nombre_eje, 'General') AS eje_tematico, v.eje_id, v.voto, v.fecha
                    FROM votaciones v
                    LEFT JOIN ejes_tematicos e ON v.eje_id = e.id
                    WHERE v.id_recinto = :id
                    ORDER BY v.fecha DESC
                ");
                $stmt->execute([':id' => $idRecinto]);
                return $stmt->fetchAll();
            } catch (\Throwable) {
                try {
                    $stmt = $this->pdo->prepare("SELECT ley, eje_tematico, voto, fecha FROM votaciones WHERE id_recinto = :id ORDER BY fecha DESC");
                    $stmt->execute([':id' => $idRecinto]);
                    return $stmt->fetchAll();
                } catch (\Throwable) {
                    return [];
                }
            }
        }
        $rows = $this->csv()['votaciones'][$idRecinto] ?? [];
        usort($rows, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
        return $rows;
    }

    public function patrimonio(string $cuit): array
    {
        if ($this->pdo) {
            $stmt = $this->pdo->prepare("
                SELECT anio, monto_declarado, ajuste_inflacion_ipc, variacion_real_porcentaje
                FROM patrimonio_ddjj_cuit WHERE cuit = :cuit ORDER BY anio ASC
            ");
            $stmt->execute([':cuit' => $cuit]);
            return $stmt->fetchAll();
        }
        return $this->csv()['ddjj'][$cuit] ?? [];
    }

    /**
     * Crecimiento patrimonial real acumulado: monto final deflactado por el IPC
     * de cada año posterior al inicial, contra el monto inicial.
     */
    public function resumenPatrimonial(array $serie): array
    {
        if (count($serie) < 2) {
            return ['crecimiento_nominal_pct' => 0.0, 'crecimiento_real_pct' => 0.0, 'inflacion_acumulada_pct' => 0.0];
        }
        $inicial = (float)$serie[0]['monto_declarado'];
        $final = (float)end($serie)['monto_declarado'];
        $factorIpc = 1.0;
        foreach (array_slice($serie, 1) as $p) {
            $factorIpc *= 1 + ((float)$p['ajuste_inflacion_ipc'] / 100);
        }
        $nominal = $inicial > 0 ? ($final / $inicial - 1) * 100 : 0.0;
        $real = $inicial > 0 ? (($final / $factorIpc) / $inicial - 1) * 100 : 0.0;

        return [
            'crecimiento_nominal_pct' => round($nominal, 2),
            'crecimiento_real_pct'    => round($real, 2),
            'inflacion_acumulada_pct' => round(($factorIpc - 1) * 100, 2),
        ];
    }

    /**
     * Promedio dinámico de votos positivos por eje temático:
     * Afirmativos / Emitidos * 100 (excluye Ausente) iterando sobre los ejes activos de la base de datos.
     * @return array<string, float>
     */
    public function perfilTematico(string $idRecinto): array
    {
        $nombres = $this->nombresEjes();
        $conteo = array_fill_keys($nombres, ['afirmativos' => 0, 'emitidos' => 0]);
        foreach ($this->votaciones($idRecinto) as $v) {
            $eje = $v['eje_tematico'] ?? null;
            // Normalizar alias histórico si vino de datos antiguos
            if ($eje === 'Educación Pública' && !isset($conteo[$eje]) && isset($conteo['Educación'])) {
                $eje = 'Educación';
            }
            if ($eje === null || !isset($conteo[$eje]) || ($v['voto'] ?? '') === 'Ausente') {
                continue;
            }
            $conteo[$eje]['emitidos']++;
            if (($v['voto'] ?? '') === 'Afirmativo') {
                $conteo[$eje]['afirmativos']++;
            }
        }
        $vector = [];
        foreach ($conteo as $eje => $c) {
            $vector[$eje] = $c['emitidos'] > 0 ? round($c['afirmativos'] / $c['emitidos'] * 100, 2) : 0.0;
        }
        return $vector;
    }

    /**
     * Obtiene los proyectos de ley presentados por un legislador (Fase 3 - Iniciativa Legislativa).
     * @return list<array{id?:int, expediente:string, titulo_sumario:string, fecha_presentacion:string, estado_tramite:string, eje_id:int, eje_tematico:string, rol:string}>
     */
    public function proyectosPorLegislador(int $legisladorId, ?string $rol = null): array
    {
        if ($this->pdo) {
            try {
                $sql = "
                    SELECT p.id, p.expediente, p.titulo_sumario, p.fecha_presentacion, p.estado_tramite,
                           p.eje_id, e.nombre_eje AS eje_tematico, pf.rol
                    FROM proyectos p
                    JOIN proyectos_firmas pf ON p.id = pf.proyecto_id
                    JOIN ejes_tematicos e ON p.eje_id = e.id
                    WHERE pf.legislador_id = :leg_id
                ";
                $params = [':leg_id' => $legisladorId];
                if ($rol !== null) {
                    $sql .= " AND pf.rol = :rol";
                    $params[':rol'] = $rol;
                }
                $sql .= " ORDER BY p.fecha_presentacion DESC";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
                if (!empty($rows)) {
                    return $rows;
                }
            } catch (\Throwable) {
            }
        }

        // Fallback a CSV / memoria
        $all = $this->csv()['proyectos_por_id'][$legisladorId] ?? [];
        if ($rol !== null) {
            $all = array_filter($all, fn($p) => $p['rol'] === $rol);
        }
        usort($all, fn($a, $b) => strcmp($b['fecha_presentacion'], $a['fecha_presentacion']));
        return array_values($all);
    }

    /**
     * Retorna los últimos proyectos presentados por el legislador como Autor principal.
     */
    public function ultimosProyectosAutor(int $legisladorId, int $limit = 5): array
    {
        $autores = $this->proyectosPorLegislador($legisladorId, 'Autor');
        return array_slice($autores, 0, $limit);
    }

    /**
     * Calcula la concentración de agenda de un legislador según sus proyectos presentados (Fase 3).
     * Regla matemática estricta:
     * - Rol 'Autor': peso 2.0
     * - Rol 'Co-autor': peso 1.0
     * Concentración por eje = (Puntos en el Eje / Puntos Totales) * 100
     *
     * @return array{
     *     puntaje_proyectos: array<string, float>,
     *     top3_ejes: list<array{eje:string, porcentaje:float, proyectos:int, puntos:float}>,
     *     total_proyectos: int,
     *     total_autor: int,
     *     total_coautor: int,
     *     total_puntos: float
     * }
     */
    public function agendaProyectos(int $legisladorId): array
    {
        $nombres = $this->nombresEjes();
        $proyectos = $this->proyectosPorLegislador($legisladorId);

        $puntosPorEje = array_fill_keys($nombres, 0.0);
        $conteoPorEje = array_fill_keys($nombres, 0);
        $totalPuntos = 0.0;
        $totalAutor = 0;
        $totalCoautor = 0;

        foreach ($proyectos as $p) {
            $eje = $p['eje_tematico'] ?? '';
            if (!isset($puntosPorEje[$eje])) {
                continue;
            }
            $peso = ($p['rol'] === 'Autor') ? 2.0 : 1.0;
            $puntosPorEje[$eje] += $peso;
            $conteoPorEje[$eje]++;
            $totalPuntos += $peso;
            if ($p['rol'] === 'Autor') {
                $totalAutor++;
            } else {
                $totalCoautor++;
            }
        }

        $concentracion = [];
        $rankingTreemap = [];
        foreach ($nombres as $eje) {
            $pct = $totalPuntos > 0.0 ? round(($puntosPorEje[$eje] / $totalPuntos) * 100, 2) : 0.0;
            $concentracion[$eje] = $pct;
            if ($conteoPorEje[$eje] > 0) {
                $rankingTreemap[] = [
                    'eje'        => $eje,
                    'porcentaje' => $pct,
                    'proyectos'  => $conteoPorEje[$eje],
                    'puntos'     => $puntosPorEje[$eje],
                ];
            }
        }

        usort($rankingTreemap, fn($a, $b) => $b['puntos'] <=> $a['puntos']);

        return [
            'puntaje_proyectos' => $concentracion,
            'top3_ejes'         => array_slice($rankingTreemap, 0, 3),
            'total_proyectos'   => count($proyectos),
            'total_autor'       => $totalAutor,
            'total_coautor'     => $totalCoautor,
            'total_puntos'      => $totalPuntos,
        ];
    }

    /**
     * Vector compuesto del Legislador (Fase 3 - Proactividad):
     * Fórmula: (Puntaje Votos * 0.7) + (Puntaje Proyectos * 0.3)
     * Si no posee proyectos registrados, se utiliza el 100% de la información de votos.
     * @return array<string, float>
     */
    public function perfilCompuesto(int $legisladorId, string $idRecinto): array
    {
        $votos = $this->perfilTematico($idRecinto);
        $agenda = $this->agendaProyectos($legisladorId);
        $proyectos = $agenda['puntaje_proyectos'];
        $tieneProyectos = ($agenda['total_proyectos'] > 0);

        $compuesto = [];
        foreach ($this->nombresEjes() as $eje) {
            $ptVotos = (float)($votos[$eje] ?? 0.0);
            $ptProy = (float)($proyectos[$eje] ?? 0.0);
            if ($tieneProyectos) {
                $compuesto[$eje] = round(($ptVotos * 0.7) + ($ptProy * 0.3), 2);
            } else {
                $compuesto[$eje] = $ptVotos;
            }
        }
        return $compuesto;
    }

    public function findById(int $id): ?array
    {
        if ($this->pdo) {
            $stmt = $this->pdo->prepare("SELECT * FROM legisladores_master WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch() ?: null;
        }
        foreach ($this->csv()['legisladores'] as $leg) {
            if ((int)$leg['id'] === $id) {
                return $leg;
            }
        }
        return null;
    }

    /**
     * Calcula el Índice de Defensa Provincial y detecta votos en conflicto por disciplina partidaria (Fase 4).
     *
     * Cruza el historial de votos del legislador con las leyes de impacto regional para su provincia.
     * Si el legislador vota en discrepancia con el interés provincial y a su vez vota igual que la mayoría
     * de su bloque, se cataloga formalmente como:
     * "Voto por Disciplina Partidaria en perjuicio provincial".
     *
     * @return array{
     *     legislador_id: int,
     *     nombre: string,
     *     provincia: string,
     *     bloque: string,
     *     indice_lealtad_partido: float,
     *     indice_defensa_provincial: float,
     *     brecha_tension: float,
     *     total_leyes_evaluadas: int,
     *     total_votos_defensa: int,
     *     total_votos_conflicto: int,
     *     votos_disciplina_perjuicio: int,
     *     alertas_conflicto: list<array{
     *         ley: string,
     *         fecha: string,
     *         voto_legislador: string,
     *         voto_esperado_regional: string,
     *         voto_mayoria_bloque: string,
     *         nivel_criticidad: int,
     *         disciplina_partidaria_perjudicial: bool,
     *         categoria_conflicto: string,
     *         descripcion_impacto: string,
     *         motivo: string
     *     }>,
     *     todos_los_votos: list<array<string, mixed>>
     * }
     */
    public function calcularDefensaProvincial(int $legisladorId): array
    {
        $leg = $this->findById($legisladorId);
        if (!$leg) {
            return [
                'legislador_id'              => $legisladorId,
                'nombre'                     => '',
                'provincia'                  => '',
                'bloque'                     => '',
                'indice_lealtad_partido'     => 0.0,
                'indice_defensa_provincial'  => 0.0,
                'brecha_tension'             => 0.0,
                'total_leyes_evaluadas'      => 0,
                'total_votos_defensa'        => 0,
                'total_votos_conflicto'      => 0,
                'votos_disciplina_perjuicio' => 0,
                'alertas_conflicto'          => [],
                'todos_los_votos'            => [],
            ];
        }

        $provincia = $leg['provincia'];
        $bloque = $leg['bloque'];
        $idRecinto = $leg['id_recinto'];
        $lealtadPartidaria = (float)$leg['indice_lealtad'];

        $leyesProvincia = [];

        if ($this->pdo) {
            try {
                $sql = "
                    SELECT 
                        lip.id AS impacto_id,
                        lip.ley,
                        lip.provincia,
                        lip.voto_esperado_regional,
                        lip.nivel_criticidad,
                        lip.descripcion_impacto,
                        v.voto AS voto_legislador,
                        COALESCE(v.fecha, '2024-01-01') AS fecha
                    FROM leyes_impacto_provincial lip
                    LEFT JOIN votaciones v ON (lip.ley = v.ley AND v.id_recinto = :recinto)
                    WHERE lip.provincia = :provincia
                    ORDER BY lip.nivel_criticidad DESC, lip.id ASC
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':recinto' => $idRecinto, ':provincia' => $provincia]);
                $leyesProvincia = $stmt->fetchAll();
            } catch (\Throwable) {
            }
        }

        // Fallback a CSV
        if (empty($leyesProvincia)) {
            $impactoRows = array_filter(
                $this->csv()['impacto_provincial'] ?? [],
                fn($r) => ($r['provincia'] ?? '') === $provincia
            );
            $votosLeg = $this->csv()['votaciones'][$idRecinto] ?? [];
            $votosPorLey = [];
            foreach ($votosLeg as $v) {
                $votosPorLey[$v['ley']] = $v;
            }

            foreach ($impactoRows as $imp) {
                $leyNombre = $imp['ley'];
                $v = $votosPorLey[$leyNombre] ?? null;
                $leyesProvincia[] = [
                    'impacto_id'             => (int)($imp['id'] ?? 1),
                    'ley'                    => $leyNombre,
                    'provincia'              => $provincia,
                    'voto_esperado_regional' => $imp['voto_esperado_regional'] ?? 'Afirmativo',
                    'nivel_criticidad'       => (int)($imp['nivel_criticidad'] ?? 2),
                    'descripcion_impacto'    => $imp['descripcion_impacto'] ?? '',
                    'voto_legislador'        => $v['voto'] ?? 'Ausente',
                    'fecha'                  => $v['fecha'] ?? '2024-09-01',
                ];
            }
        }

        // Determinar cómo votó la mayoría del bloque para cada ley
        $votosBloquePorLey = $this->obtenerMayoriaBloquePorLey($bloque);

        $puntosObtenidos = 0;
        $puntosPosibles = 0;
        $totalDefensa = 0;
        $totalConflicto = 0;
        $votosDisciplinaPerjuicio = 0;
        $alertasConflicto = [];
        $todosLosVotos = [];

        foreach ($leyesProvincia as $item) {
            $ley = $item['ley'];
            $votoLeg = $item['voto_legislador'] ?? 'Ausente';
            $votoEsperado = $item['voto_esperado_regional'];
            $criticidad = max(1, (int)($item['nivel_criticidad'] ?? 2));
            $puntosPosibles += $criticidad;

            $votoMayoria = $votosBloquePorLey[$ley] ?? $votoLeg;

            // Comparación de alineamiento con el interés regional
            $coincideRegional = ($votoLeg === $votoEsperado);

            if ($coincideRegional) {
                $totalDefensa++;
                $puntosObtenidos += $criticidad;
                $esConflicto = false;
                $disciplinaPerjudicial = false;
                $categoriaConflicto = 'Alineado con su Provincia';
                $motivo = "El legislador votó {$votoLeg} en defensa de los intereses de {$provincia}.";
            } else {
                $totalConflicto++;
                $esConflicto = true;
                // Verificar si votó igual que la mayoría de su bloque partidario
                $disciplinaPerjudicial = ($votoLeg === $votoMayoria);
                if ($disciplinaPerjudicial) {
                    $votosDisciplinaPerjuicio++;
                    $categoriaConflicto = 'Voto por Disciplina Partidaria en perjuicio provincial';
                    $motivo = "Votó {$votoLeg} siguiendo la disciplina del bloque {$bloque}, contradiciendo el interés regional de {$provincia} que requería voto {$votoEsperado}.";
                } else {
                    $categoriaConflicto = 'Discrepancia Regional Individual';
                    $motivo = "Votó {$votoLeg} en contra del interés regional esperado ({$votoEsperado}), apartándose también de su bloque ({$votoMayoria}).";
                }
            }

            $detalle = [
                'ley'                               => $ley,
                'fecha'                             => $item['fecha'] ?? '2024-01-01',
                'voto_legislador'                   => $votoLeg,
                'voto_esperado_regional'            => $votoEsperado,
                'voto_mayoria_bloque'               => $votoMayoria,
                'nivel_criticidad'                  => $criticidad,
                'coincide_regional'                 => $coincideRegional,
                'disciplina_partidaria_perjudicial' => $disciplinaPerjudicial,
                'categoria_conflicto'               => $categoriaConflicto,
                'descripcion_impacto'               => $item['descripcion_impacto'] ?? '',
                'motivo'                            => $motivo,
            ];

            $todosLosVotos[] = $detalle;
            if ($esConflicto) {
                $alertasConflicto[] = $detalle;
            }
        }

        $indiceDefensa = $puntosPosibles > 0 ? round(($puntosObtenidos / $puntosPosibles) * 100, 1) : 100.0;
        $brechaTension = round($lealtadPartidaria - $indiceDefensa, 1);

        return [
            'legislador_id'              => $legisladorId,
            'nombre'                     => $leg['nombre'],
            'provincia'                  => $provincia,
            'bloque'                     => $bloque,
            'indice_lealtad_partido'     => $lealtadPartidaria,
            'indice_defensa_provincial'  => $indiceDefensa,
            'brecha_tension'             => $brechaTension,
            'total_leyes_evaluadas'      => count($leyesProvincia),
            'total_votos_defensa'        => $totalDefensa,
            'total_votos_conflicto'      => $totalConflicto,
            'votos_disciplina_perjuicio' => $votosDisciplinaPerjuicio,
            'alertas_conflicto'          => $alertasConflicto,
            'todos_los_votos'            => $todosLosVotos,
        ];
    }

    /**
     * Determina el sentido del voto mayoritario de un bloque parlamentario para cada ley.
     * @return array<string, string> Mapa [nombre_ley => 'Afirmativo'|'Negativo']
     */
    public function obtenerMayoriaBloquePorLey(string $bloque): array
    {
        $salida = [];
        if ($this->pdo) {
            try {
                $sql = "
                    SELECT v.ley, v.voto, COUNT(*) AS total
                    FROM votaciones v
                    JOIN legisladores_master lm ON v.id_recinto = lm.id_recinto
                    WHERE lm.bloque = :bloque AND v.voto IN ('Afirmativo', 'Negativo')
                    GROUP BY v.ley, v.voto
                    ORDER BY v.ley, total DESC
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':bloque' => $bloque]);
                while ($row = $stmt->fetch()) {
                    $ley = $row['ley'];
                    if (!isset($salida[$ley])) {
                        $salida[$ley] = $row['voto'];
                    }
                }
                if (!empty($salida)) {
                    return $salida;
                }
            } catch (\Throwable) {
            }
        }

        // Fallback a CSV
        $conteo = [];
        $legisladores = $this->csv()['legisladores'] ?? [];
        $recintoABloque = [];
        foreach ($legisladores as $r => $l) {
            $recintoABloque[$r] = $l['bloque'] ?? '';
        }

        foreach ($this->csv()['votaciones'] ?? [] as $rId => $votos) {
            $bl = $recintoABloque[$rId] ?? '';
            if ($bl !== $bloque) {
                continue;
            }
            foreach ($votos as $v) {
                $ley = $v['ley'];
                $sentido = $v['voto'];
                if ($sentido === 'Afirmativo' || $sentido === 'Negativo') {
                    $conteo[$ley][$sentido] = ($conteo[$ley][$sentido] ?? 0) + 1;
                }
            }
        }

        foreach ($conteo as $ley => $votos) {
            arsort($votos);
            $salida[$ley] = array_key_first($votos);
        }

        return $salida;
    }

    public static function urlPerfil(array $leg): string
    {
        $prefijo = $leg['camara'] === 'Senadores' ? '/senador/' : '/diputado/';
        return $prefijo . $leg['slug'];
    }

    private function csv(): array
    {
        if ($this->csvCache !== null) {
            return $this->csvCache;
        }
        $base = dirname(__DIR__, 2) . '/storage/csv/';
        $bios = Database::bios();
        $legisladores = [];
        $votaciones = [];
        $ddjj = [];

        $ejesDefault = Database::getDefaultEjes();
        foreach (self::readCsv($base . 'votaciones_datacp.csv') as $r) {
            $id = $r['id_recinto'];
            if (!isset($legisladores[$id])) {
                $legisladores[$id] = [
                    'id'                 => count($legisladores) + 1,
                    'nombre'             => $r['nombre'],
                    'slug'               => Database::slugify($r['nombre']),
                    'camara'             => $r['camara'],
                    'provincia'          => $r['provincia'],
                    'bloque'             => $r['bloque'],
                    'estado'             => $r['estado'],
                    'id_recinto'         => $id,
                    'cuit'               => $r['cuit'],
                    'nombre_wiki'        => $r['nombre_wiki'],
                    'biografia_extracto' => $bios[$id] ?? null,
                    'foto_url'           => null,
                    'indice_lealtad'     => (float)$r['indice_lealtad'],
                    'asistencia'         => (float)$r['asistencia'],
                ];
            }
            $ejeId = isset($r['eje_id']) && is_numeric($r['eje_id']) ? (int)$r['eje_id'] : 1;
            $ejeNombre = $r['eje_tematico'] ?? ($ejesDefault[$ejeId]['nombre_eje'] ?? 'General');
            $votaciones[$id][] = [
                'ley'          => $r['ley'],
                'eje_id'       => $ejeId,
                'eje_tematico' => $ejeNombre,
                'voto'         => $r['voto'],
                'fecha'        => $r['fecha'],
            ];
        }

        foreach (self::readCsv($base . 'ddjj_anticorrupcion.csv') as $r) {
            $ddjj[$r['cuit']][] = [
                'anio'                      => (int)$r['anio'],
                'monto_declarado'           => (float)$r['monto_declarado'],
                'ajuste_inflacion_ipc'      => (float)$r['ajuste_inflacion_ipc'],
                'variacion_real_porcentaje' => (float)$r['variacion_real_porcentaje'],
            ];
        }

        $recintoToId = [];
        foreach ($legisladores as $recinto => $leg) {
            $recintoToId[$recinto] = (int)$leg['id'];
        }

        $proyectos_por_id = [];
        $proyCsvPath = $base . 'proyectos_iniciativa.csv';
        if (is_readable($proyCsvPath)) {
            $proyId = 1;
            foreach (self::readCsv($proyCsvPath) as $r) {
                $recinto = $r['legislador_id_recinto'] ?? '';
                $legId = $recintoToId[$recinto] ?? null;
                if ($legId === null) {
                    continue;
                }
                $ejeId = isset($r['eje_id']) && is_numeric($r['eje_id']) ? (int)$r['eje_id'] : 1;
                $ejeNombre = $ejesDefault[$ejeId]['nombre_eje'] ?? 'General';
                $proyectos_por_id[$legId][] = [
                    'id'                 => $proyId++,
                    'expediente'         => $r['expediente'] ?? '',
                    'titulo_sumario'     => $r['titulo_sumario'] ?? '',
                    'fecha_presentacion' => $r['fecha_presentacion'] ?? '',
                    'eje_id'             => $ejeId,
                    'eje_tematico'       => $ejeNombre,
                    'estado_tramite'     => $r['estado_tramite'] ?? 'En Trámite',
                    'rol'                => $r['rol'] ?? 'Autor',
                ];
            }
        }

        $impacto_provincial = [];
        $impactoPath = $base . 'leyes_impacto_provincial.csv';
        if (is_readable($impactoPath)) {
            $impacto_provincial = self::readCsv($impactoPath);
        }

        return $this->csvCache = compact('legisladores', 'votaciones', 'ddjj', 'proyectos_por_id', 'impacto_provincial');
    }

    public static function readCsv(string $path): array
    {
        if (!is_readable($path) || ($h = fopen($path, 'r')) === false) {
            return [];
        }
        $headers = fgetcsv($h, null, ',', '"', '');
        $rows = [];
        while (($row = fgetcsv($h, null, ',', '"', '')) !== false) {
            if ($headers && count($row) === count($headers)) {
                $rows[] = array_map('trim', array_combine($headers, $row));
            }
        }
        fclose($h);
        return $rows;
    }
}
