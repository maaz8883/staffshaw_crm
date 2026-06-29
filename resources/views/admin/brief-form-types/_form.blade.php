@csrf

<div class="mb-3">
    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
    <input type="text" id="name" name="name" class="form-control"
        value="{{ old('name', $briefFormType->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="slug" class="form-label">Slug</label>
    <input type="text" id="slug" name="slug" class="form-control"
        value="{{ old('slug', $briefFormType->slug ?? '') }}"
        pattern="[a-z0-9_-]+" placeholder="website">
    <div class="form-text">Lowercase identifier used for submission matching (e.g. website, logo). Auto-generated from name if left empty on create.</div>
</div>

<div class="mb-3">
    <label for="default_form_path" class="form-label">Default Form Path <span class="text-danger">*</span></label>
    <input type="text" id="default_form_path" name="default_form_path" class="form-control"
        value="{{ old('default_form_path', $briefFormType->default_form_path ?? '') }}"
        placeholder="/website-brief" required>
    <div class="form-text">Path on the brand website (must start with /).</div>
</div>

<div class="mb-3">
    <label for="sort_order" class="form-label">Sort Order</label>
    <input type="number" id="sort_order" name="sort_order" class="form-control" min="0"
        value="{{ old('sort_order', $briefFormType->sort_order ?? 0) }}">
</div>

<div class="mb-3 form-check">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input"
        @checked(old('is_active', $briefFormType->is_active ?? true))>
    <label for="is_active" class="form-check-label">Active (show in Brand brief form type dropdown)</label>
</div>
