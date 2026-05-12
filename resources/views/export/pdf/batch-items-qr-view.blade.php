<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 10mm;
            size: A4 portrait;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            font-size: 10px;
            background: white;
        }

        .sheet {
            width: 100%;
            page-break-inside: avoid;
        }

        .header {
            margin-bottom: 15px;
            padding: 10px;
            border-bottom: 2px solid #333;
            page-break-inside: avoid;
        }

        .header h2 {
            margin: 0;
            font-size: 14px;
            text-align: center;
        }

        .batch-info {
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            margin-top: 5px;
        }

        table.sticker-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.sticker-grid td {
            width: 25%;
            padding: 10px 4px;
            vertical-align: top;
            text-align: center;
            page-break-inside: avoid;
            border: 1px dashed #ccc;
        }

        .sticker {
            width: 100%;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .qr-image {
            width: 120px;
            height: 120px;
            display: block;
            margin: 0 auto 6px;
            background: #fff;
        }

        .item-code {
            font-size: 8px;
            line-height: 1.2;
            font-weight: 700;
            word-break: break-word;
            white-space: normal;
            max-width: 100%;
            overflow-wrap: break-word;
        }

        .empty {
            height: 140px;
        }

        .no-items {
            text-align: center;
            padding: 40px;
            font-size: 12px;
            color: #666;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #999;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="sheet">
        {{-- Header Section --}}
        @if($batch)
        <div class="header">
            <h2>Fixed Asset QR Code Stickers</h2>
            <div class="batch-info">
                <span><strong>Batch Reference:</strong> {{ $batch->reference }}</span>
                <span><strong>Items:</strong> {{ count($items) }}</span>
                <span><strong>Date:</strong> {{ now()->format('M d, Y') }}</span>
            </div>
        </div>
        @endif

        {{-- QR Codes Grid --}}
        @if($items->count() > 0)
            <table class="sticker-grid">
                <tbody>
                    @forelse($items->values()->chunk(4) as $row)
                        <tr>
                            @foreach($row as $item)
                                <td>
                                    <div class="sticker">
                                        @if($item->code)
                                            <div class="item-code">{{ $item->item->item_description }}</div>
                                            <img
                                                class="qr-image"
                                                src="data:image/svg+xml;base64,{{ base64_encode(\QrCode::format('svg')->size(300)->generate($item->code)) }}"
                                                alt="QR Code: {{ $item->code }}"
                                            >
                                            <div class="item-code">{{ $item->code }}</div>
                                        @else
                                            <div class="item-code" style="color: #999;">NO CODE</div>
                                        @endif
                                    </div>
                                </td>
                            @endforeach

                            {{-- Empty cells to complete the row --}}
                            @for($i = $row->count(); $i < 4; $i++)
                                <td><div class="empty"></div></td>
                            @endfor
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="no-items">No items to display</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <div class="no-items">
                <p>No batch items found.</p>
            </div>
        @endif

        {{-- Footer Section --}}
        <div class="footer">
            <p>Generated on {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>
    </div>
</body>
</html>
