(function () {
    var cfg = window.BRIEF_FORM_BUILDER;
    if (!cfg) return;

    var schema = JSON.parse(JSON.stringify(cfg.initialSchema || { version: 1, title: '', sections: [] }));
    var sectionsEl = document.getElementById('builder-sections');
    var previewEl = document.getElementById('builder-preview');
    var titleEl = document.getElementById('schema-title');
    var schemaInput = document.getElementById('schema-json-input');
    var templateSelect = document.getElementById('template-select');

    function uid(prefix) {
        return prefix + '_' + Math.random().toString(36).slice(2, 8);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function normalizeSchema() {
        schema.version = 1;
        schema.title = titleEl ? titleEl.value.trim() : (schema.title || 'Brief Form');
        if (!Array.isArray(schema.sections)) schema.sections = [];
        if (schema.sections.length === 0) {
            schema.sections.push({ id: uid('section'), title: 'Main', fields: [] });
        }
    }

    function renderBuilder() {
        normalizeSchema();
        sectionsEl.innerHTML = '';

        schema.sections.forEach(function (section, sIndex) {
            var wrap = document.createElement('div');
            wrap.className = 'border rounded p-3 mb-3';
            wrap.innerHTML =
                '<div class="d-flex justify-content-between align-items-center mb-2">' +
                    '<input type="text" class="form-control form-control-sm section-title-input" data-s-index="' + sIndex + '" value="' + escapeHtml(section.title || '') + '" placeholder="Section title">' +
                    '<div class="btn-group btn-group-sm ms-2">' +
                        '<button type="button" class="btn btn-outline-secondary move-section-up" data-s-index="' + sIndex + '">↑</button>' +
                        '<button type="button" class="btn btn-outline-secondary move-section-down" data-s-index="' + sIndex + '">↓</button>' +
                        '<button type="button" class="btn btn-outline-danger remove-section" data-s-index="' + sIndex + '">×</button>' +
                    '</div>' +
                '</div>' +
                '<div class="fields-list" data-s-index="' + sIndex + '"></div>';

            var fieldsList = wrap.querySelector('.fields-list');
            (section.fields || []).forEach(function (field, fIndex) {
                fieldsList.appendChild(buildFieldEditor(field, sIndex, fIndex));
            });

            sectionsEl.appendChild(wrap);
        });

        renderPreview();
        syncTemplateFromSelect();
        schemaInput.value = JSON.stringify(schema);
    }

    function buildFieldEditor(field, sIndex, fIndex) {
        var row = document.createElement('div');
        row.className = 'border rounded p-2 mb-2 bg-light';
        var optionsText = '';
        if (Array.isArray(field.options)) {
            optionsText = field.options.map(function (o) {
                return (o.value || '') + '|' + (o.label || '');
            }).join('\n');
        }

        row.innerHTML =
            '<div class="row g-2 align-items-end">' +
                '<div class="col-md-3">' +
                    '<label class="form-label small mb-0">ID</label>' +
                    '<input type="text" class="form-control form-control-sm field-id" data-s-index="' + sIndex + '" data-f-index="' + fIndex + '" value="' + escapeHtml(field.id || '') + '">' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<label class="form-label small mb-0">Label</label>' +
                    '<input type="text" class="form-control form-control-sm field-label" data-s-index="' + sIndex + '" data-f-index="' + fIndex + '" value="' + escapeHtml(field.label || '') + '">' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<label class="form-label small mb-0">Type</label>' +
                    '<select class="form-select form-select-sm field-type" data-s-index="' + sIndex + '" data-f-index="' + fIndex + '">' +
                        cfg.fieldTypes.map(function (t) {
                            return '<option value="' + t + '"' + (field.type === t ? ' selected' : '') + '>' + t + '</option>';
                        }).join('') +
                    '</select>' +
                '</div>' +
                '<div class="col-md-2">' +
                    '<label class="form-label small mb-0">Required</label>' +
                    '<div><input type="checkbox" class="form-check-input field-required" data-s-index="' + sIndex + '" data-f-index="' + fIndex + '"' + (field.required ? ' checked' : '') + '></div>' +
                '</div>' +
                '<div class="col-md-1 text-end">' +
                    '<button type="button" class="btn btn-sm btn-outline-danger remove-field" data-s-index="' + sIndex + '" data-f-index="' + fIndex + '">×</button>' +
                '</div>' +
                '<div class="col-12 field-options-wrap' + ((field.type === 'radio' || field.type === 'select') ? '' : ' d-none') + '">' +
                    '<label class="form-label small mb-0">Options (value|label per line)</label>' +
                    '<textarea class="form-control form-control-sm field-options" rows="2" data-s-index="' + sIndex + '" data-f-index="' + fIndex + '">' + escapeHtml(optionsText) + '</textarea>' +
                '</div>' +
                '<div class="col-md-6">' +
                    '<label class="form-label small mb-0">Placeholder</label>' +
                    '<input type="text" class="form-control form-control-sm field-placeholder" data-s-index="' + sIndex + '" data-f-index="' + fIndex + '" value="' + escapeHtml(field.placeholder || '') + '">' +
                '</div>' +
                '<div class="col-md-6 field-accept-wrap' + (field.type === 'file' ? '' : ' d-none') + '">' +
                    '<label class="form-label small mb-0">Accept</label>' +
                    '<input type="text" class="form-control form-control-sm field-accept" data-s-index="' + sIndex + '" data-f-index="' + fIndex + '" value="' + escapeHtml(field.accept || '') + '">' +
                '</div>' +
                '<div class="col-12">' +
                    '<div class="btn-group btn-group-sm">' +
                        '<button type="button" class="btn btn-outline-secondary move-field-up" data-s-index="' + sIndex + '" data-f-index="' + fIndex + '">Field ↑</button>' +
                        '<button type="button" class="btn btn-outline-secondary move-field-down" data-s-index="' + sIndex + '" data-f-index="' + fIndex + '">Field ↓</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        return row;
    }

    function renderPreview() {
        var html = '<h5 class="mb-3">' + escapeHtml(schema.title || 'Brief Form') + '</h5>';

        if (schema.description) {
            html += '<p class="text-muted small mb-3">' + escapeHtml(schema.description) + '</p>';
        }

        schema.sections.forEach(function (section) {
            if (section.title) {
                html += '<h6 class="mt-3">' + escapeHtml(section.title) + '</h6>';
            }
            if (section.help) {
                html += '<p class="text-muted small mb-2">' + escapeHtml(section.help) + '</p>';
            }
            (section.fields || []).forEach(function (field) {
                if (field.type === 'section') {
                    html += '<h6 class="mt-2">' + escapeHtml(field.label || '') + '</h6>';
                    return;
                }
                html += '<div class="mb-2">';
                html += '<label class="form-label small">' + escapeHtml(field.label || field.id || 'Field') + (field.required ? ' *' : '') + '</label>';
                if (field.help) {
                    html += '<div class="form-text small text-muted mb-1">' + escapeHtml(field.help) + '</div>';
                }
                if (field.type === 'textarea') {
                    html += '<textarea class="form-control form-control-sm" disabled rows="2"></textarea>';
                } else if (field.type === 'radio' && Array.isArray(field.options)) {
                    field.options.forEach(function (opt) {
                        html += '<div class="form-check form-check-inline"><input class="form-check-input" type="radio" disabled> <label class="form-check-label small">' + escapeHtml(opt.label || opt.value) + '</label></div>';
                    });
                } else if (field.type === 'select') {
                    html += '<select class="form-select form-select-sm" disabled><option>—</option></select>';
                } else if (field.type === 'file') {
                    html += '<input type="file" class="form-control form-control-sm" disabled>';
                } else {
                    html += '<input type="' + escapeHtml(field.type || 'text') + '" class="form-control form-control-sm" disabled placeholder="' + escapeHtml(field.placeholder || '') + '">';
                }
                html += '</div>';
            });
        });

        previewEl.innerHTML = html;
    }

    function syncFromDom() {
        normalizeSchema();
        var prevMeta = (schema.sections || []).map(function (section) {
            var fieldsById = {};

            (section.fields || []).forEach(function (field) {
                if (field.id) {
                    fieldsById[field.id] = field;
                }
            });

            return {
                help: section.help,
                fieldsById: fieldsById,
            };
        });

        document.querySelectorAll('.section-title-input').forEach(function (input) {
            var sIndex = parseInt(input.dataset.sIndex, 10);
            schema.sections[sIndex].title = input.value;
        });

        document.querySelectorAll('.fields-list').forEach(function (list) {
            var sIndex = parseInt(list.dataset.sIndex, 10);
            var prev = prevMeta[sIndex] || { fieldsById: {} };
            var fields = [];

            if (prev.help) {
                schema.sections[sIndex].help = prev.help;
            } else {
                delete schema.sections[sIndex].help;
            }

            list.querySelectorAll(':scope > .border').forEach(function (row) {
                var type = row.querySelector('.field-type').value;
                var fieldId = row.querySelector('.field-id').value.trim();
                var field = {
                    id: fieldId,
                    type: type,
                    label: row.querySelector('.field-label').value.trim(),
                    required: row.querySelector('.field-required').checked,
                };
                var placeholder = row.querySelector('.field-placeholder').value.trim();
                if (placeholder) field.placeholder = placeholder;
                if (type === 'radio' || type === 'select') {
                    var raw = row.querySelector('.field-options').value.trim();
                    field.options = raw.split('\n').map(function (line) {
                        var parts = line.split('|');
                        return { value: (parts[0] || '').trim(), label: (parts[1] || parts[0] || '').trim() };
                    }).filter(function (o) { return o.value !== ''; });
                }
                if (type === 'file') {
                    var accept = row.querySelector('.field-accept').value.trim();
                    if (accept) field.accept = accept;
                }

                var prevField = prev.fieldsById[fieldId];
                if (prevField) {
                    if (prevField.help) field.help = prevField.help;
                    if (prevField.multiple) field.multiple = prevField.multiple;
                }

                fields.push(field);
            });
            schema.sections[sIndex].fields = fields;
        });

        syncTemplateFromSelect();
        schemaInput.value = JSON.stringify(schema);
        renderPreview();
    }

    sectionsEl.addEventListener('input', syncFromDom);
    sectionsEl.addEventListener('change', function (e) {
        if (e.target.classList.contains('field-type')) {
            renderBuilder();
        } else {
            syncFromDom();
        }
    });

    sectionsEl.addEventListener('click', function (e) {
        syncFromDom();
        var sIndex = parseInt(e.target.dataset.sIndex, 10);
        var fIndex = parseInt(e.target.dataset.fIndex, 10);

        if (e.target.classList.contains('remove-section')) {
            schema.sections.splice(sIndex, 1);
            renderBuilder();
        } else if (e.target.classList.contains('move-section-up') && sIndex > 0) {
            var tmp = schema.sections[sIndex - 1];
            schema.sections[sIndex - 1] = schema.sections[sIndex];
            schema.sections[sIndex] = tmp;
            renderBuilder();
        } else if (e.target.classList.contains('move-section-down') && sIndex < schema.sections.length - 1) {
            var tmp2 = schema.sections[sIndex + 1];
            schema.sections[sIndex + 1] = schema.sections[sIndex];
            schema.sections[sIndex] = tmp2;
            renderBuilder();
        } else if (e.target.classList.contains('remove-field')) {
            schema.sections[sIndex].fields.splice(fIndex, 1);
            renderBuilder();
        } else if (e.target.classList.contains('move-field-up') && fIndex > 0) {
            var fields = schema.sections[sIndex].fields;
            var tmpF = fields[fIndex - 1];
            fields[fIndex - 1] = fields[fIndex];
            fields[fIndex] = tmpF;
            renderBuilder();
        } else if (e.target.classList.contains('move-field-down') && fIndex < schema.sections[sIndex].fields.length - 1) {
            var fields2 = schema.sections[sIndex].fields;
            var tmpF2 = fields2[fIndex + 1];
            fields2[fIndex + 1] = fields2[fIndex];
            fields2[fIndex] = tmpF2;
            renderBuilder();
        }
    });

    document.getElementById('add-section-btn').addEventListener('click', function () {
        syncFromDom();
        schema.sections.push({ id: uid('section'), title: 'New Section', fields: [] });
        renderBuilder();
    });

    document.getElementById('add-field-btn').addEventListener('click', function () {
        syncFromDom();
        var section = schema.sections[schema.sections.length - 1];
        section.fields.push({
            id: uid('field'),
            type: 'text',
            label: 'New Field',
            required: false,
        });
        renderBuilder();
    });

    if (titleEl) {
        titleEl.addEventListener('input', function () {
            schema.title = titleEl.value;
            renderPreview();
            schemaInput.value = JSON.stringify(schema);
        });
    }

    function schemaIsEmpty(s) {
        if (!s || !Array.isArray(s.sections) || s.sections.length === 0) {
            return true;
        }

        return s.sections.every(function (section) {
            return !section.fields || section.fields.length === 0;
        });
    }

    function resolveTemplateKey(s) {
        if (s && s.template && cfg.templates && cfg.templates[s.template]) {
            return s.template;
        }

        var byTitle = {
            'Website Brief Form': 'website',
            'Logo Brief': 'logo',
            'Ebook Brief': 'ebook',
            'Book Cover Design Brief': 'book_cover',
            'Brief Form': 'custom',
        };

        return byTitle[s && s.title] || 'custom';
    }

    function syncTemplateFromSelect() {
        if (templateSelect && templateSelect.value) {
            schema.template = templateSelect.value;
        }
    }

    function applyTemplate(key) {
        if (!cfg.templates || !cfg.templates[key]) {
            return;
        }

        schema = JSON.parse(JSON.stringify(cfg.templates[key]));
        schema.template = key;
        if (titleEl) {
            titleEl.value = schema.title || '';
        }
        if (templateSelect) {
            templateSelect.value = key;
        }
    }

    var activeTemplate = resolveTemplateKey(schema);

    if (schemaIsEmpty(schema) && !schema.template) {
        applyTemplate(cfg.defaultTemplate || 'custom');
    } else if (templateSelect) {
        templateSelect.value = activeTemplate;
        schema.template = activeTemplate;
    }

    if (templateSelect) {
        templateSelect.addEventListener('change', function () {
            var key = templateSelect.value;
            if (!key || !cfg.templates[key]) {
                return;
            }

            var label = templateSelect.options[templateSelect.selectedIndex].text;
            if (!confirm('Replace current schema with the ' + label + ' template?')) {
                templateSelect.value = resolveTemplateKey(schema);
                return;
            }

            applyTemplate(key);
            renderBuilder();
        });
    }

    document.getElementById('schema-save-form').addEventListener('submit', function () {
        syncFromDom();
    });

    renderBuilder();
})();
