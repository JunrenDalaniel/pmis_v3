@php
    $record = $member ?? null;
    $loans = $record ? $record->loanApplications()->with(['branchOffice'])->latest()->limit(200)->get() : collect();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Record — {{ $record?->full_name ?? 'Record' }} ({{ $record?->member_no ?? 'N/A' }})</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 12mm 12mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f3f4f6;
            color: #111827;
            padding: 24px;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
        }

        .no-print-bar {
            max-width: 900px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-print {
            background-color: #059669;
            color: white;
            border: none;
            padding: 9px 18px;
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
            color: #4b5563;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
        }

        .btn-back:hover {
            text-decoration: underline;
        }

        .sheet {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            padding: 32px 36px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
        }

        .profile-table {
            width: 100%;
            border-collapse: collapse;
        }
        .profile-table tr {
            border-bottom: 1px solid #e5e7eb;
        }
        .profile-table tr:last-child {
            border-bottom: none;
        }
        .profile-table td {
            padding: 11px 16px;
            vertical-align: top;
            font-size: 0.875rem;
            line-height: 1.25rem;
        }
        .label-cell {
            font-weight: 700;
            color: #111827;
            width: 160px;
            white-space: nowrap;
        }
        .value-cell {
            color: #374151;
            font-weight: 500;
        }
        .font-mono { font-family: monospace; }
        .font-bold { font-weight: 700; }
        .font-semibold { font-weight: 600; }
        .uppercase { text-transform: uppercase; }
        .profile-box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .print-document-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #059669;
        }
        .loans-table {
            width: 100%;
            border-collapse: collapse;
        }
        .loans-table thead tr {
            background-color: #f3f4f6;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
        }
        .loans-table thead td {
            padding: 8px 12px;
            border-bottom: 2px solid #d1d5db;
        }
        .loans-table tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }
        .loans-table tbody tr:last-child {
            border-bottom: none;
        }
        .loans-table tbody td {
            padding: 8px 12px;
            font-size: 12px;
            vertical-align: middle;
        }

        @media print {
            aside,
            header,
            nav,
            button,
            .no-print,
            .no-print-bar {
                display: none !important;
            }

            body {
                background: white !important;
                color: black !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .sheet {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                margin: 0 !important;
            }

            .print-document-header {
                border-bottom: 2px solid #059669 !important;
                padding-bottom: 12px !important;
                margin-bottom: 24px !important;
            }

            .profile-box {
                border: 1px solid #9ca3af !important;
                box-shadow: none !important;
                break-inside: avoid;
            }

            .profile-table tr {
                border-bottom: 1px solid #d1d5db !important;
            }

            .label-cell {
                color: #000000 !important;
            }

            .value-cell {
                color: #1f2937 !important;
            }
        }
    </style>
</head>
<body>

    {{-- Screen-only toolbar --}}
    <div class="no-print-bar">
        <div style="display: flex; align-items: center; gap: 10px;">
            <button onclick="window.print()" class="btn-print">
                <svg style="width: 15px; height: 15px; fill: currentColor;" viewBox="0 0 24 24">
                    <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
                </svg>
                Print as PDF / Save as PDF
            </button>
            <span style="font-size: 12px; color: #6b7280;">(Tip: Sa Printer Destination, piliin ang <strong>"Save as PDF"</strong>)</span>
        </div>
        <button type="button" onclick="window.close();" class="btn-back">✕ Isara / Close</button>
    </div>

    @if($record)
    {{-- Document Container --}}
    <div class="sheet">

        {{-- Print Only Header --}}
        <div class="print-document-header">
            <div>
                <h1 style="margin: 0 0 4px 0; font-size: 18px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #065f46;">OIC PMIS — Member Profile Record</h1>
                <p style="margin: 0; font-size: 11px; color: #6b7280;">Official Membership Identification Document</p>
            </div>
            <div style="text-align: right; font-size: 11px; color: #6b7280;">
                <p style="margin: 0 0 2px 0;">Date Printed: {{ now()->format('F j, Y h:i A') }}</p>
                <p style="margin: 0;">Member No: <span style="font-weight: 700; color: #111827;">{{ $record->member_no ?? 'N/A' }}</span></p>
            </div>
        </div>

        {{-- Main Card --}}
        <div style="border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px 32px; background: #fff; margin-bottom: 24px;">

            {{-- SECTION 1: Personal Information --}}
            <div style="margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 style="margin: 0; font-size: 13px; font-weight: 800; color: #111827; text-transform: uppercase; letter-spacing: 0.04em;">Personal Information</h3>
                    @if($record->is_active)
                        <span style="font-size: 11px; font-weight: 700; background-color: #d1fae5; color: #065f46; padding: 2px 10px; border-radius: 9999px;">
                            ✔ Active Member
                        </span>
                    @else
                        <span style="font-size: 11px; font-weight: 700; background-color: #f3f4f6; color: #374151; padding: 2px 10px; border-radius: 9999px;">
                            Inactive
                        </span>
                    @endif
                </div>

                <div class="profile-box">
                    <table class="profile-table">
                        <tbody>
                            {{-- Row 1: Member ID & Full Name --}}
                            <tr>
                                <td class="label-cell">Member ID:</td>
                                <td class="value-cell font-mono font-bold" style="color: #065f46;">{{ $record->member_no }}</td>
                                <td class="label-cell">Full Name:</td>
                                <td class="value-cell font-bold">{{ mb_strtoupper($record->full_name) }}</td>
                            </tr>

                            {{-- Row 2: Date of Birth & Sex --}}
                            <tr>
                                <td class="label-cell">Date of Birth:</td>
                                <td class="value-cell">{{ $record->birth_date ? $record->birth_date->format('F d, Y') : '—' }}</td>
                                <td class="label-cell">Sex / Gender:</td>
                                <td class="value-cell">{{ $record->sex ?: '—' }}</td>
                            </tr>

                            {{-- Row 3: Civil Status & TIN --}}
                            <tr>
                                <td class="label-cell">Civil Status:</td>
                                <td class="value-cell">{{ $record->civil_status ?: '—' }}</td>
                                <td class="label-cell">TIN:</td>
                                <td class="value-cell font-mono font-semibold">{{ $record->tin ?: '—' }}</td>
                            </tr>

                            {{-- Row 4: Email & Phone --}}
                            <tr>
                                <td class="label-cell">Email Address:</td>
                                <td class="value-cell">{{ $record->email ?: '—' }}</td>
                                <td class="label-cell">Phone Number:</td>
                                <td class="value-cell">{{ $record->phone ?: '—' }}</td>
                            </tr>

                            {{-- Row 5: Branch & Address --}}
                            <tr>
                                <td class="label-cell">Assigned Branch:</td>
                                <td class="value-cell font-semibold">{{ $record->branchOffice?->name ? 'OIC - ' . mb_strtoupper($record->branchOffice->name) : '—' }}</td>
                                <td class="label-cell">Complete Address:</td>
                                <td class="value-cell uppercase">{{ $record->address ?: '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- SECTION 2: Loan Applications & Mortgage History --}}
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 style="margin: 0; font-size: 13px; font-weight: 800; color: #111827; text-transform: uppercase; letter-spacing: 0.04em;">Loan Applications &amp; Mortgage History</h3>
                    <span style="font-size: 11px; font-weight: 700; background-color: #dbeafe; color: #1e40af; padding: 2px 10px; border-radius: 9999px;">
                        {{ $loans->count() }} {{ \Illuminate\Support\Str::plural('Record', $loans->count()) }}
                    </span>
                </div>

                @if($loans->isEmpty())
                    <div style="padding: 20px; text-align: center; color: #6b7280; border: 1px dashed #d1d5db; border-radius: 8px; font-size: 12px;">
                        No loan applications or mortgage records found for this member.
                    </div>
                @else
                    <div class="profile-box">
                        <table class="loans-table">
                            <thead>
                                <tr>
                                    <td>Date Filed</td>
                                    <td>Application ID</td>
                                    <td>Branch</td>
                                    <td style="text-align: right;">Requested Amount</td>
                                    <td>Collateral Type</td>
                                    <td>Status</td>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loans as $loan)
                                    <tr>
                                        <td style="color: #374151;">{{ $loan->created_at ? $loan->created_at->format('F d, Y') : '—' }}</td>
                                        <td style="font-family: monospace; font-weight: 700; color: #111827;">{{ $loan->application_no ?? '—' }}</td>
                                        <td style="color: #374151;">{{ $loan->branchOffice?->name ?? '—' }}</td>
                                        <td style="text-align: right; font-weight: 600; color: #111827;">₱ {{ number_format($loan->requested_amount, 2) }}</td>
                                        <td>
                                            @if($loan->collateral_type === 'real_estate_mortgage')
                                                <span style="font-size: 10.5px; font-weight: 600; color: #1e40af; background: #dbeafe; padding: 2px 7px; border-radius: 4px;">
                                                    Real Estate (REM)
                                                </span>
                                            @else
                                                <span style="font-size: 10.5px; font-weight: 600; color: #92400e; background: #fef3c7; padding: 2px 7px; border-radius: 4px;">
                                                    Chattel (CM)
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <span style="font-size: 10.5px; font-weight: 600; color: #374151; background: #f3f4f6; padding: 2px 7px; border-radius: 4px; border: 1px solid #e5e7eb;">
                                                {{ $loan->status ?: ($loan->approval_status ?: '—') }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>{{-- End Main Card --}}

        {{-- Signatures block --}}
        <div style="margin-top: 40px; display: flex; justify-content: space-between; page-break-inside: avoid;">
            <div style="width: 42%; border-top: 1px solid #9ca3af; padding-top: 6px; text-align: center; font-size: 11px; color: #4b5563;">
                <strong style="display: block; font-size: 12px; color: #111827; margin-bottom: 2px;">{{ auth()->user()?->name ?? 'Authorized Officer' }}</strong>
                Prepared / Printed By ({{ auth()->user()?->branchOffice?->name ?? 'OIC Branch' }})
            </div>
            <div style="width: 42%; border-top: 1px solid #9ca3af; padding-top: 6px; text-align: center; font-size: 11px; color: #4b5563;">
                <strong style="display: block; font-size: 12px; color: #111827; margin-bottom: 2px;">{{ $record->full_name }}</strong>
                Member Acknowledgment Signature
            </div>
        </div>

    </div>{{-- End Sheet --}}
    @else
    <div class="sheet" style="text-align: center; padding: 48px; color: #dc2626;">
        <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 8px;">Record Not Found</h2>
        <p style="font-size: 13px; color: #6b7280;">Ang hinahanap na member record ay hindi natagpuan o walang sapat na authorization.</p>
    </div>
    @endif

</body>
</html>
