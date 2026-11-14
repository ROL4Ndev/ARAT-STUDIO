# Contraste con PCAL-S13 y portada real — 13/11/2026, Lima

**Resultado actual:** el recorrido de la segunda presentación está listo para
ensayarse en el entorno local comprobado. La imagen de Departamento MR se
verificó en el servidor de trabajo, además del ensayo aislado. El documento
académico para Canvas aún debe armarse con la portada del grupo y ser entregado
por cada integrante; el checklist está en
[CHECKLIST_CAPTURAS_CANVAS.md](CHECKLIST_CAPTURAS_CANVAS.md).

## Fuente leída y alcance de la rúbrica

Se leyeron íntegramente las **siete páginas** de
`C:\Users\rolan\OneDrive\Desktop\PCAL-S13-PVERGEL-2026-02.pdf`, incluido el
cuadro de rúbrica de la página 6 y las condiciones/descuentos de la página 7.
SHA-256: `b6880d2376edfaf73205dc09738476d9d73ff33e2ef602610c24c27f13bb0c55`.
El archivo corresponde a la **segunda presentación**; no contiene la rúbrica
de la primera. Se revisaron los lineamientos comunes de su sección I sin
inventar criterios de un documento ausente. No se asigna una nota automática:
la exposición, el dominio del código y la participación son evaluaciones humanas.

## MR: fallo reproducido y corrección aplicada al registro autorizado

Se confirmó el servidor abierto en `http://127.0.0.1:8000`, PHP de Herd 8.5.12,
ejecutando `artisan serve` desde este checkout. La configuración local no está
cacheada: conexión `mysql`, host `127.0.0.1`, base `arat_studios`.
La identidad comprobada fue el único registro con slug `departamento-mr`, título
Departamento MR y categoría Remodelación; su clave interna es 3 (solo se
documenta aquí para identificar la intervención, no se muestra en el CRUD).

**Causa exacta:** `public/storage` era una junction con destino
`C:\Users\rolan\OneDrive\Desktop\PROYECTO PHP\arat-studios\storage\app\public`,
perteneciente a otra carpeta. El registro apuntaba a una subida del disco public
de **ARAT-STUDIO**, por lo que el enlace no alcanzaba el archivo. Antes de
corregir, el detalle real devolvió 200 pero su imagen devolvió **404, text/html**,
con `naturalWidth=0` y `naturalHeight=0`. En cambio, el original solicitado ya
devolvía 200, `image/webp`. El ajuste previo de `object-fit` mejoraba el encuadre
pero no resolvía esta falla de carga; aquella prueba aislada servía las imágenes
por una ruta de ensayo y no detectó la junction del servidor real.

Se corrigió únicamente el enlace de directorio, sin borrar su destino anterior,
para apuntar a `ARAT-STUDIO\storage\app\public`. También se configuró el archivo
expresamente solicitado en el registro existente mediante
`CatalogWorkflowService::save`, con autorización, control de versión, auditoría
y conservación privada. Se agregó al mismo servicio una referencia opcional
a imagen existente bajo `public/img`, con validación de ruta, tipo y tamaño;
las subidas mantienen su mecanismo y su resolución por el disco public.

| Dato | Valor comprobado |
|---|---|
| Archivo original | `public/img/Departamento MR/1.webp`, respetando nombre y espacios |
| `cover_image` anterior | `project-covers/6dFlbBWTaec4lcnucvXfAiCFQCSYZUlTpL5mhSp2.webp` |
| `cover_image` final | `img/Departamento MR/1.webp` |
| URL pública final | `http://127.0.0.1:8000/img/Departamento%20MR/1.webp` |
| Historial | De dos a tres entradas privadas; copias y archivos retirados verificados por hash |
| Cambios del registro | Solo portada, fecha de actualización y versión de edición |

Antes de intervenir se guardaron ficha, historial y bytes de la portada anterior
en almacenamiento privado, bajo `backups/guide-cover-20261113_234155/`.
El motivo de auditoría indica expresamente que es una corrección técnica
autorizada y ejecutada por Codex; se usó la cuenta administrativa existente ROL4N.
No se creó otro proyecto ni se modificaron las otras dos fichas.

**Confirmación visual real:** doce combinaciones de Home, portafolio y detalle
a 320, 390, 768 y 1440 px dieron URL final correcta, **200/image/webp**, imagen
decodificada **1400 × 1120**, caja visible con dimensiones positivas y sin otro
elemento cubriendo su centro. Se conservaron `contain`, proporciones naturales
y ausencia de zoom al hover. Se inspeccionaron las capturas de detalle a 390 y
1440 px. No se agregó cache busting: la causa comprobada era el enlace de disco.

Se comprobó también una subida ficticia del ensayo, desde el propio servidor
8000, con 200/image/png y dimensiones naturales positivas. Así se verificaron
ambas resoluciones: los assets `img/` no reciben prefijo `/storage/`, y las
subidas continúan usando la junction real corregida.

## Matriz de cumplimiento de la guía

“Probado” indica comprobación ejecutada; no equivale a certificación productiva.
Las referencias II/III/IV corresponden a las secciones del PDF adjunto.

