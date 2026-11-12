# Verificación final — 11/11/2026, Lima

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

**82 pruebas, 672 aserciones, todas correctas.**
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

También explica `commercial:recover`, `commercial:recover --inline`,
`commercial:retry ID` y la recuperación de leases; no es necesario leer esta
conversación para operarlo. GUIA_EXPOSICION contiene el recorrido actualizado
de 12 minutos. La clasificación por CU, RN, PA y requisitos transversales está
en [REVISION_INFORME_INTEGRAL.md](REVISION_INFORME_INTEGRAL.md).

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
