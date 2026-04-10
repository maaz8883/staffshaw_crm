@extends('admin.layout')

@section('title', 'Edit Lead')
@section('page-title', 'Edit Lead')
@section('page-icon', 'pencil-square')

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.leads.update', $lead) }}" method="POST">
                @method('PUT')
                @include('admin.leads._form')
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('admin.leads.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
