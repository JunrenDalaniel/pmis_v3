{{--
    PendingApprovalSoundWidget Blade View
    - Invisible widget that polls every 30 seconds via Livewire
    - Plays the selected notification sound (preset or custom file)
    - Sound preference is loaded from storage/app/pmis-sound-pref.json
--}}
<div
    wire:poll.30000ms="checkForNewPending"
    x-data="pendingApprovalSound(@js($soundConfig))"
    x-init="init()"
    @new-pending-approval.window="playSound($event.detail.count)"
    style="display:none;height:0;overflow:hidden;position:absolute;"
    aria-hidden="true"
>
</div>

<script>
function pendingApprovalSound(config) {
    return {
        audioCtx: null,
        config: config,

        init() {
            // Unlock AudioContext on first user interaction (browser autoplay policy)
            const unlock = () => {
                if (!this.audioCtx) {
                    this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
            };
            document.addEventListener('click', unlock, { once: true });
            document.addEventListener('keydown', unlock, { once: true });
        },

        getCtx() {
            if (!this.audioCtx) {
                this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (this.audioCtx.state === 'suspended') this.audioCtx.resume();
            return this.audioCtx;
        },

        playSound(count) {
            if (this.config.type === 'custom' && this.config.custom_url) {
                this.playCustomFile();
            } else {
                this.playPreset(this.config.preset || 'classic_chime');
            }
        },

        playCustomFile() {
            const audio = new Audio(this.config.custom_url);
            audio.volume = 0.8;
            audio.play().catch(() => {});
        },

        playPreset(name) {
            const ctx = this.getCtx();
            const presets = {

                // 🔔 Classic Chime — default (880Hz → 1100Hz sine)
                classic_chime: () => {
                    [880, 1100].forEach((freq, i) => {
                        const osc = ctx.createOscillator(), g = ctx.createGain();
                        osc.connect(g); g.connect(ctx.destination);
                        osc.type = 'sine'; osc.frequency.value = freq;
                        const s = ctx.currentTime + i * 0.22;
                        g.gain.setValueAtTime(0, s);
                        g.gain.linearRampToValueAtTime(0.35, s + 0.04);
                        g.gain.exponentialRampToValueAtTime(0.001, s + 0.5);
                        osc.start(s); osc.stop(s + 0.55);
                    });
                },

                // 🎵 Soft Bell — musical C6→E6 (1047→1319Hz)
                soft_bell: () => {
                    [1047, 1319].forEach((freq, i) => {
                        const osc = ctx.createOscillator(), g = ctx.createGain();
                        osc.connect(g); g.connect(ctx.destination);
                        osc.type = 'sine'; osc.frequency.value = freq;
                        const s = ctx.currentTime + i * 0.3;
                        g.gain.setValueAtTime(0, s);
                        g.gain.linearRampToValueAtTime(0.25, s + 0.02);
                        g.gain.exponentialRampToValueAtTime(0.001, s + 0.9);
                        osc.start(s); osc.stop(s + 1.0);
                    });
                },

                // ⚡ Alert Ping — snappy square wave (440→880Hz)
                alert_ping: () => {
                    [440, 880].forEach((freq, i) => {
                        const osc = ctx.createOscillator(), g = ctx.createGain();
                        osc.connect(g); g.connect(ctx.destination);
                        osc.type = 'square'; osc.frequency.value = freq;
                        const s = ctx.currentTime + i * 0.15;
                        g.gain.setValueAtTime(0, s);
                        g.gain.linearRampToValueAtTime(0.15, s + 0.01);
                        g.gain.exponentialRampToValueAtTime(0.001, s + 0.2);
                        osc.start(s); osc.stop(s + 0.22);
                    });
                },

                // 🎶 Triple Tone — three ascending notes (880→1100→1319Hz)
                triple_tone: () => {
                    [880, 1100, 1319].forEach((freq, i) => {
                        const osc = ctx.createOscillator(), g = ctx.createGain();
                        osc.connect(g); g.connect(ctx.destination);
                        osc.type = 'sine'; osc.frequency.value = freq;
                        const s = ctx.currentTime + i * 0.2;
                        g.gain.setValueAtTime(0, s);
                        g.gain.linearRampToValueAtTime(0.3, s + 0.03);
                        g.gain.exponentialRampToValueAtTime(0.001, s + 0.4);
                        osc.start(s); osc.stop(s + 0.45);
                    });
                },

                // 🪘 Low Gong — deep bass tone (110Hz, long decay)
                low_gong: () => {
                    const osc = ctx.createOscillator(), g = ctx.createGain();
                    osc.connect(g); g.connect(ctx.destination);
                    osc.type = 'sine'; osc.frequency.value = 110;
                    const s = ctx.currentTime;
                    g.gain.setValueAtTime(0, s);
                    g.gain.linearRampToValueAtTime(0.5, s + 0.05);
                    g.gain.exponentialRampToValueAtTime(0.001, s + 1.8);
                    osc.start(s); osc.stop(s + 2.0);
                },

                // 📳 Double Pulse — two quick identical pings
                double_pulse: () => {
                    [0, 0.18].forEach(delay => {
                        const osc = ctx.createOscillator(), g = ctx.createGain();
                        osc.connect(g); g.connect(ctx.destination);
                        osc.type = 'sine'; osc.frequency.value = 990;
                        const s = ctx.currentTime + delay;
                        g.gain.setValueAtTime(0, s);
                        g.gain.linearRampToValueAtTime(0.35, s + 0.02);
                        g.gain.exponentialRampToValueAtTime(0.001, s + 0.15);
                        osc.start(s); osc.stop(s + 0.18);
                    });
                },
            };

            (presets[name] || presets['classic_chime'])();
        }
    }
}
</script>