| Requisito | Implementación | Evidencia | Estado | Corrección o pendiente |
|---|---|---|---|---|
| I.1, I.4: aplicación PHP/Laravel 13 | Laravel, Blade, Bootstrap, MariaDB | `artisan --version`: **13.39.0**; composer y servidor real | Probado | Ninguno en este alcance |
| I.3: grupo de 2–4 integrantes | Dato académico, no una función del sistema | No se proporcionó nómina ni número de grupo | Pendiente | Completar portada del documento |
| II.1.1–1.3: Home completo, diseño y destinos | Estudio, servicios, equipo, proyectos, contacto y acciones públicas | Navegador, imágenes decodificadas y comprobación de destinos/anclas locales | Probado | Enlaces externos conservan sus destinos; no se enviaron mensajes |
| II.2.1–2.3: informativas accesibles y diseño común | Nosotros, Servicios y Contacto con layout compartido; Estudio en el menú | HTTP 200, cabecera/footer únicos, capturas y cuatro anchos | Probado | Corregido el espacio superior bajo cabecera fija |
| I.5.3, II.3.1–3.3: usuarios y perfiles según proyecto | Cuentas internas, login/logout, perfil, middleware y Policies | Suite de autenticación; login admin/gestor, perfil guardado y cierre real en MariaDB aislada | Probado | Registro interno existente por comando; no se añadió registro público |
| II.3.1–3.3: CRUD restringidos y menús por rol | Administración solo admin; gestión admin/gestor; cliente temporal separado | Gestor 403 en cuatro CRUD; visitante redirigido; Mi cuenta según rol; expediente ajeno denegado | Probado | Ninguno |
| I.5.4, II.4.1–4.2: dos CRUD principales, ajenos a usuarios/roles | PROYECTOS y TARIFAS para exponer; categorías y servicios conservados | Altas, consulta, edición y estados de los cuatro catálogos en navegador | Probado | Ninguno; cuatro catálogos disponibles |
| II.4.3: DELETE opcional e integridad | Desactivación/reactivación con motivo e historial | Misma ficha tras cambios de estado; categoría con proyectos activos bloqueada; DELETE 405 en suite | Probado | No se requiere implementar borrado |
| II.4.4–4.5: IDs y relaciones | Formularios sin ID editable; listados y detalle con nombres; auditoría filtrada | Código de formularios, suite y texto visible de auditoría sin JSON ni rutas privadas | Probado | IDs solo en rutas, opciones y campos ocultos necesarios |
| Proyectos: portada y publicación | Portada original MR y subidas del disco public; visibilidad por proyecto/categoría activos | Servidor 8000, portafolio aislado con subida, baja/alta y portada decodificada | Probado | Corregidos registro MR y junction |
| Tarifas: edición publicada conserva vigente | Nueva versión en borrador, activación explícita | V1 siguió activa al crear V2 borrador con nuevo precio; precio de V1 intacto | Probado | Ninguno |
| II.5.1: mayor parte del proceso funcional | Cotizador, solicitud, asignación, evaluación, observación, corrección, aprobación, v1/v2, PDF, aceptación y cierre | Flujo completo HTTP/navegador con persistencia MariaDB aislada | Probado | Sin HTML simulado como reemplazo de operaciones del flujo |
| II.5.2: escenarios de distintos perfiles | Administrador asigna; gestor evalúa y cotiza; cliente verificado corrige y acepta | Sesiones separadas, permisos positivos y negativos, `GUIA_EXPOSICION.md` | Probado | Repartir la exposición entre integrantes |
| Proceso: duplicados, versiones y privacidad | Intenciones durables, bloqueos, versiones y acceso verificado | Reenvío idéntico sin alta adicional, contenido distinto 409, v1 vigente durante v2, fallo/reintento misma versión, vencida/sustituida 409 | Probado | Ninguno en los escenarios ensayados |
| Interfaz: teclado, móvil, tipos de letra y errores | Menú único, details/summary, foco/Escape, tablas contenidas, feedback por campo | **92 combinaciones** a cuatro anchos; menú sin JS; Kodchasan cargada y Arial computada; errores reales y perfil guardado | Probado | Zoom nativo 200 % pendiente |
| Documento, pág. 4: Word/PDF, portada, capturas y repositorio | Checklist ordenado y capturas reales disponibles localmente | `CHECKLIST_CAPTURAS_CANVAS.md`; remoto Git consultado | Parcial | Armar documento final y entrega individual en Canvas; cambios locales sin publicar por instrucción |
| III y IV: 10–15 min, aplicación/código y dominio individual | Guion ajustado a 12 min con preguntas; archivos de explicación identificados | `GUIA_EXPOSICION.md`; lectura de rúbrica y condiciones | Documentado | Ensayar tiempos y respuestas con todo el grupo; no se garantiza calificación |
| I.5.6: proceso secundario | La guía lo reserva para una etapa posterior | Página 2 del PDF | No exigible aquí | No se añadió otro módulo |

La rúbrica contiene cuatro criterios de hasta cinco puntos: Home/usuarios,
CRUD, proceso principal y exposición individual. La guía contempla descuento
por errores no manejados y por no entregar el documento; no se presentan los
resultados técnicos como una nota ni como evidencia de participación de alumnos.

## Pruebas y conservación de este corte

- **88 pruebas, 774 aserciones correctas**, SQLite en memoria. Se añadieron
  pruebas de la portada existente: mismo proyecto, historial privado, original
  intacto, Home/portafolio/detalle, visibilidad, subidas y rechazo de rutas externas.
  Después del ajuste de las informativas se repitieron las seis pruebas
  afectadas (95 aserciones). Compilación Vite, sintaxis de todos los JS públicos,
  sintaxis/estilo del PHP nuevo, `view:cache` y `git diff --check`: correctos.
