# ARAT STUDIO · revisión del 14 de noviembre de 2026

Implementación sobre `44d70fb`. El checkout encontrado estaba en `main`, con ese mismo commit; se conservó la rama y el archivo previo `revision-arat.diff`. No se hicieron commits, push ni despliegues. No se modificaron `.env`, APP_KEY ni credenciales.

**La base existente conserva sus tres proyectos. Las dos migraciones nuevas están pendientes allí.** La incorporación de fotografías y todas las operaciones de demostración se ejecutaron exclusivamente en SQLite aislado. La concurrencia usó bases nuevas `arat_concurrency_*`.

## Funcionalidades y archivos principales

| Bloque | Implementación |
| --- | --- |
| Identidad | Kodchasan local Latin 400, 500 y 600, `font-display: swap`; títulos e identidad. Segoe UI para cuerpo, formularios y tablas. Carbón y azul grisáceo originales. |
| Estilos | `resources/css/studio.css` compartido, `public.css` público e `internal.css` interno. `app.css` carga Bootstrap; `panel.css` carga Tabler, que ya contiene Bootstrap. |
| Equipo | `TeamController`, `ManagerRequest`, `UserPolicy`, `TeamService` y `admin/team/*`. Alta, consulta, búsqueda, edición, estado, restablecimiento protegido, expedientes y auditoría. |
| Acceso | Rol gestor fijado por servidor. Desactivación y restablecimiento invalidan sesiones previas mediante `auth_version`; se revoca también el token de recordar sesión. El cambio de contraseña propio conserva únicamente la sesión actual. |
| Reasignación | Usuarios bloqueados por ID antes de expedientes. Desactivación y reasignación de abiertos en la misma transacción; versión y cantidad esperada evitan confirmar una vista obsoleta. Cerrados, cancelados y rechazados conservan responsable e historial. Un fallo de auditoría revierte todo. |
| Selección pública | `HomeController` consulta `is_featured`, ordena por `featured_order` e ID y carga relaciones por lote. Publicación y destacado son independientes; proyecto y categoría deben estar activos. Sin límite fijo de tres. |
| Portafolio | Todos los publicados, paginados de nueve en nueve y filtrados por categoría. Detalles inactivos devuelven 404. Estado vacío de destacados con acceso al portafolio. |
| Galería | `ProjectImage`, `ProjectGalleryController`, `GalleryRequest`, `ProjectGalleryService`, `projects-gallery.blade.php` y `gallery.js`. Vincular carpeta, subir, ordenar, portada, archivar y reactivar; originales e historial conservados. |
| Inventario | `PortfolioInventory`, `portfolio:inventario` y `portfolio:incorporar`. Revisión sin escrituras por defecto; incorporación idempotente como borradores, con administrador y categoría explícitos. No sobrescribe registros encontrados. |
| Indicadores | `CommercialIndicators`, `dashboard-insights.blade.php` y `dashboard.js`. Agregaciones SQL, gráficos por semana/estado, pendientes y recientes; gestor limitado a asignados. |
| Controlador comercial | `index()` separa filtros, bandeja mínima, historial e indicadores. Conserva los filtros en los nuevos enlaces de la bandeja y la privacidad de los contactos sin asignar. |
| Movimiento | CSS e IntersectionObserver propios: aparición de 550 ms, transiciones breves y una entrada de encuadre fotográfico de nueve segundos, una sola vez. Cuadrícula arquitectónica tenue. Sin video. |

Las contraseñas y hashes no forman parte de la auditoría ni de las respuestas del módulo de equipo. Los cambios de datos, estado, acceso, galería y selección editorial se registran con motivo. Se conserva el comando anterior de creación de usuarios internos.

## Cómo usarlo

**Equipo:** ingresar como administrador → Equipo · Gestores. Crear con nombre, correo único, contraseña confirmada de al menos 12 caracteres y motivo. Consultar la cuenta para editar datos, ver expedientes o cambiar estado. Si tiene abiertos, elegir otro responsable activo antes de desactivar. El restablecimiento exige también la contraseña del administrador y confirmación explícita; el acceso nuevo se comunica por un canal privado.

