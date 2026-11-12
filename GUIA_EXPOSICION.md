# Guía de exposición — ARAT STUDIO

## Preparación previa

- MySQL activo en XAMPP; `php -v` debe mostrar PHP 8.5 y `php --ini` el INI
  correcto. Límites documentales: 50M/64M; no cambiar PHP de XAMPP.
- Aplicar `php artisan migrate` sin reiniciar datos y comprobar
  `php artisan migrate:status`. Conservar respaldo y credenciales existentes.
- Abrir `php artisan serve`, `php artisan queue:work --tries=1 --timeout=180`
  y `php artisan schedule:work` en terminales separadas.
- Disponer de admin y gestor. Si faltan, ejecutar `php artisan usuarios:crear-interno`
  de forma interactiva; cada persona introduce su contraseña, sin claves fijas.
- Revisar `/`, `/proyectos`, portadas, enlaces y descarga de un PDF autorizado.
  Para nuevas portadas, comprobar `php artisan storage:link`.
- Tener un PDF pequeño sin datos reales. En este equipo se usó
  `storage/framework/testing/verification-input.pdf` (artefacto local ignorado);
  en otro equipo puede imprimirse una hoja ficticia como PDF.
- Usar dos perfiles/ventanas independientes: sesión interna y cliente verificado.
  `MAIL_MAILER=log` simula mensajes; el operador consulta el enlace localmente
  sin proyectar logs, tokens ni credenciales. Configurar APP_URL con el servidor
  que realmente se utilizará también para worker y scheduler.

## Recorrido de 12 minutos

| Minutos | Demostración |
|---|---|
| 0–2 | Home, acceso y perfil breves. Como admin recorrer categorías, proyectos, servicios y tarifas; mostrar consulta/edición, estado con motivo y auditoría, sin borrar. |
| 2–4 | Cotizador 80 m² / LOD 300, solicitud con PDF pequeño y confirmación. Admin localiza y asigna al gestor con motivo. |
| 4–6 | Gestor inicia evaluación y observa con texto público. Cliente solicita acceso, verifica el enlace y corrige con otro PDF; la corrección devuelve automáticamente a evaluación y avisa al gestor, que aprueba con evaluación técnica. |
| 6–8 | Crear v1 con alcance, entregables, exclusiones, plazo e importe; preparar, procesar tarea y descargar PDF. Mostrar historial y fechas en Lima. |
| 8–10 | Crear borrador v2: v1 sigue emitida. Preparar y procesar v2: v1 sustituida, archivos conservados; cliente verifica, acepta y el expediente queda cerrado. |
| 10–12 | Gestor entra a CRUD por URL y recibe 403; explicar Policies, decimal, snapshots, transacciones y trabajo durable con los archivos siguientes. |

Si se necesita evitar la espera del scheduler, ejecutar `php artisan commercial:recover`
con worker activo. Con worker detenido, usar `php artisan commercial:recover --inline`.
No anunciar PDF listo mientras la pantalla siga preparando o failed.

## Tres escenarios reproducibles

1. **80 m² → propuesta aceptada.** Introducir manualmente nombre “Cliente ficticio
   de exposición”, correo que el operador pueda verificar (o `cliente@example.test`
   con transporte log local), teléfono `999000123`, Lima, servicio Modelado BIM,
   Vivienda, 80, LOD_300, descripción ficticia y consentimiento. Confirmar la
   estimación y adjuntar PDF; con tarifa vigente 100 y factor 1.350 debe dar
   PEN 10800.00. Asignar, observar, corregir y aprobar; v1: importe 10800.00,
   v2: 11800.00 con justificación de alcance, entregables, exclusiones y plazo
   “20 días hábiles”. Dar motivo de versión, emitir y aceptar como cliente.
   Los 11800.00 son un importe oficial ficticio, distinto del ejemplo tarifario
   110 × 80 × 1.35 = 11880.00.
