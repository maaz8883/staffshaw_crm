@extends('admin.layout')

@section('title', 'Add Client')
@section('page-title', 'Add Client')
@section('page-icon', 'plus-circle')

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.clients.store') }}" method="POST">
            @include('admin.clients._form', ['client' => null])
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('admin.clients.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
