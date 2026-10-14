<div style="display: flex; align-items: center; gap: 11px; height: 100%; user-select: none;" class="fi-brand-custom-logo">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800;900&display=swap');

        .pmis-brand-title {
            color: #0d7c49;
            font-size: 23px;
            font-weight: 900;
            letter-spacing: -0.02em;
            line-height: 1;
            font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            transition: color 0.2s ease;
        }
        .pmis-brand-subtitle {
            color: #065f46;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            line-height: 1.22;
            white-space: nowrap;
            font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            transition: color 0.2s ease;
        }

        /* ☀️ Light Theme Icon Display */
        .pmis-logo-light {
            display: block !important;
        }
        .pmis-logo-dark {
            display: none !important;
        }

        /* 🌙 Dark Theme Neon Icon & Typography */
        .dark .pmis-logo-light {
            display: none !important;
        }
        .dark .pmis-logo-dark {
            display: block !important;
        }
        .dark .pmis-brand-title {
            color: #ffffff !important;
            text-shadow: 0 0 10px rgba(52, 211, 153, 0.35);
        }
        .dark .pmis-brand-subtitle {
            color: #cbd5e1 !important;
            letter-spacing: 0.12em;
            font-weight: 600;
        }
    </style>
    <img 
        src="{{ asset('images/pmis-emblem.png') }}" 
        alt="PMIS Logo" 
        class="pmis-logo-light"
        width="46"
        height="46"
        style="height: 46px; width: 46px; min-width: 46px; max-height: 46px; max-width: 46px; object-fit: contain; flex-shrink: 0;"
    />
    <img 
        src="{{ asset('images/pmis-emblem-dark.png') }}" 
        alt="PMIS Logo Dark" 
        class="pmis-logo-dark"
        width="46"
        height="46"
        style="height: 46px; width: 46px; min-width: 46px; max-height: 46px; max-width: 46px; object-fit: contain; flex-shrink: 0;"
    />
    <div style="display: flex; flex-direction: column; justify-content: center; text-align: left; line-height: 1;">
        <span class="pmis-brand-title">
            PMIS
        </span>
        <span class="pmis-brand-subtitle" style="margin-top: 3px;">
            PROGRESSIVE MORTGAGE
        </span>
        <span class="pmis-brand-subtitle">
            INFORMATION SYSTEM
        </span>
    </div>
</div>
