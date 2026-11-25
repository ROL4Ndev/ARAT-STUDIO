<?php

// Datos de presentación transcritos del index.html original local de ARAT-STUDIO-main.
// No sustituye tarifas, servicios del formulario ni reglas del proceso comercial.
return [
    'email' => 'aratstudio.pe@gmail.com',
    'phone' => '+51 918 985 143',
    'phone_link' => '+51918985143',
    'whatsapp' => 'https://wa.me/51918985143',
    'team' => [
        [
            'name' => 'Angelo Trinidad', 'initials' => 'AT',
            'portrait' => 'media/studio/angelo-trinidad.webp',
            'qualification' => 'Arquitecto | Especialista BIM en Edificaciones',
            'role' => 'Director — ARAT STUDIO',
            'specialties' => ['Modelado BIM', 'Modelado 3D', 'Renderización fotorrealista', 'Dirección y desarrollo de proyectos arquitectónicos'],
            'whatsapp' => 'https://wa.me/51954180776',
        ],
        [
            'name' => 'Luz Nicole Arana Peláez', 'initials' => 'LA',
            'portrait' => 'media/studio/nicole-arana.webp',
            'qualification' => 'Bachiller en Arquitectura y Urbanismo',
            'role' => 'Directora — ARAT STUDIO',
            'specialties' => ['Producción de planos técnicos', 'Modelado 3D arquitectónico', 'Renderización y visualización arquitectónica', 'AutoCAD, Revit y SketchUp'],
            'whatsapp' => 'https://wa.me/51916487843',
        ],
    ],
    'service_visuals' => [
        'ARQUITECTURA' => [
            'image' => 'media/studio/servicio-arquitectura.webp',
            'alt' => 'Representación de la sala de ventas del Proyecto OPV',
            'project_slug' => 'proyecto-opv',
            'description' => 'Propuestas arquitectónicas para organizar espacios residenciales, comerciales e institucionales.',
        ],
        'INTERIORES' => [
            'image' => 'media/studio/servicio-interiores.webp',
            'alt' => 'Vista de la sala del Proyecto Sala T',
            'project_slug' => 'proyecto-sala-t',
            'description' => 'Planificación de espacios que conecta funcionalidad, materialidad y confort.',
        ],
        'BIM' => [
            'image' => 'media/studio/servicio-modelado.webp',
            'alt' => 'Modelo tridimensional de escritorio y armario del Proyecto AT',
            'illustration_slug' => 'proyecto-at',
            'description' => 'Modelado BIM para acompañar el desarrollo y la comunicación del proyecto. El alcance se define mediante evaluación comercial.',
            'caption' => 'Modelo 3D del portafolio, como referencia visual. La imagen no acredita una metodología BIM.',
        ],
        'VISUALIZACION' => [
            'image' => 'media/studio/servicio-visualizacion.webp',
            'alt' => 'Visualización del comedor y la cocina del Departamento MR',
            'project_slug' => 'departamento-mr',
            'description' => 'Imágenes y representaciones tridimensionales para visualizar el proyecto antes de su ejecución.',
        ],
    ],
    'services' => [
        'Servicios generales' => [
            ['name' => 'Diseño Interior', 'description' => 'Planificación y optimización de espacios residenciales, comerciales y corporativos, priorizando funcionalidad, estética y confort.'],
            ['name' => 'Diseño de Muebles en Melamina', 'description' => 'Desarrollo de mobiliario personalizado, funcional y adaptado a las necesidades del espacio.'],
            ['name' => 'Modelado 3D', 'description' => 'Representación tridimensional precisa para validar el diseño antes de su ejecución.'],
            ['name' => 'Renderizado y Recorrido Virtual', 'description' => 'Imágenes y animaciones realistas que permiten visualizar el proyecto antes de construirse.'],
        ],
        'Servicios para estudiantes' => [
            ['name' => 'Edición de Planimetría', 'description' => 'Corrección y optimización de planos arquitectónicos para entregas académicas claras y profesionales.'],
            ['name' => 'Modelado 3D Básico', 'description' => 'Creación de modelos conceptuales para comprender volúmenes y distribución espacial.'],
            ['name' => 'Renderizado y Recorrido Virtual Básico', 'description' => 'Visualizaciones simples para presentación de ideas y propuestas académicas.'],
            ['name' => 'Diagramación de Paneles', 'description' => 'Organización gráfica de proyectos para exposiciones y entregas ordenadas.'],
        ],
    ],
];
