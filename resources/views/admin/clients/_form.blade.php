@csrf
@php
    $contact = $client?->primaryContact;
    $showTeam = isset($teams) && $teams->isNotEmpty();
@endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Client Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $client->name ?? '') }}" required>
        <div class="form-text">Main name shown on sales and invoices.</div>
    </div>
    <div class="col-md-6 mb-3">
        <label for="company_name" class="form-label">Company (optional)</label>
        <input type="text" id="company_name" name="company_name" class="form-control" value="{{ old('company_name', $client->company_name ?? '') }}">
        <div class="form-text">Business name if different from client name.</div>
    </div>

    <div class="col-md-4 mb-3">
        <label for="brand_id" class="form-label">Brand</label>
        <select id="brand_id" name="brand_id" class="form-select js-select2">
            <option value="">No Brand</option>
            @foreach(($brands ?? []) as $brand)
                <option value="{{ $brand->id }}" {{ (string) old('brand_id', $client->brand_id ?? '') === (string) $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
            @endforeach
        </select>
    </div>
    @if($showTeam)
    <div class="col-md-4 mb-3">
        <label for="team_id" class="form-label">Team <span class="text-danger">*</span></label>
        <select id="team_id" name="team_id" class="form-select" required>
            <option value="">-- Select Team --</option>
            @foreach($teams as $team)
                <option value="{{ $team->id }}" {{ (string) old('team_id', $client->team_id ?? '') === (string) $team->id ? 'selected' : '' }}>{{ $team->name }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <div class="col-md-4 mb-3">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" id="phone" name="phone" class="form-control" value="{{ old('phone', $client->phone ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $client->email ?? '') }}">
    </div>

    <div class="col-12 mb-2"><h6 class="mt-2 mb-0">Primary Contact</h6></div>
    <div class="col-md-4 mb-3">
        <label class="form-label">First Name</label>
        <input type="text" name="contact_first_name" class="form-control" value="{{ old('contact_first_name', $contact->first_name ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Last Name</label>
        <input type="text" name="contact_last_name" class="form-control" value="{{ old('contact_last_name', $contact->last_name ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Contact Phone</label>
        <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone', $contact->phone ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Contact Email</label>
        <input type="email" name="contact_email" class="form-control" value="{{ old('contact_email', $contact->email ?? '') }}">
    </div>
    <div class="col-md-6 mb-3 d-flex align-items-end">
        <div class="form-check mb-2">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ (string) old('is_active', ($client->is_active ?? true) ? '1' : '0') === '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active client</label>
        </div>
    </div>

    <div class="col-12 mb-3">
        <label for="notes" class="form-label">Notes</label>
        <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $client->notes ?? '') }}</textarea>
    </div>
</div>
