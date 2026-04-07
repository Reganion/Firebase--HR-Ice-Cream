<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OtpVerificationMail;
use App\Services\FirestoreService;
use App\Support\FirestoreCacheKeys;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AdminAuthController extends Controller
{
    public function __construct(private FirestoreService $firestore)
    {
    }

    private function currentAdmin(Request $request): ?array
    {
        $adminId = (string) $request->session()->get('admin_id', '');
        $email = strtolower(trim((string) $request->session()->get('admin_email', '')));
        $admin = $this->firestore->resolveAdminForSession($adminId, $email);

        if ($admin && !empty($admin['id'])) {
            $request->session()->put('admin_id', (string) $admin['id']);
            $request->session()->put('admin_email', (string) ($admin['email'] ?? ''));
            $request->session()->put('admin_name', (string) ($admin['name'] ?? 'Admin'));
        }

        return $admin;
    }

    /**
     * Firestore admin doc is cached for session; clear after mutations so UI stays in sync.
     */
    private function forgetCachedAdminDoc(?array $admin, ?string $emailLookupKey = null, ?string $alsoForgetEmail = null): void
    {
        if (! empty($admin['id'])) {
            FirestoreCacheKeys::forgetAdminDoc((string) $admin['id']);
        }
        FirestoreCacheKeys::forgetAdminDocByEmail($emailLookupKey);
        FirestoreCacheKeys::forgetAdminDocByEmail($alsoForgetEmail);
    }

    /**
     * Store uploaded admin profile image; returns relative public path (e.g. img/admins/...) or null.
     */
    private function storeAdminProfileImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        $dir = public_path('img/admins');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $image = $request->file('image');
        $filename = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '', $image->getClientOriginalName());
        $image->move($dir, $filename);

        return 'img/admins/'.$filename;
    }

    private function deletePublicImageIfAdminUpload(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        if (! str_starts_with(str_replace('\\', '/', $relativePath), 'img/admins/')) {
            return;
        }
        $full = public_path($relativePath);
        if (is_file($full)) {
            @unlink($full);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ], [
            'email.required'    => 'This field is required.',
            'email.email'       => 'Please enter a valid email address.',
            'password.required' => 'This field is required.',
        ]);

        $email = strtolower(trim((string) $request->email));
        $firestoreAdmin = $this->firestore->firstWhere('admins', 'email', $email);

        if (!$firestoreAdmin || !Hash::check((string) $request->password, (string) ($firestoreAdmin['password'] ?? ''))) {
            return redirect()->back()
                ->withInput($request->only('email'))
                ->withErrors(['password' => 'Invalid email or password.']);
        }

        // Firestore-only login: keep session value as Firestore document ID.
        $request->session()->put('admin_id', (string) ($firestoreAdmin['id'] ?? ''));
        $request->session()->put('admin_email', $email);
        $request->session()->put('admin_name', (string) ($firestoreAdmin['name'] ?? 'Admin'));
        return redirect()->route('admin.dashboard')->with('success', 'Welcome back!');
    }

    public function register(Request $request)
    {
        $email = strtolower(trim((string) $request->email));
        $existing = $this->firestore->firstWhere('admins', 'email', $email);

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|max:255',
            'image'      => 'nullable|image|max:2048',
            'password'   => 'required|string|confirmed|min:6',
        ], [
            'first_name.required'  => 'First name is required.',
            'last_name.required'   => 'Last name is required.',
            'email.required'       => 'Email is required.',
            'password.required'    => 'Password is required.',
            'password.confirmed'   => 'Passwords do not match.',
            'password.min'         => 'Password must be at least 6 characters.',
        ]);

        if ($existing !== null) {
            return redirect()->back()
                ->withInput($request->only('first_name', 'last_name', 'email'))
                ->withErrors(['email' => 'This email is already registered.']);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $dir = public_path('img/admins');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $image = $request->file('image');
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $image->getClientOriginalName());
            $image->move($dir, $filename);
            $imagePath = 'img/admins/' . $filename;
        }

        $this->firestore->add('admins', [
            'name'       => $request->first_name . ' ' . $request->last_name,
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $email,
            'image'      => $imagePath,
            'password'   => Hash::make($request->password),
        ]);

        return redirect()->route('admin.login')->with('success', 'Account created successfully! Please log in.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('admin_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }

    public function updateAccount(Request $request)
    {
        $admin = $this->currentAdmin($request);

        if (!$admin) {
            return redirect()->route('admin.login')->with('error', 'Please log in again.');
        }

        if ($request->session()->has('admin_password_change_pending')) {
            return redirect()->route('admin.account')
                ->with('error', 'Please finish or cancel the pending password change OTP first.');
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|max:255',
            'image'      => 'nullable|image|max:2048',
        ], [
            'first_name.required' => 'First name is required.',
            'last_name.required'  => 'Last name is required.',
            'email.required'      => 'Email is required.',
            'email.email'         => 'Please enter a valid email address.',
            'email.unique'        => 'This email is already in use.',
            'image.image'         => 'The profile photo must be an image file.',
            'image.max'           => 'The profile photo may not be greater than 2 MB.',
        ]);

        $newImagePath = $this->storeAdminProfileImage($request);

        $newEmail = strtolower(trim((string) $validated['email']));
        $currentEmail = strtolower((string) ($admin['email'] ?? ''));
        $emailChanged = $newEmail !== $currentEmail;

        if ($emailChanged) {
            $emailAlreadyUsed = $this->firestore->firstWhere('admins', 'email', $newEmail);
            if ($emailAlreadyUsed && (string) ($emailAlreadyUsed['id'] ?? '') !== (string) ($admin['id'] ?? '')) {
                return redirect()->route('admin.account')
                    ->withInput()
                    ->withErrors(['email' => 'This email is already in use.']);
            }
        }

        if ($emailChanged) {
            $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $this->firestore->update('admins', (string) $admin['id'], [
                'otp'            => $otp,
                'otp_expires_at' => now()->addMinutes(10)->toIso8601String(),
            ]);
            $this->forgetCachedAdminDoc($admin, strtolower(trim((string) ($admin['email'] ?? ''))));

            $pendingUpdate = [
                'first_name' => trim((string) $validated['first_name']),
                'last_name'  => trim((string) $validated['last_name']),
                'email'      => $newEmail,
            ];
            if ($newImagePath !== null) {
                $pendingUpdate['image'] = $newImagePath;
            }
            $request->session()->put('admin_account_update_pending', $pendingUpdate);

            try {
                Mail::to((string) ($admin['email'] ?? ''))->send(new OtpVerificationMail($otp, (string) ($admin['email'] ?? '')));
            } catch (\Throwable $e) {
                report($e);
                $request->session()->forget('admin_account_update_pending');
                $this->deletePublicImageIfAdminUpload($newImagePath);
                return redirect()->route('admin.account')
                    ->withInput()
                    ->with('error', 'Could not send OTP to your current email. Please try again.');
            }

            return redirect()->route('admin.account')
                ->withInput()
                ->with('success', 'A 4-digit OTP was sent to your current email. Enter it below to confirm the email change.');
        }

        $firstName = trim((string) $validated['first_name']);
        $lastName = trim((string) $validated['last_name']);

        $payload = [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'name'       => trim($firstName.' '.$lastName),
            'email'      => $newEmail,
            'otp'        => null,
            'otp_expires_at' => null,
        ];
        if ($newImagePath !== null) {
            $payload['image'] = $newImagePath;
        }

        $this->firestore->update('admins', (string) $admin['id'], $payload);
        $this->forgetCachedAdminDoc($admin, $currentEmail, $newEmail);

        $request->session()->forget('admin_account_update_pending');

        return redirect()->route('admin.account')->with('success', 'Account updated successfully.');
    }

    public function verifyAccountEmailOtp(Request $request)
    {
        $admin = $this->currentAdmin($request);

        if (!$admin) {
            return redirect()->route('admin.login')->with('error', 'Please log in again.');
        }

        $request->validate([
            'otp' => 'required|string|size:4|regex:/^\d{4}$/',
        ], [
            'otp.required' => 'Please enter the 4-digit OTP.',
            'otp.size'     => 'The OTP must be 4 digits.',
            'otp.regex'    => 'The OTP must contain digits only.',
        ]);

        $pending = $request->session()->get('admin_account_update_pending');
        if (!$pending || !is_array($pending)) {
            return redirect()->route('admin.account')->with('error', 'No pending email change found.');
        }

        if ((string) ($admin['otp'] ?? '') !== (string) $request->otp) {
            return redirect()->route('admin.account')
                ->withErrors(['otp' => 'Invalid OTP. Please try again.'])
                ->withInput();
        }

        if (!empty($admin['otp_expires_at']) && Carbon::parse((string) $admin['otp_expires_at'])->isPast()) {
            return redirect()->route('admin.account')
                ->withErrors(['otp' => 'This OTP has expired. Please submit the account update again.'])
                ->withInput();
        }

        $newEmail = strtolower(trim((string) ($pending['email'] ?? '')));
        $firstName = trim((string) ($pending['first_name'] ?? ''));
        $lastName = trim((string) ($pending['last_name'] ?? ''));

        if ($newEmail === '' || $firstName === '' || $lastName === '') {
            $request->session()->forget('admin_account_update_pending');
            return redirect()->route('admin.account')->with('error', 'Pending update data is invalid. Please try again.');
        }

        $emailAlreadyUsed = $this->firestore->firstWhere('admins', 'email', $newEmail);
        if ($emailAlreadyUsed && (string) ($emailAlreadyUsed['id'] ?? '') !== (string) ($admin['id'] ?? '')) {
            $request->session()->forget('admin_account_update_pending');
            return redirect()->route('admin.account')->with('error', 'That email is already in use by another account.');
        }

        $oldEmail = strtolower(trim((string) ($admin['email'] ?? '')));
        $pendingImage = $pending['image'] ?? null;

        $verifyPayload = [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'name'       => trim($firstName.' '.$lastName),
            'email'      => $newEmail,
            'otp'        => null,
            'otp_expires_at' => null,
        ];
        if (is_string($pendingImage) && $pendingImage !== '') {
            $verifyPayload['image'] = $pendingImage;
        }

        $this->firestore->update('admins', (string) $admin['id'], $verifyPayload);
        $this->forgetCachedAdminDoc($admin, $oldEmail, $newEmail);

        $request->session()->forget('admin_account_update_pending');

        return redirect()->route('admin.account')->with('success', 'Email address verified and account updated successfully.');
    }

    public function resendAccountEmailOtp(Request $request)
    {
        $admin = $this->currentAdmin($request);

        if (!$admin) {
            return redirect()->route('admin.login')->with('error', 'Please log in again.');
        }

        $pending = $request->session()->get('admin_account_update_pending');
        if (!$pending || !is_array($pending)) {
            return redirect()->route('admin.account')->with('error', 'No pending email change found.');
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->firestore->update('admins', (string) $admin['id'], [
            'otp'            => $otp,
            'otp_expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);
        $this->forgetCachedAdminDoc($admin, strtolower(trim((string) ($admin['email'] ?? ''))));

        try {
            Mail::to((string) ($admin['email'] ?? ''))->send(new OtpVerificationMail($otp, (string) ($admin['email'] ?? '')));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('admin.account')
                ->with('error', 'Could not resend OTP. Please try again.');
        }

        return redirect()->route('admin.account')
            ->with('success', 'A new OTP was sent to your current email.');
    }

    public function cancelAccountEmailOtp(Request $request)
    {
        $admin = $this->currentAdmin($request);

        if (!$admin) {
            return redirect()->route('admin.login')->with('error', 'Please log in again.');
        }

        $this->firestore->update('admins', (string) $admin['id'], [
            'otp' => null,
            'otp_expires_at' => null,
        ]);
        $this->forgetCachedAdminDoc($admin, strtolower(trim((string) ($admin['email'] ?? ''))));

        $pending = $request->session()->get('admin_account_update_pending');
        if (is_array($pending) && isset($pending['image'])) {
            $this->deletePublicImageIfAdminUpload(is_string($pending['image']) ? $pending['image'] : null);
        }

        $request->session()->forget('admin_account_update_pending');
        $request->session()->forget('admin_password_change_pending');
        $request->session()->forget('admin_password_change_form');

        return redirect()->route('admin.account')->with('success', 'Verification request was cancelled.');
    }

    public function sendAccountPasswordOtp(Request $request)
    {
        $admin = $this->currentAdmin($request);

        if (!$admin) {
            return redirect()->route('admin.login')->with('error', 'Please log in again.');
        }

        $request->session()->put('admin_password_change_form', true);

        if ($request->session()->has('admin_account_update_pending')) {
            return redirect()->route('admin.account')
                ->with('error', 'Please finish or cancel the pending email change OTP first.');
        }

        $validated = $request->validate([
            'current_password'          => 'required|string',
            'new_password'              => 'required|string|min:6|confirmed',
            'new_password_confirmation' => 'required|string|min:6',
        ], [
            'current_password.required'          => 'Current password is required.',
            'new_password.required'              => 'New password is required.',
            'new_password.min'                   => 'New password must be at least 6 characters.',
            'new_password.confirmed'             => 'New password and retype password do not match.',
            'new_password_confirmation.required' => 'Retype password is required.',
        ]);

        if (!Hash::check((string) $validated['current_password'], (string) ($admin['password'] ?? ''))) {
            return redirect()->route('admin.account')
                ->withErrors(['current_password' => 'Current password is incorrect.'])
                ->withInput();
        }

        if (Hash::check((string) $validated['new_password'], (string) ($admin['password'] ?? ''))) {
            return redirect()->route('admin.account')
                ->withErrors(['new_password' => 'New password must be different from current password.'])
                ->withInput();
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->firestore->update('admins', (string) $admin['id'], [
            'otp'            => $otp,
            'otp_expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);
        $this->forgetCachedAdminDoc($admin, strtolower(trim((string) ($admin['email'] ?? ''))));

        $request->session()->put('admin_password_change_pending', [
            'password_hash' => Hash::make((string) $validated['new_password']),
        ]);
        $request->session()->forget('admin_password_change_form');

        try {
            Mail::to((string) ($admin['email'] ?? ''))->send(new OtpVerificationMail($otp, (string) ($admin['email'] ?? '')));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('admin.account')
                ->withInput()
                ->with('error', 'Could not send OTP to your current email. Please try again.');
        }

        return redirect()->route('admin.account')
            ->with('success', 'A 4-digit OTP was sent to your current email to verify password change.');
    }

    public function verifyAccountPasswordOtp(Request $request)
    {
        $admin = $this->currentAdmin($request);

        if (!$admin) {
            return redirect()->route('admin.login')->with('error', 'Please log in again.');
        }

        $request->validate([
            'otp' => 'required|string|size:4|regex:/^\d{4}$/',
        ], [
            'otp.required' => 'Please enter the 4-digit OTP.',
            'otp.size'     => 'The OTP must be 4 digits.',
            'otp.regex'    => 'The OTP must contain digits only.',
        ]);

        $pending = $request->session()->get('admin_password_change_pending');
        if (!$pending || !is_array($pending) || empty($pending['password_hash'])) {
            return redirect()->route('admin.account')->with('error', 'No pending password change found.');
        }

        if ((string) ($admin['otp'] ?? '') !== (string) $request->otp) {
            return redirect()->route('admin.account')
                ->withErrors(['otp' => 'Invalid OTP. Please try again.']);
        }

        if (!empty($admin['otp_expires_at']) && Carbon::parse((string) $admin['otp_expires_at'])->isPast()) {
            return redirect()->route('admin.account')
                ->withErrors(['otp' => 'This OTP has expired. Please submit the password change again.']);
        }

        $this->firestore->update('admins', (string) $admin['id'], [
            'password'       => (string) $pending['password_hash'],
            'otp'            => null,
            'otp_expires_at' => null,
        ]);
        $this->forgetCachedAdminDoc($admin, strtolower(trim((string) ($admin['email'] ?? ''))));

        $request->session()->forget('admin_password_change_pending');
        $request->session()->forget('admin_password_change_form');

        return redirect()->route('admin.account')->with('success', 'Password updated successfully.');
    }

    public function resendAccountPasswordOtp(Request $request)
    {
        $admin = $this->currentAdmin($request);

        if (!$admin) {
            return redirect()->route('admin.login')->with('error', 'Please log in again.');
        }

        $pending = $request->session()->get('admin_password_change_pending');
        if (!$pending || !is_array($pending) || empty($pending['password_hash'])) {
            return redirect()->route('admin.account')->with('error', 'No pending password change found.');
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->firestore->update('admins', (string) $admin['id'], [
            'otp'            => $otp,
            'otp_expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);
        $this->forgetCachedAdminDoc($admin, strtolower(trim((string) ($admin['email'] ?? ''))));

        try {
            Mail::to((string) ($admin['email'] ?? ''))->send(new OtpVerificationMail($otp, (string) ($admin['email'] ?? '')));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('admin.account')
                ->with('error', 'Could not resend OTP. Please try again.');
        }

        return redirect()->route('admin.account')
            ->with('success', 'A new OTP was sent to your current email.');
    }

    public function startAccountPasswordChange(Request $request)
    {
        if ($request->session()->has('admin_account_update_pending')) {
            return redirect()->route('admin.account')
                ->with('error', 'Please finish or cancel the pending email change OTP first.');
        }

        $request->session()->put('admin_password_change_form', true);

        return redirect()->route('admin.account');
    }

    public function cancelAccountPasswordChange(Request $request)
    {
        $request->session()->forget('admin_password_change_form');
        $request->session()->forget('admin_password_change_pending');

        return redirect()->route('admin.account');
    }

    // --- Forgot Password (same flow as customer: email → OTP → verify → reset) ---

    public function showForgotPasswordForm()
    {
        return view('Admin.forgot-password');
    }

    public function sendForgotPasswordOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email'    => 'Please enter a valid email address.',
        ]);

        $email = strtolower(trim((string) $request->email));
        $user = $this->firestore->firstWhere('admins', 'email', $email);
        if (!$user) {
            return redirect()->back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'No account found with this email address.']);
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->firestore->update('admins', (string) $user['id'], [
            'otp'            => $otp,
            'otp_expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);
        $this->forgetCachedAdminDoc($user, $email);

        $request->session()->put('admin_forgot_password_email', $email);

        try {
            Mail::to($email)->send(new OtpVerificationMail($otp, $email));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->back()
                ->with('error', 'Could not send the verification code. Please try again later.');
        }

        return redirect()->route('admin.forgot-password.verify-otp')
            ->with('success', 'A 4-digit code has been sent to your email. Enter it below.');
    }

    public function showForgotPasswordOtpForm(Request $request)
    {
        $email = $request->session()->get('admin_forgot_password_email');
        if (!$email) {
            return redirect()->route('admin.forgot-password')
                ->with('error', 'Please enter your email first to receive a verification code.');
        }
        return view('Admin.verify-otp-forgot', ['email' => $email]);
    }

    public function resendForgotPasswordOtp(Request $request)
    {
        $email = $request->session()->get('admin_forgot_password_email');
        if (!$email) {
            return redirect()->route('admin.forgot-password')
                ->with('error', 'Session expired. Please enter your email again.');
        }

        $user = $this->firestore->firstWhere('admins', 'email', strtolower(trim((string) $email)));
        if (!$user) {
            $request->session()->forget('admin_forgot_password_email');
            return redirect()->route('admin.forgot-password')->with('error', 'Account not found.');
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->firestore->update('admins', (string) $user['id'], [
            'otp'            => $otp,
            'otp_expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);
        $this->forgetCachedAdminDoc($user, strtolower(trim((string) ($user['email'] ?? $email))));

        try {
            Mail::to((string) ($user['email'] ?? $email))->send(new OtpVerificationMail($otp, (string) ($user['email'] ?? $email)));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('admin.forgot-password.verify-otp')
                ->with('error', 'Could not send the new code. Please try again later.');
        }

        return redirect()->route('admin.forgot-password.verify-otp')
            ->with('success', 'A new 4-digit code has been sent to your email.');
    }

    public function verifyForgotPasswordOtp(Request $request)
    {
        $email = $request->session()->get('admin_forgot_password_email');
        if (!$email) {
            return redirect()->route('admin.forgot-password')
                ->with('error', 'Session expired. Please enter your email again.');
        }

        $request->validate([
            'otp' => 'required|string|size:4|regex:/^\d{4}$/',
        ], [
            'otp.required' => 'Please enter the 4-digit code.',
            'otp.size'     => 'The code must be 4 digits.',
            'otp.regex'    => 'The code must be 4 digits only.',
        ]);

        $user = $this->firestore->firstWhere('admins', 'email', strtolower(trim((string) $email)));
        if (!$user) {
            $request->session()->forget('admin_forgot_password_email');
            return redirect()->route('admin.forgot-password')->with('error', 'Account not found.');
        }

        if ((string) ($user['otp'] ?? '') !== (string) $request->otp) {
            return redirect()->back()
                ->withErrors(['otp' => 'Invalid or expired code. Please try again.']);
        }

        if (!empty($user['otp_expires_at']) && Carbon::parse((string) $user['otp_expires_at'])->isPast()) {
            return redirect()->back()
                ->withErrors(['otp' => 'This code has expired. Please request a new one.']);
        }

        $this->firestore->update('admins', (string) $user['id'], ['otp' => null, 'otp_expires_at' => null]);
        $this->forgetCachedAdminDoc($user, strtolower(trim((string) ($user['email'] ?? $email))));

        return redirect()->route('admin.forgot-password.reset-password')
            ->with('success', 'Code verified. Enter your new password below.');
    }

    public function showResetPasswordForm(Request $request)
    {
        $email = $request->session()->get('admin_forgot_password_email');
        if (!$email) {
            return redirect()->route('admin.forgot-password')
                ->with('error', 'Session expired. Please start the process again.');
        }
        return view('Admin.reset-password', ['email' => $email]);
    }

    public function updatePassword(Request $request)
    {
        $email = $request->session()->get('admin_forgot_password_email');
        if (!$email) {
            return redirect()->route('admin.forgot-password')
                ->with('error', 'Session expired. Please start the process again.');
        }

        $request->validate([
            'password' => 'required|string|confirmed|min:6',
        ], [
            'password.required'  => 'Password is required.',
            'password.confirmed' => 'Passwords do not match.',
            'password.min'       => 'Password must be at least 6 characters.',
        ]);

        $user = $this->firestore->firstWhere('admins', 'email', strtolower(trim((string) $email)));
        if (!$user) {
            $request->session()->forget('admin_forgot_password_email');
            return redirect()->route('admin.forgot-password')->with('error', 'Account not found.');
        }

        $this->firestore->update('admins', (string) $user['id'], ['password' => Hash::make((string) $request->password)]);
        $this->forgetCachedAdminDoc($user, strtolower(trim((string) ($user['email'] ?? $email))));

        $request->session()->forget('admin_forgot_password_email');

        return redirect()->route('admin.login')->with('success', 'Your password has been updated. You can now log in.');
    }
}
