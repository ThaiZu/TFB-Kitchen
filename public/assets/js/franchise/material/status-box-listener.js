(function () {
    /** helper: czy wszystkie boxy w wierszu są zielone? */
    const rowAllOk = (row) =>
        [...row.querySelectorAll('.status-box')]
            .every(b => b.classList.contains('status-ok'));

    /** uniwersalny odbiornik */
    document.addEventListener('status-box:update', ({ detail }) => {
        const { materialId, statusType, ok } = detail;

        const box = document.querySelector(
            `.status-box[data-material-id="${materialId}"][data-status="${statusType}"]`
        );
        if (!box) return;                        // brak – nic nie robimy

        box.classList.toggle('status-ok',   ok);
        box.classList.toggle('status-miss', !ok);
        box.removeAttribute('onclick');          // już nie otwieraj modala

        // jeśli cały wiersz spełniony → zdejmij czerwone tło
        const tr = box.closest('tr');
        if (tr && rowAllOk(tr)) tr.classList.remove('bg-light-danger');
    });
})();