- Ensayo nuevo en **MariaDB 10.4.32**, `arat_guide_20261113_234522`, servidor
  8141, cuentas ficticias deshabilitadas al finalizar. Se copiaron solo fichas
  públicas; no expedientes ni contactos reales. Las subidas usaron
  `storage/app/public/isolated/arat_guide_20261113_234522`, servidas por la junction
  real, sin una ruta especial que ocultara su estado.
- Flujo completo hasta cierre. V2 falló en el worker de prueba, conservó V1,
  y el reintento autorizado emitió esa misma versión. Solo dos cotizaciones
  del expediente principal; propuestas vencida y sustituida no aceptables.
  PDF v1 de una página y v2 de dos, descargados, abiertos y renderizados.
- **QUEUE_CONNECTION=database**: recuperación y worker reales, sin `sync` ni
  `--inline`; scheduler ejecutó recuperación y vencimiento. Los avisos revisados
  quedaron `simulated`, nunca declarados entregados. Se vació la cola pendiente
  antes de comprobar sus estados finales; un aviso pendiente antes del worker
  no se consideró un fallo ni se ocultó cambiando la configuración.
- 92 mediciones de páginas/formularios/tablas, más las doce de MR en el
  servidor 8000. Tras ajustar las informativas se repitieron sus doce posiciones
  y sus capturas, quedando por debajo de la cabecera en los cuatro anchos.
  Teclado, menú abierto, Escape y foco pasaron a 320/390/768/1440; navegación
  sin JS a 320 px. Cinco auditorías axe sin infracciones detectadas. Consola
  y recursos de las mediciones finales sin nuevos errores; 403/409 de pruebas
  negativas y el 404 inicial de MR se identifican como tales.
- Después de la corrección autorizada se compararon las **27 tablas** de
  desarrollo: solo `sessions` varió durante la revisión. Las otras 26 mantuvieron
  sus hashes. Las otras dos fichas de proyectos y los diez originales MR
  permanecen iguales; las tres copias históricas privadas están verificadas.
- Sin cambios de `.env`, credenciales, XAMPP ni dependencias; sin seeders,
  fresh/refresh/wipe, git add, commits ni push. Se conservan artefactos anteriores.

Evidencia ignorada en `storage/framework/testing/`: `guide-mr-before.json`,
`guide-real-image-results.json`, `guide-upload-runtime.json`,
`guide-browser-results.json`, `guide-info-position.json`,
`guide-menu-results.json`, `guide-conservation.json`, capturas `guide-*.png`
y PDF `guide-v1.pdf`/`guide-v2.pdf`. Hay capturas auténticas de alta/edición,
listados, páginas informativas y pantallas del flujo para seleccionar en Canvas.
Las capturas de fallo se conservan para diagnóstico y no se confunden con las
capturas de resultado final.

Pendientes: documento Word/PDF de Canvas con número de grupo y nombres, entrega
individual, ensayo humano de 10–15 minutos y preguntas; zoom **nativo** al 200 %
(la automatización no permite controlarlo con fiabilidad), SMTP real, producción
y navegadores/dispositivos adicionales. El pendiente histórico de 413 para
cargas superiores a 64 MiB no se volvió a ensayar. La rúbrica de primera
presentación no está en el PDF recibido. El servidor de trabajo 8000 se conserva;
solo el servidor temporal de este corte se detiene al terminar.

---

# Antecedente aislado anterior — 13/11/2026, Lima

**Nota de alcance:** las comprobaciones siguientes son históricas. La causa y
la solución definitivas del fallo de carga de MR están en el corte superior;
este antecedente no probaba la junction del servidor de trabajo.

**Resultado:** el recorrido descrito en la solicitud está listo para ensayarse
en el entorno local comprobado. Esta sección corresponde a las correcciones
actuales; los cortes anteriores se conservan debajo como antecedentes.
Por indicación del usuario, el contraste documental con
`PCAL-S13-PVERGEL-2026-02` queda para cuando facilite ese documento. No se afirma
haberlo leído ni validado sus criterios adicionales.

## Departamento MR: causa comprobada y corrección

Se consultó el registro real de `arat_studios` en una transacción de solo lectura.
La portada utilizada es
`project-covers/6dFlbBWTaec4lcnucvXfAiCFQCSYZUlTpL5mhSp2.webp`, servida por
el accessor `cover_image_url` desde `/storage/project-covers/`.
Su SHA-256 coincide exactamente con `public/img/Departamento MR/1.webp`:

`7f626251f7e66272cf4054cb385ab57c37baa171b5807d0c30205d707d759142`

Se compararon visualmente las diez imágenes disponibles. `1.webp` muestra la
cocina; `2.webp`, planta y corte; `3–4`, otras vistas de cocina; `5–10`, comedor,
sala y vistas generales. Hay varias opciones válidas y se conservó la actual,
tal como se pidió. No se modificó el registro ni se sustituyeron fotografías.

