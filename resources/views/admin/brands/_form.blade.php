@csrf

@php
    $existingBriefForms = isset($brand) ? $brand->briefForms : collect();
    $oldBriefForms = old('brief_forms');
    $brandWebsite = old('website', $brand->website ?? '');

    if (is_array($oldBriefForms)) {
        $briefFormRows = collect($oldBriefForms)->values();
    } elseif ($existingBriefForms->isNotEmpty()) {
        $briefFormRows = $existingBriefForms->map(fn ($form) => [
            'id'            => $form->id,
            'name'          => $form->name,
            'form_path'     => '/brief-form',
            'document'      => $form->document,
            'document_name' => $form->document_name,
            'is_active'     => $form->is_active,
        ])->values();
    } else {
        $briefFormRows = collect([[
            'name'      => 'Brief Form',
            'form_path' => '/brief-form',
            'is_active' => true,
        ]]);
    }
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
        <button type="button" class="btn btn-sm btn-outline-primary" id="add-brief-form-btn">Add Brief Form</button>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0" id="brief-forms-table">
            <thead class="table-light">
                <tr>
                    <th style="min-width:160px">Name</th>
                    <th style="min-width:100px">Form ID</th>
                    <th style="min-width:220px">Document</th>
                    <th class="text-center" style="width:70px">Active</th>
                    @if(config('brief.builder_enabled'))
                    <th class="text-center" style="width:100px">Builder</th>
                    @endif
                    <th class="text-center" style="width:70px">Remove</th>
                </tr>
            </thead>
            <tbody id="brief-forms-body">
                @foreach($briefFormRows as $index => $row)
                    @include('admin.brands._brief_form_row', [
                        'index' => $index,
                        'row' => $row,
                        'brand' => $brand ?? null,
                        'brandWebsite' => $brandWebsite,
                    ])
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="form-text">
        Customer link format: <code>{website}/brief-form?sale_id=&#123;sale_id&#125;&amp;form_id=&#123;form_id&#125;</code>.
        Each brief form has its own builder and schema. Save the brand to get a Form ID for new rows.
    </div>
</div>

<template id="brief-form-row-template">
    @include('admin.brands._brief_form_row', [
        'index' => '__INDEX__',
        'row' => ['name' => 'Brief Form', 'form_path' => '/brief-form', 'is_active' => true],
        'brand' => $brand ?? null,
        'brandWebsite' => $brandWebsite,
        'isTemplate' => true,
    ])
</template>
