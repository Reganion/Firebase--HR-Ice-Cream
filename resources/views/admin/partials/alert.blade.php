@php
    $alertType = session('success') ? 'success' : (session('error') || $errors->any() ? 'error' : null);
    $alertMessage = session('success') ?? session('error') ?? ($errors->any() ? $errors->first() : null);
@endphp

@if ($alertType && $alertMessage)
    <div class="global-alert {{ $alertType }}" id="globalAlert">
        <span class="material-symbols-outlined">
            {{ $alertType === 'success' ? 'check_circle' : 'error' }}
        </span>
        <p>{{ $alertMessage }}</p>
    </div>
@endif

<script>
    (function() {
        if (window.showGlobalAlert) return;
        window.showGlobalAlert = function(message, type) {
            const alertType = type === 'success' ? 'success' : 'error';
            const icon = alertType === 'success' ? 'check_circle' : 'error';

            const existing = document.getElementById('globalAlert');
            if (existing) existing.remove();

            const el = document.createElement('div');
            el.id = 'globalAlert';
            el.className = 'global-alert ' + alertType;
            el.innerHTML =
                '<span class="material-symbols-outlined">' + icon + '</span>' +
                '<p>' + String(message || '') + '</p>';
            document.body.appendChild(el);

            setTimeout(function() {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-10px)';
                setTimeout(function() {
                    el.remove();
                }, 300);
            }, 3000);
        };
    })();
</script>
