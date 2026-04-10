@extends('admin.layout')

@section('title', 'Add Lead')
@section('page-title', 'Add Lead')
@section('page-icon', 'plus-circle')

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.leads.store') }}" method="POST">
                @include('admin.leads._form', ['lead' => null])
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('admin.leads.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
