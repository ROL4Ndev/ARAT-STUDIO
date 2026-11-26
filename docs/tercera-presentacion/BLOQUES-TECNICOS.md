# Cuatro bloques técnicos para la tercera presentación

Corte sobre `7436dd5`, rama local `main`, sin commit. Las líneas y SHA-256 se generan desde los archivos finales. Los bloques combinan interfaz, MVC y una regla real de validación/autorización.

## Rolando Sanchez · Integración, modelo y registro del entregable

**Interfaz:** Expediente comercial → Entregables → Preparar entregable. Explicar cotización, título, descripción y borrador.

**Regla a explicar:** Solo una cotización aceptada del mismo expediente permite crear el entregable. La relación se resuelve y valida en el servidor; el cierre comercial no cambia.

**Prueba y demostración:** Demostrar rechazo de una cotización ajena o sin aceptación, creación con archivo y preparación explícita/idempotente de los tres escenarios.

| Archivo | Método | Línea inicial |
| --- | --- | ---: |
| [app/Http/Controllers/Commercial/DeliverableController.php](../../app/Http/Controllers/Commercial/DeliverableController.php) | `create` | 17 |
| [app/Http/Controllers/Commercial/DeliverableController.php](../../app/Http/Controllers/Commercial/DeliverableController.php) | `store` | 26 |
| [app/Http/Requests/Commercial/DeliverableDraftRequest.php](../../app/Http/Requests/Commercial/DeliverableDraftRequest.php) | `authorize` | 11 |
| [app/Http/Requests/Commercial/DeliverableDraftRequest.php](../../app/Http/Requests/Commercial/DeliverableDraftRequest.php) | `rules` | 23 |
| [app/Services/DeliverableWorkflowService.php](../../app/Services/DeliverableWorkflowService.php) | `create` | 24 |
| [app/Models/ServiceRequest.php](../../app/Models/ServiceRequest.php) | `deliverables` | 11 |
| [app/Console/Commands/PrepareDeliverableDemo.php](../../app/Console/Commands/PrepareDeliverableDemo.php) | `handle` | 30 |
| [resources/views/deliverables/form.blade.php](../../resources/views/deliverables/form.blade.php) | Vista / estructura | 1 |
| [resources/views/deliverables/fields.blade.php](../../resources/views/deliverables/fields.blade.php) | Vista / estructura | 1 |
| [database/migrations/2026_11_25_220000_create_deliverables.php](../../database/migrations/2026_11_25_220000_create_deliverables.php) | Vista / estructura | 1 |

Cada integrante debe recorrer su pantalla, localizar el método indicado y explicar el caso correcto y uno rechazado; no limitarse a enumerar librerías.

## Christian Roque · Archivos privados, integridad y descarga autorizada

**Interfaz:** Formulario de archivo → descarga interna y del cliente → versiones conservadas.

**Regla a explicar:** PDF, PNG o JPEG de hasta 10 MiB; MIME y extensión deben corresponder al contenido. Una descarga exige autorización vigente y SHA-256 íntegro. Un fallo solo limpia archivos nuevos sin referencias.

**Prueba y demostración:** Mostrar descarga de v1 y v2; explicar las pruebas de archivo falso, extensión incorrecta, exceso de tamaño, archivo alterado y rollback de una carga.

| Archivo | Método | Línea inicial |
| --- | --- | ---: |
| [app/Services/DeliverableWorkflowService.php](../../app/Services/DeliverableWorkflowService.php) | `attachment` | 212 |
| [app/Services/DeliverableWorkflowService.php](../../app/Services/DeliverableWorkflowService.php) | `verifyFile` | 249 |
| [app/Services/DeliverableWorkflowService.php](../../app/Services/DeliverableWorkflowService.php) | `discardUnreferenced` | 239 |
| [app/Http/Controllers/DeliverableFileController.php](../../app/Http/Controllers/DeliverableFileController.php) | `__invoke` | 15 |
| [app/Policies/DeliverablePolicy.php](../../app/Policies/DeliverablePolicy.php) | `view` | 13 |
| [app/Policies/DeliverablePolicy.php](../../app/Policies/DeliverablePolicy.php) | `clientView` | 26 |
| [resources/views/deliverables/fields.blade.php](../../resources/views/deliverables/fields.blade.php) | Vista / estructura | 1 |
| [resources/views/deliverables/show.blade.php](../../resources/views/deliverables/show.blade.php) | Vista / estructura | 1 |
| [config/filesystems.php](../../config/filesystems.php) | Vista / estructura | 1 |

Cada integrante debe recorrer su pantalla, localizar el método indicado y explicar el caso correcto y uno rechazado; no limitarse a enumerar librerías.

