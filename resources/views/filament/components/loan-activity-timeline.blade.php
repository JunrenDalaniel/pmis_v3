@php
    $record = $getRecord();
    $activities = $record ? \Spatie\Activitylog\Models\Activity::where('subject_type', get_class($record))
        ->where('subject_id', $record->id)
        ->with('causer')
        ->latest()
        ->take(30)
        ->get() : collect();
@endphp

<div style="font-family: inherit; font-size: 13px;">
    @if($activities->isEmpty())
        <div style="padding: 20px; text-align: center; color: #94a3b8; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px;">
            <div style="font-size: 24px; margin-bottom: 4px;">🕒</div>
            <div style="font-weight: 600; color: #475569;">No Activity Logs Yet</div>
            <div style="font-size: 11px; color: #94a3b8;">Subsequent status changes, edits, and file uploads will appear here in chronological order.</div>
        </div>
    @else
        <div style="position: relative; padding-left: 24px;">
            {{-- Vertical timeline line --}}
            <div style="position: absolute; left: 10px; top: 8px; bottom: 8px; width: 2px; background: #e2e8f0;"></div>

            <div style="display: flex; flex-direction: column; gap: 14px;">
                @foreach($activities as $act)
                    @php
                        $dotColor = match($act->event) {
                            'created' => '#10b981',
                            'updated' => '#3b82f6',
                            'deleted' => '#ef4444',
                            default   => '#64748b',
                        };
                        $props = $act->properties ? $act->properties->toArray() : [];
                        $changes = $props['attributes'] ?? [];
                        $old = $props['old'] ?? [];
                    @endphp

                    <div style="position: relative;">
                        {{-- Timeline Bullet --}}
                        <div style="position: absolute; left: -24px; top: 4px; width: 14px; height: 14px; border-radius: 50%; background: {{ $dotColor }}; border: 3px solid #ffffff; box-shadow: 0 0 0 1px #cbd5e1;"></div>

                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                            {{-- Header --}}
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span style="font-weight: 700; color: #0f172a; font-size: 12px;">
                                        {{ $act->causer?->name ?? 'System / Anonymous' }}
                                    </span>
                                    <span style="font-size: 10px; padding: 1px 6px; border-radius: 4px; font-weight: 700; text-transform: uppercase; background: #f1f5f9; color: #475569;">
                                        {{ $act->event ?? 'Event' }}
                                    </span>
                                </div>
                                <span style="font-size: 11px; color: #64748b;">
                                    {{ $act->created_at ? $act->created_at->format('F d, Y h:i A') : '' }}
                                    ({{ $act->created_at ? $act->created_at->diffForHumans() : '' }})
                                </span>
                            </div>

                            <div style="font-size: 12px; color: #334155; margin-top: 4px;">
                                {{ $act->description }}
                            </div>

                            {{-- Device and Branch Context --}}
                            @if(!empty($props['branch']) || !empty($props['ip']) || !empty($props['client_device']))
                                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 5px;">
                                    @if(!empty($props['branch']))
                                        <span style="font-size: 10px; padding: 1px 6px; border-radius: 4px; background: #e0f2fe; color: #0369a1; font-weight: 600;">
                                            🏢 {{ $props['branch'] }}
                                        </span>
                                    @endif
                                    @if(!empty($props['client_device']))
                                        <span style="font-size: 10px; padding: 1px 6px; border-radius: 4px; background: #f1f5f9; color: #475569;" title="{{ $props['user_agent'] ?? '' }}">
                                            💻 {{ $props['client_device'] }}
                                        </span>
                                    @endif
                                    @if(!empty($props['ip']))
                                        <span style="font-size: 10px; font-family: monospace; padding: 1px 6px; border-radius: 4px; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;">
                                            🌐 {{ $props['ip'] }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            {{-- Changed Fields Summary --}}
                            @if(!empty($changes))
                                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #f1f5f9; display: flex; flex-wrap: wrap; gap: 6px;">
                                    @foreach($changes as $k => $newVal)
                                        @if($k !== 'updated_at' && (!isset($old[$k]) || $old[$k] !== $newVal))
                                            <span style="font-size: 10.5px; padding: 2px 6px; border-radius: 4px; background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe;">
                                                <strong>{{ $k }}</strong>:
                                                @if(isset($old[$k]))
                                                    <span style="color: #991b1b; text-decoration: line-through;">{{ is_array($old[$k]) ? json_encode($old[$k]) : (string) $old[$k] }}</span> ➡️
                                                @endif
                                                <span style="color: #166534; font-weight: 600;">{{ is_array($newVal) ? json_encode($newVal) : (string) $newVal }}</span>
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
