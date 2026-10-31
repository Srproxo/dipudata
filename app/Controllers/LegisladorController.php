<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\LegisladorModel;

class LegisladorController
{
    private LegisladorModel $model;

    public function __construct(?LegisladorModel $model = null)
    {
        $this->model = $model ?? new LegisladorModel();
    }

    /** Datos para la vista perfil.php, o null si no existe (404). */
    public function perfil(string $slug, ?string $camaraEsperada = null): ?array
    {
        $leg = $this->model->findBySlug($slug);
        if ($leg === null || ($camaraEsperada !== null && $leg['camara'] !== $camaraEsperada)) {
            return null;
        }
        $patrimonio = $this->model->patrimonio($leg['cuit']);
        $auditorModel = new \App\Models\AuditorModel($this->model->getPdo(), $this->model);
        $auditoria = $auditorModel->auditoriaCompleta((int)$leg['id']);

        require_once __DIR__ . '/../../api/Algorithms.php';
        $store = \LegisData\Config::getMemoryStore();
        $configId = null;
        foreach ($store['legisladores'] as $cid => $cleg) {
            if ($cleg['slug'] === $leg['slug'] || mb_strtolower($cleg['nombre_completo']) === mb_strtolower($leg['nombre'])) {
                $configId = $cid;
                break;
            }
        }
        $evolucion = \LegisData\Algorithms::calcularEvolucionPatrimonial($configId ?? (int)$leg['id'], $this->model->getPdo());

        return [
            'legislador'        => $leg,
            'url'               => LegisladorModel::urlPerfil($leg),
            'patrimonio'        => $patrimonio,
            'resumen'           => $this->model->resumenPatrimonial($patrimonio),
            'evolucion'         => $evolucion,
            'perfil'            => $this->model->perfilTematico($leg['id_recinto']),
            'perfil_compuesto'  => $this->model->perfilCompuesto((int)$leg['id'], $leg['id_recinto']),
            'agenda'              => $this->model->agendaProyectos((int)$leg['id']),
            'ultimos_proyectos'   => $this->model->ultimosProyectosAutor((int)$leg['id'], 5),
            'defensa_provincial'  => $this->model->calcularDefensaProvincial((int)$leg['id']),
            'despacho'            => $this->model->despachoCostos((int)$leg['id'], 2024, $leg['id_recinto'] ?? null, $leg['slug'] ?? null),
            'media_camara_costo'  => $this->model->mediaCamaraCostoProyecto($leg['camara']),
            'auditoria'           => $auditoria,
            'votaciones'          => $this->model->votaciones($leg['id_recinto']),
            'ficha_wiki'          => $this->model->fichaWikipedia($leg['slug'] ?? '', $leg),
        ];
    }

    public function listado(): array
    {
        return $this->model->all();
    }
}
