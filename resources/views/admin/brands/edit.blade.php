@extends('admin.layout')

@section('title', 'Edit Brand')
@section('page-title', 'Edit Brand')
@section('page-icon', 'pencil-square')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.brands.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.brands.update', $brand) }}" method="POST" enctype="multipart/form-data">
                @method('PUT')
                @include('admin.brands._form')
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('admin.brands.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
window.BRAND_BRIEF_FORMS = {
    storeUrl: @json(route('admin.brands.brief-forms.store', $brand)),
    destroyUrlTemplate: @json(route('admin.brands.brief-forms.destroy', ['brand' => $brand, 'form' => '__FORM_ID__'])),
    csrfToken: @json(csrf_token()),
    builderEnabled: @json(config('brief.builder_enabled')),
};
</script>
<script src="{{ asset('js/brand-brief-forms.js') }}"></script>
@endsection
