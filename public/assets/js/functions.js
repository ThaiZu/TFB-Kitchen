const getJSON = function(target, action, data = {}, method = 'POST', headers = {}) {
    return new Promise(function(resolve, reject) {
        const splitPathname = location.pathname.split("/");

        let baseUrl;
        if (splitPathname.includes("public")) {

            if(splitPathname[1] === "public"){
                baseUrl = `${location.origin}/${splitPathname[1]}`;
            } else {
                baseUrl = `${location.origin}/${splitPathname[1]}/${splitPathname[2]}`;
            }

        } else {
            baseUrl = `${location.origin}`;
        }

        let url = `${baseUrl}/admin/${target}/${action}`;

        // Obsługa danych dla metody GET – dodanie parametrów do URL
        if (method.toUpperCase() === 'GET' && Object.keys(data).length > 0) {
            const queryParams = new URLSearchParams(data).toString();
            url += `?${queryParams}`;
        }

        // Ustawienie domyślnych nagłówków
        let defaultHeaders = {
            'Content-Type': 'application/x-www-form-urlencoded'
        };

        // Łączenie domyślnych nagłówków z dodatkowymi (np. Authorization)
        headers = Object.assign(defaultHeaders, headers);

        // Ustawienie opcji fetch
        let options = {
            method: method,
            headers: headers
        };

        // Obsługa ciała zapytania dla metod innych niż GET
        if (method.toUpperCase() !== 'GET') {
            if (headers['Content-Type'] === 'application/json') {
                options.body = JSON.stringify(data);
            } else {
                options.body = new URLSearchParams(data).toString();
            }
        }

        // Wysyłanie zapytania
        fetch(url, options)
            .then(response => {
                if (!response.ok) {
                    return reject(Error(`HTTP error! status: ${response.status}`));
                }
                return response.json();
            })
            .then(data => resolve(data))
            .catch(error => reject(Error(`Fetch error: ${error.message}`)));
    });
};

const getPDF = function(target, action, data = {}, method = 'POST', headers = {}) {
    return new Promise(function(resolve, reject) {
        const splitPathname = location.pathname.split("/");

        let baseUrl;
        if (splitPathname.includes("public")) {

            if(splitPathname[1] === "public"){
                baseUrl = `${location.origin}/${splitPathname[1]}`;
            } else {
                baseUrl = `${location.origin}/${splitPathname[1]}/${splitPathname[2]}`;
            }

        } else {
            baseUrl = `${location.origin}`;
        }

        let url = `${baseUrl}/${target}/${action}`;

        // Ustawienie domyślnych nagłówków
        let defaultHeaders = {
            'Content-Type': 'application/x-www-form-urlencoded'
        };

        headers = Object.assign(defaultHeaders, headers);

        // Ustawienie opcji fetch
        let options = {
            method: method,
            headers: headers
        };

        if (method.toUpperCase() !== 'GET') {
            if (headers['Content-Type'] === 'application/json') {
                options.body = JSON.stringify(data);
            } else {
                options.body = new URLSearchParams(data).toString();
            }
        }

        // Pobieranie pliku PDF jako binarne dane (blob)
        fetch(url, options)
            .then(response => {
                if (!response.ok) {
                    return reject(Error(`HTTP error! status: ${response.status}`));
                }
                return response.blob();  // Pobieramy plik jako blob
            })
            .then(blob => resolve(blob))
            .catch(error => reject(Error(`Fetch error: ${error.message}`)));
    });
};

function sortTable(id_table, col_num = 0) {
    var table, rows, switching, i, x, y, shouldSwitch;
    table = document.getElementById(id_table);
    switching = true;
    /* Make a loop that will continue until
    no switching has been done: */
    while (switching) {
        // Start by saying: no switching is done:
        switching = false;
        rows = table.rows;
        /* Loop through all table rows (except the
        first, which contains table headers): */
        for (i = 0; i < rows.length-1; i++) {
            // Start by saying there should be no switching:
            shouldSwitch = false;
            /* Get the two elements you want to compare,
            one from current row and one from the next: */
            x = rows[i].getElementsByTagName("td")[col_num];
            y = rows[i + 1].getElementsByTagName("td")[col_num];
            // Check if the two rows should switch place:
            if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                // If so, mark as a switch and break the loop:
                shouldSwitch = true;
                break;
            }
        }
        if (shouldSwitch) {
            /* If a switch has been marked, make the switch
            and mark that a switch has been done: */
            rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
            switching = true;
        }
    }
}

function showHideRow(className) {
    [...document.getElementsByClassName(className)].forEach(node => node.classList.toggle('hideRow'));
}

function fillInputs(inputs){
    for(let i=0; i<inputs.length; i++){

        if(inputs[i].type == "number"){
            if(!inputs[i].value){
                inputs[i].value = 0;
            }
        }

        if(inputs[i].type == "text"){
            if(!inputs[i].value){
                inputs[i].value = "";
            }
        }

    }
}

