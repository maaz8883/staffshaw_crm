@csrf

<div class="mb-3">
    <label for="name" class="form-label">Company Name <span class="text-danger">*</span></label>
    <input type="text" id="name" name="name" class="form-control"
        value="{{ old('name', $company->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="logo" class="form-label">Logo / Image</label>

    @if(isset($company) && $company->logo)
        <div class="mb-2">
            <img src="{{ asset('storage/' . $company->logo) }}"
                 alt="Current Logo"
                 style="height:80px;width:80px;object-fit:cover;border-radius:8px;border:1px solid #dee2e6;">
            <div class="small text-muted mt-1">Current logo — upload a new one to replace it.</div>
        </div>
    @endif

    <input type="file" id="logo" name="logo" class="form-control" accept="image/*">
    <div class="form-text">Accepted: JPG, PNG, GIF, WEBP — max 2MB</div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Description</label>
    <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $company->description ?? '') }}</textarea>
</div>
