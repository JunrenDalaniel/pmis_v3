@php
    $record = $getRecord();
@endphp

<div class="printable-profile-container space-y-6">
    <style>
        .profile-table {
            width: 100%;
            border-collapse: collapse;
        }
        .profile-table tr {
            border-bottom: 1px solid #e5e7eb;
        }
        .dark .profile-table tr {
            border-bottom: 1px solid #374151;
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
        .dark .label-cell {
            color: #f3f4f6;
        }
        .value-cell {
            color: #374151;
            font-weight: 500;
        }
        .dark .value-cell {
            color: #d1d5db;
        }
        @media print {
            aside,
            header,
            nav,
            .fi-topbar,
            .fi-sidebar,
            .fi-header,
            .fi-header-actions,
            .fi-breadcrumbs,
            .fi-page-header,
            .fi-relation-manager,
            button,
            .no-print {
                display: none !important;
            }

            body,
            main,
            .fi-main,
            .fi-page,
            .fi-content {
                background: white !important;
                color: black !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }

            .printable-profile-container {
                width: 100% !important;
                max-width: 100% !important;
                padding: 24px !important;
                margin: 0 !important;
            }

            .print-document-header {
                display: block !important;
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

    {{-- Print Only Header --}}
    <div class="hidden print:block print-document-header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold uppercase tracking-wider text-emerald-800">OIC PMIS — Member Profile Record</h1>
                <p class="text-xs text-gray-500">Official Membership Identification Document</p>
            </div>
            <div class="text-right text-xs text-gray-500">
                <p>Date Printed: {{ now()->format('F j, Y h:i A') }}</p>
                <p>Member No: <span class="font-semibold text-gray-800">{{ $record->member_no ?? 'N/A' }}</span></p>
            </div>
        </div>
    </div>

    {{-- Main Outer Border Card Container --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6 sm:p-8 shadow-xs space-y-8">
        {{-- SECTION 1: Personal Details --}}
        <div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3 tracking-tight">Personal Details</h3>
            <div class="profile-box border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                <table class="profile-table">
                    <tbody>
                        {{-- Row 1: Name & Branch --}}
                        <tr>
                            <td class="label-cell">Name:</td>
                            <td class="value-cell w-1/2">{{ mb_strtoupper($record->full_name) }}</td>
                            <td class="label-cell w-28">Branch:</td>
                            <td class="value-cell">{{ $record->branchOffice?->name ? 'OIC - ' . mb_strtoupper($record->branchOffice->name) : '—' }}</td>
                        </tr>

                        {{-- Row 2: Complete Address --}}
                        <tr>
                            <td class="label-cell">Address:</td>
                            <td colspan="3" class="value-cell uppercase">{{ $record->address ?: '—' }}</td>
                        </tr>

                        {{-- Row 3: Place of Birth --}}
                        <tr>
                            <td class="label-cell">Place of Birth:</td>
                            <td colspan="3" class="value-cell">{{ $record->place_of_birth ?: '—' }}</td>
                        </tr>

                        {{-- Row 4: Date of Birth & Gender --}}
                        <tr>
                            <td class="label-cell">Date of Birth:</td>
                            <td class="value-cell">{{ $record->birth_date ? $record->birth_date->format('F j, Y') : '—' }}</td>
                            <td class="label-cell">Gender:</td>
                            <td class="value-cell">{{ $record->sex ?: '—' }}</td>
                        </tr>

                        {{-- Row 5: Civil Status & Contact Number --}}
                        <tr>
                            <td class="label-cell">Civil Status:</td>
                            <td class="value-cell">{{ $record->civil_status ?: '—' }}</td>
                            <td class="label-cell">Contact Number:</td>
                            <td class="value-cell">{{ $record->phone ?: '—' }}</td>
                        </tr>

                        {{-- Row 6: TIN & SSS / GSIS --}}
                        <tr>
                            <td class="label-cell">TIN:</td>
                            <td class="value-cell font-mono">{{ $record->tin ?: '—' }}</td>
                            <td class="label-cell">SSS / GSIS:</td>
                            <td class="value-cell font-mono">{{ $record->sss_gsis ?: '—' }}</td>
                        </tr>

                        {{-- Row 7: Remarks --}}
                        <tr>
                            <td class="label-cell">Remarks:</td>
                            <td colspan="3" class="value-cell text-gray-500 dark:text-gray-400">{{ $record->remarks ?: '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- SECTION 2: Employment Details --}}
        <div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3 tracking-tight">Employment Details</h3>
            <div class="profile-box border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                <table class="profile-table">
                    <tbody>
                        {{-- Row 1: Occupation & Office Number --}}
                        <tr>
                            <td class="label-cell">Occupation:</td>
                            <td class="value-cell w-1/2 uppercase">{{ $record->occupation ?: '—' }}</td>
                            <td class="label-cell w-36">Office Number:</td>
                            <td class="value-cell">{{ $record->office_number ?: '—' }}</td>
                        </tr>

                        {{-- Row 2: Name of Employer & Employment Status --}}
                        <tr>
                            <td class="label-cell">Name of Employer:</td>
                            <td class="value-cell uppercase">{{ $record->employer_name ?: '—' }}</td>
                            <td class="label-cell">Employment Status:</td>
                            <td class="value-cell">{{ $record->employment_status ?: '—' }}</td>
                        </tr>

                        {{-- Row 3: Office Address & Email Address --}}
                        <tr>
                            <td class="label-cell">Office Address:</td>
                            <td class="value-cell uppercase">{{ $record->office_address ?: '—' }}</td>
                            <td class="label-cell">Email Address:</td>
                            <td class="value-cell">{{ $record->email ?: '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
