function addMaterial(btn)
{
    const row = btn.parentNode.parentNode;
    const input = row.cells[1].getElementsByTagName("input")[0];
    const select = row.getElementsByTagName("select")[0];
    const table = document.getElementById('id_material_table_body');
    const new_row = table.insertRow(table.length);
    const material_name = select.options[select.selectedIndex].text;

    if(select.value == -1){
        select.classList.add("is-invalid")
        return;
    } else {
        select.classList.remove("is-invalid")
    }

    if(input.value <= 0){
        input.classList.add("is-invalid")
        return;
    } else {
        input.classList.remove("is-invalid")
    }


    if(isExistInTable(material_name)){
        return;
    }

    new_row.insertCell(0).innerHTML = `
        ${material_name}
        <input type="hidden" value="${select.value}" name="materials[${select.value}][id]">
        <input type="hidden" value="${material_name}" name="materials[${select.value}][name]">
        `;

    new_row.insertCell(1).innerHTML = `<input type="number" class="form-control" name="materials[${select.value}][quantity]" step="any" value="${input.value}">`;
    new_row.insertCell(2).innerHTML = `<button class="btn btn-danger" onclick="removeRow(this)" type="button">X</button>`;

    resetAllControlInElem(row);
}

function addSubrecipe(btn)
{
    const row = btn.parentNode.parentNode;
    const input = row.cells[1].getElementsByTagName("input")[0];
    const select = row.getElementsByTagName("select")[0];
    const table = document.getElementById('id_subrecipe_table_body');
    const new_row = table.insertRow(table.length);
    const material_name = select.options[select.selectedIndex].text;


    if(select.value == -1){
        select.classList.add("is-invalid")
        return;
    } else {
        select.classList.remove("is-invalid")
    }

    if(input.value <= 0){
        input.classList.add("is-invalid")
        return;
    } else {
        input.classList.remove("is-invalid")
    }

    if(isExistInTable(material_name)){
        return;
    }

    new_row.insertCell(0).innerHTML = `
        ${material_name}
        <input type="hidden" value="${select.value}" name="subrecipes[${select.value}][id]">
        <input type="hidden" value="${material_name}" name="subrecipes[${select.value}][name]">
        `;

    new_row.insertCell(1).innerHTML = `<input type="number" class="form-control" name="subrecipes[${select.value}][quantity]" value="${input.value}">`;
    new_row.insertCell(2).innerHTML = `<button class="btn btn-danger" onclick="removeRow(this)" type="button">X</button>`;

    resetAllControlInElem(row);
}

function addPreparationType(btn){
    const row = btn.parentNode.parentNode;
    const input = row.cells[1].getElementsByTagName("input")[0];
    const select = row.getElementsByTagName("select")[0];
    const table = document.getElementById('id_preparation_type_table_body');
    const new_row = table.insertRow(table.length);
    const preparation_type_name = select.options[select.selectedIndex].text;

    if(select.value == -1){
        select.classList.add("is-invalid")
        return;
    } else {
        select.classList.remove("is-invalid")
    }

    if(input.value <= 0){
        input.classList.add("is-invalid")
        return;
    } else {
        input.classList.remove("is-invalid")
    }

    new_row.insertCell(0).innerHTML = `
        ${preparation_type_name}
        <input type="hidden" value="${select.value}" name="preparation_types[${select.value}][id]">
        <input type="hidden" value="${preparation_type_name}" name="preparation_types[${select.value}][name]">
        `;

    new_row.insertCell(1).innerHTML = `<input type="number" class="form-control" name="preparation_types[${select.value}][duration_second]" value="${input.value}">`;
    new_row.insertCell(2).innerHTML = `<button class="btn btn-danger" onclick="removeRow(this)" type="button">X</button>`;

    resetAllControlInElem(row);
}

function addPreparationStep(btn){
    const row = btn.parentNode.parentNode;
    const txtarea = row.getElementsByTagName("textarea")[0];
    const table = document.getElementById('id_preparation_step_table_body');
    const new_row = table.insertRow(table.rows.length);

    new_row.insertCell(0).innerText = table.rows.length;
    new_row.insertCell(1).innerHTML = `<textarea name="preparation_steps[]" class="form-control">${txtarea.value}</textarea>`;
    new_row.insertCell(2).innerHTML = `<button class="btn btn-danger" onclick="removeRow(this)" type="button">X</button>`;

    resetAllControlInElem(row);
}

function setUnit(value, id_control){
    document.getElementById(id_control).value = value;
    document.getElementById(id_control).value = value;
}

function isExistInTable(elem){
    const rows = document.getElementById('id_material_table_body').rows;

    for(let i=0; i<rows.length; i++){
        if(rows[i].cells.length > 0)
            if(rows[i].cells[0].innerText == elem)
            {
                const elem = rows[i];
                elem.className = "mark-table-row";
                setTimeout(function(){ elem.className = elem.className.replace("mark-table-row", ""); }, 2000);
                return true;
            }
    }

    return false;
}