La causa comprobada era la presentación: imagen de 1400 × 1120 (5:4) dentro
de tarjetas 4:3 con `object-fit: cover`, que cortaba aproximadamente 6,25 % de
su altura, más `scale(1.05)` al pasar el cursor. No se encontró una ruta rota,
una imagen distinta en el accessor ni evidencia de que la caché fuera la causa.
Las tarjetas compartidas por Home y portafolio usan ahora `contain`, centrado
y sin zoom; el detalle mantiene ancho adaptable y altura natural. La consulta
administrativa también conserva la proporción.

Chrome decodificó la portada real en Home, portafolio y detalle en los cuatro
anchos solicitados: doce mediciones con dimensiones naturales 1400 × 1120,
`object-fit: contain` y sin transformación al hover. Se inspeccionaron las
capturas, además del estado HTTP. Los originales y su historial se conservaron.

## Navegación final

- Inicio, **Estudio** (Nosotros, Servicios, Contacto), Portafolio, Cotizador,
  **Solicitar proyecto** como acción principal y **Acceso** (Mi expediente,
  Ingresar) para visitantes.
- En sesión interna, **Mi cuenta** agrupa Mi perfil, los paneles permitidos por
  rol y Cerrar sesión. Administración aparece solo para admin; Gestión, para
  admin y gestor. El acceso temporal del cliente sigue separado de la sesión
  interna; no se modificaron middleware, políticas ni reglas de autenticación.
- Se eliminó la barra adicional de secciones del Home y la duplicación del
  árbol de enlaces para escritorio/móvil. Se mantienen las páginas informativas
  y el footer común, con enlaces operativos.
- Submenús nativos `details/summary`, utilizables con clic, Enter y espacio.
  El control móvil informa `aria-expanded`; Escape cierra primero el submenú
  y devuelve foco, y luego cierra el menú. Foco visible comprobado. Sin JS,
  la navegación móvil queda expandida y desplazable y los submenús funcionan.

También se retiró el ID autogenerado del trabajo técnico del detalle de
cotización; el aviso de corrección identifica el expediente por su código
público. Se conservaron los cambios previos de auditoría legible y páginas
informativas. Las relaciones de proyectos y tarifas muestran categoría/familia,
y la auditoría utiliza etiquetas permitidas, responsables y motivos, sin
snapshots JSON, IDs de relaciones ni rutas privadas.

## Ensayos realizados en este corte

MariaDB **10.4.32**, base nueva `arat_presentation_20261113_225523`, servidor
temporal `127.0.0.1:8139`, discos y sesiones separados, cuentas ficticias y
correo `log`. Solo se copiaron las tres fichas públicas y la portada utilizada
para reproducir la presentación. No se copiaron expedientes ni contactos reales.
Las cuentas de navegador se deshabilitaron al terminar.

- PROYECTOS y TARIFAS: crear, listar/consultar, editar, desactivar y reactivar
  en navegador, conservando registros e historial. También se recorrieron los
  otros dos catálogos. La categoría con proyectos activos siguió bloqueando
  su desactivación; gestor recibió 403 en los cuatro CRUD.
- Cotizador: 80 m², LOD 300, PEN 10 800.00; solicitud con PDF, reenvío idéntico
  sin duplicación y contenido distinto con la misma clave rechazado con 409.
- Asignación, evaluación, observación pública, acceso del cliente mediante
  enlace producido por correo simulado, corrección conservando dos adjuntos,
  regreso a evaluación y aprobación técnica.
- V1 emitida, V2 en borrador conservando V1; fallo de PDF de V2 provocado solo
  en el worker de ensayo, V1 todavía vigente; reintento desde la acción
  autorizada, V2 emitida sin crear otra versión. Se mantuvieron exactamente
  dos cotizaciones y el PDF privado anterior siguió disponible al responsable.
- Propuestas sustituidas y vencidas por fecha rechazaron aceptación HTTP 409.
  La fecha vencida se preparó/restauró solo en datos ficticios aislados.
  Aceptación verificada de V2 y cierre del expediente completados.
- Gestor ajeno sin expediente ni adjunto; visitante sin PDF privado.
  Área de 501 m² sin importe automático, rechazo con motivo, servicio manual
  sin área/LOD y archivo mayor de 50 MiB retirado con mensaje correcto.
- Servidor, jobs y scheduler comprobados con **QUEUE_CONNECTION=database**.
  `commercial:recover` encoló trabajos y `queue:work --stop-when-empty`
  procesó la cola real; no se usó `--inline` ni se cambió a `sync` para el
  ensayo MariaDB. `schedule:run -v` ejecutó `commercial:recover` y
  `commercial:expire` correctamente. Las notificaciones comprobadas quedaron
  `simulated`, visibles como “Simulada (entorno local)”; no se entregó SMTP real.
- 84 combinaciones (21 páginas × 320/390/768/1440 px), incluyendo públicas,
  formularios, listados y detalles de proyectos/tarifas, perfil, expediente y
  cotizaciones: sin desbordamiento horizontal del documento. Las tablas
  conservan su contenedor de desplazamiento. Se comprobaron clic, teclado,
  Escape, foco, cambio de breakpoint y navegación sin JavaScript.
- Las cinco páginas/combinaciones auditadas con axe no informaron infracciones.
  No se presenta como certificación integral de accesibilidad. Red y consola
  de las comprobaciones visuales terminaron sin errores ni recursos 404 nuevos;
  los 403/409 de pruebas negativas fueron respuestas esperadas.

