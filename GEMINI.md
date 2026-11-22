# Reglas del proyecto PACS

## Despliegue / Sincronización a `192.168.0.109`
- La aplicación en pruebas/desarrollo corre en el servidor `192.168.0.109` en `/opt/pacs/`.
- Después de modificar archivos en `/Users/esweb/Documents/pacs/`, sincronizar los cambios con `root@192.168.0.109:/opt/pacs/` mediante `rsync` / `scp` para que se reflejen en el navegador.
- No guardar ni subir contraseñas, llaves SSH ni secretos en este archivo ni en el repositorio.

## Reglas generales de UI
- Las filas de las tablas (`tbody tr`) deben mantener siempre el mismo tamaño/altura uniforme en toda la aplicación.
- Los badges dentro de las tablas (`.table .badge`) deben tener siempre un ancho mínimo uniforme (`min-width: 84px`) y texto centrado.
- Todos los botones de guardar / envío de formularios (`button[type="submit"].btn`) deben mostrar un spinner de carga (`spinner-border spinner-border-sm`) y deshabilitarse al enviar para evitar envíos duplicados.
