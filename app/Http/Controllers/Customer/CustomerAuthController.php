<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Mail\OtpVerificationMail;
use App\Services\FirestoreService;
use App\Support\FirestoreCacheKeys;
use App\Support\FirestoreCustomerUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class CustomerAuthController extends Controller
{
    private const STATUS_ACTIVE = 'active';

    public function __construct(
        protected FirestoreService $firestore
    ) {}

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function customerRowById(mixed $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }

        return $this->firestore->get('customers', (string) $id);
    }

    private function customerObjectForView(?array $row): ?object
    {
        return FirestoreCustomerUser::fromArray($row);
    }

    private function otpExpired(?array $row): bool
    {
        $raw = $row['otp_expires_at'] ?? null;
        if ($raw === null || $raw === '') {
            return true;
        }
        try {
            return Carbon::parse((string) $raw)->isPast();
        } catch (\Throwable) {
            return true;
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'This field is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'This field is required.',
        ]);

        $email = $this->normalizeEmail((string) $request->email);
        $row = $this->firestore->firstWhere('customers', 'email', $email);

        if (! $row || ! Hash::check((string) $request->password, (string) ($row['password'] ?? ''))) {
            return redirect()->back()
                ->withInput($request->only('email'))
                ->withErrors(['password' => 'Invalid email or password.']);
        }

        $customer = FirestoreCustomerUser::fromArray($row);
        if (! $customer || ! FirestoreCustomerUser::isEmailVerified($customer)) {
            $request->session()->put('pending_verify_email', $email);

            return redirect()->route('customer.verify-otp')
                ->with('error', 'Please verify your email with the 4-digit code to continue.');
        }

        $request->session()->put('customer_id', (string) ($row['id'] ?? ''));

        return redirect()->route('customer.home')->with('success', 'Welcome back!');
    }

    public function register(Request $request)
    {
        $email = $this->normalizeEmail((string) $request->email);
        $existing = $this->firestore->firstWhere('customers', 'email', $email);

        $request->validate([
            'firstname' => 'required|string|max:50',
            'lastname' => 'required|string|max:50',
            'email' => 'required|email|max:100',
            'contact_no' => 'nullable|string|max:20|regex:/^[\d\s\-+()]+$/',
            'password' => 'required|string|confirmed|min:6',
        ], [
            'firstname.required' => 'First name is required.',
            'lastname.required' => 'Last name is required.',
            'email.required' => 'Email is required.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Passwords do not match.',
            'password.min' => 'Password must be at least 6 characters.',
        ]);

        if ($existing !== null) {
            return redirect()->back()
                ->withInput($request->only('firstname', 'lastname', 'email', 'contact_no'))
                ->withErrors(['email' => 'This email is already registered.']);
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $otpExpiresAt = now()->addMinutes(10);

        $this->firestore->add('customers', [
            'firstname' => $request->firstname,
            'lastname' => $request->lastname,
            'email' => $email,
            'contact_no' => $request->contact_no ? trim((string) $request->contact_no) : null,
            'image' => 'img/default-user.png',
            'status' => self::STATUS_ACTIVE,
            'password' => Hash::make($request->password),
            'otp' => $otp,
            'otp_expires_at' => $otpExpiresAt->toIso8601String(),
            'email_verified_at' => null,
        ]);
        FirestoreCacheKeys::invalidateCustomers();

        $request->session()->put('pending_verify_email', $email);

        try {
            Mail::to($email)->send(new OtpVerificationMail($otp, $email));
        } catch (\Throwable $e) {
            report($e);
            $message = 'Account created but we could not send the verification email. ';
            if (config('app.debug')) {
                $message .= 'Error: '.$e->getMessage();
            } else {
                $message .= 'Check MAIL_* in .env (Gmail: use App Password, port 587 + TLS or 465 + SSL).';
            }

            return redirect()->route('customer.verify-otp')
                ->with('error', $message);
        }

        return redirect()->route('customer.verify-otp')->with('success', 'We sent a 4-digit code to your email. Enter it below to verify.');
    }

    public function showOtpForm(Request $request)
    {
        $email = $request->session()->get('pending_verify_email');
        if (! $email) {
            return redirect()->route('customer.register')
                ->with('error', 'Please register first to verify your email.');
        }

        return view('Customer.verify-otp', ['email' => $email]);
    }

    public function resendOtp(Request $request)
    {
        $email = $this->normalizeEmail((string) $request->session()->get('pending_verify_email', ''));
        if ($email === '') {
            return redirect()->route('customer.register')
                ->with('error', 'Session expired. Please register again.');
        }

        $row = $this->firestore->firstWhere('customers', 'email', $email);
        if (! $row) {
            $request->session()->forget('pending_verify_email');

            return redirect()->route('customer.register')->with('error', 'Account not found. Please register again.');
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->firestore->update('customers', (string) $row['id'], [
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);
        FirestoreCacheKeys::invalidateCustomers();

        try {
            Mail::to($email)->send(new OtpVerificationMail($otp, $email));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('customer.verify-otp')
                ->with('error', 'Could not send the new code. Please try again later.');
        }

        return redirect()->route('customer.verify-otp')
            ->with('success', 'A new 4-digit code has been sent to your email.');
    }

    public function verifyOtp(Request $request)
    {
        $email = $this->normalizeEmail((string) $request->session()->get('pending_verify_email', ''));
        if ($email === '') {
            return redirect()->route('customer.register')
                ->with('error', 'Session expired. Please register again.');
        }

        $request->validate([
            'otp' => 'required|string|size:4|regex:/^\d{4}$/',
        ], [
            'otp.required' => 'Please enter the 4-digit code.',
            'otp.size' => 'The code must be 4 digits.',
            'otp.regex' => 'The code must be 4 digits only.',
        ]);

        $row = $this->firestore->firstWhere('customers', 'email', $email);
        if (! $row) {
            $request->session()->forget('pending_verify_email');

            return redirect()->route('customer.register')->with('error', 'Account not found. Please register again.');
        }

        if ((string) ($row['otp'] ?? '') !== (string) $request->otp) {
            return redirect()->back()
                ->withErrors(['otp' => 'Invalid or expired code. Please try again.']);
        }

        if ($this->otpExpired($row)) {
            return redirect()->back()
                ->withErrors(['otp' => 'This code has expired. Please request a new one by registering again.']);
        }

        $this->firestore->update('customers', (string) $row['id'], [
            'email_verified_at' => now()->toIso8601String(),
            'otp' => null,
            'otp_expires_at' => null,
        ]);
        FirestoreCacheKeys::invalidateCustomers();
        $request->session()->forget('pending_verify_email');

        return redirect()->route('customer.login')->with('success', 'Email verified. You can now log in.');
    }

    public function showForgotPasswordForm()
    {
        return view('Customer.forgot-password');
    }

    public function sendForgotPasswordOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $email = $this->normalizeEmail((string) $request->email);
        $row = $this->firestore->firstWhere('customers', 'email', $email);
        if (! $row) {
            return redirect()->back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'No account found with this email address.']);
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->firestore->update('customers', (string) $row['id'], [
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);
        FirestoreCacheKeys::invalidateCustomers();

        $request->session()->put('forgot_password_email', $email);

        try {
            Mail::to($email)->send(new OtpVerificationMail($otp, $email));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()
                ->with('error', 'Could not send the verification code. Please try again later.');
        }

        return redirect()->route('customer.forgot-password.verify-otp')
            ->with('success', 'A 4-digit code has been sent to your email. Enter it below.');
    }

    public function showForgotPasswordOtpForm(Request $request)
    {
        $email = $request->session()->get('forgot_password_email');
        if (! $email) {
            return redirect()->route('customer.forgot-password')
                ->with('error', 'Please enter your email first to receive a verification code.');
        }

        return view('Customer.verify-otp-forgot', ['email' => $email]);
    }

    public function resendForgotPasswordOtp(Request $request)
    {
        $email = $this->normalizeEmail((string) $request->session()->get('forgot_password_email', ''));
        if ($email === '') {
            return redirect()->route('customer.forgot-password')
                ->with('error', 'Session expired. Please enter your email again.');
        }

        $row = $this->firestore->firstWhere('customers', 'email', $email);
        if (! $row) {
            $request->session()->forget('forgot_password_email');

            return redirect()->route('customer.forgot-password')->with('error', 'Account not found.');
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->firestore->update('customers', (string) $row['id'], [
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);
        FirestoreCacheKeys::invalidateCustomers();

        try {
            Mail::to($email)->send(new OtpVerificationMail($otp, $email));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('customer.forgot-password.verify-otp')
                ->with('error', 'Could not send the new code. Please try again later.');
        }

        return redirect()->route('customer.forgot-password.verify-otp')
            ->with('success', 'A new 4-digit code has been sent to your email.');
    }

    public function verifyForgotPasswordOtp(Request $request)
    {
        $email = $this->normalizeEmail((string) $request->session()->get('forgot_password_email', ''));
        if ($email === '') {
            return redirect()->route('customer.forgot-password')
                ->with('error', 'Session expired. Please enter your email again.');
        }

        $request->validate([
            'otp' => 'required|string|size:4|regex:/^\d{4}$/',
        ], [
            'otp.required' => 'Please enter the 4-digit code.',
            'otp.size' => 'The code must be 4 digits.',
            'otp.regex' => 'The code must be 4 digits only.',
        ]);

        $row = $this->firestore->firstWhere('customers', 'email', $email);
        if (! $row) {
            $request->session()->forget('forgot_password_email');

            return redirect()->route('customer.forgot-password')->with('error', 'Account not found.');
        }

        if ((string) ($row['otp'] ?? '') !== (string) $request->otp) {
            return redirect()->back()
                ->withErrors(['otp' => 'Invalid or expired code. Please try again.']);
        }

        if ($this->otpExpired($row)) {
            return redirect()->back()
                ->withErrors(['otp' => 'This code has expired. Please request a new one.']);
        }

        $this->firestore->update('customers', (string) $row['id'], [
            'otp' => null,
            'otp_expires_at' => null,
        ]);
        FirestoreCacheKeys::invalidateCustomers();

        return redirect()->route('customer.forgot-password.reset-password')
            ->with('success', 'Code verified. Enter your new password below.');
    }

    public function showResetPasswordForm(Request $request)
    {
        $email = $request->session()->get('forgot_password_email');
        if (! $email) {
            return redirect()->route('customer.forgot-password')
                ->with('error', 'Session expired. Please start the process again.');
        }

        return view('Customer.reset-password', ['email' => $email]);
    }

    public function updatePassword(Request $request)
    {
        $email = $this->normalizeEmail((string) $request->session()->get('forgot_password_email', ''));
        if ($email === '') {
            return redirect()->route('customer.forgot-password')
                ->with('error', 'Session expired. Please start the process again.');
        }

        $request->validate([
            'password' => 'required|string|confirmed|min:6',
        ], [
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Passwords do not match.',
            'password.min' => 'Password must be at least 6 characters.',
        ]);

        $row = $this->firestore->firstWhere('customers', 'email', $email);
        if (! $row) {
            $request->session()->forget('forgot_password_email');

            return redirect()->route('customer.forgot-password')->with('error', 'Account not found.');
        }

        $this->firestore->update('customers', (string) $row['id'], [
            'password' => Hash::make((string) $request->password),
        ]);
        FirestoreCacheKeys::invalidateCustomers();
        $request->session()->forget('forgot_password_email');

        return redirect()->route('customer.login')->with('success', 'Your password has been updated. You can now log in.');
    }

    public function showChangePasswordForm(Request $request)
    {
        $customerId = $request->session()->get('customer_id');
        if (! $customerId) {
            return redirect()->route('customer.login')->with('error', 'Please log in to change your password.');
        }
        $row = $this->customerRowById($customerId);
        if (! $row) {
            $request->session()->forget('customer_id');

            return redirect()->route('customer.login')->with('error', 'Session expired. Please log in again.');
        }

        return view('Customer.change-password', ['customer' => $this->customerObjectForView($row)]);
    }

    public function sendChangePasswordOtp(Request $request)
    {
        $customerId = $request->session()->get('customer_id');
        if (! $customerId) {
            return redirect()->route('customer.login')->with('error', 'Please log in to change your password.');
        }
        $row = $this->customerRowById($customerId);
        if (! $row) {
            $request->session()->forget('customer_id');

            return redirect()->route('customer.login')->with('error', 'Session expired. Please log in again.');
        }

        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $accountEmail = $this->normalizeEmail((string) ($row['email'] ?? ''));
        if (strcasecmp($accountEmail, $this->normalizeEmail((string) $request->email)) !== 0) {
            return redirect()->back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This email does not match your account.']);
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->firestore->update('customers', (string) $row['id'], [
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);
        FirestoreCacheKeys::invalidateCustomers();

        $request->session()->put('change_password_email', $accountEmail);

        try {
            Mail::to($accountEmail)->send(new OtpVerificationMail($otp, $accountEmail));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()
                ->with('error', 'Could not send the verification code. Please try again later.');
        }

        return redirect()->route('customer.change-password.verify-otp')
            ->with('success', 'A 4-digit code has been sent to your email. Enter it below.');
    }

    public function showChangePasswordOtpForm(Request $request)
    {
        if (! $request->session()->get('customer_id')) {
            return redirect()->route('customer.login')->with('error', 'Please log in first.');
        }
        $email = $request->session()->get('change_password_email');
        if (! $email) {
            return redirect()->route('customer.change-password')
                ->with('error', 'Please enter your email first to receive a verification code.');
        }

        return view('Customer.change-password-verify-otp', ['email' => $email]);
    }

    public function resendChangePasswordOtp(Request $request)
    {
        if (! $request->session()->get('customer_id')) {
            return redirect()->route('customer.login')->with('error', 'Please log in first.');
        }
        $email = $this->normalizeEmail((string) $request->session()->get('change_password_email', ''));
        if ($email === '') {
            return redirect()->route('customer.change-password')
                ->with('error', 'Session expired. Please enter your email again.');
        }

        $row = $this->firestore->firstWhere('customers', 'email', $email);
        if (! $row) {
            $request->session()->forget('change_password_email');

            return redirect()->route('customer.change-password')->with('error', 'Account not found.');
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->firestore->update('customers', (string) $row['id'], [
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);
        FirestoreCacheKeys::invalidateCustomers();

        try {
            Mail::to($email)->send(new OtpVerificationMail($otp, $email));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('customer.change-password.verify-otp')
                ->with('error', 'Could not send the new code. Please try again later.');
        }

        return redirect()->route('customer.change-password.verify-otp')
            ->with('success', 'A new 4-digit code has been sent to your email.');
    }

    public function verifyChangePasswordOtp(Request $request)
    {
        if (! $request->session()->get('customer_id')) {
            return redirect()->route('customer.login')->with('error', 'Please log in first.');
        }
        $email = $this->normalizeEmail((string) $request->session()->get('change_password_email', ''));
        if ($email === '') {
            return redirect()->route('customer.change-password')
                ->with('error', 'Session expired. Please enter your email again.');
        }

        $request->validate([
            'otp' => 'required|string|size:4|regex:/^\d{4}$/',
        ], [
            'otp.required' => 'Please enter the 4-digit code.',
            'otp.size' => 'The code must be 4 digits.',
            'otp.regex' => 'The code must be 4 digits only.',
        ]);

        $row = $this->firestore->firstWhere('customers', 'email', $email);
        if (! $row) {
            $request->session()->forget('change_password_email');

            return redirect()->route('customer.change-password')->with('error', 'Account not found.');
        }

        if ((string) ($row['otp'] ?? '') !== (string) $request->otp) {
            return redirect()->back()
                ->withErrors(['otp' => 'Invalid or expired code. Please try again.']);
        }

        if ($this->otpExpired($row)) {
            return redirect()->back()
                ->withErrors(['otp' => 'This code has expired. Please request a new one.']);
        }

        $this->firestore->update('customers', (string) $row['id'], [
            'otp' => null,
            'otp_expires_at' => null,
        ]);
        FirestoreCacheKeys::invalidateCustomers();

        return redirect()->route('customer.change-password.new-password')
            ->with('success', 'Code verified. Enter your current password and new password below.');
    }

    public function showChangePasswordNewPasswordForm(Request $request)
    {
        if (! $request->session()->get('customer_id')) {
            return redirect()->route('customer.login')->with('error', 'Please log in first.');
        }
        $email = $this->normalizeEmail((string) $request->session()->get('change_password_email', ''));
        if ($email === '') {
            return redirect()->route('customer.change-password')
                ->with('error', 'Session expired. Please start the process again.');
        }
        $row = $this->firestore->firstWhere('customers', 'email', $email);
        if (! $row) {
            $request->session()->forget('change_password_email');

            return redirect()->route('customer.change-password')->with('error', 'Account not found.');
        }

        return view('Customer.change-password-new-password', ['customer' => $this->customerObjectForView($row)]);
    }

    public function updateChangePassword(Request $request)
    {
        if (! $request->session()->get('customer_id')) {
            return redirect()->route('customer.login')->with('error', 'Please log in first.');
        }
        $email = $this->normalizeEmail((string) $request->session()->get('change_password_email', ''));
        if ($email === '') {
            return redirect()->route('customer.change-password')
                ->with('error', 'Session expired. Please start the process again.');
        }

        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|confirmed|min:6',
        ], [
            'current_password.required' => 'Please enter your current password.',
            'password.required' => 'New password is required.',
            'password.confirmed' => 'New passwords do not match.',
            'password.min' => 'New password must be at least 6 characters.',
        ]);

        $row = $this->firestore->firstWhere('customers', 'email', $email);
        if (! $row) {
            $request->session()->forget('change_password_email');

            return redirect()->route('customer.change-password')->with('error', 'Account not found.');
        }

        if (! Hash::check((string) $request->current_password, (string) ($row['password'] ?? ''))) {
            return redirect()->back()
                ->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $this->firestore->update('customers', (string) $row['id'], [
            'password' => Hash::make((string) $request->password),
        ]);
        FirestoreCacheKeys::invalidateCustomers();

        $keepLoggedIn = $request->boolean('keep_logged_in');
        $request->session()->forget('change_password_email');

        if ($keepLoggedIn) {
            return redirect()->route('customer.home')->with('success', 'Your password has been updated. You are still logged in.');
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login')->with('success', 'Your password has been updated. Please log in again.');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->back();
    }
}
