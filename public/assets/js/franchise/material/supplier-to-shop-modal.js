(() => {
    const modalEl = document.getElementById('supplierToShopModal');
    if (!modalEl) return;

    const modal      = new bootstrap.Modal(modalEl);
    const saveBtn    = modalEl.querySelector('[data-supplier-to-shop-save]');
    const form       = modalEl.querySelector('#supplierToShopForm');

    // pola
    const idMaterial = form.querySelector('#id_material');
    const idShop     = form.querySelector('#id_shop');
    const matNameEl  = modalEl.querySelector('.material_name');
    const selectSup  = form.querySelector('#id_supplier');

    let countryCode;   // ustawiamy przy otwarciu

    /* ---------- otwarcie modala ---------- */
    window.runSupplierToShopLink = (materialId, materialName, countryCodeArg) => {
        form.reset();
        idMaterial.value = materialId;
        matNameEl.textContent = materialName;
        countryCode = countryCodeArg;

        loadSuppliers(materialId);
        modal.show();
    };

    /* ---------- pobranie listy dostawców ---------- */
    async function fetchSuppliers(materialId, cCode) {
        return api(`/ajax/countries/${cCode}/materials/${materialId}/material-suppliers`,
            { method: 'GET' })
            .then(res => res.data || [])
            .catch(()  => []);
    }

    async function loadSuppliers(materialId) {
        const data = await fetchSuppliers(materialId, countryCode);

        // 1. wyczyść <select>
        selectSup.innerHTML = `<option value="">['select_supplier']</option>`;

        // 2. dodaj nowe <option>
        if (data.length === 0) {
            const opt = document.createElement('option');
            opt.disabled = true;
            opt.textContent = `['no_suppliers']`;
            selectSup.appendChild(opt);
            return;
        }

        data.forEach(s => {
            const opt   = document.createElement('option');
            opt.value   = s.id;                 // id dostawcy
            opt.textContent = s.name;           // nazwa dostawcy
            selectSup.appendChild(opt);
        });
    }

    /* ---------- zapis ---------- */
    saveBtn.addEventListener('click', async () => {
        const materialId = idMaterial.value;
        const shopId     = idShop.value;
        const supplierId = selectSup.value;

        if (!supplierId) {
            alert(`['select_supplier']`);
            return;
        }

        const payload = {
            id_shop : shopId,
            id_supplier : supplierId
        };

        try {
            const res = await api(`/ajax/shops/${shopId}/suppliers/${supplierId}/link`, {
                method : 'POST',
                data   : payload
            });

            if (!res.ok) {
                showSnack(false, res.description || 'Server error');
                return;
            }

            /* 🔔 zgłoś sukces – krok: supplier_to_shop */
            emitStatus(materialId, 'supplier_to_shop', true);

            modal.hide();
            showSnack(true, res.message || 'Saved');
        } catch (err) {
            showSnack(false, err.message);
        }

    });
})();
