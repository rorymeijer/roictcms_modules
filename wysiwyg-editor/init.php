<?php
/**
 * WYSIWYG Editor Module — Main Boot File
 *
 * Geladen door ModuleManager::bootModules() voor elke actieve module.
 * Vervangt automatisch content-tekstvakken in het admin paneel door
 * een visuele Quill.js rich-text editor.
 *
 * Tekstvakken die worden omgezet:
 *   - textarea[name="content"]   — standaard inhoudsveld (pagina's, nieuws)
 *   - textarea[data-wysiwyg]     — opt-in voor willekeurige tekstvakken
 *
 * Tekstvakken die worden overgeslagen:
 *   - textarea[data-wysiwyg="false"]  — expliciet uitgesloten
 */

// ── Laad Quill CSS in <head> ──────────────────────────────────────────────
add_action('admin_head', function () {
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">' . PHP_EOL;
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/wysiwyg-editor/assets/css/editor.css">' . PHP_EOL;
});

// ── Laad Quill JS + initialisatie in <footer> ─────────────────────────────
add_action('admin_footer', function () { ?>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
(function () {
  'use strict';

  // Wacht tot de DOM volledig geladen is
  document.addEventListener('DOMContentLoaded', function () {
    initWysiwygEditors();
  });

  // Fallback: als DOMContentLoaded al voorbij is
  if (document.readyState === 'interactive' || document.readyState === 'complete') {
    initWysiwygEditors();
  }

  function initWysiwygEditors() {
    if (typeof Quill === 'undefined') return;

    // Selecteer alle tekstvakken die omgezet moeten worden
    var candidates = document.querySelectorAll(
      'textarea[name="content"], textarea[data-wysiwyg]'
    );

    candidates.forEach(function (textarea) {
      // Sla over als expliciet uitgesloten of al omgezet
      if (textarea.dataset.wysiwyg === 'false') return;
      if (textarea.dataset.wysiwygInit)          return;
      textarea.dataset.wysiwygInit = '1';

      // Bewaar de originele klassen/stijlen van het label
      var label = textarea.closest('.mb-3') || textarea.parentNode;

      // Maak wrapper aan
      var wrapper = document.createElement('div');
      wrapper.className = 'wysiwyg-wrapper';

      // Maak de Quill container aan
      var editorDiv = document.createElement('div');
      editorDiv.className = 'wysiwyg-editor-container';

      // Voeg de wrapper in en verberg het originele tekstvak
      textarea.parentNode.insertBefore(wrapper, textarea);
      wrapper.appendChild(editorDiv);

      // Verberg het originele tekstvak (blijft in DOM voor formulierverzending)
      textarea.style.display = 'none';
      wrapper.appendChild(textarea);

      // Initialiseer Quill
      var quill = new Quill(editorDiv, {
        theme: 'snow',
        placeholder: 'Typ hier de inhoud...',
        modules: {
          toolbar: [
            [{ header: [1, 2, 3, 4, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            ['blockquote', 'code-block'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            [{ indent: '-1' }, { indent: '+1' }],
            ['link', 'image'],
            [{ align: [] }],
            ['clean']
          ]
        }
      });

      // Laad bestaande HTML-inhoud in de editor
      var existing = textarea.value.trim();
      if (existing) {
        // dangerouslyPasteHTML laadt bestaande HTML correct in
        quill.clipboard.dangerouslyPasteHTML(existing);
      }

      // Synchroniseer de Quill-inhoud terug naar het tekstvak bij verzenden
      var form = textarea.closest('form');
      if (form) {
        form.addEventListener('submit', function () {
          // Controleer of de editor leeg is (alleen lege <p> tags)
          var html = quill.root.innerHTML;
          if (html === '<p><br></p>' || html.trim() === '') {
            textarea.value = '';
          } else {
            textarea.value = html;
          }
        });
      }
    });
  }
})();
</script>
<?php });
