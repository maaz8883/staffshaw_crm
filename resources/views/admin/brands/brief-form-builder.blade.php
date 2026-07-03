@extends('admin.layout')

@section('title', 'Form Builder — ' . $form->name)
@section('page-title', 'Brief Form Builder')
@section('page-icon', 'ui-checks')

@section('content')
<div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
    <a href="{{ route('admin.brands.edit', $brand) }}" class="btn btn-outline-secondary btn-sm">Back to Brand</a>
    <span class="text-muted">{{ $brand->name }} · <strong>{{ $form->name }}</strong> · <code>{{ $form->form_path }}</code></span>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Fields</span>
                <div class="d-flex gap-2 flex-wrap">
                    <select id="template-select" class="form-select form-select-sm" style="width:auto">
                        <option value="website">Website Brief Form</option>
                        <option value="custom">Custom Brief Form</option>
                        <option value="logo">Logo Brief</option>
                        <option value="ebook">Ebook Brief</option>
                        <option value="book_cover">Book Cover Design Brief</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-section-btn">Add Section</button>
                    <button type="button" class="btn btn-sm btn-primary" id="add-field-btn">Add Field</button>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Form Title</label>
                    <input type="text" id="schema-title" class="form-control" value="{{ $form->schema['title'] ?? $form->name }}">
                </div>
                <div id="builder-sections"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Live Preview</div>
            <div class="card-body" id="builder-preview"></div>
        </div>

        <form method="POST" action="{{ route('admin.brands.brief-forms.builder.update', [$brand, $form]) }}" id="schema-save-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="schema" id="schema-json-input">
            <button type="submit" class="btn btn-success w-100">Save Form Schema</button>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
window.BRIEF_FORM_BUILDER = {
    initialSchema: @json($form->schema ?? ['version' => 1, 'title' => $form->name, 'sections' => []]),
    templates: @json($templates),
    defaultTemplate: 'website',
    fieldTypes: @json(\App\Services\BriefFormSchemaService::FIELD_TYPES),
};
</script>
<script src="{{ asset('js/brief-form-builder.js') }}"></script>
@endsection
