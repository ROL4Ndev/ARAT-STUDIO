# ARAT STUDIO · recursos y portafolio · 15/11/2026

Se trabajó sobre el checkout existente `main`, commit `0583fa5`, conservando `revision-arat.diff`. No se hicieron commits, push ni despliegues. Se mantuvieron Laravel, Blade, Bootstrap público, Tabler interno, Kodchasan y la identidad original.

## Resultado en la base habitual

| Estado | Cantidad |
| --- | ---: |
| Proyectos existentes conservados | 3 |
| Proyectos incorporados | 10 |
| Proyectos totales | 13 |
| Publicados, con categoría activa | 13 |
| Borradores o pendientes de completar | 0 |
| Destacados seleccionados en la base real | 0 |
| Entradas de galería | 99 |
| Duplicados por proyecto y SHA-256 | 0 |

Se inspeccionaron las 100 imágenes y las láminas originales antes de redactar. Una repetición de contenido dentro de la misma carpeta se conserva entre los archivos originales, pero no genera otra entrada de galería. El importador se ejecutó primero sobre SQLite aislado y después sobre MariaDB habitual, utilizando `portfolio:incorporar` y los servicios existentes de catálogo, publicación y auditoría. La repetición posterior no añadió proyectos, imágenes ni versiones.

Se crearon las categorías **Arquitectura institucional** y **Arquitectura residencial**, determinadas por las tipologías y encabezados de las láminas. Los diez nuevos registros se prepararon como borradores y se publicaron únicamente después de completar título, descripción, categoría activa, portada y galería, pasando las validaciones existentes. Las propuestas académicas se identifican como tales. No se inventaron clientes, fechas, medidas, ubicaciones adicionales ni condiciones de obra ejecutada.

Los títulos, descripciones, slugs, categorías, portadas, publicación y selección editorial de los tres registros previos se conservaron. La vinculación de sus galerías añade metadatos y versiones de edición. Durante la incorporación se compararon huellas de las tablas comerciales; permanecieron iguales. La comprobación final también conserva sus recuentos y verifica **cuatro PDF históricos** contra sus SHA-256 almacenados, sin regenerarlos. [Integridad final](live-integrity.json), [incorporación real](portfolio-live.json), [ensayo previo](portfolio-demo.json).

Las dos migraciones de equipo y galería **ya estaban aplicadas al comenzar**, en el lote 12. Se consultó su estado antes y después; no fue necesario aplicar nuevas migraciones. Antes de las incorporaciones se creó un respaldo completo con `mysqldump --single-transaction`, de **147.164 bytes**, conservado en `storage/app/revision-3/arat-studios-before-portfolio.sql`. Su recibo y SHA-256 están en `storage/app/revision-3/backup-receipt.json`. Ambos son privados e ignorados por Git. Las credenciales se pasaron al proceso mediante su entorno, sin argumentos de contraseña ni impresión en consola.

## Recursos y preparación

La carpeta encontrada fue `recursos` en el escritorio de OneDrive. El usuario confirmó la correspondencia de ambos retratos. Los cuatro originales siguen intactos, comprobados contra las copias fuente privadas en `storage/app/revision-3/media`. Solo se publican derivados con nombres claros en `public/media/studio`; las vistas usan `asset()` y ninguna ruta absoluta de Windows.

FFmpeg/FFprobe no aparecieron en PATH ni en las ubicaciones de instalación revisadas. Se utilizó el Chrome y Sharp ya disponibles, sin instalar herramientas ni dependencias nuevas: decodificación de los originales, captura real de fotogramas, grabación H.264 de un canvas sin pista de audio y conversión a WebP. La duración, muestras, codecs y FPS se verificaron leyendo las tablas ISO BMFF, incluidas las muestras fragmentadas del MP4 generado. La decodificación y reproducción de ambos MP4 finales se comprobaron en Chrome.

| Recurso final | Dimensiones | Duración / formato | Peso decimal |
| --- | --- | --- | ---: |
| `inicio-charlotte-may.mp4` | 1280×720 | 12,97 s · H.264 · sin audio | 1.159.902 B |
| `estudio-arthouse.mp4` | 1280×720 | 10,21 s · H.264 · sin audio | 1.014.593 B |
| `inicio-charlotte-may-poster.webp` | 1280×720 | Fotograma real | 66.354 B |
| `estudio-arthouse-poster.webp` | 1280×720 | Fotograma real | 107.772 B |
| `angelo-trinidad.webp` | 800×1000 | Retrato WebP | 79.744 B |
| `nicole-arana.webp` | 800×1000 | Retrato WebP | 58.634 B |
| `servicio-arquitectura.webp` | 960×720 | Proyecto OPV | 103.200 B |
| `servicio-interiores.webp` | 960×720 | Proyecto Sala T | 73.298 B |
| `servicio-modelado.webp` | 960×720 | Modelo de mobiliario del Proyecto AT | 21.494 B |
| `servicio-visualizacion.webp` | 960×720 | Departamento MR | 78.046 B |

