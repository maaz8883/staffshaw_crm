@extends('admin.layout')
@section('title', 'Edit Expense')
@section('page-title', 'Edit Expense')
@section('page-icon', 'pencil')
@section('content')
<div class="card"><div class="card-body">
    <form method="POST" action="{{ route('admin.expenses.update', $expense) }}">
        @method('PUT')
        @include('admin.expenses._form')
        <button class="btn btn-primary">Save</button>
        <a href="{{ route('admin.expenses.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
@endsection
