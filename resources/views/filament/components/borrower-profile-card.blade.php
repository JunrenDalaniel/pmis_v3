@php
    $memberId = $get('member_id');
    $member = $memberId ? \App\Models\Member::with('branchOffice')->find($memberId) : null;
@endphp

@if($member)
    @php
        $initials = strtoupper(substr($member->first_name ?? '', 0, 1) . substr($member->last_name ?? '', 0, 1));
        if (empty($initials)) {
            $initials = 'MB';
        }
        $phone = $member->phone ?: ($member->mobile_no ?: 'None provided');
        $tin = $member->tin ?: 'None provided';
        $bdate = $member->birth_date ? $member->birth_date->format('F d, Y') : '—';
        $age = $member->birth_date ? ' (' . $member->birth_date->age . ' yrs)' : '';
        $branchName = $member->branchOffice?->name ?? 'Unassigned';
        $civil = $member->civil_status ?: '—';
        $sex = $member->sex ?: '—';
        $addr = $member->address ?: 'No address on record';
        $viewProfileUrl = \App\Filament\Resources\MemberResource::getUrl('view', ['record' => $member]);
    @endphp

    <div style="margin-top: 10px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(8px); padding: 16px; font-family: inherit;">
        
        {{-- Top Header: Avatar, Name, Badges, Action Button --}}
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding-bottom: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
            <div style="display: flex; align-items: center; gap: 12px;">
                {{-- Avatar circle --}}
                <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #0284c7, #0369a1); color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; letter-spacing: 0.5px; box-shadow: 0 2px 8px rgba(2, 132, 199, 0.3); shrink: 0;">
                    {{ $initials }}
                </div>
                
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: inherit; letter-spacing: -0.2px;">
                            {{ $member->full_name }}
                        </h4>
                        <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-family: monospace; font-size: 11px; font-weight: 700; background: rgba(14, 165, 233, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3);">
                            ID: {{ $member->member_no }}
                        </span>
                        @if($member->cid)
                            <span style="display: inline-block; padding: 2px 7px; border-radius: 6px; font-family: monospace; font-size: 11px; font-weight: 500; background: rgba(255, 255, 255, 0.06); color: rgba(255, 255, 255, 0.7); border: 1px solid rgba(255, 255, 255, 0.1);">
                                CID: {{ $member->cid }}
                            </span>
                        @endif
                        @if($member->ppsr_account_no)
                            <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-family: monospace; font-size: 11px; font-weight: 600; background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3);" title="Registered Mortgagor PPSR Account">
                                🛡️ PPSR: {{ $member->ppsr_account_no }}
                            </span>
                        @endif
                    </div>
                    <div style="font-size: 12px; opacity: 0.7; margin-top: 3px;">
                        {{ $sex }} • {{ $civil }} • Born {{ $bdate }}{{ $age }}
                    </div>
                </div>
            </div>

            {{-- Branch Badge & View Profile Link --}}
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3);">
                    🏢 {{ $branchName }}
                </span>
                <a href="{{ $viewProfileUrl }}" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 600; text-decoration: none; background: rgba(255, 255, 255, 0.08); color: inherit; border: 1px solid rgba(255, 255, 255, 0.12); transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'" title="Open full member profile in new tab">
                    Profile ↗
                </a>
            </div>
        </div>

        {{-- Details Grid --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; padding-top: 12px; font-size: 12px;">
            <div style="display: flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, 0.02); padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.04);">
                <span style="font-size: 16px; opacity: 0.85;">📞</span>
                <div>
                    <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.6; font-weight: 600;">Contact Number</div>
                    <div style="font-weight: 600; margin-top: 1px;">{{ $phone }}</div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, 0.02); padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.04);">
                <span style="font-size: 16px; opacity: 0.85;">📑</span>
                <div>
                    <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.6; font-weight: 600;">Tax ID (TIN)</div>
                    <div style="font-weight: 600; font-family: monospace; margin-top: 1px;">{{ $tin }}</div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, 0.02); padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.04);">
                <span style="font-size: 16px; opacity: 0.85;">✉️</span>
                <div style="min-width: 0;">
                    <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.6; font-weight: 600;">Email Address</div>
                    <div style="font-weight: 600; margin-top: 1px; word-break: break-all;">{{ $member->email ?: 'None provided' }}</div>
                </div>
            </div>
        </div>

        {{-- Address Full Width --}}
        <div style="display: flex; align-items: flex-start; gap: 8px; background: rgba(255, 255, 255, 0.02); padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.04); margin-top: 8px; font-size: 12px;">
            <span style="font-size: 16px; opacity: 0.85; margin-top: 1px;">📍</span>
            <div>
                <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.6; font-weight: 600;">Complete Address</div>
                <div style="font-weight: 500; margin-top: 2px; line-height: 1.4;">{{ $addr }}</div>
            </div>
        </div>

    </div>
@endif
