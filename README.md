# ARAT STUDIO

Laravel para presentar el estudio y gestionar solicitudes y cotizaciones.
PHP 8.5, MariaDB de XAMPP, Blade, Bootstrap y JavaScript; títulos Kodchasan,
textos Arial, español de Perú, PEN y presentación en America/Lima.

## Instalar y migrar sin perder datos

Desde la raíz del repositorio en PowerShell, con MySQL de XAMPP activo:

```powershell
php -v
php --ini
composer install
npm.cmd ci
npm.cmd run build
php artisan migrate --pretend
php artisan migrate
php artisan storage:link
```

Conservar el `.env` existente, APP_KEY y credenciales. En una instalación nueva,
preparar `.env` desde `.env.example` con la conexión propia a `arat_studios`,
`APP_URL`, `APP_TIMEZONE=America/Lima`, `APP_LOCALE=es_PE`,
`QUEUE_CONNECTION=database` y `MAIL_MAILER=log`. Generar APP_KEY solo si la
instalación nueva todavía no tiene clave. Apache solo se necesita para phpMyAdmin.

Respaldar antes de migrar. No usar fresh, refresh, wipe ni seeders sobre datos
de trabajo. Las nuevas migraciones importan catálogos sin reemplazar registros.
Su rollback se bloquea para proteger el historial; una recuperación requiere
restauración controlada del respaldo. CSS y JS se sirven desde `public`:
Vite no necesita estar ejecutándose para visualizar las pantallas.

## Iniciar servidor, worker y scheduler

Tres terminales con la misma conexión y APP_URL:

```powershell
# Terminal 1
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2
php artisan queue:work --queue=default --tries=1 --timeout=180 --sleep=1

# Terminal 3
php artisan schedule:work
```

Abrir http://127.0.0.1:8000. El scheduler despacha intenciones pendientes y marca
vencimientos cada minuto. La vigencia se valida al aceptar aunque no corra el
scheduler. Para despachar inmediatamente y para recuperar fallos:

```powershell
php artisan commercial:recover
php artisan commercial:retry 123
php artisan commercial:recover
php artisan queue:restart
```

Sustituir `123` por el ID real fallido mostrado en la cotización o en
`work_intents`. Los leases abandonados se recuperan después de cinco minutos;
los fallos permanecen para revisión, sin reintento infinito. Con el worker
detenido, la alternativa local `php artisan commercial:recover --inline`
procesa pendientes en primer plano. El PDF fallido tiene reintento en su pantalla,
con la misma versión; la notificación tiene reintento separado.

## Cuentas y permisos

```powershell
php artisan usuarios:crear-interno
```

Solicita nombre, correo, rol y contraseña oculta con confirmación. No existe
registro público ni contraseña fija. `/perfil` permite editar datos y contraseña,
no rol ni habilitación. `users.is_active=false` impide ingresar y corta las
sesiones existentes en la siguiente petición; lo administra el operador de BD.

Admin administra cuatro catálogos y todas las solicitudes; asigna y reasigna
con motivo. Gestor opera solo sus expedientes. La bandeja sin asignar muestra
código, servicio, tipo y fecha, sin contactos ni descargas, y permite tomar
atómicamente. Las Policies comprueban cada registro además del middleware.

## Rutas

| Ruta | Uso |
|---|---|
| `/`, `/proyectos`, `/proyectos/{slug}` | Home y portafolio activo |
| `/ingresar`, `/perfil`, `/panel` | Acceso interno y perfil |
| `/admin` | Panel con acceso a los cuatro catálogos |
| `/admin/categorias`, `/admin/proyectos` | Categorías y proyectos |
| `/admin/servicios`, `/admin/tarifas` | Servicios y tarifas versionadas |
| `/cotizador`, `POST /api/calcular-presupuesto` | Estimación referencial |
| `/solicitar-proyecto` | Registro público y confirmación de estimación |
| `/gestion` | Bandejas e indicadores |
| `/gestion/solicitudes/{codigo_SOL}` | Resumen, Evaluación, Archivos, Cotizaciones e Historial |
| `/gestion/cotizaciones/{codigo_COT}` | Borrador, emisión, estados técnicos y PDF |
| `/acceder-expediente` | Solicitud de enlace por código y correo |
| `/mi-solicitud` | Expediente de cliente verificado |

