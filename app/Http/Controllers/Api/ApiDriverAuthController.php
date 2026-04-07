<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpVerificationMail;
use App\Services\FirestoreService;
use App\Support\ApiDriverSession;
use App\Support\DriverStatuses;
use App\Support\FirestoreCacheKeys;
use App\Support\FirestoreDriverUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ApiDriverAuthController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    private const CHANGE_EMAIL_KEY_PREFIX = 'driver_change_email:';

    private const CHANGE_PASSWORD_KEY_PREFIX = 'driver_change_password:';

    private const FORGOT_PASSWORD_KEY_PREFIX = 'driver_forgot_password:';

    public function __construct(
        protected FirestoreService $firestore,
    ) {}

    /**
     * Authenticated session user (Firestore `drivers` doc as object).
     */
    private function isDriverUser(mixed $user): bool
    {
        return is_object($user) && isset($user->id) && (string) $user->id !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function driverByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        return $this->firestore->firstWhere('drivers', 'email', $email);
    }

    private function driverEmailTaken(string $email, ?string $exceptDriverId): bool
    {
        $found = $this->firestore->firstWhere('drivers', 'email', strtolower(trim($email)));
        if (! $found) {
            return false;
        }

        return (string) ($found['id'] ?? '') !== (string) ($exceptDriverId ?? '');
    }

    private function driverPhoneTaken(string $phone, ?string $exceptDriverId): bool
    {
        $phone = trim((string) $phone);
        $found = $this->firestore->firstWhere('drivers', 'phone', $phone);
        if (! $found) {
            return false;
        }

        return (string) ($found['id'] ?? '') !== (string) ($exceptDriverId ?? '');
    }

    /**
     * Driver login (for Flutter rider app).
     * POST /api/v1/driver/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = strtolower(trim((string) $request->email));
        $driver = $this->driverByEmail($email);

        if (! $driver || empty($driver['password']) || ! Hash::check($request->password, (string) $driver['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        $status = (string) ($driver['status'] ?? '');
        if ($status === DriverStatuses::DEACTIVATE || $status === DriverStatuses::ARCHIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact admin.',
            ], 403);
        }

        $token = Str::random(64);
        Cache::forever(ApiDriverSession::CACHE_PREFIX.$token, $driver['id']);
        $userObj = FirestoreDriverUser::fromArray($driver);
        if ($userObj) {
            $this->markDriverOnline($userObj);
        }

        $fresh = $this->firestore->get('drivers', (string) $driver['id']) ?? $driver;

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully.',
            'driver' => $this->driverProfileArray($fresh),
            'token' => $token,
        ]);
    }

    /**
     * Driver forgot password step 1: send OTP to driver's email.
     * POST /api/v1/driver/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:100',
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $email = strtolower(trim((string) $request->email));
        $driver = $this->driverByEmail($email);

        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'No driver account found with this email.',
            ], 404);
        }

        $status = (string) ($driver['status'] ?? '');
        if ($status === DriverStatuses::DEACTIVATE || $status === DriverStatuses::ARCHIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact admin.',
            ], 403);
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        Cache::put($this->forgotPasswordCacheKey($email), [
            'otp' => $otp,
            'verified' => false,
        ], now()->addMinutes(self::OTP_TTL_MINUTES));

        try {
            Mail::to($email)->send(new OtpVerificationMail($otp, $email));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not send OTP email. Please try again later.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP has been sent to your email.',
            'expires_in_minutes' => self::OTP_TTL_MINUTES,
        ]);
    }

    /**
     * Driver forgot password step 2: verify OTP.
     * POST /api/v1/driver/forgot-password/verify-otp
     */
    public function forgotPasswordVerifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:100',
            'otp' => 'required|string|size:4|regex:/^\d{4}$/',
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'otp.required' => 'Please enter the 4-digit OTP.',
            'otp.size' => 'The OTP must be 4 digits.',
            'otp.regex' => 'The OTP must be 4 digits only.',
        ]);

        $email = strtolower(trim((string) $request->email));
        $cacheKey = $this->forgotPasswordCacheKey($email);
        $payload = Cache::get($cacheKey);

        if (! $payload || ! is_array($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP expired. Please request a new OTP.',
            ], 422);
        }

        if ((string) ($payload['otp'] ?? '') !== (string) $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP. Please try again.',
            ], 422);
        }

        Cache::put($cacheKey, [
            'otp' => (string) ($payload['otp'] ?? ''),
            'verified' => true,
        ], now()->addMinutes(self::OTP_TTL_MINUTES));

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
        ]);
    }

    /**
     * Driver forgot password: resend OTP.
     * POST /api/v1/driver/forgot-password/resend-otp
     */
    public function forgotPasswordResendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:100',
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $email = strtolower(trim((string) $request->email));
        $driver = $this->driverByEmail($email);
        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'No driver account found with this email.',
            ], 404);
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        Cache::put($this->forgotPasswordCacheKey($email), [
            'otp' => $otp,
            'verified' => false,
        ], now()->addMinutes(self::OTP_TTL_MINUTES));

        try {
            Mail::to($email)->send(new OtpVerificationMail($otp, $email));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not resend OTP. Please try again later.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'A new OTP has been sent to your email.',
            'expires_in_minutes' => self::OTP_TTL_MINUTES,
        ]);
    }

    /**
     * Driver forgot password step 3: reset password after OTP verification.
     * POST /api/v1/driver/forgot-password/reset-password
     */
    public function forgotPasswordResetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:100',
            'new_password' => 'required|string|min:6|confirmed',
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'new_password.required' => 'Please enter your new password.',
            'new_password.min' => 'New password must be at least 6 characters.',
            'new_password.confirmed' => 'New password and retype password do not match.',
        ]);

        $email = strtolower(trim((string) $request->email));
        $driver = $this->driverByEmail($email);
        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'No driver account found with this email.',
            ], 404);
        }

        $cacheKey = $this->forgotPasswordCacheKey($email);
        $payload = Cache::get($cacheKey);
        if (! $payload || ! is_array($payload) || ! ($payload['verified'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP is not verified or has expired. Please verify OTP again.',
            ], 422);
        }

        if (! empty($driver['password']) && Hash::check($request->new_password, (string) $driver['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'New password must be different from current password.',
            ], 422);
        }

        $this->firestore->update('drivers', (string) $driver['id'], [
            'password' => Hash::make($request->new_password),
        ]);
        FirestoreCacheKeys::invalidateDrivers();

        Cache::forget($cacheKey);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Save/refresh FCM token for authenticated driver device.
     * POST /api/v1/driver/push/token
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! $this->isDriverUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $request->validate([
            'token' => 'nullable|string|max:2048',
            'fcm_token' => 'nullable|string|max:2048',
            'platform' => 'nullable|string|in:android,ios,web',
        ]);

        $rawToken = trim((string) ($request->input('token') ?? $request->input('fcm_token') ?? ''));
        if ($rawToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'Provide `token` or `fcm_token` (FCM registration token).',
            ], 422);
        }

        $this->firestore->update('drivers', (string) $driver->id, [
            'fcm_token' => $rawToken,
            'fcm_platform' => $request->filled('platform') ? trim((string) $request->input('platform')) : null,
        ]);
        FirestoreCacheKeys::invalidateDrivers();

        return response()->json([
            'success' => true,
            'message' => 'Push token saved successfully.',
        ]);
    }

    /**
     * Clear FCM token for authenticated driver (e.g. on logout).
     * DELETE /api/v1/driver/push/token
     */
    public function clearFcmToken(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! $this->isDriverUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $this->firestore->update('drivers', (string) $driver->id, [
            'fcm_token' => null,
            'fcm_platform' => null,
        ]);
        FirestoreCacheKeys::invalidateDrivers();

        return response()->json([
            'success' => true,
            'message' => 'Push token removed successfully.',
        ]);
    }

    /**
     * Get currently authenticated driver profile.
     * GET /api/v1/driver/me
     */
    public function me(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! $this->isDriverUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $row = $this->firestore->get('drivers', (string) $driver->id);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        return response()->json([
            'success' => true,
            'driver' => $this->driverProfileArray($row),
        ]);
    }

    /**
     * Driver logout and invalidate token.
     * POST /api/v1/driver/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $driver = $request->user();
        $token = $this->getTokenFromRequest($request);
        if ($token) {
            Cache::forget(ApiDriverSession::CACHE_PREFIX.$token);
        }
        if ($this->isDriverUser($driver)) {
            $this->markDriverOffline($driver);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out.',
        ]);
    }

    /**
     * Change email step 1: verify current password, generate OTP, send it to new email.
     * POST /api/v1/driver/change-email/send-otp
     */
    public function changeEmailSendOtp(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! $this->isDriverUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $request->validate([
            'current_password' => 'required|string',
            'new_email' => 'required|email|max:100',
        ], [
            'current_password.required' => 'Please enter your current password.',
            'new_email.required' => 'Please enter your new email address.',
            'new_email.email' => 'Please enter a valid email address.',
        ]);

        $newEmail = strtolower(trim((string) $request->new_email));
        if ($this->driverEmailTaken($newEmail, (string) $driver->id)) {
            return response()->json([
                'success' => false,
                'message' => 'This email is already used by another driver.',
            ], 422);
        }

        $row = $this->firestore->get('drivers', (string) $driver->id);
        if (! $row || empty($row['password']) || ! Hash::check($request->current_password, (string) $row['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        if (strcasecmp((string) ($row['email'] ?? ''), $newEmail) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Your new email must be different from your current email.',
            ], 422);
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        Cache::put(self::CHANGE_EMAIL_KEY_PREFIX.$driver->id, [
            'otp' => $otp,
            'new_email' => $newEmail,
        ], now()->addMinutes(self::OTP_TTL_MINUTES));

        try {
            Mail::to($newEmail)->send(new OtpVerificationMail($otp, $newEmail));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not send OTP to the new email. Please try again later.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP has been sent to your new email.',
            'expires_in_minutes' => self::OTP_TTL_MINUTES,
        ]);
    }

    /**
     * Change email step 2: verify OTP and update email.
     * POST /api/v1/driver/change-email/verify-otp
     */
    public function changeEmailVerifyOtp(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! $this->isDriverUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $request->validate([
            'otp' => 'required|string|size:4|regex:/^\d{4}$/',
        ], [
            'otp.required' => 'Please enter the 4-digit OTP.',
            'otp.size' => 'The OTP must be 4 digits.',
            'otp.regex' => 'The OTP must be 4 digits only.',
        ]);

        $payload = Cache::get(self::CHANGE_EMAIL_KEY_PREFIX.$driver->id);
        if (! $payload || ! is_array($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP expired. Please request a new OTP.',
            ], 422);
        }

        $storedOtp = (string) ($payload['otp'] ?? '');
        $newEmail = (string) ($payload['new_email'] ?? '');

        if ($storedOtp !== (string) $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP. Please try again.',
            ], 422);
        }

        if ($newEmail === '') {
            Cache::forget(self::CHANGE_EMAIL_KEY_PREFIX.$driver->id);

            return response()->json([
                'success' => false,
                'message' => 'Email change session is invalid. Please try again.',
            ], 422);
        }

        if ($this->driverEmailTaken($newEmail, (string) $driver->id)) {
            Cache::forget(self::CHANGE_EMAIL_KEY_PREFIX.$driver->id);

            return response()->json([
                'success' => false,
                'message' => 'This email is already used by another driver.',
            ], 422);
        }

        $this->firestore->update('drivers', (string) $driver->id, ['email' => $newEmail]);
        FirestoreCacheKeys::invalidateDrivers();
        Cache::forget(self::CHANGE_EMAIL_KEY_PREFIX.$driver->id);

        $fresh = $this->firestore->get('drivers', (string) $driver->id) ?? [];

        return response()->json([
            'success' => true,
            'message' => 'Email updated successfully.',
            'driver' => $this->driverProfileArray($fresh),
        ]);
    }

    /**
     * Change email: resend OTP to pending new email.
     * POST /api/v1/driver/change-email/resend-otp
     */
    public function changeEmailResendOtp(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! $this->isDriverUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $payload = Cache::get(self::CHANGE_EMAIL_KEY_PREFIX.$driver->id);
        $newEmail = is_array($payload) ? (string) ($payload['new_email'] ?? '') : '';
        if ($newEmail === '') {
            return response()->json([
                'success' => false,
                'message' => 'No pending email change found. Start again by sending OTP.',
            ], 422);
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        Cache::put(self::CHANGE_EMAIL_KEY_PREFIX.$driver->id, [
            'otp' => $otp,
            'new_email' => $newEmail,
        ], now()->addMinutes(self::OTP_TTL_MINUTES));

        try {
            Mail::to($newEmail)->send(new OtpVerificationMail($otp, $newEmail));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not resend OTP. Please try again later.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'A new OTP has been sent to your new email.',
            'expires_in_minutes' => self::OTP_TTL_MINUTES,
        ]);
    }

    /**
     * Update driver's phone number.
     * POST /api/v1/driver/change-phone
     */
    public function changePhone(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! $this->isDriverUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $request->validate([
            'phone' => 'required|string|max:30',
        ], [
            'phone.required' => 'Please enter your phone number.',
        ]);

        $newPhone = trim((string) $request->phone);
        if ($newPhone === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please enter your phone number.',
            ], 422);
        }

        if ($this->driverPhoneTaken($newPhone, (string) $driver->id)) {
            return response()->json([
                'success' => false,
                'message' => 'This phone number is already used by another driver.',
            ], 422);
        }

        $row = $this->firestore->get('drivers', (string) $driver->id) ?? [];
        if (($row['phone'] ?? '') === $newPhone) {
            return response()->json([
                'success' => true,
                'message' => 'Phone number is already up to date.',
                'driver' => $this->driverProfileArray($row),
            ]);
        }

        $this->firestore->update('drivers', (string) $driver->id, ['phone' => $newPhone]);
        FirestoreCacheKeys::invalidateDrivers();

        $fresh = $this->firestore->get('drivers', (string) $driver->id) ?? [];

        return response()->json([
            'success' => true,
            'message' => 'Phone number updated successfully.',
            'driver' => $this->driverProfileArray($fresh),
        ]);
    }

    /**
     * Change password step 1: validate current/new password, then send OTP to driver's current email.
     * POST /api/v1/driver/change-password/send-otp
     */
    public function changePasswordSendOtp(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! $this->isDriverUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $row = $this->firestore->get('drivers', (string) $driver->id);
        if (! $row || empty($row['email'])) {
            return response()->json([
                'success' => false,
                'message' => 'Driver email is not set. Please contact admin.',
            ], 422);
        }

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ], [
            'current_password.required' => 'Please enter your current password.',
            'new_password.required' => 'Please enter your new password.',
            'new_password.confirmed' => 'New password and retype password do not match.',
            'new_password.min' => 'New password must be at least 6 characters.',
        ]);

        if (empty($row['password']) || ! Hash::check($request->current_password, (string) $row['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        if (Hash::check($request->new_password, (string) $row['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'New password must be different from current password.',
            ], 422);
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        Cache::put(self::CHANGE_PASSWORD_KEY_PREFIX.$driver->id, [
            'otp' => $otp,
            'new_password_hash' => Hash::make($request->new_password),
        ], now()->addMinutes(self::OTP_TTL_MINUTES));

        try {
            Mail::to((string) $row['email'])->send(new OtpVerificationMail($otp, (string) $row['email']));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not send OTP email. Please try again later.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP has been sent to your email.',
            'expires_in_minutes' => self::OTP_TTL_MINUTES,
        ]);
    }

    /**
     * Change password step 2: verify OTP and update password.
     * POST /api/v1/driver/change-password/verify-otp
     */
    public function changePasswordVerifyOtp(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! $this->isDriverUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $request->validate([
            'otp' => 'required|string|size:4|regex:/^\d{4}$/',
        ], [
            'otp.required' => 'Please enter the 4-digit OTP.',
            'otp.size' => 'The OTP must be 4 digits.',
            'otp.regex' => 'The OTP must be 4 digits only.',
        ]);

        $payload = Cache::get(self::CHANGE_PASSWORD_KEY_PREFIX.$driver->id);
        if (! $payload || ! is_array($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP expired. Please request a new OTP.',
            ], 422);
        }

        $storedOtp = (string) ($payload['otp'] ?? '');
        $newPasswordHash = (string) ($payload['new_password_hash'] ?? '');

        if ($storedOtp !== (string) $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP. Please try again.',
            ], 422);
        }

        if ($newPasswordHash === '') {
            Cache::forget(self::CHANGE_PASSWORD_KEY_PREFIX.$driver->id);

            return response()->json([
                'success' => false,
                'message' => 'Password change session is invalid. Please try again.',
            ], 422);
        }

        $this->firestore->update('drivers', (string) $driver->id, [
            'password' => $newPasswordHash,
        ]);
        FirestoreCacheKeys::invalidateDrivers();
        Cache::forget(self::CHANGE_PASSWORD_KEY_PREFIX.$driver->id);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Change password: resend OTP to current driver email.
     * POST /api/v1/driver/change-password/resend-otp
     */
    public function changePasswordResendOtp(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! $this->isDriverUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $row = $this->firestore->get('drivers', (string) $driver->id);
        if (! $row || empty($row['email'])) {
            return response()->json([
                'success' => false,
                'message' => 'Driver email is not set. Please contact admin.',
            ], 422);
        }

        $payload = Cache::get(self::CHANGE_PASSWORD_KEY_PREFIX.$driver->id);
        $newPasswordHash = is_array($payload) ? (string) ($payload['new_password_hash'] ?? '') : '';
        if ($newPasswordHash === '') {
            return response()->json([
                'success' => false,
                'message' => 'No pending password change found. Start again by sending OTP.',
            ], 422);
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        Cache::put(self::CHANGE_PASSWORD_KEY_PREFIX.$driver->id, [
            'otp' => $otp,
            'new_password_hash' => $newPasswordHash,
        ], now()->addMinutes(self::OTP_TTL_MINUTES));

        try {
            Mail::to((string) $row['email'])->send(new OtpVerificationMail($otp, (string) $row['email']));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not resend OTP. Please try again later.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'A new OTP has been sent to your email.',
            'expires_in_minutes' => self::OTP_TTL_MINUTES,
        ]);
    }

    /**
     * @param  array<string, mixed>  $driver
     */
    private function driverProfileArray(array $driver): array
    {
        $imagePath = $driver['image'] ?? null;

        return [
            'id' => (string) ($driver['id'] ?? ''),
            'name' => $driver['name'] ?? null,
            'email' => $driver['email'] ?? null,
            'phone' => $driver['phone'] ?? null,
            'status' => $driver['status'] ?? null,
            'driver_code' => $driver['driver_code'] ?? null,
            'license_no' => $driver['license_no'] ?? null,
            'license_type' => $driver['license_type'] ?? null,
            'image' => $imagePath,
            'image_url' => $imagePath ? url((string) $imagePath) : null,
        ];
    }

    private function getTokenFromRequest(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if ($header && preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }

        return $request->header('X-Session-Token') ?: null;
    }

    private function forgotPasswordCacheKey(string $email): string
    {
        return self::FORGOT_PASSWORD_KEY_PREFIX.sha1(strtolower(trim($email)));
    }

    private function markDriverOnline(object $driver): void
    {
        $id = (string) $driver->id;
        $status = (string) ($driver->status ?? '');
        if (
            $status === DriverStatuses::DEACTIVATE ||
            $status === DriverStatuses::ARCHIVE ||
            $status === DriverStatuses::ON_ROUTE
        ) {
            Cache::put(
                ApiDriverSession::ONLINE_KEY_PREFIX.$id,
                true,
                now()->addMinutes(ApiDriverSession::ONLINE_PRESENCE_TTL_MINUTES)
            );

            return;
        }

        $this->firestore->update('drivers', $id, ['status' => DriverStatuses::AVAILABLE]);
        FirestoreCacheKeys::invalidateDrivers();
        $driver->status = DriverStatuses::AVAILABLE;
    }

    private function markDriverOffline(object $driver): void
    {
        $id = (string) $driver->id;
        Cache::forget(ApiDriverSession::ONLINE_KEY_PREFIX.$id);

        $status = (string) ($driver->status ?? '');
        if (
            $status === DriverStatuses::DEACTIVATE ||
            $status === DriverStatuses::ARCHIVE ||
            $status === DriverStatuses::ON_ROUTE
        ) {
            return;
        }

        $this->firestore->update('drivers', $id, ['status' => DriverStatuses::OFF_DUTY]);
        FirestoreCacheKeys::invalidateDrivers();
    }
}
