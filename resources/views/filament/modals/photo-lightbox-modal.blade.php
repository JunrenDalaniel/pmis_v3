@php
    $images = $record ? $record->getMedia('*')->filter(function ($m) {
        $mime = $m->mime_type ?? '';
        $ext = strtolower(pathinfo($m->file_name, PATHINFO_EXTENSION));
        return str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
    }) : collect();
@endphp

<div
    x-data="{
        initGallery() {
            this.$nextTick(() => {
                if (window.ImageGallery && typeof window.ImageGallery.scan === 'function') {
                    window.ImageGallery.scan();
                } else {
                    setTimeout(() => {
                        if (window.ImageGallery && typeof window.ImageGallery.scan === 'function') {
                            window.ImageGallery.scan();
                        }
                    }, 300);
                }
            });
        }
    }"
    x-init="initGallery()"
    style="display: flex; flex-direction: column; gap: 16px; font-family: inherit;"
>
    @if($images->isEmpty())
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 24px; text-align: center; background: #f8fafc; border-radius: 12px; border: 1px dashed #cbd5e1;">
            <div style="font-size: 44px; margin-bottom: 12px;">🖼️</div>
            <h4 style="font-size: 15px; font-weight: 600; color: #1e293b; margin: 0 0 6px;">Walang Nakitang Image Files</h4>
            <p style="font-size: 13px; color: #64748b; margin: 0; max-width: 420px;">
                Wala pang uploaded na litrato (.jpg, .png) para sa loan na ito. Kung PDF documents ang iyong tinitingnan, gamitin ang <strong>Interactive Slide-Over Viewer</strong> button.
            </p>
        </div>
    @else
        {{-- Header Instructions Banner --}}
        <div style="display: flex; align-items: center; justify-content: space-between; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 12px 16px; color: #1e40af;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">💡</span>
                <div>
                    <strong style="font-size: 13px; display: block;">Interactive Lightbox Mode:</strong>
                    <span style="font-size: 12px; color: #1d4ed8;">
                        I-click ang kahit anong larawan upang buksan ang full-screen Lightbox na may Zoom, 90° Rotate, Flip, at Carousel Slider.
                    </span>
                </div>
            </div>
            <span style="font-size: 12px; font-weight: 700; background: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 9999px; white-space: nowrap;">
                {{ $images->count() }} {{ $images->count() === 1 ? 'Photo' : 'Photos' }}
            </span>
        </div>

        {{-- Photos Grid with data-viewer-gallery --}}
        <div
            data-viewer-gallery
            style="display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 16px; max-height: 68vh; overflow-y: auto; padding: 4px;"
        >
            @foreach($images as $image)
                @php
                    $url = route('media.preview', $image->id);
                    $downloadUrl = route('media.download', $image->id);
                    $ext = strtoupper(pathinfo($image->file_name, PATHINFO_EXTENSION));
                @endphp
                <div
                    style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06); transition: all 0.2s ease-in-out; display: flex; flex-direction: column;"
                    onmouseover="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 8px 20px rgba(59,130,246,0.15)'; this.style.transform='translateY(-2px)';"
                    onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.06)'; this.style.transform='translateY(0)';"
                >
                    {{-- Image Container --}}
                    <div style="height: 190px; width: 100%; background: #0f172a; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; cursor: zoom-in;">
                        <img
                            src="{{ $url }}"
                            alt="{{ $image->file_name }}"
                            loading="lazy"
                            style="max-width: 100%; max-height: 100%; object-fit: contain; transition: transform 0.25s ease;"
                            onmouseover="this.style.transform='scale(1.06)'"
                            onmouseout="this.style.transform='scale(1.0)'"
                            title="Click to open Fullscreen Lightbox"
                        />
                        <span style="position: absolute; top: 8px; right: 8px; background: rgba(15, 23, 42, 0.75); color: #f8fafc; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 6px; backdrop-filter: blur(4px);">
                            {{ $ext }}
                        </span>
                        <span style="position: absolute; bottom: 8px; left: 8px; background: rgba(15, 23, 42, 0.75); color: #f8fafc; font-size: 10px; font-weight: 500; padding: 2px 6px; border-radius: 4px; backdrop-filter: blur(4px);">
                            {{ $image->human_readable_size }}
                        </span>
                    </div>

                    {{-- Image Details & Direct Actions --}}
                    <div style="padding: 10px 12px; background: #ffffff; display: flex; flex-direction: column; gap: 6px; border-top: 1px solid #f1f5f9;">
                        <div style="font-size: 12px; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $image->file_name }}">
                            {{ $image->file_name }}
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 2px;">
                            <span style="font-size: 11px; color: #3b82f6; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                🔍 Click Image
                            </span>
                            <a
                                href="{{ $downloadUrl }}"
                                download
                                style="font-size: 11px; color: #475569; text-decoration: none; padding: 2px 8px; border-radius: 4px; background: #f1f5f9; border: 1px solid #e2e8f0; font-weight: 500; display: inline-flex; align-items: center; gap: 2px;"
                                onmouseover="this.style.background='#e2e8f0';"
                                onmouseout="this.style.background='#f1f5f9';"
                                title="Download image"
                            >
                                ⬇️ Save
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
