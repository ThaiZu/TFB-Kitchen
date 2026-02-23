document.addEventListener('DOMContentLoaded', function () {
    const langCode = document.getElementById('langCode')?.value || 'en';
    const aliasType = document.getElementById('aliasType')?.value || 'default';
    const select = document.getElementById('langSelect');
    const btn = document.getElementById('langLoadBtn');
    const allowedLangs = ['fr', 'en', 'pl', 'nl'];

    // Ustawienie selecta na podstawie URL
    const params = new URLSearchParams(window.location.search);
    const lang = params.get('lang');
    if (lang && allowedLangs.includes(lang)) {
        select.value = lang;
    } else {
        select.value = '-1';
    }

    // Obsługa kliknięcia "Wczytaj"
    btn.addEventListener('click', function () {
        const val = select.value;
        if (allowedLangs.includes(val)) {
            const url = new URL(window.location.href);
            url.searchParams.set('lang', val);
            window.location.href = url.toString();
        } else {
            alert(window.translations?.select_valid_language || 'Please select a valid language');
        }
    });

    // Obsługa edycji aliasów
    document.querySelectorAll('.btn-edit-alias').forEach(editBtn => {
        editBtn.addEventListener('click', function () {
            const row = editBtn.closest('tr');
            row.querySelector('.alias-text').classList.add('d-none');
            row.querySelector('.alias-input').classList.remove('d-none');
            editBtn.classList.add('d-none');
            row.querySelector('.btn-save-alias').classList.remove('d-none');
        });
    });

    document.querySelectorAll('.btn-save-alias').forEach(saveBtn => {
        saveBtn.addEventListener('click', async function () {
            const row = saveBtn.closest('tr');
            const newAlias = row.querySelector('.alias-input').value;
            const idElem = row.getAttribute('data-alias-id_elem');
            const endpoint = window.endpoint;

            const data = {
                id: idElem,
                lang_code: langCode,
                alias: newAlias,
                alias_type: aliasType
            };

            api(endpoint.replace('{id}', idElem), {
                method: 'PATCH',
                data
            })
                .then(data => {
                    showSnack(data.ok, window.translations?.save_was_successful || 'Saved!');
                    row.querySelector('.alias-text').textContent = newAlias;
                    row.querySelector('.alias-text').classList.remove('d-none');
                    row.querySelector('.alias-input').classList.add('d-none');
                    row.querySelector('.btn-edit-alias').classList.remove('d-none');
                    saveBtn.classList.add('d-none');
                })
                .catch(error => {
                    alert(error?.message || window.translations?.error_saving_alias || 'Error saving alias');
                });
        });
    });
});
