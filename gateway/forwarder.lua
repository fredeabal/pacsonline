function OnStoredInstance(instanceId, tags, metadata)
   local url   = os.getenv("PACS_INGEST_URL")
   local token = os.getenv("PACS_CENTER_TOKEN")

   if url == nil or url == "" or token == nil or token == "" then
      print("[GATEWAY] Faltan PACS_INGEST_URL o PACS_CENTER_TOKEN")
      return
   end

   local dicom = RestApiGet("/instances/" .. instanceId .. "/file")
   local headers = {
      ["Authorization"] = "Bearer " .. token,
      ["Content-Type"]  = "application/dicom"
   }

   local ok, response = pcall(HttpPost, url, dicom, headers)
   if ok and response ~= nil then
      print("[GATEWAY] Instancia enviada correctamente: " .. instanceId)
      RestApiDelete("/instances/" .. instanceId)
   else
      print("[GATEWAY] Error al reenviar instancia (se conserva en buffer local): " .. instanceId)
   end
end
