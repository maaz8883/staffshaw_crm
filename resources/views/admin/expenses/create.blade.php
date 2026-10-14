@extends('admin.layout')
@section('title', 'Add Expense')
@section('page-title', 'Add Expense')
@section('page-icon', 'wallet2')
@section('content')
<div class="card"><div class="card-body">
    <form method="POST" action="{{ route('admin.expenses.store') }}">
        @include('admin.expenses._form', ['expense' => null])
        <button class="btn btn-primary">Save</button>
        <a href="{{ route('admin.expenses.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
@endsection
