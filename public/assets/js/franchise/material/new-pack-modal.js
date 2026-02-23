(() => {
    const modalEl = document.getElementById('newPackModal');
    if (!modalEl) return;                       // widok bez modala

    const modal        = new bootstrap.Modal(modalEl);
    const saveBtn      = modalEl.querySelector('[data-pack-save]');
    const form         = modalEl.querySelector('#packForm');
    const idMaterial   = form.querySelector('#id_material');
    const idUnit   = form.querySelector('#id_unit');
    const unitName   = form.querySelector('#pack_size_unit');
    const eanRow       = form.querySelector('#eanRow');
    const regexRow     = form.querySelector('#regexRow');
    const materialName     = modalEl.querySelector('.material_name');
    let countryCode;

    /* ---------- helper: przełącz wiersze EAN / REGEX ---------- */
    const toggleCodeRows = (codeType) => {
        const isEan = codeType === 'ean';
        eanRow.classList.toggle('d-none', !isEan);
        regexRow.classList.toggle('d-none', isEan);

        // wyczyść wartości
        form.querySelector('#ean').value                = '';
        form.querySelector('#regex').value              = '';
        form.querySelector('#unique_identification_key').value = '';
    };

    /* ---------- otwarcie modala ---------- */
    window.runNewPackModal = (materialIdArg, idUnitArg, unitNameArg, countryCodeArg, materialNameArg) => {
        form.reset();
        idMaterial.value = materialIdArg;
        idUnit.value = idUnitArg;
        unitName.value = unitNameArg;
        countryCode = countryCodeArg;
        materialName.innerText = materialNameArg;
        toggleCodeRows('ean');                   // domyślnie EAN
        modal.show();
    };

    /* ---------- reakcja na zmianę typu kodu ---------- */
    form.addEventListener('change', (e) => {
        if (e.target.name === 'code_type') {
            toggleCodeRows(e.target.value);
        }
    });

    /* ---------- zapis ---------- */
    saveBtn.addEventListener('click', async () => {
        const materialId     = idMaterial.value;
        const codeType       = form.code_type.value;              // ean / regex
        const ean            = form.ean.value.trim();
        const regex          = form.regex.value.trim();
        const uniqKey        = form.unique_identification_key.value.trim();

        /* walidacja */
        if (codeType === 'ean' && !ean) {
            alert('<?= addslashes($translations["ean_required"]) ?>');
            return;
        }
        if (codeType === 'regex' && (!regex || !uniqKey)) {
            alert('<?= addslashes($translations["regex_and_identification_key_required"]) ?>');
            return;
        }

        const payload = {
            id_material: materialId,
            identifier_type  : codeType === 'ean' ? 'EAN' : 'REGEX',
            identifier_value : codeType === 'ean' ? ean : uniqKey,
            identifier_regex : codeType === 'regex' ? regex : null,
            package_size_in_base_unit: form.pack_size.value,
            id_unit : form.id_unit.value,
            description: form.new_pack_description.value.trim(),
            pack_price_net: form.pack_price_net.value
        };


        try {
            const res = await api(`/ajax/countries/${countryCode}/packs`, {
                method: 'POST',
                data  : payload
            });

            if (!res.ok) {
                showSnack(false, res.description || 'Server error');
                return;
            }

            /* ✅ zgłoś sukces */
            emitStatus(materialId, 'pack', true);

            modal.hide();
            showSnack(true, res.message || 'Saved');
        } catch (err) {
            showSnack(false, err.message);
        }
    });
})();
