@php
    $files = $record ? $record->getMedia('*') : collect();

    $categorizeDocument = function ($filename) {
        $rawBase = pathinfo($filename, PATHINFO_FILENAME);
        $clean = trim(preg_replace('/[._-]+$/', '', $rawBase));
        $upper = strtoupper($clean);

        // 1. Borrower KYC & Identification
        if (preg_match('/(VALID-?ID|BIRTH(-?CERT)?|\bTIN\b|KYC|BORROWER|MARRIAGE(-?CERT)?|PASSPORT|\bSSS\b|\bUMID\b|POSTAL|CEDULA)/i', $upper)) {
            $title = $clean;
            if (preg_match('/BIRTH/i', $upper)) $title = 'Birth Certificate';
            elseif (preg_match('/VALID-?ID/i', $upper)) $title = "Borrower's Valid ID";
            elseif (preg_match('/\bTIN\b/i', $upper)) $title = 'Validated BIR TIN';
            elseif (preg_match('/MARRIAGE/i', $upper)) $title = 'Marriage Certificate';
            return [
                'code'   => 'kyc',
                'label'  => 'Borrower KYC & IDs',
                'icon'   => '👤',
                'color'  => '#059669', // Emerald
                'bg'     => '#ecfdf5',
                'border' => '#a7f3d0',
                'title'  => $title,
            ];
        }

        // 2. Loan Contracts & Promissory Notes
        if (preg_match('/(\bPN\b|PN-|PROMISSORY|\bCM\d*\b|CHATTEL|DEBIT|TRANSFER|MORTGAGE|DISCLOSURE|CONTRACT|VOUCHER)/i', $upper)) {
            $title = $clean;
            if (preg_match('/PN.*FRONT/i', $upper)) $title = 'Promissory Note (Front)';
            elseif (preg_match('/PN.*BACK/i', $upper)) $title = 'Promissory Note (Back)';
            elseif (preg_match('/^PN\b/i', $upper)) $title = 'Promissory Note';
            elseif (preg_match('/CM(\d+)/i', $upper, $m)) $title = "Chattel Mortgage (Page {$m[1]})";
            elseif (preg_match('/DEBIT.*TRANSFER/i', $upper)) $title = 'Debit Transfer Advice';
            elseif (preg_match('/DISCLOSURE/i', $upper)) $title = 'Disclosure Statement';
            return [
                'code'   => 'contracts',
                'label'  => 'Loan Contracts & PN',
                'icon'   => '📝',
                'color'  => '#2563eb', // Blue
                'bg'     => '#eff6ff',
                'border' => '#bfdbfe',
                'title'  => $title,
            ];
        }

        // 3. Collateral & Vehicle Documents (OR, CR, Stencil, Vehicle Photos)
        if (preg_match('/(\bOR\b|\bCR\b|LTO|CHASSIS|ENGINE|STENCIL|COLL-?PIC|VEHICLE|\bMOTOR\b|\bTRUCK\b|\bCAR\b)/i', $upper)) {
            $title = $clean;
            if (preg_match('/(^|[^A-Z])CR([^A-Z]|$)/i', $upper)) $title = 'Certificate of Registration (CR)';
            elseif (preg_match('/(^|[^A-Z])OR([^A-Z]|$)/i', $upper)) $title = 'Official Receipt (OR)';
            elseif (preg_match('/LTO/i', $upper)) $title = 'LTO Verification';
            elseif (preg_match('/CHASSIS.*ENGINE/i', $upper)) $title = 'Stencil — Chassis & Engine';
            elseif (preg_match('/CHASSIS/i', $upper)) $title = 'Stencil — Chassis Number';
            elseif (preg_match('/ENGINE/i', $upper)) $title = 'Stencil — Engine Number';
            elseif (preg_match('/COLL-?PIC(\d+)/i', $upper, $m)) $title = "Collateral Photo {$m[1]}";
            return [
                'code'   => 'collateral',
                'label'  => 'Collateral & Vehicle',
                'icon'   => '🚗',
                'color'  => '#d97706', // Amber
                'bg'     => '#fffbeb',
                'border' => '#fde68a',
                'title'  => $title,
            ];
        }

        // 4. Appraisal & Inspection
        if (preg_match('/(APPRAISAL|INSPECT|\bCI\b|INVESTIGATION)/i', $upper)) {
            $title = $clean;
            if (preg_match('/BRANCH.*APPRAISAL/i', $upper)) $title = 'Branch Appraisal Report';
            elseif (preg_match('/APPRAISAL/i', $upper)) $title = 'Appraisal Report';
            elseif (preg_match('/INSPECT/i', $upper)) $title = 'Inspection Report';
            return [
                'code'   => 'appraisal',
                'label'  => 'Appraisal & Inspection',
                'icon'   => '🏢',
                'color'  => '#7c3aed', // Purple
                'bg'     => '#f5f3ff',
                'border' => '#ddd6fe',
                'title'  => $title,
            ];
        }

        // 5. Registry & Government Agencies (PPSR, Notary, Transmittal, ROD, Assessor, BIR)
        if (preg_match('/(PPSR|TRANSMITTAL|NOTARY|NOTARIZED|RECEIPT|REGISTRY|\bROD\b|ASSESSOR|\bBIR\b)/i', $upper)) {
            $title = $clean;
            if (preg_match('/PPSR/i', $upper)) $title = 'PPSR Registration / Security Notice';
            elseif (preg_match('/TRANSMITTAL/i', $upper)) $title = 'Document Transmittal Slip';
            elseif (preg_match('/NOTAR/i', $upper)) $title = 'Notarized Acknowledgement';
            return [
                'code'   => 'registry',
                'label'  => 'Registry & Agency',
                'icon'   => '🏛️',
                'color'  => '#e11d48', // Rose
                'bg'     => '#fff1f2',
                'border' => '#fecdd3',
                'title'  => $title,
            ];
        }

        // 6. Fallback / Other
        $humanized = ucwords(str_replace(['-', '_'], ' ', $clean));
        return [
            'code'   => 'other',
            'label'  => 'Other Documents',
            'icon'   => '📎',
            'color'  => '#64748b', // Slate
            'bg'     => '#f8fafc',
            'border' => '#e2e8f0',
            'title'  => $humanized ?: $clean,
        ];
    };

    $filesData = $files->map(function ($media) use ($categorizeDocument) {
        $mime = $media->mime_type ?? '';
        $isPdf = str_contains(strtolower($mime), 'pdf');
        $isImage = str_contains(strtolower($mime), 'image') || in_array(strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp']);
        $cat = $categorizeDocument($media->file_name);

        return [
            'id'             => $media->id,
            'name'           => $media->file_name,
            'clean_title'    => $cat['title'],
            'category_code'  => $cat['code'],
            'category_label' => $cat['label'],
            'category_icon'  => $cat['icon'],
            'category_color' => $cat['color'],
            'category_bg'    => $cat['bg'],
            'category_border'=> $cat['border'],
            'collection'     => ucwords(str_replace('_', ' ', $media->collection_name)),
            'size'           => $media->human_readable_size,
            'url'            => route('media.preview', $media->id),
            'download_url'   => route('media.download', $media->id),
            'is_pdf'         => $isPdf,
            'is_image'       => $isImage,
            'created_at'     => $media->created_at ? $media->created_at->format('F d, Y h:i A') : '',
        ];
    })->values()->toArray();

    $categoriesList = [
        ['code' => 'all',        'label' => 'All Documents',        'icon' => '📁'],
        ['code' => 'kyc',        'label' => 'KYC & IDs',            'icon' => '👤'],
        ['code' => 'contracts',  'label' => 'Contracts & PN',       'icon' => '📝'],
        ['code' => 'collateral', 'label' => 'Collateral & Vehicle', 'icon' => '🚗'],
        ['code' => 'appraisal',  'label' => 'Appraisal Report',     'icon' => '🏢'],
        ['code' => 'registry',   'label' => 'Registry & Agency',    'icon' => '🏛️'],
        ['code' => 'other',      'label' => 'Other Documents',      'icon' => '📎'],
    ];
@endphp

<div
    x-data="{
        files: @js($filesData),
        activeCategory: 'all',
        searchQuery: '',
        activeIndex: 0,
        zoom: 1.0,
        rotation: 0,

        get categoryCounts() {
            const counts = { all: this.files.length };
            this.files.forEach(f => {
                counts[f.category_code] = (counts[f.category_code] || 0) + 1;
            });
            return counts;
        },

        get availableCategories() {
            const allCats = @js($categoriesList);
            return allCats.filter(c => c.code === 'all' || (this.categoryCounts[c.code] && this.categoryCounts[c.code] > 0));
        },

        get filteredFiles() {
            return this.files.filter(f => {
                const matchesCat = this.activeCategory === 'all' || f.category_code === this.activeCategory;
                const matchesSearch = !this.searchQuery ||
                    f.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    f.clean_title.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    f.category_label.toLowerCase().includes(this.searchQuery.toLowerCase());
                return matchesCat && matchesSearch;
            });
        },

        get activeFile() {
            if (!this.filteredFiles.length) return null;
            if (this.activeIndex >= this.filteredFiles.length) {
                this.activeIndex = 0;
            }
            return this.filteredFiles[this.activeIndex] || null;
        },

        setCategory(code) {
            this.activeCategory = code;
            this.activeIndex = 0;
            this.zoom = 1.0;
            this.rotation = 0;
        },

        selectFile(index) {
            this.activeIndex = index;
            this.zoom = 1.0;
            this.rotation = 0;
        },

        zoomIn() {
            if (this.zoom < 3.0) this.zoom = +(this.zoom + 0.25).toFixed(2);
        },

        zoomOut() {
            if (this.zoom > 0.5) this.zoom = +(this.zoom - 0.25).toFixed(2);
        },

        resetZoom() {
            this.zoom = 1.0;
            this.rotation = 0;
        },

        rotateClockwise() {
            this.rotation = (this.rotation + 90) % 360;
        },

        printActive() {
            if (!this.activeFile) return;
            const printWin = window.open(this.activeFile.url, '_blank');
            if (printWin) {
                printWin.focus();
                printWin.print();
            }
        }
    }"
    style="display: flex; flex-direction: column; height: 82vh; min-height: 580px; background: #f8fafc; border-radius: 12px; overflow: hidden; font-family: inherit;"
