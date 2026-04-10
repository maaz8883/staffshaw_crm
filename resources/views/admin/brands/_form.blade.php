@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Brand Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $brand->name ?? '') }}" required>
    </div>

    <div class="col-md-6 mb-3">
        <label for="industry" class="form-label">Industry</label>
        <input type="text" id="industry" name="industry" class="form-control" value="{{ old('industry', $brand->industry ?? '') }}">
    </div>

    <div class="col-md-6 mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $brand->email ?? '') }}">
    </div>

    <div class="col-md-6 mb-3">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" id="phone" name="phone" class="form-control" value="{{ old('phone', $brand->phone ?? '') }}">
    </div>

    <div class="col-md-6 mb-3">
        <label for="website" class="form-label">Website</label>
        <input type="url" id="website" name="website" class="form-control" placeholder="https://" value="{{ old('website', $brand->website ?? '') }}">
    </div>

    <div class="col-md-6 mb-3">
        <label for="assigned_user_id" class="form-label">Assigned To</label>
        <select id="assigned_user_id" name="assigned_user_id" class="form-select">
            <option value="">-- Unassigned --</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" {{ old('assigned_user_id', $brand->assigned_user_id ?? '') == $user->id ? 'selected' : '' }}>
                    {{ $user->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6 mb-3">
        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
        <select id="status" name="status" class="form-select" required>
            <option value="active" {{ old('status', $brand->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ old('status', $brand->status ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>

    <div class="col-12 mb-3">
        <label for="address" class="form-label">Address</label>
        <textarea id="address" name="address" class="form-control" rows="2">{{ old('address', $brand->address ?? '') }}</textarea>
    </div>

    <div class="col-12 mb-3">
        <label for="notes" class="form-label">Notes</label>
        <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $brand->notes ?? '') }}</textarea>
    </div>
</div>
