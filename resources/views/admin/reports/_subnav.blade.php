@php
    $q = request()->query();
    $r = request()->route()?->getName();
@endphp
<ul class="nav nav-pills flex-wrap gap-1 mb-4 p-2 bg-white rounded shadow-sm border">
    <li class="nav-item">
        <a class="nav-link py-2 px-3 {{ $r === 'admin.reports.index' ? 'active' : '' }}" href="{{ route('admin.reports.index') }}">
            <i class="bi bi-grid-3x3-gap"></i> Overview
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link py-2 px-3 {{ $r === 'admin.reports.company' ? 'active' : '' }}" href="{{ route('admin.reports.company', $q) }}">
            <i class="bi bi-buildings"></i> Company
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link py-2 px-3 {{ $r === 'admin.reports.team' ? 'active' : '' }}" href="{{ route('admin.reports.team', $q) }}">
            <i class="bi bi-people"></i> Team
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link py-2 px-3 {{ $r === 'admin.reports.sales' ? 'active' : '' }}" href="{{ route('admin.reports.sales', $q) }}">
            <i class="bi bi-cash-stack"></i> Sales
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link py-2 px-3 {{ $r === 'admin.reports.user' ? 'active' : '' }}" href="{{ route('admin.reports.user', $q) }}">
            <i class="bi bi-person-badge"></i> User
        </a>
    </li>
</ul>