>
    <template x-if="files.length === 0">
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #64748b; padding: 40px; text-align: center;">
            <div style="font-size: 48px; margin-bottom: 12px;">📂</div>
            <h3 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0 0 6px;">No Scanned Documents Attached</h3>
            <p style="font-size: 13px; margin: 0;">Upload documents in the 'Scan / Upload' tab to preview them here.</p>
        </div>
    </template>

    <template x-if="files.length > 0">
        <div style="display: flex; flex-direction: column; height: 100%; overflow: hidden;">
            {{-- TOP BAR: Smart Category Filter Pills --}}
            <div style="background: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 6px; overflow-x: auto; padding-bottom: 2px;">
                    <template x-for="cat in availableCategories" :key="cat.code">
                        <button
                            type="button"
                            @click="setCategory(cat.code)"
                            :style="activeCategory === cat.code
                                ? 'background: #1e293b; color: #ffffff; border-color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);'
                                : 'background: #f8fafc; color: #475569; border-color: #e2e8f0;'"
                            style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1px solid; cursor: pointer; transition: all 0.15s; white-space: nowrap;"
                        >
                            <span x-text="cat.icon"></span>
                            <span x-text="cat.label"></span>
                            <span
                                :style="activeCategory === cat.code
                                    ? 'background: rgba(255,255,255,0.25); color: #ffffff;'
                                    : 'background: #e2e8f0; color: #334155;'"
                                style="padding: 1px 6px; border-radius: 10px; font-size: 10px; font-weight: 700; margin-left: 2px;"
                                x-text="categoryCounts[cat.code] || 0"
                            ></span>
                        </button>
                    </template>
                </div>

                {{-- Total Counter Badge --}}
                <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                    <span style="font-size: 11px; padding: 4px 10px; border-radius: 9999px; background: #e0f2fe; color: #0284c7; font-weight: 700;">
                        ✨ Auto-Categorized (<span x-text="files.length"></span> files)
                    </span>
                </div>
            </div>

            {{-- MAIN SPLIT CONTENT --}}
            <div style="display: flex; flex: 1; height: calc(100% - 50px); overflow: hidden;">
                {{-- Left Sidebar: Filtered Document List --}}
                <div style="width: 320px; min-width: 290px; background: #ffffff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column;">
                    {{-- Search Input --}}
                    <div style="padding: 10px 12px; border-bottom: 1px solid #f1f5f9; background: #fafafa;">
                        <div style="position: relative; display: flex; align-items: center;">
                            <span style="position: absolute; left: 10px; font-size: 13px; color: #94a3b8; pointer-events: none;">🔍</span>
                            <input
                                type="text"
                                x-model="searchQuery"
                                placeholder="Search by name, category..."
                                style="width: 100%; padding: 6px 10px 6px 30px; font-size: 12px; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; outline: none; transition: border-color 0.15s;"
                                onfocus="this.style.borderColor='#3b82f6'"
                                onblur="this.style.borderColor='#cbd5e1'"
                            />
                            <template x-if="searchQuery">
                                <button
                                    type="button"
                                    @click="searchQuery = ''"
                                    style="position: absolute; right: 8px; font-size: 12px; color: #94a3b8; background: transparent; border: none; cursor: pointer;"
                                >✕</button>
                            </template>
                        </div>
                    </div>

                    {{-- Scrollable List --}}
                    <div style="flex: 1; overflow-y: auto; padding: 8px;">
                        <template x-if="filteredFiles.length === 0">
                            <div style="padding: 30px 16px; text-align: center; color: #94a3b8; font-size: 12px;">
                                <div>🔍</div>
                                <div style="margin-top: 4px; font-weight: 500;">No documents match this filter</div>
                            </div>
                        </template>

                        <template x-for="(file, index) in filteredFiles" :key="file.id">
                            <div
                                @click="selectFile(index)"
                                :style="activeFile && activeFile.id === file.id
                                    ? 'background: #eff6ff; border-color: #3b82f6; box-shadow: 0 1px 3px rgba(59,130,246,0.15);'
                                    : 'background: #ffffff; border-color: #e2e8f0;'"
                                style="display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 8px; border-width: 1px; border-style: solid; margin-bottom: 6px; cursor: pointer; transition: all 0.15s;"
                                onmouseover="if (this.style.borderColor !== 'rgb(59, 130, 246)') this.style.background='#f8fafc';"
                                onmouseout="if (this.style.borderColor !== 'rgb(59, 130, 246)') this.style.background='#ffffff';"
                            >
                                {{-- Icon / Miniature Thumbnail --}}
                                <div style="width: 42px; height: 42px; border-radius: 6px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; border: 1px solid #e2e8f0; position: relative;">
                                    <template x-if="file.is_image">
                                        <img :src="file.url" style="width: 100%; height: 100%; object-fit: cover;" />
                                    </template>
                                    <template x-if="file.is_pdf">
                                        <span style="font-size: 20px;">📄</span>
                                    </template>
                                    <template x-if="!file.is_image && !file.is_pdf">
                                        <span style="font-size: 20px;">📎</span>
                                    </template>
                                </div>

                                {{-- File Info with Clean Title and Category Tag --}}
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: 12px; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="file.clean_title"></div>

                                    {{-- Category Pill & Size --}}
                                    <div style="display: flex; align-items: center; gap: 5px; margin-top: 3px; flex-wrap: nowrap;">
                                        <span
                                            :style="`background: ${file.category_bg}; color: ${file.category_color}; border: 1px solid ${file.category_border};`"
                                            style="font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px;"
                                            x-text="file.category_label"
                                        ></span>
                                        <span style="font-size: 10px; color: #94a3b8;">•</span>
                                        <span style="font-size: 10px; color: #64748b; white-space: nowrap;" x-text="file.size"></span>
                                    </div>

                                    {{-- Original Filename --}}
                                    <div style="font-size: 9.5px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;" x-text="file.name"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Right Area: Live Interactive Preview Canvas --}}
                <div style="flex: 1; display: flex; flex-direction: column; background: #f8fafc; overflow: hidden;">
                    {{-- Top Action Toolbar --}}
                    <div style="min-height: 52px; background: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 6px 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                        {{-- Active File Title & Category Badge --}}
                        <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                            <template x-if="activeFile">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span
                                        :style="`background: ${activeFile.category_bg}; color: ${activeFile.category_color}; border: 1px solid ${activeFile.category_border};`"
                                        style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px; white-space: nowrap;"
                                    >
                                        <span x-text="activeFile.category_icon"></span> <span x-text="activeFile.category_label"></span>
                                    </span>
                                    <span style="font-size: 14px; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="activeFile.clean_title"></span>
                                    <span style="font-size: 11px; color: #64748b; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;" x-text="activeFile.name"></span>
                                    <span style="font-size: 11px; color: #94a3b8;" x-text="'(' + activeFile.size + ')'"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Controls (Zoom, Rotate, Print, Download, Open Tab) --}}
                        <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                            {{-- Controls for Images --}}
                            <template x-if="activeFile && activeFile.is_image">
                                <div style="display: flex; align-items: center; gap: 4px; padding-right: 8px; border-right: 1px solid #e2e8f0;">
                                    <button type="button" @click="zoomOut()" title="Zoom Out" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #e2e8f0; background: white; cursor: pointer; font-size: 13px; font-weight: bold; color: #334155;">-</button>
                                    <span style="font-size: 11px; font-weight: 600; color: #475569; width: 44px; text-align: center;" x-text="Math.round(zoom * 100) + '%'"></span>
                                    <button type="button" @click="zoomIn()" title="Zoom In" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #e2e8f0; background: white; cursor: pointer; font-size: 13px; font-weight: bold; color: #334155;">+</button>
                                    <button type="button" @click="resetZoom()" title="Reset Zoom" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #e2e8f0; background: white; cursor: pointer; font-size: 11px; color: #334155;">Reset</button>
                                    <button type="button" @click="rotateClockwise()" title="Rotate 90°" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #e2e8f0; background: white; cursor: pointer; font-size: 12px; color: #334155;">🔄 90°</button>
                                </div>
                            </template>

                            {{-- Print --}}
                            <button
                                type="button"
                                @click="printActive()"
                                title="Print Document"
                                style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 10px; border-radius: 6px; border: 1px solid #e2e8f0; background: white; cursor: pointer; font-size: 12px; color: #334155; font-weight: 500;"
                            >
                                <span>🖨️</span> Print
                            </button>

                            {{-- Direct Download --}}
                            <a
                                :href="activeFile ? activeFile.download_url : '#'"
                                download
                                title="Download File"
                                style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 6px; background: #3b82f6; color: white; text-decoration: none; font-size: 12px; font-weight: 600; box-shadow: 0 1px 2px rgba(59,130,246,0.2);"
                            >
                                <span>⬇️</span> Download
                            </a>

                            {{-- Open in New Tab --}}
                            <a
                                :href="activeFile ? activeFile.url : '#'"
                                target="_blank"
                                title="Open Fullscreen in New Tab"
                                style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; border: 1px solid #e2e8f0; background: white; color: #334155; text-decoration: none; font-size: 14px;"
                            >
                                ↗️
                            </a>
                        </div>
                    </div>

                    {{-- Canvas Area --}}
                    <div style="flex: 1; overflow: auto; display: flex; align-items: center; justify-content: center; padding: 20px; position: relative;">
                        {{-- Image Preview with Zoom & Rotation --}}
                        <template x-if="activeFile && activeFile.is_image">
                            <div style="display: inline-block; transition: transform 0.2s ease-out; transform-origin: center center;" :style="`transform: scale(${zoom}) rotate(${rotation}deg);`">
                                <img
                                    :src="activeFile.url"
                                    :alt="activeFile.name"
                                    style="max-height: 68vh; max-width: 100%; border-radius: 6px; box-shadow: 0 4px 14px rgba(0,0,0,0.12); object-fit: contain; background: white;"
                                />
                            </div>
                        </template>

                        {{-- PDF Interactive Viewer --}}
                        <template x-if="activeFile && activeFile.is_pdf">
                            <iframe
                                :src="activeFile.url"
                                style="width: 100%; height: 100%; min-height: 68vh; border: none; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); background: white;"
                            ></iframe>
                        </template>

                        {{-- Generic File Fallback --}}
                        <template x-if="activeFile && !activeFile.is_image && !activeFile.is_pdf">
                            <div style="text-align: center; color: #64748b;">
                                <div style="font-size: 48px; margin-bottom: 8px;">📎</div>
                                <div style="font-size: 14px; font-weight: 600; color: #1e293b;" x-text="activeFile.clean_title"></div>
                                <div style="font-size: 12px; margin-top: 4px;">Direct preview not available for this format.</div>
                                <a :href="activeFile.download_url" download style="display: inline-block; margin-top: 14px; padding: 8px 16px; border-radius: 6px; background: #3b82f6; color: white; text-decoration: none; font-size: 12px; font-weight: 600;">Download File</a>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>