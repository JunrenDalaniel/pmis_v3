@php
    $properties = $activity->properties ? $activity->properties->toArray() : [];
    $attributes = $properties['attributes'] ?? [];
    $old = $properties['old'] ?? [];

    $allKeys = array_values(array_filter(
        array_unique(array_merge(array_keys($attributes), array_keys($old))),
        fn($k) => $k !== 'updated_at'
    ));
    sort($allKeys);

    $formatLabel = function (string $key): string {
        $labels = [
            'approval_status'    => 'Approval Status',
            'sub_status'         => 'Deficiency / Stage',
            'requested_amount'   => 'Requested Loan Amount',
            'branch_office_id'   => 'Branch Assignment',
            'loan_account_no'    => 'Loan Account Number',
            'date_granted'       => 'Date Granted',
            'maturity_date'      => 'Maturity Date',
            'collateral_type'    => 'Collateral Type',
            'status'             => 'Loan Status',
            'notes'              => 'Remarks / Notes',
            'notary_name'        => 'Notary Public Name',
            'bir_reference_no'   => 'BIR eFPS Reference No',
            'ppsr_receipt_no'    => 'PPSR Receipt No',
            'rod_entry_no'       => 'ROD Entry Number',
            'is_active'          => 'Active Status',
            'member_id'          => 'Borrower Member ID',
        ];
        return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
    };

    $formatValue = function ($key, $val) {
        if (is_null($val) || $val === '') {
            return '<span style="color: #94a3b8; font-style: italic;">none / empty</span>';
        }
        if (is_bool($val)) {
            return $val 
                ? '<span style="display: inline-block; padding: 1px 6px; border-radius: 3px; background: #ecfdf5; color: #059669; font-weight: 700; font-size: 11px;">Yes / True</span>' 
                : '<span style="display: inline-block; padding: 1px 6px; border-radius: 3px; background: #fef2f2; color: #dc2626; font-weight: 700; font-size: 11px;">No / False</span>';
        }
        if (is_array($val)) {
            return '<pre style="margin: 0; font-size: 11px; background: #f8fafc; padding: 4px; border-radius: 4px; border: 1px solid #e2e8f0;">' . e(json_encode($val, JSON_PRETTY_PRINT)) . '</pre>';
        }

        // Amount Formatting
        if (str_contains($key, 'amount') && is_numeric($val)) {
            return '<span style="font-weight: 700; font-family: monospace;">₱' . number_format((float) $val, 2) . '</span>';
        }

        // Special value badges
        if ($val === 'Approved') {
            return '<span style="display: inline-block; padding: 2px 7px; border-radius: 4px; background: #ecfdf5; color: #059669; font-weight: 700;">✅ Approved</span>';
        }
        if ($val === 'Disapproved') {
            return '<span style="display: inline-block; padding: 2px 7px; border-radius: 4px; background: #fef2f2; color: #dc2626; font-weight: 700;">❌ Disapproved</span>';
        }
        if ($val === 'internal_deficiency') {
            return '<span style="color: #b91c1c; font-weight: 600;">🏢 Internal Deficiency (Branch Missing Docs)</span>';
        }
        if ($val === 'external_deficiency') {
            return '<span style="color: #b91c1c; font-weight: 600;">🏛️ External Deficiency (External Agencies)</span>';
        }

        return e((string) $val);
    };

    // Target Subject Details
    $targetName = null;
    $targetSubtitle = null;
    $subject = null;
    if (!empty($activity->subject_type) && class_exists($activity->subject_type)) {
        try {
            $subject = $activity->subject;
        } catch (\Throwable $e) {
            $subject = null;
        }
    }

    if ($subject instanceof \App\Models\LoanApplication) {
        $targetName = "Loan: " . ($subject->application_no ?: "#{$activity->subject_id}");
        $targetSubtitle = "Borrower: " . ($subject->member?->full_name ?? 'N/A');
    } elseif ($subject instanceof \App\Models\Member) {
        $targetName = "Member: " . ($subject->full_name ?: "#{$activity->subject_id}");
        $targetSubtitle = "CID: " . ($subject->cid ?? $subject->member_no ?? 'N/A');
    } else {
        $targetName = class_basename($activity->subject_type ?? 'Record') . ($activity->subject_id ? " #{$activity->subject_id}" : '');
    }
