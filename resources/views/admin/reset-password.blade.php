<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('img/Mask group.png') }}">
    <title>H & R Ice Cream - Admin Update Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/customer/login.css') }}">
</head>

<body>
    <div class="left">
        <img src="{{ asset('img/signins.png') }}" alt="Ice Cream Image">
        <div class="logo-left">
            <a href="{{ route('landing') }}">
                <img src="{{ asset('img/logoleft.png') }}" alt="Quinjay Logo">
            </a>
        </div>
    </div>

    <div class="right">
        <div class="container">
            <div class="logo">
                <img src="{{ asset('img/logo.png') }}" alt="Quinjay Logo">
            </div>

            <div class="title">Update Password</div>
            <p style="font-size:14px;color:#6b7280;margin-bottom:1.5rem;">Enter your new password below.</p>

            @if (session('success'))
                <div style="margin-bottom:1rem;padding:0.75rem;background:#d4edda;color:#155724;border-radius:6px;">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div style="margin-bottom:1rem;padding:0.75rem;background:#f8d7da;color:#721c24;border-radius:6px;">{{ session('error') }}</div>
            @endif

            <form action="{{ route('admin.forgot-password.reset-password.submit') }}" method="POST">
                @csrf

                <div class="input-group {{ $errors->has('password') ? 'has-error' : '' }} input-group-password">
                    <div class="input-inner">
                        <input type="password" name="password" id="password" placeholder=" " required minlength="6">
                        <label for="password">New Password</label>
                        <button type="button" class="password-toggle" aria-label="Show password" onclick="togglePassword('password')" tabindex="-1">
                            <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="input-group {{ $errors->has('password_confirmation') ? 'has-error' : '' }} input-group-password">
                    <div class="input-inner">
                        <input type="password" name="password_confirmation" id="password_confirmation" placeholder=" " required minlength="6">
                        <label for="password_confirmation">Re-enter Password</label>
                        <button type="button" class="password-toggle" aria-label="Show password" onclick="togglePassword('password_confirmation')" tabindex="-1">
                            <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                    @error('password_confirmation')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="login-btn">Update Password</button>
            </form>

            <div class="signup" style="margin-top:1.5rem;">
                <a href="{{ route('admin.login') }}">Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(id) {
            var input = document.getElementById(id);
            var group = input.closest('.input-group-password') || input.closest('.input-group');
            var eye = group ? group.querySelector('.icon-eye') : document.querySelector('.icon-eye');
            var eyeOff = group ? group.querySelector('.icon-eye-off') : document.querySelector('.icon-eye-off');
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