La suite de PHPUnit utiliza SQLite en memoria y su configuración previa de
pruebas; es evidencia distinta del ensayo de cola MariaDB:
**86 pruebas, 753 aserciones, correctas**. Se agregaron comprobaciones de
navegación única/roles, ocultación del ID técnico y código público del aviso.
La integración de concurrencia existente pasó sus **siete escenarios** en
`arat_concurrency_20261113_231151` (toma, borrador, dos reenvíos, ambos órdenes
aceptación/emisión y cambio tarifario).

`npm.cmd run build`, sintaxis de todos los JS públicos, sintaxis/estilo del PHP
modificado, `php artisan view:cache` y `git diff --check`: correctos.
El `php` del PATH apunta a XAMPP 8.2 y no satisface las dependencias instaladas;
las comprobaciones PHP usaron el ejecutable existente de **Herd 8.5.12**.
Para ensayar, iniciar servidor, worker y scheduler con ese PHP compatible.
No se modificaron PATH permanente, `.env`, credenciales ni dependencias.

## Conservación, evidencia y pendientes

Se compararon hashes de las 27 tablas de desarrollo antes/después: **26 sin
cambios**; `sessions` presentó variaciones durante la revisión, cuya causa
no se atribuye sin evidencia adicional. No cambiaron catálogos, expedientes,
cotizaciones, auditoría ni demás tablas de negocio. Las diez imágenes originales
de Departamento MR y la copia registrada mantuvieron sus hashes (11/11).
No se ejecutaron seeders, fresh, refresh, wipe ni SQL masivo sobre `arat_studios`.
No se borraron respaldos, originales ni datos históricos. Los entornos ficticios
se conservan para trazabilidad. No se hizo git add, commit ni push.

La evidencia local está ignorada en `storage/framework/testing/`:
`presentation-browser-results.json`, `presentation-conservation.json`,
`presentation-mr-*.png`, `presentation-v1.pdf`, `presentation-v2.pdf` y los
guiones `presentation-*.php/cjs`. Se conservaron los artefactos anteriores.

Pendientes concretos: contraste con PCAL-S13 cuando se facilite; **zoom nativo
al 200 %**, que no pudo controlarse de forma fiable con Playwright (no se
sustituyó por zoom CSS o densidad emulada); SMTP real y producción. Se mantiene
el pendiente histórico de respuesta 413 para cargas superiores a 64 MiB,
que no se volvió a ensayar en este corte. El servidor temporal se detuvo al
cerrar la revisión; los procesos de trabajo existentes no se alteraron.

El recorrido solicitado puede ensayarse con los componentes iniciados y PHP
compatible. Este resultado se limita a los escenarios y anchos comprobados.

---

# Verificación anterior — 11/11/2026, Lima

