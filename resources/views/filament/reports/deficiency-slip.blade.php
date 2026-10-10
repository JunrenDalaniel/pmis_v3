<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deficiency Notice — {{ $record->application_no }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 15mm 15mm;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: #0f172a;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 24px;
            background: #f8fafc;
        }

        .no-print-bar {
            max-width: 750px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-print {
            background-color: #059669;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .slip-card {
            max-width: 750px;
            margin: 0 auto;
            background: white;
            padding: 32px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #dc2626;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .org-name {
            font-size: 13px;
            font-weight: 800;
            color: #059669;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .doc-title {
            font-size: 18px;
            font-weight: 800;
            color: #991b1b;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 11.5px;
        }

        .info-table td {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
        }

        .info-label {
            width: 25%;
            background-color: #f1f5f9;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            font-size: 10px;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-internal { background: #fef3c7; color: #92400e; }
        .badge-external { background: #fee2e2; color: #991b1b; }

        .notes-box {
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 14px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .notes-heading {
            font-size: 11px;
            font-weight: bold;
            color: #92400e;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .notes-content {
            font-size: 12px;
            color: #1e293b;
            white-space: pre-wrap;
        }

        .signatures-table {
            width: 100%;
            margin-top: 36px;
            font-size: 10.5px;
        }

        .sig-line {
            width: 80%;
            border-bottom: 1px solid #0f172a;
            margin-top: 40px;
            margin-bottom: 4px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .no-print-bar { display: none; }
            .slip-card {
                border: none;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <span style="color: #64748b; font-size: 12px;">Compliance Audit Slip</span>
    <button onclick="window.print()" class="btn-print">🖨️ Print Slip</button>
</div>

<div class="slip-card">
    <table class="header-table">
        <tr>
            <td>
                <div class="org-name">OIC PMIS v3 Portal • Internal Audit & Compliance Unit</div>
                <div class="doc-title">Deficiency & Compliance Audit Slip</div>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <div style="font-size: 11px; font-weight: bold; color: #0f172a;">Ref #: {{ $record->application_no }}</div>
                @if($record->loan_account_no)
                    <div style="font-size: 10.5px; font-family: monospace; font-weight: 600; color: #1e293b;">Acct #: {{ $record->loan_account_no }}</div>
                @endif
                <div style="font-size: 10px; color: #64748b;">Printed: {{ now()->format('F d, Y h:i A') }}</div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="info-label">Borrower Name</td>
            <td style="font-weight: bold; font-size: 13px;">{{ $record->member?->natural_full_name ?? $record->member?->full_name ?? '—' }}</td>
            <td class="info-label">Branch Office</td>
            <td style="font-weight: bold;">{{ $record->branchOffice?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="info-label">Date Filed</td>
            <td>{{ $record->created_at ? $record->created_at->format('F d, Y') : '—' }}</td>
            <td class="info-label">Loan Amount</td>
            <td style="font-weight: bold; color: #059669; font-size: 13px;">₱{{ number_format((float)$record->requested_amount, 2) }}</td>
        </tr>
        <tr>
            <td class="info-label">Collateral Type</td>
            <td>{{ str($record->collateral_type)->headline() }}</td>
            <td class="info-label">Pipeline Stage</td>
            <td><strong style="color: #1e40af;">{{ $record->status ?? 'Initial Assessment' }}</strong></td>
        </tr>
        <tr>
            <td class="info-label">Deficiency Classification</td>
            <td colspan="3">
                @if($record->sub_status === 'internal_deficiency')
                    <span class="badge badge-internal">Internal Deficiency</span>
                    <span style="font-size: 10px; color: #64748b; margin-left: 6px;">(Branch document or processing requirement)</span>
                @elseif($record->sub_status === 'external_deficiency')
                    <span class="badge badge-external">External Deficiency</span>
                    <span style="font-size: 10px; color: #64748b; margin-left: 6px;">(Government agency / external compliance requirement)</span>
                @else
                    <span class="badge" style="background: #e2e8f0;">{{ str($record->sub_status)->headline() }}</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="notes-box">
        <div class="notes-heading">⚠️ Audit Findings & Assessment Notes:</div>
        <div class="notes-content">{{ $record->deficiency_notes ?: ($record->notes ?: ($record->internal_deficiency_remarks ?: ($record->external_deficiency_remarks ?: 'No specific remarks recorded.'))) }}</div>
    </div>

    <table class="signatures-table">
        <tr>
            <td width="33%">
                <div>Audited By:</div>
                <div class="sig-line"></div>
                <strong>COMPLIANCE AUDITOR</strong><br>
                <span style="font-size: 9px; color: #64748b;">Internal Audit Unit</span>
            </td>
            <td width="33%">
                <div>Received / Acknowledged By:</div>
                <div class="sig-line"></div>
                <strong>LOAN OFFICER / SPECIALIST</strong><br>
                <span style="font-size: 9px; color: #64748b;">Branch Documentation</span>
            </td>
            <td width="34%">
                <div>Noted By:</div>
                <div class="sig-line"></div>
                <strong>BRANCH MANAGER</strong><br>
                <span style="font-size: 9px; color: #64748b;">Officer-In-Charge</span>
            </td>
        </tr>
    </table>
</div>

@if(request()->has('autoprint'))
<script>
    window.addEventListener('load', function() {
        setTimeout(function() {
            window.focus();
            window.print();
        }, 300);
    });
</script>
@endif

</body>
</html>
