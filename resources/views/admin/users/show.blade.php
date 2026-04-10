@extends('admin.layout')

@section('title', 'User Details')
@section('page-title', 'User Details')
@section('page-icon', 'person-vcard')

@section('content')
    <div class="card">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Name</dt>
                <dd class="col-sm-9">{{ $user->name }}</dd>

                <dt class="col-sm-3">Email</dt>
                <dd class="col-sm-9">{{ $user->email }}</dd>

                <dt class="col-sm-3">Role</dt>
                <dd class="col-sm-9">{{ $user->role?->name ?? '-' }}</dd>

                <dt class="col-sm-3">Team</dt>
                <dd class="col-sm-9">{{ $user->team?->name ?? '-' }}</dd>
            </dl>
        </div>
    </div>
@endsection
