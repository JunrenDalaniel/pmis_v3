<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transmittal Slip - {{ $transmittal->transmittal_no }}</title>
    <style>
        @page {
            /* Standard 8.5 x 13 in (Folio / Long) in Landscape */
            size: 13in 8.5in;
            /* 22mm Left Margin prevents physical printer hardware clipping on the feed edge */
            margin: 8mm 10mm 8mm 22mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        html, body {
            width: 100%;
            margin: 0;
            padding: 0;
            background: #ffffff;
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 9px;
            color: #000000;
            line-height: 1.25;
            -webkit-font-smoothing: antialiased;
        }

        /* Safe scaling on print to guarantee 100% visibility on all printers */
        @media print {
            body {
                zoom: 95%;
            }
            .print-actions {
                display: none !important;
            }
        }

        .page-container {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
        }

        /* Screen Action Bar */
        .print-actions {
            margin: 10px 0 14px 0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #1e40af;
            color: #ffffff;
            padding: 8px 18px;
            font-size: 11px;
            font-weight: bold;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid #1e3a8a;
        }

        .btn-success { background: #15803d; border-color: #166534; }

        /* Balanced 3-Column Header Table (20% - 60% - 20% = 100%) */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .header-center {
            text-align: center;
            vertical-align: middle;
        }

        .header-org {
            font-size: 16px;
            font-weight: 900;
            color: #15803d;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.15;
        }

        .header-branch {
            font-size: 12px;
            font-weight: 900;
            color: #000000;
            margin-top: 2px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .header-title {
            font-size: 11.5px;
            font-weight: 900;
            color: #1e3a8a;
            margin-top: 3px;
            letter-spacing: 0.3px;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .header-meta {
            text-align: right;
            vertical-align: top;
            font-size: 9.5px;
            color: #000000;
            line-height: 1.35;
        }

        .badge-control {
            display: inline-block;
            background: #ffffff;
            border: 1.5px solid #000000;
            padding: 2px 6px;
            font-family: Arial, monospace;
            font-weight: 900;
            font-size: 10.5px;
            color: #000000;
            border-radius: 3px;
        }

        /* Main Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8.5px;
            table-layout: fixed;
        }

        .data-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: 900;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #000000;
            padding: 4px 2px;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.1px;
            overflow: hidden;
        }

        .data-table th.sub-th {
            background-color: #172554;
            font-size: 7.5px;
            padding: 2px 1px;
            border: 1px solid #000000;
        }

        .data-table td {
            border: 1px solid #000000;
            padding: 3px 3px;
            vertical-align: middle;
            color: #000000;
            overflow: hidden;
            word-wrap: break-word;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        /* Text & Alignment Helpers */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: 900; }
        .nowrap { white-space: nowrap; }

        .tabular-amt {
            font-weight: 900;
            color: #000000;
            white-space: nowrap;
            font-size: 8.5px;
        }

        .badge-sec {
            display: inline-block;
            font-weight: 900;
            font-size: 8px;
            color: #000000;
        }

        /* Totals Row */
        .totals-row td {
            background-color: #f3f4f6 !important;
            font-weight: 900;
            color: #000000;
            border-top: 1.5px solid #000000;
            border-bottom: 3px double #000000;
            padding: 4px 3px;
            font-size: 9px;
        }

        /* Signatories Table (4 x 25% = 100%) */
        .signatories-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            page-break-inside: avoid;
            table-layout: fixed;
        }

        .signatories-table td {
            width: 25%;
            vertical-align: top;
            padding: 0 6px;
        }

        .sig-cell {
            text-align: center;
        }

        .sig-role {
            font-size: 9px;
            font-weight: 900;
            color: #000000;
            text-transform: uppercase;
            text-align: left;
            margin-bottom: 24px;
        }

        .sig-line {
            border-bottom: 1.5px solid #000000;
            width: 88%;
            margin: 0 auto 3px auto;
        }

        .sig-name {
            font-size: 10px;
            font-weight: 900;
            color: #000000;
            text-transform: uppercase;
            min-height: 12px;
            word-wrap: break-word;
        }

        .sig-title {
            font-size: 8px;
            font-weight: bold;
            color: #1f2937;
            margin-top: 1px;
        }

        .sig-subnote {
            font-size: 7.5px;
            font-weight: bold;
            color: #374151;
            margin-top: 2px;
        }

        /* Bottom Reference & Policy Box */
        .legend-box {
            width: 100%;
            box-sizing: border-box;
            margin-top: 10px;
            padding: 5px 8px;
            border: 1px solid #000000;
            background-color: #fafafa;
            page-break-inside: avoid;
        }

        .legend-title {
            font-size: 8px;
            font-weight: 900;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .legend-grid {
            display: grid;
            grid-template-columns: 1.15fr 1fr 1fr 1.2fr;
            gap: 8px;
            font-size: 7.5px;
            color: #000000;
            line-height: 1.3;
        }

        .legend-col {
            border-right: 1px solid #d1d5db;
            padding-right: 5px;
        }

        .legend-col:last-child {
            border-right: none;
            padding-right: 0;
        }
    </style>
</head>
<body>

    <div class="print-actions">
        <button class="btn" onclick="window.print()">🖨️ Print Transmittal Slip (8.5 x 13 in)</button>
        @if(isset($excelExportUrl))
            <a class="btn btn-success" href="{{ $excelExportUrl }}">📥 Export Official Excel (.xlsx)</a>
        @endif
    </div>

    @php
        $isRem = $transmittal->collateral_type === 'real_estate_mortgage';
        $collateralTitle = $isRem ? 'REAL ESTATE MORTGAGE' : 'CHATTEL MORTGAGE';
        $branchName = strtoupper($transmittal->branchOffice?->name ?? 'ALL BRANCHES');
        $transDate = $transmittal->transmittal_date ? \Carbon\Carbon::parse($transmittal->transmittal_date)->format('F d, Y') : now()->format('F d, Y');
    @endphp

    <div class="page-container">

        {{-- Perfectly Centered Header: ORO INTEGRATED COOPERATIVE / [BRANCH NAME] / TRANSMITTAL ON MORTGAGE PROCESSING --}}
        <table class="header-table">
            <tr>
                <td style="width: 20%;"></td>
                <td style="width: 60%;" class="header-center">
                    <div class="header-org">ORO INTEGRATED COOPERATIVE</div>
                    <div class="header-branch">{{ $branchName }} BRANCH</div>
                    <div class="header-title">TRANSMITTAL ON MORTGAGE PROCESSING ({{ $collateralTitle }})</div>
                </td>
                <td style="width: 20%;" class="header-meta">
                    <div style="font-weight: bold; margin-bottom: 2px;"><strong>DATE:</strong> {{ strtoupper($transDate) }}</div>
                    <div style="margin-bottom: 2px;"><span class="badge-control">{{ $transmittal->transmittal_no }}</span></div>
                    @if($transmittal->courier_details)
                        <div style="font-size: 8px; font-weight: bold;">Courier: {{ $transmittal->courier_details }}</div>
                    @endif
                </td>
            </tr>
        </table>

        {{-- Main 17-Column Data Table: Exact 100% Total Column Percentages with Safe NO. Column Width --}}
        <table class="data-table">
            <colgroup>
                <col style="width: 3.0%;">  {{-- 1. NO. --}}
                <col style="width: 7.0%;">  {{-- 2. Loan Account # --}}
                <col style="width: 5.0%;">  {{-- 3. Granted --}}
                <col style="width: 5.0%;">  {{-- 4. Maturity --}}
                <col style="width: 11.5%;"> {{-- 5. Borrower Name --}}
                <col style="width: 10.0%;"> {{-- 6. Registered Owner --}}
                <col style="width: 7.5%;">  {{-- 7. Loan Amount --}}
                <col style="width: 3.0%;">  {{-- 8. Sec Type --}}
                <col style="width: 4.5%;">  {{-- 9. HO Fee --}}
                <col style="width: 5.0%;">  {{-- 10. BIR DST --}}
                <col style="width: 5.0%;">  {{-- 11. ROD Fee --}}
                <col style="width: 5.0%;">  {{-- 12. LTO Fee --}}
                <col style="width: 4.5%;">  {{-- 13. Misc/Env --}}
                <col style="width: 4.0%;">  {{-- 14. SPA Fee --}}
                <col style="width: 4.0%;">  {{-- 15. Affidavit Fee --}}
                <col style="width: 4.5%;">  {{-- 16. Add'l Exp. --}}
                <col style="width: 6.5%;">  {{-- 17. Total Expense --}}
                <col style="width: 5.0%;">  {{-- 18. Documents Attached (Total: 100.0%) --}}
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2" style="font-size: 8.5px;">NO.</th>
                    <th rowspan="2">LOAN ACCOUNT #</th>
                    <th colspan="2">TERM SCHEDULE</th>
                    <th rowspan="2">BORROWER'S NAME</th>
                    <th rowspan="2">REGISTERED OWNER</th>
                    <th rowspan="2">LOAN AMOUNT (PHP)</th>
                    <th rowspan="2">SEC.</th>
                    <th rowspan="2">HO FEE</th>
                    <th rowspan="2">BIR DST</th>
                    <th rowspan="2">ROD FEE</th>
                    <th rowspan="2">LTO FEE</th>
                    <th rowspan="2">MISC / ENV</th>
                    <th rowspan="2">SPA</th>
                    <th rowspan="2">AFF</th>
                    <th rowspan="2">ADD'L EXP.</th>
                    <th rowspan="2">TOTAL EXP.</th>
                    <th rowspan="2">DOCUMENTS ATTACHED / REMARKS</th>
                </tr>
                <tr>
                    <th class="sub-th">GRANTED</th>
                    <th class="sub-th">MATURITY</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totLoan = 0; $totHo = 0; $totBir = 0; $totRod = 0; $totLto = 0; $totMisc = 0; $totSpa = 0; $totAff = 0; $totAddl = 0; $totExp = 0;
                    $idx = 1;
                @endphp
                @forelse($transmittal->items as $item)
                    @php
                        $parseAmt = fn ($v) => (float) str_replace(',', '', (string) ($v ?? 0));
                        $totLoan += $parseAmt($item->loan_amount ?? 0);
                        $totHo   += $parseAmt($item->ho_processing_fee ?? 0);
                        $totBir  += $parseAmt($item->bir_dst_fee ?? 0);
                        $totRod  += $parseAmt($item->rod_fee ?? 0);
                        $totLto  += $parseAmt($item->lto_fee ?? 0);
                        $totMisc += $parseAmt($item->misc_fee ?? 0);
                        $totSpa  += $parseAmt($item->spa_fee ?? 0);
                        $totAff  += $parseAmt($item->affidavit_fee ?? 0);
                        $totAddl += $parseAmt($item->additional_fee ?? 0);
                        $totExp  += $parseAmt($item->total_expense ?? 0);

                        $formatDate = function ($v) {
                            if (empty($v) || $v === '—') return '—';
                            try {
                                return \Carbon\Carbon::parse($v)->format('Y-m-d');
                            } catch (\Throwable $e) {
                                return (string) $v;
                            }
                        };
                        $granted = $formatDate($item->date_granted ?? null);
                        $mat     = $formatDate($item->maturity_date ?? null);
                    @endphp
                    <tr>
                        <td class="text-center font-bold" style="font-size: 9px; color: #000000;">{{ $idx++ }}</td>
                        <td class="text-center font-bold nowrap" style="font-family: Arial, monospace; font-size: 8.5px;">{{ $item->loan_account_no ?? '—' }}</td>
                        <td class="text-center nowrap font-bold" style="font-size: 8px;">{{ $granted }}</td>
                        <td class="text-center nowrap font-bold" style="font-size: 8px;">{{ $mat }}</td>
                        <td class="font-bold text-left" style="font-size: 8.5px;">{{ $item->borrower_name }}</td>
                        <td class="text-left font-bold" style="font-size: 8.5px;">{{ $item->registered_owner ?: $item->borrower_name }}</td>
                        <td class="text-right tabular-amt">₱{{ number_format($parseAmt($item->loan_amount ?? 0), 2) }}</td>
                        <td class="text-center"><span class="badge-sec">{{ $item->security_type }}</span></td>
                        <td class="text-right tabular-amt">₱{{ number_format($parseAmt($item->ho_processing_fee ?? 0), 2) }}</td>
                        <td class="text-right tabular-amt">₱{{ number_format($parseAmt($item->bir_dst_fee ?? 0), 2) }}</td>
                        <td class="text-right tabular-amt">₱{{ number_format($parseAmt($item->rod_fee ?? 0), 2) }}</td>
                        <td class="text-right tabular-amt">
                            @if($parseAmt($item->lto_fee ?? 0) > 0)
                                ₱{{ number_format($parseAmt($item->lto_fee ?? 0), 2) }}
                            @else
                                <span style="color: #64748b;">—</span>
                            @endif
                        </td>
                        <td class="text-right tabular-amt">₱{{ number_format($parseAmt($item->misc_fee ?? 0), 2) }}</td>
                        <td class="text-right tabular-amt">
                            @if($parseAmt($item->spa_fee ?? 0) > 0)
                                ₱{{ number_format($parseAmt($item->spa_fee ?? 0), 2) }}
                            @else
                                <span style="color: #64748b;">—</span>
                            @endif
                        </td>
                        <td class="text-right tabular-amt">
                            @if($parseAmt($item->affidavit_fee ?? 0) > 0)
                                ₱{{ number_format($parseAmt($item->affidavit_fee ?? 0), 2) }}
                            @else
                                <span style="color: #64748b;">—</span>
                            @endif
                        </td>
                        <td class="text-right tabular-amt">
                            @if($parseAmt($item->additional_fee ?? 0) > 0)
                                ₱{{ number_format($parseAmt($item->additional_fee ?? 0), 2) }}
                            @else
                                <span style="color: #64748b;">—</span>
                            @endif
                        </td>
                        <td class="text-right tabular-amt" style="color: #1e40af;">₱{{ number_format($parseAmt($item->total_expense ?? 0), 2) }}</td>
                        <td style="font-size: 7.5px; font-weight: bold; line-height: 1.25; color: #000000;">{{ $item->documents_attached ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="18" class="text-center" style="padding: 16px; color: #000000; font-size: 9.5px; font-weight: bold;">
                            No loan records included in this transmittal batch.
                        </td>
                    </tr>
                @endforelse

                <tr class="totals-row">
                    <td colspan="6" class="text-right font-bold">TOTAL BATCH ({{ count($transmittal->items) }} {{ count($transmittal->items) === 1 ? 'RECORD' : 'RECORDS' }}):</td>
                    <td class="text-right tabular-amt">₱{{ number_format($totLoan, 2) }}</td>
                    <td></td>
                    <td class="text-right tabular-amt">₱{{ number_format($totHo, 2) }}</td>
                    <td class="text-right tabular-amt">₱{{ number_format($totBir, 2) }}</td>
                    <td class="text-right tabular-amt">₱{{ number_format($totRod, 2) }}</td>
                    <td class="text-right tabular-amt">₱{{ number_format($totLto, 2) }}</td>
                    <td class="text-right tabular-amt">₱{{ number_format($totMisc, 2) }}</td>
                    <td class="text-right tabular-amt">₱{{ number_format($totSpa, 2) }}</td>
                    <td class="text-right tabular-amt">₱{{ number_format($totAff, 2) }}</td>
                    <td class="text-right tabular-amt">₱{{ number_format($totAddl, 2) }}</td>
                    <td class="text-right tabular-amt" style="color: #1e40af;">₱{{ number_format($totExp, 2) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        {{-- Perfectly Aligned 4-Column Signatories Block with Crisp Solid Line --}}
        <table class="signatories-table">
            <tr>
                <td class="sig-cell">
                    <div class="sig-role">PREPARED BY:</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">{{ strtoupper($transmittal->prepared_by_name ?? (auth()->user()?->name ?? 'MORTGAGE DOCUMENT SPECIALIST')) }}</div>
                    <div class="sig-title">Loan Clerk / Mortgage Document Specialist</div>
                    <div class="sig-subnote">(Transmittal Generator & Packager)</div>
                </td>
                <td class="sig-cell">
                    <div class="sig-role">CHECKED BY:</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">{{ !empty($transmittal->checked_by_name) ? strtoupper($transmittal->checked_by_name) : '________________________________' }}</div>
                    <div class="sig-title">Bookkeeper</div>
                    <div class="sig-subnote">(Accounting & Expense Verification)</div>
                </td>
                <td class="sig-cell">
                    <div class="sig-role">APPROVED BY:</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">{{ !empty($transmittal->approved_by_name) ? strtoupper($transmittal->approved_by_name) : '________________________________' }}</div>
                    <div class="sig-title">Branch Manager</div>
                    <div class="sig-subnote">(Branch Operations Head)</div>
                </td>
                <td class="sig-cell">
                    <div class="sig-role">RECEIVED & VERIFIED BY (HO):</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">________________________________</div>
                    <div class="sig-title">Mortgage Assessment Specialist (Head Office)</div>
                    <div class="sig-subnote">Date & Time Received: _________________</div>
                </td>
            </tr>
        </table>

        {{-- Bottom Computation & Guidelines Reference --}}
        <div class="legend-box">
            <div class="legend-title">COOPERATIVE MORTGAGE COMPUTATION RULES & FEE BREAKDOWN</div>
            <div class="legend-grid">
                <div class="legend-col">
                    <strong>1. Statutory Registration:</strong><br>
                    • <strong>REM ROD:</strong> Tiered schedule (≤₱250k: ₱1,615.28 baseline, +bracket tiers)<br>
                    • <strong>CM ROD:</strong> Fixed ₱1,000.00 (online registration payment)<br>
                    • <strong>CM LTO:</strong> ₱1,500.00 / CR (+₱1,500 if with cancellation of prior mortgage)
                </div>
                <div class="legend-col">
                    <strong>2. BIR DST (Form 2000):</strong><br>
                    • Formula: (Loan Amount ÷ 5,000 × ₱20) + ₱20<br>
                    • Standard NIRC mortgage documentary stamp tax compliance for both REM and CM.
                </div>
                <div class="legend-col">
                    <strong>3. Administrative & Ancillary:</strong><br>
                    • <strong>HO Fee:</strong> ₱1,000.00 (REM) | ₱500.00 (CM)<br>
                    • <strong>Misc & Env:</strong> ₱580.00 (REM only; CM is ₱0.00 online)<br>
                    • <strong>SPA & Affidavits:</strong> REM only (₱626.32 / ₱536.32; CM is ₱0.00)<br>
                    • <strong>Add'l:</strong> Food, fuel, travel (REM only; CM is ₱0.00)
                </div>
                <div class="legend-col">
                    <strong>4. Custodial Compliance Notes:</strong><br>
                    • All original titles / collaterals (Titles, Tax Decs, OR/CR, Stencils) must have complete duplicate sets and clearances prior to HO transmission.
                </div>
            </div>
        </div>

    </div>

</body>
</html>