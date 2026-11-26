<!doctype html>
<html lang="es"><head><meta charset="UTF-8"><style>
body { font: 12px/1.6 'DejaVu Sans', sans-serif; color: #2e2f36; }
h1 { font-size: 23px; color: #324853; }
.notice { padding: 14px; border: 2px solid #324853; }
table { width: 100%; border-collapse: collapse; margin-top: 25px; }
th, td { border: 1px solid #8c9ba0; padding: 12px; text-align: left; }
</style></head><body>
<p>ARAT STUDIO · Tercera presentación</p>
<h1>{{ $title }}</h1>
<p class="notice"><strong>DEMOSTRACIÓN ACADÉMICA</strong><br>Documento ficticio para demostrar entrega, revisión y conformidad. No es un plano ejecutivo ni acredita un servicio real.</p>
<p>Versión {{ $number }} · {{ $description }}</p>
<table><tr><th>Documento de ejemplo</th><th>Revisión académica</th></tr>
<tr><td>Propuesta de distribución</td><td>Organización de ambientes y criterios de uso del espacio.</td></tr>
<tr><td>Información de revisión</td><td>{{ $number === 1 ? 'Revisar claridad de anotaciones y medidas de referencia.' : 'Anotaciones de referencia aclaradas según la observación ficticia.' }}</td></tr>
<tr><td>Alcance</td><td>Ejemplo documental para la exposición del sistema.</td></tr></table>
<p>Este archivo pertenece al lote documental de demostración; sus versiones permanecen privadas en el sistema.</p>
</body></html>