Los cuatro catálogos incluyen paginación, filtros, alta, consulta, edición,
desactivación/reactivación con motivo, auditoría y `lock_version`. No tienen rutas
DELETE. Categorías con proyectos activos no se desactivan; publicar un proyecto
requiere categoría activa, pero se admiten borradores inactivos. Las portadas
anteriores y heredadas se conservan privadamente. Al reemplazar una portada,
se verifica su copia y se traslada el archivo anterior fuera del disco público,
conservando SHA-256 y ubicación histórica; si falla la transacción se restaura.
Los activos comunes de identidad que siguen usándose públicamente conservan
ese uso; la copia histórica del expediente sigue siendo privada.

Home y el formulario consultan `services` mediante `OfferedService`. Se conservaron
cuatro servicios operativos activos; los ocho textos del Home original se
importaron como borradores inactivos al no probarse equivalencia exacta.
Equipo y contacto conservan la fuente local en `config/studio.php`.
No se inventaron testimonios, clientes ni reconocimientos.

## Reglas del proceso

- Cálculo BigDecimal y Half Up a dos decimales. Tarifa 100 × factor 1.350 ×
  80 m² = PEN 10800.00; una nueva tarifa 110 produce PEN 11880.00.
  Son valores demostrativos. Manual, fuera de escala o sin tarifa vigente:
  estimación nula con motivo, permitiendo evaluación personalizada.
- `tariff_groups` identifica familias; `tariff_params` contiene versiones y el
  grupo apunta a la vigente. Editar una publicada crea un borrador nuevo;
  activar cambia la familia atómicamente, incluso sin versión vigente.
- El servidor recalcula, exige confirmar la estimación y guarda snapshots.
  Una estimación cambiada devuelve 409 antes de registrar. Mismo token de sesión
  y contenido/archivo devuelve la misma solicitud; contenido diferente: 409.
- Pendiente → evaluación → observada → corrección → evaluación →
  aprobado/rechazado. Acciones explícitas y motivos; aprobar exige evaluación
  técnica. Cancelar y cerrar sin acuerdo verifican propuestas y aceptaciones.
- Correcciones conservan revisiones antes/después y archivos. Cambiar servicio
  crea otra solicitud vinculada; cambiar correo exige verificarlo y no reemplaza
  la corrección necesaria para retomar una observación. Una corrección validada
  devuelve la solicitud a evaluación y avisa al responsable; el personal solo
  registra correcciones con evidencia interna y sin suplantar al cliente.
- `quotations` es hasMany y `currentQuotation` apunta a la versión vigente.
  Solo hay un borrador/preparación por solicitud; v2 no sustituye v1 hasta
  disponer de PDF listo. Los PDFs emitidos no se sobrescriben.
- Preparar persiste `work_intents` en la misma transacción. PDF y correo se
  procesan fuera de bloqueos, comprobando estados otra vez antes de emitir.
  Cancelación o aceptación concurrente impiden emisión incompatible.
  Fallos técnicos conservan la versión y permiten reintento.
- Nuevas emisiones: instantes UTC, 15 días exactos, presentación en Lima y
  aceptación solo si `now < expires_at`. Las heredadas conservan la fecha
  visible de vencimiento anterior, al final de ese día de Lima.
- Acceso de cliente: token aleatorio, hash en BD, uso único, 15 minutos.
  Grant revocable con tope de 30 minutos, sujeto además al tiempo de sesión.
  Solo permite su expediente y su historial público. Respuesta POST con CSRF:
  repetir la decisión es idempotente; decisión opuesta o versión sustituida: 409.
  Aceptar cierra la solicitud; staff no puede simular al cliente.

