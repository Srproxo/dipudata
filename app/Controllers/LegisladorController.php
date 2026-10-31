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

        return [
            'legislador'        => $leg,
            'url'               => LegisladorModel::urlPerfil($leg),
            'patrimonio'        => $patrimonio,
            'resumen'           => $this->model->resumenPatrimonial($patrimonio),
            'perfil'            => $this->model->perfilTematico($leg['id_recinto']),
            'perfil_compuesto'  => $this->model->perfilCompuesto((int)$leg['id'], $leg['id_recinto']),
            'agenda'              => $this->model->agendaProyectos((int)$leg['id']),
            'ultimos_proyectos'   => $this->model->ultimosProyectosAutor((int)$leg['id'], 5),
            'defensa_provincial'  => $this->model->calcularDefensaProvincial((int)$leg['id']),
            'despacho'            => $this->model->despachoCostos((int)$leg['id']),
            'media_camara_costo'  => $this->model->mediaCamaraCostoProyecto($leg['camara']),
            'votaciones'          => $this->model->votaciones($leg['id_recinto']),
        ];
    }

    public function listado(): array
    {
        return $this->model->all();
    }
}
