<div style="font-family: inherit; font-size: 13px;">
    {{-- Meta Header Grid --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; padding: 14px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 16px;">
        <div>
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">👤 Staff / User</div>
            <div style="font-weight: 700; color: #0f172a; margin-top: 3px; font-size: 13.5px;">
                {{ $log->user_name ?? 'Unknown / Unregistered' }}
            </div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                {{ $log->user_email ?? '—' }}
            </div>
            @php
                $modalRole = $log->role_name ?? $log->user?->role?->name ?? $log->user?->roles?->first()?->name;
                if ($modalRole === 'super_admin') $modalRole = 'Super Admin';
            @endphp
            @if($modalRole)
                <div style="margin-top: 4px;">
                    <span style="display: inline-block; padding: 2px 7px; border-radius: 4px; background: {{ $modalRole === 'Super Admin' ? '#fee2e2' : '#e0f2fe' }}; color: {{ $modalRole === 'Super Admin' ? '#991b1b' : '#0369a1' }}; font-weight: 700; font-size: 10.5px;">
                        {{ $modalRole }}
                    </span>
                </div>
            @endif
        </div>

        <div>
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">🏢 Branch Assignment</div>
            <div style="font-weight: 600; color: #0f172a; margin-top: 3px;">
                {{ $log->branch_name ?? 'Head Office / System' }}
            </div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                Operational Branch
            </div>
        </div>

        <div>
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">⚡ Session Status</div>
            <div style="margin-top: 4px;">
                @if($log->status === 'active')
                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span> Active Session
                    </span>
                @elseif($log->status === 'logged_out')
                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">
                        ⚪ Logged Out
                    </span>
                @else
                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;">
                        🔴 Failed Login
                    </span>
                @endif
            </div>
            @if($log->failure_reason)
                <div style="font-size: 11px; color: #b91c1c; margin-top: 4px; font-weight: 600;">
                    Reason: {{ $log->failure_reason }}
                </div>
            @endif
        </div>
    </div>

    {{-- Session Timestamps Card --}}
    <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: white; margin-bottom: 16px;">
        <div style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #334155; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em;">
            🕒 Session Lifecycle & Duration
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; padding: 14px 16px;">
            <div>
                <div style="font-size: 11px; font-weight: 600; color: #64748b;">Login Timestamp</div>
                <div style="font-weight: 600; color: #0f172a; margin-top: 2px;">
                    {{ $log->login_at ? $log->login_at->format('F d, Y h:i:s A') : '—' }}
                </div>
                <div style="font-size: 11px; color: #64748b;">
                    {{ $log->login_at ? $log->login_at->diffForHumans() : '' }}
                </div>
            </div>

            <div>
                <div style="font-size: 11px; font-weight: 600; color: #64748b;">Logout Timestamp</div>
                <div style="font-weight: 600; color: #0f172a; margin-top: 2px;">
                    @if($log->logout_at)
                        {{ $log->logout_at->format('F d, Y h:i:s A') }}
                    @elseif($log->is_active)
                        <span style="color: #059669; font-weight: 700;">Currently Logged In</span>
                    @else
                        —
                    @endif
                </div>
                <div style="font-size: 11px; color: #64748b;">
                    {{ $log->logout_at ? $log->logout_at->diffForHumans() : '' }}
                </div>
            </div>

            <div>
                <div style="font-size: 11px; font-weight: 600; color: #64748b;">Total Duration</div>
                <div style="font-weight: 700; color: #1e40af; margin-top: 2px; font-size: 14px;">
                    {{ $log->duration }}
                </div>
                <div style="font-size: 11px; color: #64748b;">
                    Connected session time
                </div>
            </div>
        </div>
    </div>

    {{-- Network & Client Device Footprint --}}
    <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: white;">
        <div style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #334155; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em;">
            🌐 Network & Device Audit Trail
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <tbody>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 10px 14px; font-weight: 600; color: #475569; width: 30%;">IP Address</td>
                    <td style="padding: 10px 14px; font-family: monospace; font-size: 12.5px; font-weight: 600; color: #0f172a;">
                        {{ $log->ip_address ?? '—' }}
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9; background: #fafafa;">
                    <td style="padding: 10px 14px; font-weight: 600; color: #475569;">ISP / Network Provider</td>
                    <td style="padding: 10px 14px; font-weight: 600; color: #0f172a;">
                        <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; background: #f1f5f9; color: #334155; font-size: 11.5px;">
                            📶 {{ $log->isp ?? 'Localhost (Dev)' }}
                        </span>
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 10px 14px; font-weight: 600; color: #475569;">Host / Workstation</td>
                    <td style="padding: 10px 14px; font-family: monospace; color: #334155;">
                        {{ $log->host ?? '—' }}
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 10px 14px; font-weight: 600; color: #475569;">Client Device & OS</td>
                    <td style="padding: 10px 14px; font-weight: 600; color: #0f172a;">
                        {{ $log->device ?? 'Unknown' }}
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9; background: #fafafa;">
                    <td style="padding: 10px 14px; font-weight: 600; color: #475569;">Session ID</td>
                    <td style="padding: 10px 14px; font-family: monospace; font-size: 11px; color: #64748b; word-break: break-all;">
                        {{ $log->session_id ?? '—' }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px 14px; font-weight: 600; color: #475569; vertical-align: top;">Raw User Agent</td>
                    <td style="padding: 10px 14px; font-family: monospace; font-size: 11px; color: #64748b; word-break: break-all; background: #f8fafc;">
                        {{ $log->user_agent ?? '—' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
