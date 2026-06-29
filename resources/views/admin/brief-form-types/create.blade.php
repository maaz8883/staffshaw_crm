@extends('admin.layout')

@section('title', 'Add Brief Form Type')
@section('page-title', 'Add Brief Form Type')
@section('page-icon', 'plus-circle')

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.brief-form-types.store') }}" method="POST">
                @include('admin.brief-form-types._form', ['briefFormType' => null])
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('admin.brief-form-types.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    const pathInput = document.getElementById('default_form_path');
    if (!nameInput || !slugInput || !pathInput) {
        return;
    }

    let slugTouched = slugInput.value.trim() !== '';
    let pathTouched = pathInput.value.trim() !== '';

    slugInput.addEventListener('input', function () {
        slugTouched = slugInput.value.trim() !== '';
    });

    pathInput.addEventListener('input', function () {
        pathTouched = pathInput.value.trim() !== '';
    });

    nameInput.addEventListener('input', function () {
        const slug = nameInput.value
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        if (!slugTouched && slug) {
            slugInput.value = slug;
        }

        if (!pathTouched && slug) {
            pathInput.value = '/' + slug + '-brief';
        }
    });
});
</script>
@endsection
