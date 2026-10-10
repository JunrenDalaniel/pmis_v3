<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LTO Registration Renewal Notice — {{ $record->plate_no ?: $record->mv_file_no }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 15mm 15mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: #0f172a;
            font-size: 11.5px;
            line-height: 1.45;
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

        .notice-card {
            max-width: 750px;
            margin: 0 auto;
            background: white;
            padding: 36px 40px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #059669;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .org-name {
            font-size: 13px;
            font-weight: 800;
            color: #059669;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .doc-title {
            font-size: 17px;
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
            padding: 7px 10px;
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

        .badge-danger  { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-success { background: #dcfce7; color: #166534; }

        .letter-body {
            font-size: 12px;
            line-height: 1.6;
            margin: 20px 0;
            color: #1e293b;
        }

        .directive-box {
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 14px;
            border-radius: 4px;
            margin: 18px 0;
            font-size: 11.5px;
        }

        .directive-heading {
            font-size: 11px;
            font-weight: bold;
            color: #92400e;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .signatures-table {
            width: 100%;
            margin-top: 36px;
            font-size: 11px;
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
            .notice-card {
                border: none;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <span style="color: #64748b; font-size: 12px;">LTO Registration Renewal Notice</span>
    <button onclick="window.print()" class="btn-print">🖨️ Print Notice</button>
</div>

<div class="notice-card">
    <table class="header-table">
        <tr>
            <td>
                <div class="org-name">OIC PMIS v3 Portal • Chattel Mortgage Asset Monitoring</div>
                <div class="doc-title">Notice of LTO Registration Renewal</div>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <div style="font-size: 11px; font-weight: bold; color: #0f172a;">Plate: {{ $record->plate_no ?: $record->mv_file_no }}</div>
                <div style="font-size: 10px; color: #64748b;">Date: {{ now()->format('F d, Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="info-label">Borrower Name</td>
            <td style="font-weight: bold; font-size: 13px;">{{ $record->loanApplication?->member?->natural_full_name ?? $record->loanApplication?->member?->full_name ?? '—' }}</td>
            <td class="info-label">Branch Office</td>
            <td style="font-weight: bold;">{{ $record->loanApplication?->branchOffice?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="info-label">Contact Number</td>
            <td>{{ $record->loanApplication?->member?->phone_no ?: ($record->loanApplication?->member?->mobile_no ?: 'No Contact Provided') }}</td>
            <td class="info-label">Loan Account #</td>
            <td style="font-family: monospace; font-weight: bold;">{{ $record->loanApplication?->loan_account_no ?: ($record->loanApplication?->application_no ?: '—') }}</td>
        </tr>
        <tr>
            <td class="info-label">Vehicle Description</td>
            <td><strong>{{ $record->year_model ? "{$record->year_model} " : '' }}{{ $record->vehicle_description ?: 'Motor Vehicle' }}</strong></td>
            <td class="info-label">Plate / MV File No.</td>
            <td style="font-family: monospace; font-weight: bold; color: #059669;">{{ $record->plate_no ?: $record->mv_file_no }}</td>
        </tr>
        <tr>
            <td class="info-label">Engine Number</td>
            <td style="font-family: monospace;">{{ $record->engine_no ?: 'N/A' }}</td>
            <td class="info-label">Chassis Number</td>
            <td style="font-family: monospace;">{{ $record->chassis_no ?: 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-label">Renewal Due Date</td>
            <td style="font-weight: bold; color: #991b1b; font-size: 13px;">
                {{ $record->registration_renewal_date ? $record->registration_renewal_date->format('F d, Y') : 'NO DATE RECORDED' }}
            </td>
            <td class="info-label">Registration Status</td>
            <td>
                @php
                    $renewalDate = $record->registration_renewal_date;
                    $isExpired = $renewalDate && $renewalDate->isPast();
                    $days = $renewalDate ? (int) now()->diffInDays($renewalDate) : null;
                @endphp
                @if(!$renewalDate)
                    <span class="badge badge-warning">No Date Set</span>
                @elseif($isExpired)
                    <span class="badge badge-danger">EXPIRED ({{ $days }} day(s) ago)</span>
                @elseif($days <= 30)
                    <span class="badge badge-warning">DUE SOON ({{ $days }} day(s) left)</span>
                @else
                    <span class="badge badge-success">ACTIVE ({{ $days }} day(s) left)</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="letter-body">
        <p>Dear <strong>{{ $record->loanApplication?->member?->natural_full_name ?? $record->loanApplication?->member?->full_name ?? 'Valued Member' }}</strong>,</p>

        <p>This is a formal reminder regarding your motor vehicle pledged as <strong>Chattel Mortgage Collateral</strong> under Loan Account No. <strong>{{ $record->loanApplication?->loan_account_no ?: $record->loanApplication?->application_no }}</strong> with our <strong>{{ $record->loanApplication?->branchOffice?->name ?? 'OIC' }}</strong> branch.</p>

        <p>Our records indicate that the Land Transportation Office (LTO) official registration for your vehicle described above is due for renewal or has elapsed on <strong>{{ $record->registration_renewal_date ? $record->registration_renewal_date->format('F d, Y') : 'the specified date' }}</strong>.</p>
    </div>

    <div class="directive-box">
        <div class="directive-heading">⚠️ Action Required:</div>
        <div>Please renew the vehicle registration with the Land Transportation Office (LTO) at the earliest opportunity and furnish a copy of the newly validated <strong>Official Receipt (OR) and Certificate of Registration (CR)</strong> to your servicing branch to keep your collateral records in good standing.</div>
    </div>

    <table class="signatures-table">
        <tr>
            <td width="50%">
                <div>Prepared / Reminded By:</div>
                <div class="sig-line"></div>
                <strong>LOAN OFFICER / COLLATERAL SPECIALIST</strong><br>
                <span style="font-size: 10px; color: #64748b;">Credit & Operations Department</span>
            </td>
            <td width="50%">
                <div>Noted & Approved By:</div>
                <div class="sig-line"></div>
                <strong>BRANCH MANAGER</strong><br>
                <span style="font-size: 10px; color: #64748b;">Branch Office Management</span>
            </td>
        </tr>
    </table>
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
