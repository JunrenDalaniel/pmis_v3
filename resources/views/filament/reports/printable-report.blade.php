<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $data['title'] }} — OIC PMIS v3</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm 12mm 12mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            padding: 24px;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
        }

        .no-print-bar {
            max-width: 1100px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-print {
            background-color: #059669;
            color: white;
            border: none;
            padding: 9px 20px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        }

        .btn-print:hover {
            background-color: #047857;
        }

        .btn-back {
            color: #475569;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-back:hover {
            color: #0f172a;
        }

        .report-card {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            padding: 28px 32px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #059669;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .header-title {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 4px 0;
        }

        .header-subtitle {
            font-size: 12px;
            color: #059669;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            background-color: #f1f5f9;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 18px;
            font-size: 11px;
        }

        .meta-item strong {
            color: #475569;
            font-size: 10px;
            text-transform: uppercase;
            display: block;
            margin-bottom: 2px;
        }

        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .kpi-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #059669;
            padding: 10px 12px;
            border-radius: 4px;
        }

        .kpi-label {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .kpi-val {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
            margin-bottom: 24px;
        }

        table.data-table th {
            background-color: #15803d;
            color: white;
            font-weight: 700;
            text-align: left;
            padding: 7px 8px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid #15803d;
        }

        table.data-table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }

        table.data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        table.data-table tfoot,
        tfoot {
            display: table-row-group !important;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        table.data-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .badge-status {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-green { background: #dcfce7; color: #166534; }
        .badge-blue  { background: #dbeafe; color: #1e40af; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-red   { background: #fee2e2; color: #991b1b; }

        .signatory-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 36px;
            padding-top: 20px;
            border-top: 1px dashed #cbd5e1;
            page-break-inside: avoid;
        }

        .sign-box {
            text-align: center;
        }

        .sign-box .role {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 40px;
        }

        .sign-box .line {
            border-bottom: 1px solid #334155;
            margin: 0 20px 4px 20px;
        }

        .sign-box .name {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
        }

        .sign-box .title {
            font-size: 9.5px;
            color: #64748b;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .no-print-bar {
                display: none;
            }
            .report-card {
                border: none;
                box-shadow: none;
                padding: 0;
            }
            table.data-table tfoot,
            tfoot {
                display: table-row-group !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            table.data-table tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <a href="{{ url('/admin/reports') }}" class="btn-back">
        ← Bumalik sa Reports Center
    </a>
    <button onclick="window.print()" class="btn-print">
        🖨️ Print / Save as PDF
    </button>
</div>

<div class="report-card">
    <table class="header-table">
        <tr>
            <td>
                <div class="header-subtitle">OIC PMIS v3 Portal • Management Information System</div>
                <h1 class="header-title">{{ $data['title'] }}</h1>
                <div style="font-size: 11px; color: #64748b;">{{ $data['description'] }}</div>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <div style="font-size: 10px; color: #64748b;">Confidential & Proprietary</div>
                <div style="font-size: 11px; font-weight: 700; color: #0f172a;">Run Date: {{ $data['metadata']['generated_at'] }}</div>
            </td>
        </tr>
    </table>

    <div class="meta-grid">
        <div class="meta-item">
            <strong>Coverage Period</strong>
            <span>{{ $data['metadata']['date_label'] }}</span>
        </div>
        <div class="meta-item">
            <strong>Branch Office</strong>
            <span>{{ $data['metadata']['branch_label'] }}</span>
        </div>
        <div class="meta-item">
            <strong>Collateral Scope</strong>
            <span>{{ $data['metadata']['collateral_label'] }}</span>
        </div>
        @if(!empty($data['metadata']['approval_label']) && $data['metadata']['approval_label'] !== 'All Statuses')
        <div class="meta-item">
            <strong>Approval Scope</strong>
            <span>{{ $data['metadata']['approval_label'] }}</span>
        </div>
        @endif
        <div class="meta-item">
            <strong>Prepared By</strong>
            <span>{{ $data['metadata']['generated_by'] }}</span>
        </div>
    </div>

    @if(!empty($data['kpis']))
        <div class="kpi-row">
            @foreach($data['kpis'] as $kpi)
                <div class="kpi-box">
                    <div class="kpi-label">{{ $kpi['label'] }}</div>
                    <div class="kpi-val">{{ $kpi['value'] }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <table class="data-table">
        <thead>
            <tr>
                @foreach($data['headers'] as $header)
                    @php
                        $isCenter = in_array($header, ['#', 'Date Filed', 'Loan Account', 'Collateral', 'Type', 'Deficiency Classification', 'Classification', 'Pipeline Stage', 'OIC Branch', 'ROD Status', 'Pending Days', 'Last Updated', 'Approval Status', 'LTO Renewal Date', 'Renewal Date', 'LTO Status', 'Days Left / Overdue', 'Plate / MV File No.', 'Plate #']);
                        $isAmount = str_contains($header, 'Amount') || str_contains($header, '₱') || str_contains($header, 'Volume');
                    @endphp
                    <th style="{{ $isAmount ? 'text-align: right;' : ($isCenter ? 'text-align: center;' : 'text-align: left;') }}">
                        {{ $header }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($data['rows'] as $row)
                <tr>
                    @foreach($data['headers'] as $colIdx => $hText)
                        @php
                            $cell = $row[$hText] ?? ($row[$colIdx] ?? '—');
                            $isAmount = str_contains($hText, 'Amount') || str_contains($hText, '₱') || str_contains($hText, 'Volume');
                            $isCenter = in_array($hText, ['#', 'Date Filed', 'Loan Account', 'Collateral', 'Type', 'Deficiency Classification', 'Classification', 'Pipeline Stage', 'OIC Branch', 'ROD Status', 'Pending Days', 'Last Updated', 'Approval Status', 'LTO Renewal Date', 'Renewal Date', 'LTO Status', 'Days Left / Overdue', 'Plate / MV File No.', 'Plate #']);
                            $isMono = in_array($hText, ['Loan Account', 'EPEB Entry #']);
                        @endphp
                        @if($isAmount && is_numeric($cell))
                            <td style="text-align: right; font-family: monospace; font-weight: 600;">
                                ₱{{ number_format((float) $cell, 2) }}
                            </td>
                        @elseif($isMono)
                            <td style="text-align: center; font-family: monospace; font-weight: 600;">
                                {{ $cell }}
                            </td>
                        @else
                            <td style="{{ $isCenter ? 'text-align: center;' : 'text-align: left;' }}">
                                @if(is_string($cell) && in_array(strtoupper($cell), ['APPROVED', 'SECURED', 'ACTIVE', 'ON TRACK', 'RELEASING', 'RELEASED', 'CLOSED']))
                                    <span class="badge-status badge-green">{{ $cell }}</span>
                                @elseif(is_string($cell) && in_array(strtoupper($cell), ['DELAYED', 'DUE SOON', 'ON-PROCESS', 'ENCODING', 'EXAMINATION', 'INTERNAL DEFICIENCY', 'CM']))
                                    <span class="badge-status badge-amber">{{ $cell }}</span>
                                @elseif(is_string($cell) && in_array(strtoupper($cell), ['PAYMENT', 'SCAN/UPLOADING', 'PRINTING', 'REM']))
                                    <span class="badge-status badge-blue">{{ $cell }}</span>
                                @elseif(is_string($cell) && in_array(strtoupper($cell), ['CRITICAL', 'EXPIRED', 'EXTERNAL DEFICIENCY']))
                                    <span class="badge-status badge-red">{{ $cell }}</span>
                                @else
                                    {{ $cell }}
                                @endif
                            </td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($data['headers']) }}" style="text-align: center; padding: 24px; color: #64748b;">
                        Walang natagpuang records para sa napiling criteria at petsa.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if(!empty($data['rows']))
            <tfoot>
                <tr style="background: #f1f5f9; font-weight: 700; border-top: 2px solid #334155;">
                    @foreach($data['headers'] as $cellIdx => $hText)
                        @php
                            $isAmount = str_contains($hText, 'Amount') || str_contains($hText, '₱') || str_contains($hText, 'Volume');
                            $isCount = in_array($hText, [
                                'Chattel Mortgage (CM)', 'Real Estate Mortgage (REM)',
                                'ENCUMBERED CM', 'ENCUMBERED REM',
                                'STILL ON PROCESS CM', 'STILL ON PROCESS REM',
                                'Deficiency CM', 'Deficiency REM'
                            ]);
                            $colSum = 0;
                            if ($isAmount || $isCount) {
                                foreach ($data['rows'] as $r) {
                                    $val = $r[$hText] ?? ($r[$cellIdx] ?? 0);
                                    if (is_numeric($val)) {
                                        $colSum += (float) $val;
                                    }
                                }
                            }
                        @endphp
                        @if($cellIdx === 0)
                            <td style="font-weight: 800; text-transform: uppercase; text-align: center;">TOTAL</td>
                        @elseif($cellIdx === 1)
                            <td style="font-weight: 700; color: #475569; font-size: 10px; text-align: center;">
                                {{ count($data['rows']) }} record(s)
                            </td>
                        @elseif($isAmount)
                            <td style="text-align: right; font-family: monospace; font-weight: 800; color: #166534;">
                                ₱{{ number_format($colSum, 2) }}
                            </td>
                        @elseif($isCount)
                            <td style="text-align: center; font-weight: 800; color: #0f172a;">
                                {{ number_format($colSum) }}
                            </td>
                        @elseif(str_contains($hText, 'Rate'))
                            @php
                                $totalEnc = 0;
                                $totalLoans = 0;
                                foreach ($data['rows'] as $r) {
                                    $totalLoans += ($r[1] ?? 0) + ($r[2] ?? 0);
                                    $totalEnc += ($r[4] ?? 0) + ($r[5] ?? 0);
                                }
                                $avgRate = $totalLoans > 0 ? round(($totalEnc / $totalLoans) * 100, 1) : 0.0;
                            @endphp
                            <td style="text-align: center; font-weight: 800; color: #1e40af;">
                                {{ $avgRate }}%
                            </td>
                        @else
                            <td></td>
                        @endif
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="signatory-grid">
        <div class="sign-box">
            <div class="role">Prepared By:</div>
            <div class="line"></div>
            <div class="name">{{ $data['metadata']['generated_by'] }}</div>
            <div class="title">Loan Operations Officer / Specialist</div>
        </div>
        <div class="sign-box">
            <div class="role">Verified & Checked By:</div>
            <div class="line"></div>
            <div class="name">Compliance & Audit Officer</div>
            <div class="title">Internal Audit & Risk Unit</div>
        </div>
        <div class="sign-box">
            <div class="role">Approved By:</div>
            <div class="line"></div>
            <div class="name">Branch Manager / Executive Director</div>
            <div class="title">Executive Management</div>
        </div>
    </div>
</div>

@if(request()->has('autoprint'))
<script>
    (function() {
        let hasPrinted = false;
        function doPrint() {
            if (hasPrinted) return;
            hasPrinted = true;
            try {
                window.focus();
                window.print();
            } catch(e) {}
        }

        if (document.readyState === 'complete') {
            setTimeout(doPrint, 300);
        } else {
            window.addEventListener('load', function() {
                setTimeout(doPrint, 300);
            });
        }
    })();
</script>
@endif
</body>
</html>
