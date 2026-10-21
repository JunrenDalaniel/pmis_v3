@if (auth()->check())
<div
    x-data="inactivityDetector({
        idleLimitSeconds: {{ config('pmis.idle_timeout_seconds', 900) }}, // 15 mins
        warningSeconds: {{ config('pmis.idle_warning_seconds', 120) }}, // 2 mins warning
        keepAliveUrl: '{{ route('admin.session.keep-alive') }}',
        idleLogoutUrl: '{{ route('admin.session.idle-logout') }}',
        loginUrl: '{{ filament()->getLoginUrl() }}',
        csrfToken: '{{ csrf_token() }}'
    })"
    x-init="init()"
    class="relative"
>
    <!-- Inactivity Warning Modal -->
    <div
        x-show="showWarning"
        x-cloak
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-md"
        role="dialog"
        aria-modal="true"
        aria-labelledby="inactivity-modal-title"
    >
        <div
            x-show="showWarning"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-900 border border-amber-200 dark:border-amber-800/60 shadow-2xl p-6 text-center"
        >
            <!-- Animated Warning Icon -->
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 mb-4 ring-8 ring-amber-500/15 animate-pulse" style="width: 64px; height: 64px;">
                <svg style="width: 32px; height: 32px; min-width: 32px; min-height: 32px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <!-- Title -->
            <h3 id="inactivity-modal-title" class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">
                Babala sa Kawalan ng Aktibidad
            </h3>
            <p class="text-[11px] uppercase font-semibold text-amber-600 dark:text-amber-400 tracking-wider mt-0.5">
                Session Inactivity Warning
            </p>

            <!-- Explanation -->
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                Wala nang aktibidad sa PMIS sa nakalipas na 13 minuto. Para sa seguridad ng mga datos at loan accounts, awtomatikong magla-log out sa loob ng:
            </p>

            <!-- Countdown Display -->
            <div class="my-5 inline-flex items-center gap-2.5 px-6 py-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700/60 shadow-inner">
                <svg style="width: 20px; height: 20px; min-width: 20px; min-height: 20px; animation-duration: 4s;" class="text-amber-600 dark:text-amber-400 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span class="text-3xl font-extrabold tabular-nums tracking-wider text-amber-700 dark:text-amber-300" x-text="formatCountdown(remainingSeconds)">
                    02:00
                </span>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-2.5 mt-2">
                <button
                    @click="stayLoggedIn()"
                    type="button"
                    :disabled="isLoggingOut"
                    class="w-full inline-flex justify-center items-center gap-2 px-5 py-3 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] shadow-lg shadow-emerald-600/25 transition-all cursor-pointer disabled:opacity-50"
                >
                    <svg style="width: 20px; height: 20px; min-width: 20px; min-height: 20px;" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    Manatiling Naka-Login (Stay Logged In)
                </button>

                <button
                    @click="logout()"
                    type="button"
                    :disabled="isLoggingOut"
                    class="w-full inline-flex justify-center items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors cursor-pointer disabled:opacity-50"
                >
                    <span x-show="!isLoggingOut">Mag-Log Out Na (Log Out Now)</span>
                    <span x-show="isLoggingOut">Nagla-log out...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function inactivityDetector(config) {
    return {
        idleLimitMs: config.idleLimitSeconds * 1000,
        warningWindowMs: config.warningSeconds * 1000,
        keepAliveUrl: config.keepAliveUrl,
        idleLogoutUrl: config.idleLogoutUrl,
        loginUrl: config.loginUrl,
        csrfToken: config.csrfToken,

        lastActivity: Date.now(),
        showWarning: false,
        remainingSeconds: config.warningSeconds,
        isLoggingOut: false,
        tickIntervalId: null,
        lastThrottleTime: 0,

        init() {
            this.lastActivity = Date.now();

            // Bind user activity listeners
            const events = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'];
            events.forEach(eventName => {
                window.addEventListener(eventName, () => this.handleActivity(), { passive: true });
            });

            // Tab switch / Window focus listener
            document.addEventListener('visibilitychange', () => this.handleVisibilityOrFocus());
            window.addEventListener('focus', () => this.handleVisibilityOrFocus());

            // Run 1-second interval ticker
            this.tickIntervalId = setInterval(() => this.tick(), 1000);
        },

        handleActivity() {
            // Once the warning modal is open, user activity does NOT auto-dismiss it;
            // they must explicitly click "Manatiling Naka-Login" or "Mag-Log Out Na".
            if (this.showWarning || this.isLoggingOut) {
                return;
            }

            // Throttle activity resets to at most once every 1000ms
            const now = Date.now();
            if (now - this.lastThrottleTime > 1000) {
                this.lastThrottleTime = now;
                this.lastActivity = now;
            }
        },

        handleVisibilityOrFocus() {
            if (this.isLoggingOut) return;

            const now = Date.now();
            const elapsed = now - this.lastActivity;

            if (elapsed >= this.idleLimitMs) {
                this.logout();
            } else if (elapsed >= (this.idleLimitMs - this.warningWindowMs)) {
                this.showWarning = true;
                const remainingMs = this.idleLimitMs - elapsed;
                this.remainingSeconds = Math.max(1, Math.floor(remainingMs / 1000));
            }
        },

        tick() {
            if (this.isLoggingOut) return;

            const now = Date.now();
            const elapsed = now - this.lastActivity;

            if (elapsed >= this.idleLimitMs) {
                this.logout();
                return;
            }

            const threshold = this.idleLimitMs - this.warningWindowMs;
            if (elapsed >= threshold) {
                this.showWarning = true;
                const remainingMs = this.idleLimitMs - elapsed;
                this.remainingSeconds = Math.max(1, Math.floor(remainingMs / 1000));
            } else {
                if (this.showWarning) {
                    this.showWarning = false;
                }
            }
        },

        formatCountdown(seconds) {
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            return `${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}`;
        },

        async stayLoggedIn() {
            if (this.isLoggingOut) return;

            try {
                // Ping server to keep session alive and refresh token
                const response = await fetch(this.keepAliveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.csrf_token) {
                        this.csrfToken = data.csrf_token;
                    }
                }
            } catch (e) {
                console.warn('Keep-alive ping failed, resetting local timer:', e);
            }

            // Reset local timer and hide modal
            this.lastActivity = Date.now();
            this.showWarning = false;
            this.remainingSeconds = config.warningSeconds;
        },

        async logout() {
            if (this.isLoggingOut) return;
            this.isLoggingOut = true;

            clearInterval(this.tickIntervalId);

            try {
                const response = await fetch(this.idleLogoutUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    window.location.href = data.redirect || this.loginUrl;
                    return;
                }
            } catch (e) {
                console.error('Logout request failed:', e);
            }

            // Fallback redirect
            window.location.href = this.loginUrl;
        }
    };
}
</script>
@endif
