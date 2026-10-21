@php
    $record = $getRecord();
    $chattels = $record?->chattelCollaterals ?? collect();
    $rems = $record?->realEstateCollaterals ?? collect();
@endphp

@if($chattels->isEmpty() && $rems->isEmpty())
    <div class="text-xs text-gray-500 py-1.5 italic">
        No collateral recorded yet. You can add collaterals in the <b>Collateral Information</b> tab.
    </div>
@else
    <div style="display: flex; flex-direction: column; gap: 14px; width: 100%;">
        @foreach($chattels as $c)
            @php
                $plate = $c->plate_no ?: 'No Plate';
                $desc = $c->vehicle_description ?: '—';
                $year = $c->year_model ?: '—';
                $engine = $c->engine_no ?: '—';
                $chassis = $c->chassis_no ?: '—';
                $mvFile = $c->mv_file_no ?: '—';
                $renDate = 'No Date';
                if ($c->registration_renewal_date instanceof \DateTimeInterface) {
                    $renDate = $c->registration_renewal_date->format('F d, Y');
                } elseif (! empty($c->registration_renewal_date)) {
                    try {
                        $renDate = \Carbon\Carbon::parse($c->registration_renewal_date)->format('F d, Y');
                    } catch (\Throwable $e) {
                        $renDate = (string) $c->registration_renewal_date;
                    }
                }
                $encumbered = $c->encumbered_to ?: 'NEW';
            @endphp
            <div style="border-radius: 12px; border: 1px solid rgba(156, 163, 175, 0.25); background: rgba(156, 163, 175, 0.05); padding: 14px 16px; width: 100%; box-sizing: border-box;">
                {{-- Header with LTO Renewal and Encumbrance Badge --}}
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(156, 163, 175, 0.2); padding-bottom: 8px; margin-bottom: 10px;">
                    <div style="font-size: 12px; color: inherit; opacity: 0.85;">
                        LTO Renewal: <strong style="opacity: 1; font-weight: 700;">{{ $renDate }}</strong>
                    </div>
                    <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                        {{ $encumbered }}
                    </span>
                </div>

                {{-- Specification Lines matching exact user format --}}
                <div style="line-height: 1.65; font-size: 13px; color: inherit;">
                    <div>
                        🚗 <span style="opacity: 0.8;">Make / Brand :</span> <strong>{{ $desc }}</strong>
                    </div>
                    <div style="padding-left: 24px;">
                        <span style="opacity: 0.8;">Year Model :</span> <strong>{{ $year }}</strong>
                    </div>
                    <div style="padding-left: 24px;">
                        <span style="opacity: 0.8;">Engine # :</span> <strong style="font-family: monospace;">{{ $engine }}</strong>
                    </div>
                    <div style="padding-left: 24px;">
                        <span style="opacity: 0.8;">Chassis # :</span> <strong style="font-family: monospace;">{{ $chassis }}</strong>
                    </div>
                    <div style="padding-left: 24px;">
                        <span style="opacity: 0.8;">Plate # :</span> <strong style="font-family: monospace;">{{ $plate }}</strong>
                    </div>
                    <div style="padding-left: 24px;">
                        <span style="opacity: 0.8;">File No. / MV File # :</span> <strong style="font-family: monospace;">{{ $mvFile }}</strong>
                    </div>
                </div>
            </div>
        @endforeach

        @foreach($rems as $r)
            @php
                $titleNo = $r->title_number ?: ($r->tct_oct_no ?: 'No Title Number');
                $owner = $r->title_owner_name ?: ($r->registered_owner ?: '—');
                $loc = $r->location ?: '—';
                $rodBranch = $r->rodBranch?->name ?: '—';
                $remStatus = $r->rem_status ?: 'Active';
            @endphp
            <div style="border-radius: 12px; border: 1px solid rgba(156, 163, 175, 0.25); background: rgba(156, 163, 175, 0.05); padding: 14px 16px; width: 100%; box-sizing: border-box;">
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(156, 163, 175, 0.2); padding-bottom: 8px; margin-bottom: 10px;">
                    <div style="font-size: 13px; font-weight: 700; color: inherit;">
                        🏠 <span>Title # :</span> <strong>{{ $titleNo }}</strong>
                    </div>
                    <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3);">
                        {{ $remStatus }}
                    </span>
                </div>
                <div style="line-height: 1.65; font-size: 13px; color: inherit;">
                    <div>
                        <span style="opacity: 0.8;">Registered Owner :</span> <strong>{{ $owner }}</strong>
                    </div>
                    <div>
                        <span style="opacity: 0.8;">Location :</span> <strong>{{ $loc }}</strong>
                    </div>
                    <div>
                        <span style="opacity: 0.8;">ROD Branch :</span> <strong>{{ $rodBranch }}</strong>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
