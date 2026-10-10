<x-filament-panels::page>
    <style>
        /* Sleek Modern Scrollbars for Windows (eliminates default grey arrows < >) */
        .fi-tabs,
        nav[role="tablist"],
        .fi-tabs-item-ctn,
        .fi-ta-content {
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.35) transparent;
        }
        .fi-tabs::-webkit-scrollbar,
        nav[role="tablist"]::-webkit-scrollbar,
        .fi-tabs-item-ctn::-webkit-scrollbar,
        .fi-ta-content::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }
        .fi-tabs::-webkit-scrollbar-button,
        nav[role="tablist"]::-webkit-scrollbar-button,
        .fi-tabs-item-ctn::-webkit-scrollbar-button,
        .fi-ta-content::-webkit-scrollbar-button {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
        .fi-tabs::-webkit-scrollbar-track,
        nav[role="tablist"]::-webkit-scrollbar-track,
        .fi-tabs-item-ctn::-webkit-scrollbar-track,
        .fi-ta-content::-webkit-scrollbar-track {
            background: transparent;
        }
        .fi-tabs::-webkit-scrollbar-thumb,
        nav[role="tablist"]::-webkit-scrollbar-thumb,
        .fi-tabs-item-ctn::-webkit-scrollbar-thumb,
        .fi-ta-content::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.35);
            border-radius: 9999px;
        }
        .fi-tabs::-webkit-scrollbar-thumb:hover,
        nav[role="tablist"]::-webkit-scrollbar-thumb:hover,
        .fi-tabs-item-ctn::-webkit-scrollbar-thumb:hover,
        .fi-ta-content::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.6);
        }
    </style>

    <div class="space-y-6">

        {{-- 1. Executive Filament Native Tabs --}}
        <x-filament::tabs label="Report Navigation Tabs">
            @foreach($reportTypes as $key => $info)
                <x-filament::tabs.item
                    :active="$report_type === $key"
                    wire:click="setReportType('{{ $key }}')"
                    :icon="$info['icon']"
                    :badge="$info['badge']"
                >
                    {{ $info['label'] }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        {{-- 2. Compact Filter Command Section --}}
        <x-filament::section compact>
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

                {{-- Collateral Type --}}
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

        {{-- 3. Live Report Preview & Data Presentation --}}
        @if($previewData)
            <x-filament::section>
                {{-- Report Summary Header Card with Integrated Action Buttons --}}
                <div class="flex items-start justify-between flex-wrap gap-4 pb-4 border-b border-gray-200/80 dark:border-white/10">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <x-filament::badge color="success" size="sm">
                                Official Live Preview
                            </x-filament::badge>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                Generated: {{ $previewData['metadata']['generated_at'] }} • By: {{ $previewData['metadata']['generated_by'] }}
                            </span>
                        </div>
                        <h2 class="text-xl font-extrabold text-gray-900 dark:text-white mt-1.5 tracking-tight">
                            {{ $previewData['title'] }}
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $previewData['description'] }}
                        </p>
                    </div>

                    {{-- Integrated Official Export & Print Action Buttons --}}
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <x-filament::button
                            tag="a"
                            href="{{ $printUrl }}"
                            target="_blank"
                            color="success"
                            icon="heroicon-o-printer"
                            size="sm"
                        >
                            Print Official PDF
                        </x-filament::button>

                        <x-filament::button
                            tag="a"
                            href="{{ $excelUrl }}"
                            color="primary"
                            icon="heroicon-o-arrow-down-tray"
                            size="sm"
                        >
                            Export to Excel (.xlsx)
                        </x-filament::button>
                    </div>
                </div>

                {{-- Active Scope Badges --}}
                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-top: 14px;">
                    <span style="font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Filter Scope:</span>
                    <x-filament::badge color="gray" size="sm">
                        🗓️ {{ $previewData['metadata']['date_label'] }}
                    </x-filament::badge>
                    <x-filament::badge color="gray" size="sm">
                        🏢 {{ $previewData['metadata']['branch_label'] }}
                    </x-filament::badge>
                    <x-filament::badge color="gray" size="sm">
                        🛡️ {{ $previewData['metadata']['collateral_label'] }}
                    </x-filament::badge>
                    <x-filament::badge color="info" size="sm">
                        📊 {{ count($previewData['rows']) }} {{ Str::plural('Record', count($previewData['rows'])) }}
                    </x-filament::badge>
                </div>

                {{-- Executive Balanced KPI Cards Grid --}}
                @if(!empty($previewData['kpis']))
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 16px;">
                        @foreach($previewData['kpis'] as $kpi)
                            @php
                                $isVolume = str_contains($kpi['label'], 'Volume') || str_contains($kpi['label'], 'Amount') || str_contains($kpi['value'], '₱');
                                $isRate = str_contains($kpi['label'], 'Rate') || str_contains($kpi['value'], '%');
                            @endphp
                            <div class="bg-gray-50/75 dark:bg-white/[0.03] border border-gray-200/80 dark:border-white/10 rounded-xl p-3 sm:p-4 flex flex-col justify-between">
                                <span style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; line-height: 1.3;" class="text-gray-500 dark:text-gray-400">
                                    {{ $kpi['label'] }}
                                </span>
                                <div class="text-lg font-extrabold mt-1.5 {{ $isVolume ? 'text-emerald-600 dark:text-emerald-400 font-mono' : ($isRate ? 'text-primary-600 dark:text-primary-400' : 'text-gray-900 dark:text-white') }}">
                                    {{ $kpi['value'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- 100% Native Filament Table Builder --}}
                <div class="mt-6">
                    {{ $this->table }}
                </div>
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
