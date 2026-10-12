<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order - {{ $reference }}</title>
    <style>
        @page {
            margin: 15mm 12mm;
        }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .logo {
            height: 65px;
            width: auto;
        }
        .company-name {
            font-size: 16px;
            font-weight: 800;
            text-transform: uppercase;
            color: #0f172a;
        }
        .company-subtitle {
            font-size: 10px;
            color: #475569;
        }
        .doc-badge {
            display: inline-block;
            background: #0f172a;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            padding: 3px 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-ref {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 4px;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px;
        }
        .info-grid td {
            vertical-align: top;
            font-size: 10.5px;
            padding: 3px 6px;
        }
        .info-label {
            color: #64748b;
            font-weight: normal;
        }
        .info-val {
            color: #0f172a;
            font-weight: 600;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .items-table th {
            background: #f1f5f9;
            color: #334155;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
        }
        .items-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 8px;
        }
        .items-table tfoot td {
            border-top: 2px solid #0f172a;
            font-weight: bold;
            font-size: 10px;
            padding: 8px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .notes-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            margin-bottom: 20px;
            font-size: 10px;
        }
        .signatures-table {
            width: 100%;
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .signatures-table td {
            text-align: center;
            width: 33.33%;
            padding: 0 15px;
            vertical-align: bottom;
        }
        .sig-line {
            border-bottom: 1px solid #94a3b8;
            font-weight: bold;
            padding-bottom: 4px;
            font-size: 11px;
            color: #0f172a;
        }
        .sig-title {
            font-size: 9.5px;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .footer-text {
            border-top: 1px dashed #cbd5e1;
            padding-top: 8px;
            font-size: 9px;
            color: #94a3b8;
            margin-top: 25px;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 75px; vertical-align: middle;">
                @if(!empty($logo))
                    <img src="{{ $logo }}" class="logo" alt="Logo" />
                @endif
            </td>
            <td style="vertical-align: middle; padding-left: 10px;">
                <div class="company-name">{{ $company }}</div>
                <div class="company-subtitle">{{ $branch }} @if($branch_address) • {{ $branch_address }} @endif</div>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <div class="doc-badge">PURCHASE ORDER</div>
                <div class="doc-ref">{{ $reference }}</div>
                <div style="font-size: 10px; color: #64748b; margin-top: 2px;">
                    Date: {{ $created_at ? \Carbon\Carbon::parse($created_at)->format('M d, Y') : now()->format('M d, Y') }}
                </div>
                <!-- <div style="font-size: 10px; font-weight: bold; color: {{ $status === 'FINALIZED' ? '#15803d' : '#b45309' }};">
                    Status: {{ $status }}
                </div> -->
            </td>
        </tr>
    </table>

    {{-- Order Information Grid --}}
    <table class="info-grid" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 50%;">
                <span class="info-label">Supplier:</span>
                <span class="info-val">{{ $supplier?->supp_name ?? 'N/A' }}</span>
                @if($supplier?->contact_person || $supplier?->contact_no_1)
                    <br><span class="info-label">Contact:</span>
                    <span class="info-val">{{ $supplier->contact_person ? $supplier->contact_person . ' (' . $supplier->contact_no_1 . ')' : $supplier->contact_no_1 }}</span>
                @endif
                @if($supplier?->supp_address)
                    <br><span class="info-label">Address:</span>
                    <span class="info-val">{{ $supplier->supp_address }}</span>
                @endif
            </td>
            <td style="width: 50%;">
                <span class="info-label">Merchandise P.O #:</span>
                <span class="info-val">{{ $merchandisePONumber ?: 'N/A' }}</span>
                <br><span class="info-label">Type / Terms:</span>
                <span class="info-val">{{ $type?->name ?? 'N/A' }} • {{ $term?->name ?? 'N/A' }}</span>
                @if($event)
                    <br><span class="info-label">Associated Event:</span>
                    <span class="info-val">{{ $event->event_name }} ({{ $event->reference }})</span>
                @endif
            </td>
        </tr>
    </table>

    {{-- Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">#</th>
                <th>Item Code</th>
                <th>Description</th>
                <th>Category</th>
                <th class="text-center" style="width: 45px;">Unit</th>
                <th class="text-right" style="width: 65px;">Cost</th>
                <th class="text-right" style="width: 45px;">Qty</th>
                <th class="text-right" style="width: 80px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($selectedRows as $index => $row)
                <tr>
                    <td class="text-start" style="color: #64748b;">{{ $index + 1 }}</td>
                    <td style="font-weight: 600;">{{ $row['item_code'] ?? 'N/A' }}</td>
                    <td>{{ $row['item_description'] ?? 'N/A' }}</td>
                    <td style="color: #475569;">{{ $row['category'] ?? 'N/A' }}</td>
                    <td class="text-start">{{ $row['unit'] ?? 'N/A' }}</td>
                    <td class="text-right">P{{ number_format((float) ($row['cost'] ?? 0), 2) }}</td>
                    <td class="text-right" style="font-weight: 600;">{{ $row['quantity'] ?? 0 }}</td>
                    <td class="text-right" style="font-weight: bold;">P{{ number_format((float) ($row['sub_total'] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 15px; color: #94a3b8;">No items in this purchase order.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" class="text-right" style="text-transform: uppercase;">Total Purchase Order Cost:</td>
                <td class="text-right" style="color: #15803d; font-size: 12px;">P{{ number_format((float) $grand_total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Notes --}}
    @if($notes)
        <div class="notes-box">
            <strong>Notes / Instructions:</strong><br>
            {{ $notes }}
        </div>
    @endif

    {{-- Signatures --}}
    <table class="signatures-table" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="sig-line">{{ $preparer?->full_name ?? 'Purchasing Officer' }}</div>
                <div class="sig-title">Prepared By</div>
                <div style="font-size: 8.5px; color: #94a3b8;">{{ $preparer?->position_name ?? 'Purchaser' }}</div>
            </td>
            <td>
                <div class="sig-line">{{ $reviewer?->full_name ?? '_________________________' }}</div>
                <div class="sig-title">Reviewed By</div>
                <div style="font-size: 8.5px; color: #94a3b8;">{{ $reviewer?->position_name ?? 'Reviewer' }}</div>
            </td>
            <td>
                <div class="sig-line">{{ $approver?->full_name ?? '_________________________' }}</div>
                <div class="sig-title">Approved By</div>
                <div style="font-size: 8.5px; color: #94a3b8;">{{ $approver?->position_name ?? 'Approver' }}</div>
            </td>
        </tr>
    </table>

    <table style="width: 100%;" class="footer-text">
        <tr>
            <td>{{ $company }} • Purchase Order</td>
            <td style="text-align: right;">Generated on {{ now()->format('M d, Y h:i A') }}</td>
        </tr>
    </table>
</body>
</html>
