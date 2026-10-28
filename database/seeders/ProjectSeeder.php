<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            [
                'title' => 'Proyecto Sala T',
                'slug' => 'proyecto-sala-t',
                'category' => 'Diseño interior',
                'description' => 'Una sala contemporánea donde la materialidad y la luz natural crean un ambiente acogedor.',
                'cover_image' => 'img/Proyecto Sala T/1.webp',
            ],
            [
                'title' => 'Proyecto OPV',
                'slug' => 'proyecto-opv',
                'category' => 'Arquitectura comercial',
                'description' => 'Una sala de ventas con espacios abiertos y una distribución interior eficiente.',
                'cover_image' => 'img/Proyecto OPV/1.webp',
            ],
            [
                'title' => 'Departamento MR',
                'slug' => 'departamento-mr',
                'category' => 'Remodelación',
                'description' => 'Remodelación integral que conecta sala, comedor y cocina en un espacio continuo.',
                'cover_image' => 'img/Departamento MR/1.webp',
            ],
        ];

        foreach ($projects as $project) {
            Project::updateOrCreate(
                ['slug' => $project['slug']],
                $project
            );
        }
    }
}