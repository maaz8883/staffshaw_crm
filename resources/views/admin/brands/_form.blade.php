@csrf

@php
    $briefFormTypes = $briefFormTypes ?? collect();
    $existingBriefForms = isset($brand) ? $brand->briefForms : collect();
    $oldBriefForms = old('brief_forms');

    if (is_array($oldBriefForms)) {
        $briefFormRows = collect($oldBriefForms)->values();
    } elseif ($existingBriefForms->isNotEmpty()) {
        $briefFormRows = $existingBriefForms->map(fn ($form) => [
            'id'                 => $form->id,
            'brief_form_type_id' => $form->brief_form_type_id,
            'name'               => $form->name,
            'form_path'          => $form->form_path,
            'document'           => $form->document,
            'document_name'      => $form->document_name,
            'is_active'          => $form->is_active,
        ])->values();
    } else {
        $briefFormRows = collect();
    }

    $typeOptionsJson = $briefFormTypes->map(fn ($type) => [
        'id'                => $type->id,
        'name'              => $type->name,
        'default_form_path' => $type->default_form_path,
    ])->values()->toJson();
@endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Brand Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control"
            value="{{ old('name', $brand->name ?? '') }}" required>
    </div>

    <div class="col-md-6 mb-3">
        <label for="website" class="form-label">URL <span class="text-danger">*</span></label>
        <input type="url" id="website" name="website" class="form-control" placeholder="https://"
            value="{{ old('website', $brand->website ?? '') }}" required>
    </div>

    <div class="col-md-6 mb-3">
        <label for="image" class="form-label">Image <span class="text-danger">*</span></label>

        @if(isset($brand) && $brand->image)
            <div class="mb-2">
                <img src="{{ asset('storage/' . $brand->image) }}"
                     alt="{{ $brand->name }}"
                     style="height:80px;width:80px;object-fit:cover;border-radius:8px;border:1px solid #dee2e6;">
                <div class="small text-muted mt-1">Current image — leave the field below empty to keep it.</div>
            </div>
        @endif

        <input type="file" id="image" name="image" class="form-control" accept="image/*"
            @if(!isset($brand) || !($brand->image ?? null)) required @endif>
        <div class="form-text">
            @if(isset($brand) && $brand->image)
                Upload only if you want to replace the current image. Accepted: JPG, PNG, GIF, WEBP — max 2MB
            @else
                Accepted: JPG, PNG, GIF, WEBP — max 2MB
            @endif
        </div>
    </div>
</div>

<div class="mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="form-label mb-0">Brief Forms</label>
        <button type="button" class="btn btn-sm btn-outline-primary" id="add-brief-form-row">Add Brief Form</button>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0" id="brief-forms-table">
            <thead class="table-light">
                <tr>
                    <th style="min-width:140px">Type</th>
                    <th style="min-width:140px">Name</th>
                    <th style="min-width:160px">Form Path</th>
                    <th style="min-width:200px">Document</th>
                    <th class="text-center" style="width:70px">Active</th>
                    <th class="text-center" style="width:80px">Remove</th>
                </tr>
            </thead>
            <tbody id="brief-forms-body">
                @foreach($briefFormRows as $index => $row)
                    @include('admin.brands._brief_form_row', [
                        'index' => $index,
                        'row' => $row,
                        'briefFormTypes' => $briefFormTypes,
                    ])
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="form-text">Each brief form gets its own link path (e.g. /brief-form, /logo-brief). Document is optional.</div>
</div>

<template id="brief-form-row-template">
    @include('admin.brands._brief_form_row', [
        'index' => '__INDEX__',
        'row' => [],
        'briefFormTypes' => $briefFormTypes,
    ])
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const body = document.getElementById('brief-forms-body');
    const addBtn = document.getElementById('add-brief-form-row');
    const template = document.getElementById('brief-form-row-template');
    const typeOptions = {!! $typeOptionsJson !!};

    function reindexRows() {
        body.querySelectorAll('tr.brief-form-row').forEach(function (row, index) {
            row.dataset.index = index;
            row.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/brief_forms\[\d+\]/, 'brief_forms[' + index + ']');
            });
        });
    }

    function applyTypeDefaults(row) {
        const typeSelect = row.querySelector('.brief-form-type');
        const nameInput = row.querySelector('.brief-form-name');
        const pathInput = row.querySelector('.brief-form-path');

        if (!typeSelect || !nameInput || !pathInput) {
            return;
        }

        typeSelect.addEventListener('change', function () {
            const selected = typeOptions.find(function (t) {
                return String(t.id) === String(typeSelect.value);
            });

            if (!selected) {
                return;
            }

            if (!nameInput.dataset.userEdited || nameInput.value.trim() === '') {
                nameInput.value = selected.name;
            }

            if (!pathInput.dataset.userEdited || pathInput.value.trim() === '') {
                pathInput.value = selected.default_form_path;
            }
        });

        nameInput.addEventListener('input', function () {
            nameInput.dataset.userEdited = '1';
        });

        pathInput.addEventListener('input', function () {
            pathInput.dataset.userEdited = '1';
        });
    }

    body.querySelectorAll('tr.brief-form-row').forEach(applyTypeDefaults);

    addBtn.addEventListener('click', function () {
        const index = body.querySelectorAll('tr.brief-form-row').length;
        const html = template.innerHTML.replace(/__INDEX__/g, index);
        body.insertAdjacentHTML('beforeend', html);
        const row = body.lastElementChild;
        applyTypeDefaults(row);
    });

    body.addEventListener('click', function (event) {
        const btn = event.target.closest('.remove-brief-form-row');

        if (!btn) {
            return;
        }

        const row = btn.closest('tr.brief-form-row');
        const idInput = row.querySelector('.brief-form-id');

        if (idInput && idInput.value !== '') {
            row.classList.add('d-none');
            row.querySelector('input[name*="[_delete]"]').value = '1';
        } else {
            row.remove();
            reindexRows();
        }
    });
});
</script>
