<x-filament-panels::page>
    <div class="space-y-6">

        {{-- 1. Parameters & Scope Filter Section (Collapsible) --}}
        <x-filament::section compact collapsible>
            <x-slot name="heading">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <x-filament::icon icon="heroicon-o-funnel" class="w-5 h-5 text-primary-500" />
                    <span>Parameters & Scope</span>
                </div>
            </x-slot>

            <x-slot name="headerEnd">
                <x-filament::button
                    type="button"
                    wire:click="resetFilters"
                    color="gray"
                    size="sm"
                    icon="heroicon-o-arrow-path"
                >
                    Reset Filters
                </x-filament::button>
            </x-slot>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; align-items: flex-end;">
                {{-- Date Coverage Preset --}}
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                        Date Coverage Preset
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="date_preset">
                            <option value="all">🌐 All Record</option>
                            <option value="this_month">🗓️ This Month</option>
                            <option value="last_month">⏮️ Last Month</option>
                            <option value="midyear">🌓 Midyear</option>
                            <option value="this_year">📈 This Year</option>
                            <option value="last_year">📜 Last Year</option>
                            <option value="custom">⚙️ Custom Date Range</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                {{-- Branch Office --}}
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                        Branch Office
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="branch_office_id">
                            <option value="">All Allowed Branches</option>
                            @foreach($branches as $bId => $bName)
                                <option value="{{ $bId }}">{{ $bName }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                {{-- Approval Status (Approved / Disapproved / Pending filter next to Branch Office) --}}
                @if($hasApprovalStatusFilter ?? false)
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            Approval Status
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="approval_status">
                                <option value="all">All (All Statuses)</option>
                                <option value="Approved">✅ Approved</option>
                                <option value="Disapproved_Pending">⚠️ Disapproved & Pending</option>
                                <option value="Disapproved">❌ Disapproved Only</option>
                                <option value="Pending">⏳ Pending Only</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                @endif

                {{-- Collateral Type (Optional per report) --}}
                @if($hasCollateralFilter ?? true)
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            Collateral Type
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="collateral_type">
                                <option value="">All Collateral Types</option>
                                <option value="real_estate_mortgage">Real Estate Mortgage (REM)</option>
                                <option value="chattel_mortgage">Chattel Mortgage (CM)</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                @endif

                {{-- Deficiency Scope (Optional for Deficiency Audit) --}}
                @if($hasDeficiencyFilter ?? false)
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            Deficiency Classification
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="deficiency_type">
                                <option value="all">All Deficiencies</option>
                                <option value="internal">🏢 Internal Deficiency</option>
                                <option value="external">🌐 External Deficiency</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                @endif
            </div>

            {{-- Custom Date Inputs --}}
            @if($date_preset === 'custom')
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 14px; padding-top: 14px; border-top: 1px solid rgba(255, 255, 255, 0.08);">
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Start Date</label>
                        <x-filament::input.wrapper>
                            <x-filament::input type="date" wire:model.live="startDate" />
                        </x-filament::input.wrapper>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">End Date</label>
                        <x-filament::input.wrapper>
                            <x-filament::input type="date" wire:model.live="endDate" />
                        </x-filament::input.wrapper>
                    </div>
                </div>
            @endif
        </x-filament::section>

        {{-- 2. Grounded Executive Summary & Distinct Metric Cards --}}
        @if($previewData)
            <x-filament::section compact>
                <div class="flex items-center justify-between flex-wrap gap-2 pb-3 border-b border-gray-200/80 dark:border-white/10">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <x-filament::badge color="success" size="sm">
                            Live Report Active
                        </x-filament::badge>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            Generated: {{ $previewData['metadata']['generated_at'] }} • By: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $previewData['metadata']['generated_by'] }}</span>
                        </span>
                    </div>
                </div>

                {{-- Distinct Metric Cards with Subtle Borders & Backgrounds --}}
                @if(!empty($previewData['kpis']))
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-top: 14px;">
                        @foreach($previewData['kpis'] as $kpi)
                            @php
                                $isVolume = str_contains($kpi['label'], 'Volume') || str_contains($kpi['label'], 'Amount') || str_contains($kpi['value'], '₱');
                                $isRate = str_contains($kpi['label'], 'Rate') || str_contains($kpi['value'], '%');
                                $isDanger = str_contains($kpi['label'], 'Deficien') || str_contains($kpi['label'], 'Pending') || str_contains($kpi['label'], 'Overdue');
                            @endphp
                            <div
                                class="rounded-xl p-4 bg-gray-50/80 dark:bg-white/[0.05] border border-gray-200/90 dark:border-white/10 shadow-sm flex flex-col justify-between"
                                style="border-radius: 12px; padding: 14px 16px; border: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.04); box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);"
                            >
                                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; line-height: 1.3;">
                                    {{ $kpi['label'] }}
                                </span>
                                <div class="text-2xl font-black mt-2 tracking-tight {{ $isVolume ? 'text-emerald-500 font-mono' : ($isRate ? 'text-primary-500' : ($isDanger ? 'text-amber-500' : 'text-gray-900 dark:text-white')) }}">
                                    {{ $kpi['value'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            {{-- 3. Standard Filament Contained Tabs for Deficiency Audit --}}
            @if($hasDeficiencyFilter ?? false)
                <div class="py-1">
                    <x-filament::tabs contained>
                        <x-filament::tabs.item
                            :active="($deficiency_type ?? 'all') === 'all'"
                            wire:click="$set('deficiency_type', 'all')"
                            :badge="$deficiencyCounts['all'] ?? 0"
                            badge-color="primary"
                            icon="heroicon-o-document-magnifying-glass"
                        >
                            All Deficiencies
                        </x-filament::tabs.item>

                        <x-filament::tabs.item
                            :active="($deficiency_type ?? '') === 'internal'"
                            wire:click="$set('deficiency_type', 'internal')"
                            :badge="$deficiencyCounts['internal'] ?? 0"
                            badge-color="warning"
                            icon="heroicon-o-building-office"
                        >
                            Internal Deficiency
                        </x-filament::tabs.item>

                        <x-filament::tabs.item
                            :active="($deficiency_type ?? '') === 'external'"
                            wire:click="$set('deficiency_type', 'external')"
                            :badge="$deficiencyCounts['external'] ?? 0"
                            badge-color="danger"
                            icon="heroicon-o-globe-alt"
                        >
                            External Deficiency
                        </x-filament::tabs.item>
                    </x-filament::tabs>
                </div>
            @endif

            {{-- 4. 100% Native Filament Table Builder --}}
            <div>
                {{ $this->table }}
            </div>
        @endif

    </div>

    <script>
        (function() {
            let isPrinting = false;

            function handleSilentPrint(event) {
                if (isPrinting) return;

                const detail = event.detail;
                let url = null;
                if (typeof detail === 'string') {
                    url = detail;
                } else if (detail && typeof detail.url === 'string') {
                    url = detail.url;
                } else if (Array.isArray(detail) && detail[0]?.url) {
                    url = detail[0].url;
                } else if (detail && detail[0] && typeof detail[0] === 'string') {
                    url = detail[0];
                }

                if (!url) return;

                isPrinting = true;
                setTimeout(function() {
                    isPrinting = false;
                }, 2000);

                let iframe = document.getElementById('pmis-silent-print-frame');
                if (iframe) {
                    iframe.remove();
                }

                iframe = document.createElement('iframe');
                iframe.id = 'pmis-silent-print-frame';
                iframe.style.position = 'fixed';
                iframe.style.top = '-9999px';
                iframe.style.left = '-9999px';
                iframe.style.width = '1024px';
                iframe.style.height = '768px';
                iframe.style.border = '0';
                document.body.appendChild(iframe);

                iframe.src = url;
            }

            window.addEventListener('print-report-silent', handleSilentPrint);
        })();
    </script>
</x-filament-panels::page>
