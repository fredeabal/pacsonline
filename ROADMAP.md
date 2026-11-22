# Roadmap — Sistema PACS Online y Segunda Opinión Médica

Este documento define las fases para construir una plataforma PACS en la nube capaz de recibir estudios DICOM desde dispositivos externos, permitir que el usuario comparta casos mediante credenciales seguras con otros médicos y gestionar un flujo de segunda opinión por parte del equipo médico.

---

## Fase 0 — Definición, Arquitectura y Cumplimiento (Semana 1)

**Objetivo:** Establecer las reglas de negocio, seguridad y entorno técnico antes del desarrollo.

- [x] **Roles y permisos:** Capacidades definidas en `AuthGroups` para `admin`, `medico`, `paciente` y acceso de médico externo (invitado) mediante enlace compartido.
- [ ] **Marco legal y privacidad:** Definir requisitos normativos aplicables (RGPD/GDPR, HIPAA o normativa local): consentimiento informado, retención de datos y cifrado.
- [x] **Stack técnico definido:**
  - **Contenedores / Infraestructura:** Docker Compose (local en Mac y desplegable en Proxmox VM/LXC).
  - **Motor PACS:** Orthanc (con índice en **PostgreSQL 16** y almacenamiento DICOM en disco externo `ext4`).
  - **Visor web:** OHIF Viewer (Cornerstone3D).
  - **Backend / Portal:** PHP 8 + CodeIgniter 4 + SQLite (con migraciones listas para pasar a PostgreSQL tras finalizar la ingesta histórica).
  - **Proxy interno del stack:** Nginx (enruta `/`, `/viewer/` y `/dicom-web/` detrás de tu propio reverse proxy con TLS).

---

## Fase 1 — Núcleo PACS + Visor Web (Semanas 2–3)

**Objetivo:** Contar con un PACS funcional en Docker para subir estudios y visualizarlos desde el navegador.

- [x] Crear `docker-compose.yml` con Orthanc (SQLite + volumen local), OHIF Viewer, PHP/CodeIgniter 4 y Nginx.
- [x] Configurar **DICOMweb** (`QIDO-RS`, `WADO-RS`, `STOW-RS`) entre OHIF y Orthanc.
- [x] Implementar subida manual de archivos `.dcm` desde PHP hacia Orthanc vía API REST.
- [x] Validar visualización con estudios de prueba (verificado en OHIF Viewer v3).

**Hito:** Subir un estudio DICOM desde el navegador y abrirlo en OHIF con herramientas de medición y ajuste de ventana/contraste. [COMPLETADO]

---

## Fase 2 — Usuarios, Proxy Seguro y Compartición por Credenciales (Semanas 4–6)

**Objetivo:** Proteger el acceso a los estudios y habilitar la compartición controlada con otros médicos.