Revisión contra `Informe_Integral_ARAT_STUDIO_Laravel.docx`, versión 1.0,
facilitado en `C:\Users\rolan\OneDrive\Desktop\`. Se leyó su contenido y sus
tablas de CU/RN/aceptación; la fuente y su SHA-256 constan en la
[matriz de cumplimiento](REVISION_INFORME_INTEGRAL.md).

**Resultado funcional:** cuatro catálogos y flujo comercial verificados.
**Parcial:** el servidor PHP integrado todavía no devuelve 413 controlado para
peticiones superiores a 64 MiB. SMTP real y producción no se han ensayado.
No se presenta esta revisión como certificación productiva.

## Conservación antes y después

Antes de corregir se generó y restauró:

`storage/app/private/backups/20261112_020135/`

Dump, inventario y ZIP de 326 archivos verificados. Las 27 tablas del respaldo
se restauraron en `arat_restore_20261112_020135`. La versión definitiva de la
migración de revisión se ensayó desde otra restauración original:
`arat_review_20261112_022538`. Las trece tablas de negocio comparadas conservaron
todas sus columnas y filas anteriores.

Después de aplicar la migración al desarrollo, se compararon **18 tablas** con
esa copia validada: mismos registros y valores. Se conservan:

| Entidad | Antes | Después |
|---|---:|---:|
| Categorías | 3 | 3 |
| Proyectos | 3 | 3 |
| Servicios | 12 | 12 |
| Grupos de tarifas | 3 | 3 |
| Versiones de tarifas | 3 | 3 |
| Usuarios | 7 | 7 |
| Solicitudes | 7 | 7 |
| Cotizaciones | 3 | 3 |
| Revisiones | 7 | 7 |
| Adjuntos referenciados | 7 | 7 |
| Historial de portadas | 3 | 3 |
| Historial comercial | 20 | 20 |

Auditoría, notificaciones, intenciones, evaluaciones técnicas y accesos de la
base de desarrollo conservan también sus cantidades y datos originales.
Los **122 archivos de negocio e imágenes** presentes en el respaldo nuevo
coinciden en SHA-256. Se mantienen los IDs, códigos, importes, fechas y rutas
de las tres cotizaciones heredadas. No se crearon cuentas ficticias ni se
sembraron solicitudes en `arat_studios`.

El respaldo anterior `20261112_002703` también se conserva. Los sufijos técnicos
usan UTC; la fecha local de la revisión sigue siendo 11 de noviembre.

## Correcciones aplicadas al alcance obligatorio

- Protección de modelo para contenido congelado, PDF, hash y fechas de
  cotizaciones emitidas; identificadores publicados y eventos históricos.
- Conservación privada de portadas sustituidas: copia verificada, traslado del
  original y recuperación de ubicación si falla el guardado. No se elimina el
  archivo confirmado. Activos compartidos de identidad mantienen su uso público.
- Validación de ficha y portada existente antes de reactivar un proyecto.
- Activación tarifaria con puntero esperado, conflicto 409 y bloqueo de familia;
  booleano heredado derivado del estado. Precios publicados requieren versión.
- Filtros de catálogo, bandejas e historial; categorías muestran asociados y
  conteos. Visibilidad pública exige proyecto y categoría activos.
- Corrección validada vuelve a evaluación y genera avisos durables; personal
  registra evidencia con su propia identidad y sin modificar contactos ni
  simular aceptación.
- Cada intento de notificación registra fecha/destino/error/referencia y
  conserva el anterior; reintento autorizado y atómico. Destino de propuesta
  tomado del snapshot.
- Fecha y razón de cierre; expediente terminal sin cambio posterior de correo.
  La aceptación registra cotización y acceso verificado en el historial.
- Cohorte de aceptación por **primera emisión**, evitando incluir como clientes
  nuevos las revisiones recientes de expedientes antiguos.
- Reintento de una emisión heredada devuelve el resultado existente sin otro
  PDF, y una respuesta repetida a versión ya sustituida devuelve 409.

Migración nueva ejecutada, después del ensayo y respaldo:

`2026_11_12_040000_complete_review_integrity`

Agrega metadatos, registro de intentos e índices, sin borrar datos ni cambiar
campos originales. Se conservan aplicadas las migraciones anteriores
010000, 020000 y 030000 del avance. No se ejecutaron seeders, fresh, refresh,
wipe, rollback destructivo, cambios de APP_KEY ni modificaciones de XAMPP.

## Suite ejecutada

```powershell
php artisan test --compact
```

**82 pruebas, 674 aserciones, todas correctas.**
SQLite exclusivamente en memoria, discos/correo de prueba y salvaguarda en
`tests/TestCase.php`. Las pruebas nuevas de `FinalReviewTest` cubren las cinco
operaciones reales por catálogo, con activación inicial separada de la baja y
reactivación, conteos e IDs; inmutabilidad, hash, privacidad/rollback de portada,
fichas incompletas, correo por intento, staff con evidencia, cierre, cohorte,
revalidación de grant y límites de tiempo.

Los casos previos de autenticación/perfil, cálculo decimal, registro, catálogo,
archivos, errores, evaluación, versiones y respuesta volvieron a pasar.
No se eliminaron pruebas para ocultar fallos.

## Concurrencia MariaDB

```powershell
php tests/Integration/MariaDbConcurrency.php --run
```

**7 comprobaciones correctas** en `arat_concurrency_20261112_022133`.
Procesos PHP y conexiones independientes:

1. Toma simultánea: una confirmación y un 409.
2. Borradores simultáneos: un mismo ID, una única versión.
3. Registro simultáneo idéntico: una solicitud y mismo ID.
4. Registro simultáneo distinto con misma clave: un registro y un conflicto.
5. Aceptación primero: v1 aceptada, v2 anulada y solicitud cerrada.
6. Emisión primero: v2 emitida, v1 sustituida y respuesta antigua 409.
7. Activación tarifaria desde el mismo puntero: una confirmación y un 409,
   manteniendo una vigente y un puntero de su propia familia.

Durante el render se exige transacción cero; otra conexión aceptó mientras el
render esperaba, comprobando que no mantenía el bloqueo común de solicitud.
La concurrencia SQL se probó en servicios del flujo, no se atribuye al servidor
PHP monohilo una capacidad de atender dos POST en paralelo.

## Navegador y HTTP reales

Chrome headless mediante Playwright local; servidor `127.0.0.1:8127` y
MariaDB aislada. Cuentas de ensayo con contraseñas aleatorias en memoria,
deshabilitadas al terminar. No se usaron contraseñas ni correos reales.

- Cuatro CRUD: creación, consulta, edición, estados y filtros; categoría con
  proyecto activo bloqueada y dependencia visible.
- Sustitución de portada: URL pública anterior devuelve 403 y admin descarga
  el original privado con exactamente los mismos bytes.
- Home y portadas de MariaDB; cotizador 80/LOD 300 = PEN 10800.00; solicitud con
  PDF pequeño, reenvío idéntico y contenido distinto 409.
- Asignación, observación, acceso verificado y corrección conservando dos
  adjuntos; retorno automático a evaluación y aprobación técnica.
- V1 real, v2 borrador manteniendo v1, emisión de v2, PDF anterior disponible
  privadamente, aceptación de cliente y cierre.
- 501 m² sin estimación, rechazo con motivo y solicitud manual sin área/LOD.
- Gestor: 403 en los cuatro CRUD; gestor ajeno: 403 en expediente/adjunto;
  cliente de otro expediente: PDF 403; visitante sin descarga privada.
- Cuenta deshabilitada: sesión ya iniciada denegada con 403.
- POST sin CSRF: 419; token usado: 403; expiración forzada de enlace/grant en
  la base aislada: 403. El límite temporal exacto se verifica además con reloj
  de pruebas, sin esperar físicamente quince minutos.
- Edición real del perfil; escritorio 1440 px; móvil 390 y expediente 320/390;
  sin desbordamiento final, foco/Tab/Escape, menú sin JavaScript.
  Axe sin infracciones detectadas en las 15 combinaciones auditadas, sin
  presentarlo como certificación integral de accesibilidad.
- PDF descargado de una y dos páginas. Mantiene marca textual ARAT STUDIO,
  fuente DejaVu para Unicode, importes/fechas en Lima y saltos legibles;
  la web conserva Kodchasan/Arial. No incorpora logo gráfico al PDF.

Las notificaciones procesadas localmente están marcadas simulated; un fallo de
correo no cambia estados comerciales ni elimina PDF. La respuesta del cliente
no constituye firma digital certificada.

## Rendimiento con 1.000 solicitudes aisladas

Se completó la base de ensayo hasta 1.000 solicitudes ficticias; nunca se usó
desarrollo para esta carga. Cinco GET HTTP autenticados de `/gestion`,
15 filas por listado y HTTP 200 en todos:

`224.12, 245.76, 186.23, 106.38 y 104.50 ms`

Máximo: **245.76 ms**, inferior al objetivo de 2.000 ms. Medición del HTML
servido, sin incluir CDN, descarga de fuentes o latencia de otra infraestructura.
Esto no es una prueba de carga concurrente masiva.

## PHP, tamaños y límite pendiente

`php --ini` confirmó `C:\Users\rolan\.config\herd-lite\bin\php.ini`.
PHP 8.5.12, sin otros INI: upload_max_filesize=50M y post_max_size=64M.
No se modificaron esos archivos ni XAMPP durante la revisión.

| Ensayo actual en servidor aislado | Resultado |
|---|---|
| PDF de 49 MiB | Archivo y solicitud guardados |
| 51 MiB en formulario | JS retira el archivo y muestra aviso/enlace |
| 51 MiB por HTTP sin JS | 422 con mensaje; sin registro adicional |
| 65 MiB por HTTP | Advertencia de arranque, HTTP 200; sin registro ni confirmación |

**Parcial:** PHP responde antes de Laravel para >64 MiB. El manejador 413 de
Laravel pasa aislado, pero no resuelve el runtime. Debe configurarse y probarse
el servidor/proxy del despliegue; no se declara solucionado.

## Documentación, herramientas y archivos nuevos

README explica instalación sin perder datos y tres terminales:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
php artisan queue:work --queue=default --tries=1 --timeout=180 --sleep=1
php artisan schedule:work
```

