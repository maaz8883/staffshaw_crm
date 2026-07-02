(function () {
    var tableBody = document.getElementById('brief-forms-body');
    var addBtn = document.getElementById('add-brief-form-btn');
    var template = document.getElementById('brief-form-row-template');

    if (!tableBody || !addBtn || !template) {
        return;
    }

    function reindexRows() {
        var visibleIndex = 0;

        tableBody.querySelectorAll('tr.brief-form-row').forEach(function (row) {
            if (row.classList.contains('d-none')) {
                return;
            }

            row.dataset.index = String(visibleIndex);
            row.querySelectorAll('[name^="brief_forms["]').forEach(function (input) {
                input.name = input.name.replace(/brief_forms\[\d+\]/, 'brief_forms[' + visibleIndex + ']');
            });

            visibleIndex += 1;
        });
    }

    function bindRemoveButtons() {
        tableBody.querySelectorAll('.remove-brief-form-row').forEach(function (btn) {
            if (btn.dataset.bound === '1') {
                return;
            }

            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                var row = btn.closest('tr.brief-form-row');

                if (!row) {
                    return;
                }

                var deleteInput = row.querySelector('.brief-form-delete-flag');

                if (deleteInput) {
                    deleteInput.value = '1';
                }

                row.classList.add('d-none');
                reindexRows();
            });
        });
    }

    addBtn.addEventListener('click', function () {
        var clone = template.content.firstElementChild.cloneNode(true);
        tableBody.appendChild(clone);
        reindexRows();
        bindRemoveButtons();
    });

    bindRemoveButtons();
})();