**Destacados:** Administración → Proyectos → Editar → Destacado en inicio y Orden en inicio. Los números menores aparecen primero; los empates se resuelven por ID. Desmarcar destacado conserva la publicación en el portafolio. Tras migrar, los registros existentes comienzan sin destacar: seleccionar los que deben aparecer en inicio.

**Galería:** consultar el proyecto → Imágenes del proyecto. Elegir una carpeta existente o subir hasta 20 imágenes JPG, PNG o WebP por operación, de 5 MiB cada una. Repetidas por SHA-256 no crean entradas duplicadas. Modificar posiciones y guardar el orden con motivo. En Opciones de imagen se puede elegir portada, archivar o reactivar; cambiar antes la portada si se quiere archivar la actual. La galería pública permite Enter, flechas, Inicio, Fin y Escape, y devuelve el foco al enlace original. Sin JS, los enlaces abren las fotografías originales.

Los originales de `public/img` ya son archivos públicos: archivar retira una imagen de la galería, conserva el archivo y su URL original. La visibilidad del expediente de proyecto sigue controlada por su estado y categoría.

## Inventario y contenido pendiente

Se consultó la base existente antes y después, sin escrituras: 13 carpetas, 100 imágenes y tres coincidencias únicas, sin cambios entre ambos inventarios. [Inventario inicial](inventario-trabajo.json) y [consulta final](inventario-trabajo-final.json) incluyen archivos, tamaños, dimensiones, SHA-256, agrupación propuesta, coincidencias y datos faltantes.

| Carpeta | Imágenes | Registro existente |
| --- | ---: | --- |
| Aldea Infantil para NNA | 6 | Falta |
| Centro Cultural | 9 | Falta |
| Centro de Int. Residencial | 9 | Falta |
| Departamento MR | 10 | Coincide |
| Dormitorio Luna | 6 | Falta |
| Dormitorio SR | 5 | Falta |
| Edificio Multifamiliar | 12 | Falta |
| Hogar de Refugio | 3 | Falta |
| Hospital de Megantoni | 6 | Falta |
| Proyecto AT | 7 | Falta |
| Proyecto OPV | 8 | Coincide |
| Proyecto PET | 15 | Falta |
| Proyecto Sala T | 4 | Coincide |

Para los diez faltantes hay que confirmar categoría, descripción editorial, título y selección de fotografías. El importador usa el nombre de carpeta como título provisional y deja descripción vacía, inactivo y sin destacar. No inventa clientes, fechas ni características. La publicación posterior exige completar la descripción y las validaciones habituales.

En la demostración se probaron 13 registros, diez borradores y 99 entradas de galería: una repetición de contenido dentro de una carpeta se deduplicó. Las 100 fotografías originales conservaron su SHA-256. Las miniaturas de 480 px sumaron 2.19 MB frente a 23.85 MB de originales vinculados únicos; es una comparación de archivos, no una medición de carga de página. [Datos de imágenes](media-summary.json).

## Indicadores y significado

Administrador: ámbito general autorizado. Gestor: exclusivamente sus asignados. La bandeja mínima sin asignar conserva solo código, servicio, tipo de proyecto, fecha, estado y versión; sin contactos ni descargas.

El período predeterminado comprende 30 días calendario, incluido hoy, en America/Lima. Las semanas empiezan explícitamente el lunes y terminan el domingo; los extremos se recortan al período. Se muestran hasta 52 intervalos recientes si se elige un período muy largo. Distribución: estado actual de solicitudes creadas dentro del período; no es un historial de cambios de estado.

En Administración, pendientes y recientes incluyen todos los períodos: cinco pendientes/en evaluación/observadas más antiguas y seis recientes. En Gestión, los filtros de la bandeja también se aplican al nuevo resumen y sus enlaces; la interfaz lo indica. Las métricas comerciales anteriores conservan sus definiciones y ámbito: aceptación por expediente en la cohorte de primera emisión, emisiones por período y promedio hasta primera evaluación. Ninguna representa ingresos cobrados.

