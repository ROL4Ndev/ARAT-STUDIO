# Revisión final contra el Informe Integral

Fuente leída íntegramente: `C:\Users\rolan\OneDrive\Desktop\Informe_Integral_ARAT_STUDIO_Laravel.docx`,
“Especificación del sistema web de ARAT STUDIO”, versión 1.0, 11/11/2026.
SHA-256: `A85D6D6793B701BEB3AE37E8C23CF176CE33B73BD172CF395D0F707970F43A65`.
Se contrastaron texto, tablas de CU/RN, diccionarios y PA01–PA14.
La organización del equipo y las referencias del documento no se interpretaron
como autorización para commits, push, cuentas reales o cambios fuera del proyecto.

**Resultado:** el recorrido funcional y los cuatro catálogos tienen comprobación
de código, HTTP y persistencia. El manejo del envío superior a 64 MiB sigue
**parcial** en el servidor PHP integrado. Producción y entrega SMTP real quedan
**pendientes de verificación**; no se presentan como terminadas.

## Evidencias y alcance de esta revisión

- Pruebas funcionales: `php artisan test --compact`; SQLite en memoria, con
  salvaguarda en `tests/TestCase.php`. Resultado definitivo en
  [VERIFICACION_ENTREGA.md](VERIFICACION_ENTREGA.md).
- Concurrencia: `php tests/Integration/MariaDbConcurrency.php --run`;
  **7 comprobaciones correctas**, procesos/conexiones independientes, base
  `arat_concurrency_20261112_022133`. No se probaron las carreras sobre desarrollo.
- Chrome real mediante Playwright local, servidor `http://127.0.0.1:8127`,
  conectado exclusivamente a aquella base aislada. Cuentas persistentes ficticias
  con contraseñas aleatorias, deshabilitadas al terminar.
- Los archivos de evidencia están ignorados en `storage/framework/testing/`:
  `workflow-browser-results.json`, `review-security-results.json`,
  `workflow-visual-results.json`, `workflow-profile-results.json`,
  `review-performance-results.json` y `workflow-upload-results.json`.
  No forman parte de los archivos nuevos publicables.
- “Cumplido” indica el resultado funcional y controles del código probado;
  no equivale a certificar producción, SMTP, accesibilidad completa o todos
  los servidores posibles.

## Cuatro CRUD: las cinco operaciones

Rutas reales: `/admin/categorias`, `/admin/proyectos`, `/admin/servicios`,
`/admin/tarifas`. Coordinación en `app/Http/Controllers/Admin/CatalogController.php`,
validación en `app/Http/Requests/Admin/CatalogRequest.php`, autorización en
`app/Policies/CatalogPolicy.php` y reglas en `app/Services/CatalogWorkflowService.php`.

| Catálogo | Crear / consultar / editar | Desactivar / reactivar | Resultado y evidencia |
|---|---|---|---|
| Categorías | HTTP probado, nombre/slug únicos, slug conservado, detalle con asociados y conteo | Motivo y versión; dependencia activa impide la baja | Cumplido. `AdminCatalogTest`, `CatalogPhaseTest` y `FinalReviewTest::test_each_catalog_completes_all_five_operations_without_decreasing_rows` |
| Proyectos | Ficha, categoría, portada con contenido y límite 5 MiB; borrador y detalle | Mantiene ID; al reactivar exige ficha, portada accesible y categoría activa | Cumplido. `AdminCatalogTest::test_project_upload_edit_history_and_search` y `FinalReviewTest::test_incomplete_project_cannot_reactivate_and_published_identifiers_cannot_change` |
| Servicios | Alta inactiva, detalle, edición, código inmutable y filtros | Activación inicial, baja y reactivación reales conservando ID y cantidad | Cumplido. `FinalReviewTest`, `CatalogPhaseTest::test_service_codes_are_immutable_and_imports_do_not_assume_equivalence` |
| Tarifas | Borrador editable; editar publicada crea versión, no sobrescribe precios | Confirma puntero esperado; grupo bloqueado; reactivación histórica sustituye la actual | Cumplido. `CatalogPhaseTest::test_published_tariff_edit_creates_version_and_empty_group_can_be_activated`, `FinalReviewTest::test_stale_tariff_family_pointer_conflicts_even_if_target_version_is_unchanged` y MariaDB |

La prueba común publica primero los borradores de servicio/tarifa, luego hace
una baja real y una reactivación. Comprueba que el conteo no disminuye y que una
petición DELETE devuelve 405. Chrome comprobó formularios y estados de los cuatro.

