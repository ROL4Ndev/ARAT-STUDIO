# ARAT STUDIO — guía de exposición (12 minutos)

Guion basado en el código actual. Solo preparación documental: no se crearon
cuentas ni registros. Sustituir `MARCA` por una marca nueva, por ejemplo
`20261111-1200`; no reutilizarla en otro ensayo ni editar datos ajenos.

## Antes de comenzar

- MySQL/MariaDB de XAMPP activo y conexión del proyecto disponible; comprobar
  `php artisan migrate:status`. No reiniciar tablas ni ejecutar seeders.
- Ejecutar `php -v` y `php --ini`: usar PHP 8.5 de Herd Lite, cuyo INI es
  `C:\Users\rolan\.config\herd-lite\bin\php.ini`, no el PHP de XAMPP.
  Verificar `upload_max_filesize=50M` y `post_max_size=64M`.
- Desde la raíz: `php artisan serve`; abrir `http://127.0.0.1:8000`.
- Disponer de cuentas de demostración propias `admin` y `gestor`, con claves
  introducidas manualmente, nunca en la guía o capturas. Si faltan, una persona
  autorizada puede ejecutar `php artisan usuarios:crear-interno`, que pregunta
  rol y contraseña; no hay registro público. No usar las cuentas QA deshabilitadas.
- Usar navegador normal para admin y ventana privada para visitante/gestor;
  así se evita mezclar sesiones. No proyectar `.env` ni contraseñas.
- Abrir `/proyectos`: comprobar portadas antiguas `public/img` y las nuevas
  del disco público (`public/storage`). El enlace público es solo para portadas,
  nunca para `storage/app/commercial`.
- PDF pequeño existente en este entorno: `storage/framework/testing/verification-input.pdf`
  (1,339 bytes). Es local e ignorado por Git; si falta en otra máquina, crear
  manualmente una página «Plano ficticio de exposición, sin datos reales» y
  exportarla a PDF menor a 1 MB. No usar planos reales.
- Antes de exponer, ensayar la descarga de una cotización de prueba autorizada
  desde `/gestion` y abrirla en un lector PDF. Confirmar permisos de escritura
  del almacenamiento privado. No inventar códigos: copiar los generados.

## Recorrido cronometrado

| Tiempo | Qué mostrar y decir |
|---|---|
| 00:00–02:00 | Home `/`: estudio, servicios, equipo, proyectos activos y contacto. Entrar por `/ingresar` como admin de demostración; en `/perfil`, editar únicamente el nombre de esa cuenta, por ejemplo «Expositor MARCA», conservando correo y rol. Mostrar confirmación y navegación según sesión. |
| 02:00–05:00 | `/admin/categorias`: crear «Demo MARCA»; mostrar validación y edición de esa categoría nueva. `/admin/proyectos`: crear «Proyecto ficticio MARCA», asociarlo a la categoría, descripción «Prueba de exposición; no corresponde a una obra del estudio», portada `public/img/Proyecto Sala T/1.webp` y estado activo. Mostrar listado, edición del registro propio y su enlace en `/proyectos`. |
| 05:00–10:00 | En ventana privada, ejecutar escenario A: `/cotizador` → solicitud → confirmación; entrar allí como gestor y localizar el código en `/gestion`. Descargar adjunto, evaluar, aprobar, emitir y abrir PDF; contrastar estimación e importe oficial. Mostrar escenario B con 501 m² y su historial, reservando aproximadamente el último minuto del bloque. |
| 10:00–12:00 | Escenario C: 403 como gestor; probar la URL privada del adjunto tras cerrar sesión (redirige a `/ingresar`). Explicar los archivos siguientes y las limitaciones, sin presentar funciones pendientes como terminadas. |

Para enseñar eliminación de categorías, usar solo una categoría vacía nueva
del ensayo y explicar el bloqueo si tiene proyectos. Los proyectos se desactivan:
no existe ruta de eliminación. No borrar ni modificar contenido previo.

## Tres escenarios manuales reproducibles

### A. 80 m² / LOD 300 → cotización

1. Como visitante, abrir `/cotizador`, área `80`, nivel `LOD_300`, tipo `Vivienda`.
   Con las tarifas consultadas en esta preparación: `80 × 100.00 × 1.350 = S/ 10,800.00`.
   Son tarifas demostrativas, no un precio oficial; si cambian, ensayar de nuevo.
