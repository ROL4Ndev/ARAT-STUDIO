# Verificación de cierre — 11/11/2026

Sin funcionalidades nuevas, commits ni push. Se aplicó la actualización mínima
de tres dependencias Symfony autorizada para el cierre.

## PHP activo

`php --ini` identificó `C:\Users\rolan\.config\herd-lite\bin\php.ini`.
PHP CLI: 8.5.12; sin archivos INI adicionales. Valores actuales comprobados:
`upload_max_filesize=50M`, `post_max_size=64M`. El INI mínimo no contenía esas
directivas y tomaba los predeterminados 2M/8M; se añadieron ambas líneas al
archivo activo de Herd Lite y se abrió en Notepad. No se modificó XAMPP.
Se reinició el servidor Laravel del proyecto en 127.0.0.1:8000.

Configuración aplicada exclusivamente en ese archivo:

```ini
upload_max_filesize = 50M
post_max_size = 64M
```

Detener `php artisan serve` con Ctrl+C y volver a iniciarlo:

```powershell
& 'C:\Users\rolan\.config\herd-lite\bin\php.exe' artisan serve
```

Volver a ejecutar `php --ini` y comprobar los valores con:

```powershell
php -i | Select-String -Pattern '^upload_max_filesize','^post_max_size'
```

## Navegador y MariaDB

Chrome headless mediante Playwright temporal, servidor Laravel local en
127.0.0.1:8000 (reverificación tras actualizar Symfony) y MariaDB configurada
en el proyecto. Credenciales ficticias
generadas en memoria; no se utilizaron contraseñas reales. Cuenta de prueba
deshabilitada retirando el rol y rotando la clave al terminar. No se borraron datos.

Comprobaciones realizadas:

- 80 m², LOD 300: S/ 10,800.00.
- Solicitud con PDF pequeño y consentimiento; reenvío del mismo formulario
  sin duplicar (comprobación de cantidad en MariaDB).
- Inicio de sesión como gestor, búsqueda por código y descarga del adjunto.
- Pendiente → evaluación → aprobada; historial con cuatro eventos al emitir.
- Cotización oficial PEN 12000.50; emisión 11/11/2026, vencimiento 26/11/2026;
  descarga de un archivo PDF real.
- Otra solicitud de 501 m² con estimación nula; rechazo sin motivo rechazado
  por la validación y posterior rechazo con motivo persistido (tres eventos).
- Visitante sin acceso a adjunto ni PDF: redirección a ingreso; gestor recibe
  403 al intentar ambos CRUD administrativos.
- Capturas revisadas visualmente: logo web, tipografía y textos legibles,
  importe y fechas correctos. El PDF usa cabecera textual ARAT STUDIO,
  sin logo gráfico. Documento corto: una página, sin recortes.
- Alcance ficticio largo, renderizado sin modificar la cotización persistida:
  tres páginas, 100 líneas conservadas, saltos legibles e importe al final.

Registros ficticios conservados de la primera verificación:

- Aprobada: `SOL-01M75JYHXQG6AMR303ZNNS9NMH`.
- Cotización: `COT-01M75JYNDZ0ZFRAVB5BR8W8Y3X`.
- Rechazada/fuera de escala: `SOL-01M75JYQ797X8DCSPT9CM6TCHZ`.

Reverificación tras actualizar Symfony: solicitud aprobada
`SOL-01M75N9SERM6JVXE0K60RQRVTT`, cotización
`COT-01M75N9ZTX3YF3NVC35SBYEZPM`, solicitud rechazada/fuera de escala
`SOL-01M75NA354HYE110D7BT5XNZTC`. Todos los pasos anteriores volvieron
a pasar, incluida descarga privada y restricciones de ambos CRUD.

Artefactos de verificación en `storage/framework/testing` y archivos privados
en `storage/app/commercial`, todos ignorados por Git. Correos solo en log.

Regresión: `php artisan test`: 46 pruebas, 345 aserciones correctas; SQLite
en memoria, discos y correo falsos. `git diff --check`: correcto.

## Composer: actualización mínima aplicada

`composer audit --format=json` tras actualizar: `advisories: []`,
`abandoned: []`. Los tres paquetes están instalados en 8.1.11.
Los seis avisos siguientes afectaban a 8.1.10 y quedaron resueltos:

