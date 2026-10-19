@php
    $q = request()->query();
    $r = request()->route()?->getName();
    $isAdminUser = auth()->user()?->hasRole(['Admin', 'Finance']); // expenses report
    $canViewOrgReports = $canViewOrgReports ?? auth()->user()?->hasRole(['Admin', 'Manager']);
    $items = [
        ['route' => 'admin.reports.index', 'label' => 'Overview', 'icon' => 'grid-3x3-gap'],
    ];
    if ($canViewOrgReports && ! ($singleCompany ?? false)) {
        $items[] = ['route' => 'admin.reports.company', 'label' => 'Company', 'icon' => 'buildings'];
    }
    $items = array_merge($items, [
        ['route' => 'admin.reports.team', 'label' => 'Team', 'icon' => 'people'],
        ['route' => 'admin.reports.sales', 'label' => 'Sales', 'icon' => 'cash-stack'],
        ['route' => 'admin.reports.user', 'label' => 'Agents', 'icon' => 'person-badge'],
        ['route' => 'admin.reports.brands', 'label' => 'Brands', 'icon' => 'tags'],
        ...(auth()->user()?->hasRole('Finance') ? [] : [['route' => 'admin.reports.leads', 'label' => 'Leads', 'icon' => 'funnel']]),
        ['route' => 'admin.reports.clients', 'label' => 'Clients', 'icon' => 'people-fill'],
        ['route' => 'admin.reports.invoices', 'label' => 'Invoices', 'icon' => 'receipt'],
    ]);
    if ($isAdminUser) {
        $items[] = ['route' => 'admin.reports.expenses', 'label' => 'Expenses', 'icon' => 'wallet2'];
    }
@endphp
@once
@push('styles')
<style>
    .report-subnav {
        background: var(--bg-card);
        border-color: var(--border-color) !important;
    }
    .report-subnav .nav-link {
        color: var(--text-muted);
        border-radius: 8px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .report-subnav .nav-link .bi {
        color: inherit;
        opacity: 0.95;
    }
    .report-subnav .nav-link:hover {
        color: var(--text-primary);
        background: var(--bg-hover);
    }
    .report-subnav .nav-link.active {
        color: #fff !important;
        background: var(--brand-blue) !important;
    }
    .report-subnav .nav-link.active .bi {
        color: #fff !important;
    }
</style>
@endpush
@endonce
<ul class="nav nav-pills flex-wrap gap-1 mb-4 p-2 rounded border report-subnav">
    @foreach($items as $item)
    <li class="nav-item">
        <a class="nav-link py-2 px-3 {{ $r === $item['route'] ? 'active' : '' }}"
           href="{{ route($item['route'], $item['route'] === 'admin.reports.index' ? [] : $q) }}">
            <i class="bi bi-{{ $item['icon'] }}"></i> {{ $item['label'] }}
        </a>
    </li>
    @endforeach
</ul>