No hay rutas DELETE ni métodos de eliminación de negocio en controllers.
`BusinessModel` impide delete de modelos de negocio, y el historial se protege
contra cambios. `AuthenticatedSessionController::destroy` cierra una sesión:
no elimina usuarios. Los únicos borrados de archivos de los servicios limpian
cargas nuevas no confirmadas; la retirada de una portada confirmada es un
**traslado comprobado a privado**, con SHA-256 y ubicación conservados, y
restauración de ubicación si falla la transacción.

Las migraciones antiguas conservan sus `down()` con DROP; son mantenimiento
histórico de esquema, no acciones de la aplicación. No se ejecutaron, y el
rollback de las migraciones nuevas se bloquea para evitar pérdida de historial.

## Casos de uso obligatorios CU01–CU22

Abreviaturas: los servicios citados están en `app/Services/` y las pruebas
`*Test` en `tests/Feature/`.

| CU | Clasificación | Evidencia concreta |
|---|---|---|
| CU01 Portafolio | Cumplido | `PublicSite/ProjectController.php` y `HomeController.php` filtran proyecto/categoría activos, slug y 404. `HomePresentationTest`, `AdminCatalogTest` y Chrome. |
| CU02 Estimación | Cumplido | `QuotationCalculatorService::calculate` usa BigDecimal/HalfUp y versión vigente; nulo con motivo. `DecimalCalculatorTest` y `PublicServiceRequestTest`. |
| CU03 Registro | Cumplido | `StoreServiceRequest`, `ServiceRequestService::register` y confirmación ligada a sesión. `PublicServiceRequestTest`, dos procesos simultáneos en MariaDB y Chrome con PDF/reenvío. |
| CU04 Acceso interno | Cumplido | `LoginRequest`, `AuthenticatedSessionController`, `EnsureActiveUser` y `EnsureUserHasRole`. `AuthenticationAndProfileTest`, `CommercialWorkflowTest` y HTTP 403 tras deshabilitar sesión existente. |
| CU05 Perfil | Cumplido | `ProfileController` y Requests de perfil/contraseña; rol no editable. `AuthenticationAndProfileTest` y edición real de perfil en Chrome. |
| CU06 Categorías | Cumplido | Matriz CRUD anterior; dependencias, asociados inactivos, conteo, slug, auditoría y bloqueo de edición. |
| CU07 Proyectos | Cumplido | Matriz CRUD; `retireCover` conserva bytes y retira URL anterior. `FinalReviewTest::test_replaced_cover_is_private_and_failed_transaction_restores_its_public_location` y descarga histórica real. |
| CU08 Servicios | Cumplido | `OfferedService`, `CatalogRequest` y Home/formulario desde BD. Cuatro operativos y ocho borradores preservados; prueba de servicio inactivo en `PublicServiceRequestTest`. |
| CU09 Tarifas | Cumplido | `TariffGroup`/`TariffParam` y servicio de catálogo. Grupo+versión único, selección explícita, control de familia incluso vacía y puntero esperado. Pruebas de catálogo y carrera MariaDB. |
| CU10 Bandeja/asignación | Cumplido | `Commercial/ServiceRequestController::index` filtra código, servicio, fecha y estado; bandeja mínima sin contactos; Policies por registro. `CommercialWorkflowTest`, `FinalReviewTest::test_service_and_local_date_filters_apply_to_owned_queue_and_history` y toma concurrente. |
| CU11 Evaluación | Cumplido | `CommercialWorkflowService::transition` exige responsable y evaluación técnica; acciones explícitas y lock_version. `CommercialWorkflowTest` y Chrome. |
| CU12 Observación/corrección | Cumplido | `ClientRevisionService` conserva antes/después/archivos, autor/grant o staff con evidencia interna; vuelve a evaluación y avisa. `CorrectionRequest` prohíbe cambiar servicio/correo y contactos por staff. Pruebas `test_valid_correction_resumes_evaluation_and_queues_both_required_notices` y `test_staff_correction_requires_evidence_and_never_impersonates_the_client`; cliente en Chrome. |
| CU13 Rechazo/cancelación | Cumplido | Motivo, estados terminales y anulación de ofertas abiertas; aceptación bloquea cancelar. `CommercialWorkflowService`, `OfficialQuotationTest`, `ClientDossierTest` y rechazo 501 m² en Chrome. |
| CU14 Borrador | Cumplido | `IssueQuotationService::draft/edit` y `IssueQuotationRequest`; importe positivo, alcance/entregables/exclusiones/plazo y justificación. Borrador único con bloqueo; `OfficialQuotationTest` y MariaDB. |
| CU15 Emisión/PDF | Cumplido | `IssueQuotationService::prepare` persiste intención; `WorkProcessor::generate` renderiza fuera de transacción, revalida y guarda hash/archivo privado. `Quotation` protege contenido/fechas emitidas; `OfficialQuotationTest`, `FinalReviewTest`, MariaDB y PDF real descargado. |
| CU16 Nueva versión | Cumplido | Relación hasMany/currentQuotation; v2 borrador conserva v1, y solo emisión correcta sustituye. `test_failed_v2_keeps_v1_current_until_successful_retry` y carrera en ambos órdenes de MariaDB. |
| CU17 Notificación | Cumplido en transporte local | `NotificationRecord` y `NotificationAttempt` conservan cada intento, destino histórico, fecha, error y referencia. SMTP fuera de transacción. `test_notification_failure_and_retry_preserve_each_attempt_and_its_error` y `test_emission_notifies_snapshot_recipient_and_legacy_retry_returns_existing_result`. Log/array se marca simulated; SMTP real pendiente. |
| CU18 Acceso cliente | Cumplido | `ClientAccessService`: hash de token aleatorio, 15 minutos/uso único, límite de intentos y respuesta genérica; grant limitado, revocable y solo expediente propio. `ClientDossierTest` y HTTP de token consumido, enlace/grant vencidos y PDF ajeno: 403. |
| CU19 Decisión | Cumplido | `ClientResponseService` bloquea solicitud→cotización, exige versión actual/fecha/PDF/grant y registra evidencia. POST/CSRF, idempotencia/opuesta 409; staff no simula. `ClientDossierTest`, `OfficialQuotationTest` y límite antes/exacto de vencimiento. |
| CU20 Cierre | Cumplido | Fecha UTC, resultado y razón; aceptación cierra; cierre sin acuerdo bloqueado con borrador/oferta vigente. `FinalReviewTest::test_close_without_agreement_requires_no_live_or_preparing_offer_and_records_reason`; expediente terminal de solo consulta. |
| CU21 Historial/indicadores | Cumplido | Historial paginado y filtrado, alcance por asignación, pendientes/primera evaluación/emisiones; aceptación por **primera emisión** de solicitudes distintas. `CommercialOperationsTest` y `FinalReviewTest::test_first_emission_cohort_excludes_new_versions_of_old_requests`. |
| CU22 Reintentos | Cumplido | Intenciones durables, `commercial:recover`/`commercial:retry`, lease abandonado, PDF de misma versión y reintento autorizado de avisos. `CommercialOperationsTest`, `OfficialQuotationTest` y `FinalReviewTest`. Resultado ya completo se recupera, incluso para emisión heredada. |

