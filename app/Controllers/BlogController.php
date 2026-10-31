<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\InternalLinker;
use App\Models\BlogModel;
use App\Models\LegisladorModel;

/**
 * Hub & Spoke:
 *   /blog                         -> Hub principal (todas las categorías)
 *   /blog/{categoria}             -> Sub-hub de categoría
 *   /blog/{categoria}/{articulo}  -> Spoke, con enlaces internos inyectados hacia /diputado|senador/{slug}
 */
class BlogController
{
    private BlogModel $blog;
    private LegisladorModel $legisladores;

    public function __construct(?BlogModel $blog = null, ?LegisladorModel $legisladores = null)
    {
        $this->blog = $blog ?? new BlogModel();
        $this->legisladores = $legisladores ?? new LegisladorModel();
    }

    public function hub(): array
    {
        return ['categorias' => $this->blog->categorias(), 'articulos' => $this->blog->all()];
    }

    public function categoria(string $categoria): ?array
    {
        $articulos = $this->blog->byCategoria($categoria);
        return $articulos === [] ? null : ['categoria' => $categoria, 'articulos' => $articulos];
    }

    public function articulo(string $categoria, string $slug): ?array
    {
        $art = $this->blog->find($categoria, $slug);
        if ($art === null) {
            return null;
        }
        $linkeado = InternalLinker::inject($art['contenido_html'], $this->legisladores->all());

        $relacionados = array_values(array_filter(
            $this->blog->byCategoria($categoria),
            fn($a) => $a['articulo_slug'] !== $slug
        ));

        return [
            'articulo'     => $art,
            'html'         => $linkeado['html'],
            'mencionados'  => $linkeado['enlazados'],
            'relacionados' => array_slice($relacionados, 0, 3),
        ];
    }

    public static function nombreCategoria(string $slug): string
    {
        $map = [
            'patrimonio-y-transparencia' => 'Patrimonio y Transparencia',
            'anatomia-del-voto'          => 'Anatomía del Voto',
            'educacion-civica'           => 'Educación Cívica',
            'educacion-financiera'       => 'Educación Financiera',
            'auditoria-legislativa'      => 'Auditoría Legislativa',
        ];
        return $map[$slug] ?? ucfirst(str_replace('-', ' ', $slug));
    }
}
