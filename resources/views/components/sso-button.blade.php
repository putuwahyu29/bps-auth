@php
    $themeClass = ($driver === 'majapahit') ? 'bps-sso-btn-majapahit' : 'bps-sso-btn-pusat';
@endphp

<a href="{{ $url }}" {{ $attributes->merge(['class' => 'bps-sso-btn ' . $themeClass . ' ' . $class]) }}>
    @if($icon)
        @if($driver === 'majapahit')
            <svg class="bps-sso-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <polyline points="16 11 18 13 22 9"/>
            </svg>
        @else
            <svg class="bps-sso-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                <path d="m9 12 2 2 4-4"/>
            </svg>
        @endif
    @endif
    <span>{{ $text }}</span>
</a>

<style>
    .bps-sso-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        color: #ffffff !important;
        font-size: 0.875rem;
        font-weight: 500;
        line-height: 1.25rem;
        border-radius: 0.375rem;
        text-decoration: none;
        cursor: pointer;
        transition: background-color 0.15s ease-in-out;
        border: none;
    }
    .bps-sso-btn-pusat {
        background-color: #0284c7;
    }
    .bps-sso-btn-pusat:hover {
        background-color: #0369a1;
        color: #ffffff !important;
    }
    .bps-sso-btn-majapahit {
        background-color: #6d28d9;
    }
    .bps-sso-btn-majapahit:hover {
        background-color: #5b21b6;
        color: #ffffff !important;
    }
    .bps-sso-btn:active {
        opacity: 0.95;
    }
    .bps-sso-icon {
        width: 1.125rem;
        height: 1.125rem;
        flex-shrink: 0;
    }
</style>
