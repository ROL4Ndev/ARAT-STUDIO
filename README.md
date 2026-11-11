# ARAT STUDIO

Aplicación web para un estudio de arquitectura, diseño y visualización
3D. Desarrollada con Laravel y arquitectura MVC.

## Tecnologías

- Laravel 13 y PHP 8.5.
- MariaDB mediante XAMPP.
- Composer, Node.js y npm.
- Blade, Bootstrap 5 y JavaScript Fetch.
- Kodchasan para títulos y Arial para textos.

## Preparar el entorno

Instalar:

- [XAMPP](https://www.apachefriends.org/download.html).
- [Composer](https://getcomposer.org/download/).
- [Node.js LTS](https://nodejs.org/en/download).
- [Git](https://git-scm.com/downloads).
- [VS Code](https://code.visualstudio.com/).

Para instalar PHP 8.5 en Windows, seguir la
[documentación de Laravel](https://laravel.com/docs/13.x/installation).

Verificar en una nueva terminal:

```powershell
php -v
composer --version
node -v
npm.cmd -v
git --version
```

PHP y Composer deben utilizar PHP 8.5. Si instalaste PHP mediante
php.new y la terminal sigue utilizando el PHP de XAMPP:

```powershell
$env:Path = "$env:USERPROFILE\.config\herd-lite\bin;$env:Path"
```

Este ajuste se aplica únicamente a la terminal actual.

## Instalación

Se necesita acceso al repositorio privado.

```powershell
git clone https://github.com/ROL4Ndev/arat-studios.git
cd arat-studios
composer install
Copy-Item .env.example .env
php artisan key:generate
npm.cmd ci
npm.cmd run build
```

Copiar `.env.example` solo durante la instalación inicial; conservar
el `.env` existente si el proyecto ya está configurado.

## Base de datos

Iniciar Apache y MySQL en XAMPP. Abrir
http://localhost/phpmyadmin y ejecutar:

```sql
CREATE DATABASE IF NOT EXISTS arat_studios
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Configurar `.env`:

```dotenv
APP_NAME="ARAT STUDIO"
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=arat_studios
DB_USERNAME=root
DB_PASSWORD=
```

Si el usuario tiene contraseña, colocarla en `DB_PASSWORD`.

Crear las tablas y cargar los datos de demostración:

```powershell
php artisan config:clear
php artisan migrate --seed
```

Los seeders incluyen tres proyectos, sus categorías y los parámetros
tarifarios. `ProjectSeeder` actualiza los proyectos de ejemplo:
evitar ejecutarlo sobre contenido editado que se quiera conservar.

## Ejecutar

```powershell
php artisan serve
```

Abrir http://127.0.0.1:8000.

Mantener MySQL de XAMPP activo. Apache solo es necesario para
phpMyAdmin; Laravel utiliza su propio servidor de desarrollo.

El CSS y el JavaScript actuales se cargan directamente desde
`public/css/arat.css` y `public/js/calculator.js`. No requieren
ejecutar Vite para visualizar estos cambios.

## Funcionalidades actuales

- Portada y presentación del estudio.
- Portafolio paginado con filtros por categoría.
- Detalle de proyectos mediante slug.
- Categorías relacionadas y proyectos activos o inactivos.
- Cotizador referencial sin recargar la página.
- Validaciones mediante Form Requests.
- Cálculo en un servicio con tarifas consultadas desde MariaDB.
- Inicio y cierre de sesión, perfiles editables y acceso por roles
  (`admin` y `gestor`).
- CRUD administrativo de categorías y proyectos, protegido para `admin`.
- Portadas cargadas en el disco público de Laravel; se conservan las
  imágenes anteriores de `public/img`.
- Interfaz y validaciones en español de Perú; zona horaria de Lima.

### Rutas principales

| Método | Ruta | Función |
|---|---|---|
| GET | `/` | Inicio |
| GET | `/proyectos` | Portafolio y filtros |
| GET | `/proyectos/{slug}` | Detalle de proyecto |
| GET | `/cotizador` | Calculadora |
| GET, POST | `/ingresar` | Formulario e inicio de sesión |
| POST | `/salir` | Cierre de sesión |
| GET, PUT | `/perfil` | Editar perfil |
| PUT | `/perfil/contrasena` | Cambiar contraseña |
| GET | `/panel` | Redirección según el rol |
| GET | `/admin` | Panel exclusivo de administración |
| GET | `/gestion` | Panel de administración y gestión |
| GET, POST, PUT, DELETE | `/admin/categorias` | CRUD de categorías |
| GET, POST, PUT | `/admin/proyectos` | CRUD de proyectos y búsqueda |
| POST | `/api/calcular-presupuesto` | Cálculo en JSON |

### Cuentas internas

Después de configurar la base de datos, aplicar las migraciones existentes
sin borrar datos:

```powershell
php artisan migrate
```

Crear las cuentas internas de forma interactiva; el comando solicita
nombre, correo, rol y contraseña confirmada (la contraseña no se muestra):

```powershell
php artisan usuarios:crear-interno
```

Elige `admin` o `gestor`. No existe registro público. El administrador
accede a `/admin` y `/gestion`; el gestor solo puede acceder a `/gestion`.
Las cuentas existentes reciben rol nulo al migrar, sin privilegios nuevos.

Iniciar sesión en `/ingresar`. Desde la barra de navegación se puede abrir
Mi perfil o cerrar sesión. El perfil permite actualizar nombre, correo y
contraseña; el rol no es editable.

### Catálogo administrativo

Aplicar migraciones sin reiniciar la base y crear el enlace para las portadas
cargadas por administradores:

```powershell
php artisan migrate
php artisan storage:link
```

Inicia sesión con una cuenta `admin` y abre `/admin`. Desde allí se gestionan
categorías y proyectos; los gestores reciben 403 en todas las rutas del CRUD.
Las categorías asociadas a proyectos no se pueden eliminar. Los proyectos se
desactivan desde su formulario y dejan de aparecer en el sitio público.

La migración de transición crea categorías a partir del campo heredado de cada
proyecto y luego retira dicho campo. Los archivos existentes en `public/img`
siguen siendo válidos; las nuevas portadas se guardan en `storage/app/public`
mediante el disco `public`.

## Cotizador de demostración

Fórmula:

`Área × tarifa base por m² × factor LOD`

| Nivel | Tarifa base | Factor |
|---|---:|---:|
| LOD 200 | S/ 100 | 1.00 |
| LOD 300 | S/ 100 | 1.35 |
| LOD 400 | S/ 100 | 1.75 |

El límite automático inicial es 500 m². Si el área lo supera,
el endpoint devuelve `out_of_scale: true` y un importe nulo.

Ejemplo: 80 m² en LOD 300 produce S/ 10,800.

Estos valores son de demostración y no constituyen una cotización
comercial aprobada.

## Pendiente

- Administración de tarifas.
- Galerías de imágenes por proyecto.

## Solicitudes y cotizaciones oficiales

Flujo: visitante en `/cotizador` → **Solicitar proyecto** → registro sin
sesión → gestor o admin en `/gestion` → evaluación → aprobación → emisión
de cotización PDF. También se puede abrir `/solicitar-proyecto` directamente.
El cotizador conserva área, LOD y tipo de proyecto al abrir el formulario.

La estimación usa Brick Math 1.0.0, redondeo decimal a dos posiciones y
tarifas demostrativas. El servidor recalcula al registrar y guarda una copia
de la tarifa; sobre el límite guarda estimación nula y evaluación personalizada.
El importe oficial se define tras evaluar el alcance y puede ser diferente.

Se añadieron modelos `ServiceRequest`, `ServiceRequestHistory` y `Quotation`;
controladores en `PublicSite` y `Commercial`; Form Requests, regla de adjuntos,
servicios de registro, estados y emisión; Mailable; vistas, JavaScript y pruebas.
Las rutas y navegación existentes se conservan. Los CRUD continúan solo para admin.

Aplicar la migración aditiva (ya aplicada en este entorno), sin seeders:

```powershell
composer install
php artisan migrate --path=database/migrations/2026_11_11_180000_create_commercial_tables.php
```

Dependencias directas: `barryvdh/laravel-dompdf` 3.1.2 (compatible con
Illuminate 13), `dompdf/dompdf` 3.1.6 como dependencia transitiva y `brick/math`
1.0.0, que ya estaba instalado y ahora se declara directamente.

### Archivos y correo locales

El disco `commercial` usa `storage/app/commercial`, sin publicación ni URL
firmada pública. Laravel necesita permisos de escritura allí y en `storage`.
No crear un enlace público a esa carpeta. El `storage:link` descrito arriba
es únicamente para las portadas públicas del catálogo.
PDF, JPG, JPEG, PNG, DWG y ZIP admiten hasta 50 MiB (52 428 800 bytes).
Se validan extensión y contenido; no se descomprimen ZIP. La descarga pasa
por controladores autorizados. Solo PDF e imágenes admiten vista previa.

Configurar manualmente el `php.ini` del servidor que atiende Laravel:

```ini
upload_max_filesize = 50M
post_max_size = 64M
```

Reiniciar el servidor tras cambiarlos. `php --ini` muestra el archivo usado
por CLI; Apache puede utilizar otro. Revisar también los límites del proxy
o servidor web si existen. No se modificó automáticamente PHP.
JavaScript retira archivos mayores y exige enlace; Laravel vuelve a validar.
Una petición que excede `post_max_size` recibe una página controlada 413.

En desarrollo usar `MAIL_MAILER=log` (valor de ejemplo y predeterminado):
la confirmación se registra en `storage/logs/laravel.log`, sin entrega real.
La interfaz confirma únicamente el registro de la solicitud. Un fallo de
correo se registra para diagnóstico y no revierte ni duplica la solicitud.
Los datos del estudio usados como snapshot se editan en `config/commercial.php`.

Las solicitudes e historial se guardan juntos. Los reintentos del formulario
usan un token único ligado a la sesión y restricción única en BD. Los estados
se cambian con bloqueo de fila y verificación del estado anterior; los finales
no retroceden. Cada solicitud admite una cotización, protegida también por
índice único. El PDF se guarda antes de confirmar la transacción y se limpia
ante fallos; el generador no admite recursos remotos, PHP ni JavaScript.
La vigencia termina al finalizar la fecha de vencimiento, 15 días tras emitir.

### Verificación

```powershell
php artisan test
node --check public/js/calculator.js
node --check public/js/service-request.js
git diff --check
```

Resultado de esta entrega: 46 pruebas, 345 aserciones, todas correctas.
Las pruebas usan exclusivamente SQLite en memoria, almacenamiento falso y
correo falso; la base de pruebas rechaza ejecutar la suite sobre otra conexión.
Incluyen regresión de autenticación, perfil y CRUD, archivos, permisos,
estados, snapshots, duplicados, errores de disco/BD/correo y PDF real.
La migración se aplicó correctamente en MariaDB sin reiniciar datos.
Se verificó el recorrido completo en Chrome con datos ficticios sobre MariaDB:
registro con PDF, reenvío sin duplicado, evaluación, aprobación, descarga,
fuera de escala, rechazo y permisos. Se revisaron capturas del sitio y PDF
de una y tres páginas. La cuenta ficticia quedó sin rol y con clave rotada;
los dos registros ficticios se conservaron para trazabilidad. No se probó
carga simultánea contra MariaDB. Ver [informe de cierre](VERIFICACION_ENTREGA.md).

Se actualizaron únicamente `symfony/http-foundation`, `symfony/http-kernel`
y `symfony/routing` de 8.1.10 a 8.1.11. `composer audit` ya no reporta avisos.
El PHP 8.5 activo de Herd Lite quedó configurado con límites 50M/64M.
La carga real de 49 MiB se confirmó y la de 51 MiB se rechazó. El servidor
integrado de PHP 8.5.12 presenta una limitación al superar 64 MiB: devuelve
una advertencia de arranque con HTTP 200 antes de Laravel, sin guardar datos.
Ver el informe de cierre para esta limitación y los resultados detallados.

## Nota

No subir `.env`, `vendor` ni `node_modules` al repositorio.
Los comandos indicados están preparados para PowerShell en Windows.
