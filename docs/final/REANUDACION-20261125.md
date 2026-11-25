# Reanudación de ARAT STUDIO

25 de noviembre de 2026 · America/Lima.

Se retomó la instalación local después del apagado. Los cambios y la evidencia del
24 de noviembre estaban guardados. No se detectó corrupción en los archivos,
recursos y datos comprobados. La causa del apagado del equipo no se evaluó.

Se conservó `main`, sin commit, push ni despliegue. No se repitieron la depuración,
la generación de demostraciones ni las migraciones de la instalación existente.

## Datos e integridad

El verificador de solo lectura confirmó cuatro cuentas del equipo con sus datos
originales, 16 solicitudes (15 de demostración y una conservada por origen dudoso),
nueve cotizaciones, ocho PDFs íntegros, 148 eventos de auditoría, 13 proyectos y
99 imágenes de galería. Los recuentos de todas las tablas coinciden con el cierre
anterior. No hay referencias huérfanas, trabajos pendientes o fallidos ni
migraciones pendientes (17 aplicadas).

Los 112 recursos visuales conservan sus hashes originales. Se verificaron también
los archivos del manifiesto de Vite. Las sumas SHA-256 del SQL y del ZIP del respaldo
privado coinciden con el recibo de la restauración comprobada el día 24. Hoy se
comprobó su integridad; no se volvió a restaurar ni a reemplazar la base local.

Detalle: [verificación de datos](data-resume-20261125.json).

## Comprobaciones nuevas

| Comprobación | Resultado del día 25 |
| --- | --- |
| PHPUnit con SQLite en memoria | 118 pruebas, 1.251 aserciones; cero errores, fallos u omisiones |
| Concurrencia en MariaDB aislada | Diez comprobaciones satisfactorias |
| Compilación Vite | Correcta, con el cargador `runner` del proyecto |
| Composer | `validate --strict` y requisitos de plataforma correctos con PHP 8.5.12 |
| Sintaxis PHP | 182 archivos sin errores |
| Vistas Blade | Compilación y limpieza correctas |
| JavaScript del recorrido | Sintaxis correcta |
| Espacios y conflictos del diff | `git diff --check` correcto |
| Recorrido de Chrome | Completo, sin errores registrados |

La integración creó `arat_concurrency_20261125_200130`, sin escribir en
`arat_studios`. Comprobó toma y asignación concurrentes, borradores y envíos
idempotentes, aceptación frente a emisión de PDF, vigencia tarifaria, desactivación
de gestores frente a asignaciones y agregación de tiempos en MariaDB.

El navegador usó SQLite y cuentas ficticias aisladas. Completó solicitud,
asignación, evaluación, observación, corrección, aprobación, borrador, emisión de
PDF, aceptación y cierre. También verificó repetición idempotente, bloqueo de la
decisión opuesta, vencimiento, consulta de versiones anteriores, regreso al panel,
menú con Escape y reflujo a 640 px.

Evidencia nueva, separada de la del día 24:

- [Resumen de comprobaciones](resume-20261125/checks-summary.json).
- [Resultado del recorrido](resume-20261125/workflow/checks.json).
- [Captura de aceptación y cierre](resume-20261125/workflow/07-aceptacion-y-cierre.png).
- [Captura móvil del expediente](resume-20261125/workflow/06-expediente-cliente-movil.png).
- [PDF ficticio generado](resume-20261125/workflow/propuesta-demostracion.pdf).
- JUnit local: `storage/framework/testing/resume-20261125.xml`.
- [Cierre de procesos](resume-20261125/teardown.json).

Las once capturas nuevas y el PDF están en `resume-20261125/workflow/`. No se
sobrescribieron las capturas ni los resultados anteriores.

## Ajustes de esta reanudación y arranque

El verificador admite `--output=data-resume-20261125.json` y calcula los recuentos
del lote y de la solicitud conservada mediante consultas. El recorrido admite
`ARAT_WORKFLOW_DIR` para separar la evidencia por ejecución. No hubo cambios
adicionales en las reglas comerciales ni en las fotografías o videos aprobados.

Se cerraron el servidor PHP de vista previa y MariaDB iniciados para esta revisión.
El arranque habitual sigue documentado en el [README](../../README.md): preparar
PHP con `. .\tools\use-php.ps1`, iniciar la base con
`.\tools\start-database.ps1` y ejecutar `composer dev` en otra terminal preparada.

El reflujo a 640 px no equivale a una prueba de zoom nativo del navegador. SMTP
real, Safari, Firefox, dispositivos físicos y producción siguen sin comprobarse.
El helper de MariaDB continúa siendo necesario para evitar la configuración de
replicación dañada de XAMPP sin modificar sus archivos originales.
