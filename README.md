# PACS Online + Segunda Opinión Médica

Plataforma PACS basada en **Orthanc** (con índice **PostgreSQL 16**), **OHIF Viewer**, **PHP 8 / CodeIgniter 4** (**SQLite3**) y **Nginx**, desplegada con Docker Compose en Proxmox.

Consulta el plan y progreso completo en [ROADMAP.md](./ROADMAP.md).

## Estructura del proyecto

```text
.
├── app/                  # Aplicación PHP (CodeIgniter 4 + Shield)
├── docker/
│   ├── nginx/            # Proxy inverso (Portal + /viewer/ + /dicom-web/ con auth_request)
│   ├── ohif/             # Configuración de OHIF Viewer (app-config.js)
│   └── php/              # Dockerfile PHP 8.3-FPM + SQLite + GD + Zip + Composer
├── gateway/              # Agente local C-STORE -> STOW-RS (forwarder.lua)
├── scripts/
│   ├── backup.sh         # Respaldo cifrado de SQLite + datos de Orthanc
│   └── import_horos.py   # Importador reanudable de Horos/OsiriX -> Orthanc
├── .env.example          # Plantilla de variables de entorno
├── .gitignore
└── docker-compose.yml    # Servicios: postgres, orthanc, ohif, php, nginx
```

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