`created_at` se interpreta como hora local histórica y `first_evaluated_at`/emisiones como UTC, respetando el modelo existente. El promedio se calcula con `AVG` y `julianday` en SQLite o `TIMESTAMPDIFF` en MariaDB; se normaliza el ruido de coma flotante a seis decimales. Las consultas agregadas no hidratan todos los expedientes. Índices nuevos para asignado/estado, asignado/fecha y fecha/estado; selección de proyectos y orden de galería también indexados.

Las tablas alternativas nacen abiertas. Cuando Chart.js construye correctamente un gráfico, su tabla queda disponible mediante el desplegable. Sin JS, o con gráfico ausente, los datos siguen visibles. Solo Administración y Gestión cargan `dashboard.js`; la galería es un módulo dinámico exclusivo de páginas con imágenes.

## Dependencias, licencias e investigación

| Recurso | Licencia comprobada / decisión |
| --- | --- |
| `@fontsource/kodchasan` 5.3.0 | SIL OFL 1.1. Solo se empaquetan Latin 400/500/600 WOFF2, unos 45.6 kB en total. [Uso oficial](https://fontsource.org/fonts/kodchasan/use), [licencia incluida](../../public/licenses/Kodchasan-OFL.txt). |
| Chart.js 4.6.1 | MIT. Importación explícita de Chart, BarController/BarElement, CategoryScale/LinearScale, DoughnutController/ArcElement, Tooltip y Legend. [Integración oficial](https://www.chartjs.org/docs/latest/getting-started/integration.html), [licencia](../../public/licenses/Chart-js-MIT.txt). |
| Sharp 0.35.5 | Apache-2.0. Genera miniaturas WebP conservando originales. Necesario porque el GD de este Herd no decodifica JPEG/WebP; proceso Node con argumentos separados y trabajos por stdin. [Instalación](https://sharp.pixelplumbing.com/install/), [redimensionado](https://sharp.pixelplumbing.com/api-resize/), [licencia](../../public/licenses/Sharp-Apache-2.0.txt). |
| [bg.ibelick.com](https://bg.ibelick.com/) y [background-snippets](https://github.com/ibelick/background-snippets) | Se revisó la colección de fondos. No se encontró licencia explícita en la raíz consultada; no se copió su código ni se incorporó el proyecto Next/Tailwind. Se tomó la referencia visual de retícula para una implementación propia. |
| [pattern.css](https://bansal.io/pattern-css) | El [README oficial declara MIT](https://github.com/bansal/pattern.css#license) y compatibilidad con Bootstrap. No se instaló: una retícula propia de dos gradientes cubre la necesidad. |
| [Animista](https://animista.net/) | El sitio identifica el CSS generado como FreeBSD. Se revisaron entradas suaves; se implementaron keyframes propios, sin copiar código generado ni descargar la colección. |
| [Motion](https://motion.dev/docs/quick-start) | [MIT](https://github.com/motiondivision/motion/blob/main/LICENSE.md). No se instaló: no hay secuencias complejas que justifiquen otro motor; CSS e IntersectionObserver resuelven esta fase. |
| [GLightbox](https://github.com/biati-digital/glightbox) | [MIT](https://github.com/biati-digital/glightbox/blob/master/LICENSE.md). Se evaluó para galería; se eligió `dialog` nativo con enlaces de respaldo, teclado y foco, sin otra dependencia. |

No se incorporaron bibliotecas de animación, patrones o lightbox. No se generó ni añadió video. La aplicación usa archivos locales, sin peticiones de fuentes o bibliotecas a CDN.

## Verificación ejecutada

PHP utilizado: `C:\Users\rolan\.config\herd-lite\bin\php.exe`, versión **8.5.12**. Node **26.13.0** y Vite **7.3.7**. Se conservó intacta la protección de `tests/TestCase.php`: pruebas HTTP exclusivamente SQLite `:memory:`.

- Antes de cambios: **94 pruebas, 830 aserciones**, todas pasaron.
- Suite final: **110 pruebas, 1.022 aserciones**, todas pasaron; 76.13 s de consola. [Resumen del JUnit](tests-summary.json). Nuevas regresiones de permisos, roles, credenciales, sesiones, reasignación, reversión de transacción, selección, orden, paginación, consulta por lotes, galería, subidas, imágenes falsas, inventario idempotente, semanas de Lima y gráficos vacíos/privados.
- MariaDB: **diez comprobaciones pasaron** en `arat_concurrency_20261114_211348`, creada para ensayo. Incluyen toma simultánea, borrador idempotente, envío repetido, aceptación contra emisión en ambos órdenes, cambio tarifario, desactivación contra toma/asignación y media SQL de 45 minutos. [Resultados](mariadb-concurrency.json). Las bases de ensayo se conservan para inspección; no se reinició ni borró la base de trabajo.
- Compilación final correcta: `npm.cmd run build -- --configLoader runner`. Se usó caché npm dentro de `storage/app` por el sandbox de Windows. [Tamaños del build](build-summary.json): CSS público 36.24 kB gzip, CSS interno 89.76 kB gzip, JS común 2.30 kB gzip, dashboard 56.82 kB gzip y galería dinámica 0.48 kB gzip. No se afirma una reducción frente al build anterior.
- Chrome **157.0.8103.51**, Playwright local: [59 comprobaciones del sitio y recorrido comercial](captures/commercial/checks.json), sin errores JS; [17 comprobaciones específicas](captures/review/checks.json), sin errores de consola ni JS. Escritorio 1440 px, móvil emulado 390 px, teclado, errores, sesión revocada, reasignación, estados vacíos, movimiento reducido y JS desactivado.
- Chrome DevTools Protocol confirmó **Kodchasan-Regular** realmente renderizada en el título y **SegoeUI** en el párrafo; no solo una declaración CSS. También se comprobó que inicio no descarga el paquete de gráficos y solicita solo tres WOFF2.
- El recorrido comercial usa datos ficticios: solicitud → asignación → evaluación → aprobación → borrador → emisión/PDF → acceso temporal → aceptación → cierre. También observa propuesta vencida y descarga de una versión anterior. No se regeneraron PDF reales o históricos de la base de trabajo.
- [Revisión de artefactos](artifact-checks.json): 100 originales intactos por SHA-256; ningún `.env`, SQLite temporal ni `state.json` entre archivos nuevos/modificados versionables; sin coincidencias de claves privadas, APP_KEY real o URL/token de acceso en el escaneo final. Se omitieron los tokens de demostración del JSON nuevo y del JSON de evidencia anterior; el capturador ya los oculta. Se revisaron además los cambios. El escaneo por patrones no es un detector universal de secretos.

Medición acotada del controlador con la misma muestra de 1.000 solicitudes sintéticas: [antes](indicators-before.json), 15 consultas/68.68 ms; [después](indicators-after.json), 19 consultas/52.34 ms. El nuevo resumen añade consultas, mientras el promedio pasa de recuperar 1.000 filas a un único agregado. Son ejecuciones individuales de SQLite, sin renderizar la vista, con cachés/carga variables: **no demuestran una mejora de latencia**. La regresión de tarjetas verifica consultas por lote y ausencia de escrituras, sin crecimiento por tarjeta.

## Capturas para revisar

| Pantalla | Antes | Después |
| --- | --- | --- |
| Inicio escritorio | [Ver](captures/before/01-inicio.png) | [Ver](captures/after/01-inicio.png) |
| Inicio móvil | [Ver](captures/before/02-inicio-movil.png) | [Ver](captures/after/02-inicio-movil.png) |
| Proyecto y galería | [Ver](captures/before/03-proyecto.png) | [Ver](captures/after/03-proyecto.png) |
| Dashboard | [Ver](captures/before/04-dashboard.png) | [Ver](captures/after/04-dashboard.png) |
| Gestión comercial | [Ver](captures/before/05-gestion.png) | [Ver](captures/after/05-gestion.png) |
| Proyectos administrativos | [Ver](captures/before/06-proyectos.png) | [Ver](captures/after/06-proyectos.png) |

También: [equipo](captures/review/06-equipo.png), [crear con error](captures/review/07-error-confirmacion.png), [consultar gestor](captures/review/08-consulta-gestor.png), [móvil](captures/review/09-consulta-gestor-movil.png), [galería administrativa](captures/review/10-galeria-administracion.png), [destacados](captures/review/12-seleccion-destacados.png), [indicadores vacíos](captures/review/14-indicadores-vacios.png), [sin JS](captures/review/15-sin-javascript.png) y [galería por teclado](captures/review/02-galeria-teclado.png). Las cifras son de demostración; las capturas posteriores incluyen escenarios ficticios adicionales.

## Pasos pendientes en la base existente

1. Aplicar las migraciones `2026_11_14_160000_add_team_account_guards` y `2026_11_14_170000_add_portfolio_selection_and_gallery` mediante el procedimiento habitual y con respaldo. Ambas ya se ejecutaron en SQLite de ensayo y MariaDB exclusivo. **El código nuevo requiere estas migraciones antes de usarse sobre la base existente.** No ejecutar `migrate:fresh`, `refresh` ni seeders de reinicio.
2. Instalar dependencias del lockfile (`npm ci`, incluidas opcionales nativas de Sharp), compilar y disponer de Node para las operaciones de galería. El mínimo combinado con Vite 7 es Node 20.19+ o 22.12+; se probó 26.13.0. Si el proceso PHP no encuentra Node, configurar `THUMBNAIL_NODE_BINARY` con su ruta absoluta en el entorno del servidor. No se cambió `.env` en esta tarea.
3. Comprobar el enlace público de almacenamiento existente (`php artisan storage:link` si falta), permisos de escritura y carga de las miniaturas. Las herramientas de revisión usan un router local aislado; no se publican.
4. Seleccionar destacados en el panel. Revisar el inventario de los diez faltantes y confirmar su categoría y metadatos antes de publicar.
5. Para una incorporación posterior: ejecutar primero `php artisan portfolio:inventario` y `php artisan portfolio:incorporar` (solo revisión). Tras decidir los IDs, `php artisan portfolio:incorporar --apply --category=ID_CATEGORIA --actor=ID_ADMIN --include-galleries`. Sustituir los IDs; la opción final también vincula fotos de coincidencias existentes, sin cambiar sus datos editoriales. Una repetición no duplica proyectos ni imágenes; se puede reintentar la galería de un borrador importado.

Los comandos `php` anteriores deben usar Herd 8.5, no el XAMPP 8.2 que aparece primero en PATH en esta terminal.

Para repetir la revisión aislada: `ARAT_PREVIEW_NAME=design-preview-v2` en el entorno, servidor `php -S 127.0.0.1:8765 -t public tools/preview/router.php`, y scripts `revision-capture.mjs`/`revision-verify.mjs`. Las credenciales efímeras permanecen solo en `storage/app/design-preview-v2/state.json`, ignorado por Git. `verify.mjs` permite `ARAT_CAPTURE_DIRECTORY` para conservar separada la evidencia previa. La preparación inicial se niega a reemplazar un SQLite existente.

Limitaciones: se probó Chrome de escritorio con emulación móvil, no dispositivos físicos ni Safari/Firefox. No se probó correo SMTP real ni despliegue. Los contenidos faltantes siguen pendientes de confirmación editorial. Los datos comerciales del dashboard serán los reales autorizados cuando se apliquen las migraciones; los gráficos y PDF de las capturas corresponden exclusivamente al ensayo.
