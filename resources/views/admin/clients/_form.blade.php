@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Client Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control"
            value="{{ old('name', $client->name ?? '') }}" required>
    </div>

    <div class="col-md-6 mb-3">
        <label for="company_name" class="form-label">Company</label>
        <input type="text" id="company_name" name="company_name" class="form-control"
            value="{{ old('company_name', $client->company_name ?? '') }}">
    </div>

    <div class="col-md-6 mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" class="form-control"
            value="{{ old('email', $client->email ?? '') }}">
    </div>

    <div class="col-md-6 mb-3">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" id="phone" name="phone" class="form-control"
            value="{{ old('phone', $client->phone ?? '') }}">
    </div>

    <div class="col-12 mb-3">
        <label for="address" class="form-label">Address</label>
        <textarea id="address" name="address" class="form-control" rows="2">{{ old('address', $client->address ?? '') }}</textarea>
    </div>

    <div class="col-12 mb-3">
        <label for="notes" class="form-label">Notes</label>
        <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $client->notes ?? '') }}</textarea>
    </div>
</div>
