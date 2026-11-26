# Resultados de verificación

Corte local del 25 de noviembre de 2026, America/Lima. No se hizo commit, push ni
despliegue. [Guía del proceso](README.md) · [Galería](index.html).

## Ejecuciones

| Comprobación | Resultado obtenido |
| --- | --- |
| Suite completa existente y nuevas regresiones | **132 pruebas, 1.395 aserciones**, cero errores, fallos u omisiones |
| Pruebas del nuevo flujo | 13 casos de PHPUnit, incluidos archivos, permisos, inmutabilidad, repetición y rollback |
| Concurrencia comercial existente | Diez comprobaciones satisfactorias en `arat_concurrency_20261126_042241` |
| Concurrencia documental | Seis carreras satisfactorias en `arat_delivery_concurrency_20261126_042300` |
| Navegador | Flujo completo con cuatro sesiones separadas; 20 capturas del recorrido y catálogos |
| Teclado y respaldo sin JS | Secuencia de Tab y foco a 390 px; formulario completo sin JS a 360 px |
| PDF nativo | Cinco documentos abiertos en el visor de Chrome, incluidos los cuatro archivos de los escenarios habituales |
| Frontend | `npm.cmd run build` correcto, sin paquetes nuevos; hashes de CSS y JS aprobados conservados |
| PHP | 201 archivos comprobados, sin errores de sintaxis |
| Blade y rutas | Vistas compiladas y limpiadas; ocho rutas nuevas revisadas |
| Integridad real | 532 filas anteriores y 239 archivos conservados, cuatro cuentas, lote de quince solicitudes intacto, cero referencias huérfanas |
| Demostración | Tres entregables y cuatro versiones privadas; repetición del comando sin cambios |
| Servidor de prueba | Sin PHP Fatal/Warning/Notice, excepciones sin capturar ni errores SQL en su salida |

El JUnit íntegro está en `storage/framework/testing/third-presentation.xml`.
Las evidencias legibles están en [browser.json](browser.json),
[concurrency.json](concurrency.json),
[commercial-concurrency.json](commercial-concurrency.json),
[accessibility.json](accessibility.json), [pdfs.json](pdfs.json) y
[data-verification.json](data-verification.json). Los IDs numéricos de los JSON
de concurrencia son referencias técnicas de las bases aisladas, no contenido de
las pantallas.

## Escenarios, acciones y resultados

| Rol y acción | Resultado esperado | Obtenido / evidencia |
| --- | --- | --- |
| Gestor asignado crea título, descripción y archivo | Borrador privado, aún no disponible para cliente | Correcto; capturas 01 y 02 |
| Cliente intenta descargar borrador | 403, sin notas internas | Correcto; navegador y PHPUnit |
| Gestor envía v1 | En revisión, archivo descargable | Correcto; capturas 03 y 04 |
| Cliente observa sin comentario después de descargar | Volver al expediente, error junto al campo | Correcto; captura 05 y regresión específica |
| Cliente registra observación | Comentario y fecha sobre v1 | Correcto; captura 06 |
| Gestor consulta y guarda v2 | v1 intacta; v2 borrador oculta al cliente | Correcto; capturas 07 y 08 |
| Gestor envía v2 | Última versión disponible, v1 conservada | Correcto; captura 09; descarga v1 con el mismo hash |
| Cliente responde sobre v1 sustituida | 409 sin sobrescribir historial | Correcto; navegador, PHPUnit |
| Cliente da conformidad a v2 | Confirmación explícita, estado final y fecha | Correcto; capturas 10 y 11 en tres anchos |
| Cliente repite conformidad / intenta decisión opuesta | Repetición sin duplicado / 409 para la opuesta | Correcto; navegador y carreras MariaDB |
| Gestor intenta editar conforme | Sin acción de edición y rechazo del servidor | Correcto; captura 12 y PHPUnit |
| Administrador consulta | Autorizado | Correcto; captura 13 |
| Administrador abre cuatro catálogos | Proyectos, categorías, servicios y tarifas conservados | Correcto; capturas 14 y suite de catálogos existente |
| Gestor ajeno / personal interno en ruta de cliente | 403 | Correcto; captura 15 y navegador |
| Cuenta desactivada o Grant vencido/revocado/de otro expediente | Sin operación ni descarga autorizada | Correcto; PHPUnit |
| Dos guardados de la misma versión / versiones competidoras | Una versión / un ganador con conflicto controlado | Correcto; MariaDB aislada |
| Conformidad frente a nueva versión simultánea | Conforme final, sin nueva versión incompatible | Correcto; MariaDB aislada |
| Fallo de auditoría durante creación, guardado, envío o respuesta | Rollback, archivo anterior intacto y carga nueva sin referencia eliminada | Correcto; PHPUnit |

Se comprobaron archivos PDF, PNG y JPEG válidos; formatos prohibidos, extensión
falsa, PDF incompleto, archivo alterado y exceso de tamaño. El JPEG de prueba es
un archivo pequeño real: no requiere que PHP incluya un codificador JPEG para
fabricarlo durante la prueba. Las validaciones de producción usan contenido/MIME,
no ese generador de pruebas.

## Pantallas y archivos

Las capturas del recorrido están ordenadas por paso en la galería. Las de cliente
son capturas de su sección Entregables; las internas muestran la pantalla completa.
Se comprobaron 360, 390, 768 y 1440 px sin desbordamiento horizontal del documento.
Las capturas 16 y 19–22 muestran el visor PDF nativo. No son imágenes dibujadas
ni documentos simulados en HTML.

Los PDFs de `archivos/` son únicamente ejemplos académicos identificados. Las
contraseñas de los gestores ficticios, enlaces temporales, configuración y backups
siguen en almacenamiento privado ignorado por Git. No se copiaron contraseñas
reales a las bases de navegador.

## Límites y estado final

Se usó Chrome 158 local. No se probaron Safari, Firefox, dispositivos físicos,
lectores de pantalla, SMTP real ni producción. El proceso documental no depende
de correo nuevo: un envío queda disponible al cliente aunque no exista transporte
de avisos configurado. Los errores antiguos de preparación se conservan en sus
logs privados; el recorrido final terminó sin errores de aplicación registrados
por el navegador.

Las bases de restauración y concurrencia son separadas y se conservaron como
evidencia. No sustituyen a `arat_studios`. No se reiniciaron los catálogos ni el
lote Faker. El cierre de procesos propios y conservación de los servicios que
ya estaban encendidos se documenta en [teardown.json](teardown.json).

No quedan pendientes que impidan recorrer el flujo completo local. El estado
Conforme es definitivo; para otra práctica se crea un entregable académico nuevo,
sin borrar ni reabrir los finalizados.