2. **501 m² → evaluación personalizada.** Abrir otro formulario y usar los mismos
   datos ficticios con área 501. La estimación debe ser nula con motivo, nunca
   cero; registrar, asignar e iniciar evaluación. Mostrar rechazo con motivo
   “El alcance requiere información técnica adicional”, o evaluar/aprobar y
   justificar un importe manual; no saltar la evaluación técnica.
3. **Gestor → CRUD = 403.** Con sesión de gestor, escribir `/admin/categorias`,
   `/admin/proyectos`, `/admin/servicios` y `/admin/tarifas`. Deben responder 403;
   `/gestion` funciona y solo muestra sus expedientes y la bandeja mínima.
   Un visitante recibe redirección al ingreso; ese caso no demuestra el 403
   de gestor. Otro gestor tampoco descarga el adjunto del expediente ajeno.

Introducir nombres de categoría/proyecto con un sufijo nuevo y una portada propia;
no editar datos históricos para montar el ensayo. Desactivar/reactivar con motivo.
Una categoría con proyecto activo debe bloquearse mostrando su dependencia.
Para una tarifa nueva, crear borrador y activarlo conscientemente: no alterar
la vigente de trabajo solo para obtener una captura. No hay borrados de negocio.

## Código que debemos explicar

- **Rutas y roles:** `routes/web.php` declara las cuatro rutas administrativas,
  gestión y acceso del cliente; `routes/api.php` declara el cálculo. El alias
  de `EnsureUserHasRole` y `EnsureActiveUser` se registra en `bootstrap/app.php`;
  `app/Policies/CatalogPolicy.php` y `ServiceRequestPolicy.php` autorizan cada recurso.
- **Validación CRUD:** `app/Http/Requests/Admin/CatalogRequest.php` valida según
  módulo, estado, motivo y lock_version; `app/Rules/ProjectImage.php` verifica
  el contenido de la portada. `app/Http/Controllers/Admin/CatalogController.php`
  entrega los datos validados a `app/Services/CatalogWorkflowService.php`.
- **Proyecto y categoría:** `app/Models/Project.php::projectCategory()` usa
  belongsTo y `app/Models/Category.php::projects()` usa hasMany por category_id.
  `app/Http/Controllers/PublicSite/HomeController.php` consulta proyectos activos
  con su categoría y servicios activos de BD; no construye proyectos ficticios.
- **Decimal e historia:** `app/Services/QuotationCalculatorService.php` calcula
  con BigDecimal/HalfUp y devuelve nulo con motivo cuando no corresponde estimar.
  `ServiceRequestService.php`, en esa carpeta, recalcula y guarda snapshots;
  `CatalogWorkflowService.php` publica versiones sin reescribir precios históricos.
- **Estados y duplicados:** `app/Services/CommercialWorkflowService.php` centraliza
  transiciones y asignaciones bajo bloqueo; `ClientRevisionService.php` conserva
  correcciones y archivos. `ServiceRequestService.php` compara token ligado a
  sesión y hash del contenido; `DomainLock.php` detecta cambios simultáneos.
- **Versiones y PDF privado:** `app/Services/IssueQuotationService.php` congela
  snapshots y persiste la intención durable; `WorkProcessor.php` genera fuera
  de transacción y comprueba otra vez estados. `QuotationPdfService.php` renderiza
  `resources/views/commercial/quotation-pdf.blade.php`; las descargas pasan por
  `app/Http/Controllers/Commercial/QuotationController.php` y `app/Policies/QuotationPolicy.php`.
- **Cliente y cierre:** `app/Services/ClientAccessService.php` gestiona hash,
  uso único, caducidad y grant revocable; `ClientResponseService.php` bloquea
  solicitud/cotización y comprueba versión, autorización y fecha. El controlador
  `app/Http/Controllers/Client/DossierController.php` solo muestra su expediente.
