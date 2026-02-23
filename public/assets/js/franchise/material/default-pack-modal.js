(() => {
    const modalEl = document.getElementById('defaultPackModal');
    if (!modalEl) return;

    const modal     = new bootstrap.Modal(modalEl);
    const saveBtn   = modalEl.querySelector('[data-default-pack-save]');
    const form      = modalEl.querySelector('#defaultPackForm');

    // pola
    const idShop      = form.querySelector('#id_shop');
    const idMaterial  = form.querySelector('#id_material');
    const selectPack  = form.querySelector('#pack_select');
    const matNameEl   = modalEl.querySelector('.material_name');

    let packsCache = [];           // zapamiętujemy paczki

    /* ---------- otwarcie ---------- */
    window.runDefaultPackAssotiationModal = (shopId, materialId) => {
        form.reset();
        idShop.value     = shopId;
        idMaterial.value = materialId;
        matNameEl.textContent = '';          // uzupełnisz, jeśli podasz nazwę

        loadPacks(shopId, materialId);
        modal.show();
    };

    /* ---------- fetch paczek ---------- */
    const fetchPacks = async (shopId, materialId) =>
        api(`/ajax/shops/${shopId}/materials/${materialId}/packs/default`,
            { method: 'GET' })
            .then(res => res.data?.available_packs ?? [])
            .catch(()  => []);

    /* ---------- zbuduj etykietę option ---------- */
    const makeLabel = (p) => {
        const supplier = p.supplier_name ?? '["no_supplier"]';
        const sku      = p.supplier_sku   ?? '–';
        return `${supplier} – SKU: ${sku} – ${p.package_size_in_base_unit} ${p.unit_name}`;
    };

    /* ---------- wypełnij select ---------- */
    async function loadPacks(shopId, materialId) {
        packsCache = await fetchPacks(shopId, materialId);

        selectPack.innerHTML =
            `<option value="-1" selected>['select_pack']</option>`;

        if (packsCache.length === 0) {
            const opt = document.createElement('option');
            opt.disabled = true;
            opt.value = '';
            opt.textContent = `['no_packaging_found']`;
            selectPack.appendChild(opt);
            return;
        }
        packsCache
            .forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = makeLabel(p);
                selectPack.appendChild(opt);
            });
    }

    /* ---------- zapis ---------- */
    saveBtn.addEventListener('click', async () => {
        const packId = selectPack.value;
        if (packId === '-1' || packId === '') {
            alert('<?= addslashes($translations["select_pack"]) ?>');
            return;
        }

        /* ❶ znajdź paczkę w cache */
        const pack = packsCache.find(p => String(p.id) === packId);
        const idSupplier = pack ? pack.id_supplier : null;

        /* ❷ payload z id_supplier */
        const payload = {
            id_material : idMaterial.value,
            id_supplier : idSupplier,          // ← dodane
            id_pack     : packId,
            id_shop     : idShop.value
        };

        try {
            const res = await api('/ajax/material-packs/default', {
                method : 'PATCH',
                data   : payload
            });

            if (!res.ok) {
                showSnack(false, res.description || 'Server error');
                return;
            }

            emitStatus(idMaterial.value, 'default_pack', true);

            modal.hide();
            showSnack(true, res.message || 'Saved');
        } catch (err) {
            showSnack(false, err.message);
        }
    });
})();
