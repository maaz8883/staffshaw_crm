@extends('admin.layout')

@section('title', 'Reports')
@section('page-title', 'Reports')
@section('page-icon', 'graph-up-arrow')

@section('content')
@include('admin.reports._subnav')

<div class="row g-3">
    <div class="col-md-6 col-lg-3">
        <a href="{{ route('admin.reports.company') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-4">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex p-4 mb-3">
                        <i class="bi bi-buildings fs-2 text-primary"></i>
                    </div>
                    <h5 class="fw-semibold text-dark">Company report</h5>
                    <p class="text-muted small mb-0">Revenue &amp; sales by company</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-3">
        <a href="{{ route('admin.reports.team') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-4">
                    <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex p-4 mb-3">
                        <i class="bi bi-people fs-2 text-success"></i>
                    </div>
                    <h5 class="fw-semibold text-dark">Team report</h5>
                    <p class="text-muted small mb-0">Performance by team</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-3">
        <a href="{{ route('admin.reports.sales') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-4">
                    <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex p-4 mb-3">
                        <i class="bi bi-cash-stack fs-2 text-warning"></i>
                    </div>
                    <h5 class="fw-semibold text-dark">Sales report</h5>
                    <p class="text-muted small mb-0">Trends, status &amp; approvals</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-3">
        <a href="{{ route('admin.reports.user') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-4">
                    <div class="rounded-circle bg-info bg-opacity-10 d-inline-flex p-4 mb-3">
                        <i class="bi bi-person-badge fs-2 text-info"></i>
                    </div>
                    <h5 class="fw-semibold text-dark">User report</h5>
                    <p class="text-muted small mb-0">Agents &amp; revenue</p>
                </div>
            </div>
        </a>
    </div>
</div>
@endsection