| Paquete | Aviso | Problema |
|---|---|---|
| symfony/http-foundation | [CVE-2026-118514](https://symfony.com/cve-2026-118514) | HEAD evita determinadas reglas basadas en método |
| symfony/http-foundation | [GHSA-3h27-hjv7-fqjx](https://github.com/symfony/symfony/security/advisories/GHSA-3h27-hjv7-fqjx) | Bypass de reglas por ruta añadida al script |
| symfony/http-foundation | [GHSA-xg2q-4rrq-657q](https://github.com/symfony/symfony/security/advisories/GHSA-xg2q-4rrq-657q) | Confianza indebida en proxies con private_ranges |
| symfony/http-foundation | [GHSA-5mx8-mwrp-5693](https://github.com/symfony/symfony/security/advisories/GHSA-5mx8-mwrp-5693) | Host de URL controlado por cliente |
| symfony/http-kernel | [CVE-2026-123339 / GHSA-wg5q-m2ww-f3c9](https://github.com/symfony/symfony/security/advisories/GHSA-wg5q-m2ww-f3c9) | Cabeceras reenviadas alteran URLs mediante fragmentos |
| symfony/routing | [CVE-2026-118516](https://symfony.com/cve-2026-118516) | Host absorbe parte de la ruta en matcher compilado |

Se repitió la simulación y luego se ejecutó el comando autorizado:

```powershell
composer update symfony/http-foundation:8.1.11 symfony/http-kernel:8.1.11 symfony/routing:8.1.11 --no-interaction
```

Composer resolvió exactamente tres actualizaciones 8.1.10 → 8.1.11,
sin instalaciones ni eliminaciones adicionales, compatible con el proyecto.
Se comparó cada entrada de `composer.lock` con una copia previa: únicamente
cambiaron esos tres paquetes. `composer.json`, los demás paquetes y los
metadatos restantes del lock no cambiaron. No se ignoraron requisitos.
`composer audit` terminó sin avisos y la suite completa volvió a pasar:
46 pruebas y 345 aserciones, en SQLite en memoria. Dos consultas posteriores
tuvieron timeout de Packagist; el último reintento volvió a terminar sin avisos.

## Cargas reales tras configurar PHP

- PDF válido de 49 MiB (51 380 224 bytes): confirmado y persistido una sola
  vez por formulario. Se repitió con un correo ficticio distinto y también
  pasó. Los registros de prueba se conservaron, sin borrar datos.
- PDF de 51 MiB (53 477 376 bytes), navegador: JavaScript retiró el archivo,
  exigió enlace externo y no hubo POST a `/solicitar-proyecto`, ni registro
  ni confirmación. El diagnóstico inicial contaba también un POST del
  antivirus Kaspersky; se corrigió el filtro del script de prueba.
- El mismo PDF de 51 MiB enviado directamente, sin JavaScript: HTTP 422,
  sin registro. El error genérico de PHP se aclaró en `StoreServiceRequest`:
  «No se pudo recibir el archivo. Revisa que no supere 50 MB o utiliza un
  enlace externo». También se aclaró el mensaje del límite de Laravel.
  Ambas rutas se verificaron en la suite; el caso directo se volvió a probar.
- PDF de 65 MiB (68 157 440 bytes): se detectó una limitación real del servidor
  integrado de PHP 8.5.12. Playwright y curl devolvieron HTTP 200 con solo
  una advertencia `PHP Request Startup: POST Content-Length ... exceeds
  the limit of 67108864 bytes`, sin página Laravel ni confirmación de guardado.
  El comportamiento se repitió con otro espaciado válido del PDF. La consulta
  de MariaDB para el correo ficticio del caso de rechazo devolvió `[]`:
  ninguna solicitud creada por los envíos de 51/65 MiB.
  No debe presentarse este resultado como un 413 correcto en navegador.
  El servidor procesa el arranque antes de ejecutar el router Laravel
  ([código PHP 8.5.12](https://raw.githubusercontent.com/php/php-src/php-8.5.12/sapi/cli/php_cli_server.c)).
  No se modificó el runtime, `vendor`, el router ni se subió el límite para
  ocultar el problema. El manejador Laravel sí devuelve 413 en su prueba
  aislada; queda pendiente verificar una página 413 del servidor web de
  despliegue o resolver el comportamiento del runtime fuera de este alcance.

Se usan datos ficticios y PDF sin información real. Los archivos grandes,
respuestas de diagnóstico y scripts están en `storage/framework/testing`,
ignorados por Git. Ningún rechazo debe interpretarse como solicitud guardada.

## Git y archivos de ejecución

`Microsoft/Windows/PowerShell/ModuleAnalysisCache` (8246 bytes) es una caché
local de análisis de módulos PowerShell. Ya estaba sin seguimiento al iniciar
la implementación. `git ls-files Microsoft` y `git log --all -- Microsoft`
no devolvieron entradas: no está versionada ni aparece en el historial local
disponible. Se añadió `/Microsoft/` al `.gitignore` raíz y se comprobó con
`git check-ignore`. No se borró ni preparó la carpeta.

`.env`, logs de correo, adjuntos, PDF, capturas y herramientas de verificación
están ignorados. La selección versionada de storage solo contiene `.gitignore`.
No hay archivos preparados en el índice. Las credenciales ficticias no se
escribieron en archivos. No se agregaron credenciales reales a los cambios.

## Límites pendientes y exposición

- Límites PHP 50M/64M aplicados y servidor reiniciado; audit Composer limpio.
- No se hizo prueba de carga simultánea sobre MariaDB, impresión física,
  ni comprobación de producción/proxy. Reintentos y restricciones únicas sí
  se verificaron, junto con estados desactualizados en pruebas aisladas.
- Cargas de 49 y 51 MiB comprobadas. Sigue pendiente el error controlado real
  de más de 64 MiB: PHP integrado devuelve una advertencia HTTP 200 antes de
  Laravel. Repetir en el servidor de despliegue con límite de petición y página
  413 configurados, o evaluar por separado una corrección del runtime.

Exposición: visitante abre `/cotizador`, calcula 80 m²/LOD 300, solicita proyecto
y adjunta PDF; gestor inicia sesión, busca el código en `/gestion`, descarga
el plano, evalúa y aprueba; define alcance e importe, emite y descarga la
cotización. Mostrar luego la solicitud de 501 m² rechazada y las restricciones
de visitante y gestor. Para acceso manual usar una cuenta propia introduciendo
su contraseña directamente en el navegador; la cuenta ficticia está deshabilitada.

## Estado y diff

Salida actualizada al cierre. El diff cuenta solo archivos seguidos; los nuevos
figuran con `??`. No hay archivos preparados.

```text
 M .gitignore
 M README.md
 M app/Http/Controllers/PublicSite/QuotationCalculatorController.php
 M app/Services/QuotationCalculatorService.php
 M bootstrap/app.php
 M composer.json
 M composer.lock
 M config/filesystems.php
 M public/js/calculator.js
 M resources/views/layouts/app.blade.php
 M resources/views/quotations/calculator.blade.php
 M routes/web.php
 M tests/TestCase.php
?? VERIFICACION_ENTREGA.md
?? app/Http/Controllers/Commercial/
?? app/Http/Controllers/PublicSite/ServiceRequestController.php
?? app/Http/Requests/Commercial/
?? app/Http/Requests/PublicSite/StoreServiceRequest.php
?? app/Mail/
?? app/Models/Quotation.php
?? app/Models/ServiceRequest.php
?? app/Models/ServiceRequestHistory.php
?? app/Rules/
?? app/Services/CommercialWorkflowService.php
?? app/Services/IssueQuotationService.php
?? app/Services/QuotationPdfService.php
?? app/Services/ServiceRequestService.php
?? config/commercial.php
?? database/migrations/2026_11_11_180000_create_commercial_tables.php
?? public/js/service-request.js
?? resources/views/commercial/
?? resources/views/errors/
?? resources/views/mail/
?? resources/views/service-requests/
?? tests/Feature/CommercialWorkflowTest.php
?? tests/Feature/DecimalCalculatorTest.php
?? tests/Feature/OfficialQuotationTest.php
?? tests/Feature/PublicServiceRequestTest.php
```

```text
 .gitignore                                         |   1 +
 README.md                                          |  99 ++++-
 .../PublicSite/QuotationCalculatorController.php   |   2 +-
 app/Services/QuotationCalculatorService.php        |  20 +-
 bootstrap/app.php                                  |  17 +-
 composer.json                                      |   2 +
 composer.lock                                      | 416 ++++++++++++++++++++-
 config/filesystems.php                             |   8 +
 public/js/calculator.js                            |  10 +
 resources/views/layouts/app.blade.php              |   3 +-
 resources/views/quotations/calculator.blade.php    |   2 +
 routes/web.php                                     |  17 +-
 tests/TestCase.php                                 |  10 +-
 13 files changed, 568 insertions(+), 39 deletions(-)
```
