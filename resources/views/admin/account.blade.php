@extends('admin.layout.layout')

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('img/logo.png') }}">
    @section('title', 'Account Management')

    <style>
        html,
        body {
            height: 100%;
            margin: 0;
            overflow: hidden;
        }

        /* =========================
           CONTENT AREA
        ========================== */
        .content-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 10px 10px 0;
            overflow: hidden;
            background: #f2f2f2;
            border-top-left-radius: 30px;
            min-height: 0;
        }

        /* =========================
           ACCOUNT PAGE LAYOUT
        ========================== */
        .account-page {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
        }


        /* HEADER */
        .account-header {
            margin-bottom: 20px;
            flex-shrink: 0;
        }

        .account-header h2 {
            font-size: 22px;
            font-weight: 600;
        }

        /* BODY */
        .account-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
        }


        /* SCROLLABLE CARD */
        .account-scroll {
            flex: 1;
            overflow-y: auto;
            padding-right: 6px;
            padding-bottom: 0;
        }


        .account-card {
            background: #fff;
            border-top-right-radius: 24px;
            border-top-left-radius: 24px;
            padding: 35px 40px;
            box-sizing: border-box;
            margin: 0;

            min-height: 100%;
            display: flex;
            flex-direction: column;
        }



        .account-card-scroll {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding-right: 6px;
        }



        /* =========================
           PROFILE
        ========================== */
        .profile-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 35px;
        }

        .profile-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .profile-avatar-wrap {
            position: relative;
            flex-shrink: 0;
            width: 90px;
            height: 90px;
        }

        .profile-avatar-wrap img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
        }

        .profile-image-input {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .profile-camera-btn {
            position: absolute;
            right: 0;
            bottom: 0;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #eee;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            pointer-events: none;
            cursor: pointer;
        }

        .profile-camera-btn svg {
            width: 16px;
            height: 16px;
            color: #555;
        }

        .account-form.is-editing .profile-camera-btn {
            display: flex;
            pointer-events: auto;
        }

        .form-group input[readonly] {
            background: #f7f7f7;
            color: #333;
            cursor: default;
        }

        .account-form.is-editing .form-group input:not([readonly]) {
            background: #fff;
            cursor: text;
        }

        .profile-info h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .profile-info small {
            color: #888;
            font-size: 14px;
        }

        .profile-actions {
            display: flex;
            gap: 12px;
        }

        .btn-outline {
            padding: 10px 18px;
            border-radius: 12px;
            border: 1px solid #ddd;
            background: #fff;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-light {
            padding: 10px 18px;
            border-radius: 12px;
            border: none;
            background: #f2f2f2;
            cursor: pointer;
            font-weight: 500;
        }

        /* =========================
           FORM
        ========================== */


        .form-section h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 14px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }


        .form-group label {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid #ddd;
            font-size: 14px;
        }

        .divider {
            height: 1px;
            background: #eee;
            margin: 25px 0;
        }

        .password-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 24px;
            align-items: end;
        }


        .password-row .form-group {
            margin-bottom: 0;
        }

        .change-btn {
            height: 48px;
            padding: 0 22px;
            border-radius: 12px;
            border: none;
            background: #f2f2f2;
            font-weight: 500;
            cursor: pointer;
        }


        /* =========================
           RESPONSIVE
        ========================== */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .content-area {
                padding: 8px;
                border-radius: 0;
            }

            .account-card {
                padding: 22px;
                border-radius: 24px;
            }
        }

        .form-section,
        .form-row,
        .password-row {
            width: 100%;
        }

        .single-col {
            max-width: 49%;
        }

        .single-cols {
            max-width: 57%;
        }

        .form-alert {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 14px;
            position: relative;
            padding-right: 40px;
            transition: opacity 0.2s ease;
        }

        .form-alert-success {
            background: #eaf9ef;
            border: 1px solid #b9e8c6;
            color: #17733b;
        }

        .form-alert-error {
            background: #fff0f0;
            border: 1px solid #f1c0c0;
            color: #a12e2e;
        }

        .form-alert.is-hidden {
            opacity: 0;
            pointer-events: none;
        }

        .alert-close-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 24px;
            height: 24px;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: inherit;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        .alert-close-btn:hover {
            background: rgba(0, 0, 0, 0.06);
        }

        .field-error {
            display: block;
            margin-top: 6px;
            color: #a12e2e;
            font-size: 12px;
        }

        .otp-error {
            margin-bottom: 10px;
        }

        .otp-state-wrap {
            width: 100%;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px 0;
        }

        .otp-top-bar {
            width: 100%;
            display: flex;
            justify-content: flex-start;
            margin-bottom: 6px;
        }

        .otp-state-card {
            width: 100%;
            max-width: 520px;
            text-align: center;
        }

        .password-change-center-card {
            width: 100%;
            max-width: 560px;
            text-align: left;
        }

        .password-change-center-card h4 {
            text-align: center;
            font-size: 22px;
            margin-bottom: 10px;
        }

        .password-change-sub {
            text-align: center;
            color: #666;
            margin-bottom: 22px;
            font-size: 14px;
        }

        .password-change-actions {
            margin-top: 16px;
            display: flex;
            justify-content: center;
        }

        .otp-back-form {
            margin: 0;
        }

        .otp-back-btn {
            width: 36px;
            height: 36px;
            border: 1px solid #ddd;
            background: #fff;
            color: #444;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            padding: 0;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .otp-back-btn:hover {
            background: #f8f8f8;
        }

        .otp-state-card h4 {
            font-size: 22px;
            margin-bottom: 10px;
        }

        .otp-state-sub {
            color: #666;
            margin-bottom: 22px;
            font-size: 14px;
        }

        .otp-boxes {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .otp-box {
            width: 56px;
            height: 60px;
            border-radius: 12px;
            border: 1px solid #ddd;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            outline: none;
        }

        .otp-box:focus {
            border-color: #ff9e66;
            box-shadow: 0 0 0 3px rgba(255, 158, 102, 0.15);
        }

        .otp-actions {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .otp-resend-btn {
            border: none;
            background: none;
            color: #d66a2e;
            font-weight: 600;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
        }

        .otp-resend-btn[disabled] {
            color: #9a9a9a;
            cursor: not-allowed;
            text-decoration: none;
        }

        .otp-resend-copy {
            color: #666;
            font-size: 14px;
        }

        .otp-countdown {
            margin-top: 4px;
            color: #555;
            font-size: 13px;
            font-weight: 500;
        }

        .otp-resend-loading {
            display: none;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 13px;
        }

        .otp-resend-loading.is-active {
            display: inline-flex;
        }

        .otp-spinner {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid #ddd;
            border-top-color: #d66a2e;
            animation: otp-spin 0.8s linear infinite;
        }

        @keyframes otp-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 600px) {
            .otp-box {
                width: 48px;
                height: 54px;
            }
        }
    </style>
</head>

<body>
    @section('content')

        <div class="content-area account-page">

            <!-- HEADER -->
            <div class="account-header">
                <h2>Account Settings</h2>
            </div>

            <!-- BODY -->
            <div class="account-body">

                <!-- SCROLLABLE -->
                <div class="account-scroll">
                    <div class="account-card">
                        @if (session('success'))
                            <div class="form-alert form-alert-success" data-auto-dismiss="10000">
                                {{ session('success') }}
                                <button type="button" class="alert-close-btn" aria-label="Close alert">&times;</button>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="form-alert form-alert-error" data-auto-dismiss="10000">
                                {{ session('error') }}
                                <button type="button" class="alert-close-btn" aria-label="Close alert">&times;</button>
                            </div>
                        @endif

                        @php
                            $pendingAccountUpdate = session('admin_account_update_pending');
                            $pendingPasswordChange = session('admin_password_change_pending');
                            $hasPendingOtpVerification = $pendingAccountUpdate || $pendingPasswordChange;
                            $showPasswordChangeForm = session('admin_password_change_form') && !$hasPendingOtpVerification;
                            $startAccountEditMode = $errors->has('first_name') || $errors->has('last_name') || $errors->has('email') || $errors->has('image');
                            $oldOtp = old('otp', '');
                            $otpRemainingSeconds = 0;
                            $otpExpires = $adminUser ? data_get($adminUser, 'otp_expires_at') : null;
                            if ($otpExpires) {
                                if ($otpExpires instanceof \Carbon\Carbon || $otpExpires instanceof \DateTimeInterface) {
                                    $otpRemainingSeconds = max(0, $otpExpires->getTimestamp() - now()->getTimestamp());
                                } else {
                                    $otpRemainingSeconds = max(0, \Carbon\Carbon::parse((string) $otpExpires)->getTimestamp() - now()->getTimestamp());
                                }
                            }
                        @endphp

                        @if (!$hasPendingOtpVerification && !$showPasswordChangeForm)
                            <form method="POST" action="{{ route('admin.account.update') }}" id="accountProfileForm" class="account-form{{ $startAccountEditMode ? ' is-editing' : '' }}" data-start-editing="{{ $startAccountEditMode ? '1' : '0' }}" enctype="multipart/form-data">
                                @csrf

                                <!-- PROFILE -->
                                <div class="profile-row">
                                    <div class="profile-left">
                                        <div class="profile-avatar-wrap">
                                            <img id="profileAvatarImg" src="{{ data_get($adminUser, 'image') ? asset(data_get($adminUser, 'image')) : asset('img/default-user.png') }}" alt="Profile">
                                            <input type="file" name="image" id="profileImageInput" class="profile-image-input" accept="image/*" tabindex="-1" aria-label="Upload profile photo">
                                            <label for="profileImageInput" class="profile-camera-btn" title="Change profile photo">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                                    <circle cx="12" cy="13" r="4"></circle>
                                                </svg>
                                            </label>
                                        </div>
                                        <div class="profile-info">
                                            <h4>{{ $adminUser ? trim((data_get($adminUser, 'first_name', '') ?? '') . ' ' . (data_get($adminUser, 'last_name', '') ?? '')) ?: 'Admin' : 'Admin' }}</h4>
                                            <small>Profile picture</small>
                                        </div>
                                    </div>

                                    <div class="profile-actions">
                                        <button type="{{ $startAccountEditMode ? 'submit' : 'button' }}" class="btn-outline" id="accountEditBtn" data-label-default="Update Account" data-label-save="Save changes">{{ $startAccountEditMode ? 'Save changes' : 'Update Account' }}</button>
                                    </div>
                                </div>
                                @error('image')
                                    <div class="field-error" style="margin: -10px 0 18px;">{{ $message }}</div>
                                @enderror

                                <div class="form-row form-content">
                                    <div class="form-group">
                                        <label>First Name</label>
                                        <input type="text" name="first_name" value="{{ old('first_name', data_get($adminUser, 'first_name', '')) }}" @unless($startAccountEditMode) readonly @endunless autocomplete="given-name">
                                        @error('first_name')
                                            <span class="field-error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="form-group">
                                        <label>Last Name</label>
                                        <input type="text" name="last_name" value="{{ old('last_name', data_get($adminUser, 'last_name', '')) }}" @unless($startAccountEditMode) readonly @endunless autocomplete="family-name">
                                        @error('last_name')
                                            <span class="field-error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>


                                <div class="divider"></div>

                                <div class="form-section">
                                    <h4>Contact Email</h4>

                                    <div class="form-group single-col">
                                        <label>Email Address</label>
                                        <input type="email" name="email" value="{{ old('email', data_get($adminUser, 'email', '')) }}" @unless($startAccountEditMode) readonly @endunless autocomplete="email">
                                        @error('email')
                                            <span class="field-error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>


                            </form>

                            <div class="divider"></div>
                            <div class="form-section">
                                <h4>Password</h4>
                                <div class="password-row">
                                    <div class="form-group single-cols">
                                        <label>Current Password</label>
                                        <input type="password" value="••••••••" disabled autocomplete="off" aria-readonly="true">
                                    </div>
                                    <form method="POST" action="{{ route('admin.account.password.start') }}">
                                        @csrf
                                        <button type="submit" class="change-btn">Change password</button>
                                    </form>
                                </div>
                            </div>
                        @elseif ($showPasswordChangeForm)
                            <div class="otp-top-bar">
                                <form method="POST" action="{{ route('admin.account.password.cancel') }}" class="otp-back-form">
                                    @csrf
                                    <button type="submit" class="otp-back-btn" aria-label="Go back" title="Back">&larr;</button>
                                </form>
                            </div>
                            <div class="otp-state-wrap">
                                <div class="password-change-center-card">
                                    <h4>Change Password</h4>
                                    <p class="password-change-sub">Enter your current and new password to continue.</p>
                                    <form method="POST" action="{{ route('admin.account.password.send-otp') }}">
                                        @csrf
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Current Password</label>
                                                <input type="password" name="current_password" placeholder="Enter current password">
                                            </div>
                                            <div class="form-group">
                                                <label>New Password</label>
                                                <input type="password" name="new_password" placeholder="Enter new password">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Retype Password</label>
                                            <input type="password" name="new_password_confirmation" placeholder="Retype new password">
                                            @if ($errors->has('current_password') || $errors->has('new_password') || $errors->has('new_password_confirmation'))
                                                <span class="field-error">
                                                    {{ $errors->first('current_password') ?: ($errors->first('new_password') ?: $errors->first('new_password_confirmation')) }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="password-change-actions">
                                            <button type="submit" class="change-btn">Continue</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @else
                            <div class="otp-top-bar">
                                <form method="POST" action="{{ route('admin.account.cancel-email-otp') }}" class="otp-back-form">
                                    @csrf
                                    <button type="submit" class="otp-back-btn" aria-label="Go back" title="Back">&larr;</button>
                                </form>
                            </div>
                            <div class="otp-state-wrap">
                                <div class="otp-state-card">
                                    <h4>{{ $pendingAccountUpdate ? 'Email Change OTP Verification' : 'Password Change OTP Verification' }}</h4>
                                    <p class="otp-state-sub">
                                        {{ $pendingAccountUpdate
                                            ? 'Enter the 4-digit code sent to your current email to continue updating your account.'
                                            : 'Enter the 4-digit code sent to your current email to continue changing your password.' }}
                                    </p>

                                    <form method="POST" action="{{ $pendingAccountUpdate ? route('admin.account.verify-email-otp') : route('admin.account.password.verify-otp') }}" id="otpVerifyForm">
                                        @csrf
                                        <input type="hidden" name="otp" id="otpHiddenInput" value="{{ $oldOtp }}">
                                        @error('otp')
                                            <span class="field-error otp-error">{{ $message }}</span>
                                        @enderror
                                        <div class="otp-boxes">
                                            <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*" value="{{ substr($oldOtp, 0, 1) }}">
                                            <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*" value="{{ substr($oldOtp, 1, 1) }}">
                                            <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*" value="{{ substr($oldOtp, 2, 1) }}">
                                            <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*" value="{{ substr($oldOtp, 3, 1) }}">
                                        </div>
                                        <button type="submit" class="change-btn">Verify OTP</button>
                                    </form>

                                    <div class="otp-actions">
                                        <form method="POST" action="{{ $pendingAccountUpdate ? route('admin.account.resend-email-otp') : route('admin.account.password.resend-otp') }}" id="resendOtpForm">
                                            @csrf
                                            <span class="otp-resend-copy">Didn&apos;t receive OTP?
                                                <button type="submit" class="otp-resend-btn" id="resendOtpBtn">Resend OTP</button>
                                            </span>
                                        </form>
                                        <div class="otp-countdown" id="otpCountdown" data-remaining-seconds="{{ $otpRemainingSeconds }}">
                                            OTP expires in 00:00
                                        </div>
                                        <div class="otp-resend-loading" id="resendOtpLoading" aria-live="polite">
                                            <span class="otp-spinner"></span>
                                            <span>Resending OTP...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>
                </div>

            </div>
        </div>

        <script>
            (function() {
                const alerts = Array.from(document.querySelectorAll('.form-alert'));
                alerts.forEach(function(alertEl) {
                    const closeBtn = alertEl.querySelector('.alert-close-btn');
                    const hideAlert = function() {
                        if (!alertEl || !alertEl.parentNode) {
                            return;
                        }
                        alertEl.classList.add('is-hidden');
                        window.setTimeout(function() {
                            if (alertEl.parentNode) {
                                alertEl.parentNode.removeChild(alertEl);
                            }
                        }, 200);
                    };

                    if (closeBtn) {
                        closeBtn.addEventListener('click', hideAlert);
                    }

                    const delay = parseInt(alertEl.getAttribute('data-auto-dismiss') || '0', 10);
                    if (Number.isFinite(delay) && delay > 0) {
                        window.setTimeout(hideAlert, delay);
                    }
                });

                const otpForm = document.getElementById('otpVerifyForm');
                const otpHiddenInput = document.getElementById('otpHiddenInput');
                const otpBoxes = Array.from(document.querySelectorAll('.otp-box'));

                if (otpForm && otpHiddenInput && otpBoxes.length === 4) {
                    const syncOtpValue = function() {
                        otpHiddenInput.value = otpBoxes.map((box) => box.value).join('');
                    };

                    otpBoxes.forEach((box, index) => {
                        box.addEventListener('input', function(e) {
                            const digit = e.target.value.replace(/\D/g, '').slice(0, 1);
                            e.target.value = digit;
                            if (digit && index < otpBoxes.length - 1) {
                                otpBoxes[index + 1].focus();
                            }
                            syncOtpValue();
                        });

                        box.addEventListener('keydown', function(e) {
                            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                                otpBoxes[index - 1].focus();
                            }
                            if (e.key === 'ArrowLeft' && index > 0) {
                                otpBoxes[index - 1].focus();
                            }
                            if (e.key === 'ArrowRight' && index < otpBoxes.length - 1) {
                                otpBoxes[index + 1].focus();
                            }
                        });

                        box.addEventListener('paste', function(e) {
                            const pasted = (e.clipboardData || window.clipboardData).getData('text');
                            const digits = pasted.replace(/\D/g, '').slice(0, 4).split('');
                            if (!digits.length) {
                                return;
                            }
                            e.preventDefault();
                            otpBoxes.forEach((input, i) => {
                                input.value = digits[i] || '';
                            });
                            syncOtpValue();
                            const nextIndex = Math.min(digits.length, 4) - 1;
                            if (nextIndex >= 0) {
                                otpBoxes[nextIndex].focus();
                            }
                        });
                    });

                    otpForm.addEventListener('submit', function() {
                        syncOtpValue();
                    });
                }

                const accountProfileForm = document.getElementById('accountProfileForm');
                const accountEditBtn = document.getElementById('accountEditBtn');
                if (accountProfileForm && accountEditBtn) {
                    const accountFieldInputs = accountProfileForm.querySelectorAll('input[name="first_name"], input[name="last_name"], input[name="email"]');
                    const labelDefault = accountEditBtn.getAttribute('data-label-default') || 'Update Account';
                    const labelSave = accountEditBtn.getAttribute('data-label-save') || 'Save changes';
                    const enterAccountEditMode = function() {
                        accountProfileForm.classList.add('is-editing');
                        accountFieldInputs.forEach(function(el) {
                            el.removeAttribute('readonly');
                        });
                        accountEditBtn.type = 'submit';
                        accountEditBtn.textContent = labelSave;
                    };
                    if (accountProfileForm.getAttribute('data-start-editing') !== '1') {
                        accountEditBtn.addEventListener('click', function(e) {
                            if (!accountProfileForm.classList.contains('is-editing')) {
                                e.preventDefault();
                                enterAccountEditMode();
                            }
                        });
                    }
                }

                const profileImageInput = document.getElementById('profileImageInput');
                const profileAvatarImg = document.getElementById('profileAvatarImg');
                if (profileImageInput && profileAvatarImg) {
                    let profilePreviewUrl = null;
                    profileImageInput.addEventListener('change', function() {
                        const file = this.files && this.files[0];
                        if (!file || !file.type.startsWith('image/')) {
                            return;
                        }
                        if (profilePreviewUrl) {
                            URL.revokeObjectURL(profilePreviewUrl);
                            profilePreviewUrl = null;
                        }
                        profilePreviewUrl = URL.createObjectURL(file);
                        profileAvatarImg.src = profilePreviewUrl;
                    });
                }

                const resendOtpForm = document.getElementById('resendOtpForm');
                const resendOtpBtn = document.getElementById('resendOtpBtn');
                const resendOtpLoading = document.getElementById('resendOtpLoading');
                const otpCountdown = document.getElementById('otpCountdown');

                if (otpCountdown && resendOtpBtn) {
                    let remaining = parseInt(otpCountdown.dataset.remainingSeconds || '0', 10);
                    if (!Number.isFinite(remaining) || remaining < 0) {
                        remaining = 0;
                    }

                    const renderCountdown = function() {
                        const minutes = Math.floor(remaining / 60);
                        const seconds = remaining % 60;
                        otpCountdown.textContent = 'OTP expires in ' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

                        if (remaining > 0) {
                            resendOtpBtn.disabled = true;
                        } else if (!resendOtpLoading || !resendOtpLoading.classList.contains('is-active')) {
                            resendOtpBtn.disabled = false;
                        }
                    };

                    renderCountdown();

                    if (remaining > 0) {
                        const countdownInterval = setInterval(function() {
                            remaining -= 1;
                            if (remaining <= 0) {
                                remaining = 0;
                                renderCountdown();
                                clearInterval(countdownInterval);
                                return;
                            }
                            renderCountdown();
                        }, 1000);
                    }
                }

                if (resendOtpForm && resendOtpBtn && resendOtpLoading) {
                    resendOtpForm.addEventListener('submit', function() {
                        resendOtpBtn.disabled = true;
                        resendOtpBtn.textContent = 'Resending...';
                        resendOtpLoading.classList.add('is-active');
                    });
                }
            })();
        </script>

    @endsection
</body>

</html>
