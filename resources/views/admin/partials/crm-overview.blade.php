@if(!empty($crmOverview['ready']) && empty($crmOverview['limited']))
@php
    $leadLabels = [];
    $leadValues = [];
    foreach (\App\Models\Lead::STATUS_LABELS as $key => $label) {
        $count = (int) ($crmOverview['lead_counts'][$key] ?? 0);
        if ($count > 0) {
            $leadLabels[] = $label;
            $leadValues[] = $count;
        }
    }
@endphp
<style>
    .kpi {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 6px;
        padding: 14px 16px 12px;
        border-bottom-width: 3px;
        height: 100%;
        color: var(--text-primary);
    }
    .kpi-label { font-size:12px; color:var(--text-muted); font-weight:600; }
    .kpi-value { font-size:22px; font-weight:700; color:var(--text-primary); line-height:1.2; margin-top:4px; }
    .kpi-sub { font-size:12px; color:var(--text-muted); margin-top:2px; }
    .kpi-revenue { border-bottom-color:#1e293b; }
    .kpi-invoice { border-bottom-color:#ef4444; }
    .kpi-leads { border-bottom-color:#3b82f6; }
    .kpi-pipeline { border-bottom-color:#f59e0b; }
    [data-theme="dark"] .kpi-revenue { border-bottom-color:#94a3b8; }
    .dash-chart { height:220px; }
    .dash-card-title { font-size:13px; font-weight:600; color:var(--text-primary); }
    .team-bar { height:7px; background:var(--bg-hover); border-radius:99px; overflow:hidden; }
    .team-bar > span { display:block; height:100%; background:#3b82f6; border-radius:99px; transition:width .35s ease; }
    .team-perf-grid {
        display: grid;
        gap: 12px;
        width: 100%;
    }
    .team-perf-grid.cols-1 { grid-template-columns: 1fr; }
    .team-perf-grid.cols-2 { grid-template-columns: 1fr 1fr; }
    .team-perf-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
    @media (max-width: 767.98px) {
        .team-perf-grid.cols-2,
        .team-perf-grid.cols-3 { grid-template-columns: 1fr; }
    }
    .team-perf-item {
        background: var(--bg-hover);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 14px 16px;
        min-width: 0;
    }
    .team-perf-item .team-perf-name {
        font-size: 14px;
        font-weight: 650;
        color: var(--text-primary);
        line-height: 1.2;
    }
    .team-perf-item .team-perf-pct {
        font-size: 13px;
        font-weight: 650;
        color: var(--text-primary);
        font-variant-numeric: tabular-nums;
    }
    .team-perf-item .team-perf-meta {
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 4px;
        margin-bottom: 10px;
    }
    .sale-row { border-bottom:1px solid var(--border-color); }
    .sale-row a { color:var(--text-primary); font-weight:600; }
</style>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="kpi kpi-revenue">
            <div class="kpi-label">{{ $crmOverview['month_label'] }} revenue</div>
            <div class="kpi-value">${{ number_format($crmOverview['month_revenue'], 0) }}</div>
            <div class="kpi-sub">Target ${{ number_format($crmOverview['month_target'], 0) }} · {{ $crmOverview['target_percent'] }}%</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi kpi-invoice">
            <div class="kpi-label">Invoices with balance</div>
            <div class="kpi-value">{{ $crmOverview['open_invoices'] }}<span style="font-size:14px;color:#94a3b8;font-weight:600"> / {{ $crmOverview['invoice_total'] }}</span></div>
            <div class="kpi-sub">Outstanding ${{ number_format($crmOverview['outstanding'], 0) }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi kpi-leads">
            <div class="kpi-label">Leads converted</div>
            <div class="kpi-value">{{ $crmOverview['converted'] }}<span style="font-size:14px;color:#94a3b8;font-weight:600"> / {{ $crmOverview['lead_total'] }}</span></div>
            <div class="kpi-sub">Clients from pipeline</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi kpi-pipeline">
            <div class="kpi-label">Active leads</div>
            <div class="kpi-value">{{ $crmOverview['active_leads'] }}</div>
            <div class="kpi-sub">Still in pipeline</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <div class="dash-card-title mb-2">Revenue, last 6 months</div>
                <div class="dash-chart"><canvas id="bossRevenueChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <div class="dash-card-title mb-2">Invoices</div>
                <div class="dash-chart"><canvas id="bossInvoiceChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

@php
    $showLeads = array_sum($leadValues) > 0;
    $salesCol = $showLeads ? 'col-lg-8' : 'col-12';
@endphp
<div class="row g-3 mb-3">
    @if($showLeads)
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="dash-card-title mb-2">Leads</div>
                <div class="dash-chart"><canvas id="bossLeadChart"></canvas></div>
            </div>
        </div>
    </div>
    @endif
    <div class="{{ $salesCol }}">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="dash-card-title">Recent sales</div>
                    <a href="{{ route('admin.sales.index') }}" class="small">View all</a>
                </div>
                @forelse($crmOverview['recent_sales'] as $sale)
                    <div class="d-flex justify-content-between align-items-center py-2 sale-row">
                        <div>
                            <a href="{{ route('admin.sales.show', $sale) }}" class="text-decoration-none sale-row">{{ \Illuminate\Support\Str::limit($sale->title, 28) }}</a>
                            <div class="text-muted" style="font-size:11px">{{ $sale->client_name ?: ($sale->user?->name ?? '—') }}</div>
                        </div>
                        <div class="fw-semibold">${{ number_format($sale->amount, 0) }}</div>
                    </div>
                @empty
                    <div class="text-muted small py-4 text-center">No sales yet</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@if(!empty($crmOverview['team_cards']))
@php
    $teamCards = $crmOverview['team_cards'];
    $teamCols = min(3, max(1, count($teamCards)));
@endphp
<div class="card mb-3">
    <div class="card-body">
        <div class="dash-card-title mb-3">Team performance · {{ $crmOverview['month_label'] }}</div>
        <div class="team-perf-grid cols-{{ $teamCols }}">
            @foreach($teamCards as $card)
                @php $barWidth = min(100, (float) $card['percent']); @endphp
                <div class="team-perf-item">
                    <div class="d-flex justify-content-between align-items-baseline gap-2">
                        <span class="team-perf-name text-truncate">{{ $card['name'] }}</span>
                        <span class="team-perf-pct flex-shrink-0">{{ $card['percent'] }}%</span>
                    </div>
                    <div class="team-perf-meta text-truncate">
                        {{ $card['lead'] ?: 'No lead' }} · ${{ number_format($card['achieved'], 0) }} / ${{ number_format($card['target'], 0) }}
                    </div>
                    <div class="team-bar"><span style="width:{{ $barWidth }}%"></span></div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

@php
    $bossDash = [
        'revenue' => ['labels' => $crmOverview['revenue_labels'], 'values' => $crmOverview['revenue_values']],
        'invoices' => $crmOverview['invoice_chart'],
        'leads' => ['labels' => $leadLabels, 'values' => $leadValues],
    ];
@endphp
<script type="application/json" id="boss-dash-data">@json($bossDash)</script>
@endif
