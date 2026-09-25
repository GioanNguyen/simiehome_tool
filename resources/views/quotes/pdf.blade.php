<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Báo giá {{ $quote->quote_code }}</title>
    <style>
        @page { margin: 42px 48px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #1f2937;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #7f9675;
            padding-bottom: 18px;
            margin-bottom: 26px;
        }

        .header .logo {
            height: 56px;
        }

        .header .subtitle {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #57694f;
            margin-top: 10px;
        }

        .info-table {
            width: 100%;
            margin-bottom: 24px;
        }

        .info-table td {
            padding: 5px 0;
            vertical-align: top;
        }

        .info-table .label {
            color: #6b7280;
            width: 110px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        table.items th {
            background: #f4f6f3;
            color: #43523d;
            text-align: left;
            padding: 10px 12px;
            border-bottom: 2px solid #7f9675;
            font-size: 11px;
            text-transform: uppercase;
        }

        table.items td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
        }

        table.items .num { text-align: right; white-space: nowrap; }

        .totals {
            width: 280px;
            margin-left: auto;
            margin-bottom: 24px;
        }

        .totals td {
            padding: 6px 0;
        }

        .totals .label { color: #6b7280; }
        .totals .value { text-align: right; font-weight: bold; white-space: nowrap; }

        .totals .grand-total td {
            border-top: 2px solid #7f9675;
            padding-top: 10px;
            font-size: 15px;
            color: #57694f;
        }

        .notes {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        .notes .label {
            color: #6b7280;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .footer {
            margin-top: 32px;
            padding-top: 14px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
            color: #9ca3af;
            text-align: center;
            line-height: 1.5;
        }

        .badge {
            display: inline-block;
            font-size: 9px;
            color: #43523d;
            background: #f4f6f3;
            border-radius: 3px;
            padding: 2px 6px;
            margin-left: 6px;
        }

        table.items .thumb-cell {
            width: 44px;
        }

        table.items .thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="header">
        @php
            $logoPath = public_path('images/logo.png');
        @endphp
        @if (is_file($logoPath))
            <img class="logo" src="data:image/png;base64,{{ base64_encode(file_get_contents($logoPath)) }}" alt="Simie Home">
        @endif
        <div class="subtitle">BÁO GIÁ</div>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Khách hàng:</td>
            <td>{{ $quote->customer_name ?? '—' }}</td>
            <td class="label">Mã báo giá:</td>
            <td>{{ $quote->quote_code }}</td>
        </tr>
        <tr>
            <td class="label">Số điện thoại:</td>
            <td>{{ $quote->customer_phone ?? '—' }}</td>
            <td class="label">Ngày báo giá:</td>
            <td>{{ $quote->created_at->format('d/m/Y') }}</td>
        </tr>
        @if ($quote->customer_email)
            <tr>
                <td class="label">Email:</td>
                <td colspan="3">{{ $quote->customer_email }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Nhân viên:</td>
            <td colspan="3">{{ $quote->user?->name ?? '—' }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 24px;">STT</th>
                <th class="thumb-cell">Ảnh</th>
                <th>Sản phẩm</th>
                <th>Kích thước</th>
                <th class="num" style="width: 40px;">SL</th>
                <th class="num" style="width: 90px;">Đơn giá</th>
                <th class="num" style="width: 100px;">Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            @php $stt = 1; @endphp
            @foreach ($quote->items->where('role', '!=', 'fee') as $item)
                @php $dataUri = $imageEmbedder->toDataUri($item->image_url); @endphp
                <tr>
                    <td>{{ $stt++ }}</td>
                    <td class="thumb-cell">
                        @if ($dataUri)
                            <img class="thumb" src="{{ $dataUri }}" alt="">
                        @endif
                    </td>
                    <td>
                        {{ $item->product_name ?? '—' }}
                        @if ($item->sku)
                            <br><span style="color:#9ca3af; font-size: 10px;">SKU: {{ $item->sku }}</span>
                        @endif
                        @if ($item->role === 'addon')
                            <span class="badge">Sản phẩm phụ</span>
                        @endif
                        @if ((float) $item->surcharge > 0)
                            <br><span style="color:#6b7280; font-size: 10px;">Thành tiền đã gồm phụ phí {{ number_format((float) $item->surcharge, 0, ',', '.') }} đ</span>
                        @endif
                    </td>
                    <td>{{ $item->length_cm ? $item->length_cm.' cm' : '—' }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ number_format((float) $item->unit_price, 0, ',', '.') }} đ</td>
                    <td class="num">{{ number_format((float) $item->line_total, 0, ',', '.') }} đ</td>
                </tr>
            @endforeach

            @foreach ($quote->items->where('role', 'fee') as $item)
                <tr>
                    <td>{{ $stt++ }}</td>
                    <td class="thumb-cell"></td>
                    <td>{{ $item->product_name }} <span class="badge">Phụ phí</span></td>
                    <td>—</td>
                    <td class="num">1</td>
                    <td class="num">{{ number_format((float) $item->unit_price, 0, ',', '.') }} đ</td>
                    <td class="num">{{ number_format((float) $item->line_total, 0, ',', '.') }} đ</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Tạm tính:</td>
            <td class="value">{{ number_format((float) $quote->subtotal, 0, ',', '.') }} đ</td>
        </tr>
        <tr>
            <td class="label">Phụ phí:</td>
            <td class="value">{{ number_format((float) $quote->fee_total, 0, ',', '.') }} đ</td>
        </tr>
        @if ((float) $quote->discount_total > 0)
            <tr>
                <td class="label">
                    Giảm giá{{ $quote->discount_type === 'percent' ? ' ('.rtrim(rtrim(number_format((float) $quote->discount_value, 2, ',', '.'), '0'), ',').'%)' : '' }}:
                </td>
                <td class="value">- {{ number_format((float) $quote->discount_total, 0, ',', '.') }} đ</td>
            </tr>
        @endif
        <tr class="grand-total">
            <td class="label">TỔNG CỘNG:</td>
            <td class="value">{{ number_format((float) $quote->grand_total, 0, ',', '.') }} đ</td>
        </tr>
    </table>

    @if ($quote->notes)
        <div class="notes">
            <div class="label">Ghi chú</div>
            <div>{{ $quote->notes }}</div>
        </div>
    @endif

    <div class="footer">
        Báo giá được lập tự động bởi hệ thống Simie Home Calculator · In lúc {{ now()->format('d/m/Y H:i') }}<br>
        Báo giá này được tạo từ dữ liệu tại thời điểm lập, có thể thay đổi theo thoả thuận thực tế với khách hàng.
    </div>
</body>
</html>
