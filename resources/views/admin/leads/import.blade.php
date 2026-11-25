@extends('admin.layout')

@section('title', 'Import Leads')
@section('page-title', 'Import Leads')
@section('page-icon', 'upload')

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.leads.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Leads
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
    <strong>Imported with notes</strong> (lead was still created)
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
                    <strong>Assigned To</strong> is optional — match CRM user if possible, otherwise lead stays Unassigned.
                </p>
                <form method="POST" action="{{ route('admin.leads.import.store') }}" enctype="multipart/form-data">
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
                        <button type="submit" class="btn btn-primary">Import leads</button>
                        <a href="{{ route('admin.leads.import.template') }}" class="btn btn-outline-primary">
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
                    <li><strong>Name</strong> — required</li>
                    <li><strong>Status</strong> — New Lead, Contacted, Follow Up, Proposal, Client, Lost (blank = New Lead)</li>
                    <li><strong>Source</strong> — website, upwork, referral, social_media, email, cold_call, other</li>
                    <li><strong>Assigned To</strong> — CRM name/email; no match → Unassigned</li>
                    <li><strong>Brand / Team</strong> — exact CRM names when filled</li>
                    <li>Duplicate email/phone rows are skipped</li>
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