## Reglas RN01–RN12

| Regla | Clasificación | Código y prueba principal |
|---|---|---|
| RN01 Bajas lógicas | Cumplido | Rutas sin DELETE, Policies y `BusinessModel`; matriz HTTP CRUD, conteos y dependencia. No cascade delete en migraciones del proyecto. |
| RN02 Historial inmutable | Cumplido | `AuditEvent` con triggers, `ServiceRequestHistory`, `RequestRevision` y protección de contenido en `Quotation`. Pruebas de actualización/borrado rechazados y PDFs previos idénticos. |
| RN03 Visibilidad | Cumplido | Queries públicas activas, FK/snapshots históricos conservados y servicio inactivo bloqueado. `HomePresentationTest` y `PublicServiceRequestTest`. |
| RN04 Decimal | Cumplido | `BigDecimal`/HalfUp, persistencia decimal; 80×100×1.35=10800 y 110→11880 sin alterar el anterior. `DecimalCalculatorTest` y `test_tariff_change_requires_conflict_and_confirmation_without_altering_history`. |
| RN05 Manual/nulo | Cumplido | Motivo explícito, nulo para manual/sin tarifa/501 m². Pruebas de calculador/registro y solicitudes reales aisladas. |
| RN06 Monto oficial | Cumplido | `IssueQuotationRequest` y `validateAmount`; positivo/PEN y justificación si difiere o no hubo estimación. `OfficialQuotationTest`. |
| RN07 Vigencia | Cumplido | `UtcDateTime`, 15 días exactos, `Quotation::isExpired` y comprobación en respuesta sin scheduler. Límite antes, exacto y después cubierto con reloj de pruebas. |
| RN08 Responsabilidad | Cumplido | Policies y grant verificado por recurso; revalidación contra BD de revocación. `CommercialWorkflowTest`, `ClientDossierTest` y `FinalReviewTest`. |
| RN09 Invariantes | Cumplido | Locks de familia/solicitud, índices de versión y punteros propios; solo un borrador/preparación y una emisión vigente. MariaDB con conflictos y resultado compatible. |
| RN10 Archivos | Cumplido en aplicación; límite total parcial | Validación de contenido y tamaño, copias/traslados privados y descargas autorizadas. SHA-256 PDF y portadas; casos de disco/BD fallidos. El runtime de >64 MiB se detalla abajo. |
| RN11 Duplicados | Cumplido funcional | submission_token+hash ligado a sesión para alta; emisión usa identidad de cotización e intención única `pdf:{id}` como clave de idempotencia. No crea versión al reintentar. Pruebas HTTP y dos procesos MariaDB. |
| RN12 Cierre | Cumplido | `CommercialWorkflowService` y `ClientResponseService`; anulación, bloqueo de aceptación y fecha/motivo. `FinalReviewTest` y carrera de cancelación/render. |

