@if (session('inactivity_message'))
<div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; border-radius: 12px; border: 1px solid rgba(245, 158, 11, 0.4); background: rgba(254, 243, 199, 0.9); color: #92400e; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); text-align: left;">
    <svg style="width: 20px; height: 20px; min-width: 20px; min-height: 20px; flex-shrink: 0; color: #d97706; margin-top: 2px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
    </svg>
    <div style="flex: 1;">
        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #b45309;">
            Session Timed Out / Natapos ang Session
        </div>
        <div style="font-size: 12px; color: #78350f; margin-top: 3px; line-height: 1.45;">
            {{ session('inactivity_message') }}
        </div>
    </div>
</div>
@endif
