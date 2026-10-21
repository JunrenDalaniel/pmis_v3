@if (session('inactivity_message'))
<div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-950/50 dark:border-amber-700/60 p-4 shadow-sm animate-fade-in">
    <div class="flex items-start gap-3">
        <div class="flex-shrink-0 text-amber-600 dark:text-amber-400 mt-0.5">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
        </div>
        <div class="flex-1">
            <h4 class="text-xs font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">
                Session Timed Out / Natapos ang Session
            </h4>
            <p class="text-xs text-amber-700 dark:text-amber-300/90 mt-0.5 leading-relaxed">
                {{ session('inactivity_message') }}
            </p>
        </div>
    </div>
</div>
@endif
