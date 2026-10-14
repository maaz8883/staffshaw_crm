@csrf
@php $isAdminOrManager = auth()->user()->hasRole(['Admin', 'Manager']); @endphp

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
        <select id="status" name="status" class="form-select" required>
            @foreach(\App\Models\Lead::STATUS_LABELS as $s => $label)
                <option value="{{ $s }}" {{ old('status', $lead->status ?? 'new_lead') === $s ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label for="source" class="form-label">Source</label>
        <select id="source" name="source" class="form-select">
            <option value="">-- Select Source --</option>
            @foreach(\App\Models\Lead::SOURCES as $src)
                <option value="{{ $src }}" {{ old('source', $lead->source ?? '') === $src ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $src)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label for="assigned_to" class="form-label">Assigned</label>
        <select id="assigned_to" name="assigned_to" class="form-select">
            <option value="">-- Unassigned --</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" {{ (string) old('assigned_to', $lead->assigned_to ?? auth()->id()) === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $lead->name ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label for="company" class="form-label">Company</label>
        <input type="text" id="company" name="company" class="form-control" value="{{ old('company', $lead->company ?? '') }}">
    </div>

    <div class="col-md-4 mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $lead->email ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" id="phone" name="phone" class="form-control" value="{{ old('phone', $lead->phone ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label for="lead_value" class="form-label">Lead Value ($)</label>
        <input type="number" step="0.01" min="0" id="lead_value" name="lead_value" class="form-control" value="{{ old('lead_value', $lead->lead_value ?? 0) }}">
    </div>

    <div class="col-md-4 mb-3">
        <label for="brand_id" class="form-label">Brand</label>
        <select id="brand_id" name="brand_id" class="form-select">
            <option value="">No Brand</option>
            @foreach(($brands ?? []) as $brand)
                <option value="{{ $brand->id }}" {{ (string) old('brand_id', $lead->brand_id ?? '') === (string) $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
            @endforeach
        </select>
    </div>
    @if($isAdminOrManager)
    <div class="col-md-4 mb-3">
        <label for="team_id" class="form-label">Team</label>
        <select id="team_id" name="team_id" class="form-select">
            <option value="">-- No Team --</option>
            @foreach($teams as $team)
                <option value="{{ $team->id }}" {{ (string) old('team_id', $lead->team_id ?? '') === (string) $team->id ? 'selected' : '' }}>{{ $team->name }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <div class="col-md-4 mb-3">
        <label for="follow_up_at" class="form-label">Next Follow-up</label>
        <input type="date" id="follow_up_at" name="follow_up_at" class="form-control"
            value="{{ old('follow_up_at', isset($lead) && $lead->follow_up_at ? $lead->follow_up_at->toDateString() : '') }}">
        <div class="form-text">Next call / WhatsApp reminder date.</div>
    </div>

    <div class="col-12 mb-3">
        <label for="notes" class="form-label">Description</label>
        <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="What does the client need? What was discussed?">{{ old('notes', $lead->notes ?? '') }}</textarea>
    </div>
</div>