## Alejandro Nolasco · Publicación, nuevas versiones y concurrencia

**Interfaz:** Borrador → Enviar a revisión → consultar observación → preparar y enviar versión 2.

**Regla a explicar:** El estado del entregable y sent_at de la versión son distintos: puede existir una nueva versión en borrador mientras el entregable sigue Observado. Bloqueos de fila, contador e índices únicos impiden duplicados.

**Prueba y demostración:** Explicar las seis carreras de MariaDB y mostrar historial v1 observada/v2 enviada. En particular, dos creaciones de versión compiten con el mismo contador y una conformidad impide otra versión.

| Archivo | Método | Línea inicial |
| --- | --- | ---: |
| [app/Services/DeliverableWorkflowService.php](../../app/Services/DeliverableWorkflowService.php) | `saveDraft` | 64 |
| [app/Services/DeliverableWorkflowService.php](../../app/Services/DeliverableWorkflowService.php) | `send` | 116 |
| [app/Services/DeliverableWorkflowService.php](../../app/Services/DeliverableWorkflowService.php) | `expected` | 188 |
| [app/Services/DeliverableWorkflowService.php](../../app/Services/DeliverableWorkflowService.php) | `locked` | 180 |
| [app/Http/Controllers/Commercial/DeliverableController.php](../../app/Http/Controllers/Commercial/DeliverableController.php) | `save` | 49 |
| [app/Http/Controllers/Commercial/DeliverableController.php](../../app/Http/Controllers/Commercial/DeliverableController.php) | `send` | 63 |
| [app/Models/DeliverableVersion.php](../../app/Models/DeliverableVersion.php) | `booted` | 10 |
| [resources/views/deliverables/show.blade.php](../../resources/views/deliverables/show.blade.php) | Vista / estructura | 1 |
| [tests/Integration/DeliverableConcurrency.php](../../tests/Integration/DeliverableConcurrency.php) | Vista / estructura | 1 |

Cada integrante debe recorrer su pantalla, localizar el método indicado y explicar el caso correcto y uno rechazado; no limitarse a enumerar librerías.

## Guyin Palacios · Revisión del cliente, acceso temporal y conformidad

**Interfaz:** Expediente del cliente → descargar → Observar → comentar → revisar versión 2 → Dar conformidad.

**Regla a explicar:** El ClientGrant debe seguir vigente y corresponder al expediente. Solo responde a la última versión enviada. La observación requiere comentario, la conformidad confirmación y una decisión incompatible devuelve 409.

**Prueba y demostración:** Mostrar error de comentario, observación, conformidad final y acceso denegado de otro expediente o una sesión interna. Explicar por qué no se reabren cancelación ni corrección comercial.

| Archivo | Método | Línea inicial |
| --- | --- | ---: |
| [app/Http/Requests/Client/DeliverableResponseRequest.php](../../app/Http/Requests/Client/DeliverableResponseRequest.php) | `rules` | 16 |
| [app/Http/Requests/Client/DeliverableResponseRequest.php](../../app/Http/Requests/Client/DeliverableResponseRequest.php) | `getRedirectUrl` | 24 |
| [app/Services/DeliverableWorkflowService.php](../../app/Services/DeliverableWorkflowService.php) | `respond` | 135 |
| [app/Http/Controllers/Client/DeliverableController.php](../../app/Http/Controllers/Client/DeliverableController.php) | `respond` | 12 |
| [app/Http/Controllers/Client/DossierController.php](../../app/Http/Controllers/Client/DossierController.php) | `show` | 23 |
| [app/Http/Middleware/EnsureClientGrant.php](../../app/Http/Middleware/EnsureClientGrant.php) | `handle` | 11 |
| [app/Services/ClientAccessService.php](../../app/Services/ClientAccessService.php) | `grant` | 81 |
| [resources/views/deliverables/client-list.blade.php](../../resources/views/deliverables/client-list.blade.php) | Vista / estructura | 1 |
| [resources/views/client/dossier.blade.php](../../resources/views/client/dossier.blade.php) | Vista / estructura | 1 |

Cada integrante debe recorrer su pantalla, localizar el método indicado y explicar el caso correcto y uno rechazado; no limitarse a enumerar librerías.

## Integración común

Los cuatro bloques usan el mismo servicio de flujo, las mismas Policies y las relaciones Eloquent; no representan cuatro subsistemas independientes. Los cuatro CRUD previos (proyectos, categorías, servicios y tarifas) permanecen en el menú del administrador. La aceptación comercial conserva el expediente cerrado y el proceso documental administra su propio estado.

Las líneas de este archivo se pueden regenerar con `php tools/maintenance/document-deliverables.php`; el mapa JSON conserva las huellas del corte.
