(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.ReportVoiceHelpers = factory();
  }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  const VOICE_MACROS = [
    {
      key: '.normal',
      voiceAlias: 'sin hallazgos',
      label: 'Sin hallazgos',
      text: 'Sin hallazgos patológicos agudos.',
    },
    {
      key: '.senos',
      voiceAlias: 'senos libres',
      label: 'Senos libres',
      text: 'Senos costofrénicos libres, sin derrame pleural ni neumotórax.',
    },
    {
      key: '.silueta',
      voiceAlias: 'silueta normal',
      label: 'Silueta normal',
      text: 'Silueta cardiomediastínica de tamaño y morfología dentro de límites normales.',
    },
    {
      key: '.parenquima',
      voiceAlias: 'parenquima normal',
      label: 'Parénquima normal',
      text: 'Campos pulmonares bien aireados, sin infiltrados, consolidaciones ni nódulos.',
    },
    {
      key: '.oseo',
      voiceAlias: 'sin lesiones oseas',
      label: 'Sin lesiones óseas',
      text: 'Estructuras óseas valoradas sin lesiones líticas, blásticas ni trazos de fractura agudos.',
    },
    {
      key: '.derrame',
      voiceAlias: 'sin derrame',
      label: 'Sin derrame',
      text: 'No se observa derrame pleural ni pericárdico.',
    },
    {
      key: '.comparativo',
      voiceAlias: 'sin cambios previos',
      label: 'Sin cambios previos',
      text: 'Sin cambios significativos respecto al estudio previo disponible.',
    },
    {
      key: '.control',
      voiceAlias: 'control evolutivo',
      label: 'Control evolutivo',
      text: 'Se recomienda correlación clínica y control evolutivo según criterio médico.',
    },
  ];

  function normalizeForMatch(str) {
    return String(str || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .trim();
  }

  /**
   * Detecta comandos de navegación por voz para cambiar el campo activo.
   * Devuelve 'clinical_history' | 'findings' | 'impression' | null.
   */
  function detectNavigationCommand(rawText) {
    const norm = normalizeForMatch(rawText);
    if (/^(ir a |pasar a |campo )?(antecedentes|motivo clinico|historia)$/.test(norm)) {
      return 'clinical_history';
    }
    if (/^(ir a |pasar a |campo )?(hallazgos)$/.test(norm)) {
      return 'findings';
    }
    if (/^(ir a |pasar a |campo )?(conclusion|impresion|impresion diagnostica)$/.test(norm)) {
      return 'impression';
    }
    return null;
  }

  /**
   * Convierte comandos de puntuación y macros habladas en texto formateado.
   */
  function applyVoiceCommands(rawText, macros) {
    const list = Array.isArray(macros) ? macros : VOICE_MACROS;
    let text = String(rawText || '').trim();
    if (!text) return '';

    // Comandos de macro por voz: "macro <alias>"
    const norm = normalizeForMatch(text);
    if (norm.startsWith('macro ')) {
      const alias = norm.slice(6).trim();
      const matched = list.find(function (m) {
        return normalizeForMatch(m.voiceAlias) === alias || normalizeForMatch(m.label) === alias;
      });
      if (matched) {
        return matched.text;
      }
    }

    // Reemplazo de comandos de puntuación (de más específico a más general)
    const replacements = [
      [/\bpunto y aparte\b/gi, '\n'],
      [/\bnueva l[ií]nea\b/gi, '\n'],
      [/\bnuevo p[aá]rrafo\b/gi, '\n\n'],
      [/\bpunto y seguido\b/gi, '.'],
      [/\bpunto y coma\b/gi, ';'],
      [/\bdos puntos\b/gi, ':'],
      [/\babrir par[eé]ntesis\b/gi, '('],
      [/\babre par[eé]ntesis\b/gi, '('],
      [/\bcerrar par[eé]ntesis\b/gi, ')'],
      [/\bcierra par[eé]ntesis\b/gi, ')'],
      [/\bpunto\b/gi, '.'],
      [/\bcoma\b/gi, ','],
    ];

    replacements.forEach(function (pair) {
      text = text.replace(pair[0], pair[1]);
    });

    // Limpiar espacios sobrantes alrededor de puntuación y saltos de línea
    text = text
      .replace(/[ \t]+([.,:;)])/g, '$1')
      .replace(/\([ \t]+/g, '(')
      .replace(/[ \t]*\n[ \t]*/g, '\n')
      .replace(/[ \t]{2,}/g, ' ')
      .trim();

    return text;
  }

  /**
   * Inserta texto en la posición actual del cursor de un textarea,
   * añadiendo espacio inteligente cuando corresponde.
   */
  function insertTextAtCursor(textarea, text) {
    if (!textarea || !text) return;

    const value = textarea.value || '';
    const start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : value.length;
    const end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : value.length;

    const before = value.slice(0, start);
    const after = value.slice(end);

    const startsWithPunctuation = /^[.,:;)\n]/.test(text);
    const needsLeadingSpace =
      before.length > 0 &&
      !/[\s(\n]$/.test(before) &&
      !startsWithPunctuation;

    const needsTrailingSpace =
      after.length > 0 &&
      !/^[\s.,:;)\n]/.test(after) &&
      !/[\s(\n]$/.test(text);

    // Capitalizar primera letra si estamos al inicio del campo o tras punto/salto de línea
    let formatted = text;
    if (before.length === 0 || /([.!?]\s*|\n)$/.test(before)) {
      formatted = formatted.replace(/^[a-záéíóúñ]/, function (ch) {
        return ch.toUpperCase();
      });
    }

    const prefix = needsLeadingSpace ? ' ' : '';
    const suffix = needsTrailingSpace ? ' ' : '';
    const inserted = prefix + formatted + suffix;

    textarea.value = before + inserted + after;
    const newCursor = before.length + inserted.length;
    if (typeof textarea.setSelectionRange === 'function') {
      textarea.setSelectionRange(newCursor, newCursor);
    } else {
      textarea.selectionStart = newCursor;
      textarea.selectionEnd = newCursor;
    }

    if (typeof Event === 'function' && typeof textarea.dispatchEvent === 'function') {
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }

  /**
   * Expande una macro escrita justo antes del cursor (p. ej. ".normal").
   * Devuelve { expanded: true, macro } si hubo expansión, o { expanded: false }.
   */
  function expandSlashMacroAtCursor(textarea, macros) {
    if (!textarea) return { expanded: false };
    const list = Array.isArray(macros) ? macros : VOICE_MACROS;
    const value = textarea.value || '';
    const cursor = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : value.length;

    const before = value.slice(0, cursor);
    const match = before.match(/(^|[\s\n])(\.[a-z0-9_-]+)$/i);
    if (!match) return { expanded: false };

    const trigger = match[2].toLowerCase();
    const macro = list.find(function (m) {
      return m.key.toLowerCase() === trigger;
    });
    if (!macro) return { expanded: false };

    const triggerStart = cursor - trigger.length;
    const after = value.slice(cursor);
    const prefix = value.slice(0, triggerStart);
    const needsTrailingSpace = after.length > 0 && !/^[\s.,:;)\n]/.test(after);
    const inserted = macro.text + (needsTrailingSpace ? ' ' : '');

    textarea.value = prefix + inserted + after;
    const newCursor = prefix.length + inserted.length;
    if (typeof textarea.setSelectionRange === 'function') {
      textarea.setSelectionRange(newCursor, newCursor);
    } else {
      textarea.selectionStart = newCursor;
      textarea.selectionEnd = newCursor;
    }

    if (typeof Event === 'function' && typeof textarea.dispatchEvent === 'function') {
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    return { expanded: true, macro: macro };
  }

  return {
    VOICE_MACROS: VOICE_MACROS,
    normalizeForMatch: normalizeForMatch,
    detectNavigationCommand: detectNavigationCommand,
    applyVoiceCommands: applyVoiceCommands,
    insertTextAtCursor: insertTextAtCursor,
    expandSlashMacroAtCursor: expandSlashMacroAtCursor,
  };
});
