# Capturas para Canvas — segunda presentación

Fuente: PCAL-S13-PVERGEL-2026-02, página 4. Las capturas indicadas existen en
`storage/framework/testing/`; corresponden a pruebas reales con datos ficticios.
La portada MR del servidor de trabajo se comprobó además en 8000. Cada imagen
debe llevar un título breve en el documento final.

| Orden | Captura y título sugerido | Archivo disponible |
|---|---|---|
| 1 | Home completo y proyectos publicados | `guide-home-desktop.png`, `guide-real-mr-1440-home.png` |
| 2 | Nosotros: enfoque y equipo | `guide-info-nosotros.png` |
| 3 | Servicios obtenidos del catálogo activo | `guide-info-servicios.png` |
| 4 | Contacto y canales del estudio | `guide-info-contacto.png` |
| 5 | Proyectos: listado con nombres de categorías | `guide-proyectos-list.png` |
| 6 | Proyectos: registro con portada y categoría | `guide-proyectos-create.png` |
| 7 | Proyectos: edición de ficha existente | `guide-proyectos-edit.png` |
| 8 | Tarifas: listado de versiones y estados | `guide-tarifas-list.png` |
| 9 | Tarifas: registro de un borrador | `guide-tarifas-create.png` |
| 10 | Tarifas: edición y borrador sin sustituir la vigente | `guide-tarifas-edit.png`, `guide-tariffs-published-edit.png`, `guide-tariffs-new-draft.png` |
| 11 | Cotizador referencial: 80 m² / LOD 300 | `guide-calculator.png` |
| 12 | Solicitud registrada con código público | `guide-registered.png` |
| 13 | Responsable asignado e inicio de evaluación | `guide-evaluation.png` |
| 14 | Observación del gestor y acceso verificado del cliente | `guide-observed.png`, `guide-client-observed.png` |
| 15 | Corrección del cliente conservando adjuntos | `guide-client-correction-mobile.png` |
| 16 | Aprobación técnica del expediente | `guide-approved.png` |
| 17 | Cotización v1: borrador y emisión | `guide-v1-draft.png`, `guide-quote-v1.png` |
| 18 | Cotización v2: borrador y emisión | `guide-v2-draft.png`, `guide-quote-v2.png` |
| 19 | PDF privado descargado: ejemplo completo de v1 | `guide-v1.pdf.png` (PDF real de una página; v2 se conserva en `guide-v2.pdf`, de dos páginas) |
| 20 | Propuesta vigente, aceptación y cierre | `guide-client-proposal-mobile.png`, `guide-client-accepted-mobile.png` |
| 21 | Historial final del expediente | `guide-history-closed.png` |

Como respaldo opcional están el menú móvil (`guide-menu-390.png`), las páginas
informativas a 390 px, el perfil (`guide-profile-saved.png`), los errores junto
al campo (`guide-projects-field-errors.png`, `guide-tariffs-field-errors.png`),
la auditoría (`guide-crud-proyectos.png`, `guide-crud-tarifas.png`) y el reintento
de PDF (`guide-pdf-failed.png`). DELETE es opcional y no se expone como función.

Antes de entregar:

- [ ] Añadir portada: número del grupo, ARAT STUDIO, apellidos y nombres de cada integrante.
- [ ] Insertar y titular las capturas en el orden Home → informativas → Proyectos → Tarifas → proceso principal.
- [ ] Usar datos ficticios; excluir logs, contraseñas, enlaces con tokens y capturas de diagnóstico `guide-failure-*` o del fallo inicial de MR.
- [ ] Añadir la URL del repositorio: `https://github.com/ROL4Ndev/ARAT-STUDIO`. Esta revisión permanece local; no se hizo commit ni push.
- [ ] Exportar a Word `.docx` o PDF y revisar legibilidad, especialmente tablas y pantallas largas.
- [ ] Cada integrante debe subir su copia a su propia aula Canvas. El documento final y su entrega no se han realizado aquí.
- [ ] Ensayar 10–15 minutos con todos los integrantes y reservar preguntas sobre rutas, controladores, relaciones, permisos y cola.
