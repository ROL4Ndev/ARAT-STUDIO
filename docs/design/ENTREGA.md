# ARAT STUDIO · entrega de diseño y revisión

Fecha: 14 de noviembre de 2026. Base revisada: `8f9c467`. El repositorio estaba limpio al comenzar. No se hicieron commits, push ni despliegues. `.env`, `APP_KEY`, credenciales y datos de trabajo se conservaron.

## Cambios implementados

- **Inicio y sitio público:** composición con fotografía del portafolio, títulos legibles, carbón `#2E2F36`, azul grisáceo `#324853`, fondos neutros y espaciado común. Se revisaron visualmente ambos logos y fotografías originales. Servicios con enlaces al formulario preseleccionado; portafolio filtrable y detalle con siguiente paso.
- **Navegación:** menú público único y navegación propia para el panel, con sección activa, cuenta y acceso al sitio público. Menús operables por teclado, Escape y cambio de ancho. Recursos compilados locales; se retiró la dependencia del CDN y de Google Fonts.
- **Cotizador y solicitud:** campos agrupados, ayuda sobre LOD, separación entre referencia y precio oficial, errores junto al campo y resumen con enlaces y foco. La firma de la estimación se invalida inmediatamente al editar datos. Los fallos de red se explican en español y permiten reintentar.
- **Confirmación:** código conservado y explicación de revisión, propuesta y acceso temporal.
- **Panel y catálogos:** indicadores separados, filtros y tablas legibles, desplazamiento horizontal contenido y formularios con secciones. Se mantienen la consulta, edición, activación/desactivación y auditoría de proyectos, categorías, servicios y tarifas.
- **Gestión comercial:** avance del expediente, evaluación y acciones agrupadas, aprobaciones y motivos conservados, detalles de tarifa y procesamiento en desplegables. Mensajes de actividad y estados en español.
- **Cliente:** propuesta actual priorizada, importe y vigencia visibles, versiones anteriores desplegables con sus PDF; instrucciones públicas de corrección destacadas. Las propuestas vencidas conservan su documento y no permiten aceptar. Las opciones de correo, rechazo y cancelación permanecen accesibles.
- **PDF:** cabecera con logo original, datos históricos, servicio, vigencia, alcance, entregables, exclusiones, plazo, importe y justificación. Se preservan los 15 días y la condición sobre firma digital. Los PDF ya emitidos no se regeneraron.
- **Mantenimiento:** consulta de actores, categorías y familias de auditoría agrupada; relaciones cargadas desde el controlador; filtros comerciales compartidos sin ampliar la visibilidad de la bandeja mínima. Se retiraron CSS, vistas, alias de controladores, validaciones y correo antiguos sin referencias activas. Las transacciones, autorización, versiones, historial y bloqueos del negocio se conservaron.

## Bloqueo de PHPUnit

Se reprodujo exactamente el error de `tests/TestCase.php` al heredar `DB_CONNECTION=mysql` y `DB_DATABASE` desde PowerShell.

1. Sin `force="true"`, PHPUnit conserva las variables del proceso.
2. Con `force="true"` en `<env>`, PHPUnit reemplaza `getenv()` y `$_ENV`, pero el PHP CLI de Windows conserva el valor anterior en `$_SERVER`.
3. Dotenv consulta `ServerConstAdapter` antes que `EnvConstAdapter`. Por eso cambiar solo `<env>` no resolvía este entorno.
4. `phpunit.xml` fija también las entradas `<server>` de aislamiento. `tests/TestCase.php` sigue rechazando cualquier entorno distinto de testing/SQLite/`:memory:` y también rechaza una URL de conexión configurada.

Se validó con `APP_ENV=local`, `DB_CONNECTION=mysql`, una base ficticia y una `DB_URL` MySQL heredadas. La suite pasó utilizando SQLite en memoria; no se conectó a esa URL.

El comando `php` del PATH resuelve a XAMPP **8.2.12**, incompatible con este Laravel/PHPUnit. Se ejecutó PHP **8.5.12** desde `C:\Users\rolan\.config\herd-lite\bin\php.exe`.

## Archivos principales