## Aceptación PA01–PA14

| PA | Clasificación | Evidencia ejecutada y resultado |
|---|---|---|
| PA01 CRUD×4 | Cumplido | Prueba HTTP de las cinco operaciones, conteo sin disminuir, mismo ID y Chrome. |
| PA02 Dependencias | Cumplido | Categoría con activo denegada, proyecto conserva estado; servicio inactivo no registra. |
| PA03 Histórico | Cumplido | 10800→11880 al cambiar tarifa; solicitud anterior permanece 10800. |
| PA04 Manual/límites | Cumplido | 501 produce nulo; servicio manual registra sin área/LOD. |
| PA05 Doble envío | Cumplido | HTTP con token/sesión y contenido; carreras del servicio del POST con dos procesos MariaDB: mismo ID o un 409 por contenido diferente. No se presenta el servidor PHP monohilo como prueba de concurrencia SQL. |
| PA06 Corrección | Cumplido | Nueva revisión, autor, valores/archivos anteriores, aviso y retorno a evaluación. |
| PA07 Acceso | Cumplido | 403 por rol/registro ajeno, grant/token vencido y sesión deshabilitada; 419 sin CSRF real. |
| PA08 PDF fallido | Cumplido | Misma versión, error técnico, reintento y solo un PDF emitido; v1 permanece vigente mientras falla v2. |
| PA09 Versiones | Cumplido | Borrador v2 conserva v1; emisión correcta sustituye y responder versión vieja da 409. |
| PA10 Carrera | Cumplido | Ambas intercalaciones medidas con procesos/conexiones independientes y render sin transacción. |
| PA11 Vencimiento | Cumplido | Acepta un microsegundo antes; exacto denegado sin scheduler. |
| PA12 Correo | Cumplido localmente | Fallo controlado conserva datos; dos intentos quedan registrados y no aparece otra cotización; simulated explícito. Entrega SMTP no ensayada. |
| PA13 Cierre | Cumplido | Cancelación anula pendientes, aceptación cierra y no permite edición/cancelación incompatible; cierre sin acuerdo conserva motivo. |
| PA14 Archivo/datos | Cumplido en validación/privacidad; parcial runtime | MIME/tamaño/copia/traslado/rollback y permisos probados; 49 MiB permitido y 51 MiB rechazado. Más de 64 MiB no obtiene el 413 esperado en PHP integrado. |

## Requisitos transversales y diferencias de implementación

