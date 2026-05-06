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
        @foreach($allUsers as $user)
            <option value="{{ $user->id }}" {{ old('team_head_id', $team->team_head_id ?? '') == $user->id ? 'selected' : '' }}>
                {{ $user->name }}
                @if($user->company)
                    ({{ $user->company->name }})
                @endif
            </option>
        @endforeach
    </select>
</div>

<div class="mb-4">
    <label class="form-label">Sub-Team Heads (Optional)</label>
    @if(isset($team) && $team->id && $teamUsers->isEmpty())
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No users assigned to this team yet. Add users to the team first to assign sub-team heads.
        </div>
    @endif
    <div id="sub-team-heads-container">
        @if(isset($team) && $team->subTeamHeads && $team->subTeamHeads->count() > 0)
            @foreach($team->subTeamHeads as $index => $subHead)
                <div class="sub-team-head-row mb-3 border rounded p-3 bg-light" data-index="{{ $index }}">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" name="sub_heads[{{ $index }}][title]" class="form-control" placeholder="Title (e.g., Front Head)" value="{{ old('sub_heads.'.$index.'.title', $subHead->title) }}" required>
                        </div>
                        <div class="col-md-6">
                            <select name="sub_heads[{{ $index }}][user_id]" class="form-select team-user-select" required>
                                <option value="">-- Select User --</option>
                                @foreach((isset($team) && $team->id ? $teamUsers : $allUsers) as $user)
                                    <option value="{{ $user->id }}" {{ old('sub_heads.'.$index.'.user_id', $subHead->user_id) == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-sub-head">Remove</button>
                        </div>
                    </div>
                    @php
                        $usersUnderSubHead = $teamUsers->where('sub_team_head_id', $subHead->id);
                    @endphp
                    @if($usersUnderSubHead->count() > 0)
                        <div class="mt-3 ps-4 border-start border-3 border-primary">
                            <div class="small text-muted mb-2"><i class="bi bi-people-fill"></i> Users under this Sub-Team Head:</div>
                            <ul class="list-unstyled mb-0">
                                @foreach($usersUnderSubHead as $user)
                                    <li class="mb-1">
                                        <i class="bi bi-arrow-return-right text-primary"></i>
                                        <strong>{{ $user->name }}</strong>
                                        @if($user->email)
                                            <span class="text-muted small">({{ $user->email }})</span>
                                        @endif
                                        @if($user->role)
                                            <span class="badge bg-secondary">{{ $user->role->name }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
    <button type="button" id="add-sub-head" class="btn btn-sm btn-outline-primary mt-2" {{ (isset($team) && $team->id && $teamUsers->isEmpty()) ? 'disabled' : '' }}>
        <i class="bi bi-plus-circle"></i> Add Sub-Team Head
    </button>
    <div class="form-text">Add multiple sub-team heads (e.g., Front Head, Upsell Head, etc.){{ isset($team) && $team->id ? ' - Only users from this team are shown' : '' }}</div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Description</label>
    <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $team->description ?? '') }}</textarea>
</div>

<script>
(function() {
    let subHeadIndex = {{ isset($team) && $team->subTeamHeads ? $team->subTeamHeads->count() : 0 }};
    const container = document.getElementById('sub-team-heads-container');
    const addBtn = document.getElementById('add-sub-head');
    const teamHeadSelect = document.getElementById('team_head_id');
    
    // Use team users for edit, all users for create
    const isEdit = {{ isset($team) && $team->id ? 'true' : 'false' }};
    const allTeamUsers = @json((isset($team) && $team->id ? $teamUsers : $allUsers)->map(fn($u) => ['id' => $u->id, 'name' => $u->name]));

    function getSelectedSubHeadUsers() {
        const selectedUsers = [];
        const subHeadSelects = container.querySelectorAll('.team-user-select');
        subHeadSelects.forEach(select => {
            if (select.value) {
                selectedUsers.push(select.value);
            }
        });
        return selectedUsers;
    }

    function getAvailableUsers(currentSelectValue = null) {
        const teamHeadId = teamHeadSelect.value;
        const selectedSubHeadUsers = getSelectedSubHeadUsers();
        
        return allTeamUsers.filter(user => {
            // Exclude team head
            if (String(user.id) === String(teamHeadId)) return false;
            
            // Include if this is the current select's value
            if (currentSelectValue && String(user.id) === String(currentSelectValue)) return true;
            
            // Exclude if already selected in another sub-team head
            if (selectedSubHeadUsers.includes(String(user.id))) return false;
            
            return true;
        });
    }

    function createSubHeadRow() {
        const row = document.createElement('div');
        row.className = 'sub-team-head-row mb-3 border rounded p-3 bg-light';
        row.dataset.index = subHeadIndex;
        
        const availableUsers = getAvailableUsers();
        let userOptions = '<option value="">-- Select User --</option>';
        availableUsers.forEach(user => {
            userOptions += `<option value="${user.id}">${user.name}</option>`;
        });

        row.innerHTML = `
            <div class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="sub_heads[${subHeadIndex}][title]" class="form-control" placeholder="Title (e.g., Front Head)" required>
                </div>
                <div class="col-md-6">
                    <select name="sub_heads[${subHeadIndex}][user_id]" class="form-select team-user-select" required>
                        ${userOptions}
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-sub-head">Remove</button>
                </div>
            </div>
        `;
        
        subHeadIndex++;
        return row;
    }

    function updateAllSubHeadDropdowns() {
        const subHeadSelects = container.querySelectorAll('.team-user-select');
        
        subHeadSelects.forEach(select => {
            const currentValue = select.value;
            const availableUsers = getAvailableUsers(currentValue);
            let userOptions = '<option value="">-- Select User --</option>';
            
            availableUsers.forEach(user => {
                const selected = String(user.id) === String(currentValue) ? 'selected' : '';
                userOptions += `<option value="${user.id}" ${selected}>${user.name}</option>`;
            });
            
            select.innerHTML = userOptions;
        });
    }

    addBtn.addEventListener('click', function() {
        if (isEdit && allTeamUsers.length === 0) {
            alert('Please add users to this team first before assigning sub-team heads.');
            return;
        }
        const row = createSubHeadRow();
        container.appendChild(row);
    });

    // Update sub-team head dropdowns when team head changes
    teamHeadSelect.addEventListener('change', function() {
        updateAllSubHeadDropdowns();
    });

    // Update dropdowns when any sub-team head user is selected
    container.addEventListener('change', function(e) {
        if (e.target.classList.contains('team-user-select')) {
            updateAllSubHeadDropdowns();
        }
    });

    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-sub-head') || e.target.closest('.remove-sub-head')) {
            const row = e.target.closest('.sub-team-head-row');
            if (row) {
                row.remove();
                updateAllSubHeadDropdowns();
            }
        }
    });

    // Initial update for existing sub-team heads
    updateAllSubHeadDropdowns();
})();
</script>
