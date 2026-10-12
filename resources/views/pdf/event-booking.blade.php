<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Banquet Event Order - {{ $event->reference }}</title>
    <style>
        @page {
            margin: 14mm 12mm;
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
            letter-spacing: 0.5px;
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
        .info-label {
            color: #64748b;
        }
        .info-val {
            color: #0f172a;
            font-weight: 600;
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
        .summary-table {
            width: 100%;
            margin-top: 8px;
            margin-bottom: 12px;
        }
        .summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            font-size: 10px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
        }
        .signatures-table {
            width: 100%;
            margin-top: 20px;
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
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <div class="doc-badge">BANQUET EVENT ORDER</div>
                <div class="doc-ref">{{ $event->reference }}</div>
                <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">
                    Date: {{ $event->created_at ? $event->created_at->format('M d, Y') : now()->format('M d, Y') }}
                </div>
                <!-- <div style="font-size: 9.5px; font-weight: bold; color: {{ $event->status === 'CONFIRMED' ? '#15803d' : '#b45309' }};">
                     {{ $event->status }}
                </div> -->
            </td>
        </tr>
    </table>

    {{-- Event & Client Details Grid (NO logged-in user info) --}}
    <table class="info-grid" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 50%;">
                <span class="info-label">Event Name:</span>
                <span class="info-val">{{ $event->event_name }}</span>
                <br><span class="info-label">Client / Customer:</span>
                <span class="info-val">{{ $event->customer?->full_name ?? 'N/A' }}</span>
                @if($event->customer?->contact_number || $event->customer?->email)
                    <br><span class="info-label">Contact:</span>
                    <span class="info-val">{{ $event->customer?->contact_number ?? 'N/A' }} • {{ $event->customer?->email ?? 'N/A' }}</span>
                @endif
            </td>
            <td style="width: 50%;">
                <span class="info-label">Event Dates:</span>
                <span class="info-val">{{ \Carbon\Carbon::parse($event->start_date)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($event->end_date)->format('M d, Y') }}</span>
                <br><span class="info-label">Schedule / Hours:</span>
                <span class="info-val">{{ date('g:i A', strtotime($event->arrival_time)) }} - {{ date('g:i A', strtotime($event->departure_time)) }}</span>
                <br><span class="info-label">Venue / Guest Count:</span>
                <span class="info-val">{{ $event->event_address }} ({{ $event->guest_count }} Pax)</span>
            </td>
        </tr>
    </table>

    {{-- 1. Catering & Menus --}}
    @if($event->menus && $event->menus->isNotEmpty())
        <div class="section-title">1. Catering & Menu Packages</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 25px;" class="text-center">#</th>
                    <th>Recipe / Menu Item</th>
                    <th>Category</th>
                    <th class="text-right" style="width: 65px;">Rate</th>
                    <th class="text-right" style="width: 40px;">Qty</th>
                    <th class="text-right" style="width: 75px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($event->menus as $mIndex => $menuItem)
                    <tr>
                        <td class="text-start" style="color: #64748b;">{{ $mIndex + 1 }}</td>
                        <td style="font-weight: 600;">
                            {{ $menuItem->recipe?->menu_name ?? 'N/A' }}
                            @if($menuItem->note)
                                <span style="font-size: 8.5px; color: #64748b; font-weight: normal; display: block;">Note: {{ $menuItem->note }}</span>
                            @endif
                        </td>
                        <td style="color: #475569;">{{ $menuItem->recipe?->category?->category_name ?? 'N/A' }}</td>
                        <td class="text-right">P{{ number_format((float) ($menuItem->price?->amount ?? $menuItem->rate?->amount ?? 0), 2) }}</td>
                        <td class="text-right">{{ $menuItem->qty }}</td>
                        <td class="text-right" style="font-weight: bold;">P{{ number_format((float) $menuItem->total_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- 2. Venues --}}
    @if($event->venues && $event->venues->isNotEmpty())
        <div class="section-title">2. Function Halls & Venues</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 25px;" class="text-center">#</th>
                    <th>Venue / Facility</th>
                    <th class="text-right" style="width: 65px;">Rate</th>
                    <th class="text-right" style="width: 40px;">Qty</th>
                    <th class="text-right" style="width: 75px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($event->venues as $vIndex => $venueItem)
                    <tr>
                        <td class="text-start" style="color: #64748b;">{{ $vIndex + 1 }}</td>
                        <td style="font-weight: 600;">{{ $venueItem->venue?->venue_name ?? 'N/A' }}</td>
                        <td class="text-right">P{{ number_format((float) ($venueItem->rate?->amount ?? 0), 2) }}</td>
                        <td class="text-right">{{ $venueItem->qty }}</td>
                        <td class="text-right" style="font-weight: bold;">P{{ number_format((float) $venueItem->total_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- 3. Services & Miscellaneous --}}
    @if($event->services && $event->services->isNotEmpty())
        <div class="section-title">3. Additional Services & Miscellaneous</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 25px;" class="text-center">#</th>
                    <th>Service Name</th>
                    <th class="text-right" style="width: 65px;">Rate</th>
                    <th class="text-right" style="width: 40px;">Qty</th>
                    <th class="text-right" style="width: 75px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($event->services as $sIndex => $serviceItem)
                    <tr>
                        <td class="text-start" style="color: #64748b;">{{ $sIndex + 1 }}</td>
                        <td style="font-weight: 600;">{{ $serviceItem->service?->service_name ?? 'N/A' }}</td>
                        <td class="text-right">P{{ number_format((float) ($serviceItem->price?->amount ?? 0), 2) }}</td>
                        <td class="text-right">{{ $serviceItem->qty }}</td>
                        <td class="text-right" style="font-weight: bold;">P{{ number_format((float) $serviceItem->total_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Financial Summary & Notes --}}
    <table class="summary-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 55%; vertical-align: top; padding-right: 15px;">
                @if($event->notes)
                    <div class="summary-box" >
                        <strong>Special Instructions / Notes:</strong><br>
                        {{ $event->notes }}
                    </div>
                @endif
            </td>
            <td style="width: 45%; vertical-align: top;">
                <div class="summary-box">
                    <strong style="text-transform: uppercase; font-size: 9.5px;">Financial Summary</strong>
                    <table style="width: 100%; margin-top: 4px;" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="color: #64748b;">Catering & Menus:</td>
                            <td class="text-right" style="font-weight: 600;">P{{ number_format((float) $event->menus->sum('total_amount'), 2) }}</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;">Venues & Facilities:</td>
                            <td class="text-right" style="font-weight: 600;">P{{ number_format((float) $event->venues->sum('total_amount'), 2) }}</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;">Services & Misc:</td>
                            <td class="text-right" style="font-weight: 600;">P{{ number_format((float) $event->services->sum('total_amount'), 2) }}</td>
                        </tr>
                        <tr style="border-top: 1px solid #cbd5e1;">
                            <td style="font-weight: bold; padding-top: 4px;">Contract Total:</td>
                            <td class="text-right" style="font-weight: bold; color: #15803d; font-size: 11.5px; padding-top: 4px;">P{{ number_format((float) $event->total_amount, 2) }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Signatures Section (3 COLUMNS ONLY: Prepared By, Reviewed By, Approved By - NO CLIENT CONFORME per user instruction) --}}
    <table class="signatures-table" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="sig-line">{{ $event->preparedBy?->full_name ?? 'Event Coordinator' }}</div>
                <div class="sig-title">Prepared By</div>
                <div style="font-size: 8px; color: #94a3b8;">{{ $event->preparedBy?->position_name ?? 'Event Coordinator' }}</div>
            </td>
            <td>
                <div class="sig-line">{{ $event->reviewedBy?->full_name ?? '_________________________' }}</div>
                <div class="sig-title">Reviewed By</div>
                <div style="font-size: 8px; color: #94a3b8;">{{ $event->reviewedBy?->position_name ?? 'Banquet Supervisor' }}</div>
            </td>
            <td>
                <div class="sig-line">{{ $event->approvedBy?->full_name ?? '_________________________' }}</div>
                <div class="sig-title">Approved By</div>
                <div style="font-size: 8px; color: #94a3b8;">{{ $event->approvedBy?->position_name ?? 'Operations Manager' }}</div>
            </td>
        </tr>
    </table>

    <table style="width: 100%;" class="footer-text">
        <tr>
            <td>{{ $company }} • Banquet Event Order</td>
            <td style="text-align: right;">Generated on {{ now()->format('M d, Y h:i A') }}</td>
        </tr>
    </table>
</body>
</html>
