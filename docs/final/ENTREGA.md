# Entrega local de ARAT STUDIO

Revalidación posterior al apagado: [25 de noviembre de 2026](REANUDACION-20261125.md).
La evidencia de esta entrega del día 24 se conserva como historial.

24 de noviembre de 2026 · America/Lima. Se conservó `main` y el trabajo local
posterior a `0583fa5`, incluidos fotografías, retratos, videos y `revision-arat.diff`.
No se cambió de rama ni se hicieron commit, push o despliegue. No se encontró
`AGENTS.md` en el proyecto ni en los directorios ascendentes revisados; se siguieron
el encargo y la documentación local.

## Cambios funcionales y visuales

- «Consultar mi expediente» aparece directamente en la navegación. «Acceso al
  equipo» queda en el footer; las sesiones internas pueden volver al panel por rol.
- Se retiraron los créditos y explicaciones de recursos de las pantallas públicas.
  Los videos conservan poster, pausa accesible, fuente diferida, silencio,
  detención fuera de vista y respaldo sin JS. Los textos alternativos identifican
  escenas referenciales sin atribuirlas a ARAT. Fuentes y licencias permanecen en
  [la documentación de medios](../revision-3/README.md#fuentes-y-licencia).
- Inicio resume servicios y equipo; Nosotros conserva el desarrollo profesional.
  Servicios mantiene imágenes verificadas, acciones por servicio, tres pasos del
  proceso comercial y preguntas sobre estimación, cotización y seguimiento.
- El filtro de portafolio conserva GET, URL, historial, paginación y respaldo sin
  JS. Los controles Publicado/Destacado/Orden siguen visibles en administración.
  Detalle añade proyectos relacionados publicados de la misma categoría.
- Se centralizó la visibilidad pública y se excluyeron fichas vacías. El inventario
  confirma las 13 carpetas incorporadas, 13 proyectos publicados completos y 99
  imágenes de galería. La selección real de destacados sigue vacía, sin imponerla.
- Se corrigieron el orden por fecha de solicitudes e historial y la paginación en
  español. El seeder de tarifas ahora enlaza las versiones con sus grupos: la
  instalación nueva puede calcular los tres niveles LOD y repetir el seeder sin
  sobrescribir tarifas existentes.
- El selector de PHP prepara la terminal para PHP 8.5 y Composer. El INI del
  proyecto evita que un aviso de PHP impida responder 413. La prueba HTTP real de
  65 MiB pasó; no se editaron los INI instalados.

## Simplificación

`Project::published()` reúne el criterio que antes se repetía en inicio,
portafolio y referencias de servicios. Se retiraron la consulta de tarifas no
utilizada del cotizador, ramas inalcanzables de `retireCover`, una asignación
duplicada de cierre y un grupo duplicado de rutas con los mismos permisos.

Se eliminaron estilos sin referencias (`admin-form__field`,
`admin-project-cover--large`, `dashboard-card`, captions y numeración de servicios).
La portada usa un resumen en lugar de repetir los bloques completos de Servicios.
No se comprimió código ni se añadieron capas para las reglas comerciales.

Se retiró **laravel/pail 1.2.7** y el script genérico `setup`, que regeneraba la
clave y migraba sin distinguir una instalación existente. `composer dev` utiliza
servidor, cola, scheduler y Vite. El lockfile registra una eliminación y ninguna
actualización de paquetes. Se mantuvieron Sharp, Playwright, Chart.js y las demás
dependencias con referencias vigentes. El helper global `FixtureAccess` de la
prueba MariaDB pasó a una clase anónima para evitar el aviso PSR-4 de Composer.

Se consultaron [Bootstrap 5.3](https://getbootstrap.com/docs/5.3/),
[Tabler](https://docs.tabler.io/), [Motion](https://github.com/motiondivision/motion)
y [Bootstrap Icons](https://github.com/twbs/icons). Bootstrap/Tabler conservan sus
licencias MIT y se cargan por separado. Motion e Icons también ofrecen licencia
MIT; no se instalaron: CSS e IntersectionObserver cubren el movimiento requerido.
El [cargador runner de Vite 7](https://v7.vite.dev/config/) resuelve el fallo observado
del cargador predeterminado al leer directorios restringidos de Windows.

## Datos y conservación

Se confirmó la conexión efectiva **mysql / arat_studios**. Antes de escribir se
generaron inventario, SQL completo y ZIP privado, y se restauraron **28 tablas y
604 archivos**, comparando su contenido y SHA-256. La depuración y el lote se
ejecutaron primero en `arat_restore_final_20261124_222224`.

| Registro | Antes | Después |
| --- | ---: | ---: |
| Cuentas | 9 | 4 |
| Solicitudes | 9 | 16 |
| Cotizaciones | 5 | 9 |
| Revisiones | 9 | 17 |
| Historial comercial | 33 | 67 |
| Archivos de solicitudes | 8 | 0 |
| Auditoría | 67 | 148 |
| Proyectos / imágenes de galería | 13 / 99 | 13 / 99 |
| Categorías / servicios / tarifas | 5 / 12 / 3 | 5 / 12 / 3 |

Se retiraron ocho solicitudes explícitas de verificación/prueba con dominios
reservados y cinco cuentas ficticias sin rol ni dependencias conservadas. La
solicitud de origen dudoso y su cotización permanecen intactas. Los 67 eventos
previos de auditoría se conservaron íntegros; no se retiraron triggers ni se
desactivaron claves foráneas para depurar. Resultado: **cero referencias huérfanas**.

Las cuentas **ROL4N, Roque, Jh y Guyin**, IDs **1, 2, 8 y 9**, conservan todos sus
campos originales, incluidos correo, hash de contraseña, rol y estado. Los
catálogos anteriores coinciden con el respaldo. No se modificaron imágenes:
**112 recursos visuales intactos**, incluidos los retratos aprobados; también se
conservó el archivo de estructura `.gitkeep`. Se identificó
un duplicado binario entre `Centro de Int. Residencial/3.webp` y `4.webp`; se
conservaron ambos originales y la galería ya evita duplicarlos por SHA-256.
`4.webp` es el único original de proyecto sin referencia en la base; se conserva
como alias duplicado recuperable y no se elimina material aprobado.

Los **12 archivos retirados** se trasladaron a cuarentena privada tras comprobar
que ningún registro conservado los utilizaba. Se verificaron **ocho PDFs** actuales
con cabecera válida y sus hashes disponibles. [Inventario y recuentos sin secretos](data-verification.json).

Respaldo y recibos privados, ignorados por Git:

```text
storage/app/private/final-maintenance/arat-studios.sql
storage/app/private/final-maintenance/related-files.zip
storage/app/private/final-maintenance/backup-receipt.json
storage/app/private/final-maintenance/restore-files/
storage/app/private/final-maintenance/quarantine/
```

El lote **arat-2026-v1**, semilla **20261124**, fecha de referencia **2026-11-24**,
contiene 15 solicitudes creadas con Factory/Faker y servicios comerciales reales:
pendiente, asignada, evaluación, observada, corregida, borrador, emitida, versión
sustituida, vencida, aceptada y cerrada, rechazada, cancelada, propuesta rechazada,
cierre sin acuerdo y una segunda pendiente. El lote usa example.com y teléfono
no enrutable, con avisos simulados en `array`. Su repetición no creó duplicados ni
alteró estados en la restauración ni en la regresión automatizada. La verificación
final de la base habitual fue exclusivamente de lectura.

## Comprobaciones ejecutadas

| Comprobación | Resultado |
| --- | --- |
| Referencia funcional inicial | 114 pruebas, 1.063 aserciones |
| Suite final, PHP 8.5.12 y SQLite en memoria | **118 pruebas, 1.251 aserciones; todas correctas** |
| Concurrencia MariaDB aislada | **10 comprobaciones correctas** |
| Videos, filtros, galería, teclado, sin JS y movimiento reducido | **25 comprobaciones Chrome; cero errores inesperados** |
| Flujo completo por formularios reales | Corrección verificada, PDF, aceptación/cierre y rechazo del reenvío opuesto correctos |
| Escritorio y móvil | 18 capturas antes y 18 después; sin imágenes rotas ni desbordamiento de página |
| Carga HTTP de 65 MiB | **413 y mensaje controlado** con INI local |
| Frontend | `npm.cmd run build` correcto |
| PHP y vistas | 180 archivos iniciales sin errores; helpers posteriores comprobados aparte; Blade compilado y limpiado |
| Composer | JSON válido y requisitos instalados correctos con PHP 8.5 |
| Migraciones | 17 aplicadas, ninguna pendiente |

La concurrencia cubrió toma y borrador simultáneos, envíos idénticos/diferentes,
aceptación frente a emisión, cambio de tarifa y desactivación frente a
asignación/toma. La suite conserva casos de permisos cruzados, sesiones y grants
vencidos/revocados, cuentas desactivadas, confirmación de contraseña y correo único,
archivos inválidos/privados, cálculos y redondeos, versiones congeladas, cancelación,
rechazo, vencimiento y reintentos fallidos de PDF/notificaciones.

Se midieron contrastes de los pares principales: texto 13,32:1, secundario sobre
blanco 5,86:1, sobre neutro 5,37:1 y botón principal 9,60:1. El foco, menú, galería y
formularios se operaron con teclado. Se comprobó reflujo a 640 px, equivalente al
espacio disponible al ampliar una ventana de 1280 px al 200%; no se automatizó el
zoom nativo del navegador. No se afirma una certificación integral de accesibilidad.
[Resultados reproducibles](checks-summary.json) · [Carga excesiva](upload-limit.json).

## Capturas y recorrido

[Comparación completa antes/después](index.html),
[expediente del cliente](workflow/05-expediente-cliente.png),
[móvil](workflow/06-expediente-cliente-movil.png),
[aceptación y cierre](workflow/07-aceptacion-y-cierre.png),
[versión anterior](workflow/09-version-anterior.png).

Las capturas usan SQLite, cuentas efímeras y fotografías reales del portafolio.
Las altas y decisiones del navegador no se hicieron en `arat_studios`.
El «antes» refleja el código local inicial con fixtures previos: los dos destacados
visibles allí pertenecen al ensayo y no a una selección aplicada a MariaDB.

1. Arrancar MariaDB, servidor, cola y scheduler según el [README](../../README.md#arrancar-y-detener).
2. Recorrer inicio, Nosotros, Servicios, filtro de Portafolio y detalle/galería.
3. Calcular una estimación y distinguirla de la cotización oficial.
4. Entrar por «Acceso al equipo» con una cuenta existente. En Gestión, los correos
   del lote permiten identificar cada escenario sin nombres técnicos visibles.
5. Abrir una observada para mostrar corrección e historial; una con borrador para
   emitir; una emitida para mostrar PDF y vigencia; y las de sustitución/vencimiento.
6. Usar el acceso local temporal en otra sesión para mostrar el expediente del
   cliente. Mostrar la aceptada como expediente cerrado; crear otro recorrido solo
   si se desea modificar deliberadamente un escenario de demostración.

Los procesos temporales quedaron detenidos; [comprobación de cierre](teardown.json).
El arranque y parada habitual
están documentados en README. El helper MariaDB evita el problema de índices de
replicación de XAMPP sin repararlos ni borrarlos; el arranque directo de XAMPP puede
seguir requiriendo una intervención específica fuera del proyecto. No se probaron
SMTP real, despliegue, Safari/Firefox, dispositivos físicos ni lector de pantalla.
No queda un bloqueo del flujo comercial local con los comandos entregados.
