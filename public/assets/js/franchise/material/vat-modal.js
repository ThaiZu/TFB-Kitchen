// VAT-modal logic – czysty ES6
(function () {
    const modalEl   = document.getElementById('materialVatModal');
    if (!modalEl) return;        // brak modala = nic nie robimy

    const modal     = new bootstrap.Modal(modalEl);
    const saveBtn   = modalEl.querySelector('[data-vat-save]');
    const form      = modalEl.querySelector('#vat_rates_form');
    const nameH4    = modalEl.querySelector('#material_name_vat_modal');
    const idInput   = modalEl.querySelector('#id_material_vat_modal');

    /** Otwiera modal z danymi materiału */
    window.runVatRateModal = (idMaterial, materialName) => {
        idInput.value = idMaterial;
        nameH4.textContent = materialName;
        modal.show();
    };

    /** Zapis stawek VAT */
    saveBtn.addEventListener('click', async () => {
        const idMaterial = idInput.value;
        const rates = [...form.querySelectorAll('tbody tr')]
            .map(tr => ({
                country_code : tr.dataset.countryCode,
                vat_rate     : parseFloat(tr.querySelector('input').value) || null
            }))
            .filter(r => r.vat_rate !== null);

        try {
            const res = await api(`/ajax/materials/${idMaterial}/vat-rates`, {
                method : 'POST',
                headers: { 'Content-Type': 'application/json' },
                data   : rates
            });

            if (!res.ok) {
                showSnack(false, res.description || 'Server error');
                return;
            }

            emitStatus(idMaterial, 'vat', true);

            modal.hide();
            form.reset();                       // czyścimy inputy
            showSnack(true);


        } catch (e) {
            showSnack(false);
        }
    });


})();
