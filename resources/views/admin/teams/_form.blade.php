@csrf

<div class="mb-3">
    <label for="company_id" class="form-label">Company / Branch</label>
    <select id="company_id" name="company_id" class="form-select">
        <option value="">-- No Company --</option>
        @foreach($companies as $company)
            <option value="{{ $company->id }}" {{ old('company_id', $team->company_id ?? '') == $company->id ? 'selected' : '' }}>
                {{ $company->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="name" class="form-label">Team Name <span class="text-danger">*</span></label>
    <input type="text" id="name" name="name" class="form-control"
        value="{{ old('name', $team->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="team_head_id" class="form-label">Team Head</label>
    <select id="team_head_id" name="team_head_id" class="form-select">
        <option value="">-- No Team Head --</option>
        @foreach($users as $user)
            <option value="{{ $user->id }}" {{ old('team_head_id', $team->team_head_id ?? '') == $user->id ? 'selected' : '' }}>
                {{ $user->name }}
                @if($user->company)
                    ({{ $user->company->name }})
                @endif
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Description</label>
    <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $team->description ?? '') }}</textarea>
</div>
