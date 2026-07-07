(function ($) {
    const taxName = (window.DIM_ADD_COLOR && DIM_ADD_COLOR.taxonomy) || 'product_kleur';

    // Fallback: in case ACF isn't present, we still try native box
    const $nativeBox = $(`#${taxName}div`);

    // Build the modal once
    const $modal = $(`
    <div id="dim-add-color-modal" style="position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;z-index:100000;">
      <div style="background:#fff;padding:16px;border-radius:8px;width:360px;max-width:90%;margin:10vh auto;">
        <h2 style="margin-top:0">Nieuwe kleur</h2>
        <p><label>Naam<br><input type="text" id="dim-color-name" class="regular-text" /></label></p>
        <p><label>Hex kleur<br><input type="text" id="dim-color-hex" class="dim-term-color" /></label></p>
        <p style="margin-top:16px;">
          <button type="button" class="button button-primary" id="dim-save-color">Opslaan</button>
          <button type="button" class="button" id="dim-cancel-color">Annuleren</button>
        </p>
        <div id="dim-color-error" style="color:#b32d2e;"></div>
      </div>
    </div>
  `).appendTo('body');

    // Init WP color picker
    if (typeof $.fn.wpColorPicker === 'function') {
        $('#dim-color-hex').wpColorPicker();
    }

    $modal.on('click', (e) => { if (e.target === $modal[0]) $modal.hide(); });
    $('#dim-cancel-color').on('click', () => $modal.hide());

    // ---------- helpers ----------
    function getSelectedIdsFromAcfField(acfField) {
        const $acfField = $(acfField);
        // select (tag-like)
        const $select = $acfField.find('select');
        if ($select.length) {
            const v = $select.val() || [];
            return (Array.isArray(v) ? v : [v]).map(String).filter(Boolean);
        }
        // checkbox (category-like)
        return $acfField.find('input[type="checkbox"]:checked').map((i, el) => el.value).get().map(String);
    }

    function getSelectedIdsFromNative() {
        if (!$nativeBox.length) return [];
        return $nativeBox.find('input[type="checkbox"]:checked').map((i, el) => el.value).get().map(String);
    }

    function renderPreview($target, ids) {
        if (!$target || !$target.length) return;
        if (!ids || !ids.length) { $target.empty(); return; }

        $.post(DIM_ADD_COLOR.ajax, {
            action: 'dim_get_color_terms',
            taxonomy: taxName,
            ids: ids
        }).done((resp) => {
            if (!resp.success) { $target.empty(); return; }
            const html = resp.data.map(t => {
                const hex = t.hex || '';
                const swatch = hex ? `<span style="display:inline-block;width:14px;height:14px;border-radius:50%;border:1px solid #ccc;background:${hex};margin-right:6px;vertical-align:middle;"></span>` : '';
                const safeName = (window._ && _.escape) ? _.escape(t.name) : t.name;
                return `<span style="display:inline-flex;align-items:center;padding:3px 8px;border:1px solid #ddd;border-radius:12px;background:#fff;">${swatch}${safeName}</span>`;
            }).join(' ');
            $target.html(html);
        });
    }

    function selectInAcfField($acfField, termId, termName) {
        const $select = $acfField.find('select');
        if ($select.length) {
            let current = $select.val() || [];
            current = Array.isArray(current) ? current : [current];
            if (!$select.find(`option[value="${termId}"]`).length) {
                $select.append(new Option(termName, termId, true, true));
            }
            if (!current.includes(String(termId))) current.push(String(termId));
            $select.val(current).trigger('change');
            return;
        }
        // checkbox appearance
        const $list = $acfField.find('.categorychecklist, .acf-checkbox-list, .acf-taxonomy-field').first();
        if ($list.length) {
            let $cb = $list.find(`input[type="checkbox"][value="${termId}"]`);
            if (!$cb.length) {
                const $li = $(
                    `<li><label><input type="checkbox" value="${termId}" checked="checked" /> <span>${termName}</span></label></li>`
                );
                $list.prepend($li);
                $cb = $li.find('input');
            }
            $cb.prop('checked', true).trigger('change');
        }
    }

    function attachCreateButton(acfField) {
        const $acfField = $(acfField);
        // Add button next to the field label
        const $labelArea = $acfField.find('.acf-label').first();
        if (!$labelArea.length || $labelArea.data('dim-btn-added')) return;
        $labelArea.data('dim-btn-added', true);

        const $btn = $(`<button type="button" class="button button-small" style="margin-left:8px;">Nieuwe kleur +</button>`);
        $labelArea.append($btn);

        $btn.on('click', () => $modal.show());

        $('#dim-save-color').off('click').on('click', function () {
            const name = $('#dim-color-name').val().trim();
            const hex  = $('#dim-color-hex').val().trim();
            if (!name) { $('#dim-color-error').text('Naam is verplicht'); return; }

            $(this).prop('disabled', true);
            $('#dim-color-error').text('');

            $.post(DIM_ADD_COLOR.ajax, {
                action: 'dim_create_color_term',
                nonce: DIM_ADD_COLOR.nonce,
                taxonomy: taxName,
                name, hex
            }).done((resp) => {
                $('#dim-save-color').prop('disabled', false);
                if (!resp.success) { $('#dim-color-error').text(resp.data?.message || 'Fout bij opslaan'); return; }

                const { id, name } = resp.data;

                // select in field
                selectInAcfField($acfField, id, name);

                // re-render preview
                const ids = getSelectedIdsFromAcfField($acfField);
                const $preview = $acfField.data('dim-preview');
                if ($preview && $preview.length) renderPreview($preview, ids);

                // close & reset
                $('#dim-add-color-modal').hide();
                $('#dim-color-name').val('');
            }).fail(() => {
                $('#dim-save-color').prop('disabled', false);
                $('#dim-color-error').text('Kon kleur niet opslaan.');
            });
        });
    }

    function ensurePreviewBox(acfField) {
        const $acfField = $(acfField);
        let $preview = $acfField.data?.dimPreview;
        if (!$preview || !$preview.length) {
            $preview = $(`<div class="dim-color-preview" style="margin:.5rem 0 0; display:flex; flex-wrap:wrap; gap:6px;"></div>`);
            $acfField.find('.acf-input').first().after($preview);
            $acfField.data('dim-preview', $preview);
        }
        return $preview;
    }

    // --------- ACF-aware init (this is the key bit you were missing) ---------

    if (window.acf && typeof acf.addAction === 'function') {
        // Runs when fields are ready (correct timing for initial selection)
        acf.addAction('ready_field/type=taxonomy', function ($field) {

            if ($field.data.name !== taxName) return;

            attachCreateButton($field);
            const $preview = ensurePreviewBox($field);


            // Initial render after ACF has loaded existing terms into the UI
            const initIds = getSelectedIdsFromAcfField($field);
            renderPreview($preview, initIds);

            // Keep preview in sync when user changes selection
            $field.on('change', 'select', () => renderPreview($preview, getSelectedIdsFromAcfField($field)));
            $field.on('change', 'input[type="checkbox"]', () => renderPreview($preview, getSelectedIdsFromAcfField($field)));
        });

        acf.addAction('ready', function ($field) {

            $(`.acf-field-taxonomy[data-name="${taxName}"]`).each(function () {
                const $field = $(this);
                attachCreateButton($field);
                const $preview = ensurePreviewBox($field);
                renderPreview($preview, getSelectedIdsFromAcfField($field));
                $field.on('change', 'select', () => renderPreview($preview, getSelectedIdsFromAcfField($field)));
                $field.on('change', 'input[type="checkbox"]', () => renderPreview($preview, getSelectedIdsFromAcfField($field)));
            });
        });
    } else if ($nativeBox.length) {
        // Fallback: native taxonomy meta box (no ACF custom field)
        // Insert preview below the native box
        const $preview = $(`<div class="dim-color-preview" style="margin:.5rem 0 0; display:flex; flex-wrap:wrap; gap:6px;"></div>`);
        $nativeBox.append($preview);

        const sync = () => renderPreview($preview, getSelectedIdsFromNative());
        sync();
        $nativeBox.on('change', 'input[type="checkbox"]', sync);

        // Add create button in native box title area
        const $title = $nativeBox.find('h2, .handlediv').first();
        const $btn = $(`<button type="button" class="button button-small" style="margin-left:8px;">Nieuwe kleur +</button>`);
        $title.append($btn);
        $btn.on('click', () => $modal.show());

        // Save in native mode selects cannot auto-add; creation still works
        $('#dim-save-color').on('click', function () {
            const name = $('#dim-color-name').val().trim();
            const hex  = $('#dim-color-hex').val().trim();
            const productId = document.getElementById('post_ID').value;
            if (!name) { $('#dim-color-error').text('Naam is verplicht'); return; }

            $(this).prop('disabled', true);
            $('#dim-color-error').text('');
            $.post(DIM_ADD_COLOR.ajax, {
                action: 'dim_create_color_term',
                nonce: DIM_ADD_COLOR.nonce,
                taxonomy: taxName,
                name,
                hex,
                product_id: productId
            }).done((resp) => {
                $('#dim-save-color').prop('disabled', false);
                if (!resp.success) { $('#dim-color-error').text(resp.data?.message || 'Fout bij opslaan'); return; }
                // We could append a new checkbox here if needed
                $modal.hide();
            }).fail(() => {
                $('#dim-save-color').prop('disabled', false);
                $('#dim-color-error').text('Kon kleur niet opslaan.');
            });
        });
    }
})(jQuery);


