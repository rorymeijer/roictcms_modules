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
 *
 * De afbeeldingsknop in de toolbar opent de interne media manager
 * in plaats van de standaard Quill upload-dialoog.
 */

// ── Laad Quill CSS in <head> ──────────────────────────────────────────────
add_action('admin_head', function () {
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">' . PHP_EOL;
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/wysiwyg-editor/assets/css/editor.css">' . PHP_EOL;
});

// ── Laad Quill JS + initialisatie in <footer> ─────────────────────────────
add_action('admin_footer', function () {
    $mediaApiUrl = BASE_URL . '/admin/media/api.php';
    $mediaManagerUrl = BASE_URL . '/admin/media/';
?>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
(function () {
  'use strict';

  var MEDIA_API_URL     = <?= json_encode($mediaApiUrl) ?>;
  var MEDIA_MANAGER_URL = <?= json_encode($mediaManagerUrl) ?>;

  // Wacht tot de DOM volledig geladen is
  document.addEventListener('DOMContentLoaded', function () {
    createMediaPickerModal();
    initWysiwygEditors();
  });

  // Fallback: als DOMContentLoaded al voorbij is
  if (document.readyState === 'interactive' || document.readyState === 'complete') {
    createMediaPickerModal();
    initWysiwygEditors();
  }

  // ── Media picker modal ──────────────────────────────────────────────────

  var _modalCreated = false;
  var _currentInsertCallback = null;

  function createMediaPickerModal() {
    if (_modalCreated || document.getElementById('wysiwyg-media-modal')) return;
    _modalCreated = true;

    var modal = document.createElement('div');
    modal.id = 'wysiwyg-media-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-label', 'Media kiezen');
    modal.innerHTML = [
      '<div class="wysiwyg-media-backdrop"></div>',
      '<div class="wysiwyg-media-dialog">',
        '<div class="wysiwyg-media-header">',
          '<span class="wysiwyg-media-title">',
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
            'Afbeelding kiezen',
          '</span>',
          '<div style="display:flex;gap:8px;align-items:center;">',
            '<a href="' + MEDIA_MANAGER_URL + '" target="_blank" class="wysiwyg-media-btn-link" title="Nieuwe afbeelding uploaden (opent media manager)">',
              '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',
              ' Uploaden',
            '</a>',
            '<button type="button" class="wysiwyg-media-close" aria-label="Sluiten">&times;</button>',
          '</div>',
        '</div>',
        '<div class="wysiwyg-media-search">',
          '<input type="search" id="wysiwyg-media-search-input" placeholder="Zoeken op bestandsnaam…" autocomplete="off">',
        '</div>',
        '<div class="wysiwyg-media-body" id="wysiwyg-media-body">',
          '<div class="wysiwyg-media-loading">Laden…</div>',
        '</div>',
        '<div class="wysiwyg-media-footer">',
          '<span id="wysiwyg-media-count" class="wysiwyg-media-count"></span>',
          '<button type="button" class="wysiwyg-media-cancel">Annuleren</button>',
        '</div>',
      '</div>'
    ].join('');

    document.body.appendChild(modal);

    // Sluit via backdrop of knop
    modal.querySelector('.wysiwyg-media-backdrop').addEventListener('click', closeMediaModal);
    modal.querySelector('.wysiwyg-media-close').addEventListener('click', closeMediaModal);
    modal.querySelector('.wysiwyg-media-cancel').addEventListener('click', closeMediaModal);

    // Zoekfunctie
    modal.querySelector('#wysiwyg-media-search-input').addEventListener('input', function () {
      filterMediaItems(this.value.toLowerCase());
    });

    // Sluit met Escape
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('wysiwyg-media-open')) {
        closeMediaModal();
      }
    });
  }

  function openMediaModal(insertCallback) {
    _currentInsertCallback = insertCallback;
    var modal = document.getElementById('wysiwyg-media-modal');
    if (!modal) { createMediaPickerModal(); modal = document.getElementById('wysiwyg-media-modal'); }

    // Reset zoekveld
    var searchInput = modal.querySelector('#wysiwyg-media-search-input');
    if (searchInput) searchInput.value = '';

    // Edge fallback: force zichtbaar maken naast de CSS class-toggle.
    modal.style.display = 'flex';
    modal.classList.add('wysiwyg-media-open');
    document.body.style.overflow = 'hidden';
    loadMediaItems();
  }

  function closeMediaModal() {
    var modal = document.getElementById('wysiwyg-media-modal');
    if (modal) modal.classList.remove('wysiwyg-media-open');
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = '';
    _currentInsertCallback = null;
  }

  function loadMediaItems() {
    var body = document.getElementById('wysiwyg-media-body');
    if (!body) return;

    body.innerHTML = '<div class="wysiwyg-media-loading">Laden\u2026</div>';

    fetch(MEDIA_API_URL, { credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        renderMediaItems(data.media || []);
      })
      .catch(function (err) {
        body.innerHTML = '<div class="wysiwyg-media-error">Fout bij laden: ' + err.message + '</div>';
      });
  }

  function renderMediaItems(items) {
    var body  = document.getElementById('wysiwyg-media-body');
    var count = document.getElementById('wysiwyg-media-count');
    if (!body) return;

    if (!items.length) {
      body.innerHTML = [
        '<div class="wysiwyg-media-empty">',
          '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.3;display:block;margin:0 auto 12px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
          '<p>Nog geen afbeeldingen geüpload.</p>',
          '<a href="' + MEDIA_MANAGER_URL + '" target="_blank" class="wysiwyg-media-btn-link">Upload afbeelding</a>',
        '</div>'
      ].join('');
      if (count) count.textContent = '';
      return;
    }

    if (count) count.textContent = items.length + ' afbeelding' + (items.length !== 1 ? 'en' : '');

    var grid = document.createElement('div');
    grid.className = 'wysiwyg-media-grid';

    items.forEach(function (item) {
      var tile = document.createElement('button');
      tile.type = 'button';
      tile.className = 'wysiwyg-media-tile';
      tile.dataset.name = (item.original_name || '').toLowerCase();
      tile.title = item.original_name || item.filename;

      tile.innerHTML = [
        '<div class="wysiwyg-media-thumb">',
          '<img src="' + item.url + '" alt="' + escapeAttr(item.alt_text || item.original_name) + '">',
        '</div>',
        '<div class="wysiwyg-media-label">' + escapeHtml(item.original_name || item.filename) + '</div>'
      ].join('');

      tile.addEventListener('click', function () {
        if (typeof _currentInsertCallback === 'function') {
          _currentInsertCallback(item.url, item.alt_text || item.original_name || '');
        }
        closeMediaModal();
      });

      grid.appendChild(tile);
    });

    body.innerHTML = '';
    body.appendChild(grid);
  }

  function filterMediaItems(query) {
    var tiles = document.querySelectorAll('#wysiwyg-media-body .wysiwyg-media-tile');
    var visible = 0;
    forEachNode(tiles, function (tile) {
      var match = !query || ((tile.dataset.name || '').indexOf(query) !== -1);
      tile.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    var count = document.getElementById('wysiwyg-media-count');
    if (count) count.textContent = visible + ' afbeelding' + (visible !== 1 ? 'en' : '');
  }

  function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
  function escapeAttr(str) {
    return String(str).replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function forEachNode(list, callback) {
    if (!list || !callback) return;
    for (var i = 0; i < list.length; i++) callback(list[i], i);
  }

  // Expose openMediaModal globally so other admin pages (e.g. the featured
  // image picker in news/add.php) can call it directly without being coupled
  // to the WYSIWYG editor internals.
  window.openMediaModal = openMediaModal;

  // ── Editor initialisatie ────────────────────────────────────────────────

  function initWysiwygEditors() {
    if (typeof Quill === 'undefined') return;

    // Selecteer alle tekstvakken die omgezet moeten worden
    var candidates = document.querySelectorAll(
      'textarea[name="content"], textarea[data-wysiwyg]'
    );

    forEachNode(candidates, function (textarea) {
      // Sla over als expliciet uitgesloten of al omgezet
      if (textarea.dataset.wysiwyg === 'false') return;
      if (textarea.dataset.wysiwygInit)          return;
      textarea.dataset.wysiwygInit = '1';

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
        placeholder: 'Typ hier de inhoud…',
        modules: {
          toolbar: {
            container: [
              [{ header: [1, 2, 3, 4, false] }],
              ['bold', 'italic', 'underline', 'strike'],
              ['blockquote', 'code-block'],
              [{ list: 'ordered' }, { list: 'bullet' }],
              [{ indent: '-1' }, { indent: '+1' }],
              ['link', 'image'],
              [{ align: [] }],
              ['clean']
            ],
            handlers: {
              // Vervang de standaard afbeeldingshandler door de interne media picker
              image: function () {
                openMediaModal(function (url, altText) {
                  var range = quill.getSelection(true);
                  quill.insertEmbed(range.index, 'image', url, Quill.sources.USER);
                  quill.setSelection(range.index + 1, Quill.sources.SILENT);
                });
              }
            }
          }
        }
      });

      // Laad bestaande HTML-inhoud in de editor
      var existing = textarea.value.trim();
      if (existing) {
        quill.clipboard.dangerouslyPasteHTML(existing);
      }

      // Synchroniseer de Quill-inhoud terug naar het tekstvak bij verzenden
      var form = textarea.closest('form');
      if (form) {
        form.addEventListener('submit', function () {
          var html = quill.root.innerHTML;
          textarea.value = (html === '<p><br></p>' || html.trim() === '') ? '' : html;
        });
      }
    });
  }
})();
</script>
<?php });