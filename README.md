# PACSline — Plataforma PACS Online + Segunda Opinión Médica

Plataforma PACS basada en **Orthanc** (con índice **PostgreSQL 16**), **OHIF Viewer**, **PHP 8 / CodeIgniter 4** (**PostgreSQL 16**), **Whisper local** (dictado por voz) y **Nginx**, desplegada con Docker Compose.

Consulta el plan y progreso en [ROADMAP.md](./ROADMAP.md).

## Estructura del proyecto

```text
.
├── app/                  # Aplicación PHP (CodeIgniter 4 + Shield + Dompdf)
├── docker/
│   ├── nginx/            # Proxy inverso (Portal + /viewer/ + /dicom-web/ con auth_request)
│   ├── ohif/             # Configuración de OHIF Viewer (app-config.js)
│   ├── php/              # Dockerfile PHP 8.3-FPM + PostgreSQL + SQLite + GD + Zip + Composer
│   └── whisper/          # Servicio local Whisper (FastAPI/CPU) para transcripción médica
├── gateway/              # Agente local C-STORE -> STOW-RS (forwarder.lua)
├── scripts/
│   ├── backup.sh         # Respaldo cifrado de BD + datos de Orthanc
│   └── import_horos.py   # Importador reanudable de Horos/OsiriX -> Orthanc
├── .env.example          # Plantilla de variables de entorno
├── .gitignore
└── docker-compose.yml    # Servicios: postgres, orthanc, ohif, php, nginx, whisper
```

## Funcionalidades de la aplicación

### 1. Gestión de estudios DICOM y Visor OHIF (`/studies`)
- **Listado paginado y búsqueda en servidor** por nombre de paciente, `PatientID`, modalidad (`CT`, `MR`, `DX`, `US`, etc.), rango de fechas, centro remitente y estado de informe.
- **Visor web integrado (OHIF Viewer)**: apertura individual o **comparación lado a lado** con estudios previos del mismo `PatientID` (`ohif_compare_url`).
- **Subida manual de estudios** (`.dcm` / `.zip`) y **edición de etiquetas DICOM** en Orthanc (`/studies/case/<uid>/dicom`).
- **Ficha del caso (`/studies/case/<uid>`)**: resumen clínico, asignación de médico informante, prioridad (`Normal` / `Urgente`), motivo de consulta/antecedentes, **documentos clínicos** (informe oficial o borrador, documentos DICOM encapsulados y adjuntos PDF/JPG/PNG) y tarjeta de **estudios previos del paciente** con vista rápida de informes anteriores y cita directa en antecedentes.

### 2. Informes médicos, dictado por voz y PDF (`/reports`)
- **Worklist médico** con bandejas (`Mis asignados` / `Todos`), filtros por estado (`Pendiente`, `En revisión`, `Informado`), prioridad y acciones rápidas (editar borrador/informe, vista previa PDF, abrir OHIF y ficha del caso).
- **Editor unificado (Quill)** con plantillas personales por modalidad (`/templates`) y **dictado por voz offline** mediante servicio **Whisper local** (`docker/whisper`), sin enviar audio a APIs externas.
- **Flujo de firma médica compatible con LOPDGDD**:
  - Separación entre **autoría/transcripción** (`created_by_user_id`, `updated_by_user_id`) y **firma médica** (`doctor_user_id`, permiso `reports.sign` exclusivo del rol `medico`).
  - Supervisores o transcriptores pueden redactar y guardar **borradores** (`in_review`), pero **solo el médico asignado** (o un usuario con rol `medico` si no hay asignación previa) puede **firmar y publicar**.
  - Un informe publicado queda bloqueado frente a ediciones de terceros; solo el médico firmante puede modificarlo.
- **Generación de PDF (`ReportPdfService`)**:
  - Cabecera corporativa con logo **pacsline**, tarjeta de datos del estudio (`NOMBRE`, `ESTUDIO`, `REFERIDO`, `N°`, `FECHA`), código QR para acceso directo a imágenes DICOM, firma digitalizada del médico (solo en informes publicados), pie de página con numeración (`Página X de Y`) y marca de agua diagonal **BORRADOR** en informes no publicados.

