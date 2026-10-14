@php
    /** @var string $action */
    $showCompany = $showCompany ?? true;
    $showTeam = $showTeam ?? true;
    $showAgent = $showAgent ?? true;
@endphp
<form method="get" action="{{ $action }}" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-auto">
                <label class="form-label small text-muted mb-0">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="{{ $filters['date_from'] ?? $dateFrom->toDateString() }}">
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small text-muted mb-0">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="{{ $filters['date_to'] ?? $dateTo->toDateString() }}">
            </div>
            @if($showCompany && !($singleCompany ?? false) && !($isAgent ?? false) && ($companies ?? collect())->isNotEmpty())
            <div class="col-12 col-md-auto">
                <label class="form-label small text-muted mb-0">Company</label>
                <select name="company_id" class="form-select form-select-sm" style="min-width: 160px">
                    <option value="">All companies</option>
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}" @selected(($filters['company_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            @if($showTeam)
            <div class="col-12 col-md-auto">
                <label class="form-label small text-muted mb-0">Team</label>
                <select name="team_id" class="form-select form-select-sm" style="min-width: 140px">
                    <option value="">All teams</option>
                    @foreach($teams ?? [] as $t)
                        <option value="{{ $t->id }}" @selected(($filters['team_id'] ?? '') == $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            @if($showAgent && !($isAgent ?? false) && ($users ?? collect())->isNotEmpty())
            <div class="col-12 col-md-auto">
                <label class="form-label small text-muted mb-0">Agent</label>
                <select name="user_id" class="form-select form-select-sm" style="min-width: 140px">
                    <option value="">All agents</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <a href="{{ $action }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
        <div class="text-muted small mt-2 mb-0">
            Period: <strong>{{ $dateFrom->format('M j, Y') }}</strong> — <strong>{{ $dateTo->format('M j, Y') }}</strong>
        </div>
    </div>
</form>
