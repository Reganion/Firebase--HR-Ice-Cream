<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('img/Mask group.png') }}">
    <title>H & R Ice Cream - Admin Verify Code</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/customer/register.css') }}">
    <style>
        .verify-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #5b7efc;
            border-radius: 50%;
        }
        .verify-icon svg { width: 32px; height: 32px; }
        .verify-title { font-size: 26px; font-weight: 700; color: #111; margin-bottom: 0.5rem; text-align: center; }
        .verify-subtitle { font-size: 14px; color: #6b7280; margin-bottom: 1.5rem; text-align: center; }
        .verify-subtitle strong { color: #374151; }
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .otp-inputs input {
            width: 56px;
            height: 56px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #f0f4ff;
            color: #111;
        }
        .otp-inputs input:focus {
            outline: none;
            border-color: #5b7efc;
            background: #fff;
        }
        .otp-inputs input.error { border-color: #dc2626; }
        .verify-btn {
            width: 100%;
            padding: 14px;
            background: #5b7efc;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            margin-bottom: 1.5rem;
        }
        .verify-btn:hover { background: #4a6ee5; }
        .resend-row { text-align: center; font-size: 14px; color: #6b7280; margin-bottom: 1rem; }
        .resend-row button.resend-link {
            background: none; border: none; padding: 0; font: inherit;
            font-size: 14px; color: #5b7efc; font-weight: 500; cursor: pointer;
        }
        .resend-row button.resend-link:hover { text-decoration: underline; }
        .resend-row button.resend-link.disabled { color: #9ca3af; cursor: not-allowed; pointer-events: none; }
        .resend-row .timer { font-weight: 600; color: #374151; }
        .login-link-row { text-align: center; font-size: 14px; color: #6b7280; }
        .login-link-row a { color: #5b7efc; font-weight: 500; text-decoration: none; }
        .login-link-row a:hover { text-decoration: underline; }
    </style>
</head>

<body>
    <div class="left">
        <img src="{{ asset('img/signup.png') }}" alt="Ice Cream Image">
        <div class="logo-left">
          
                <img src="{{ asset('img/logoleft.png') }}" alt="H&R Ice Cream Logo">
            
        </div>
    </div>

    <div class="right">
        <div class="container">
            <div class="logo">
                <img src="{{ asset('img/logo.png') }}" alt="H&R Ice Cream Logo">
            </div>

            <div class="verify-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <path d="M9 12l2 2 4-4"/>
                </svg>
            </div>
            <h1 class="verify-title">Verify code</h1>
            <p class="verify-subtitle">Enter the code sent to <strong>{{ $email ?? '' }}</strong></p>

            @if (session('success'))
                <div style="margin-bottom:1rem;padding:0.75rem;background:#d4edda;color:#155724;border-radius:8px;font-size:14px;">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div style="margin-bottom:1rem;padding:0.75rem;background:#f8d7da;color:#721c24;border-radius:8px;font-size:14px;">{{ session('error') }}</div>
            @endif

            <form id="verify-form" action="{{ route('admin.forgot-password.verify-otp.submit') }}" method="POST">
                @csrf
                <input type="hidden" name="otp" id="otp-combined" value="{{ old('otp') }}">

                <div class="otp-inputs {{ $errors->has('otp') ? 'has-error' : '' }}">
                    <input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" data-idx="0" aria-label="Digit 1">
                    <input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" data-idx="1" aria-label="Digit 2">
                    <input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" data-idx="2" aria-label="Digit 3">
                    <input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" data-idx="3" aria-label="Digit 4">
                </div>
                @error('otp')
                    <p class="error-text" style="text-align:center;margin-bottom:1rem;color:#dc2626;font-size:14px;">{{ $message }}</p>
                @enderror

                <button type="submit" class="verify-btn">Verify</button>
            </form>

            <div class="resend-row">
                Didn't receive code?
                <form id="resend-form" action="{{ route('admin.forgot-password.resend-otp') }}" method="POST" style="display:inline;">
                    @csrf
                    <button type="submit" id="resend-btn" class="resend-link disabled" disabled>Resend now</button>
                </form>
                <span class="timer" id="timer"> 5:00</span>
            </div>
            <div class="login-link-row">
                <a href="{{ route('admin.login') }}">Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var inputs = document.querySelectorAll('.otp-inputs input');
            var combined = document.getElementById('otp-combined');
            var form = document.getElementById('verify-form');

            function updateCombined() {
                var val = '';
                inputs.forEach(function(inp) { val += inp.value; });
                combined.value = val;
            }

            inputs.forEach(function(input, i) {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/\D/g, '').slice(0, 1);
                    updateCombined();
                    if (this.value && i < inputs.length - 1) inputs[i + 1].focus();
                });
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && i > 0) inputs[i - 1].focus();
                });
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    var paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 4);
                    for (var j = 0; j < paste.length && j < inputs.length; j++) {
                        inputs[j].value = paste[j];
                        inputs[j].classList.remove('error');
                    }
                    if (paste.length > 0) inputs[Math.min(paste.length, inputs.length - 1)].focus();
                    updateCombined();
                });
            });

            var oldOtp = "{{ old('otp') }}";
            if (oldOtp && oldOtp.length <= 4) {
                for (var k = 0; k < oldOtp.length && k < inputs.length; k++) {
                    inputs[k].value = oldOtp[k];
                }
                updateCombined();
            }

            form.addEventListener('submit', function() { updateCombined(); });

            var timerEl = document.getElementById('timer');
            var resendBtn = document.getElementById('resend-btn');
            var totalSeconds = 5 * 60;
            var remaining = totalSeconds;

            function formatTime(s) {
                var m = Math.floor(s / 60);
                var sec = s % 60;
                return m + ':' + (sec < 10 ? '0' : '') + sec;
            }

            function tick() {
                remaining--;
                timerEl.textContent = formatTime(remaining);
                if (remaining <= 0) {
                    clearInterval(interval);
                    resendBtn.disabled = false;
                    resendBtn.classList.remove('disabled');
                    timerEl.textContent = '';
                }
            }

            var interval = setInterval(tick, 1000);
            timerEl.textContent = formatTime(remaining);
        })();
    </script>
</body>
</html>
