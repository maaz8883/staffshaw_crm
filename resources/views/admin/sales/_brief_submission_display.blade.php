@php
    /** @var list<array{type: string, question?: string, answers?: list<string>, label?: string, value?: string}> $displayBlocks */
@endphp

<dl class="row mb-0 brief-submission-display">
    @foreach($displayBlocks as $block)
        @if(($block['type'] ?? '') === 'checkbox_group')
            <dt class="col-12 fw-semibold mt-3 mb-1">{{ $block['question'] ?? '' }}</dt>
            <dd class="col-12 mb-0">
                <ul class="mb-0 ps-3">
                    @foreach($block['answers'] ?? [] as $answer)
                        <li>{{ $answer }}</li>
                    @endforeach
                </ul>
            </dd>
        @elseif(($block['type'] ?? '') === 'field')
            <dt class="col-sm-4">{{ $block['label'] ?? '' }}</dt>
            <dd class="col-sm-8">{!! nl2br(e($block['value'] ?? '')) ?: '—' !!}</dd>
        @endif
    @endforeach
</dl>