function resetAllControlInElem(elem){
    const inputs = elem.getElementsByTagName("input");
    const selects = elem.getElementsByTagName("select");
    const textareas = elem.getElementsByTagName("textarea");

    for(let i=0; i<inputs.length; i++){
        inputs[i].value = "";
    }

    for(let i=0; i<selects.length; i++){
        selects[i].selectedIndex = 0;
    }

    for(let i=0; i<textareas.length; i++){
        textareas[i].value = "";
    }
}


function removeRow(btn){
    const row = btn.parentNode.parentNode;
    row.parentNode.removeChild(row);
}

function filterTable(input, tbody_name, sort_column = 0, uncheck_first_line = false) {
    // Declare variables
    var filter, tbody, tr, td, unchecked_td, i, txtValue;

    filter = input.value.toUpperCase();

    tbody = document.getElementById(tbody_name);
    tr = tbody.getElementsByTagName("tr");

    if(filter == "-1"){
        for (i = 0; i < tr.length; i++) {
            tr[i].style.display = "";
        }
        return;
    }

    // Loop through all table rows, and hide those who don't match the search query
    for (i = 0; i < tr.length; i++) {
        td = tr[i].getElementsByTagName("td")[sort_column];

        if(uncheck_first_line){
            unchecked_td = tr[i].getElementsByTagName("td")[0];
            const checkbox = unchecked_td.querySelector('input[type="checkbox"]');
            checkbox.checked = false;
        }
        if (td) {
            txtValue = td.textContent || td.innerText;

            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}

function setupCheckboxSelection(selectAllSelector, rowCheckboxSelector) {
    // Pobieramy checkbox z nagłówka oraz wszystkie checkboxy w wierszach tabeli
    const selectAllCheckbox = document.querySelector(selectAllSelector);
    const rowCheckboxes = document.querySelectorAll(rowCheckboxSelector);

    // Sprawdzamy, czy elementy istnieją
    if (!selectAllCheckbox || rowCheckboxes.length === 0) {
        console.error('Nie znaleziono elementów o podanych selektorach.');
        return;
    }

    // Funkcja do zaznaczania/odznaczania wszystkich checkboxów
    selectAllCheckbox.addEventListener('change', function() {
        rowCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Opcjonalnie: aktualizacja stanu checkboxa "selectAll", jeśli wszystkie są zaznaczone/odznaczone ręcznie
    rowCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (!this.checked) {
                selectAllCheckbox.checked = false;
            } else if (Array.from(rowCheckboxes).every(checkbox => checkbox.checked)) {
                selectAllCheckbox.checked = true;
            }
        });
    });
}

/**
 * Generuje slug z podanego tekstu, uwzględniając polskie i francuskie znaki diakrytyczne.
 * Możliwość łatwej rozbudowy o kolejne języki przez dodanie do mapy znaków.
 * @param {string} text
 * @returns {string}
 */
function generateSlug(text) {
    const charMap = {
        // polskie
        'ą': 'a', 'ć': 'c', 'ę': 'e', 'ł': 'l', 'ń': 'n', 'ó': 'o', 'ś': 's', 'ź': 'z', 'ż': 'z',
        'Ą': 'a', 'Ć': 'c', 'Ę': 'e', 'Ł': 'l', 'Ń': 'n', 'Ó': 'o', 'Ś': 's', 'Ź': 'z', 'Ż': 'z',
        // francuskie
        'à': 'a', 'â': 'a', 'ä': 'a', 'ç': 'c', 'é': 'e', 'è': 'e', 'ê': 'e', 'ë': 'e',
        'î': 'i', 'ï': 'i', 'ô': 'o', 'ö': 'o', 'ù': 'u', 'û': 'u', 'ü': 'u', 'ÿ': 'y',
        'À': 'a', 'Â': 'a', 'Ä': 'a', 'Ç': 'c', 'É': 'e', 'È': 'e', 'Ê': 'e', 'Ë': 'e',
        'Î': 'i', 'Ï': 'i', 'Ô': 'o', 'Ö': 'o', 'Ù': 'u', 'Û': 'u', 'Ü': 'u', 'Ÿ': 'y'
        // dodaj kolejne znaki według potrzeb
    };
    return text
        .toLowerCase()
        .split('')
        .map(char => charMap[char] || char)
        .join('')
        .replace(/[^a-z0-9]+/g, '-') // zamień wszystko co nie jest a-z lub 0-9 na -
        .replace(/^-+|-+$/g, '')     // usuń nadmiarowe myślniki z początku i końca
        .replace(/-{2,}/g, '-');     // zamień wielokrotne myślniki na pojedynczy
}