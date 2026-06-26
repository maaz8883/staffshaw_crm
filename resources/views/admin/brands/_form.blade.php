@csrf

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

    <div class="col-md-6 mb-3">
        <label for="brief_document" class="form-label">Brief Document</label>

        @if(isset($brand) && $brand->brief_document)
            <div class="mb-2">
                <a href="{{ asset('storage/' . $brand->brief_document) }}" target="_blank" rel="noopener">
                    {{ $brand->brief_document_name ?? basename($brand->brief_document) }}
                </a>
                <div class="small text-muted mt-1">Current document — upload a new one to replace it.</div>
            </div>
        @endif

        <input type="file" id="brief_document" name="brief_document" class="form-control"
            accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
        <div class="form-text">Accepted: PDF, DOC, DOCX — max 10MB</div>
    </div>
</div>