- **Durabilidad y auditoría:** `app/Jobs/ProcessWorkIntent.php` procesa intenciones;
  `app/Console/Commands/RecoverCommercialWork.php` recupera pendientes y leases.
  `app/Services/AuditService.php` sanea eventos, y la migración
  `database/migrations/2026_11_12_010000_extend_catalog_and_commercial_workflow.php`
  crea triggers append-only y migra cotizaciones v1 conservando sus identificadores.

## Preguntas probables

| Pregunta | Respuesta basada en el código |
|---|---|
| ¿Ocultar enlaces protege? | No: middleware, Form Requests y Policies deniegan URL directa y registros ajenos. |
| ¿Se manipula el importe desde el navegador? | El servidor recalcula; si cambió la estimación exige confirmación con 409. El importe oficial se establece tras evaluación y justifica diferencias. |
| ¿Cambiar tarifa cambia una solicitud anterior? | No: conserva snapshots y cada cotización sus datos congelados. La publicada se revisa creando versión. |
| ¿Qué hace un doble clic? | Igual token/contenido recupera la solicitud; contenido diferente devuelve 409. El borrador pendiente es único por expediente bajo bloqueo. |
| ¿Dos gestores toman la misma solicitud? | Solo uno; se probó con procesos independientes en MariaDB aislada, no solo SQLite. |
| ¿Qué ocurre si falla el PDF? | La misma versión queda preparando/failed; una intención durable permite reintentar. La v1 sigue vigente hasta completar v2. |
| ¿Y si aceptan v1 mientras se genera v2? | Se vuelve a comprobar bajo bloqueo: o se acepta v1 y v2 se anula, o v2 sustituye y responder v1 devuelve 409. Ambos órdenes se probaron. |
| ¿Si no corre scheduler se acepta una vencida? | No: la respuesta exige now < expires_at. Los nuevos plazos son 15 días exactos UTC presentados en Lima. |
| ¿El código SOL abre el expediente? | No: se necesita enlace de un uso al correo y grant temporal; solo muestra historial público del expediente propio. |
| ¿Es firma digital o correo entregado? | No es firma certificada. log significa simulación; sent tampoco garantiza entrega en bandeja ni SMTP exactamente una vez. |
| ¿Se borran proyectos o portadas? | Se desactivan registros; archivos históricos y versiones emitidas se conservan. |
| ¿La tasa cuenta versiones como clientes? | No: el denominador son solicitudes distintas cuya primera emisión pertenece al período elegido. |

## Capturas de cada integrante

Con URL visible y datos ficticios: Home; los cuatro catálogos y sus estados;
solicitud registrada; asignación/evaluación e historial; corrección y archivos
conservados; cotización v1/v2 y PDF; expediente aceptado/cerrado; 403 del gestor;
repositorio https://github.com/ROL4Ndev/arat-studios. No capturar enlaces con
tokens, contraseñas, .env ni logs. El repositorio remoto puede diferir del trabajo
local: esta entrega no hace git add, commit ni push.

## Límites que deben decirse

Correo local en log: no se entregó correo real ni se contactó al estudio.
Adjuntos hasta 50 MiB; se verificaron 49 MiB admitidos y 51 MiB rechazados.
En PHP integrado 8.5.12, 65 MiB produce advertencia de arranque con HTTP 200
antes de Laravel, sin registro; no presentarlo como 413 solucionado.
No se probó producción, SMTP real, Safari/Firefox ni dispositivos físicos.
El PDF usa marca textual ARAT STUDIO y fuente DejaVu para Unicode, sin logo
gráfico; la web mantiene Kodchasan/Arial. La matriz contra el Informe Integral está en [REVISION_INFORME_INTEGRAL.md](REVISION_INFORME_INTEGRAL.md).
Resultados exactos y alcance de las
pruebas constan en [VERIFICACION_ENTREGA.md](VERIFICACION_ENTREGA.md).
