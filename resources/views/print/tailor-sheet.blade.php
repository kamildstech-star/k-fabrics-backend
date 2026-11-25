<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tailor Job Sheet & Packing Slip - {{ $order->order_number }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #1A1A1A;
            background: #F9F8F6;
            padding: 24px;
            font-size: 13px;
            line-height: 1.5;
        }

        .no-print-bar {
            max-width: 900px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #1A1A1A;
            color: #FFFFFF;
            padding: 12px 20px;
            border-radius: 4px;
        }

        .btn-print {
            background: #B89B5E;
            color: #FFFFFF;
            border: none;
            padding: 8px 18px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            cursor: pointer;
            border-radius: 2px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-print:hover {
            background: #9d824a;
        }

        .btn-back {
            color: #B89B5E;
            text-decoration: none;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .sheet-container {
            max-width: 900px;
            margin: 0 auto;
            background: #FFFFFF;
            border: 1px solid #E5E5E5;
            padding: 36px 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        }

        .sheet-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1A1A1A;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }

        .brand-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 26px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #1A1A1A;
        }

        .brand-subtitle {
            font-size: 9px;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: #B89B5E;
            margin-top: 2px;
        }

        .doc-badge {
            text-align: right;
        }

        .ticket-number {
            font-family: monospace;
            font-size: 18px;
            font-weight: 700;
            color: #1A1A1A;
            letter-spacing: 0.05em;
        }

        .ticket-date {
            font-size: 11px;
            color: #6B6B6B;
            margin-top: 2px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            background: #F9F8F6;
            border: 1px solid #E5E5E5;
            padding: 16px 20px;
            margin-bottom: 26px;
        }

        .info-block h4 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #B89B5E;
            margin-bottom: 6px;
        }

        .info-block p {
            font-size: 12px;
            color: #1A1A1A;
            margin-bottom: 3px;
        }

        .section-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 16px;
            font-weight: 700;
            color: #1A1A1A;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-bottom: 1px solid #E5E5E5;
            padding-bottom: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-title span.tag {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 10px;
            background: #B89B5E;
            color: #FFFFFF;
            padding: 2px 8px;
            letter-spacing: 0.05em;
        }

        .tailoring-card {
            border: 1px solid #1A1A1A;
            padding: 20px;
            margin-bottom: 24px;
            background: #FFFFFF;
        }

        .tailoring-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #E5E5E5;
        }

        .garment-name {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 16px;
            font-weight: 700;
        }

        .styling-specs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 18px;
            background: #F9F8F6;
            padding: 12px 14px;
            border: 1px solid #E5E5E5;
        }

        .spec-item .label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #6B6B6B;
            display: block;
        }

        .spec-item .value {
            font-size: 12px;
            font-weight: 600;
            color: #1A1A1A;
            margin-top: 2px;
        }

        /* Measurements Table */
        .measurement-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 12px;
        }

        .measurement-table th,
        .measurement-table td {
            border: 1px solid #D5D5D5;
            padding: 8px 10px;
            text-align: left;
        }

        .measurement-table th {
            background: #1A1A1A;
            color: #FFFFFF;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 600;
        }

        .measurement-table td.val {
            font-weight: 700;
            font-size: 13px;
            color: #1A1A1A;
            background: #FFFFFF;
        }

        .reference-img-box {
            display: flex;
            gap: 16px;
            margin-top: 14px;
            padding: 12px;
            border: 1px dashed #B89B5E;
            background: #FAF8F2;
        }

        .reference-img-box img {
            width: 90px;
            height: 120px;
            object-fit: cover;
            border: 1px solid #E5E5E5;
        }

        .special-notes {
            margin-top: 10px;
            padding: 10px 14px;
            background: #FFF9E6;
            border-left: 3px solid #B89B5E;
            font-size: 11px;
            color: #66501F;
        }

        /* Quality Assurance Checklist */
        .qa-checklist {
            margin-top: 20px;
            padding: 14px 18px;
            border: 1px solid #E5E5E5;
            background: #F9F8F6;
        }

        .qa-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 10px;
            font-size: 11px;
        }

        .qa-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .checkbox-square {
            width: 14px;
            height: 14px;
            border: 1.5px solid #1A1A1A;
            display: inline-block;
        }

        .sign-row {
            display: flex;
            justify-content: space-between;
            margin-top: 36px;
            padding-top: 14px;
            border-top: 1px solid #D5D5D5;
        }

        .sign-box {
            width: 220px;
            text-align: center;
        }

        .sign-line {
            border-top: 1px solid #1A1A1A;
            margin-bottom: 4px;
        }

        .sign-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #6B6B6B;
        }

        /* Ready to Wear Items */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 12px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #E5E5E5;
            padding: 8px 12px;
        }

        .items-table th {
            background: #F9F8F6;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #6B6B6B;
        }

        @media print {
            body {
                background: #FFFFFF !important;
                padding: 0 !important;
            }

            .no-print-bar {
                display: none !important;
            }

            .sheet-container {
                border: none !important;
                box-shadow: none !important;
                padding: 10px 0 !important;
            }

            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>
    <div class="no-print-bar">
        <div>
            <strong>K. Fabrics Workshop Job Ticket</strong> &bull; {{ $order->order_number }}
        </div>
        <div style="display: flex; gap: 16px; align-items: center;">
            <a href="javascript:history.back()" class="btn-back">&larr; Back to Admin</a>
            <button class="btn-print" onclick="window.print()">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
                </svg>
                Print Ticket
            </button>
        </div>
    </div>

    <div class="sheet-container">
        <!-- Sheet Header -->
        <div class="sheet-header">
            <div>
                <h1 class="brand-title">K. Fabrics</h1>
                <div class="brand-subtitle">Atelier Master Tailor Sheet & Packing Ticket</div>
            </div>
            <div class="doc-badge">
                <div class="ticket-number">{{ $order->order_number }}</div>
                <div class="ticket-date">Order Date: {{ $order->created_at->format('d M Y, h:i A') }}</div>
            </div>
        </div>

        <!-- Order & Delivery Meta Grid -->
        <div class="info-grid">
            <div class="info-block">
                <h4>Atelier Job</h4>
                <p><strong>Order:</strong> {{ $order->order_number }}</p>
            </div>
            <div class="info-block">
                <h4>Fulfillment & Payment</h4>
                <p><strong>Payment Method:</strong> {{ strtoupper($order->payment_method) }}</p>
                <p><strong>Payment Status:</strong> {{ ucfirst($order->payment_status) }}</p>
                <p><strong>Order Status:</strong> {{ ucfirst($order->order_status) }}</p>
                <p><strong>Total Amount:</strong> Rs. {{ number_format($order->total_amount, 2) }}</p>
            </div>
        </div>

        <!-- Section 1: Bespoke Tailoring Jobs (If Any) -->
        @if($stitchingRequests->isNotEmpty())
            <div class="section-title">
                <span>Atelier Stitching Jobs ({{ $stitchingRequests->count() }})</span>
                <span class="tag">Workshop Priority</span>
            </div>

            @foreach($stitchingRequests as $idx => $stitching)
                <div class="tailoring-card {{ $idx > 0 ? 'page-break' : '' }}">
                    <div class="tailoring-header">
                        <div>
                            <span style="font-size: 10px; color: #B89B5E; text-transform: uppercase; font-weight: 700; letter-spacing: 0.1em;">
                                Job #{{ $idx + 1 }} &bull; {{ strtoupper(str_replace('_', ' ', $stitching->request_type)) }}
                            </span>
                            <div class="garment-name">
                                {{ $stitching->orderItem ? $stitching->orderItem->product_name : ($stitching->stitchingService->name ?? 'Custom Tailoring') }}
                            </div>
                        </div>
                        <div style="text-align: right; font-size: 11px;">
                            <span style="font-weight: 700;">Service:</span> {{ $stitching->stitchingService->name ?? 'Three-Piece Finishing' }}<br>
                            <span style="color: #6B6B6B;">Delivery: {{ ucwords(str_replace('_', ' ', $stitching->fabric_delivery_method)) }}</span>
                        </div>
                    </div>

                    <!-- Cut & Style Specifications -->
                    <div class="styling-specs">
                        <div class="spec-item">
                            <span class="label">Neckline Design</span>
                            <span class="value">{{ $stitching->neck_style ?: 'Standard Master Pattern' }}</span>
                        </div>
                        <div class="spec-item">
                            <span class="label">Sleeve Cut</span>
                            <span class="value">{{ $stitching->sleeve_style ?: 'Straight with Overlock' }}</span>
                        </div>
                        <div class="spec-item">
                            <span class="label">Trouser Silhouette</span>
                            <span class="value">{{ $stitching->trouser_style ?: 'Straight Cigarette Pant' }}</span>
                        </div>
                    </div>

                    <!-- Measurements Table -->
                    @php
                        $m = $stitching->orderMeasurement();
                        $unit = $m ? ($m->unit ?: 'inches') : 'inches';
                    @endphp

                    @if($m)
                        <table class="measurement-table">
                            <thead>
                                <tr>
                                    <th colspan="6">Shirt Measurements ({{ strtoupper($unit) }})</th>
                                </tr>
                                <tr style="background: #F9F8F6; color: #1A1A1A;">
                                    <th>Length</th>
                                    <th>Shoulder</th>
                                    <th>Chest / Bust</th>
                                    <th>Waist</th>
                                    <th>Hips</th>
                                    <th>Sleeve</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="val">{{ $m->shirt_length ?: '-' }}</td>
                                    <td class="val">{{ $m->shoulder ?: '-' }}</td>
                                    <td class="val">{{ $m->chest ?: '-' }}</td>
                                    <td class="val">{{ $m->waist ?: '-' }}</td>
                                    <td class="val">{{ $m->hips ?: '-' }}</td>
                                    <td class="val">{{ $m->sleeve_length ?: '-' }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <table class="measurement-table">
                            <thead>
                                <tr>
                                    <th colspan="3">Trouser / Shalwar Measurements ({{ strtoupper($unit) }})</th>
                                </tr>
                                <tr style="background: #F9F8F6; color: #1A1A1A;">
                                    <th>Length</th>
                                    <th>Waist</th>
                                    <th>Pancha / Bottom</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="val">{{ $m->trouser_length ?: '-' }}</td>
                                    <td class="val">{{ $m->trouser_waist ?: '-' }}</td>
                                    <td class="val">{{ $m->pancha ?: '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    @else
                        <div style="padding: 12px; background: #FFF9E6; border: 1px solid #FFE58F; font-size: 11px; color: #876800; margin-bottom: 14px;">
                            <strong>Note:</strong> Customer opted for physical sample suit pickup. Duplicate dimensions from rider-collected garment.
                        </div>
                    @endif

                    @if($stitching->special_instructions || ($m && $m->extra_notes))
                        <div class="special-notes">
                            <strong>Tailor Instructions / Customer Notes:</strong>
                            <p>{{ $stitching->special_instructions ?: $m->extra_notes }}</p>
                        </div>
                    @endif

                    @if($stitching->design_reference_image && str_starts_with($stitching->design_reference_image, 'stitching_references/'))
                        <div class="reference-img-box">
                            <img src="{{ route('admin.stitching-requests.reference', ['stitchingRequest' => $stitching]) }}" alt="Reference Image">
                            <div style="font-size: 11px;">
                                <strong>Customer Design Reference Attached:</strong>
                                <p style="color: #6B6B6B; margin-top: 4px;">Inspect uploaded neck, sleeve or embroidery reference photo before pattern cutting.</p>
                            </div>
                        </div>
                    @endif

                    <!-- Workshop Quality Check -->
                    <div class="qa-checklist">
                        <strong style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: #1A1A1A;">
                            Workshop Verification & QA Check
                        </strong>
                        <div class="qa-grid">
                            <div class="qa-item"><span class="checkbox-square"></span> Fabric Inspected & Ironed</div>
                            <div class="qa-item"><span class="checkbox-square"></span> Pattern Cut to Measurements</div>
                            <div class="qa-item"><span class="checkbox-square"></span> Neckline & Lace Interfacing</div>
                            <div class="qa-item"><span class="checkbox-square"></span> French Seams & Overlock Done</div>
                            <div class="qa-item"><span class="checkbox-square"></span> Tape Measure Verified</div>
                            <div class="qa-item"><span class="checkbox-square"></span> Steam Pressed & Polybagged</div>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif

        <!-- Section 2: Order Items (Ready-to-Wear / Packing Items) -->
        <div class="section-title">
            <span>Packing Items Breakdown</span>
            <span class="tag">Fulfillment</span>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Item & Silhouette</th>
                    <th>Variant / Size</th>
                    <th>Color</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Unit Price</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->product_name }}</strong>
                        </td>
                        <td>{{ $item->variant_title ?: ($item->size ?: 'Standard') }}</td>
                        <td>{{ $item->productVariant->color ?? ($item->color ?? 'Standard') }}</td>
                        <td style="text-align: center; font-weight: 700;">{{ $item->quantity }}</td>
                        <td style="text-align: right;">Rs. {{ number_format($item->unit_price, 2) }}</td>
                        <td style="text-align: right; font-weight: 600;">Rs. {{ number_format($item->total_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Special Packing Notes (if any) -->
        @if($order->notes)
            <div class="special-notes" style="margin-bottom: 24px;">
                <strong>Order Delivery Notes:</strong> {{ $order->notes }}
            </div>
        @endif

        <!-- Sign-off Signatures -->
        <div class="sign-row">
            <div class="sign-box">
                <div class="sign-line"></div>
                <div class="sign-label">Cutting Master (Ustaad)</div>
            </div>
            <div class="sign-box">
                <div class="sign-line"></div>
                <div class="sign-label">Senior Tailor & Finishing</div>
            </div>
            <div class="sign-box">
                <div class="sign-line"></div>
                <div class="sign-label">Packaging & Courier Dispatch</div>
            </div>
        </div>
    </div>
</body>
</html>