La versión del README preparada en esa entrega también explicaba
`commercial:recover`, `commercial:recover --inline`, `commercial:retry ID`,
`queue:restart` y la recuperación de leases. Los comandos de recuperación son:

```powershell
php artisan commercial:recover
php artisan commercial:recover --inline
php artisan commercial:retry ID
php artisan queue:restart
```

`ID` corresponde al trabajo fallido; `--inline` se usa con el worker detenido.
GUIA_EXPOSICION contiene el recorrido de 12 minutos. La clasificación por CU,
RN, PA y requisitos transversales está en
[REVISION_INFORME_INTEGRAL.md](REVISION_INFORME_INTEGRAL.md).

Se revisaron Pint sobre PHP modificado, sintaxis JS, vistas Blade y `git diff --check`.
Los archivos nuevos publicables son código, migraciones, pruebas y documentación.
No se incluyen .env, credenciales, respaldos SQL/ZIP, adjuntos, PDFs generados,
logs, capturas ni scripts temporales. Las evidencias locales permanecen ignoradas
en `storage/framework/testing` y los archivos privados fuera de public.

`/Microsoft/` sigue ignorado; contiene caché local de PowerShell
`Windows/PowerShell/ModuleAnalysisCache`, no versionada. No se borró ni preparó.
El índice permanece vacío. No se hizo git add, commit, push ni cambio de rama.

## Pendientes concretos

- Respuesta controlada 413 del servidor para peticiones superiores a 64 MiB.
- SMTP real y recepción de mensajes: no se configuraron credenciales ni se
  contactó al estudio.
- Verificación productiva, proxy y navegadores/dispositivos adicionales.
- Mejoras P1 del informe fuera del alcance de esta revisión; no se añaden
  pagos, obras, contabilidad, roles nuevos ni otro CRUD.

Los datos originales se conservan. Los registros y archivos ficticios nuevos
quedaron en el entorno aislado para trazabilidad.

## Cierre de interfaz: comprobaciones de este corte

Se revisaron las pantallas públicas Inicio, Portafolio (estado vacío), Cotizador
y Solicitar proyecto, más el menú móvil y el foco de teclado. El navegador usó
un servidor local con SQLite temporal aislado, migrado sin seeders ni registros
de negocio; no se modificó `.env` ni la base local.

- Inicio → Portafolio → Cotizador → Solicitar proyecto: navegación correcta,
  todas las páginas respondieron HTTP 200.
- A 320, 390, 768 y 1440 px, las cuatro pantallas no presentaron desbordamiento
  horizontal del documento.
- El salto al contenido recibió foco visible. El menú móvil se abrió con
  teclado, recibió foco interno y Escape lo cerró devolviendo el foco al
  resumen. A 200 % de zoom CSS no apareció desbordamiento horizontal; esto no
  sustituye una comprobación de zoom nativo en otros navegadores.
- El cotizador respondió HTTP 200 en PEN y mostró el caso real del entorno
  aislado sin tarifa vigente: evaluación manual, sin importe inventado.
