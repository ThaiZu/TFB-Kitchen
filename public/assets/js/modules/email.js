async function saveTemplate() {
    var htmlContent = $("#email_subject").summernote("code");
    var tempDiv = document.createElement("div");
    tempDiv.innerHTML = htmlContent;
    var plainTextSubject = tempDiv.textContent || tempDiv.innerText || "";
    var plainTextBody = $("#email_body").summernote("code")
    var plainTextAdditBody = $("#email_franchisee_additional_body").summernote("code")
    const emailBc = document.getElementById('email_bc').value;
    const emailBcc = document.getElementById('email_bcc').value;
    const idTemplate = document.getElementById('id_template').value;


    const data = {
        "id": idTemplate,
        "email_bc": emailBc,
        "email_bcc": emailBcc,
        "subject_content": plainTextSubject,
        "body_content": plainTextBody,
        "franchisee_additional_body": plainTextAdditBody
    }

    const result = await api(`/ajax/email-templates/${idTemplate}`, {
        method: 'PATCH',
        data
    })
        .then(result => {

            if (!result.ok) {
                showSnack(false, result.description);

                return;
            }

            showSnack(true);
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        })
        .catch(err => {
            console.error(err);
            showSnack(false);
        })
}

// const templateHints = {

//     reminder: [
//         '{{ client_name }}',
//         '{{ invoice_number }}',
//         '{{ due_date }}',
//         '{{ payment_link }}'
//     ]
// };

function validateTemplateVariables() {
    // wszystkie edytory, które sprawdzamy
    const editors = [
        {id: '#email_subject', label: window.translations.subject},
        {id: '#email_body', label: window.translations.body},
        {id: '#email_franchisee_additional_body', label: window.translations.additional_franchisee_body},
        {id: '#email_franchisor_additional_body', label: window.translations.additional_franchisor_body}
    ];

    let errors = [];

    editors.forEach(editor => {
        let content = $(editor.id).summernote('code'); // pobiera treść HTML z edytora
        let matches = content.match(/\[\[\s*[\w_]+\s*\]\]/g) || [];

        matches.forEach(match => {
            if (!currentWords.includes(match)) {
                errors.push(`${window.translations.field} "${editor.label}" ${window.translations.contains_unknown_variable}: ${match}`);
            }
        });
    });

    if (errors.length > 0) {
        alert(errors.join("\n"));
        return false;
    }

    return true;
}


function initSummernote(selector, words = [], customOptions = {}) {
    const baseConfig = {
        tabsize: 2,
        height: 120,
        disableDragAndDrop: true,
        toolbar: [
            ['style', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
            ['font', ['fontname', 'fontsize', 'color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link']],
            ['view', ['codeview', 'help']]
        ],
        hint: {
            words: words,
            match: /(\[\[\s*\w*)$/,
            search: function (keyword, callback) {
                callback($.grep(this.words, function (item) {
                    return item.indexOf(keyword) === 0;
                }));
            }
        }
    };

    const finalConfig = $.extend(true, {}, baseConfig, customOptions);
    $(selector).summernote(finalConfig);
}

function disableSummernote(selector) {
    $(selector).summernote('disable');
    $(selector).siblings('.note-editor').find('.note-toolbar').hide();
}


function renderTemplateHints(hints) {
    const list = document.getElementById('template-hints-list');
    list.innerHTML = '';
    hints.forEach(hint => {
        const li = document.createElement('li');
        li.textContent = hint;
        li.style.cursor = 'pointer';

        const description = hintDescriptions[hint] || window.translations['click_to_copy'];
        li.setAttribute('data-tooltip', description);

        li.addEventListener('click', () => {
            insertHintToClipboard(hint);
        });

        list.appendChild(li);
    });
}

function insertHintToClipboard(hint) {
    navigator.clipboard.writeText(hint)
        .then(() => {
            showSnack(true, `${window.translations['copied_to_clipboard']} ${window.translations['use_shortcout_to_paste']}`);
        })
        .catch(err => {
            console.error('Błąd kopiowania:', err);
            alert(window.translations['copy_failure']);
        });
}

function prepareEmailPreview() {
    const subjectHtml = $('#email_subject').summernote('code');
    const bodyHtml = $('#email_body').summernote('code') +
        "<br>" + $('#email_franchisee_additional_body').summernote('code') +
        "<br>" + $('#email_franchisor_additional_body').summernote('code')

    let parsedSubject = subjectHtml;
    let parsedBody = bodyHtml;

    for (let key in sampleData) {
        const value = sampleData[key];
        parsedSubject = parsedSubject.replaceAll(key, value);
        parsedBody = parsedBody.replaceAll(key, value);
    }

    document.getElementById('email-preview-subject').innerHTML = parsedSubject;
    document.getElementById('email-preview-body').innerHTML = parsedBody;
}