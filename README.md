# ARAT STUDIO

Sitio del estudio y gestión de solicitudes, evaluaciones, cotizaciones versionadas y
expedientes privados. Laravel 13, PHP 8.5, MariaDB, Blade y Vite. Bootstrap en la web
pública; Tabler, que incluye Bootstrap, en el panel. Kodchasan se sirve localmente.
Interfaz en español, moneda PEN y zona horaria America/Lima.

## Preparar la terminal en Windows

Desde la raíz, ejecutar en cada terminal que vaya a usar PHP:

```powershell
. .\tools\use-php.ps1
php -v
composer --version
```

El helper busca PHP 8.5 de Herd y después el del PATH. Si hace falta, indicar la
ruta con `-PhpBinary 'C:\ruta\php.exe'`. Solo cambia esa terminal: añade el INI
local de cargas y hace que Composer y sus procesos hijos usen el PHP seleccionado.
No modifica XAMPP ni el PATH del sistema. `tools/php/local.ini` conserva el INI
instalado y sus extensiones; limita archivos a 50 MiB y peticiones a 64 MiB.
Los avisos se registran sin impedir la respuesta HTTP 413.

## Instalación nueva

Requiere PHP 8.5, Composer, Node.js/npm y MariaDB. Clonar el repositorio y preparar
`.env` desde `.env.example`. Configurar la base vacía, `APP_URL`,
`APP_TIMEZONE=America/Lima`, `APP_LOCALE=es_PE`, `APP_FALLBACK_LOCALE=es`,
`QUEUE_CONNECTION=database` y `MAIL_MAILER=log`.

```powershell
composer install
npm.cmd ci
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan usuarios:crear-interno
php artisan storage:link
npm.cmd run build
```

Generar la clave y ejecutar los seeders **solo en una instalación nueva**. Las
tarifas iniciales quedan enlazadas a sus grupos y son demostrativas. El catálogo
comercial activo es la fuente de servicios; no se publica un borrador por importarlo.
`portfolio:inventario` revisa las carpetas y `portfolio:incorporar` muestra el plan
antes de incorporar proyectos faltantes como borradores.

## Actualizar una instalación existente

Conservar `.env`, `APP_KEY`, cuentas, archivos y datos. Hacer un respaldo privado
restaurable y detener cola/scheduler antes de cambios de datos. No usar `fresh`,
`refresh`, `wipe`, regenerar la clave ni ejecutar seeders sobre la base de trabajo.

```powershell
composer install
npm.cmd ci
php artisan migrate --pretend
php artisan migrate
php artisan optimize:clear
npm.cmd run build
```

En esta instalación la migración de identificación de lotes ya está aplicada.
Los proyectos, imágenes, categorías, servicios y tarifas previos se conservaron.

## Arrancar y detener

Si MariaDB no está iniciado, abrir una terminal adicional:

```powershell
.\tools\start-database.ps1
```

El helper usa XAMPP, verifica que no exista otra instancia, escucha en localhost y
usa índices de replicación propios en storage privado. Resuelve el arranque local
sin modificar los índices dañados de XAMPP. Detener con **Ctrl+C en esa terminal**.
Si el servicio ya estaba iniciado desde XAMPP, administrarlo desde su panel.

Con MariaDB disponible, abrir tres terminales, preparar PHP en cada una y ejecutar:

```powershell
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

```powershell
php artisan queue:work --queue=default --tries=1 --timeout=180 --sleep=1
```

```powershell
php artisan schedule:work
```

Abrir http://127.0.0.1:8000. Detener los tres procesos con **Ctrl+C en sus
terminales**. `--no-reload` conserva el INI local en el proceso hijo de Laravel.
Para editar frontend con recarga, `composer dev` inicia servidor, cola, scheduler y
Vite; Ctrl+C detiene el grupo. El build normal ya usa el cargador de configuración
compatible con las restricciones de esta carpeta en Windows.

`MAIL_MAILER=log` simula correos. Para mostrar un expediente ficticio, habilitar
`CLIENT_ACCESS_DEMO_ENABLED=true` solo en local y usar «Preparar acceso de cliente
para demostración» dentro del expediente. El enlace es privado, temporal y de un
solo uso; abrirlo en otra sesión sin cuenta interna. No publicar logs ni enlaces.

## Demostración y trabajos

La base `arat_studios` contiene 15 solicitudes ficticias del lote `arat-2026-v1` y
una solicitud de origen dudoso conservada. Las cuatro cuentas del equipo mantienen
sus datos originales. Los nombres y necesidades del lote son ficticios: no son
clientes, encargos ni ingresos reales. Sus correos usan example.com y el teléfono
no es enrutable. Los avisos de preparación se procesaron con transporte `array`.

```powershell
php artisan demo:prepare
php artisan demo:prepare --batch=arat-2026-v1 --seed=20261124 --date=2026-11-24 --actor=1 --apply
```

Sin `--apply` solo se muestra el plan. Para escribir exige entorno local y
mantenimiento, con cola y scheduler detenidos. Un lote completo se conserva al
repetirlo; uno incompleto se rechaza. No borra otros registros ni modifica tarifas.
No hace falta volver a prepararlo para recorrer la demostración.

```powershell
php artisan commercial:recover
php artisan commercial:retry ID
php artisan commercial:expire
```

`ID` es el trabajo fallido. Con el worker detenido se puede usar
`commercial:recover --inline`. La emisión conserva versiones, genera un PDF
inmutable y admite reintento sobre la misma versión. La aceptación cierra el
expediente; no añade pagos ni ejecución de obra.

## Verificar

```powershell
php artisan test --compact
php tests/Integration/MariaDbConcurrency.php --run
npm.cmd run build
php artisan view:cache
php artisan view:clear
composer validate --strict
composer check-platform-reqs
```

PHPUnit exige SQLite `:memory:` y aísla también correo, sesión, caché y mantenimiento.
La integración crea una base nueva `arat_concurrency_*` y archivos privados
separados; requiere permiso de creación. Nunca reinicia `arat_studios`.

La revisión de navegador usa `tools/preview/bootstrap.php`, SQLite y cuentas
efímeras. No ejecuta altas ni decisiones de prueba sobre la demostración local.
Los scripts de `tools/maintenance` documentan la intervención acotada de esta
instalación: no forman parte del arranque ni se deben repetir como seeders.

## Evidencia de entrega

- [Revalidación después del apagado, 25 de noviembre](docs/final/REANUDACION-20261125.md)
- [Informe final, datos, comprobaciones y recorrido](docs/final/ENTREGA.md)
- [Capturas antes/después](docs/final/index.html)
- [Fuentes y licencias de medios aprobados](docs/revision-3/README.md#fuentes-y-licencia)

Los informes de `docs/design`, `docs/revision-2`, `docs/revision-3` y las guías de
entregas anteriores se conservan como historial. El informe final distingue los
resultados vigentes de sus límites históricos. No se han hecho commit, push ni
despliegue. SMTP real, producción y dispositivos físicos quedan fuera de esta
verificación local.