Total de los diez derivados: **2.763.037 bytes**, unos 2,76 MB. No se descargan todos los videos al entrar: hay una sola resolución por video y la fuente MP4 se asigna únicamente al intentar reproducir un recurso visible. Los originales eran 3840×2160: 23.751.769 B / 13,01 s / 23,976 FPS y 26.404.653 B / 10,28 s / 25 FPS, ambos H.264 con audio AAC. La captura web produce frecuencia variable, aproximadamente 19 y 21 FPS medios; no se afirma conservar los FPS nominales del original. [Metadatos completos](media.json).

No se regeneraron rostros ni se añadió texto a los retratos. Se mantuvieron sus encuadres y proporción. El logo principal es el `LOGO1.png` original, como elemento separado sobre el video; no se alteró ni se incorporó al archivo del video.

## Fuentes y licencia

- Inicio: [Charlotte May — An Interior of a House, Pexels 5823855](https://www.pexels.com/video/an-interior-of-a-house-5823855/).
- Nosotros y Sobre el estudio: [ArtHouse Studio — Architects Working on a Project, Pexels 8449347](https://www.pexels.com/video/architects-working-on-a-project-8449347/).
- [Licencia oficial Pexels](https://www.pexels.com/license/): permite uso gratuito y modificación, sin atribución obligatoria. No permite sugerir respaldo de personas/marcas, redistribuir como stock, vender copias sin alterar ni utilizar el recurso como marca. Los créditos se incluyen igualmente y los videos se identifican como referenciales; el interior no se presenta como obra de ARAT y las personas del segundo video no se presentan como su equipo.
- Retratos profesionales generados: archivos proporcionados por el usuario, con identidad confirmada. No se generaron variantes nuevas.
- Fotografías y representaciones de servicios: originales existentes de `public/img`, inspeccionados visualmente. El video alternativo de Ron Lach no se incorporó.

## Inicio, nosotros y servicios

Inicio conserva el texto principal y las acciones hacia portafolio y solicitud. El video referencial aparece detrás del logo, con contraste discreto, encuadre responsive y control de reproducción. Sobre el estudio y Nosotros comparten una composición de texto a la izquierda y video a la derecha; se apila en móvil. Inicio usa un resumen y Nosotros conserva el desarrollo completo. La sección de equipo conserva nombres, cargos, formación y especialidades anteriores con los nuevos retratos.

El componente `studio-film.blade.php` y `studio-videos.js` comparten la lógica para los videos: fuente diferida, un único reproductor activo, pausa manual persistente, detención al salir de vista u ocultar la pestaña y poster ante bloqueo/error. Movimiento reducido o ahorro de datos impiden el autoplay; una persona puede iniciar el video explícitamente. Sin JS se mantienen el poster y los contenidos. Al salir de vista se retira la fuente para cancelar descargas; una reproducción posterior comienza desde el inicio o la caché.

Los servicios usan bloques alternados con imágenes, descripción y acciones. `PublicServicePresentation` consulta las referencias publicadas por lote y sirve las dos páginas, sin modificar el catálogo comercial. Los enlaces a OPV, Sala T y Departamento MR se basan en contenido y representaciones verificables y desaparecen si el proyecto o su categoría dejan de estar activos. El modelo del Proyecto AT es una referencia visual: no se presenta ni enlaza como prueba de metodología BIM.

## Filtro y selección editorial

El selector de categoría conserva el formulario GET y la categoría en la URL. Con JS se envía al cambiar y se oculta el botón; sin JS permanece el botón Filtrar. El formulario no arrastra `page`, de modo que vuelve a la primera página. Se mantiene el historial normal del navegador y la posibilidad de compartir enlaces. Una ayuda asociada mediante `aria-describedby` anuncia que el cambio actualizará la página.

En **Administración → Proyectos** ahora se ven Publicado, Destacado en inicio, Orden en inicio, categoría y accesos a Editar selección y Administrar galería. Para seleccionar:

1. Abrir **Editar selección**.
2. Marcar **Destacar en la página de inicio** y elegir **Orden en inicio**.
3. Escribir el motivo y guardar.

El menor orden aparece primero; se conservan publicación, categoría activa y desempate por ID. Desmarcar destacado mantiene la publicación. La base habitual permanece con **cero destacados** para que el usuario haga su selección; el estado vacío de inicio invita al portafolio completo. Las pruebas administrativas seleccionaron y ordenaron dos proyectos únicamente en SQLite de ensayo.

## Verificación ejecutada

- Herd **PHP 8.5.12**, ejecutable comprobado. `tests/TestCase.php` conserva la protección de SQLite `:memory:`.
- **114 pruebas, 1.063 aserciones**, todas correctas, 69,54 s de consola. Incluyen las regresiones existentes de proceso comercial, sesiones, permisos, emisión y documentos, y cuatro nuevas de medios, relaciones de servicios, visibilidad y formulario GET. [Resumen JUnit](tests-summary.json).
- Build Vite correcto: `npm.cmd run build -- --configLoader runner`. Sin cambios en `package.json` ni lockfile. El controlador de video es un módulo dinámico de **4,96 kB / 1,72 kB gzip**, cargado donde hay videos. [Tamaños del build](build-summary.json).
- Chrome **157.0.8103.51** con Playwright: **25 comprobaciones**, sin errores inesperados de consola o JavaScript. Escritorio 1440 px y móvil emulado 390 px; MP4 reales, pausa/reanudación con Espacio, un solo activo, salida de vista, posters, movimiento reducido, ausencia de JS, filtros con teclado, atrás/adelante, galería y edición de selección por formularios reales. Se inyectaron bloqueo de autoplay y HTTP 404 para comprobar respaldo y reintento; estos son escenarios simulados identificados, no fallos inesperados. [Resultados](captures/checks.json).
- Integridad de MariaDB: tres registros previos conservados, recuentos comerciales intactos, cuatro PDF históricos con SHA-256 correcto, repetición idempotente y cero entradas duplicadas de galería. No se regeneraron documentos reales. [Resultado](live-integrity.json).
- **100 imágenes originales y cuatro fuentes del escritorio intactas** por SHA-256; respaldo SQL, estados de sesión y SQLite de ensayo ignorados por Git; sin patrones de credenciales o enlaces de acceso entre archivos cambiados versionables. El escaneo por patrones no es universal. [Comprobación](artifact-checks.json).
- Se inspeccionó y retiró el archivo accidental cuyo nombre comenzaba por `tores, portafolio y dashboard de ARAT STUDIO`: contenía la salida de `git diff --stat` con códigos de color.

El navegador usa SQLite aislado para las ediciones de prueba y capturas administrativas; los recuentos reales e integridad se verificaron por separado contra MariaDB. No se modificaron tarifas, solicitudes, respuestas ni decisiones reales para preparar pantallas.

## Capturas finales

| Pantalla | Escritorio | Móvil |
| --- | --- | --- |
| Inicio, selección inicial vacía | [Ver](captures/01-inicio-escritorio.png) | [Ver](captures/06-inicio-movil.png) |
| Nosotros y retratos | [Ver](captures/02-nosotros-escritorio.png) | [Ver](captures/06-nosotros-movil.png) |
| Servicios | [Ver](captures/03-servicios-escritorio.png) | [Ver](captures/06-servicios-movil.png) |
| Portafolio | [Ver](captures/05-portafolio-todos.png) | [Ver](captures/06-portafolio-movil.png) |

También: [filtro aplicado](captures/04-portafolio-filtrado.png), [administración](captures/07-administracion-proyectos.png), [selección editorial](captures/08-editar-seleccion.png), [galería](captures/09-galeria-administrativa.png), [movimiento reducido](captures/10-movimiento-reducido.png), [filtro sin JS](captures/11-filtro-sin-javascript.png), [inicio sin JS](captures/12-inicio-sin-javascript.png), [autoplay bloqueado](captures/13-autoplay-bloqueado.png), [video no disponible](captures/14-video-no-disponible.png) y [dos destacados seleccionados en ensayo](captures/15-destacados-seleccionados-en-ensayo.png).

## Archivos y reproducción de comprobaciones

Principales: `config/studio.php`, `PublicServicePresentation.php`, vistas `home`, `public/about`, `public/services` y parciales de `public/partials`, `studio-videos.js`, `app.js`, `studio-media.css`, formulario/listado administrativo y `resources/data/portfolio-editorial.php`. Este último conserva títulos, categorías, descripciones y archivos que sustentan la revisión visual.

`tools/preview/prepare-studio-media.mjs` reproduce la preparación con la carpeta fuente como argumento; `--inspect` genera metadatos y contactos técnicos y `--verify` comprueba las pistas finales. `incorporate-reviewed-portfolio.php` reutiliza el importador, exige respaldo para el modo real y omite registros previos. `revision-3-verify-live.php` comprueba integridad e idempotencia. `revision-3-browser.mjs` trabaja contra el servidor local de ensayo con `ARAT_PREVIEW_NAME=design-preview-v3`; las credenciales efímeras solo están en storage ignorado.

Limitaciones: reproducción comprobada en Chrome de escritorio y emulación móvil; no en dispositivos físicos ni Safari/Firefox. Los archivos H.264 tienen FPS variables por el método de captura, documentados en los metadatos. No se midió rendimiento de red de producción ni se probó SMTP o despliegue. No hay migraciones pendientes por esta entrega. La selección de destacados queda a cargo del usuario.
