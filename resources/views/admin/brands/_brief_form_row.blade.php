@php

    $row = $row ?? [];

    $isTemplate = ($index === '__INDEX__') || ! empty($isTemplate);

    $rowId = $row['id'] ?? null;

    $document = $row['document'] ?? null;

    $documentName = $row['document_name'] ?? null;

    $isActive = filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

    $brandWebsite = rtrim((string) ($brandWebsite ?? ''), '/');

@endphp



<tr class="brief-form-row" data-index="{{ $index }}">

    <td>

        <input type="text" name="brief_forms[{{ $index }}][name]"

            class="form-control form-control-sm brief-form-name" required

            value="{{ $isTemplate ? 'Brief Form' : old("brief_forms.{$index}.name", $row['name'] ?? 'Brief Form') }}">

        <input type="hidden" name="brief_forms[{{ $index }}][form_path]" value="/brief-form">

        <!-- @if($rowId && ! $isTemplate)

            <div class="small text-muted mt-1">

                @if($brandWebsite)

                    <a href="{{ $brandWebsite }}/brief-form?sale_id=0&amp;form_id={{ $rowId }}" target="_blank" rel="noopener">

                        Preview link

                    </a>

                @endif

            </div>

        @endif -->

    </td>

    <td>

        @if($rowId && ! $isTemplate)

            <code>#{{ $rowId }}</code>

        @else

            <span class="text-muted small">Save to assign</span>

        @endif

    </td>

    <td>

        @if($rowId && ! $isTemplate)

            <input type="hidden" name="brief_forms[{{ $index }}][id]" class="brief-form-id" value="{{ $rowId }}">

        @endif

        <input type="hidden" name="brief_forms[{{ $index }}][_delete]" value="0" class="brief-form-delete-flag">



        @if($document && ! $isTemplate)

            <div class="small mb-1">

                <a href="{{ asset('storage/' . $document) }}" target="_blank" rel="noopener">

                    {{ $documentName ?? basename($document) }}

                </a>

            </div>

        @endif



        <input type="file" name="brief_forms[{{ $index }}][document]" class="form-control form-control-sm"

            accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">

    </td>

    <td class="text-center">

        <input type="hidden" name="brief_forms[{{ $index }}][is_active]" value="0">

        <input type="checkbox" name="brief_forms[{{ $index }}][is_active]" value="1"

            class="form-check-input" @checked($isTemplate ? true : old("brief_forms.{$index}.is_active", $isActive))>

    </td>

    @if(config('brief.builder_enabled'))

    <td class="text-center">

        @if($rowId && ! $isTemplate && isset($brand) && $brand)

            <a href="{{ route('admin.brands.brief-forms.builder', [$brand, $rowId]) }}" class="btn btn-sm btn-outline-primary">Build</a>

        @else

            <span class="text-muted small">Save first</span>

        @endif

    </td>

    @endif

    <td class="text-center">

        <button type="button" class="btn btn-sm btn-outline-danger remove-brief-form-row" title="Remove">×</button>

    </td>

</tr>

