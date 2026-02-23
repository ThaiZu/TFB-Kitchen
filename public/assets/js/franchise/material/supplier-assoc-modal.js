(() => {
    const modalEl = document.getElementById('supplierAssociationModal');
    if (!modalEl) return;

    const modal        = new bootstrap.Modal(modalEl);
    const saveBtn      = modalEl.querySelector('[data-assoc-save]');
    const form         = modalEl.querySelector('#assocForm');
    const materialName     = modalEl.querySelector('.material_name');
    const idMaterial   = form.querySelector('#id_material');
    const packTbody   = form.querySelector('.pack_tbody');
    let countryCode;


    /* ---------- otwarcie modala ---------- */
    window.runSupplierAssociationModal = (idMaterialArg, materialNameArg, countryCodeArg) => {
        form.reset();
        materialName.innerText = materialNameArg;
        idMaterial.value = idMaterialArg;
        countryCode = countryCodeArg;

        loadAvailblePacks(idMaterialArg);

        modal.show();
    };

    async function getPackagingsByMaterial(idMaterial, countryCode) {
        return await api(`/ajax/countries/${countryCode}/materials/${idMaterial}/packs`, {method: 'GET'})
            .then(function (res) {
                console.log(res);
                return res.data;
            })
            .catch(function (err) {
                console.error(err);
                return [];
            });
    }

    function loadAvailblePacks(idMaterial) {
        packTbody.innerHTML = "";

        getPackagingsByMaterial(idMaterial, countryCode)
            .then(packagings => {
                if (packagings.length > 0) {
                    packagings.forEach(function (pack) {
                        const row  = packTbody.insertRow();
                        const c0   = row.insertCell(0);
                        const cb   = document.createElement('input');
                        cb.type    = 'checkbox';
                        cb.className = 'form-check-input';
                        cb.value   = pack.id;

                        cb.addEventListener('change', () => toggleSkuInput(cb)); // ⬅️

                        c0.appendChild(cb);

                        row.insertCell(1).innerHTML = `${pack.description} <input type="hidden" value="${pack.id}">`;
                        row.insertCell(2).innerText = `${pack.package_size_in_base_unit}${pack.unit_name}`;
                        row.insertCell(3).innerHTML = '';
                    });
                } else {
                    packTbody.innerHTML = `<tr><td colspan="2" class="text-center"><?=$translations['no_packaging_found']?></td></tr>`;
                }
            })
    }

    function toggleSkuInput(checkbox) {
        const row     = checkbox.closest('tr');
        const skuCell = row.children[3];

        if (checkbox.checked) {
            skuCell.innerHTML =
                `<input type="text" class="form-control mt-2"
              placeholder="SKU">`;
        } else {
            skuCell.innerHTML = '';
        }
    }

    saveBtn.addEventListener('click', async () => {
        const materialId = idMaterial.value;                          // hidden z modala
        const supplierId = form.querySelector('#id_supplier').value;  // select/datalist

        if (!supplierId) {
            alert('<?= addslashes($translations["select_supplier"]) ?>');
            return;
        }


        /* 1. wybrano paczki? */
        const checked = [...packTbody.querySelectorAll('input[type="checkbox"]:checked')];
        if (checked.length === 0) {
            alert('<?= addslashes($translations["select_packaging"]) ?>');
            return;
        }


        /* 2. zbuduj tablicę {id_packaging, supplier_sku} */
        const packagings = checked.map(cb => {
            const row      = cb.closest('tr');
            const skuInput = row.querySelector('td:nth-child(4) input');
            return {
                id_packaging : cb.value,                    // value ustawiliśmy na pack.id
                supplier_sku : skuInput ? skuInput.value.trim() : ''
            };
        });

        /* 3. payload i request */
        const payload = {
            id_material : materialId,
            id_supplier : supplierId,
            packagings  : packagings
        };

        try {
                const res = await api(`/ajax/material-suppliers/${supplierId}/materials`, {
                method : 'POST',
                data   : payload
            });

            if (!res.ok) {
                showSnack(false, res.description || 'Server error');
                return;
            }

            /* 4. sukces → pomaluj box „supplier” */
            emitStatus(materialId, 'supplier_assoc', true);

            modal.hide();
            showSnack(true, res.message || 'Saved');
        } catch (err) {
            showSnack(false, err.message);
        }
    });

})();