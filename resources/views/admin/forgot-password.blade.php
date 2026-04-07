<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('img/Mask group.png') }}">
    <title>H & R Ice Cream - Admin Forgot Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/customer/login.css') }}">
</head>

<body>
    <div class="left">
        <img src="{{ asset('img/signins.png') }}" alt="Ice Cream Image">
        <div class="logo-left">
                <img src="{{ asset('img/logoleft.png') }}" alt="Quinjay Logo">
        </div>
    </div>

    <div class="right">
        <div class="container">
            <div class="logo">
                <img src="{{ asset('img/logo.png') }}" alt="Quinjay Logo">
            </div>

            <div class="title">Forgot Password</div>
            <p style="font-size:14px;color:#6b7280;margin-bottom:1.5rem;">Enter your email and we'll send you a verification code to reset your password.</p>

            @if (session('success'))
                <div style="margin-bottom:1rem;padding:0.75rem;background:#d4edda;color:#155724;border-radius:6px;">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div style="margin-bottom:1rem;padding:0.75rem;background:#f8d7da;color:#721c24;border-radius:6px;">{{ session('error') }}</div>
            @endif

            <form action="{{ route('admin.forgot-password.submit') }}" method="POST">
                @csrf
                <div class="input-group {{ $errors->has('email') ? 'has-error' : '' }}">
                    <div class="input-inner">
                        <input type="email" name="email" id="email" placeholder=" " value="{{ old('email') }}" required>
                        <label for="email">Email Address</label>
                    </div>
                    @error('email')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="login-btn">Send verification code</button>
            </form>

            <div class="signup" style="margin-top:1.5rem;">
                Remember your password? <a href="{{ route('admin.login') }}">Back to Login</a>
            </div>
        </div>
    </div>
</body>

</html>
