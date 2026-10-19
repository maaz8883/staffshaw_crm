<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; }
        /* DejaVu runs wider than screen fonts: tighten the type a little for A4 */
        .inv-doc { font-size: 11px !important; }
        .inv-doc .inv-title { font-size: 22px !important; }
        .inv-doc .inv-totals td { font-size: 11px !important; }
        .inv-doc .inv-totals .grand td { font-size: 13px !important; }
        .inv-doc .inv-summary .amt { font-size: 14px !important; }
        /* Tighter spacing so a normal invoice fits on one A4 page */
        .inv-doc .inv-pad { padding: 26px 34px 20px !important; }
        .inv-doc .inv-rule { margin: 18px 0 16px !important; }
        .inv-doc .inv-items { margin-top: 20px !important; }
        .inv-doc .inv-items th { padding: 8px 12px !important; }
        .inv-doc .inv-items td { padding: 9px 12px !important; }
        .inv-doc .inv-totals { margin-top: 12px !important; }
        .inv-doc .inv-totals td { padding: 4px 12px !important; }
        .inv-doc .inv-totals .grand td { padding: 9px 12px !important; }
        .inv-doc .inv-summary, .inv-doc .inv-notes { margin-top: 18px !important; }
        .inv-doc .inv-foot { margin-top: 22px !important; padding-top: 12px !important; page-break-inside: avoid; }
        /* DejaVu has no 600 weight: dompdf would fall back to a serif font */
        .inv-doc, .inv-doc * { font-family: DejaVu Sans, sans-serif !important; }
        .inv-doc .inv-meta .v, .inv-doc .inv-items .desc { font-weight: bold !important; }
    </style>
</head>
<body>
    @include('admin.invoices._document', ['forPdf' => true])
</body>
</html>
