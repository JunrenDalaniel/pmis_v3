<x-filament-panels::page>
    <style>
        .pmis-epeb-container {
            font-family: inherit;
        }

        .pmis-page-subtitle {
            font-size: 12px;
            color: #64748b;
            margin-top: -12px;
            margin-bottom: 8px;
        }
        .dark .pmis-page-subtitle {
            color: #94a3b8;
        }
        
        /* Filter Bar Matching User Screenshot */
        .pmis-epeb-filters-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }

        .pmis-epeb-filter-item {
            display: inline-flex;
            align-items: center;
            border-radius: 7px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            transition: all 0.15s ease;
        }
        .dark .pmis-epeb-filter-item {
            background: #0f172a;
            border-color: #334155;
            box-shadow: none;
        }
        .pmis-epeb-filter-item:focus-within {
            border-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }

        .pmis-epeb-filter-badge {
            background: #f1f5f9;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            padding: 8px 12px;
            border-right: 1px solid #cbd5e1;
            user-select: none;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .dark .pmis-epeb-filter-badge {
            background: #1e293b;
            color: #94a3b8;
            border-right-color: #334155;
        }

        .pmis-epeb-filter-select {
            border: none;
            outline: none;
            background: transparent;
            padding: 8px 30px 8px 10px;
            font-size: 12.5px;
            font-weight: 500;
            color: #1e293b;
            cursor: pointer;
            min-width: 140px;
        }
        .dark .pmis-epeb-filter-select {
            color: #f1f5f9 !important;
            background: #0f172a !important;
        }
        .dark .pmis-epeb-filter-select option {
            background: #0f172a !important;
            color: #f1f5f9 !important;
        }

        /* Status Tabs */
        .pmis-status-tab {
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .pmis-status-tab-active {
            background: #ecfdf5;
            border-color: #86efac;
            color: #065f46;
        }
        .dark .pmis-status-tab-active {
            background: rgba(16, 185, 129, 0.18) !important;
            border-color: rgba(16, 185, 129, 0.45) !important;
            color: #34d399 !important;
        }
        .pmis-status-tab-inactive {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #64748b;
        }
        .pmis-status-tab-inactive:hover {
            background: #f1f5f9;
            color: #1e293b;
        }
        .dark .pmis-status-tab-inactive {
            background: #0b1120 !important;
            border-color: #1e293b !important;
            color: #94a3b8 !important;
        }
        .dark .pmis-status-tab-inactive:hover {
            background: #131d33 !important;
            color: #f1f5f9 !important;
        }

        /* KPI Cards */
        .pmis-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 12px;
        }
        .pmis-kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            padding: 12px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }
        .dark .pmis-kpi-card {
            background: #0f172a !important;
            border-color: #1e293b !important;
            box-shadow: none !important;
        }

        .pmis-kpi-label {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
        }
        .dark .pmis-kpi-label {
            color: #94a3b8 !important;
        }

        .pmis-kpi-val {
            font-size: 20px;
            font-weight: 800;
            margin-top: 6px;
            letter-spacing: -0.01em;
        }
        .pmis-kpi-val-default {
            color: #0f172a;
        }
        .dark .pmis-kpi-val-default {
            color: #f8fafc !important;
        }
        .pmis-kpi-val-amount {
            color: #059669;
            font-family: ui-monospace, monospace;
        }
        .dark .pmis-kpi-val-amount {
            color: #34d399 !important;
        }
        .pmis-kpi-val-pending {
            color: #d97706;
        }
        .dark .pmis-kpi-val-pending {
            color: #fbbf24 !important;
        }
        .pmis-kpi-val-released {
            color: #059669;
        }
        .dark .pmis-kpi-val-released {
            color: #34d399 !important;
        }

    </style>

    <div class="pmis-epeb-container space-y-4">
        
        {{-- Page Subtitle under Native Filament Heading --}}
        <div class="pmis-page-subtitle">
            Registry of Deeds (ROD) Electronic Primary Entry Book tracking & pending encumbrance monitoring •
            <a href="https://lots.lra.gov.ph/TransactionStatus/" target="_blank" rel="noopener noreferrer" style="color: #0284c7; text-decoration: underline; font-weight: 600;" class="dark:text-sky-400">
                Check Online via LRA LOTS Portal ↗
            </a>
        </div>

        {{-- 4 Filter Controls Matching User Screenshot --}}
        <div class="pmis-epeb-filters-row">
            {{-- 1. ROD --}}
            <div class="pmis-epeb-filter-item">
                <span class="pmis-epeb-filter-badge">ROD</span>
                <select wire:model.live="rod_branch_id" class="pmis-epeb-filter-select">
                    <option value="">All ROD Branches</option>
                    @foreach($rodBranches as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 2. OIC --}}
            <div class="pmis-epeb-filter-item">
                <span class="pmis-epeb-filter-badge">OIC</span>
                <select wire:model.live="branch_office_id" class="pmis-epeb-filter-select">
                    <option value="">All OIC Branches</option>
                    @foreach($oicBranches as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 3. Type --}}
            <div class="pmis-epeb-filter-item">
                <span class="pmis-epeb-filter-badge">Type</span>
                <select wire:model.live="collateral_type" class="pmis-epeb-filter-select">
                    <option value="">All REM Types</option>
                    <option value="real_estate_mortgage">Real Estate Mortgage (REM)</option>
                    <option value="rem_amendment">Amendment Of REM</option>
                    <option value="rem_certification">REM Certification</option>
                </select>
            </div>

            {{-- 4. Year --}}
            <div class="pmis-epeb-filter-item">
                <span class="pmis-epeb-filter-badge">Year</span>
                <select wire:model.live="year" class="pmis-epeb-filter-select">
                    <option value="">All Years</option>
                    @foreach($years as $yVal)
                        <option value="{{ $yVal }}">{{ $yVal }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 5. ROD Status --}}
            <div class="pmis-epeb-filter-item">
                <span class="pmis-epeb-filter-badge">Status</span>
                <select wire:model.live="rod_status_id" class="pmis-epeb-filter-select">
                    <option value="">All ROD Statuses</option>
                    @foreach($rodStatuses as $sId => $sName)
                        <option value="{{ $sId }}">{{ $sName }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Reset Button --}}
            @if($rod_branch_id || $branch_office_id || $collateral_type || $year || $rod_status_id || $search || $status_filter !== 'all')
                <x-filament::button
                    type="button"
                    wire:click="resetFilters"
                    color="gray"
                    size="sm"
                    icon="heroicon-o-arrow-path"
                >
                    Reset
                </x-filament::button>
            @endif
        </div>

        {{-- Status Tabs --}}
        <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
            <button
                type="button"
                wire:click="setStatusFilter('all')"
                class="{{ $status_filter === 'all' ? 'pmis-status-tab pmis-status-tab-active' : 'pmis-status-tab pmis-status-tab-inactive' }}"
            >
                <span>All ROD Paid</span>
                <span style="font-size: 10px; padding: 1px 6px; border-radius: 4px; background: rgba(148, 163, 184, 0.2); color: inherit;">
                    {{ $reportData ? count($reportData['rows']) : 0 }}
                </span>
            </button>

            <button
                type="button"
                wire:click="setStatusFilter('pending')"
                class="{{ $status_filter === 'pending' ? 'pmis-status-tab pmis-status-tab-active' : 'pmis-status-tab pmis-status-tab-inactive' }}"
            >
                <span>⏳ Pending at ROD</span>
                <span style="font-size: 10px; padding: 1px 6px; border-radius: 4px; background: rgba(245, 158, 11, 0.2); color: #d97706;" class="dark:text-amber-400">
                    {{ $reportData['kpis'][1]['value'] ?? 0 }}
                </span>
            </button>

            <button
                type="button"
                wire:click="setStatusFilter('released')"
                class="{{ $status_filter === 'released' ? 'pmis-status-tab pmis-status-tab-active' : 'pmis-status-tab pmis-status-tab-inactive' }}"
            >
                <span>🟢 ROD Released</span>
                <span style="font-size: 10px; padding: 1px 6px; border-radius: 4px; background: rgba(16, 185, 129, 0.2); color: #059669;" class="dark:text-emerald-400">
                    {{ $reportData['kpis'][2]['value'] ?? 0 }}
                </span>
            </button>
        </div>

        {{-- KPI Stat Cards Grid --}}
        @if(!empty($reportData['kpis']))
            <div class="pmis-kpi-grid">
                @foreach($reportData['kpis'] as $kpi)
                    @php
                        $isAmount = str_contains($kpi['label'], 'Fees') || str_contains($kpi['value'], '₱');
                        $isPending = str_contains($kpi['label'], 'Pending');
                        $isReleased = str_contains($kpi['label'], 'Released');
                        $valClass = $isAmount ? 'pmis-kpi-val-amount' : ($isPending ? 'pmis-kpi-val-pending' : ($isReleased ? 'pmis-kpi-val-released' : 'pmis-kpi-val-default'));
                    @endphp
                    <div class="pmis-kpi-card">
                        <span class="pmis-kpi-label">
                            {{ $kpi['label'] }}
                        </span>
                        <div class="pmis-kpi-val {{ $valClass }}">
                            {{ $kpi['value'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Native Filament Table Section --}}
        <div class="mt-4">
            {{ $this->table }}
        </div>

    </div>

    <script>
        (function() {
            let isPrinting = false;

            function handleSilentPrint(event) {
                if (isPrinting) return;

                const url = event.detail?.url || (Array.isArray(event.detail) ? event.detail[0]?.url : event.detail);
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
