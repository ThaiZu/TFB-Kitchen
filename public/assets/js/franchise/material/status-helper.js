window.emitStatus = function (materialId, statusType, ok = true) {

    document.dispatchEvent(new CustomEvent('status-box:update', {
        detail: { materialId, statusType, ok }
    }));
};

(function () {
    /** sprawdza czy wszystkie boxy w wierszu mają klasę status-ok */
    function rowAllOk(row) {
        return [...row.querySelectorAll('.status-box')]
            .every(b => b.classList.contains('status-ok'));
    }

    document.addEventListener('status-box:update', ({ detail }) => {
        const { materialId, statusType, ok } = detail;

        /* 1. znajdź odpowiedni kwadracik */
        const selector =
            `.status-box[data-material-id="${materialId}"][data-status="${statusType}"]`;
        const box = document.querySelector(selector);
        if (!box) return;                       // brak? nic nie robimy

        /* 2. podmień klasę szary ↔ zielony */
        box.classList.toggle('status-ok',   ok);
        box.classList.toggle('status-miss', !ok);

        /* 3. zgaś czerwone tło wiersza, jeżeli wszystkie boxy są zielone */
        const tr = box.closest('tr');
        if (tr && rowAllOk(tr)) {
            tr.classList.remove('bg-light-danger'); // klasa Bootstrapa
            tr.style.backgroundColor = '';          // usuwamy inline-style (jeśli był)
        }
    });
})();
