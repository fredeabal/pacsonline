import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const helpers = require('../../public/assets/js/report-editor-voice.js');

function makeTextarea(value = '', cursor = value.length) {
  return {
    value,
    selectionStart: cursor,
    selectionEnd: cursor,
    setSelectionRange(start, end) {
      this.selectionStart = start;
      this.selectionEnd = end;
    },
    dispatchEvent() {},
  };
}

test('applyVoiceCommands converts punctuation and newlines', () => {
  assert.equal(
    helpers.applyVoiceCommands('campos pulmonares libres coma sin derrame punto'),
    'campos pulmonares libres, sin derrame.'
  );
  assert.equal(
    helpers.applyVoiceCommands('hallazgos normales punto y aparte sin lesiones'),
    'hallazgos normales\nsin lesiones'
  );
  assert.equal(
    helpers.applyVoiceCommands('nódulo abrir paréntesis 5 mm cerrar paréntesis'),
    'nódulo (5 mm)'
  );
});

test('applyVoiceCommands expands spoken macros', () => {
  assert.equal(
    helpers.applyVoiceCommands('macro sin hallazgos'),
    'Sin hallazgos patológicos agudos.'
  );
  assert.equal(
    helpers.applyVoiceCommands('macro senos libres'),
    'Senos costofrénicos libres, sin derrame pleural ni neumotórax.'
  );
});

test('detectNavigationCommand identifies target fields', () => {
  assert.equal(helpers.detectNavigationCommand('ir a hallazgos'), 'findings');
  assert.equal(helpers.detectNavigationCommand('ir a conclusión'), 'impression');
  assert.equal(helpers.detectNavigationCommand('pasar a antecedentes'), 'clinical_history');
  assert.equal(helpers.detectNavigationCommand('texto normal de prueba'), null);
});

test('insertTextAtCursor inserts with smart spacing and capitalization', () => {
  const ta = makeTextarea('');
  helpers.insertTextAtCursor(ta, 'campos pulmonares libres.');
  assert.equal(ta.value, 'Campos pulmonares libres.');

  helpers.insertTextAtCursor(ta, 'senos libres.');
  assert.equal(ta.value, 'Campos pulmonares libres. Senos libres.');

  const mid = makeTextarea('Texto inicial final.', 14);
  helpers.insertTextAtCursor(mid, 'intermedio');
  assert.equal(mid.value, 'Texto inicial intermedio final.');
});

test('expandSlashMacroAtCursor replaces trigger before cursor', () => {
  const ta = makeTextarea('Paciente estable. .normal');
  const res = helpers.expandSlashMacroAtCursor(ta);
  assert.equal(res.expanded, true);
  assert.equal(ta.value, 'Paciente estable. Sin hallazgos patológicos agudos.');

  const none = makeTextarea('Texto sin macro');
  assert.equal(helpers.expandSlashMacroAtCursor(none).expanded, false);
});
