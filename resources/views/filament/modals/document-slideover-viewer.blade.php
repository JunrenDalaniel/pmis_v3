@php
    $files = $record ? $record->getMedia('*') : collect();
    $filesData = $files->map(function ($media) {
        $mime = $media->mime_type ?? '';
        $isPdf = str_contains(strtolower($mime), 'pdf');
        $isImage = str_contains(strtolower($mime), 'image') || in_array(strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif']);

        return [
            'id'           => $media->id,
            'name'         => $media->file_name,
            'collection'   => ucwords(str_replace('_', ' ', $media->collection_name)),
            'size'         => $media->human_readable_size,
            'url'          => route('media.preview', $media->id),
            'download_url' => route('media.download', $media->id),
            'is_pdf'       => $isPdf,
            'is_image'     => $isImage,
            'created_at'   => $media->created_at ? $media->created_at->format('M d, Y h:i A') : '',
        ];
    })->values()->toArray();
@endphp

<div
    x-data="{
        files: @js($filesData),
        activeIndex: 0,
        zoom: 1.0,
        rotation: 0,

        get activeFile() {
            return this.files[this.activeIndex] || null;
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
    style="display: flex; flex-direction: column; height: 80vh; min-height: 550px; background: #f8fafc; border-radius: 12px; overflow: hidden; font-family: inherit;"
>
    <template x-if="files.length === 0">
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #64748b; padding: 40px; text-align: center;">
            <div style="font-size: 48px; margin-bottom: 12px;">📂</div>
            <h3 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0 0 6px;">No Scanned Documents Attached</h3>
            <p style="font-size: 13px; margin: 0;">Upload documents in the 'Scan / Upload' tab to preview them here.</p>
        </div>
    </template>

    <template x-if="files.length > 0">
        <div style="display: flex; flex: 1; height: 100%; overflow: hidden;">
            {{-- Left Sidebar: File List & Thumbnails --}}
            <div style="width: 280px; min-width: 260px; background: #ffffff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column;">
                {{-- Header --}}
                <div style="padding: 14px 16px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #475569;">
                        Documents (<span x-text="files.length"></span>)
                    </span>
                    <span style="font-size: 11px; padding: 2px 8px; border-radius: 9999px; background: #e0e7ff; color: #4338ca; font-weight: 600;">
                        Slide-over
                    </span>
                </div>

                {{-- Scrollable List --}}
                <div style="flex: 1; overflow-y: auto; padding: 10px 8px;">
                    <template x-for="(file, index) in files" :key="file.id">
                        <div
                            @click="selectFile(index)"
                            :style="activeIndex === index
                                ? 'background: #eff6ff; border-color: #3b82f6; box-shadow: 0 1px 2px rgba(59,130,246,0.1);'
                                : 'background: #ffffff; border-color: #e2e8f0;'"
                            style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; border-width: 1px; border-style: solid; margin-bottom: 6px; cursor: pointer; transition: all 0.15s;"
                            onmouseover="if (this.style.borderColor !== 'rgb(59, 130, 246)') this.style.background='#f8fafc';"
                            onmouseout="if (this.style.borderColor !== 'rgb(59, 130, 246)') this.style.background='#ffffff';"
                        >
                            {{-- Icon / Miniature Thumbnail --}}
                            <div style="width: 38px; height: 38px; border-radius: 6px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; border: 1px solid #e2e8f0;">
                                <template x-if="file.is_image">
                                    <img :src="file.url" style="width: 100%; height: 100%; object-fit: cover;" />
                                </template>
                                <template x-if="file.is_pdf">
                                    <span style="font-size: 18px;">📄</span>
                                </template>
                                <template x-if="!file.is_image && !file.is_pdf">
                                    <span style="font-size: 18px;">📎</span>
                                </template>
                            </div>

                            {{-- File Info --}}
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 12px; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="file.name"></div>
                                <div style="display: flex; align-items: center; gap: 6px; font-size: 10px; color: #64748b; margin-top: 2px;">
                                    <span x-text="file.size"></span>
                                    <span>•</span>
                                    <span x-text="file.collection" style="max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Right Area: Live Interactive Preview Canvas --}}
            <div style="flex: 1; display: flex; flex-direction: column; background: #f8fafc; overflow: hidden;">
                {{-- Top Action Toolbar --}}
                <div style="height: 52px; background: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 0 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                    {{-- Active File Title --}}
                    <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                        <span style="font-size: 14px; font-weight: 600; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="activeFile ? activeFile.name : ''"></span>
                        <span style="font-size: 11px; padding: 2px 8px; border-radius: 4px; background: #f1f5f9; color: #64748b; white-space: nowrap;" x-text="activeFile ? activeFile.size : ''"></span>
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
                                style="max-height: 65vh; max-width: 100%; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); object-fit: contain; background: white;"
                            />
                        </div>
                    </template>

                    {{-- PDF Interactive Viewer --}}
                    <template x-if="activeFile && activeFile.is_pdf">
                        <iframe
                            :src="activeFile.url"
                            style="width: 100%; height: 100%; min-height: 65vh; border: none; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); background: white;"
                        ></iframe>
                    </template>

                    {{-- Generic File Fallback --}}
                    <template x-if="activeFile && !activeFile.is_image && !activeFile.is_pdf">
                        <div style="text-align: center; color: #64748b;">
                            <div style="font-size: 48px; margin-bottom: 8px;">📎</div>
                            <div style="font-size: 14px; font-weight: 600; color: #1e293b;" x-text="activeFile.name"></div>
                            <div style="font-size: 12px; margin-top: 4px;">Direct preview not available for this format.</div>
                            <a :href="activeFile.download_url" download style="display: inline-block; margin-top: 14px; padding: 8px 16px; border-radius: 6px; background: #3b82f6; color: white; text-decoration: none; font-size: 12px; font-weight: 600;">Download File</a>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>