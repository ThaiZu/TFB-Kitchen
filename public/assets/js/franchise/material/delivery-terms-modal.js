(() => {
    const modalEl = document.getElementById('deliveryTermsModal');
    if (!modalEl) return;

    const modal     = new bootstrap.Modal(modalEl);
    const saveBtn   = modalEl.querySelector('[data-delivery-terms-save]');
    const form      = modalEl.querySelector('#deliveryTermsForm');

    // pola
    const idShop          = form.querySelector('#id_shop');
    const selectSup       = form.querySelector('#id_supplier');
    const leadInput       = form.querySelector('#lead_time_hours');
    const deliveryBlock   = modalEl.querySelector('.delivery_terms');
    const idMaterial   = form.querySelector('#id_material');

    let suppliersCache = [];          // zapamiętujemy listę dostawców

    window.runSupplierDeliveryTerms = (materialId) => {
        form.reset();
        deliveryBlock.classList.add('d-none');
        idMaterial.value = materialId;

        /* najpierw spróbuj zaliczyć krok – modal pokaż dopiero,
           jeśli będzie faktycznie coś do uzupełnienia */
        loadSuppliers(materialId);
    };

    /* ---------- pobranie listy dostawców ---------- */
    const fetchSuppliers = async (materialId, shopId) =>
        api(`/ajax/shops/${shopId}/materials/${materialId}/material-suppliers`,
            { method: 'GET' })
            .then(res => res.data || [])
            .catch(()  => []);

    async function loadSuppliers(materialId) {
        suppliersCache = await fetchSuppliers(materialId, idShop.value);

        /* 1. policz dostawców, którzy mają już delivery_days */
        const rejected = suppliersCache.filter(s => {
            const daysFlat   = s.delivery_days ?? [];                   // (historyczne)
            const daysNested = s.delivery_terms?.delivery_days ?? [];   // (aktualne)
            return daysFlat.length > 0 || daysNested.length > 0;
        }).length;

        /* 2. jeśli któryś spełnia warunek → auto-zalicz krok i nie pokazuj modala */
        if (rejected > 0) {
            showSnack(true, '["delivery_terms_already_set"]');
            emitStatus(idMaterial.value, 'delivery_terms', true);
            return;                              // nie wywołujemy modal.show()
        }

        /* 3. brak skonfigurowanych → przygotuj listę i otwórz modal */
        selectSup.innerHTML =
            `<option value="-1" selected>['select_supplier']</option>`;

        suppliersCache.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            selectSup.appendChild(opt);
        });

        modal.show();                          // pokazujemy dopiero teraz
    }

    /* ---------- reaguj na wybór dostawcy ---------- */
    selectSup.addEventListener('change', () => {
        const supplierId = selectSup.value;

        // nic wybranego → ukryj blok
        if (!supplierId) {
            deliveryBlock.classList.add('d-none');
            leadInput.value = '';
            return;
        }

        const supplier = suppliersCache.find(s => String(s.id) === supplierId);

        // pokaż blok i uzupełnij lead time (jeśli jest)
        deliveryBlock.classList.remove('d-none');
        leadInput.value = supplier?.lead_time_hours ?? '';
    });

    /* ---------- zapis ---------- */
    saveBtn.addEventListener('click', async () => {
        const supplierId = selectSup.value;
        if (!supplierId) {
            alert(['select_supplier']);
            return;
        }

        const payload = {
            lead_time_hours  : leadInput.value,
            delivery_days    : [...form.querySelectorAll('input[name="delivery_days[]"]:checked')]
                .map(cb => cb.value)           // [1,3,5] itd.
        };

        try {
            const res = await api(`/ajax/shops/${idShop.value}/suppliers/${supplierId}/delivery-terms`, {
                method : 'PATCH',
                data   : payload
            });

            if (!res.ok) {
                showSnack(false, res.description || 'Server error');
                return;
            }

            /* 🔔 zmień status-box „delivery_terms” na zielony */
            emitStatus(idMaterial.value, 'delivery_terms', true);

            modal.hide();
            showSnack(true, res.message || 'Saved');
        } catch (err) {
            showSnack(false, err.message);
        }
    });
})();
