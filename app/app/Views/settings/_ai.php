<?php
  $aiProvider    = old('provider', $ai['provider'] ?? 'cloud');
  $aiCloudPreset = old('cloud_preset', $ai['cloudPreset'] ?? 'gemini');
?>
<div class="card mb-4">
  <div class="card-header bg-primary-subtle d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h5 class="mb-0 text-primary fw-semibold">
      <i class="ti ti-robot me-1"></i> Asistente de IA para Informes
    </h5>
    <span class="badge <?= ! empty($ai['enabled']) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' ?> fs-2">
      <?= ! empty($ai['enabled']) ? 'Asistente activo' : 'Desactivado' ?>
    </span>
  </div>

  <div class="card-body">
    <form action="<?= base_url('settings/ai') ?>" method="post" id="aiSettingsForm">
      <?= csrf_field() ?>

      <div class="form-check form-switch mb-4">
        <input class="form-check-input" type="checkbox" id="ai_enabled" name="enabled" value="1" <?= ! empty($ai['enabled']) ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold" for="ai_enabled">
          Habilitar asistente de IA en el editor de informes (conclusión, ordenación y resumen para paciente)
        </label>
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold d-block mb-2">Proveedor de IA</label>
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="border rounded-2 p-3 d-flex align-items-start gap-2 w-100 h-100 cursor-pointer <?= $aiProvider === 'cloud' ? 'border-primary bg-primary-subtle bg-opacity-25' : '' ?>" for="ai_provider_cloud" style="cursor: pointer;">
              <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="provider" id="ai_provider_cloud" value="cloud" <?= $aiProvider === 'cloud' ? 'checked' : '' ?>>
              <div>
                <div class="fw-semibold text-body"><i class="ti ti-cloud me-1 text-primary"></i> Cloud (API externa)</div>
                <div class="text-muted small">Gemini, OpenAI, Groq o cualquier API compatible OpenAI. Ideal si el servidor no tiene GPU.</div>
              </div>
            </label>
          </div>
          <div class="col-12 col-md-6">
            <label class="border rounded-2 p-3 d-flex align-items-start gap-2 w-100 h-100 cursor-pointer <?= $aiProvider === 'ollama' ? 'border-primary bg-primary-subtle bg-opacity-25' : '' ?>" for="ai_provider_ollama" style="cursor: pointer;">
              <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="provider" id="ai_provider_ollama" value="ollama" <?= $aiProvider === 'ollama' ? 'checked' : '' ?>>
              <div>
                <div class="fw-semibold text-body"><i class="ti ti-server me-1 text-primary"></i> Ollama (Local / Red local)</div>
                <div class="text-muted small">Privacidad total en tu red usando MedGemma, Qwen o Llama en un equipo con suficiente RAM/GPU.</div>
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- Configuración Cloud API -->
      <div id="aiCloudSection" class="<?= $aiProvider === 'cloud' ? '' : 'd-none' ?>">
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label for="ai_cloud_preset" class="form-label fw-semibold">Servicio Cloud</label>
            <select class="form-select" id="ai_cloud_preset" name="cloud_preset">
              <?php foreach (($aiPresets ?? []) as $key => $preset): ?>
                <option value="<?= esc($key, 'attr') ?>"
                        data-url="<?= esc($preset['url'], 'attr') ?>"
                        data-model="<?= esc($preset['model'], 'attr') ?>"
                        <?= $aiCloudPreset === $key ? 'selected' : '' ?>>
                  <?= esc($preset['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-md-8">
            <label for="ai_cloud_url" class="form-label fw-semibold">URL Base (compatible OpenAI)</label>
            <input type="url" class="form-control" id="ai_cloud_url" name="cloud_url"
                   value="<?= esc(old('cloud_url', $ai['cloudUrl'] ?? '')) ?>"
                   placeholder="https://generativelanguage.googleapis.com/v1beta/openai">
          </div>

          <div class="col-12 col-md-6">
            <label for="ai_cloud_model" class="form-label fw-semibold">Modelo</label>
            <div class="input-group flex-nowrap">
              <div class="flex-grow-1 min-w-0">
                <input type="text" class="form-control rounded-end-0" id="ai_cloud_model" name="cloud_model"
                       value="<?= esc(old('cloud_model', $ai['cloudModel'] ?? '')) ?>"
                       placeholder="gemini-3.8-flash">
                <div id="ai_cloud_select_wrap" class="d-none">
                  <select class="form-select rounded-end-0" id="ai_cloud_model_select"></select>
                </div>
              </div>
              <button type="button" class="btn btn-outline-secondary flex-shrink-0" id="aiDetectCloudBtn" title="Cargar modelos disponibles en Cloud">
                <i class="ti ti-refresh"></i>
              </button>
            </div>
            <div class="form-text d-none" id="aiCloudModelsStatus"></div>
          </div>

          <div class="col-12 col-md-6">
            <label for="ai_cloud_key" class="form-label fw-semibold">API Key</label>
            <input type="password" class="form-control" id="ai_cloud_key" name="cloud_key"
                   placeholder="<?= ! empty($ai['hasApiKey']) ? '•••••••• (dejar vacío para mantener)' : 'Introduce tu API Key' ?>">
          </div>
        </div>
      </div>

      <!-- Configuración Ollama -->
      <div id="aiOllamaSection" class="<?= $aiProvider === 'ollama' ? '' : 'd-none' ?>">
        <div class="row g-3">
          <div class="col-12 col-md-7">
            <label for="ai_ollama_url" class="form-label fw-semibold">URL del servidor Ollama</label>
            <input type="url" class="form-control" id="ai_ollama_url" name="ollama_url"
                   value="<?= esc(old('ollama_url', $ai['ollamaUrl'] ?? 'http://127.0.0.1:11434')) ?>"
                   placeholder="http://192.168.0.50:11434">
          </div>

          <div class="col-12 col-md-5">
            <label for="ai_ollama_model" class="form-label fw-semibold">Modelo Ollama</label>
            <div class="input-group flex-nowrap">
              <div class="flex-grow-1 min-w-0">
                <input type="text" class="form-control rounded-end-0" id="ai_ollama_model" name="ollama_model"
                       value="<?= esc(old('ollama_model', $ai['ollamaModel'] ?? 'medgemma:4b')) ?>"
                       placeholder="medgemma:4b">
                <div id="ai_ollama_select_wrap" class="d-none">
                  <select class="form-select rounded-end-0" id="ai_ollama_model_select"></select>
                </div>
              </div>
              <button type="button" class="btn btn-outline-secondary flex-shrink-0" id="aiDetectOllamaBtn" title="Cargar modelos instalados en Ollama">
                <i class="ti ti-refresh"></i>
              </button>
            </div>
            <div class="form-text d-none" id="aiOllamaModelsStatus"></div>
          </div>
        </div>
      </div>

      <div class="alert alert-light border mt-4 mb-0 py-2 px-3 small text-muted">
        <i class="ti ti-shield-check me-1 text-success"></i>
        <strong>Privacidad:</strong> El asistente envía únicamente la modalidad/estudio y el texto clínico de hallazgos, excluyendo nombre del paciente, NHC, fechas e identificadores DICOM.
      </div>

      <div class="d-grid d-sm-flex justify-content-sm-center gap-2 mt-4">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ti ti-device-floppy me-1"></i> Guardar Configuración IA
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header bg-primary-subtle">
    <h5 class="mb-0 text-primary fw-semibold">Probar conexión IA</h5>
  </div>
  <div class="card-body">
    <p class="text-muted small mb-3">
      Guarda primero los cambios y pulsa para comprobar que el proveedor y modelo seleccionados responden correctamente.
    </p>
    <form action="<?= base_url('settings/test-ai') ?>" method="post">
      <?= csrf_field() ?>
      <div class="d-grid d-sm-flex justify-content-sm-center">
        <button type="submit" class="btn btn-sm btn-outline-primary">
          <i class="ti ti-plug-connected me-1"></i> Probar Conexión IA
        </button>
      </div>
    </form>
  </div>
</div>

<script {csp-script-nonce}>
(function() {
  const cloudRadio  = document.getElementById('ai_provider_cloud');
  const ollamaRadio = document.getElementById('ai_provider_ollama');
  const cloudSec    = document.getElementById('aiCloudSection');
  const ollamaSec   = document.getElementById('aiOllamaSection');
  const presetSel   = document.getElementById('ai_cloud_preset');
  const urlInput    = document.getElementById('ai_cloud_url');
  const modelInput  = document.getElementById('ai_cloud_model');

  const cloudCard   = document.querySelector('label[for="ai_provider_cloud"]');
  const ollamaCard  = document.querySelector('label[for="ai_provider_ollama"]');

  function syncProviderUI() {
    const isCloud = cloudRadio && cloudRadio.checked;
    if (cloudSec) cloudSec.classList.toggle('d-none', !isCloud);
    if (ollamaSec) ollamaSec.classList.toggle('d-none', isCloud);
    if (cloudCard) {
      cloudCard.classList.toggle('border-primary', isCloud);
      cloudCard.classList.toggle('bg-primary-subtle', isCloud);
      cloudCard.classList.toggle('bg-opacity-25', isCloud);
    }
    if (ollamaCard) {
      ollamaCard.classList.toggle('border-primary', !isCloud);
      ollamaCard.classList.toggle('bg-primary-subtle', !isCloud);
      ollamaCard.classList.toggle('bg-opacity-25', !isCloud);
    }
  }

  if (cloudRadio) cloudRadio.addEventListener('change', syncProviderUI);
  if (ollamaRadio) ollamaRadio.addEventListener('change', syncProviderUI);

  if (presetSel) {
    presetSel.addEventListener('change', function() {
      const opt = presetSel.options[presetSel.selectedIndex];
      if (!opt) return;
      const presetUrl   = opt.getAttribute('data-url') || '';
      const presetModel = opt.getAttribute('data-model') || '';
      if (urlInput && presetUrl !== '') urlInput.value = presetUrl;
      if (modelInput && presetModel !== '') modelInput.value = presetModel;
    });
  }

  const ollamaUrlInput    = document.getElementById('ai_ollama_url');
  const ollamaModelInput  = document.getElementById('ai_ollama_model');
  const ollamaModelSelect = document.getElementById('ai_ollama_model_select');
  const ollamaSelectWrap  = document.getElementById('ai_ollama_select_wrap');
  const detectOllamaBtn   = document.getElementById('aiDetectOllamaBtn');
  const ollamaStatusEl    = document.getElementById('aiOllamaModelsStatus');
  const ollamaModelsUrl   = <?= json_encode(base_url('settings/ollama-models')) ?>;

  const cloudKeyInput     = document.getElementById('ai_cloud_key');
  const cloudModelSelect  = document.getElementById('ai_cloud_model_select');
  const cloudSelectWrap   = document.getElementById('ai_cloud_select_wrap');
  const detectCloudBtn    = document.getElementById('aiDetectCloudBtn');
  const cloudStatusEl     = document.getElementById('aiCloudModelsStatus');
  const cloudModelsUrl    = <?= json_encode(base_url('settings/cloud-models')) ?>;
  const aiCsrfName        = <?= json_encode(csrf_token()) ?>;
  let aiCsrfHash          = <?= json_encode(csrf_hash()) ?>;

  function toggleModelField(inputEl, wrapEl, showSelect) {
    if (inputEl) inputEl.classList.toggle('d-none', showSelect);
    if (wrapEl) wrapEl.classList.toggle('d-none', !showSelect);
  }

  function refreshSelect2Options(selectEl, selectedVal) {
    if (!selectEl) return;
    if (selectedVal) selectEl.value = selectedVal;
    if (window.jQuery && jQuery.fn.select2) {
      jQuery(selectEl).trigger('change.select2');
    }
  }

  function syncAiCsrf(newHash) {
    if (!newHash) return;
    aiCsrfHash = newHash;
    document.querySelectorAll('input[name="' + aiCsrfName + '"]').forEach(function(inp) {
      inp.value = newHash;
    });
  }

  async function loadCloudModels() {
    const targetUrl = urlInput ? urlInput.value.trim() : '';
    if (!targetUrl) return;

    if (detectCloudBtn) {
      detectCloudBtn.disabled = true;
      detectCloudBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    }

    try {
      const body = new URLSearchParams();
      body.set(aiCsrfName, aiCsrfHash);
      body.set('url', targetUrl);
      body.set('api_key', cloudKeyInput ? cloudKeyInput.value.trim() : '');

      const resp = await fetch(cloudModelsUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
      });
      const data = await resp.json().catch(function() { return {}; });
      if (data.csrf_hash) syncAiCsrf(data.csrf_hash);

      const models = Array.isArray(data.models) ? data.models : [];
      if (data.ok && models.length > 0 && cloudModelSelect) {
        cloudModelSelect.innerHTML = '';
        let chosen = modelInput ? modelInput.value.trim() : '';
        if (!chosen || !models.includes(chosen)) {
          chosen = models[0];
        }
        models.forEach(function(m) {
          const opt = document.createElement('option');
          opt.value = m;
          opt.textContent = m;
          if (chosen === m) opt.selected = true;
          cloudModelSelect.appendChild(opt);
        });
        if (modelInput) modelInput.value = chosen;
        toggleModelField(modelInput, cloudSelectWrap, true);
        refreshSelect2Options(cloudModelSelect, chosen);
        if (cloudStatusEl) {
          cloudStatusEl.textContent = models.length + ' modelo(s) disponible(s).';
          cloudStatusEl.className = 'form-text text-success';
          cloudStatusEl.classList.remove('d-none');
        }
      } else {
        toggleModelField(modelInput, cloudSelectWrap, false);
        if (cloudStatusEl) {
          cloudStatusEl.textContent = data.ok ? 'No se encontraron modelos.' : ('Error: ' + (data.error || 'no se pudo consultar'));
          cloudStatusEl.className = 'form-text text-danger';
          cloudStatusEl.classList.remove('d-none');
        }
      }
    } catch (e) {
      toggleModelField(modelInput, cloudSelectWrap, false);
    } finally {
      if (detectCloudBtn) {
        detectCloudBtn.disabled = false;
        detectCloudBtn.innerHTML = '<i class="ti ti-refresh"></i>';
      }
    }
  }

  if (detectCloudBtn) {
    detectCloudBtn.addEventListener('click', loadCloudModels);
  }
  if (cloudModelSelect && modelInput) {
    cloudModelSelect.addEventListener('change', function() {
      modelInput.value = cloudModelSelect.value;
    });
  }
  if (presetSel) {
    presetSel.addEventListener('change', function() {
      toggleModelField(modelInput, cloudSelectWrap, false);
    });
  }

  async function loadOllamaModels(silent) {
    const targetUrl = ollamaUrlInput ? ollamaUrlInput.value.trim() : '';
    if (!targetUrl) return;

    if (detectOllamaBtn) {
      detectOllamaBtn.disabled = true;
      detectOllamaBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    }

    try {
      const resp = await fetch(ollamaModelsUrl + '?url=' + encodeURIComponent(targetUrl));
      const data = await resp.json().catch(function() { return {}; });
      const models = Array.isArray(data.models) ? data.models : [];

      if (data.ok && models.length > 0 && ollamaModelSelect) {
        ollamaModelSelect.innerHTML = '';
        let chosen = ollamaModelInput ? ollamaModelInput.value.trim() : '';
        if (!chosen || !models.includes(chosen)) {
          chosen = models[0];
        }
        models.forEach(function(m) {
          const opt = document.createElement('option');
          opt.value = m;
          opt.textContent = m;
          if (chosen === m) opt.selected = true;
          ollamaModelSelect.appendChild(opt);
        });
        if (ollamaModelInput) ollamaModelInput.value = chosen;
        toggleModelField(ollamaModelInput, ollamaSelectWrap, true);
        refreshSelect2Options(ollamaModelSelect, chosen);
        if (ollamaStatusEl) {
          ollamaStatusEl.textContent = models.length + ' modelo(s) detectado(s) en Ollama.';
          ollamaStatusEl.className = 'form-text text-success';
          ollamaStatusEl.classList.remove('d-none');
        }
      } else {
        toggleModelField(ollamaModelInput, ollamaSelectWrap, false);
        if (ollamaStatusEl && !silent) {
          ollamaStatusEl.textContent = data.ok ? 'No hay modelos instalados en ese servidor Ollama.' : ('No se pudo conectar: ' + (data.error || 'error'));
          ollamaStatusEl.className = 'form-text text-muted';
          ollamaStatusEl.classList.remove('d-none');
        }
      }
    } catch (e) {
      toggleModelField(ollamaModelInput, ollamaSelectWrap, false);
    } finally {
      if (detectOllamaBtn) {
        detectOllamaBtn.disabled = false;
        detectOllamaBtn.innerHTML = '<i class="ti ti-refresh"></i>';
      }
    }
  }

  if (detectOllamaBtn) {
    detectOllamaBtn.addEventListener('click', function() { loadOllamaModels(false); });
  }
  if (ollamaModelSelect && ollamaModelInput) {
    ollamaModelSelect.addEventListener('change', function() {
      ollamaModelInput.value = ollamaModelSelect.value;
    });
  }
  if (ollamaRadio) {
    ollamaRadio.addEventListener('change', function() {
      if (ollamaRadio.checked) loadOllamaModels(true);
    });
  }
  if (ollamaUrlInput) {
    ollamaUrlInput.addEventListener('change', function() {
      if (ollamaRadio && ollamaRadio.checked) loadOllamaModels(false);
    });
  }
  if (ollamaRadio && ollamaRadio.checked) {
    loadOllamaModels(true);
  }
})();
</script>
