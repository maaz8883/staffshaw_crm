(function () {
    var tableBody = document.getElementById('brief-forms-body');
    var addBtn = document.getElementById('add-brief-form-btn');
    var template = document.getElementById('brief-form-row-template');
    var config = window.BRAND_BRIEF_FORMS || {};

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

    function destroyUrlFor(formId) {
        return String(config.destroyUrlTemplate || '').replace('__FORM_ID__', String(formId));
    }

    function confirmDelete(title, text) {
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Delete',
            }).then(function (result) {
                return result.isConfirmed;
            });
        }

        return Promise.resolve(window.confirm(title + (text ? '\n' + text : '')));
    }

    function parseJsonResponse(response) {
        return response.json().then(function (payload) {
            if (!response.ok) {
                var message = payload.message;

                if (!message && payload.errors) {
                    message = Object.values(payload.errors).flat().join('\n');
                }

                throw new Error(message || 'Request failed.');
            }

            return payload;
        });
    }

    function removeRowFromDom(row) {
        row.remove();
        reindexRows();
    }

    function markRowDeleted(row) {
        var deleteInput = row.querySelector('.brief-form-delete-flag');

        if (deleteInput) {
            deleteInput.value = '1';
        }

        row.classList.add('d-none');
        reindexRows();
    }

    function deleteBriefFormRow(row, btn) {
        var idInput = row.querySelector('.brief-form-id');
        var formId = idInput ? idInput.value : '';

        if (!formId) {
            removeRowFromDom(row);
            return;
        }

        if (!config.destroyUrlTemplate) {
            markRowDeleted(row);
            return;
        }

        confirmDelete('Delete this brief form?', 'This cannot be undone.').then(function (confirmed) {
            if (!confirmed) {
                return;
            }

            btn.disabled = true;
            row.classList.add('brief-form-row-loading');

            fetch(destroyUrlFor(formId), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(parseJsonResponse)
                .then(function () {
                    removeRowFromDom(row);
                })
                .catch(function (error) {
                    alert(error.message || 'Could not delete brief form.');
                    row.classList.remove('brief-form-row-loading');
                    btn.disabled = false;
                });
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

                deleteBriefFormRow(row, btn);
            });
        });
    }

    function updateRowWithSavedData(row, data) {
        var idCell = row.querySelector('.brief-form-id-cell');

        if (idCell) {
            idCell.innerHTML = '<code>#' + data.id + '</code>';
        }

        var docCell = row.querySelector('.brief-form-document-cell');

        if (docCell && !docCell.querySelector('.brief-form-id')) {
            var hiddenId = document.createElement('input');
            hiddenId.type = 'hidden';
            hiddenId.name = row.querySelector('.brief-form-name').name.replace('[name]', '[id]');
            hiddenId.className = 'brief-form-id';
            hiddenId.value = String(data.id);

            var deleteFlag = docCell.querySelector('.brief-form-delete-flag');

            if (deleteFlag) {
                docCell.insertBefore(hiddenId, deleteFlag);
            } else {
                docCell.insertBefore(hiddenId, docCell.firstChild);
            }
        }

        if (config.builderEnabled) {
            var builderCell = row.querySelector('.brief-form-builder-cell');

            if (builderCell) {
                builderCell.innerHTML =
                    '<a href="' + escapeAttr(data.build_url) + '" class="btn btn-sm btn-outline-primary">Build</a>';
            }
        }
    }

    function escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function createBriefFormViaAjax() {
        addBtn.disabled = true;

        var row = template.content.firstElementChild.cloneNode(true);
        row.classList.add('brief-form-row-loading');
        tableBody.appendChild(row);
        reindexRows();
        bindRemoveButtons();

        fetch(config.storeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ name: 'Brief Form', is_active: true }),
        })
            .then(parseJsonResponse)
            .then(function (data) {
                updateRowWithSavedData(row, data);
                row.classList.remove('brief-form-row-loading');
            })
            .catch(function (error) {
                alert(error.message || 'Could not create brief form.');
                row.remove();
                reindexRows();
            })
            .finally(function () {
                addBtn.disabled = false;
            });
    }

    addBtn.addEventListener('click', function () {
        if (config.storeUrl && config.builderEnabled) {
            createBriefFormViaAjax();
            return;
        }

        var clone = template.content.firstElementChild.cloneNode(true);
        tableBody.appendChild(clone);
        reindexRows();
        bindRemoveButtons();
    });

    bindRemoveButtons();
})();