2. Pulsar **Solicitar proyecto** (`/solicitar-proyecto`) e introducir manualmente:

   | Campo | Valor ficticio |
   |---|---|
   | Nombre | Cliente ficticio A MARCA |
   | Correo | exposicion.a.MARCA@example.test |
   | Teléfono / ciudad | 999000123 / Lima |
   | Servicio / tipo | Modelado BIM / Vivienda |
   | Área / LOD | 80 / LOD 300 |
   | Descripción | Modelado de vivienda ficticia para la exposición académica. |
   | Archivo / enlace | PDF pequeño anterior / dejar enlace vacío |
   | Consentimiento | Marcar la casilla |

3. Registrar una sola vez y copiar el código `SOL-…` de
   `/solicitar-proyecto/confirmacion`. Esa confirmación depende de la sesión;
   no es un buscador público de solicitudes.
4. Entrar como gestor; buscar el código en `/gestion`, abrir el detalle y descargar
   el archivo. Guardar **En evaluación**, observación «Revisión ficticia del alcance»;
   luego **Aprobar**, observación «Alcance viable para la demostración».
5. Introducir alcance «Modelado BIM de vivienda ficticia y entrega de planos para
   demostración académica», importe oficial `12000.50`; emitir y descargar PDF.
   Comprobar código `COT-…`, cliente, PEN, alcance, fecha del día y vencimiento
   a 15 días. No prometer envío automático de ese PDF al cliente.

### B. 501 m² → evaluación personalizada

Abrir un formulario nuevo, usar «Cliente ficticio B MARCA», correo
`exposicion.b.MARCA@example.test`, área `501` y LOD 300; conservar los demás
datos ficticios y aceptar consentimiento. Con límite actual de 500 m² debe
registrarse pendiente, con evaluación personalizada y estimación **null**, no cero.
Buscar su código en `/gestion`, pasar a evaluación y mostrar el historial;
opcionalmente rechazar con motivo «Alcance ficticio fuera de disponibilidad».
Sin motivo no se guarda el rechazo; una solicitud pendiente no admite cotización.

### C. Gestor → CRUD administrativo: 403

Con sesión **gestor** confirmada, escribir `/admin/categorias` y después
`/admin/proyectos` en la barra: ambos deben devolver **403**. No basta con mostrar
que el enlace está oculto; como visitante, la misma URL redirige al ingreso,
por lo que no demuestra el mismo caso. `/gestion` sí debe abrir para el gestor.

## Código que debemos explicar (dos frases por punto)

- **Rutas y roles:** `routes/web.php` separa las rutas públicas, las protegidas
  por `auth`, el CRUD `role:admin` y gestión `role:admin,gestor`; `routes/api.php`
  define `POST /api/calcular-presupuesto`. `bootstrap/app.php` registra el alias
  `role` y `app/Http/Middleware/EnsureUserHasRole.php` devuelve 403 si no coincide.
- **Validación CRUD:** `app/Http/Requests/Admin/StoreProjectRequest.php` autoriza
  solo admin y exige título, categoría existente, descripción y una portada
  JPG/JPEG/PNG/WebP de hasta 5 MB. `app/Http/Controllers/Admin/ProjectController.php`
  recibe los datos validados y guarda la portada en el disco público.
- **Relaciones:** `app/Models/Project.php::projectCategory()` usa `belongsTo`
  mediante `category_id`, y `app/Models/Category.php::projects()` usa `hasMany`.
  `app/Http/Controllers/PublicSite/HomeController.php` carga la categoría con
  `with()` y consulta hasta tres proyectos activos, sin listas ficticias en Blade.
- **Decimal e historia:** `app/Services/QuotationCalculatorService.php` multiplica
  con `BigDecimal` y redondea con `RoundingMode::HalfUp` a dos decimales; fuera
  de escala devuelve null. `app/Services/ServiceRequestService.php` recalcula y
  guarda importe decimal, tarifa, factor y límite como copia histórica;
  `app/Models/ServiceRequest.php` los expone con casts decimales.
- **Transacciones, estados y duplicados:** `ServiceRequestService.php` guarda
  solicitud e historial juntos; `CommercialWorkflowService.php`, ambos en
  `app/Services/`, bloquea la fila, verifica estado anterior y permite solo
  pendiente → evaluación → aprobado/rechazado. La migración
  `database/migrations/2026_11_11_180000_create_commercial_tables.php` impone
  códigos y token únicos, y una cotización por solicitud; `IssueQuotationService.php`
  en la misma carpeta usa otra transacción y limpia el PDF ante fallos.
