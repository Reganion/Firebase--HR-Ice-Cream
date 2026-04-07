<?php

use App\Http\Controllers\Api\ApiAddressController;
use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\ApiCartController;
use App\Http\Controllers\Api\ApiDriverAuthController;
use App\Http\Controllers\Api\ApiDriverNotificationController;
use App\Http\Controllers\Api\ApiDriverShipmentController;
use App\Http\Controllers\Api\ApiFavoriteController;
use App\Http\Controllers\Api\ApiFlavorController;
use App\Http\Controllers\Api\ApiNotificationController;
use App\Http\Controllers\Api\ApiOrderController;
use App\Http\Controllers\Api\ApiChatController;
use App\Http\Controllers\Api\ApiOrderMessageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiOrderPaymentController;
use App\Http\Controllers\Api\ApiGeoController;

/*
|--------------------------------------------------------------------------
| API Routes (for Flutter / mobile app)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Public
    Route::post('/login', [ApiAuthController::class, 'login']);
    Route::post('/driver/login', [ApiDriverAuthController::class, 'login']);
    Route::post('/driver/forgot-password', [ApiDriverAuthController::class, 'forgotPassword']);
    Route::post('/driver/forgot-password/resend-otp', [ApiDriverAuthController::class, 'forgotPasswordResendOtp']);
    Route::post('/driver/forgot-password/verify-otp', [ApiDriverAuthController::class, 'forgotPasswordVerifyOtp']);
    Route::post('/driver/forgot-password/reset-password', [ApiDriverAuthController::class, 'forgotPasswordResetPassword']);
    Route::post('/register', [ApiAuthController::class, 'register']);
    Route::post('/verify-otp', [ApiAuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [ApiAuthController::class, 'resendOtp']);
    Route::post('/google-login', [ApiAuthController::class, 'googleLogin']); 
    Route::post('/google-sign-in', [ApiAuthController::class, 'googleSignIn']);
    Route::post('/google-sign-up', [ApiAuthController::class, 'googleSignUp']);

    // Forgot password (email -> OTP -> verify -> reset)
    Route::post('/forgot-password', [ApiAuthController::class, 'forgotPassword']);
    Route::post('/forgot-password/resend-otp', [ApiAuthController::class, 'resendForgotPasswordOtp']);
    Route::post('/forgot-password/verify-otp', [ApiAuthController::class, 'verifyForgotPasswordOtp']);
    Route::post('/forgot-password/reset-password', [ApiAuthController::class, 'resetPassword']);

    Route::get('/best-sellers', [ApiFlavorController::class, 'bestSellers']);
    Route::get('/popular', [ApiFlavorController::class, 'popular']);
    Route::get('/flavors', [ApiFlavorController::class, 'index']);
    Route::get('/flavors/{id}', [ApiFlavorController::class, 'show']);
    Route::get('/gallons', [ApiFlavorController::class, 'gallons']);

    // Google Maps config (Flutter Maps SDK key — restrict in Google Cloud Console)
    Route::get('/geo/maps-config', [ApiGeoController::class, 'mapsConfig']);
    // Geocoding (public; consider protecting if order IDs are guessable)
    Route::get('/geo/orders/{orderId}/geocode', [ApiGeoController::class, 'geocodeOrderAddress']);
    // Driver GPS (legacy: body includes driver_id). Prefer POST /driver/geo/location with Bearer driver token.
    Route::post('/geo/location', [ApiGeoController::class, 'updateLocation']);

    // Protected (require session token: Authorization: Bearer {token} or X-Session-Token)
    Route::middleware('api.customer')->group(function () {
        // Orders + downpayment via QRPH
        Route::post('/orders/downpayment', [ApiOrderPaymentController::class, 'createDownpayment']);
        Route::get('/orders/downpayment/status/{invoiceId}', [ApiOrderPaymentController::class, 'checkDownpaymentStatus']);
        Route::post('/orders/downpayment/cancel/{invoiceId}', [ApiOrderPaymentController::class, 'cancelDownpayment']);
        Route::post('/logout', [ApiAuthController::class, 'logout']);
        Route::get('/me', [ApiAuthController::class, 'me']);
        Route::get('/profile', [ApiAuthController::class, 'profile']);
        Route::post('/profile/update', [ApiAuthController::class, 'updateProfile']);
        // Account: fetch logged-in account (account information) and update profile
        Route::get('/account', [ApiAuthController::class, 'account']);
        Route::post('/account/update', [ApiAuthController::class, 'updateProfile']);
        Route::delete('/account', [ApiAuthController::class, 'deleteAccount']);
        Route::put('/address', [ApiAuthController::class, 'updateAddress']);
        Route::post('/address', [ApiAuthController::class, 'updateAddress']);
        // Customer addresses (clone table linked by customer_id)
        Route::get('/addresses', [ApiAddressController::class, 'index']);
        Route::post('/addresses', [ApiAddressController::class, 'store']);
        Route::get('/addresses/{id}', [ApiAddressController::class, 'show']);
        Route::put('/addresses/{id}', [ApiAddressController::class, 'update']);
        Route::patch('/addresses/{id}', [ApiAddressController::class, 'update']);
        Route::delete('/addresses/{id}', [ApiAddressController::class, 'destroy']);
        Route::post('/addresses/{id}/default', [ApiAddressController::class, 'setDefault']);
        // Change password: email → send OTP → verify OTP → update (current + new password, keep_logged_in)
        Route::post('/change-password/send-otp', [ApiAuthController::class, 'changePasswordSendOtp']);
        Route::post('/change-password/verify-otp', [ApiAuthController::class, 'changePasswordVerifyOtp']);
        Route::post('/change-password/resend-otp', [ApiAuthController::class, 'changePasswordResendOtp']);
        Route::post('/change-password/update', [ApiAuthController::class, 'changePasswordUpdate']);
        Route::get('/orders', [ApiOrderController::class, 'index']);
        Route::post('/orders', [ApiOrderController::class, 'store']);
        Route::get('/orders/{id}', [ApiOrderController::class, 'show']);
        Route::patch('/orders/{id}/cancel', [ApiOrderController::class, 'cancel']);
        Route::post('/orders/{id}/feedback', [ApiOrderController::class, 'feedback']);
        Route::get('/orders/{id}/messages', [ApiOrderMessageController::class, 'customerMessages']);
        Route::post('/orders/{id}/messages', [ApiOrderMessageController::class, 'customerSend']);
        Route::post('/orders/{id}/messages/read', [ApiOrderMessageController::class, 'customerMarkRead']);
        Route::post('/orders/{id}/messages/archive', [ApiOrderMessageController::class, 'customerArchive']);
        Route::post('/orders/{id}/messages/archive-selected', [ApiOrderMessageController::class, 'customerArchiveSelected']);
        Route::post('/orders/{id}/messages/unarchive', [ApiOrderMessageController::class, 'customerUnarchive']);
        // Favorites (heart icon): list, add/remove toggle, check, delete
        Route::get('/favorites', [ApiFavoriteController::class, 'index']);
        Route::post('/favorites', [ApiFavoriteController::class, 'store']);
        Route::get('/favorites/check', [ApiFavoriteController::class, 'check']);
        Route::delete('/favorites/{flavor_id}', [ApiFavoriteController::class, 'destroy']);
        // Cart: list, add, update quantity, remove
        Route::get('/cart', [ApiCartController::class, 'index']);
        Route::post('/cart', [ApiCartController::class, 'store']);
        Route::put('/cart/{id}', [ApiCartController::class, 'update']);
        Route::patch('/cart/{id}', [ApiCartController::class, 'update']);
        Route::delete('/cart/{id}', [ApiCartController::class, 'destroy']);
        // Notifications (for Flutter customer app)
        Route::get('/notifications', [ApiNotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [ApiNotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [ApiNotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [ApiNotificationController::class, 'markAllRead']);
        Route::delete('/notifications/{id}', [ApiNotificationController::class, 'destroy']);
        Route::delete('/notifications', [ApiNotificationController::class, 'destroyAll']);
        // Chat with admin (for Flutter customer app)
        Route::get('/chat', [ApiChatController::class, 'index']);
        Route::get('/chat/messages', [ApiChatController::class, 'messages']);
        Route::post('/chat/messages', [ApiChatController::class, 'store']);
        Route::post('/chat/read', [ApiChatController::class, 'markRead']);
        Route::post('/push/token', [ApiAuthController::class, 'updateFcmToken']);
        Route::delete('/push/token', [ApiAuthController::class, 'clearFcmToken']);
        // Live map: destination + driver + route polyline (customer must own the order)
        Route::get('/geo/orders/{orderId}/track', [ApiGeoController::class, 'trackOrderForCustomer']);
        Route::post('/geo/customer/location', [ApiGeoController::class, 'customerUpdateLocation']);
    });

    // Driver protected endpoints
    Route::middleware('api.driver')->prefix('driver')->group(function () {
        Route::get('/me', [ApiDriverAuthController::class, 'me']);
        Route::post('/logout', [ApiDriverAuthController::class, 'logout']);
        Route::post('/change-phone', [ApiDriverAuthController::class, 'changePhone']);
        Route::post('/change-email/send-otp', [ApiDriverAuthController::class, 'changeEmailSendOtp']);
        Route::post('/change-email/verify-otp', [ApiDriverAuthController::class, 'changeEmailVerifyOtp']);
        Route::post('/change-email/resend-otp', [ApiDriverAuthController::class, 'changeEmailResendOtp']);
        Route::post('/change-password/send-otp', [ApiDriverAuthController::class, 'changePasswordSendOtp']);
        Route::post('/change-password/verify-otp', [ApiDriverAuthController::class, 'changePasswordVerifyOtp']);
        Route::post('/change-password/resend-otp', [ApiDriverAuthController::class, 'changePasswordResendOtp']);
        Route::post('/push/token', [ApiDriverAuthController::class, 'updateFcmToken']);
        Route::delete('/push/token', [ApiDriverAuthController::class, 'clearFcmToken']);
        Route::get('/shipments', [ApiDriverShipmentController::class, 'index']);
        Route::get('/shipments/{id}', [ApiDriverShipmentController::class, 'show']);
        Route::post('/shipments/{id}/accept', [ApiDriverShipmentController::class, 'accept']);
        Route::post('/shipments/{id}/reject', [ApiDriverShipmentController::class, 'reject']);
        Route::post('/shipments/{id}/deliver', [ApiDriverShipmentController::class, 'deliver']);
        Route::post('/shipments/{id}/complete', [ApiDriverShipmentController::class, 'complete']);
        Route::get('/notifications', [ApiDriverNotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [ApiDriverNotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [ApiDriverNotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [ApiDriverNotificationController::class, 'markAllRead']);
        Route::delete('/notifications/{id}', [ApiDriverNotificationController::class, 'destroy']);
        Route::delete('/notifications', [ApiDriverNotificationController::class, 'destroyAll']);
        Route::get('/messages/archived-threads', [ApiOrderMessageController::class, 'driverArchivedThreads']);
        Route::get('/shipments/{id}/messages', [ApiOrderMessageController::class, 'driverMessages']);
        Route::post('/shipments/{id}/messages', [ApiOrderMessageController::class, 'driverSend']);
        Route::post('/shipments/{id}/messages/read', [ApiOrderMessageController::class, 'driverMarkRead']);
        Route::post('/shipments/{id}/messages/archive', [ApiOrderMessageController::class, 'driverArchive']);
        Route::post('/shipments/{id}/messages/unarchive', [ApiOrderMessageController::class, 'driverUnarchive']);
        // Driver GPS + same tracker payload when assigned to the order
        Route::post('/geo/location', [ApiGeoController::class, 'driverUpdateLocation']);
        Route::get('/geo/orders/{orderId}/track', [ApiGeoController::class, 'trackOrderForDriver']);
    });
});