| Responsabilidad | Archivos |
| --- | --- |
| Diseño y compilación | `resources/css/studio.css`, `app.css`, `panel.css`, `vite.config.js` |
| Composiciones y navegación | `resources/views/layouts/app.blade.php`, `layouts/components/workspace-navigation.blade.php`, `resources/js/app.js` |
| Sitio y formularios | `home.blade.php`, `public/services.blade.php`, `projects/show.blade.php`, `service-requests/*`, `quotations/calculator.blade.php` |
| Panel y catálogos | `panels/dashboard.blade.php`, `admin/catalog/*`, `commercial/index.blade.php`, `commercial/show.blade.php`, `commercial/quotation.blade.php` |
| Expediente | `client/dossier.blade.php`, `client/quotation-card.blade.php`, `shared/request-progress.blade.php` |
| Validación e interacción | `shared/errors.blade.php`, `public/js/interface.js`, `public/js/estimate.js` |
| Consultas y mensajes | `app/Http/Controllers/Admin/CatalogController.php`, `Commercial/ServiceRequestController.php`, `Commercial/QuotationController.php` |
| PDF | `app/Services/QuotationPdfService.php`, `commercial/quotation-pdf.blade.php` |
| Pruebas | `phpunit.xml`, `tests/TestCase.php`, `tests/Feature/PresentationWorkflowTest.php` |
| Evidencia repetible | `tools/preview/*`, `docs/design/captures/*`, `docs/design/contrast.json` |

Las rutas y tablas de negocio mantienen sus nombres.

## Dependencias

| Cambio | Motivo |
| --- | --- |
| Bootstrap **5.3.9** | Base pública instalada localmente y compilada con Vite; sustituye la carga CDN 5.3.3. |
| Tabler Core **1.7.5** | CSS del panel para tablas, formularios, indicadores y navegación. Incluye Bootstrap y sustituye su hoja en esas páginas. |
| playwright-core **1.65.1**, desarrollo | Capturas y recorridos contra Chrome instalado; no descarga otro navegador. |
| Retirados Tailwind, @tailwindcss/vite y Axios | Eran parte del inicio de Laravel y no tenían consumidores activos tras la revisión. |

Cada página carga **una** base CSS: Bootstrap público o Tabler interno. No se cargan bundles JavaScript de Bootstrap/Tabler, plugins de tablas, React ni otro framework. Los menús usan el JavaScript pequeño del proyecto y elementos HTML nativos.

