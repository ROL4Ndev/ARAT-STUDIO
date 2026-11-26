<?php

// Genera referencias del código del corte final. No inicia Laravel ni abre una base.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$root = dirname(__DIR__, 2);
$groups = [
    ['Rolando Sanchez', 'Integración, modelo y registro del entregable',
        'Expediente comercial → Entregables → Preparar entregable. Explicar cotización, título, descripción y borrador.',
        'Solo una cotización aceptada del mismo expediente permite crear el entregable. La relación se resuelve y valida en el servidor; el cierre comercial no cambia.',
        [[App\Http\Controllers\Commercial\DeliverableController::class, 'create'], [App\Http\Controllers\Commercial\DeliverableController::class, 'store'],
            [App\Http\Requests\Commercial\DeliverableDraftRequest::class, 'authorize'], [App\Http\Requests\Commercial\DeliverableDraftRequest::class, 'rules'],
            [App\Services\DeliverableWorkflowService::class, 'create'], [App\Models\ServiceRequest::class, 'deliverables'],
            [App\Console\Commands\PrepareDeliverableDemo::class, 'handle']],
        ['resources/views/deliverables/form.blade.php', 'resources/views/deliverables/fields.blade.php', 'database/migrations/2026_11_25_220000_create_deliverables.php'],
        'Demostrar rechazo de una cotización ajena o sin aceptación, creación con archivo y preparación explícita/idempotente de los tres escenarios.'],
    ['Christian Roque', 'Archivos privados, integridad y descarga autorizada',
        'Formulario de archivo → descarga interna y del cliente → versiones conservadas.',
        'PDF, PNG o JPEG de hasta 10 MiB; MIME y extensión deben corresponder al contenido. Una descarga exige autorización vigente y SHA-256 íntegro. Un fallo solo limpia archivos nuevos sin referencias.',
        [[App\Services\DeliverableWorkflowService::class, 'attachment'], [App\Services\DeliverableWorkflowService::class, 'verifyFile'],
            [App\Services\DeliverableWorkflowService::class, 'discardUnreferenced'], [App\Http\Controllers\DeliverableFileController::class, '__invoke'],
            [App\Policies\DeliverablePolicy::class, 'view'], [App\Policies\DeliverablePolicy::class, 'clientView']],
        ['resources/views/deliverables/fields.blade.php', 'resources/views/deliverables/show.blade.php', 'config/filesystems.php'],
        'Mostrar descarga de v1 y v2; explicar las pruebas de archivo falso, extensión incorrecta, exceso de tamaño, archivo alterado y rollback de una carga.'],
    ['Alejandro Nolasco', 'Publicación, nuevas versiones y concurrencia',
        'Borrador → Enviar a revisión → consultar observación → preparar y enviar versión 2.',
        'El estado del entregable y sent_at de la versión son distintos: puede existir una nueva versión en borrador mientras el entregable sigue Observado. Bloqueos de fila, contador e índices únicos impiden duplicados.',
        [[App\Services\DeliverableWorkflowService::class, 'saveDraft'], [App\Services\DeliverableWorkflowService::class, 'send'],
            [App\Services\DeliverableWorkflowService::class, 'expected'], [App\Services\DeliverableWorkflowService::class, 'locked'],
            [App\Http\Controllers\Commercial\DeliverableController::class, 'save'], [App\Http\Controllers\Commercial\DeliverableController::class, 'send'],
            [App\Models\DeliverableVersion::class, 'booted']],
        ['resources/views/deliverables/show.blade.php', 'tests/Integration/DeliverableConcurrency.php'],
        'Explicar las seis carreras de MariaDB y mostrar historial v1 observada/v2 enviada. En particular, dos creaciones de versión compiten con el mismo contador y una conformidad impide otra versión.'],
    ['Guyin Palacios', 'Revisión del cliente, acceso temporal y conformidad',
        'Expediente del cliente → descargar → Observar → comentar → revisar versión 2 → Dar conformidad.',
        'El ClientGrant debe seguir vigente y corresponder al expediente. Solo responde a la última versión enviada. La observación requiere comentario, la conformidad confirmación y una decisión incompatible devuelve 409.',
        [[App\Http\Requests\Client\DeliverableResponseRequest::class, 'rules'], [App\Http\Requests\Client\DeliverableResponseRequest::class, 'getRedirectUrl'],
            [App\Services\DeliverableWorkflowService::class, 'respond'], [App\Http\Controllers\Client\DeliverableController::class, 'respond'],
            [App\Http\Controllers\Client\DossierController::class, 'show'], [App\Http\Middleware\EnsureClientGrant::class, 'handle'],
            [App\Services\ClientAccessService::class, 'grant']],
        ['resources/views/deliverables/client-list.blade.php', 'resources/views/client/dossier.blade.php'],
        'Mostrar error de comentario, observación, conformidad final y acceso denegado de otro expediente o una sesión interna. Explicar por qué no se reabren cancelación ni corrección comercial.'],
];
$map = [];
$markdown = "# Cuatro bloques técnicos para la tercera presentación\n\nCorte sobre `7436dd5`, rama local `main`, sin commit. Las líneas y SHA-256 se generan desde los archivos finales. Los bloques combinan interfaz, MVC y una regla real de validación/autorización.\n\n";
foreach ($groups as [$person, $title, $interface, $rule, $methods, $views, $demo]) {
    $markdown .= '## '.$person.' · '.$title."\n\n**Interfaz:** ".$interface."\n\n**Regla a explicar:** ".$rule."\n\n**Prueba y demostración:** ".$demo."\n\n| Archivo | Método | Línea inicial |\n| --- | --- | ---: |\n";
    foreach ($methods as [$class, $method]) {
        $reflection = new ReflectionMethod($class, $method);
        $file = str_replace('\\', '/', substr($reflection->getFileName(), strlen($root) + 1));
        $line = $reflection->getStartLine();
        $map[] = ['presenter' => $person, 'file' => $file, 'method' => $method, 'line' => $line, 'end_line' => $reflection->getEndLine(), 'sha256' => hash_file('sha256', $reflection->getFileName())];
        $markdown .= '| ['.$file.'](../../'.$file.') | `'.$method.'` | '.$line." |\n";
    }
    foreach ($views as $file) {
        $map[] = ['presenter' => $person, 'file' => $file, 'method' => null, 'line' => 1, 'sha256' => hash_file('sha256', $root.'/'.$file)];
        $markdown .= '| ['.$file.'](../../'.$file.') | Vista / estructura | 1 |'."\n";
    }
    $markdown .= "\nCada integrante debe recorrer su pantalla, localizar el método indicado y explicar el caso correcto y uno rechazado; no limitarse a enumerar librerías.\n\n";
}
$markdown .= "## Integración común\n\nLos cuatro bloques usan el mismo servicio de flujo, las mismas Policies y las relaciones Eloquent; no representan cuatro subsistemas independientes. Los cuatro CRUD previos (proyectos, categorías, servicios y tarifas) permanecen en el menú del administrador. La aceptación comercial conserva el expediente cerrado y el proceso documental administra su propio estado.\n\nLas líneas de este archivo se pueden regenerar con `php tools/maintenance/document-deliverables.php`; el mapa JSON conserva las huellas del corte.\n";
file_put_contents($root.'/docs/tercera-presentacion/BLOQUES-TECNICOS.md', $markdown);
file_put_contents($root.'/docs/tercera-presentacion/source-map.json', json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo count($map).' referencias de archivo y método generadas sin consultar datos.'.PHP_EOL;
