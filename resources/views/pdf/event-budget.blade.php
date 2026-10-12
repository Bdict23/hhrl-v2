<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Event Budget Allocation - {{ $reference }}</title>
    <style>
        @page {
            margin: 15mm 12mm;
        }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 10.5px;
            color: #1e293b;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .logo {
            height: 60px;
            width: auto;
        }
        .company-name {
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            color: #0f172a;
        }
        .company-subtitle {
            font-size: 9.5px;
            color: #475569;
        }
        .doc-badge {
            display: inline-block;
            background: #0f172a;
            color: #ffffff;
            font-size: 9.5px;
            font-weight: bold;
            padding: 2px 7px;
            text-transform: uppercase;
        }
        .doc-ref {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 3px;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
        }
        .info-grid td {
            vertical-align: top;
            font-size: 10px;
            padding: 2px 4px;
        }
        .info-label { color: #64748b; }
        .info-val { color: #0f172a; font-weight: 600; }
        .metrics-table {
            width: 100%;
            margin-bottom: 12px;
        }
        .metric-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            text-align: center;
        }
        .metric-title {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
        }
        .metric-val {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
        }
        .section-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #0f172a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 3px;
            margin-top: 10px;
            margin-bottom: 6px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .items-table th {
            background: #f1f5f9;
            color: #334155;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            padding: 5px 6px;
            text-align: left;
        }
        .items-table td {
            padding: 4px 6px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 9.5px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
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
            padding-bottom: 3px;
            font-size: 10.5px;
            color: #0f172a;
        }
        .sig-title {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .footer-text {
            border-top: 1px dashed #cbd5e1;
            padding-top: 6px;
            font-size: 8.5px;
            color: #94a3b8;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    @php
        $menuTotal = $menuTotal ?? 0;
        $serviceTotal = $serviceTotal ?? 0;
        $totalGrossOrder = $totalGrossOrder ?? 0;
        $allocatedBudget = $allocatedBudget ?? 0;
        $budgetPercentage = $budgetPercentage ?? '0.00';
    @endphp

    {{-- Header --}}
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 70px; vertical-align: middle;">
                @if(!empty($logo))
                    <img src="{{ $logo }}" class="logo" alt="Logo" />
                @endif
            </td>
            <td style="vertical-align: middle; padding-left: 8px;">
                <div class="company-name">{{ $company }}</div>
                <div class="company-subtitle">{{ $branch }} @if($branch_address) • {{ $branch_address }} @endif</div>
                <div class="company-subtitle">Banquet Event Procurement & Budget Allocation Division</div>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <div class="doc-badge">EVENT BUDGET</div>
                <div class="doc-ref">{{ $reference }}</div>
                <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">
                    Event Ref: <span style="font-weight: bold;">{{ $eventReference }}</span>
                </div>
                <!-- <div style="font-size: 9.5px; font-weight: bold; color: {{ $status === 'APPROVED' ? '#15803d' : ($status === 'REJECTED' ? '#dc2626' : '#b45309') }};">
                    {{ $status }}
                </div> -->
            </td>
        </tr>
    </table>

    {{-- Event Profile Grid (NO current user info) --}}
    <table class="info-grid" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 50%;">
                <span class="info-label">Customer / Client:</span>
                <span class="info-val">{{ $customerName ?: 'N/A' }}</span>
                <br><span class="info-label">Include Services in Gross:</span>
                <span class="info-val">{{ $addToGross ? 'Yes (Included)' : 'No' }}</span>
            </td>
            <td style="width: 50%;">
                <span class="info-label">Event Schedule:</span>
                <span class="info-val">{{ $eventStartDate }} - {{ $eventEndDate }}</span>
                <br><span class="info-label">Hours:</span>
                <span class="info-val">{{ $eventArrivalTime }} to {{ $eventDepartureTime }}</span>
            </td>
        </tr>
    </table>

    {{-- Financial Metrics --}}
    <table class="metrics-table" cellpadding="0" cellspacing="6">
        <tr>
            <td style="width: 25%;">
                <div class="metric-card">
                    <div class="metric-title">Menu Subtotal</div>
                    <div class="metric-val">P{{ number_format((float) $menuTotal, 2) }}</div>
                </div>
            </td>
            <td style="width: 25%;">
                <div class="metric-card">
                    <div class="metric-title">Services Subtotal</div>
                    <div class="metric-val">P{{ number_format((float) $serviceTotal, 2) }}</div>
                </div>
            </td>
            <td style="width: 25%;">
                <div class="metric-card">
                    <div class="metric-title">Gross Order Amount</div>
                    <div class="metric-val">P{{ number_format((float) $totalGrossOrder, 2) }}</div>
                </div>
            </td>
            <td style="width: 25%;">
                <div class="metric-card" style="background: #f0fdf4; border-color: #bbf7d0;">
                    <div class="metric-title" style="color: #15803d;">Allocated Budget ({{ $budgetPercentage }}%)</div>
                    <div class="metric-val" style="color: #15803d; font-size: 13px;">P{{ number_format((float) str_replace(',', '', $allocatedBudget), 2) }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- 1. Menu Items Table --}}
    @if(!empty($menuList))
        <div class="section-title">1. Menu Items Breakdown</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 25px;" class="text-center">#</th>
                    <th>Menu / Recipe</th>
                    <th>Category</th>
                    <th class="text-right" style="width: 65px;">Rate</th>
                    <th class="text-right" style="width: 40px;">Qty</th>
                    <th class="text-right" style="width: 75px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($menuList as $mIdx => $mItem)
                    <tr>
                        <td class="text-center" style="color: #64748b;">{{ $mIdx + 1 }}</td>
                        <td style="font-weight: 600;">{{ $mItem['menu_name'] ?? 'N/A' }}</td>
                        <td style="color: #475569;">{{ $mItem['category'] ?? 'N/A' }}</td>
                        <td class="text-start">P{{ number_format((float) ($mItem['amount'] ?? 0), 2) }}</td>
                        <td class="text-start">{{ $mItem['qty'] ?? 0 }}</td>
                        <td class="text-right" style="font-weight: bold;">P{{ number_format((float) ($mItem['total'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- 2. Services Table --}}
    @if(!empty($servicesList))
        <div class="section-title">2. Services & Miscellaneous Breakdown</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 25px;" class="text-center">#</th>
                    <th>Service Name</th>
                    <th class="text-right" style="width: 65px;">Rate / Income</th>
                    <th class="text-right" style="width: 40px;">Qty</th>
                    <th class="text-right" style="width: 75px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($servicesList as $sIdx => $sItem)
                    <tr>
                        <td class="text-center" style="color: #64748b;">{{ $sIdx + 1 }}</td>
                        <td style="font-weight: 600;">{{ $sItem['title'] ?? 'N/A' }}</td>
                        <td class="text-start">P{{ number_format((float) ($sItem['income'] ?? 0), 2) }}</td>
                        <td class="text-start">{{ $sItem['qty'] ?? 0 }}</td>
                        <td class="text-right" style="font-weight: bold;">P{{ number_format((float) ($sItem['total'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Notes --}}
    @if($notes)
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 10px; font-size: 9.5px; margin-bottom: 15px;">
            <strong>Notes / Justification:</strong><br>
            {{ $notes }}
        </div>
    @endif

    {{-- Signatures --}}
    <table class="signatures-table" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="sig-line">{{ $preparer?->full_name ?? 'Procurement Officer' }}</div>
                <div class="sig-title">Prepared By</div>
                <div style="font-size: 8px; color: #94a3b8;">{{ $preparer?->position_name ?? 'Procurement Staff' }}</div>
            </td>
            <td>
                <div class="sig-line">{{ $reviewer?->full_name ?? '_________________________' }}</div>
                <div class="sig-title">Reviewed / Noted By</div>
                <div style="font-size: 8px; color: #94a3b8;">{{ $reviewer?->position_name ?? 'Financial Reviewer' }}</div>
            </td>
            <td>
                <div class="sig-line">{{ $approver?->full_name ?? '_________________________' }}</div>
                <div class="sig-title">Approved By</div>
                <div style="font-size: 8px; color: #94a3b8;">{{ $approver?->position_name ?? 'General Manager' }}</div>
            </td>
        </tr>
    </table>

    <table style="width: 100%;" class="footer-text">
        <tr>
            <td>{{ $company }} • Banquet Budget Management System</td>
            <td style="text-align: right;">Generated on {{ now()->format('M d, Y h:i A') }}</td>
        </tr>
    </table>
</body>
</html>