- **PDF privado:** `app/Services/QuotationPdfService.php` renderiza
  `resources/views/commercial/quotation-pdf.blade.php` con Dompdf, sin recursos
  remotos, PHP ni JavaScript, y `IssueQuotationService` guarda en el disco
  `commercial` definido en `config/filesystems.php`. `app/Http/Controllers/Commercial/QuotationController.php::download()`
  entrega el PDF por código bajo middleware autorizado; el cliente nunca elige
  una ruta de archivo y los snapshots conservan los datos emitidos.

## Preguntas probables del profesor

| Pregunta | Respuesta basada en el código |
|---|---|
| ¿Ocultar botones protege los datos? | No: el middleware y los Form Requests validan el acceso también al escribir la URL o enviar una petición. |
| ¿Se puede alterar el presupuesto desde el navegador? | Al registrar se recalcula con tarifas de BD; no se aceptan importes, factores ni estado inicial del navegador. La conversión a número del JSON mantiene compatibilidad; el cálculo y lo persistido usan decimal. |
| ¿Cambiar tarifas modifica solicitudes anteriores? | No: cada solicitud guarda los valores usados; la cotización conserva también cliente y estudio como snapshots. |
| ¿Qué pasa con un doble clic? | El token del mismo envío tiene índice único y el servicio recupera el registro existente; dos formularios nuevos pueden crear solicitudes distintas para el mismo correo. |
| ¿Dos gestores pueden emitir dos cotizaciones? | Se bloquea la solicitud y hay índice único sobre su relación con cotizaciones; se devuelve la existente si ya fue emitida. No afirmar que se hizo una prueba de carga simultánea en MariaDB: se verificaron reintentos y restricciones, además de las regresiones aisladas. |
| ¿Y si falla correo o PDF? | El fallo de correo no revierte la solicitud; si falla la emisión, la transacción revierte y se intenta limpiar el archivo para reintentar sin duplicar. |
| ¿Está completo el CRUD de proyectos? | Incluye alta, listado, detalle y edición; se desactiva en vez de eliminar. Una categoría con proyectos asociados no puede borrarse. |
| ¿Cómo se comprobó? | El informe previo registra Chrome móvil/escritorio y 48 pruebas con 370 aserciones; las pruebas usan SQLite en memoria, archivos y correo falsos. Esta guía no vuelve a ejecutar esos escenarios ni modifica la BD. |

## Capturas que debe entregar cada integrante

Cada integrante entrega su propio juego, con datos ficticios y barra de URL
visible; nunca incluir contraseñas, `.env` ni logs con datos de clientes.

1. Home completo o portada y secciones principales, `/`.
2. Categorías: listado y formulario del ensayo, `/admin/categorias`.
3. Proyectos: listado y edición del ensayo, `/admin/proyectos`.
4. Solicitud registrada con código, `/solicitar-proyecto/confirmacion`.
5. Evaluación e historial, `/gestion/solicitudes/{codigo_SOL}`.
6. Cotización y PDF abierto, `/gestion/cotizaciones/{codigo_COT}`;
   descarga: `/gestion/cotizaciones/{codigo_COT}/pdf`.
7. Repositorio con URL visible: https://github.com/ROL4Ndev/arat-studios
   (requiere acceso si sigue privado). La captura muestra el estado publicado,
   que puede diferir del trabajo local; esta preparación no hace push.

## Limitaciones que debemos mencionar

- En desarrollo el correo usa `log`: se registra localmente, no se entrega
  realmente. `app/Mail/ServiceRequestReceived.php` implementa recepción de
  solicitud; no anunciar «correo enviado» ni envío del PDF.
- El límite de adjuntos es 50 MiB. El informe documenta que, con este servidor
  integrado PHP 8.5.12 y `post_max_size=64M`, un envío de 65 MiB devolvió una
  advertencia de arranque y HTTP 200 antes de Laravel, sin guardar solicitud;
  no presentarlo como un error 413 real resuelto. El manejador 413 sí tiene
  prueba aislada; usar el PDF pequeño y explicar el pendiente del runtime.
- Tarifas demostrativas, una cotización por solicitud y estados finales sin
  retroceso. No presentar como terminados administración de tarifas, galerías
  ampliadas, entrega real de correos ni pruebas de producción/concurrencia.
