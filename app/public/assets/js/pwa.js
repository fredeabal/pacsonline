// =====================================================================
// PWA (Progressive Web App) Manager para PACS Online (iOS & Android)
// =====================================================================

(function () {
  'use strict';

  // 1. Registro del Service Worker
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker
        .register('/sw.js')
        .then(function (registration) {
          // Service worker registrado correctamente
        })
        .catch(function (error) {
          console.warn('Error al registrar Service Worker PWA:', error);
        });
    });
  }

  // 2. Detección de plataforma y modo de ejecución
  var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  var isIOS = /iphone|ipad|ipod/.test(navigator.userAgent.toLowerCase());
  var deferredInstallPrompt = null;

  // Si ya está ejecutándose como PWA instalada, no mostramos prompts
  if (isStandalone) {
    return;
  }

  // Capturar evento de instalación nativa en Android / Chromium
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredInstallPrompt = e;
    
    // Si existe algún botón de instalación en la interfaz, mostrarlo
    var installBtns = document.querySelectorAll('.pwa-install-btn');
    installBtns.forEach(function (btn) {
      btn.classList.remove('d-none');
    });
  });

  // Función global para disparar la instalación
  window.installPacsPWA = function () {
    if (deferredInstallPrompt) {
      deferredInstallPrompt.prompt();
      deferredInstallPrompt.userChoice.then(function (choiceResult) {
        if (choiceResult.outcome === 'accepted') {
          console.log('Usuario aceptó instalar PACS Online');
        }
        deferredInstallPrompt = null;
      });
    } else if (isIOS) {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Instalar PACS Online',
          html: '<div class="text-start fs-3">' +
                '<p class="mb-2">Para instalar <strong>PACS Online</strong> en iOS:</p>' +
                '<ol class="ps-3 mb-0">' +
                '<li class="mb-2">Pulsa <strong>Compartir</strong> <i class="ti ti-share fs-5 text-primary align-middle"></i> en Safari.</li>' +
                '<li class="mb-2">Elige <strong>"Añadir a pantalla de inicio"</strong> <i class="ti ti-square-plus fs-5 text-primary align-middle"></i>.</li>' +
                '<li>Pulsa <strong>Añadir</strong>.</li>' +
                '</ol></div>',
          icon: 'info',
          confirmButtonText: 'Entendido'
        });
      }
    } else {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Instalar PACS Online',
          text: 'Abre el menú del navegador y selecciona "Instalar aplicación" o "Añadir a pantalla de inicio".',
          icon: 'info',
          confirmButtonText: 'Entendido'
        });
      }
    }
  };

  // Evento cuando la PWA se ha instalado con éxito
  window.addEventListener('appinstalled', function () {
    var installBtns = document.querySelectorAll('.pwa-install-btn');
    installBtns.forEach(function (btn) {
      btn.classList.add('d-none');
    });
    deferredInstallPrompt = null;
  });
})();

