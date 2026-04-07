<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('img/Mask group.png') }}">
    <title>Quinjay Ice Cream - Login</title>

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
            <a href="{{ route('customer.home') }}">
                <img src="{{ asset('img/logoleft.png') }}" alt="Quinjay Logo">
            </a>
        </div>

    </div>

    <!-- RIGHT LOGIN FORM -->
    <div class="right">
        <div class="container">

            <div class="logo">
                <img src="{{ asset('img/logo.png') }}" alt="Quinjay Logo">
            </div>

            <div class="title desktop-title">Sign In</div>
            <div class="title mobile-title">Login to your Account</div>

            @if (session('success'))
                <div class="alert alert-success" style="margin-bottom:1rem;padding:0.75rem;background:#d4edda;color:#155724;border-radius:6px;">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div style="margin-bottom:1rem;padding:0.75rem;background:#f8d7da;color:#721c24;border-radius:6px;">{{ session('error') }}</div>
            @endif

            <!-- LOGIN FORM START -->
            <form id="login-form" action="{{ route('customer.login.submit') }}" method="POST">
                @csrf

                <div class="input-group {{ $errors->has('email') ? 'has-error' : '' }}">
                    <div class="input-inner">
                        <input type="email" name="email" id="email" placeholder=" " value="{{ old('email') }}" >
                        <label for="email">Email Address</label>
                    </div>
                    @error('email')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="input-group {{ $errors->has('password') ? 'has-error' : '' }} input-group-password">
                    <div class="input-inner">
                        <input type="password" name="password" id="password" placeholder=" ">
                        <label for="password">Password</label>
                        <button type="button" class="password-toggle" aria-label="Show password" onclick="togglePassword()" tabindex="-1">
                            <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="forgot-password">
                    <a href="{{ route('customer.forgot-password') }}">Forgot Password?</a>
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>
            <!-- LOGIN FORM END -->

            <div class="divider"><span>Or, Sign In With</span></div>
            <a href="#" class="google-btn" style="display:flex;align-items:center;justify-content:center;gap:10px;text-decoration:none;color:inherit;">
                <img src="{{ asset('img/google.png') }}" alt="Google Logo"> Sign In with Google
            </a>

            <div class="signup">
                Don’t have an account?
                <a href="{{ route('customer.register') }}">Sign up</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const eye = document.querySelector('.input-group-password .icon-eye');
            const eyeOff = document.querySelector('.input-group-password .icon-eye-off');
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
