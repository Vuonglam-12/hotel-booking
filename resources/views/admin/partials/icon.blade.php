{{-- Local Lucide SVG sprite; source and licenses are in public/vendor/lucide/. --}}
<svg class="admin-icon {{ $iconClass ?? '' }}" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <use href="{{ asset('vendor/lucide/aurel-admin.svg') }}#{{ $name }}"></use>
</svg>