Documentación consultada: [Bootstrap 5.3](https://getbootstrap.com/docs/5.3/), [repositorio de Tabler](https://github.com/tabler/tabler), [documentación](https://docs.tabler.io/), [instalación e inclusión de Bootstrap](https://docs.tabler.io/ui/getting-started/installation). Se verificaron los componentes y utilidades usados contra el paquete instalado. Licencias MIT conservadas en [docs/licenses](../licenses/); los avisos de distribución permanecen en los CSS del proveedor.

## Resultados ejecutados

| Comprobación | Resultado |
| --- | --- |
| Suite original antes de cambios | **92 pruebas, 817 aserciones**, correctas con PHP de Herd y sin variables conflictivas. |
| Reproducción del fallo heredado | Error de protección confirmado antes de acceder a MySQL. |
| Suite tras los cambios, con variables heredadas | **94 pruebas, 830 aserciones, 0 fallos**, 33,102 segundos. |
| Compilación `npm.cmd run build` | Correcta con Vite 7.3.7. CSS público 36,45 kB gzip; CSS interno 90,94 kB gzip; JS común 1,57 kB gzip. |
| Navegador | Chrome real, controlado con Playwright. Escritorio 1440 × 1000, móvil 390 × 844; revisión adicional de gestión a 320, 768 y 1024 px. |
| Comprobaciones del navegador | 59 del recorrido y sus variantes, más 9 de interacción; sin errores JavaScript ni desbordamientos en las comprobaciones registradas. Se guardaron 64 capturas de revisión, además del antes, el piloto y las comparaciones finales. |
| Blade y sintaxis | Todas las vistas compilaron correctamente; sintaxis PHP de las herramientas y JavaScript comprobada sin errores. `git diff --check` sin incidencias. |
| PDF | Descarga HTTP 200, cabecera `%PDF`, documento abierto e inspeccionado en el visor PDF de Chrome. |
| Contraste de los pares principales | Texto 13,32:1; texto secundario 5,37:1; botón principal 9,60:1; estados 6,45:1 o superior. [Cálculos](contrast.json). |
| Integración MariaDB | **No completada**: `127.0.0.1:3306` rechazó la conexión. El intento no creó una base ni modificó la de trabajo. |

La compilación con el sandbox de Windows necesitó lectura de directorios padre para esbuild. Se verificó tanto `vite build --configLoader runner` como la compilación estándar con ese permiso. No requiere cambiar el script de npm.

Los informes JSON junto a las capturas registran los controles de desbordamiento, imágenes y errores JavaScript. Las comprobaciones de teclado verificaron el salto al contenido, foco de campo inválido, menú público y menú interno. Se probó también el fallo de red **simulado** y su recuperación; la captura lo identifica expresamente.

## Recorrido comercial comprobado en navegador

Todos estos pasos se completaron sobre datos ficticios en SQLite:

1. **Solicitud — correcto:** selección desde el cotizador, transferencia de datos y registro con consentimiento. Captura `10-confirmacion`.
2. **Responsable — correcto:** asignación mediante formulario y motivo obligatorio.
3. **Evaluación — correcto:** inicio y evaluación interna completa.
4. **Aprobación técnica — correcto:** estado aprobado y formulario de cotización habilitado. Captura `25-aprobacion-tecnica`.
5. **Borrador — correcto:** alcance, entregables, exclusiones, plazo, importe y justificación guardados. Captura `26-borrador`.
6. **Emisión y PDF — correcto:** procesamiento por el scheduler aislado y descarga del documento. Capturas `27-emision` y `34-pdf`.
7. **Acceso de cliente — correcto:** enlace temporal preparado por la función local existente y consumido en una sesión sin cuenta interna. Captura `28-verificar-acceso`.
8. **Aceptación — correcto:** confirmación de propuesta vigente. Captura `29-propuesta-cliente` antes de aceptar.
9. **Cierre — correcto:** estado cerrado, respuesta conservada y sin botón de aceptación. Captura `30-aceptacion-y-cierre`.
10. **Variantes — correctas:** solicitud observada con instrucciones públicas, propuesta vencida sin aceptación y versión anterior con PDF descargable. Capturas `31` a `33`.
11. **Errores y recuperación — correctos:** conexión interrumpida simulada, validación junto al campo, permiso denegado, catálogo vacío y anchos intermedios. Capturas `35` a `39`.

La suite automatizada conserva las verificaciones de permisos, concurrencia lógica, idempotencia, revisiones, inmutabilidad, tarifas históricas, versiones y rechazo de operaciones incompatibles. Las dos regresiones nuevas comprueban la presentación segura de observaciones y de propuestas vencidas antes y después del proceso de expiración.

## Capturas reales

La [galería local](index.html) permite comparar inicio y expediente y abrir las capturas del resto de pantallas.

| Pantalla | Antes | Después |
| --- | --- | --- |
| Inicio, escritorio | [Abrir](captures/before/01-inicio-escritorio.png) | [Abrir](captures/after/01-inicio-escritorio.png) |
| Inicio, móvil | [Abrir](captures/before/02-inicio-movil.png) | [Abrir](captures/after/02-inicio-movil.png) |
| Expediente, escritorio | [Abrir](captures/before/03-expediente-escritorio.png) | [Abrir](captures/after/03-expediente-escritorio.png) |
| Expediente, móvil | [Abrir](captures/before/04-expediente-movil.png) | [Abrir](captures/after/04-expediente-movil.png) |

Las capturas iniciales documentan el estado **real de este entorno**, donde Bootstrap desde CDN no cargó. Algunas imágenes con carga diferida todavía no aparecían en la captura integral inicial. No se reconstruyó un “antes” artificial. En la revisión posterior se esperó la decodificación de imágenes antes de capturar. Las capturas `pilot` corresponden a la validación inicial de inicio y expediente, anterior a la extensión al resto del sitio.

## Repetir la demostración aislada

Desde la raíz, con PHP 8.5 en el PATH o usando la ruta de Herd:

```powershell
php tools/preview/prepare.php
php tools/preview/scenarios.php
php -S 127.0.0.1:8765 -t public tools/preview/router.php
```

En otra terminal:

```powershell
php tools/preview/console.php schedule:work
node tools/preview/capture.mjs after
node tools/preview/verify.mjs
node tools/preview/interactions.mjs
```

`prepare.php` **rechaza una base ya existente**. En esta sesión la demostración ya quedó preparada: no hace falta volver a crearla. Su base, sesiones, caché, documentos y credenciales efímeras están en `storage/app/design-preview`, ignorado por Git. No se imprimen las contraseñas ni los enlaces temporales. Todo correo usa transporte `array`. El servidor enlaza únicamente con loopback y debe detenerse al terminar. Estas herramientas no añaden rutas de demostración a producción.

## Límites comprobados

- MariaDB estaba detenido/inaccesible: falta ejecutar la prueba de concurrencia real con el servicio disponible. No se sustituye ese resultado por las pruebas SQLite.
- No se verificaron SMTP real, producción, despliegue, Safari, Firefox ni lector de pantalla. El contraste medido y las pruebas de teclado no certifican accesibilidad completa.
- Las capturas del antes cubren inicio y expediente; el después incluye el resto de pantallas. La evidencia usa datos ficticios y las fotografías originales del portafolio.
- Product Design aportó sus guías; no había navegador integrado del plugin en esta sesión. Las capturas y las interacciones se hicieron con Chrome local, no con una conexión de otro chat.
- Las herramientas de vista previa y los resultados de prueba no reemplazan los procesos de backup, cola y scheduler del entorno de trabajo.
