@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $lead->name ?? '') }}" required>
    </div>

    <div class="col-md-6 mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $lead->email ?? '') }}">
    </div>

    <div class="col-md-6 mb-3">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" id="phone" name="phone" class="form-control" value="{{ old('phone', $lead->phone ?? '') }}">
    </div>

    <div class="col-md-6 mb-3">
        <label for="source" class="form-label">Source</label>
        <select id="source" name="source" class="form-select">
            <option value="">-- Select Source --</option>
            @foreach(\App\Models\Lead::SOURCES as $src)
                <option value="{{ $src }}" {{ old('source', $lead->source ?? '') === $src ? 'selected' : '' }}>
                    {{ ucwords(str_replace('_', ' ', $src)) }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6 mb-3">
        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
        <select id="status" name="status" class="form-select" required>
            @foreach(\App\Models\Lead::STATUSES as $s)
                <option value="{{ $s }}" {{ old('status', $lead->status ?? 'new') === $s ? 'selected' : '' }}>
                    {{ ucfirst($s) }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6 mb-3">
        <label for="assigned_to" class="form-label">Assigned To</label>
        <select id="assigned_to" name="assigned_to" class="form-select">
            <option value="">-- Unassigned --</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" {{ old('assigned_to', $lead->assigned_to ?? '') == $user->id ? 'selected' : '' }}>
                    {{ $user->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6 mb-3">
        <label for="team_id" class="form-label">Team</label>
        <select id="team_id" name="team_id" class="form-select">
            <option value="">-- No Team --</option>
            @foreach($teams as $team)
                <option value="{{ $team->id }}" {{ old('team_id', $lead->team_id ?? '') == $team->id ? 'selected' : '' }}>
                    {{ $team->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-12 mb-3">
        <label for="notes" class="form-label">Notes</label>
        <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $lead->notes ?? '') }}</textarea>
    </div>
</div>