- La solicitud expuso etiquetas y nombres accesibles para sus campos; no se
  envió ni creó ninguna solicitud durante esta revisión. Se probó seleccionar
  un archivo temporal, mostrar nombre y tamaño, retirarlo y devolver foco al
  campo; el control quedó oculto y el archivo se limpió.
- Se corrigieron tres peticiones 404 a fuentes locales Kodchasan inexistentes;
  la hoja de estilos ya usa la fuente Kodchasan declarada en el layout.
- `php artisan view:cache`: correcto. `npm.cmd run build`: correcto.
  `node --check` sobre todos los JS de `public/js`: correcto.
  `php artisan test`: 82 pruebas y 674 aserciones correctas.
  `git diff --check`: correcto; Git solo informó normalizaciones CRLF/LF.

La nota anterior corresponde al corte público previo. Se actualiza a
continuación con los recorridos autenticados realizados en una MariaDB aislada.

## Validación autenticada en MariaDB aislada

Se usó `arat_ui_review_20261111_2331`, con cuentas y expedientes ficticios,
servidor temporal en `127.0.0.1:8127`, almacenamiento privado/público y sesiones
separados. Las operaciones de prueba y los PDFs se limitaron a esa base. No se
modificó `.env`, no se conectó para escritura a `arat_studios` y no se cambiaron
credenciales reales.

- **Admin:** páginas de categorías, proyectos, servicios, tarifas y perfil;
  altas/edición, activación/desactivación, auditoría y consulta de dependencias.
  La desactivación de categoría con proyecto asociado fue bloqueada como se
  esperaba. Editar tarifa publicada generó una v2 en borrador sin desplazar a
  la v1 vigente. Se guardó el perfil ficticio y se comprobó el control accesible
  de mostrar contraseña.
- **Gestor:** tomó una solicitud sin asignar, inició evaluación, registró
  observación pública y motivo interno, y la bandeja ocultó los contactos de
  solicitudes no asignadas. El cliente corrigió el expediente; el gestor lo
  aprobó con evaluación técnica.
- **Cotizaciones:** se creó y emitió v1 por PEN 12 800.00; la v2 por PEN
  13 500.00 se mantuvo borrador mientras v1 seguía vigente. Tras preparar y
  procesar el trabajo durable, v2 quedó emitida/vigente y v1 sustituida, ambas
  con PDF listo. La descarga de v2 respondió HTTP 200, `application/pdf` y
  firma `%PDF-`. En otra cotización ficticia se vio el estado PDF fallido y la
  acción autorizada “Reintentar PDF de la misma versión”; el reintento produjo
  PDF listo. Las notificaciones se procesaron solo con transporte `array` y se
  mostraron como simuladas, no como correos entregados.
- **Cliente:** enlace temporal verificado, consulta de expediente observado,
  corrección registrada y retorno a evaluación. Después de la aprobación,
  consultó v1 anterior y v2 vigente, descargó el PDF y aceptó la propuesta.
  El expediente terminó cerrado con resultado claro; se comprobó que la
  respuesta no se describe como firma certificada ni se muestran notas internas,
  tokens o rutas privadas.
- Se corrigió un mensaje de éxito de catálogo que mencionaba una versión de
  tarifa al editar otros registros y se eliminó el render duplicado del mensaje
  flash en el expediente del cliente.
- **Responsive:** en las cuatro pantallas CRUD y el perfil, además de bandeja,
  detalle comercial y páginas de v1/v2, se midieron viewports CSS reportados
  de 320, 390, 768 y 1440 px; no hubo desbordamiento del documento después de
  contener el layout de las tablas administrativas. En tablas estrechas el
  desplazamiento quedó localizado en su contenedor. El expediente de cliente
  se revisó a 320, 390 y 1440 px sin overflow horizontal. El menú móvil se
  abrió con teclado y cerró con Escape. En la tabla de categorías, Tab hacia la
  acción desplazó el contenedor local hasta el enlace enfocado.
- **Tipografía:** Kodchasan se cargó realmente: la hoja de Google Fonts y tres
  recursos WOFF2 respondieron 200; `document.fonts.check('700 24px Kodchasan')`
  fue `true` y el encabezado computó Kodchasan.

**Pendiente de comprobación:** la automatización del navegador informó
`devicePixelRatio=2` durante una parte de las mediciones, pero no permitió
atribuirlo con certeza al zoom nativo del navegador frente a emulación/
escalamiento del dispositivo. No se declara comprobado el zoom nativo al 200 %.
Tampoco se probó entrega SMTP real ni se provocó un fallo de notificación para
recorrer su reintento; los mensajes revisados usaron transporte simulado.

## Correcciones visuales de esta validación

Las tablas administrativas contenían filas más anchas que el viewport. Aunque
cada tabla tenía su propio contenedor desplazable, su ancho intrínseco hacía
crecer el ancho medido del documento a 320/390 px. Se añadió contención de
layout a `.admin-page .table-responsive`; las cuatro pantallas mantienen ahora
el scroll dentro de la tabla y el documento no desborda.

Se ejecutaron `php artisan test --compact` (82 pruebas, 674 aserciones),
`php artisan view:cache`, `vendor\\bin\\pint --test
app\\Http\\Controllers\\Admin\\CatalogController.php` y `git diff --check`;
todos terminaron correctamente. Git solo advirtió normalizaciones CRLF/LF.
