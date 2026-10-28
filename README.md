# ARAT STUDIO

Aplicación web para un estudio de arquitectura y diseño, desarrollada
con Laravel y arquitectura MVC.

## Tecnologías

- Laravel 13 y PHP 8.5.
- MariaDB mediante XAMPP.
- Composer, Node.js y npm.
- Blade, Bootstrap 5 y CSS.
- Kodchasan para títulos y Arial para textos.

## Preparar el entorno

Instalar:

- [XAMPP](https://www.apachefriends.org/download.html): MariaDB y phpMyAdmin.
- [Composer](https://getcomposer.org/download/).
- [Node.js LTS](https://nodejs.org/en/download).
- [Git](https://git-scm.com/downloads).
- [VS Code](https://code.visualstudio.com/).

El PHP 8.2 de XAMPP no es compatible con Laravel 13. Para utilizar
PHP 8.5 en Windows, seguir la instalación indicada en la
[documentación de Laravel](https://laravel.com/docs/13.x/installation).

Verificar en una nueva terminal:

```powershell
php -v
composer --version
node -v
npm.cmd -v
git --version
```

PHP y Composer deben utilizar PHP 8.5.

## Instalación del proyecto

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

## Base de datos

Iniciar Apache y MySQL en XAMPP. Entrar en
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

Si el usuario de la base de datos tiene contraseña, añadirla en
`DB_PASSWORD`.

Crear las tablas y cargar los proyectos de ejemplo:

```powershell
php artisan config:clear
php artisan migrate --seed
```

## Ejecutar

```powershell
php artisan serve
```

Abrir http://127.0.0.1:8000.

Mantener MySQL de XAMPP activo. Apache solo es necesario para
phpMyAdmin; la aplicación se ejecuta con el servidor de Laravel.

Para trabajar en los recursos de Vite, usar otra terminal:

```powershell
npm.cmd run dev
```

El CSS actual de ARAT está en `public/css/arat.css` y se sirve
directamente, sin necesidad de recompilarlo.

## Avance actual

- Portada y presentación del estudio.
- Proyectos consultados desde MariaDB.
- Página de detalle por proyecto.
- Vistas Blade con layout compartido.

Pendiente: autenticación, administración del portafolio, solicitudes
y cotizador.

## Nota

No subir `.env`, `vendor` ni `node_modules` al repositorio.