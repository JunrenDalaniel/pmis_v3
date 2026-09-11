<x-filament-widgets::widget>
    @if ($record)
        @php
            $stages = $this->getStages();
            $currentIndex = $this->getCurrentStageIndex();
            $days = $record->status_changed_at ? (int) now()->diffInDays($record->status_changed_at) : 0;
            $isRed = $days >= 14;
            $isYellow = $days >= 7 && $days < 14;
            $nextStage = $stages[$currentIndex + 1]['name'] ?? null;
        @endphp

        <div style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); width: 100%; margin-bottom: 8px;">
            {{-- Header Row --}}
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #f3f4f6;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; background: #e0e7ff; color: #4338ca;">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">
                            Document Lifecycle Progress
                        </div>
                        <div style="font-size: 13px; font-weight: 700; color: #111827;">
                            Current Stage: <span style="color: #4f46e5;">{{ $record->status }}</span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    {{-- Aging Badge --}}
                    @if ($days >= 7)
                        <div style="padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; background: {{ $isRed ? '#fee2e2' : '#fef3c7' }}; color: {{ $isRed ? '#b91c1c' : '#92400e' }}; display: flex; align-items: center; gap: 4px;">
                            <span>{{ $isRed ? '🔴' : '🟡' }}</span>
                            <span>{{ $days }} days in this stage</span>
                        </div>
                    @else
                        <div style="padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; background: #f3f4f6; color: #4b5563;">
                            ⏱️ {{ $days }} day{{ $days !== 1 ? 's' : '' }} in this stage
                        </div>
                    @endif

                    {{-- Next Step Quick Button --}}
                    @if ($nextStage)
                        <button
                            type="button"
                            wire:click="advanceToStage('{{ addslashes($nextStage) }}')"
                            wire:confirm="Advance document to next stage: '{{ $nextStage }}'?"
                            style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; background: #4f46e5; color: white; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(79, 70, 229, 0.3);"
                            onmouseover="this.style.background='#4338ca';"
                            onmouseout="this.style.background='#4f46e5';"
                        >
                            <span>Advance to {{ $nextStage }}</span>
                            <span style="font-size: 14px;">→</span>
                        </button>
                    @else
                        <span style="padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #dcfce7; color: #15803d;">
                            ✅ Final Stage (Encumbered)
                        </span>
                    @endif
                </div>
            </div>

            {{-- Stepper Track (Horizontal Scrollable on small screens) --}}
            <div style="overflow-x: auto; padding: 8px 4px 12px 4px;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; min-width: 780px; position: relative;">
                    @foreach ($stages as $index => $stage)
                        @php
                            $isCompleted = $index < $currentIndex;
                            $isCurrent   = $index === $currentIndex;
                            $isUpcoming  = $index > $currentIndex;
                        @endphp

                        <div style="display: flex; flex-direction: column; align-items: center; position: relative; flex: 1; text-align: center;">
                            {{-- Connector Line Left --}}
                            @if ($index > 0)
                                <div style="position: absolute; top: 14px; right: 50%; width: 100%; height: 3px; background: {{ $isCompleted || $isCurrent ? '#4f46e5' : '#e5e7eb' }}; z-index: 1;"></div>
                            @endif

                            {{-- Step Circle Button --}}
                            <button
                                type="button"
                                wire:click="advanceToStage('{{ addslashes($stage['name']) }}')"
                                wire:confirm="Move document to '{{ $stage['name'] }}'?"
                                title="Click to move to {{ $stage['name'] }}"
                                style="position: relative; z-index: 2; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; cursor: pointer; transition: all 0.2s; border: 2px solid {{ $isCompleted ? '#16a34a' : ($isCurrent ? '#4f46e5' : '#d1d5db') }}; background: {{ $isCompleted ? '#16a34a' : ($isCurrent ? '#4f46e5' : '#ffffff') }}; color: {{ $isCompleted || $isCurrent ? '#ffffff' : '#6b7280' }}; box-shadow: {{ $isCurrent ? '0 0 0 4px rgba(79, 70, 229, 0.2)' : 'none' }};"
                            >
                                @if ($isCompleted)
                                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                @else
                                    <span>{{ $index + 1 }}</span>
                                @endif
                            </button>

                            {{-- Stage Name Label --}}
                            <div style="margin-top: 8px; font-size: 10px; font-weight: {{ $isCurrent ? '700' : '500' }}; color: {{ $isCurrent ? '#4f46e5' : ($isCompleted ? '#16a34a' : '#6b7280') }}; line-height: 1.2; max-width: 80px;">
                                {{ $stage['name'] }}
                            </div>

                            {{-- Sub-status Pill --}}
                            @if ($isCurrent)
                                <div style="margin-top: 4px; padding: 1px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; text-transform: uppercase; background: #e0e7ff; color: #4338ca; letter-spacing: 0.05em;">
                                    Active
                                </div>
                            @elseif ($isCompleted)
                                <div style="margin-top: 4px; font-size: 9px; color: #16a34a; font-weight: 600;">
                                    ✓ Done
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
