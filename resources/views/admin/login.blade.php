<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="icon" href="{{ asset('img/Mask group.png') }}">
    <title>H & R Ice Cream - Admin Login</title>

    <!-- Import Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/customer/login.css') }}">
</head>

<body>

    <!-- LEFT IMAGE PANEL -->
    <div class="left">
        <img src="{{ asset('img/signins.png') }}" alt="Ice Cream Image">
        <div class="logo-left">
                <img src="{{ asset('img/logoleft.png') }}" alt="Quinjay Logo">
        </div>
    </div>

    <!-- RIGHT LOGIN FORM -->
    <div class="right">
            <!-- Decorative shapes -->
    <div class="right-design">
        <span class="circle circle1"></span>
        <span class="circle circle2"></span>
        <span class="circle circle3"></span>
    </div>

        <div class="container">

            <div class="logo">
                <img src="{{ asset('img/logo.png') }}" alt="Quinjay Logo">
            </div>

            @if(session('success'))
                <div style="margin-bottom:1rem;padding:0.75rem;background:#d4edda;color:#155724;border-radius:8px;font-size:14px;">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div style="margin-bottom:1rem;padding:0.75rem;background:#f8d7da;color:#721c24;border-radius:8px;font-size:14px;">{{ session('error') }}</div>
            @endif

            <div class="title desktop-title">Admin Sign In</div>
            <div class="title mobile-title">Login to Admin</div>

            <form id="login-form" action="{{ route('admin.login.submit') }}" method="POST">
                @csrf

                <div class="input-group {{ $errors->has('email') ? 'has-error' : '' }}">
                    <div class="input-inner">
                        <input type="email" name="email" id="email" placeholder=" " value="{{ old('email') }}">
                        <label for="email">Email Address</label>
                    </div>
                    @if($errors->has('email'))
                        <span class="error-text">{{ $errors->first('email') }}</span>
                    @endif
                </div>

                <div class="input-group {{ $errors->has('password') ? 'has-error' : '' }} input-group-password">
                    <div class="input-inner">
                        <input type="password" name="password" id="password" placeholder=" ">
                        <label for="password">Password</label>
                        <button type="button" class="password-toggle" aria-label="Show password" onclick="togglePassword('password')" tabindex="-1">
                            <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                    @if($errors->has('password'))
                        <span class="error-text">{{ $errors->first('password') }}</span>
                    @endif
                </div>

                <div class="forgot-password">
                    <a href="{{ route('admin.forgot-password') }}">Forgot Password?</a>
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>
        </div>
    </div>

    <script>
        // Force a fresh page when restored from browser back/forward cache to avoid stale CSRF tokens.
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        function togglePassword(id) {
            const input = document.getElementById(id);
            const group = input.closest('.input-group-password') || input.closest('.input-group');
            if (!group) return;
            const eye = group.querySelector('.icon-eye');
            const eyeOff = group.querySelector('.icon-eye-off');
            if (input.type === 'password') {
                input.type = 'text';
                if (eye) eye.style.display = 'none';
                if (eyeOff) eyeOff.style.display = 'block';
            } else {
                input.type = 'password';
                if (eye) eye.style.display = 'block';
                if (eyeOff) eyeOff.style.display = 'none';
            }
        }
    </script>
</body>

</html>
