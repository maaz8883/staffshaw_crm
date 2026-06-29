@php
    $row = $row ?? [];
    $isTemplate = ($index === '__INDEX__');
    $rowId = $row['id'] ?? null;
    $document = $row['document'] ?? null;
    $documentName = $row['document_name'] ?? null;
    $isActive = filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
@endphp

<tr class="brief-form-row" data-index="{{ $index }}">
    <td>
        <select name="brief_forms[{{ $index }}][brief_form_type_id]" class="form-select form-select-sm brief-form-type">
            <option value="">Custom</option>
            @foreach($briefFormTypes as $type)
                <option value="{{ $type->id }}"
                    @selected((string) old("brief_forms.{$index}.brief_form_type_id", $row['brief_form_type_id'] ?? '') === (string) $type->id)>
                    {{ $type->name }}
                </option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="text" name="brief_forms[{{ $index }}][name]"
            class="form-control form-control-sm brief-form-name" required
            value="{{ old("brief_forms.{$index}.name", $row['name'] ?? '') }}">
    </td>
    <td>
        <input type="text" name="brief_forms[{{ $index }}][form_path]"
            class="form-control form-control-sm brief-form-path" required
            placeholder="/brief-form"
            value="{{ old("brief_forms.{$index}.form_path", $row['form_path'] ?? '') }}">
    </td>
    <td>
        @if($rowId)
            <input type="hidden" name="brief_forms[{{ $index }}][id]" class="brief-form-id" value="{{ $rowId }}">
        @endif
        <input type="hidden" name="brief_forms[{{ $index }}][_delete]" value="0">

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
            class="form-check-input" @checked(old("brief_forms.{$index}.is_active", $isActive))>
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger remove-brief-form-row">Remove</button>
    </td>
</tr>