### 3. Compartición segura y portal de paciente (`/studies/patient-access`, `/paciente/<token>`)
- Generación de **enlace único + PIN** (hasheado) con caducidad configurable (`24h`, `7d`, `30d` o sin caducidad) y revocación manual.
- *Rate limiting* en la verificación del PIN y aislamiento estricto por `StudyInstanceUID` en `/dicom-web/` mediante `auth_request` en Nginx.
- Descarga de informe oficial en PDF y acceso a imágenes en OHIF para el paciente o médico externo.

### 4. Usuarios, perfiles, centros y auditoría (`/users`, `/profile`, `/centers`, `/settings`)
- **Control de acceso (RBAC con Shield)**: grupos `admin` (Supervisor), `medico`, `usuario` y `observador`.
- **Aislamiento por centro (`center_id`)**: los usuarios asociados a un centro remitente solo ven y gestionan estudios de su centro.
- **Perfil médico (`/profile`)**: nombre completo, especialidad, nº de colegiado y subida de **firma digital** (PNG/JPG) para informes PDF.
- **Auditoría (`audit_logs`)**: registro de subidas, asignaciones, borradores, publicaciones/firmas, descargas de PDF, enlaces compartidos e intentos de acceso.
- **Notificaciones por email (`NotificationService`)**: aviso al médico al asignarle un caso y al paciente con PDF adjunto al publicar el informe.
- **Monitorización y mantenimiento (`/settings`, `/health`)**: estado de Orthanc/PostgreSQL/Whisper, alertas de disco y respaldo de base de datos.

## Despliegue con Docker Compose

1. Clona este repositorio en el servidor y entra a la carpeta:
   ```bash
   git clone <URL_DE_TU_REPO> pacs
   cd pacs
   ```

2. Crea tu archivo `.env` local (nunca lo subas a Git) y cambia las contraseñas por defecto (`ORTHANC_PASSWORD`, `ORTHANC_DB_PASSWORD`, etc.):
   ```bash
   cp .env.example .env
   ```

3. Levanta los contenedores:
   ```bash
   docker compose up -d --build
   ```

4. Ejecuta las migraciones iniciales de CodeIgniter 4 (si es una instalación nueva):
   ```bash
   docker exec pacs-php php spark migrate --all
   ```

5. Abre en tu navegador `http://<IP_DEL_SERVIDOR>:8080`:
   - Portal de estudios (`/studies`), worklist médico (`/reports`), compartición por enlace + PIN, informes PDF con membrete y visor **OHIF Viewer** (`/viewer/`).

## Seguridad DICOM y Centros Remitentes (`/centers`)

El servidor Orthanc aplica restricción de acceso sobre el puerto DICOM (`11112` / `4242`) mediante:

1. **Validación de AE Title destino (`ORTHANC__DICOM_CHECK_CALLED_AET=true`):**
   - El equipo o visor emisor debe especificar exactamente el AE Title del PACS (`PACSONLINE`, definido en `ORTHANC_AET`). Cualquier petición dirigida a otro AE Title es rechazada.
2. **Lista blanca de centros emisores (`ORTHANC__DICOM_ALWAYS_ALLOW_STORE=false` y `ORTHANC__DICOM_CHECK_MODALITY_HOST=false`):**
   - Solo se aceptan envíos (`C-STORE`) cuyo **Calling AET** (AE Title del equipo emisor) esté registrado en `/centers`, activo (`active = 1`) y con la opción **Recibir estudios** (`can_receive`) habilitada.
   - Al crear, editar o eliminar un centro en `/centers`, el portal sincroniza automáticamente la modalidad `center_<id>` en Orthanc con sus permisos (`AllowStore`, `AllowEcho`, etc.).

### Configuración en un visor o modalidad externa (ej. Horos / OsiriX)
- **Dirección:** IP o dominio del servidor (ej. `pacs.deabreu.es`)
- **Puerto:** `11112`
- **AE Title destino (Called AET):** `PACSONLINE`
- **AE Title local / Nombre (Calling AET):** El `AE Title` exacto dado de alta en `/centers` (ej. `ISIX`)

## Migración desde Horos / OsiriX

El script [scripts/import_horos.py](./scripts/import_horos.py) permite ingestar archivos `.dcm` desde `DATABASE.noindex` hacia Orthanc vía API REST (`/instances`) en modo *streaming* de baja memoria, con registro de progreso (`/data/import_state.txt`) y omisión segura de archivos vacíos o anómalos (`> 256 MB`).
