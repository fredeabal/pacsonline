# Gateway Local para Centros Médicos (`C-STORE` → HTTPS)

Este gateway ligero se instala en un PC o servidor dentro de la red local de la clínica/centro médico. Recibe estudios por protocolo DICOM tradicional (`C-STORE`, puerto `4242`) y los reenvía cifrados por HTTPS a tu servidor **PACS Online** (`/api/ingest/dicom`).

## Instalación en el Centro Médico

1. Copia esta carpeta `gateway/` en el equipo del centro médico.
2. Crea un archivo `.env` junto a `docker-compose.yml`:
   ```env
   CLINIC_AET=CLINICA_NORTE
   PACS_INGEST_URL=https://tu-dominio-pacs.com/api/ingest/dicom
   PACS_CENTER_TOKEN=pacs_tu_token_generado_en_centros
   ```
3. Inicia el gateway:
   ```bash
   docker compose up -d
   ```
4. Configura en el ecógrafo / RX / TAC / RM como destino DICOM:
   - **AE Title:** el `CLINIC_AET` configurado (ej. `CLINICA_NORTE`)
   - **IP:** la IP local del PC donde corre el gateway
   - **Puerto:** `4242`

Cada imagen recibida se envía al instante por HTTPS y, una vez confirmada, se elimina del buffer local.