@endphp

<div style="font-family: inherit; font-size: 13px;">
    {{-- Meta Header --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; padding: 14px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 16px;">
        <div>
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: flex; align-items: center; gap: 4px;">
                👤 User / Performed By
            </div>
            <div style="font-weight: 600; color: #0f172a; margin-top: 3px;">
                {{ $activity->causer?->name ?? 'System / Anonymous' }}
            </div>
            @php
                $actRole = $properties['role'] ?? $activity->causer?->role?->name ?? $activity->causer?->roles?->first()?->name;
                if ($actRole === 'super_admin') $actRole = 'Super Admin';
            @endphp
            <div style="font-size: 11px; color: #64748b; margin-top: 3px;">
                @if($actRole)
                    <span style="display: inline-block; padding: 2px 7px; border-radius: 4px; background: {{ $actRole === 'Super Admin' ? '#fee2e2' : '#e0f2fe' }}; color: {{ $actRole === 'Super Admin' ? '#991b1b' : '#0369a1' }}; font-weight: 700; font-size: 10.5px;">{{ $actRole }}</span>
                @elseif($activity->causer?->email)
                    {{ $activity->causer->email }}
                @else
                    <span style="display: inline-block; padding: 1px 6px; border-radius: 3px; background: #f1f5f9; color: #64748b; font-size: 10.5px;">Automated / CLI</span>
                @endif
            </div>
        </div>

        <div>
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: flex; align-items: center; gap: 4px;">
                🎯 Target Record
            </div>
            <div style="font-weight: 700; color: #0f172a; margin-top: 3px;">
                {{ $targetName }}
            </div>
            @if($targetSubtitle)
                <div style="font-size: 11px; color: #475569; font-weight: 500;">
                    {{ $targetSubtitle }}
                </div>
            @endif
        </div>

        <div>
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: flex; align-items: center; gap: 4px;">
                🏢 Branch Office
            </div>
            <div style="font-weight: 600; color: #0f172a; margin-top: 3px;">
                {{ $properties['branch'] ?? 'Head Office / System' }}
            </div>
            <div style="font-size: 11px; color: #64748b;">
                Operational Unit
            </div>
        </div>

        <div>
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: flex; align-items: center; gap: 4px;">
                🌐 IP & Workstation
            </div>
            <div style="font-family: monospace; font-size: 12px; font-weight: 600; color: #0f172a; margin-top: 3px;">
                {{ $properties['ip'] ?? '—' }}
            </div>
            <div style="font-size: 11px; color: #64748b;">
                {{ $properties['client_device'] ?? 'Device' }}
            </div>
        </div>

        <div>
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: flex; align-items: center; gap: 4px;">
                ⚡ Action & Timestamp
            </div>
            <div style="margin-top: 3px; display: flex; align-items: center; gap: 6px;">
                @php
                    $isApproved = str_contains((string) $activity->description, 'APPROVED');
                    $isDisapproved = str_contains((string) $activity->description, 'DISAPPROVED');

                    $badgeBg = match(true) {
                        $isApproved => '#ecfdf5',
                        $isDisapproved => '#fef2f2',
                        $activity->event === 'created' => '#ecfdf5',
                        $activity->event === 'deleted' => '#fef2f2',
                        default => '#eff6ff',
                    };
                    $badgeColor = match(true) {
                        $isApproved => '#059669',
                        $isDisapproved => '#dc2626',
                        $activity->event === 'created' => '#059669',
                        $activity->event === 'deleted' => '#dc2626',
                        default => '#2563eb',
                    };
                    $actionLabel = match(true) {
                        $isApproved => 'Approved',
                        $isDisapproved => 'Disapproved',
                        default => ucfirst($activity->event ?? 'Action'),
                    };
                @endphp
                <span style="display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; background: {{ $badgeBg }}; color: {{ $badgeColor }};">
                    {{ $actionLabel }}
                </span>
                <span style="font-size: 11px; color: #64748b;">
                    {{ $activity->created_at ? $activity->created_at->diffForHumans() : '' }}
                </span>
            </div>
            <div style="font-size: 11px; color: #334155; margin-top: 2px; font-weight: 500;">
                {{ $activity->created_at ? $activity->created_at->format('F d, Y h:i:s A') : 'N/A' }}
            </div>
        </div>
    </div>

    {{-- Activity Note Banner --}}
    @if($activity->description)
        <div style="padding: 10px 14px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 16px;">📝</span>
            <div style="font-size: 12.5px; color: #0369a1; font-weight: 600;">
                {{ $activity->description }}
            </div>
        </div>
    @endif

    {{-- Diff Table --}}
    @if(empty($allKeys))
        <div style="padding: 24px; text-align: center; color: #64748b; background: white; border: 1px dashed #cbd5e1; border-radius: 8px;">
            <div style="font-size: 24px; margin-bottom: 6px;">ℹ️</div>
            <div style="font-weight: 600; color: #1e293b;">No Direct Field Diffs Recorded</div>
            <div style="font-size: 12px; margin-top: 2px;">{{ $activity->description }}</div>
        </div>
    @else
        <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: white;">
            <div style="padding: 10px 14px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #334155; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; display: flex; justify-content: space-between; align-items: center;">
                <span>Detailed Field Changes (Before vs After)</span>
                <span style="font-size: 11px; font-weight: normal; color: #64748b;">{{ count($allKeys) }} field(s) modified</span>
            </div>
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 12px;">
                <thead>
                    <tr style="background: #fafafa; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 10px 12px; font-weight: 600; color: #475569; width: 30%;">Field Name / Label</th>
                        <th style="padding: 10px 12px; font-weight: 600; color: #dc2626; width: 35%;">Previous (Old) Value</th>
                        <th style="padding: 10px 12px; font-weight: 600; color: #16a34a; width: 35%;">Updated (New) Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allKeys as $key)
                        @php
                            $oldVal = $old[$key] ?? null;
                            $newVal = $attributes[$key] ?? null;
                            $hasChanged = ($oldVal !== $newVal);
                        @endphp
                        <tr style="border-bottom: 1px solid #f1f5f9; {{ $hasChanged ? 'background: #fffdf5;' : '' }}">
                            <td style="padding: 10px 12px; vertical-align: top;">
                                <div style="font-weight: 700; color: #1e293b; font-size: 12.5px;">
                                    {{ $formatLabel($key) }}
                                </div>
                                <div style="font-family: monospace; font-size: 10px; color: #94a3b8; margin-top: 1px;">
                                    {{ $key }}
                                </div>
                            </td>
                            <td style="padding: 10px 12px; color: #991b1b; background: {{ isset($old[$key]) ? '#fef2f2' : 'transparent' }}; vertical-align: top;">
                                {!! isset($old[$key]) ? $formatValue($key, $oldVal) : '<span style="color: #94a3b8; font-style: italic;">none / empty</span>' !!}
                            </td>
                            <td style="padding: 10px 12px; color: #166534; background: {{ isset($attributes[$key]) ? '#f0fdf4' : 'transparent' }}; font-weight: {{ $hasChanged ? '600' : 'normal' }}; vertical-align: top;">
                                {!! isset($attributes[$key]) ? $formatValue($key, $newVal) : '<span style="color: #94a3b8; font-style: italic;">none / empty</span>' !!}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
