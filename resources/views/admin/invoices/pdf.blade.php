<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #212529; margin: 24px; }
        h3, h4, h6 { margin: 0 0 8px; }
        .text-muted { color: #6c757d; }
        .text-end { text-align: right; }
        .fw-semibold { font-weight: 600; }
        .fw-bold { font-weight: 700; }
        .small { font-size: 11px; }
        .mb-0 { margin-bottom: 0; }
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .badge { display: inline-block; padding: 4px 8px; background: #6c757d; color: #fff; font-size: 10px; border-radius: 4px; }
        .row { width: 100%; margin-bottom: 16px; }
        .col-md-6 { width: 48%; display: inline-block; vertical-align: top; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #dee2e6; padding: 8px; }
        thead th { background: #f8f9fa; text-align: left; }
        tfoot th, tfoot td { background: #fff; }
    </style>
</head>
<body>
    @include('admin.invoices._body', ['forPdf' => true])
</body>
</html>
