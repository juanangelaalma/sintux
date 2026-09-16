@php
    /** @var \Modules\Purchasing\Models\PurchaseOrder $order */
    /** @var array<string, mixed>|null $supplier */
    /** @var object|null $branch */
    /** @var string $companyName */
    /** @var string $printedAt */
    /** @var string $printedBy */

    $fmtMoney = fn ($v) => 'Rp. '.number_format((float) $v, 2, ',', '.');
    $fmtDate = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('d F Y') : '-';
    $statusLabel = $order->status instanceof \BackedEnum ? $order->status->label() : (string) $order->status;

    $supplierName = $supplier['name'] ?? null;
    $supplierCompany = $supplier['company_name'] ?? null;
    $supplierEmail = $order->supplier_email ?: ($supplier['email'] ?? null);
    $supplierPhone = $supplier['mobile_phone'] ?? $supplier['telephone'] ?? null;
    $billing = $supplier['billing_address'] ?? null;
    $supplierAddress = $order->billing_address;
    if (! $supplierAddress && is_array($billing)) {
        $supplierAddress = implode(', ', array_filter([
            $billing['detail'] ?? null,
            isset($billing['kelurahan']) ? 'Kel. '.$billing['kelurahan'] : null,
            isset($billing['kecamatan']) ? 'Kec. '.$billing['kecamatan'] : null,
            $billing['kabupaten'] ?? null,
            $billing['provinsi'] ?? null,
        ]));
    }

    $safeNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $order->number);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PO {{ $order->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; color: #111827; margin: 0; padding: 24px; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .company-name { font-size: 20px; font-weight: bold; margin: 0; }
        .company-branch { font-size: 12px; color: #475569; margin: 2px 0 0; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 22px; margin: 0; letter-spacing: 1px; }
        .doc-number { font-size: 13px; font-weight: bold; margin: 4px 0 0; }
        .status { display: inline-block; font-size: 11px; font-weight: bold; border: 1px solid #0e7490; color: #0e7490; border-radius: 4px; padding: 2px 8px; margin-top: 4px; }
        hr.divider { border: 0; border-top: 2px solid #0e7490; margin: 12px 0 16px; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta-table td { vertical-align: top; padding: 0; }
        .info-box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; }
        .info-box h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; margin: 0 0 6px; }
        .info-box p { margin: 2px 0; }
        .items-table { width: 100%; border-collapse: collapse; margin: 8px 0 0; }
        .items-table th { background: #ecfeff; color: #164e63; font-size: 11px; text-transform: uppercase; text-align: left; padding: 8px; border: 1px solid #cffafe; }
        .items-table td { padding: 8px; border: 1px solid #e2e8f0; vertical-align: top; }
        .items-table .num { text-align: right; white-space: nowrap; }
        .items-table .center { text-align: center; }
        .small { font-size: 11px; color: #475569; }
        .totals-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .totals-table td { padding: 0; vertical-align: top; }
        .totals-box { width: 260px; margin-left: auto; }
        .totals-box table { width: 100%; border-collapse: collapse; }
        .totals-box td { padding: 4px 0; }
        .totals-box .grand { border-top: 2px solid #0e7490; font-size: 14px; font-weight: bold; }
        .note-box { border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 6px; padding: 10px 12px; margin-top: 16px; }
        .sign-table { width: 100%; border-collapse: collapse; margin-top: 32px; }
        .sign-table td { text-align: center; vertical-align: top; width: 33.33%; }
        .sign-space { height: 64px; }
        .footer { margin-top: 24px; font-size: 10px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 8px; }
        @media print {
            body { padding: 0; }
        }
        @page { size: A4; margin: 14mm 12mm; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <p class="company-name">{{ $companyName !== '' ? $companyName : 'Purchase Order' }}</p>
                {{-- @if ($branch)
                    <p class="company-branch">{{ $branch->name }} ({{ $branch->code }})</p>
                @endif --}}
            </td>
            <td class="doc-title">
                <h1>PURCHASE ORDER</h1>
                <p class="doc-number">#{{ $order->number }}</p>
                <span class="status">{{ $statusLabel }}</span>
            </td>
        </tr>
    </table>

    <hr class="divider">

    <table class="meta-table">
        <tr>
            <td style="width: 50%; padding-right: 8px;">
                <div class="info-box">
                    <h3>Kepada Supplier</h3>
                    <p><strong>{{ $supplierName ?? 'Supplier #'.$order->supplier_id }}</strong></p>
                    @if ($supplierCompany)
                        <p class="small">{{ $supplierCompany }}</p>
                    @endif
                    @if ($supplierEmail)
                        <p class="small">{{ $supplierEmail }}</p>
                    @endif
                    @if ($supplierPhone)
                        <p class="small">{{ $supplierPhone }}</p>
                    @endif
                    @if ($supplierAddress)
                        <p class="small">{{ $supplierAddress }}</p>
                    @endif
                    @if ($order->supplier_reference)
                        <p class="small">Ref. supplier: {{ $order->supplier_reference }}</p>
                    @endif
                </div>
            </td>
            <td style="width: 50%; padding-left: 8px;">
                <div class="info-box">
                    <h3>Detail Pesanan</h3>
                    <p>Tanggal pesanan: <strong>{{ $fmtDate($order->order_date) }}</strong></p>
                    @if ($order->due_date)
                        <p>Jatuh tempo: <strong>{{ $fmtDate($order->due_date) }}</strong></p>
                    @endif
                    @if ($order->payment_term)
                        <p>Termin: <strong>{{ $order->payment_term }}</strong></p>
                    @endif
                    <p>Mata uang: <strong>{{ $order->currency_code }}</strong></p>
                    @if ($order->tags->isNotEmpty())
                        <p class="small">Tag: {{ $order->tags->pluck('name')->implode(', ') }}</p>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <h3 style="margin: 0 0 4px; font-size: 13px;">Item Pesanan ({{ $order->items->count() }} item)</h3>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 28px;" class="center">No</th>
                <th>Produk</th>
                <th style="width: 60px;" class="center">Qty</th>
                <th style="width: 110px;" class="num">Harga Satuan</th>
                <th style="width: 120px;" class="num">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $item->product_name }}</strong>
                        <div class="small">SKU: {{ $item->sku }}{{ $item->uom_name ? ' - '.$item->uom_name : '' }}</div>
                        @if ($item->description)
                            <div class="small">{{ $item->description }}</div>
                        @endif
                        @php
                            $destLabel = 'Tujuan: '.($item->destinationBranch?->name ?? 'Cabang #'.$item->destination_branch_id);
                            if ($item->destinationWarehouse) {
                                $destLabel .= ' - '.$item->destinationWarehouse->name;
                            }
                        @endphp
                        <div class="small">{{ $destLabel }}</div>
                    </td>
                    <td class="center">{{ rtrim(rtrim(number_format((float) $item->qty_ordered, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="num">{{ $fmtMoney($item->unit_price) }}</td>
                    <td class="num"><strong>{{ $fmtMoney($item->line_total) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>
                @if ($order->note)
                    <div class="note-box" style="margin-top: 12px;">
                        <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b;">Catatan</strong>
                        <p style="margin: 4px 0 0; white-space: pre-wrap;">{{ $order->note }}</p>
                    </div>
                @endif
            </td>
            <td style="width: 280px;">
                <div class="totals-box">
                    <table>
                        <tr>
                            <td class="small">Subtotal</td>
                            <td class="num">{{ $fmtMoney($order->subtotal) }}</td>
                        </tr>
                        <tr>
                            <td class="small">Pajak{{ $order->is_tax_inclusive ? ' (inklusif)' : '' }}</td>
                            <td class="num">{{ $fmtMoney($order->tax_amount) }}</td>
                        </tr>
                        <tr class="grand">
                            <td>Total Pesanan</td>
                            <td class="num">{{ $fmtMoney($order->total) }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="sign-table">
        <tr>
            <td>
                <p>Dibuat oleh,</p>
                <div class="sign-space"></div>
                <p><strong>( ............................ )</strong></p>
            </td>
            <td>
                <p>Disetujui oleh,</p>
                <div class="sign-space"></div>
                <p><strong>( ............................ )</strong></p>
            </td>
            <td>
                <p>Supplier,</p>
                <div class="sign-space"></div>
                <p><strong>( ............................ )</strong></p>
            </td>
        </tr>
    </table>

    <div class="footer">
        Dicetak pada {{ $printedAt }} oleh {{ $printedBy }} · Dokumen {{ $safeNumber }}
    </div>
</body>
</html>
