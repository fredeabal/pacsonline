-- =============================================================================
-- Orthanc Gateway Forwarder (Lua)
-- Se ejecuta en la clínica/centro remoto dentro del contenedor pacs-clinic-gateway.
-- Cada vez que un equipo médico envía una imagen DICOM por C-STORE al gateway,
-- este script la reenvía por HTTPS al servidor central PACS Online (/api/ingest/dicom)
-- autenticándose con el Bearer Token del centro y limpia el buffer local al confirmar.
-- =============================================================================

-- Callback nativo de Orthanc: se dispara automáticamente al almacenar una instancia DICOM.
function OnStoredInstance(instanceId, tags, metadata)
   -- Lee la URL de ingesta del PACS central y el token secreto del centro desde el entorno
   local url   = os.getenv("PACS_INGEST_URL")
   local token = os.getenv("PACS_CENTER_TOKEN")

   -- Si falta configuración, registra aviso y deja la instancia en el buffer local
   if url == nil or url == "" or token == nil or token == "" then
      print("[GATEWAY] Faltan PACS_INGEST_URL o PACS_CENTER_TOKEN")
      return
   end

   -- Obtiene el archivo binario DICOM (.dcm) recién recibido desde el Orthanc local
   local dicom = RestApiGet("/instances/" .. instanceId .. "/file")

   -- Cabeceras HTTP: autenticación Bearer del centro médico y tipo de contenido DICOM
   local headers = {
      ["Authorization"] = "Bearer " .. token,
      ["Content-Type"]  = "application/dicom"
   }

   -- Envía el archivo al PACS central capturando posibles errores de red/HTTP con pcall
   local ok, response = pcall(HttpPost, url, dicom, headers)
   if ok and response ~= nil then
      print("[GATEWAY] Instancia enviada correctamente: " .. instanceId)
      -- Elimina la copia del buffer local para no llenar el disco de la clínica
      RestApiDelete("/instances/" .. instanceId)
   else
      -- Si falla el envío (ej. caída de internet), conserva el archivo en el buffer local
      print("[GATEWAY] Error al reenviar instancia (se conserva en buffer local): " .. instanceId)
   end
end
