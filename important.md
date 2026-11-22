# Tareas pendientes al finalizar la importación de Horos (`192.168.0.109`)

> Archivo temporal para no olvidar los pasos de cierre tras terminar `horos-importer`. Borrar cuando se complete.

## 1. Verificar que la importación terminó
En `192.168.0.109`:
```bash
tail -n 20 /opt/pacs/data/import.log
wc -l /opt/pacs/data/import_state.txt /opt/pacs/data/import_errors.log
```
Comprobar estadísticas finales de Orthanc:
```bash
docker exec pacs-php curl -s http://orthanc:8042/statistics
```

---

## 2. Reintentar los fallos de red registrados en `import_errors.log`
Durante la importación hubo ~1.880 errores transitorios (`Connection refused`, `Name does not resolve`, `Connection reset by peer`) cuando se reinició Orthanc. Como `scripts/import_horos.py` marca el shard como completado aunque tenga errores, esos archivos **no se reintentan solos**.

Comando para reintentar únicamente las rutas con error de red dentro de la red Docker (`pacs_pacs-net`):
```bash
docker run --rm --network pacs_pacs-net \
  -v /opt/pacs/data:/data \
  -v /mnt/osirix-ro:/mnt/osirix-ro:ro \
  python:3.12-alpine python -c '
import http.client
from pathlib import Path

errors_file = Path("/data/import_errors.log")
retry_log = Path("/data/import_retry_errors.log")
conn = http.client.HTTPConnection("orthanc", 8042, timeout=120)

targets = ("Connection refused", "Name does not resolve", "Connection reset by peer")
paths = []
for line in errors_file.read_text(encoding="utf-8", errors="ignore").splitlines():
    if "\t" not in line:
        continue
    p, reason = line.split("\t", 1)
    if any(t in reason for t in targets):
        paths.append(Path(p))

# Deduplicar manteniendo orden
paths = list(dict.fromkeys(paths))
print(f"Reintentando {len(paths)} archivos...")

ok = err = 0
for idx, path in enumerate(paths, 1):
    try:
        data = path.read_bytes()
        conn.request("POST", "/instances", body=data, headers={"Content-Type": "application/dicom", "Connection": "keep-alive"})
        resp = conn.getresponse()
        resp.read()
        if resp.status >= 400:
            raise RuntimeError(f"HTTP {resp.status} {resp.reason}")
        ok += 1
    except Exception as exc:
        err += 1
        conn.close()
        conn = http.client.HTTPConnection("orthanc", 8042, timeout=120)
        with retry_log.open("a", encoding="utf-8") as f:
            f.write(f"{path}\t{exc}\n")
    if idx % 200 == 0:
        print(f"Progreso: {idx}/{len(paths)} ok={ok} err={err}")

print(f"Finalizado: ok={ok} err={err}")
'
```

---

## 3. Revisar y subir los archivos `skipped_too_large` (> 256 MB)
Listar los archivos omitidos por tamaño:
```bash
grep "skipped_too_large" /opt/pacs/data/import_errors.log
```
- Los de **~299–426 MB** se pueden subir directamente aumentando el timeout/límite (ej. con `curl` o un script Python puntual sin límite de 256 MB).
- El de **~4,02 GB** (`4025905152 bytes`) conviene inspeccionarlo primero para confirmar si es un DICOM multiframe/cine válido o un archivo anómalo.

---

## 4. Limpieza final
1. Parar y eliminar el contenedor temporal `horos-importer` cuando ya no haga falta:
   ```bash
   docker rm -f horos-importer
   ```
2. Desmontar el disco origen de solo lectura (`/mnt/osirix-ro`) si ya no se va a consultar.
3. Si procede según `ROADMAP.md` (Fase 5), planificar la migración de `pacs.sqlite` a PostgreSQL.
4. Borrar este archivo `important.md`.
