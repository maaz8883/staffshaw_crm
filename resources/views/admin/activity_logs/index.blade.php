@extends('admin.layout')

@section('title', 'Activity Logs')
@section('page-title', 'Activity Logs')
@section('page-icon', 'clock-history')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="row g-2 align-items-end">
            <div class="col-sm-4">
                <label class="form-label small mb-1">User</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All Users</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small mb-1">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}" @selected(request('type') === $t)>{{ str_replace('_', ' ', ucfirst($t)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-auto">
                <button class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>IP</th>
                        <th>Country / City</th>
                        <th>Browser / OS</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td>
                            @if($log->user)
                                <a href="{{ route('admin.users.show', $log->user_id) }}">{{ $log->user->name }}</a>
                            @else
                                <span class="text-muted">Deleted</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $log->typeBadgeClass() }}">
                                {{ str_replace('_', ' ', ucfirst($log->type)) }}
                            </span>
                        </td>
                        <td>{{ $log->description }}</td>
                        <td class="font-monospace">{{ $log->ip_address ?? '-' }}</td>
                        <td>
                            @if($log->country)
                                {{ $log->country }}{{ $log->city ? ', ' . $log->city : '' }}
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $log->user_agent }}">
                            {{ $log->user_agent ? \Illuminate\Support\Str::limit($log->user_agent, 50) : '-' }}
                        </td>
                        <td class="text-nowrap text-muted">{{ $log->created_at->format('d M Y H:i') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No activity logs found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
    <div class="card-footer">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
