@extends('admin.layout')

@section('title', 'Sale Details')
@section('page-title', 'Sale Details')
@section('page-icon', 'cash-stack')

@section('content')
<div class="mb-3">
    @if(auth()->user()->hasRole('Admin') || (int)$sale->user_id === (int)auth()->id())
    <a href="{{ route('admin.sales.edit', $sale) }}" class="btn btn-outline-warning btn-sm">Edit</a>
    @endif
    <a href="{{ route('admin.sales.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <small class="text-muted d-block">Title</small>
                <strong>{{ $sale->title }}</strong>
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Status</small>
                @php
                    $colors = ['completed'=>'success','pending'=>'warning','cancelled'=>'danger',\App\Models\Sale::STATUS_REFUNDED=>'dark'];
                @endphp
                <span class="badge bg-{{ $colors[$sale->status] ?? 'secondary' }}">{{ $sale->statusLabel() }}</span>
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Approval</small>
                @php
                    $ac = ['approved'=>'success','rejected'=>'danger','pending_approval'=>'warning'];
                    $al = ['approved'=>'Approved','rejected'=>'Rejected','pending_approval'=>'Pending Approval'];
                @endphp
                <span class="badge bg-{{ $ac[$sale->approval_status] ?? 'secondary' }}">
                    {{ $al[$sale->approval_status] ?? $sale->approval_status }}
                </span>
                @if($sale->approvedBy)
                    <span class="text-muted small ms-2">by {{ $sale->approvedBy->name }}
                        {{ $sale->approved_at?->format('d M Y') }}</span>
                @endif
            </div>
            @if($sale->approval_note)
            <div class="col-12">
                <small class="text-muted d-block">Rejection Reason</small>
                <div class="alert alert-danger py-2 mb-0">{{ $sale->approval_note }}</div>
            </div>
            @endif
            <div class="col-md-6">
                <small class="text-muted d-block">Sale type</small>
                @php $tc = ['front'=>'primary','upsell'=>'info']; @endphp
                <span class="badge bg-{{ $tc[$sale->sale_type ?? 'front'] ?? 'secondary' }}">{{ $sale->saleTypeLabel() }}</span>
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Brand</small>
                {{ $sale->brand?->name ?? '-' }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Client Name</small>
                {{ $sale->client_name }}
            </div>



            <!-- for email -->
            <div class="col-md-6">
                <small class="text-muted d-block">Client Email</small>
           {{ $sale->client_email }}

            </div>
            <!-- for phone -->
            <div class="col-md-6">
                <small class="text-muted d-block">Client Phone</small>
             {{ $sale->client_phone }}
            </div>


            
            <div class="col-md-6">
                <small class="text-muted d-block">Amount (Total)</small>
                <span class="fw-bold fs-5 {{ $sale->is_refunded ? 'text-danger' : 'text-success' }}">${{ number_format($sale->amount, 2) }}</span>
                @if($sale->is_refunded)
                    <span class="badge bg-danger ms-2">Refunded</span>
                    @if($sale->refunded_at)
                        <span class="text-muted small ms-1">{{ $sale->refunded_at->format('d M Y H:i') }}</span>
                    @endif
                    @if($sale->refundedBy)
                        <span class="text-muted small">· {{ $sale->refundedBy->name }}</span>
                    @endif
                @endif
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Received Amount</small>
                <span class="fw-bold">${{ number_format($sale->received_amount, 2) }}</span>
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Remaining Amount</small>
                @php $remaining = $sale->remainingAmount(); @endphp
                <span class="fw-bold {{ $remaining > 0 ? 'text-warning' : 'text-success' }}">${{ number_format($remaining, 2) }}</span>
            </div>
            @if($canToggleRefund ?? false)
            <div class="col-12">
                <small class="text-muted d-block mb-1">Refund (admin / team lead)</small>
                <form action="{{ route('admin.sales.toggle-refund', $sale) }}" method="POST" class="d-inline-flex align-items-center gap-2">
                    @csrf
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="refund-switch"
                            {{ $sale->is_refunded ? 'checked' : '' }}
                            onchange="this.form.submit()">
                        <label class="form-check-label" for="refund-switch">Mark as refunded</label>
                    </div>
                </form>
            </div>
            @endif
            <div class="col-md-6">
                <small class="text-muted d-block">Sale Date</small>
                {{ $sale->sale_date->format('d M Y') }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Agent</small>
                {{ $sale->user?->name ?? '-' }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Team</small>
                {{ $sale->team?->name ?? '-' }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Company</small>
                {{ $sale->company?->name ?? '-' }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Created</small>
                {{ $sale->created_at->format('d M Y, h:i A') }}
            </div>
            <div class="col-12">
                <small class="text-muted d-block">Notes</small>
                {{ $sale->notes ?: '-' }}
            </div>
            @if(($briefForms ?? collect())->isNotEmpty())
            <div class="col-12">
                <small class="text-muted d-block mb-2">Brief Forms</small>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Brief Form</th>
                                <th>Link</th>
                                <th style="width:160px">Document</th>
                                <th style="width:120px">Status</th>
                                <th style="width:120px">Answers</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($briefForms as $index => $form)
                            <tr>
                                <td>{{ $form['name'] }}</td>
                                <td>
                                    <a href="{{ $form['url'] }}" target="_blank" rel="noopener">{{ $form['url'] }}</a>
                                </td>
                                <td>
                                    @if($form['downloadUrl'])
                                        <a href="{{ $form['downloadUrl'] }}" class="btn btn-sm btn-success">Download</a>
                                    @else
                                        <span class="text-muted small">No document</span>
                                    @endif
                                </td>
                                <td>
                                    @if(($form['submissionStatus'] ?? 'pending') === 'submitted')
                                        <span class="badge bg-success">Submitted</span>
                                        @if(!empty($form['submittedAt']))
                                            <div class="small text-muted mt-1">{{ $form['submittedAt']->format('d M Y, h:i A') }}</div>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!empty($form['submission']))
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#briefAnswersModal{{ $index }}">
                                            View
                                        </button>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @foreach($briefForms as $index => $form)
                @if(!empty($form['submission']))
                <div class="modal fade" id="briefAnswersModal{{ $index }}" tabindex="-1" aria-labelledby="briefAnswersModalLabel{{ $index }}" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="briefAnswersModalLabel{{ $index }}">{{ $form['name'] }} — Brief Answers</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <dl class="row mb-0">
                                    @foreach(($form['submission']->data ?? []) as $key => $value)
                                        <dt class="col-sm-4">{{ $form['fieldLabels'][$key] ?? ucfirst(str_replace('_', ' ', $key)) }}</dt>
                                        <dd class="col-sm-8">
                                            @if(is_array($value))
                                                {{ implode(', ', $value) }}
                                            @else
                                                {!! nl2br(e((string) $value)) ?: '—' !!}
                                            @endif
                                        </dd>
                                    @endforeach
                                </dl>

                                @if(!empty($form['submission']->attachments))
                                    <hr>
                                    <h6 class="mb-2">Attachments</h6>
                                    <ul class="mb-0">
                                        @foreach($form['submission']->attachments as $attachment)
                                            <li>
                                                @if(!empty($attachment['public_url']))
                                                    <a href="{{ $attachment['public_url'] }}" target="_blank" rel="noopener">
                                                        {{ $attachment['original_name'] ?? 'Download file' }}
                                                    </a>
                                                @else
                                                    {{ $attachment['original_name'] ?? 'File' }}
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            @endforeach
            @elseif($sale->brand)
            <div class="col-12">
                <small class="text-muted d-block">Brief Forms</small>
                <span class="text-muted">No brief forms configured for this brand.</span>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
