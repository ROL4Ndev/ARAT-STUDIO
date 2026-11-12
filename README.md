# ARAT STUDIO

Aplicación Laravel para presentar el estudio y gestionar solicitudes y
cotizaciones. Usa PHP 8.5, MariaDB, Blade, Bootstrap y JavaScript; la interfaz
está en español de Perú y presenta importes en PEN y fechas en America/Lima.

## Clonar

```powershell
git clone https://github.com/ROL4Ndev/ARAT-STUDIO.git
cd ARAT-STUDIO
```

## Instalación y ejecución

Requiere PHP 8.5, Composer, Node.js/npm y MariaDB (por ejemplo, MySQL de
XAMPP). Con la base de datos activa, desde la raíz del proyecto:

```powershell
composer install
npm.cmd ci
npm.cmd run build
```

En una instalación nueva, preparar `.env` desde `.env.example` y configurar
la conexión con la base `arat_studios`, `APP_URL`,
`APP_TIMEZONE=America/Lima`,
`APP_LOCALE=es_PE`, `QUEUE_CONNECTION=database` y `MAIL_MAILER=log`.
Generar `APP_KEY` solo si aún no existe. En una instalación existente,
conservar `.env`, `APP_KEY` y credenciales. Respaldar antes de migrar; no usar
`fresh`, `refresh`, `wipe` ni seeders sobre datos de trabajo.
No publicar `.env`, credenciales ni logs.

```powershell
php artisan migrate --pretend
php artisan migrate
php artisan storage:link
```

Para operar localmente, abrir tres terminales:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
php artisan queue:work --queue=default --tries=1 --timeout=180 --sleep=1
php artisan schedule:work
```

Abrir http://127.0.0.1:8000. `MAIL_MAILER=log` simula el correo; no entrega
mensajes reales. Los CSS y JavaScript compilados se sirven desde `public`.
Las pruebas funcionales usan SQLite en memoria, no la base MySQL local.

## Documentación y verificación

- [Recorrido de exposición y límites](GUIA_EXPOSICION.md)
- [Matriz de requisitos y evidencia](REVISION_INFORME_INTEGRAL.md)
- [Resultados, pruebas y pendientes](VERIFICACION_ENTREGA.md)

La suite se ejecuta con `php artisan test --compact`. La integración MariaDB
requiere una conexión con permiso para crear una base de ensayo:
`php tests/Integration/MariaDbConcurrency.php --run`.

El límite de archivos del servidor PHP integrado sigue pendiente para cargas
superiores a 64 MiB: puede responder HTTP 200 antes de Laravel, no el 413
esperado. No se han verificado producción ni SMTP real. Los resultados y el
alcance completo están descritos en los informes enlazados.
