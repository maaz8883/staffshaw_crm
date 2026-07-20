@extends('admin.layout')

@section('title', 'Edit Client')
@section('page-title', 'Edit Client')
@section('page-icon', 'pencil-square')

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.clients.update', $client) }}" method="POST">
            @method('PUT')
            @include('admin.clients._form')
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
