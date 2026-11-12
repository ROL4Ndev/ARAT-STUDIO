<?php

namespace App\Services;

use App\Models\Category;
use App\Models\OfferedService;
use App\Models\Project;
use App\Models\TariffParam;

class CatalogRegistry
{
    public static function all(): array
    {
        return ['categories' => ['Categorías', Category::class, 'categorias'], 'projects' => ['Proyectos', Project::class, 'proyectos'], 'services' => ['Servicios', OfferedService::class, 'servicios'], 'tariffs' => ['Tarifas', TariffParam::class, 'tarifas']];
    }

    public static function get(string $kind): array
    {
        return self::all()[$kind] ?? abort(404);
    }
}
