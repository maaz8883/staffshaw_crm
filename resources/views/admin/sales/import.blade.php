@extends('admin.layout')

@section('title', 'Import Sales')
@section('page-title', 'Import Sales')
@section('page-icon', 'upload')

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.sales.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Sales
    </a>
</div>

@if(session('import_errors') && count(session('import_errors')))
<div class="alert alert-danger">
    <strong>Rows skipped</strong>
    <ul class="mb-0 mt-2 small">
        @foreach(session('import_errors') as $err)
            <li>Row {{ $err['row'] }}: {{ $err['message'] }}</li>
        @endforeach
    </ul>
</div>
@endif

@if(session('import_warnings') && count(session('import_warnings')))
<div class="alert alert-warning">
    <strong>Imported with notes</strong> (sale was still created)
    <ul class="mb-0 mt-2 small">
        @foreach(session('import_warnings') as $warn)
            <li>Row {{ $warn['row'] }}: {{ $warn['message'] }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Upload CSV</div>
            <div class="card-body">
                <p class="text-muted small">
                    Fill the template in Excel, then <strong>Save As → CSV (Comma delimited)</strong> and upload here.
                    Each row = one sale. Team leads / managers / admins: imports are auto-approved.
                    Agents: sales stay pending approval.
                </p>
                <form method="POST" action="{{ route('admin.sales.import.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="file" class="form-label">CSV file</label>
                        <input type="file" name="file" id="file" class="form-control @error('file') is-invalid @enderror"
                               accept=".csv,text/csv" required>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">Import sales</button>
                        <a href="{{ route('admin.sales.import.template') }}" class="btn btn-outline-primary">
                            Download template
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Column rules</div>
            <div class="card-body small">
                <ul class="mb-3">
                    <li><strong>Sales Type</strong> — Front or Upsell</li>
                    <li><strong>PPC</strong> — Yes / No</li>
                    <li><strong>Brand</strong> — exact CRM brand name</li>
                    <li><strong>Order Date</strong> — YYYY-MM-DD or MM/DD/YYYY (US)</li>
                    <li><strong>Paid Amount + Balance</strong> = total sale amount</li>
                    <li><strong>Status</strong> — Pending, Completed, Cancelled (blank = Completed)</li>
                    <li><strong>Sales Agent</strong> — match CRM user when possible; if the person left / isn’t in CRM, their <em>name is still saved</em> on the sale (shown as Former)</li>
                    <li><strong>Account / Project Manager</strong> — same: link if found, otherwise keep the sheet name for history</li>
                    <li>Do <strong>not</strong> fill merchant summary columns (Gross Total, Disputes, etc.) — those come from reports</li>
                </ul>
                <p class="mb-1 fw-semibold">Your CRM brands</p>
                <div class="border rounded p-2" style="max-height:140px;overflow:auto">
                    @forelse($brands as $brand)
                        <span class="badge bg-secondary me-1 mb-1">{{ $brand->name }}</span>
                    @empty
                        <span class="text-muted">No brands available.</span>
                    @endforelse
                </div>
                <p class="mt-3 mb-1 fw-semibold">Template columns</p>
                <code class="small d-block" style="white-space:normal">{{ implode(' | ', $headers) }}</code>
            </div>
        </div>
    </div>
</div>
@endsection