- [x] **Proxy de autorización:** Bloqueado acceso directo a Orthanc mediante `auth_request` en Nginx hacia [DicomAuthController.php](file:///Users/esweb/Documents/pacs/app/app/Controllers/DicomAuthController.php) validando sesión o enlace compartido por `StudyInstanceUID`.
- [x] **Autenticación y RBAC:** CodeIgniter Shield configurado con roles (`admin`, `radiologo`, `medico`, `paciente`), CSRF activo y plantilla azul (`deca`).
- [x] **Módulo de compartición:**
  - [x] Generación de enlace único + **PIN** (hasheado) para un estudio específico.
  - [x] Configuración de fecha de expiración (24h, 7d, 30d, sin caducidad).
  - [x] Revocación manual de accesos en cualquier momento.
- [x] **Auditoría (*Audit Log*):** Registro de subidas, creación/revocación de enlaces, intentos de PIN y accesos en `audit_logs`.

**Hito:** Un usuario sube un caso, genera credenciales temporales y un médico externo accede únicamente a ese estudio. [COMPLETADO]

---

## Fase 3 — Flujo de Segunda Opinión Médica (Semanas 7–9)

**Objetivo:** Digitalizar la solicitud, revisión médica y entrega de informes de segunda opinión.

- [x] **Solicitud de segunda opinión:**
  - [x] Ficha del caso (`/studies/case/<uid>`) con asignación de paciente, médico informante, prioridad y subida **multiarchivo** de adjuntos clínicos (PDF, PNG, JPG) con eliminación por paciente/médico/admin.
  - [x] Motivo de consulta y síntomas editable por el paciente en la ficha del caso y visible en el editor de informes.
  - [x] Visualización del informe médico publicado debajo de *Motivo de consulta* y acceso al PDF oficial dentro de *Documentos clínicos (Informes / Analíticas)*.
- [x] **Worklist del equipo médico:** Bandeja de casos (`ReportController`) con filtros por estado (`Pendiente`, `En revisión`, `Informado`), asignación (`Mis asignados`), modalidad, búsqueda por paciente/ID, paginación (100/pág), `PatientID` debajo del nombre y apertura directa a la ficha del caso.
- [x] **Módulo de informes:**
  - [x] Editor de informe médico con plantillas personales y por defecto según modalidad (`TemplateController`) y borrado de informe por médico autor o administrador.
  - [x] Generación automática de **PDF** (`ReportPdfService`) con hoja membretada A4 de **CAREMA**, datos del estudio (con fallback automático a Orthanc), código QR y firma del especialista.
- [ ] **Notificaciones automáticas:**
  - [x] Aviso por email (SMTP configurable) al paciente con PDF adjunto al publicar el informe y al médico informante al asignarle un caso (`NotificationService`).
  - [ ] Canal opcional WhatsApp/SMS.
- [ ] *(Opcional)* Integración de pasarela de pagos si el servicio de segunda opinión se comercializa por caso.

**Hito:** Flujo completo operativo: estudio recibido → asignado a especialista → informe en PDF publicado → usuario notificado. [OPERATIVO — PENDIENTE DE SOLICITUD POR PACIENTE]

---

## Fase 4 — Recepción Automática desde Otros Dispositivos (Semanas 10–12)

**Objetivo:** Conectar equipos médicos y estaciones externas de forma segura sin exponer puertos DICOM sin cifrar a internet.

- [x] **Agente / Gateway local:** Paquete ligero en `gateway/` (`docker-compose.yml` + `forwarder.lua`) que recibe por `C-STORE` en red local y reenvía por HTTPS a `/api/ingest/dicom` con Bearer Token.
- [ ] **Soporte VPN / DICOM TLS:** Opción de túneles WireGuard/Tailscale o `C-STORE` sobre TLS con certificados mutuos.
- [x] **Reglas de enrutamiento:** Gestión de centros médicos (`CenterController`, `medical_centers`) con `AE Title` y token rotatorio para asociar automáticamente estudios entrantes (`IngestController`).
- [ ] **Monitor de ingesta:** Registro de `last_seen_at` y contador de estudios por centro implementado; pendiente panel de colas, reintentos y errores de transmisión.

**Hito:** Un equipo externo envía un estudio y aparece automáticamente en el portal listo para compartir o solicitar segunda opinión. [EN PROGRESO]

---

## Fase 5 — Endurecimiento, Calidad y Producción (Semanas 13–14)

**Objetivo:** Preparar la plataforma para operar en producción con datos clínicos reales.

- [ ] **Anonimización / Seudonimización:** Opción para limpiar etiquetas DICOM sensibles al compartir o exportar casos.
- [ ] **Backups automáticos y cifrados:** Descarga manual de BD SQLite implementada en `SettingController`; pendiente respaldo periódico automático y cifrado junto al almacenamiento DICOM con prueba de restauración.
- [ ] **Escalado y rendimiento:**
  - [x] Eliminar consultas $N+1$ en listados (`mapByStudyUids`, `countActiveByStudyUids`), filtrar usuarios por grupo en SQL, usar `/tools/find` en Orthanc y *streaming* en descarga ZIP.
  - [x] Paginación (100/pág, estilo Bootstrap flechas) y búsqueda en servidor por `PatientName` / `PatientID` en `/studies` y `/reports`, con tablas de ancho fijo sin scroll horizontal.
  - [x] Índice de Orthanc migrado a **PostgreSQL 16** y script de migración histórica desde Horos/OsiriX (`scripts/import_horos.py`) con almacenamiento en disco `ext4` externo.
  - [ ] Migrar BD de CodeIgniter 4 (`pacs.sqlite`) a PostgreSQL (y archivos DICOM a S3/MinIO si aplica) tras completar la ingesta histórica.
- [ ] **Observabilidad:** Endpoint `/health` implementado; pendiente logs centralizados, métricas y alertas de almacenamiento.
- [x] **Pruebas de seguridad (Endurecimiento inicial):**
  - [x] Aislamiento estricto de estudios por `StudyInstanceUID` en `/dicom-web/` (denegación `403` a pacientes sin UID explícito).
  - [x] *Rate limiting* en verificación de PIN de enlaces compartidos (`throttler`).
  - [x] Validación real de MIME type (`finfo`) en adjuntos clínicos y envío de credenciales por correo en lugar de texto plano en UI.
- [ ] **Documentación:** Guías rápidas para pacientes, médicos remitentes y equipo de radiología.

---

## Orden de Prioridades Recomendado

| Prioridad | Bloque | Motivo |
| :--- | :--- | :--- |
| **1. Core visual** | Orthanc + OHIF + subida web | Validar rápido el visor y compatibilidad de estudios |
| **2. Valor inmediato** | Cuentas + compartición con PIN + segunda opinión | Operar el servicio recibiendo estudios vía web desde el día uno |
| **3. Automatización** | Gateway `C-STORE` → `STOW-RS` | Conectar dispositivos físicos sin bloquear el lanzamiento |
