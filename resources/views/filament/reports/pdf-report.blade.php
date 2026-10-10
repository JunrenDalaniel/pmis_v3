<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $data['title'] ?? 'EPEB Status Report' }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm 12mm 12mm;
        }

        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 9pt;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #059669;
            margin-bottom: 10px;
            padding-bottom: 6px;
        }

        .org-title {
            font-size: 14pt;
            font-weight: bold;
            color: #059669;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .report-title {
            font-size: 11pt;
            font-weight: bold;
            color: #0f172a;
            margin: 2px 0 0 0;
            text-transform: uppercase;
        }

        .meta-table {
            width: 100%;
            background-color: #f1f5f9;
            border-radius: 4px;
            margin-bottom: 12px;
            padding: 6px 10px;
            font-size: 8pt;
        }

        .meta-table td {
            padding: 2px 6px;
            vertical-align: top;
        }

        .meta-label {
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
        }

        .kpi-table {
            width: 100%;
            margin-bottom: 12px;
            border-spacing: 6px;
            border-collapse: separate;
        }

        .kpi-box {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-left: 3px solid #059669;
            padding: 6px 10px;
            border-radius: 4px;
            text-align: left;
        }

        .kpi-label {
            font-size: 7.5pt;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
        }

        .kpi-value {
            font-size: 12pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-bottom: 16px;
        }

        .data-table th {
            background-color: #059669;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 5px 6px;
            border: 1px solid #047857;
            text-transform: uppercase;
            font-size: 7.5pt;
        }

        .data-table td {
            padding: 4px 6px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }

        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .data-table tfoot,
        tfoot {
            display: table-row-group;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .data-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-semibold { font-weight: bold; }

        .badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 7.5pt;
            font-weight: bold;
        }

        .badge-rem { background-color: #e0e7ff; color: #3730a3; }
        .badge-status { background-color: #fef3c7; color: #92400e; }
        .badge-released { background-color: #d1fae5; color: #065f46; }

        .signatures-table {
            width: 100%;
            margin-top: 24px;
            font-size: 8.5pt;
        }

        .signature-line {
            width: 80%;
            border-bottom: 1px solid #0f172a;
            margin-top: 35px;
            margin-bottom: 4px;
        }

        .footer-note {
            margin-top: 14px;
            font-size: 7.5pt;
            color: #94a3b8;
            text-align: right;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td>
                <div class="org-title">OIC PMIS v3 — MANAGEMENT REPORT</div>
                <div class="report-title">{{ $data['title'] ?? 'EPEB Status Report' }}</div>
            </td>
            <td class="text-right" style="vertical-align: bottom;">
                <span style="font-size: 8pt; color: #64748b;">Generated: {{ $data['metadata']['generated_at'] ?? now()->format('M d, Y h:i A') }}</span>
            </td>
        </tr>
    </table>

    {{-- Filter Metadata --}}
    <table class="meta-table">
        <tr>
            <td width="25%"><span class="meta-label">ROD Branch:</span> {{ $data['metadata']['rod_label'] ?? 'All Branches' }}</td>
            <td width="25%"><span class="meta-label">OIC Branch:</span> {{ $data['metadata']['branch_label'] ?? 'All Allowed' }}</td>
            <td width="25%"><span class="meta-label">Collateral Type:</span> {{ $data['metadata']['collateral_label'] ?? 'All REM Types' }}</td>
            <td width="25%"><span class="meta-label">Coverage Year:</span> {{ $data['metadata']['year_label'] ?? 'All Years' }}</td>
        </tr>
        <tr>
            <td><span class="meta-label">ROD Stage:</span> {{ $data['metadata']['rod_status_label'] ?? 'All Statuses' }}</td>
            <td><span class="meta-label">Generated By:</span> {{ $data['metadata']['generated_by'] ?? 'System User' }}</td>
            <td colspan="2"><span class="meta-label">System Scope:</span> Electronic Primary Entry Book Tracking & Pending Encumbrances</td>
        </tr>
    </table>

    {{-- KPIs --}}
    @if(!empty($data['kpis']))
    <table class="kpi-table">
        <tr>
            @foreach($data['kpis'] as $kpi)
            <td class="kpi-box">
                <div class="kpi-label">{{ $kpi['label'] }}</div>
                <div class="kpi-value">{{ $kpi['value'] }}</div>
            </td>
            @endforeach
        </tr>
    </table>
    @endif

    {{-- Data Table --}}
    <table class="data-table">
        <thead>
            <tr>
                @foreach($data['headers'] as $header)
                    <th class="{{ in_array($header, ['#', 'Date Filed', 'Type', 'Pending Days']) ? 'text-center' : (str_contains($header, 'Amount') ? 'text-right' : '') }}">
                        {{ $header }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($data['rows'] as $row)
            <tr>
                @foreach($data['headers'] as $header)
                    @php
                        $val = $row[$header] ?? '—';
                        $isAmount = str_contains($header, 'Amount');
                        $isCenter = in_array($header, ['#', 'Date Filed', 'Type', 'Pending Days']);
                    @endphp
                    <td class="{{ $isAmount ? 'text-right font-semibold' : ($isCenter ? 'text-center' : '') }}">
                        @if($isAmount)
                            ₱{{ number_format((float)$val, 2) }}
                        @elseif($header === 'Type')
                            <span class="badge badge-rem">{{ $val }}</span>
                        @elseif($header === 'ROD Status')
                            <span class="badge badge-status">{{ $val }}</span>
                        @elseif($header === 'Pending Days' && $val === 'Released')
                            <span class="badge badge-released">{{ $val }}</span>
                        @else
                            {{ $val }}
                        @endif
                    </td>
                @endforeach
            </tr>
            @empty
            <tr>
                <td colspan="{{ count($data['headers']) }}" class="text-center" style="padding: 16px; color: #64748b;">
                    No electronic primary entry book records found for the selected filters.
                </td>
            </tr>
            @endforelse
        </tbody>
        @if(!empty($data['rows']))
        <tfoot>
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="4" class="text-right">TOTAL ({{ count($data['rows']) }} Records):</td>
                @php
                    $totalLoan = array_sum(array_column($data['rows'], 'Loan Amount'));
                @endphp
                <td class="text-right font-semibold" style="color: #059669;">₱{{ number_format($totalLoan, 2) }}</td>
                <td colspan="{{ count($data['headers']) - 5 }}"></td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- Signatures --}}
    <table class="signatures-table">
        <tr>
            <td width="33%">
                <div>Prepared By:</div>
                <div class="signature-line"></div>
                <strong>{{ $data['metadata']['generated_by'] ?? 'Records Custodian' }}</strong><br>
                <span style="font-size: 7.5pt; color: #64748b;">ROD Documentation Specialist</span>
            </td>
            <td width="33%">
                <div>Checked By:</div>
                <div class="signature-line"></div>
                <strong>OPERATIONS SUPERVISOR</strong><br>
                <span style="font-size: 7.5pt; color: #64748b;">Branch Operations Officer</span>
            </td>
            <td width="34%">
                <div>Noted & Approved By:</div>
                <div class="signature-line"></div>
                <strong>BRANCH MANAGER</strong><br>
                <span style="font-size: 7.5pt; color: #64748b;">Officer-In-Charge</span>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Confidential — Generated from OIC PMIS v3 Portal • Registry of Deeds Encumbrance Monitoring
    </div>
</body>
</html>
