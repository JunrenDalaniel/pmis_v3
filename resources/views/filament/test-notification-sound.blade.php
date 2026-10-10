<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Notification Sound Settings — PMIS v3</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;padding:2rem 1rem}
        .container{max-width:680px;margin:0 auto}
        h1{font-size:1.5rem;font-weight:700;color:#f1f5f9;margin-bottom:.25rem}
        .subtitle{color:#64748b;font-size:.9rem;margin-bottom:2rem}
        .section{background:#1e293b;border:1px solid #334155;border-radius:14px;padding:1.5rem;margin-bottom:1.5rem}
        .section-title{font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:1.25rem}
        .presets{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
        @media(max-width:480px){.presets{grid-template-columns:1fr}}
        .preset-card{
            background:#0f172a;border:2px solid #334155;border-radius:10px;
            padding:1rem;cursor:pointer;transition:all .15s ease;text-align:left;
            display:flex;align-items:center;gap:.75rem;width:100%
        }
        .preset-card:hover{border-color:#4f46e5;background:#1e1b4b}
        .preset-card.active{border-color:#f59e0b;background:#1c1505}
        .preset-card.playing{border-color:#22c55e;background:#052e16}
        .preset-icon{font-size:1.5rem;flex-shrink:0}
        .preset-info{flex:1;min-width:0}
        .preset-name{font-size:.9rem;font-weight:600;color:#f1f5f9}
        .preset-desc{font-size:.75rem;color:#64748b;margin-top:.2rem}
        .preset-actions{display:flex;gap:.4rem;flex-shrink:0}
        .btn-play{
            background:#334155;color:#94a3b8;border:none;border-radius:6px;
            padding:.35rem .6rem;font-size:.75rem;cursor:pointer;transition:all .15s
        }
        .btn-play:hover{background:#475569;color:#f1f5f9}
        .btn-play.playing-anim{background:#065f46;color:#6ee7b7}
        .btn-use{
            background:#92400e;color:#fcd34d;border:none;border-radius:6px;
            padding:.35rem .6rem;font-size:.75rem;font-weight:600;cursor:pointer;transition:all .15s
        }
        .btn-use:hover{background:#b45309;color:#fef3c7}
        .btn-use.active-use{background:#064e3b;color:#6ee7b7}
        .divider{border:none;border-top:1px solid #334155;margin:1.25rem 0}
        .upload-zone{
            border:2px dashed #334155;border-radius:10px;padding:2rem;text-align:center;
            cursor:pointer;transition:all .2s;position:relative
        }
        .upload-zone:hover,.upload-zone.drag{border-color:#6366f1;background:#1e1b4b}
        .upload-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
        .upload-icon{font-size:2rem;margin-bottom:.5rem}
        .upload-label{font-size:.9rem;color:#94a3b8}
        .upload-label span{color:#818cf8;font-weight:600}
        .upload-hint{font-size:.75rem;color:#475569;margin-top:.35rem}
        .file-selected{background:#1e3a5f;border-color:#3b82f6;border-style:solid}
        .file-selected .upload-label{color:#93c5fd}
        .btn-upload{
            margin-top:1rem;width:100%;padding:.75rem;background:#1d4ed8;color:#fff;
            border:none;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;transition:all .2s;display:none
        }
        .btn-upload:hover{background:#1e40af}
        .btn-upload.visible{display:block}
        .status-bar{
            margin-top:1rem;padding:.7rem 1rem;border-radius:8px;
            font-size:.85rem;display:none
        }
        .status-bar.ok{background:#052e16;color:#4ade80;border:1px solid #065f46;display:block}
        .status-bar.err{background:#450a0a;color:#fca5a5;border:1px solid #7f1d1d;display:block}
        .current-badge{
            display:inline-flex;align-items:center;gap:.4rem;
            background:#1c1505;color:#fbbf24;border:1px solid #92400e;
            border-radius:999px;padding:.2rem .75rem;font-size:.75rem;font-weight:600;margin-bottom:.75rem
        }
        .wave{display:flex;align-items:center;gap:3px;height:18px;display:inline-flex}
        .wb{width:3px;border-radius:2px;background:#22c55e;animation:wv 1s ease-in-out infinite;opacity:0}
        .wb:nth-child(1){animation-delay:.0s;height:8px}
        .wb:nth-child(2){animation-delay:.1s;height:14px}
        .wb:nth-child(3){animation-delay:.2s;height:18px}
        .wb:nth-child(4){animation-delay:.1s;height:14px}
        .wb:nth-child(5){animation-delay:.0s;height:8px}
        .wb.on{opacity:1}
        @keyframes wv{0%,100%{transform:scaleY(.5)}50%{transform:scaleY(1.2)}}
        .back{display:inline-flex;align-items:center;gap:.4rem;color:#475569;text-decoration:none;font-size:.85rem;margin-bottom:1.5rem;transition:color .15s}
        .back:hover{color:#94a3b8}
    </style>
</head>
<body x-data="soundSettings(@js($config))" x-init="init()">
<div class="container">

    <a href="/admin" class="back">← Admin Panel</a>

    <h1>🔔 Notification Sound Settings</h1>
    <p class="subtitle">Piliin ang tunog na lalabas kapag may bagong Pending Approval na dumating.</p>

    {{-- Current active sound badge --}}
    <div class="current-badge">
        <template x-if="config.type === 'custom'">
            <span>📁 Custom File ang ginagamit ngayon</span>
        </template>
        <template x-if="config.type !== 'custom'">
            <span>✅ <span x-text="getPresetLabel(config.preset)"></span> ang ginagamit ngayon</span>
        </template>
    </div>

    {{-- ── PRESET SOUNDS ─────────────────────────────────────────── --}}
    <div class="section">
        <div class="section-title">Preset Sounds</div>
        <div class="presets">

            <template x-for="preset in presets" :key="preset.id">
                <div class="preset-card"
                     :class="{ active: config.type === 'preset' && config.preset === preset.id, playing: playingId === preset.id }"
                     @click="usePreset(preset.id)">
                    <span class="preset-icon" x-text="preset.icon"></span>
                    <div class="preset-info">
                        <div class="preset-name" x-text="preset.name"></div>
                        <div class="preset-desc" x-text="preset.desc"></div>
                    </div>
                    <div class="preset-actions">
                        <div class="wave" :id="'wave-' + preset.id">
                            <div class="wb" :class="{on: playingId === preset.id}"></div>
                            <div class="wb" :class="{on: playingId === preset.id}"></div>
                            <div class="wb" :class="{on: playingId === preset.id}"></div>
                            <div class="wb" :class="{on: playingId === preset.id}"></div>
                            <div class="wb" :class="{on: playingId === preset.id}"></div>
                        </div>
                        <button class="btn-play" :class="{'playing-anim': playingId === preset.id}"
                                @click.stop="previewPreset(preset.id)"
                                x-text="playingId === preset.id ? '▶ Playing' : '▶ Preview'">
                        </button>
                        <button class="btn-use"
                                :class="{'active-use': config.type === 'preset' && config.preset === preset.id}"
                                @click.stop="usePreset(preset.id)"
                                x-text="config.type === 'preset' && config.preset === preset.id ? '✓ Active' : 'Use'">
                        </button>
                    </div>
                </div>
            </template>

        </div>
        <div class="status-bar" id="preset-status" :class="presetStatus.type" x-show="presetStatus.msg" x-text="presetStatus.msg"></div>
    </div>

    {{-- ── CUSTOM UPLOAD ──────────────────────────────────────────── --}}
    <div class="section">
        <div class="section-title">Custom Sound File</div>

        <div class="upload-zone" :class="{'file-selected': selectedFile, drag: isDragging}"
             @dragover.prevent="isDragging=true" @dragleave="isDragging=false"
             @drop.prevent="handleDrop($event)">
            <input type="file" accept=".mp3,.wav,.ogg" @change="handleFileSelect($event)">
            <div class="upload-icon">🎵</div>
            <div class="upload-label">
                <template x-if="!selectedFile">
                    <span>I-drag dito o <span>i-click para pumili</span> ng MP3, WAV, o OGG file</span>
                </template>
                <template x-if="selectedFile">
                    <span x-text="'📄 ' + selectedFile.name"></span>
                </template>
            </div>
            <div class="upload-hint">Tanggap: MP3, WAV, OGG &nbsp;•&nbsp; Maximum: 4 MB</div>
        </div>

        <button class="btn-upload" :class="{visible: selectedFile}" @click="uploadFile()">
            ⬆ I-upload at Gamitin ang File na Ito
        </button>

        <div class="status-bar" :class="uploadStatus.type" x-show="uploadStatus.msg" x-text="uploadStatus.msg"></div>
    </div>

</div>

<script>
function soundSettings(initialConfig) {
    return {
        config: initialConfig,
        audioCtx: null,
        playingId: null,
        presetStatus: { type: '', msg: '' },
        uploadStatus: { type: '', msg: '' },
        selectedFile: null,
        isDragging: false,

        presets: [
            { id: 'classic_chime', icon: '🔔', name: 'Classic Chime',  desc: 'Dalawang tono, pataas (default)' },
            { id: 'soft_bell',     icon: '🎵', name: 'Soft Bell',      desc: 'Malambot, musical notes C6→E6' },
            { id: 'alert_ping',    icon: '⚡', name: 'Alert Ping',     desc: 'Mabilis, malinaw na signal' },
            { id: 'triple_tone',   icon: '🎶', name: 'Triple Tone',    desc: 'Tatlong pataas na nota' },
            { id: 'low_gong',      icon: '🪘', name: 'Low Gong',       desc: 'Malalim, matagal na tugtog' },
            { id: 'double_pulse',  icon: '📳', name: 'Double Pulse',   desc: 'Dalawang mabilis na ping' },
        ],

        init() {
            const unlock = () => {
                if (!this.audioCtx) this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            };
            document.addEventListener('click', unlock, { once: true });
        },

        getCtx() {
            if (!this.audioCtx) this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (this.audioCtx.state === 'suspended') this.audioCtx.resume();
            return this.audioCtx;
        },

        getPresetLabel(id) {
            const p = this.presets.find(p => p.id === id);
            return p ? p.name : id;
        },

        previewPreset(id) {
            this.playingId = id;
            this.playPreset(id);
            setTimeout(() => { if(this.playingId === id) this.playingId = null; }, 1200);
        },

        async usePreset(id) {
            this.previewPreset(id);
            try {
                const res = await fetch('{{ route('save.notification.sound') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ preset: id })
                });
                const data = await res.json();
                if (data.ok) {
                    this.config = data.config;
                    this.presetStatus = { type: 'ok', msg: `✅ Na-save! Gagamitin na ngayon ang "${this.getPresetLabel(id)}".` };
                    setTimeout(() => this.presetStatus = { type: '', msg: '' }, 4000);
                }
            } catch(e) {
                this.presetStatus = { type: 'err', msg: '❌ Hindi na-save. Subukan ulit.' };
            }
        },

        handleFileSelect(e) {
            this.selectedFile = e.target.files[0] || null;
            this.uploadStatus = { type: '', msg: '' };
        },

        handleDrop(e) {
            this.isDragging = false;
            this.selectedFile = e.dataTransfer.files[0] || null;
            this.uploadStatus = { type: '', msg: '' };
        },

        async uploadFile() {
            if (!this.selectedFile) return;

            // Client-side extension check before even sending
            const ext = this.selectedFile.name.split('.').pop().toLowerCase();
            if (!['mp3', 'wav', 'ogg'].includes(ext)) {
                this.uploadStatus = { type: 'err', msg: '❌ Tanggap lang ang MP3, WAV, o OGG na file.' };
                return;
            }

            const form = new FormData();
            form.append('sound_file', this.selectedFile);
            form.append('_token', document.querySelector('meta[name=csrf-token]').content);

            this.uploadStatus = { type: 'ok', msg: '⏳ Nag-u-upload... hintay lang po.' };
            try {
                const res = await fetch('{{ route('upload.notification.sound') }}', { method: 'POST', body: form });

                // Always try to parse as JSON regardless of status code
                let data;
                const contentType = res.headers.get('content-type') || '';
                if (contentType.includes('application/json')) {
                    data = await res.json();
                } else {
                    // Server returned HTML (error page) — show status code hint
                    const text = await res.text();
                    const statusHint = res.status === 422 ? 'Validation error (422)' : `Server error (${res.status})`;
                    this.uploadStatus = { type: 'err', msg: `❌ ${statusHint} — Subukan ulit o kontakin ang developer.` };
                    console.error('Upload failed — server response:', text);
                    return;
                }

                if (data.ok) {
                    this.config = data.config;
                    this.selectedFile = null;
                    // Preview the uploaded file immediately
                    const audio = new Audio(data.url + '?t=' + Date.now());
                    audio.volume = 0.8;
                    audio.play().catch(() => {});
                    this.uploadStatus = { type: 'ok', msg: `✅ Na-upload at naka-set na! (${data.url.split('/').pop()}) — Tumutugtog ngayon para sa preview.` };
                } else {
                    const errMsg = data.error || (data.errors ? Object.values(data.errors).flat().join(' ') : 'May error sa pag-upload.');
                    this.uploadStatus = { type: 'err', msg: `❌ ${errMsg}` };
                }
            } catch(e) {
                this.uploadStatus = { type: 'err', msg: `❌ Hindi ma-upload: ${e.message}` };
            }
        },


        playPreset(name) {
            const ctx = this.getCtx();
            const run = {
                classic_chime: () => { [880,1100].forEach((f,i)=>{ const o=ctx.createOscillator(),g=ctx.createGain(); o.connect(g);g.connect(ctx.destination); o.type='sine';o.frequency.value=f; const s=ctx.currentTime+i*.22; g.gain.setValueAtTime(0,s);g.gain.linearRampToValueAtTime(.35,s+.04);g.gain.exponentialRampToValueAtTime(.001,s+.5); o.start(s);o.stop(s+.55); }); },
                soft_bell:     () => { [1047,1319].forEach((f,i)=>{ const o=ctx.createOscillator(),g=ctx.createGain(); o.connect(g);g.connect(ctx.destination); o.type='sine';o.frequency.value=f; const s=ctx.currentTime+i*.3; g.gain.setValueAtTime(0,s);g.gain.linearRampToValueAtTime(.25,s+.02);g.gain.exponentialRampToValueAtTime(.001,s+.9); o.start(s);o.stop(s+1); }); },
                alert_ping:    () => { [440,880].forEach((f,i)=>{ const o=ctx.createOscillator(),g=ctx.createGain(); o.connect(g);g.connect(ctx.destination); o.type='square';o.frequency.value=f; const s=ctx.currentTime+i*.15; g.gain.setValueAtTime(0,s);g.gain.linearRampToValueAtTime(.15,s+.01);g.gain.exponentialRampToValueAtTime(.001,s+.2); o.start(s);o.stop(s+.22); }); },
                triple_tone:   () => { [880,1100,1319].forEach((f,i)=>{ const o=ctx.createOscillator(),g=ctx.createGain(); o.connect(g);g.connect(ctx.destination); o.type='sine';o.frequency.value=f; const s=ctx.currentTime+i*.2; g.gain.setValueAtTime(0,s);g.gain.linearRampToValueAtTime(.3,s+.03);g.gain.exponentialRampToValueAtTime(.001,s+.4); o.start(s);o.stop(s+.45); }); },
                low_gong:      () => { const o=ctx.createOscillator(),g=ctx.createGain(); o.connect(g);g.connect(ctx.destination); o.type='sine';o.frequency.value=110; const s=ctx.currentTime; g.gain.setValueAtTime(0,s);g.gain.linearRampToValueAtTime(.5,s+.05);g.gain.exponentialRampToValueAtTime(.001,s+1.8); o.start(s);o.stop(s+2); },
                double_pulse:  () => { [0,.18].forEach(d=>{ const o=ctx.createOscillator(),g=ctx.createGain(); o.connect(g);g.connect(ctx.destination); o.type='sine';o.frequency.value=990; const s=ctx.currentTime+d; g.gain.setValueAtTime(0,s);g.gain.linearRampToValueAtTime(.35,s+.02);g.gain.exponentialRampToValueAtTime(.001,s+.15); o.start(s);o.stop(s+.18); }); },
            };
            (run[name] || run['classic_chime'])();
        }
    }
}
</script>
<script src="//unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>