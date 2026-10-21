@csrf

<div class="mb-3">
    <label for="name" class="form-label">Name</label>
    <input type="text" id="name" name="name" class="form-control"
        value="{{ old('name', $user->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="email" class="form-label">Email</label>
    <input type="email" id="email" name="email" class="form-control"
        value="{{ old('email', $user->email ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="role_id" class="form-label">Role</label>
    <select id="role_id" name="role_id" class="form-select">
        <option value="">Select role</option>
        @foreach($roles as $role)
            <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id ?? '') === (string) $role->id)>
                {{ $role->name }}
            </option>
        @endforeach
    </select>
</div>

@unless($singleCompany)
<div class="mb-3">
    <label for="company_id" class="form-label">Company / Branch</label>
    <select id="company_id" name="company_id" class="form-select">
        <option value="">-- Select Company --</option>
        @foreach($companies as $company)
            <option value="{{ $company->id }}" @selected((string) old('company_id', $user->company_id ?? '') === (string) $company->id)>
                {{ $company->name }}
            </option>
        @endforeach
    </select>
</div>
@endunless

<div class="mb-3">
    <label for="team_id" class="form-label">Team</label>
    <select id="team_id" name="team_id" class="form-select js-select2">
        <option value="">-- Select Team --</option>
        @if($singleCompany)
            @foreach($teams as $team)
                <option value="{{ $team->id }}" @selected((string) old('team_id', $user->team_id ?? '') === (string) $team->id)>{{ $team->name }}</option>
            @endforeach
        @elseif(isset($user) && $user->team)
            <option value="{{ $user->team->id }}" selected>{{ $user->team->name }}</option>
        @endif
    </select>
    @unless($singleCompany)
    <small class="text-muted">Select a company first to load its teams.</small>
    @endunless
</div>

<div class="mb-3" id="sub-team-head-wrap" style="display: none;">
    <label for="sub_team_head_id" class="form-label">Sub-Team Head (Optional)</label>
    <select id="sub_team_head_id" name="sub_team_head_id" class="form-select">
        <option value="">-- No Sub-Team Head --</option>
        {{-- Populated via AJAX based on team --}}
    </select>
    <small class="text-muted">Select a team first to load its sub-team heads.</small>
</div>

<div class="mb-3">
    <label for="password" class="form-label">Password</label>
    <input type="password" id="password" name="password" class="form-control"
        @if(!isset($user)) required @endif>
</div>

<div class="mb-3">
    <label for="password_confirmation" class="form-label">Confirm Password</label>
    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control"
        @if(!isset($user)) required @endif>
</div>

<script>
    (function () {
        var roleSelect       = document.getElementById('role_id');
        var companySelect    = document.getElementById('company_id');
        var teamSelect       = document.getElementById('team_id');
        var subTeamHeadSelect = document.getElementById('sub_team_head_id');
        var companyWrap      = companySelect ? companySelect.closest('.mb-3') : null;
        var teamWrap         = teamSelect.closest('.mb-3');
        var subTeamHeadWrap  = document.getElementById('sub-team-head-wrap');
        var currentTeamId    = '{{ old('team_id', $user->team_id ?? '') }}';
        var currentSubTeamHeadId = '{{ old('sub_team_head_id', $user->sub_team_head_id ?? '') }}';

        // Role IDs
        var ppcRoleIds   = @json($roles->where('name', 'PPC')->pluck('id')->values());
        var agentRoleIds = @json($roles->where('name', 'Agent')->pluck('id')->values());

        function isPpc() {
            return ppcRoleIds.includes(parseInt(roleSelect.value));
        }

        function isAgent() {
            return agentRoleIds.includes(parseInt(roleSelect.value));
        }

        function toggleCompanyTeam() {
            var hideCompany = isPpc();
            if (companyWrap) {
                companyWrap.style.display = hideCompany ? 'none' : '';
            }
            teamWrap.style.display = '';
            if (hideCompany && companySelect) {
                companySelect.value = '';
            }
            toggleSubTeamHead();
        }

        function toggleSubTeamHead() {
            // Show sub-team head dropdown only for Agent role
            if (isAgent() && teamSelect.value) {
                loadSubTeamHeads(teamSelect.value, currentSubTeamHeadId);
            } else {
                subTeamHeadWrap.style.display = 'none';
                subTeamHeadSelect.innerHTML = '<option value="">-- No Sub-Team Head --</option>';
            }
        }

        // Team box is a Select2: redraw it after its options change
        function refreshTeamSelect() {
            if (window.jQuery) window.jQuery(teamSelect).trigger('change.select2');
        }

        function loadTeams(companyId, preselectId) {
            teamSelect.innerHTML = '<option value="">-- Select Team --</option>';
            refreshTeamSelect();
            subTeamHeadSelect.innerHTML = '<option value="">-- No Sub-Team Head --</option>';
            subTeamHeadWrap.style.display = 'none';
            if (!companyId) return;

            fetch('/admin/companies/' + companyId + '/teams')
                .then(function (r) { return r.json(); })
                .then(function (teams) {
                    teams.forEach(function (t) {
                        var opt = document.createElement('option');
                        opt.value = t.id;
                        opt.textContent = t.name;
                        if (String(t.id) === String(preselectId)) opt.selected = true;
                        teamSelect.appendChild(opt);
                    });
                    refreshTeamSelect();
                    if (preselectId && isAgent()) {
                        loadSubTeamHeads(preselectId, currentSubTeamHeadId);
                    }
                });
        }

        function loadSubTeamHeads(teamId, preselectId) {
            subTeamHeadSelect.innerHTML = '<option value="">-- No Sub-Team Head --</option>';
            if (!teamId || !isAgent()) {
                subTeamHeadWrap.style.display = 'none';
                return;
            }

            fetch('/admin/teams/' + teamId + '/sub-team-heads')
                .then(function (r) { return r.json(); })
                .then(function (subTeamHeads) {
                    if (subTeamHeads.length > 0) {
                        subTeamHeadWrap.style.display = '';
                        subTeamHeads.forEach(function (sth) {
                            var opt = document.createElement('option');
                            opt.value = sth.id;
                            opt.textContent = sth.title + ' - ' + sth.user_name;
                            if (String(sth.id) === String(preselectId)) opt.selected = true;
                            subTeamHeadSelect.appendChild(opt);
                        });
                    } else {
                        subTeamHeadWrap.style.display = 'none';
                    }
                });
        }

        roleSelect.addEventListener('change', function() {
            toggleCompanyTeam();
            toggleSubTeamHead();
        });

        if (companySelect) {
            companySelect.addEventListener('change', function () {
                loadTeams(this.value, '');
            });
        }

        teamSelect.addEventListener('change', function () {
            toggleSubTeamHead();
        });

        // On page load
        toggleCompanyTeam();
        if (companySelect && companySelect.value) {
            loadTeams(companySelect.value, currentTeamId);
        }
    })();
</script>