| Requisito del informe | Clasificación | Evidencia / límite |
|---|---|---|
| Respaldo, restauración y migración sin perder datos | Cumplido | Nuevo respaldo `storage/app/private/backups/20261112_020135`; 326 archivos verificados y 27 tablas restauradas. Ensayo definitivo en `arat_review_20261112_022538`: trece tablas de negocio conservan todas sus columnas originales y filas. |
| IDs, códigos, precios, fechas visibles y PDF de cotizaciones v1 | Cumplido | Comparación con respaldo restaurado; migración previa documenta compatibilidad de vencimiento al fin del día de Lima. Esta revisión solo añade metadatos, no cambia importes, códigos, fechas ni rutas históricas. |
| Concurrencia MariaDB | Cumplido | Siete comprobaciones; SQLite no se usa como evidencia de bloqueo de MariaDB. |
| Listados de 15 en <2 s, al menos 1.000 solicitudes | Cumplido en entorno local | Cinco respuestas HTTP 200 con 15 filas: 224.12, 245.76, 186.23, 106.38 y 104.50 ms. Base aislada, nunca desarrollo. Medición de respuesta HTML, no descarga de CDN ni carga masiva. |
| Índices de consulta | Cumplido | Migración `2026_11_12_040000_complete_review_integrity.php` agrega índices de estado/asignación/fecha, estado/vencimiento, servicio/orden y proyecto/categoría. |
| Renderer/SMTP sin locks | Cumplido | Intención confirmada antes de generar; asserts de transacción cero y aceptación por otra conexión mientras render espera. El intento de correo se registra en transacción corta, y se transmite después. |
| Jobs, scheduler y recuperación | Cumplido | `ProcessWorkIntent`, `RecoverCommercialWork`, `RetryCommercialWork` y `routes/console.php`; prueba de lease y ejecución real de worker/recover. README incluye comandos exactos. |
| Español, identidad, tipografías y público/interno | Cumplido en Chrome | Blade/Bootstrap/Kodchasan/Arial conservados; perfiles y Home con DB. Desktop 1440; móvil 390 y expediente 320/390; foco, Tab, Escape y menú sin JS. Axe sin infracciones detectadas en combinaciones auditadas. |
| Confirmaciones y consecuencias | Cumplido en UI existente | Formularios muestran efecto de desactivar, cancelar y preparar; acciones específicas con motivo. Cliente consulta alcance/versión/PDF y confirma por POST. |
| Diccionario/arquitectura propuesta | Cumplido funcional con equivalencias | Se reutilizan `CatalogController/CatalogPolicy` para cuatro entidades; `project_cover_histories` y `request_files` representan medios/adjuntos. `status_reason` y `closure_outcome` conservan la semántica de los nombres propuestos. El booleano heredado de tarifa se deriva del status; no es fuente independiente del cálculo. |
| Convenciones HTTP propuestas | Cumplido funcional con compatibilidad | Se conservan rutas Blade existentes (`/create`, `/edit` y POST de estado) y redirecciones HTML. Los códigos/versión/lock/grant/CSRF más la intención única implementan los controles; no se añadió una API nueva solo para replicar nombres/alias del documento. |
| Error 413 del servidor de despliegue | Parcial | En PHP integrado 8.5.12 con 50M/64M, 65 MiB vuelve a responder advertencia de arranque y HTTP 200 antes de Laravel; no registra ni confirma. El manejador 413 aislado sí pasa. No se cambió XAMPP ni se declara resuelto. |
| SMTP real / recepción del destinatario | Pendiente de verificación externa | Solo log/array y fallo simulado; sent no equivale a entrega y no se garantiza SMTP exactamente una vez. |
| Producción, proxy, otros navegadores/dispositivos | Pendiente de verificación externa | No se dispone de despliegue productivo ni ensayo en Safari/Firefox/dispositivos físicos. No es un módulo nuevo pendiente de programar. |
| P1: exportaciones/filtros avanzados/mejoras visuales | Pendiente, fuera de esta revisión | Se conserva el alcance del informe; no se añaden pagos, obras, contabilidad, nuevos roles ni quinto CRUD. |
| Git / archivos locales | Cumplido | `git diff --check` y revisión de archivos nuevos; credenciales, .env, respaldos, adjuntos, PDFs, logs y herramientas/capturas temporales ignorados. No se ejecutaron git add, commit ni push. |

Los nombres de clases/tablas y las rutas propuestas en el informe se contrastan
por su responsabilidad y garantía funcional, no se sustituyen mecánicamente si
la estructura existente ya cubre la regla. Las diferencias se muestran arriba
para que el equipo pueda revisarlas; no se oculta el uso de redirecciones HTML
ni se promete un contrato API que el proyecto no publica.

## Pendientes reales

1. Configurar y probar el límite de petición del servidor/proxy del despliegue,
   logrando rechazo controlado antes de PHP. El 413 para >64 MiB **no está resuelto**
   en el servidor integrado revisado.
2. Si se requiere entrega real, configurar y ensayar SMTP con intervención del
   operador; esta revisión no usa credenciales ni correos reales.
3. Validación en producción y navegadores/dispositivos adicionales, cuando estén
   disponibles. Las mejoras P1 no bloquean los casos de uso comprobados.

No se presenta el sistema como completamente validado para producción mientras
persistan estos límites. Los datos de desarrollo se conservan y los ensayos de
carga/concurrencia se mantienen en bases aisladas.