`audit_events` registra actor, recurso, fecha UTC, motivo y antes/después;
`AuditService` filtra campos sensibles y triggers impiden UPDATE/DELETE.
El historial del cliente excluye notas internas.

Indicadores de `/gestion`: pendientes actuales, minutos hasta primera evaluación
de solicitudes creadas en el período, versiones emitidas y tasa por solicitudes
distintas cuya **primera emisión** pertenece a la cohorte. Numerador: solicitudes de
esa cohorte cerradas como aceptada, aunque la aceptación ocurra después.
Fechas inclusivas de Lima, por defecto desde hace 30 días hasta hoy.
Las versiones no multiplican el denominador; el gestor ve su ámbito asignado.

## Archivos, correo y límites reales

Disco privado `commercial`: `storage/app/commercial`, sin enlace público.
Portadas actuales públicas; historial privado. Descargas siempre autorizadas.
Documentos PDF/JPG/JPEG/PNG/DWG/ZIP hasta **50 MiB**, con contenido validado;
portadas JPG/PNG/WebP hasta 5 MiB. No se ejecutan ni descomprimen adjuntos.
JS retira documentos mayores y propone enlace externo; servidor también valida.

PHP local comprobado: 8.5.12, INI
`C:\Users\rolan\.config\herd-lite\bin\php.ini`:

```ini
upload_max_filesize = 50M
post_max_size = 64M
```

Después de modificar ese INI, detener con Ctrl+C y reiniciar `php artisan serve`.
Confirmar con `php --ini`. No modificar por error el PHP de XAMPP.

`MAIL_MAILER=log` significa **simulación**, sin entrega real. El enlace de
desarrollo se consulta localmente en `storage/logs/laravel.log`; contiene un
secreto y no debe capturarse, compartirse ni versionarse. Las notificaciones
guardan estado, destinatario, intentos, error saneado y referencia cuando existe.
Cada intento nuevo queda en `notification_attempts`, con fecha y resultado,
sin sobrescribir los fallos anteriores.
`sent` indica aceptación por el transporte, no entrega garantizada ni SMTP
exactamente una vez. La respuesta del cliente no es firma digital certificada.

Limitación comprobada del servidor integrado PHP 8.5.12: peticiones superiores
a 64 MiB pueden responder advertencia de arranque y HTTP 200 antes de Laravel,
sin guardar. La prueba aislada 413 no resuelve esa respuesta del runtime.
Un despliegue necesita configurar y probar el límite del servidor/proxy.

## Verificación reproducible

```powershell
php artisan test --compact
php tests/Integration/MariaDbConcurrency.php --run
composer audit
node --check public/js/estimate.js
node --check public/js/service-request.js
node --check public/js/navigation.js
git diff --check
```

La suite funcional exige SQLite **en memoria** y discos/correo aislados.
La integración necesita MariaDB y permiso CREATE DATABASE con la conexión local:
crea una base nueva `arat_concurrency_*`, usa procesos independientes, archivos
aislados y contraseñas aleatorias; conserva la base para trazabilidad.

Revisión contra el Informe Integral v1.0: [REVISION_INFORME_INTEGRAL.md](REVISION_INFORME_INTEGRAL.md).
Resultados y respaldo: [VERIFICACION_ENTREGA.md](VERIFICACION_ENTREGA.md).
Recorrido de 12 minutos: [GUIA_EXPOSICION.md](GUIA_EXPOSICION.md).
Repositorio: https://github.com/ROL4Ndev/arat-studios (puede requerir acceso).
No incluir `.env`, logs, credenciales, dependencias ni archivos de ejecución.
`/Microsoft/` contiene caché local de PowerShell y permanece ignorada.
