@php
    // Sleek Accent Cardlet Style with Guaranteed Scoped CSS Colors
@endphp

<style>
    .pmis-comment-card {
        border-radius: 9px;
        padding: 9px 13px;
        margin-bottom: 9px;
        border: 1px solid #e2e8f0;
        background-color: #ffffff;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        transition: border-color 0.15s ease;
    }
    .dark .pmis-comment-card {
        background-color: #0b1120;
        border-color: #1e293b;
    }

    /* Left 3.5px accent bar */
    .pmis-border-indigo  { border-left: 3.5px solid #6366f1 !important; }
    .pmis-border-amber   { border-left: 3.5px solid #f59e0b !important; }
    .pmis-border-emerald { border-left: 3.5px solid #10b981 !important; }
    .pmis-border-rose    { border-left: 3.5px solid #f43f5e !important; }

    /* Author text color */
    .pmis-text-sky     { color: #0284c7; }
    .dark .pmis-text-sky { color: #38bdf8; }
    .pmis-text-amber   { color: #d97706; }
    .dark .pmis-text-amber { color: #fbbf24; }
    .pmis-text-rose    { color: #e11d48; }
    .dark .pmis-text-rose { color: #fb7185; }
    .pmis-text-emerald { color: #059669; }
    .dark .pmis-text-emerald { color: #34d399; }

    /* Comment text color (Teal / Emerald / Rose) */
    .pmis-comment-teal { color: #0f766e; font-weight: 500; }
    .dark .pmis-comment-teal { color: #2dd4bf; font-weight: 500; }
    .pmis-comment-rose { color: #be123c; font-weight: 500; }
    .dark .pmis-comment-rose { color: #fda4af; font-weight: 500; }
    .pmis-comment-emerald { color: #047857; font-weight: 500; }
    .dark .pmis-comment-emerald { color: #6ee7b7; font-weight: 500; }

    /* Date & Timestamp color (Rose / Coral) */
    .pmis-date-rose { color: #e11d48; }
    .dark .pmis-date-rose { color: #fb7185; }
</style>

@if ($notes->isEmpty())
    <div class="py-4 px-4 rounded-xl text-center space-y-1.5 border border-dashed border-slate-300 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40">
        <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">No comments in thread yet</p>
        <p class="text-[11px] text-slate-400 dark:text-slate-500">Evaluator assessments, deficiency notes, and branch compliance replies will appear here.</p>
    </div>
@else
    <div class="max-h-[30rem] overflow-y-auto pr-1 py-1">
        @foreach ($notes as $n)
            @php
                $author = $n->user?->name ?? 'System Staff';
                $roleName = $n->user?->role?->name ?? ($n->user ? 'Staff' : 'System');
                $stage = $n->stage ?? 'Comment';
                $date = $n->created_at ? $n->created_at->format('F d, Y h:i A') : 'N/A';
                $timeAgo = $n->created_at ? $n->created_at->diffForHumans() : '';

                $isBranch = str_contains(strtolower($stage), 'branch')
                    || str_contains(strtolower($roleName), 'branch')
                    || str_contains(strtolower($roleName), 'document specialist');

                $isDisapproved = str_contains(strtolower($stage), 'disapprov')
                    || str_contains(strtolower($stage), 'reject')
                    || str_contains(strtolower($stage), 'deficien');

                $isApproved = str_contains(strtolower($stage), 'approv') && ! $isDisapproved && ! str_contains(strtolower($stage), 'pending');

                if ($isDisapproved) {
                    $borderClass = 'pmis-border-rose';
                    $authorClass = 'pmis-text-rose';
                    $commentClass = 'pmis-comment-rose';
                    $badgeStyle = 'background: rgba(244,63,94,0.15); color: #fb7185; border: 1px solid rgba(244,63,94,0.3);';
                } elseif ($isApproved) {
                    $borderClass = 'pmis-border-emerald';
                    $authorClass = 'pmis-text-emerald';
                    $commentClass = 'pmis-comment-emerald';
                    $badgeStyle = 'background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3);';
                } elseif ($isBranch) {
                    $borderClass = 'pmis-border-amber';
                    $authorClass = 'pmis-text-amber';
                    $commentClass = 'pmis-comment-teal';
                    $badgeStyle = 'background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3);';
                } else {
                    // Evaluator / Head Office (Default Indigo/Sky)
                    $borderClass = 'pmis-border-indigo';
                    $authorClass = 'pmis-text-sky';
                    $commentClass = 'pmis-comment-teal';
                    $badgeStyle = 'background: rgba(99,102,241,0.15); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.3);';
                }
            @endphp

            <!-- Sleek Accent Cardlet with Live Guaranteed Text Colors -->
            <div class="pmis-comment-card {{ $borderClass }}">
                <!-- Top Row: Author, Role Badge, Stage, and Timestamp -->
                <div class="flex items-center justify-between flex-wrap gap-x-2 gap-y-1 pb-1.5 border-b border-slate-100 dark:border-slate-800/80">
                    <!-- Left: Author & Roles -->
                    <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                        <span class="font-bold text-xs sm:text-[13px] {{ $authorClass }} tracking-tight">
                            {{ $author }}:
                        </span>

                        <span class="px-1.5 py-0.5 rounded text-[10.5px] font-semibold" style="{{ $badgeStyle }}">
                            {{ $roleName }}
                        </span>

                        <span class="text-slate-300 dark:text-slate-700 text-xs select-none">•</span>

                        <span class="text-[11px] font-medium text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800/90 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700/60">
                            {{ $stage }}
                        </span>
                    </div>

                    <!-- Right: Date & Timestamp (Rose/Reddish) -->
                    <div class="flex items-center gap-1 text-[11px] font-mono pmis-date-rose ml-auto">
                        <span class="text-[10px]">⏱️</span>
                        <span>&mdash; {{ $date }}</span>
                        @if ($timeAgo)
                            <span class="font-sans text-[10px] text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800/80 px-1 py-0.2 rounded">
                                ({{ $timeAgo }})
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Main Comment Content: Guaranteed Vibrant Teal / Rose / Emerald -->
                <div class="pt-1 text-[13.5px] leading-relaxed {{ $commentClass }} whitespace-pre-line pl-1 font-sans">
                    &mdash; {!! nl2br(e($n->note)) !!}
                </div>
            </div>
        @endforeach
    </div>
@endif
