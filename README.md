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

- Solicitudes de proyectos con archivos o enlace externo.
- Notificaciones por correo.
- Gestión comercial y cotizaciones oficiales en PDF.
- Administración de tarifas.
- Galerías de imágenes por proyecto.
- Completar pruebas automatizadas.

## Nota

No subir `.env`, `vendor` ni `node_modules` al repositorio.
Los comandos indicados están preparados para PowerShell en Windows.
