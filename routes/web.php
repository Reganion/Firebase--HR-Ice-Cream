<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Customer\CustomerPageController;
use App\Http\Controllers\Customer\CustomerAuthController;

use App\Http\Controllers\Admin\AdminPagesController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\FlavorController;
use App\Http\Controllers\Admin\GallonController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminChatController;
use App\Http\Controllers\SuperAdmin\SuperAdminAuthController;
use App\Http\Controllers\Api\ApiOrderPaymentController;

Route::get('/', [CustomerPageController::class, 'home'])
    ->name('customer.home');

Route::get('/superadmin', [SuperAdminAuthController::class, 'page'])->name('superadmin.page');
Route::get('/superadmin/home', [SuperAdminAuthController::class, 'page'])->name('superadmin.home');
Route::post('/superadmin/setup', [SuperAdminAuthController::class, 'setup'])->name('superadmin.setup');
Route::post('/superadmin/login', [SuperAdminAuthController::class, 'login'])->name('superadmin.login');
Route::post('/superadmin/create-admin', [SuperAdminAuthController::class, 'createAdmin'])->name('superadmin.create-admin');
Route::post('/superadmin/logout', [SuperAdminAuthController::class, 'logout'])->name('superadmin.logout');

Route::get('/payment/qrph/qrph', [ApiOrderPaymentController::class, 'qrindex']);

Route::get('/about', [CustomerPageController::class, 'about'])->name('customer.about');
Route::get('/about', [CustomerPageController::class, 'about'])->name('customer.about');
Route::get('/Customer/login', [CustomerPageController::class, 'Customerlogin'])->name('customer.login');
Route::post('/Customer/login', [CustomerAuthController::class, 'login'])->name('customer.login.submit');
Route::get('/Customer/register', [CustomerPageController::class, 'register'])->name('customer.register');
Route::post('/Customer/register', [CustomerAuthController::class, 'register'])->name('customer.register.submit');
Route::get('/Customer/verify-otp', [CustomerAuthController::class, 'showOtpForm'])->name('customer.verify-otp');
Route::post('/Customer/verify-otp', [CustomerAuthController::class, 'verifyOtp'])->name('customer.verify-otp.submit');
Route::post('/Customer/resend-otp', [CustomerAuthController::class, 'resendOtp'])->name('customer.resend-otp');

Route::get('/Customer/forgot-password', [CustomerAuthController::class, 'showForgotPasswordForm'])->name('customer.forgot-password');
Route::post('/Customer/forgot-password', [CustomerAuthController::class, 'sendForgotPasswordOtp'])->name('customer.forgot-password.submit');
Route::get('/Customer/forgot-password/verify-otp', [CustomerAuthController::class, 'showForgotPasswordOtpForm'])->name('customer.forgot-password.verify-otp');
Route::post('/Customer/forgot-password/verify-otp', [CustomerAuthController::class, 'verifyForgotPasswordOtp'])->name('customer.forgot-password.verify-otp.submit');
Route::post('/Customer/forgot-password/resend-otp', [CustomerAuthController::class, 'resendForgotPasswordOtp'])->name('customer.forgot-password.resend-otp');
Route::get('/Customer/forgot-password/reset-password', [CustomerAuthController::class, 'showResetPasswordForm'])->name('customer.forgot-password.reset-password');
Route::post('/Customer/forgot-password/reset-password', [CustomerAuthController::class, 'updatePassword'])->name('customer.forgot-password.reset-password.submit');

Route::get('/driver', function () {
    return view('driver.landing');
})->name('driver.landing');


Route::get('/customer/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');

Route::get('/customer/change-password', [CustomerAuthController::class, 'showChangePasswordForm'])->name('customer.change-password');
Route::post('/customer/change-password/send-otp', [CustomerAuthController::class, 'sendChangePasswordOtp'])->name('customer.change-password.send-otp');
Route::get('/customer/change-password/verify-otp', [CustomerAuthController::class, 'showChangePasswordOtpForm'])->name('customer.change-password.verify-otp');
Route::post('/customer/change-password/verify-otp', [CustomerAuthController::class, 'verifyChangePasswordOtp'])->name('customer.change-password.verify-otp.submit');
Route::post('/customer/change-password/resend-otp', [CustomerAuthController::class, 'resendChangePasswordOtp'])->name('customer.change-password.resend-otp');
Route::get('/customer/change-password/new-password', [CustomerAuthController::class, 'showChangePasswordNewPasswordForm'])->name('customer.change-password.new-password');
Route::post('/customer/change-password/update', [CustomerAuthController::class, 'updateChangePassword'])->name('customer.change-password.update');

// Admin guest routes (no login required)
Route::get('/admin/login', [AdminPagesController::class, 'login'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');
Route::post('/admin/register', [AdminAuthController::class, 'register'])->name('admin.register.submit');
Route::get('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

Route::get('/admin/forgot-password', [AdminAuthController::class, 'showForgotPasswordForm'])->name('admin.forgot-password');
Route::post('/admin/forgot-password', [AdminAuthController::class, 'sendForgotPasswordOtp'])->name('admin.forgot-password.submit');
Route::get('/admin/forgot-password/verify-otp', [AdminAuthController::class, 'showForgotPasswordOtpForm'])->name('admin.forgot-password.verify-otp');
Route::post('/admin/forgot-password/verify-otp', [AdminAuthController::class, 'verifyForgotPasswordOtp'])->name('admin.forgot-password.verify-otp.submit');
Route::post('/admin/forgot-password/resend-otp', [AdminAuthController::class, 'resendForgotPasswordOtp'])->name('admin.forgot-password.resend-otp');
Route::get('/admin/forgot-password/reset-password', [AdminAuthController::class, 'showResetPasswordForm'])->name('admin.forgot-password.reset-password');
Route::post('/admin/forgot-password/reset-password', [AdminAuthController::class, 'updatePassword'])->name('admin.forgot-password.reset-password.submit');

// Admin protected routes (login required)
Route::prefix('admin')->middleware('admin.auth')->group(function () {
    Route::post('/account/update', [AdminAuthController::class, 'updateAccount'])->name('admin.account.update');
    Route::post('/account/verify-email-otp', [AdminAuthController::class, 'verifyAccountEmailOtp'])->name('admin.account.verify-email-otp');
    Route::post('/account/resend-email-otp', [AdminAuthController::class, 'resendAccountEmailOtp'])->name('admin.account.resend-email-otp');
    Route::post('/account/cancel-email-otp', [AdminAuthController::class, 'cancelAccountEmailOtp'])->name('admin.account.cancel-email-otp');
    Route::post('/account/password/send-otp', [AdminAuthController::class, 'sendAccountPasswordOtp'])->name('admin.account.password.send-otp');
    Route::post('/account/password/verify-otp', [AdminAuthController::class, 'verifyAccountPasswordOtp'])->name('admin.account.password.verify-otp');
    Route::post('/account/password/resend-otp', [AdminAuthController::class, 'resendAccountPasswordOtp'])->name('admin.account.password.resend-otp');
    Route::post('/account/password/start', [AdminAuthController::class, 'startAccountPasswordChange'])->name('admin.account.password.start');
    Route::post('/account/password/cancel', [AdminAuthController::class, 'cancelAccountPasswordChange'])->name('admin.account.password.cancel');
});

Route::prefix('admin')->name('admin.')->middleware('admin.auth')->group(function () {
    Route::get('/dashboard', [AdminPagesController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard/chart-data', [AdminPagesController::class, 'dashboardChartData'])->name('dashboard.chart-data');
    Route::get('/dashboard/completed-orders', [AdminPagesController::class, 'dashboardCompletedOrders'])->name('dashboard.completed-orders');
    Route::get('/flavors', [AdminPagesController::class, 'flavors'])->name('flavors');
    Route::get('/ingredients', [AdminPagesController::class, 'ingredients'])->name('ingredients');
    Route::get('/gallon', [AdminPagesController::class, 'gallon'])->name('gallon');
    Route::get('/orders', [AdminPagesController::class, 'orders'])->name('orders');
    Route::get('/records', [AdminPagesController::class, 'records'])->name('records');
    Route::get('/support-centre', [AdminPagesController::class, 'supportCentre'])->name('support-centre');
    Route::get('/drivers', [AdminPagesController::class, 'drivers'])->name('drivers');
    Route::get('/customer', [AdminPagesController::class, 'customer'])->name('customer');
    Route::get('/reports', [AdminPagesController::class, 'reports'])->name('reports');
    Route::get('/archive', [AdminPagesController::class, 'archive'])->name('archive');
    Route::get('/account', [AdminPagesController::class, 'account'])->name('account');
    Route::get('/admins/create', [AdminPagesController::class, 'addAdmin'])->name('admins.create');
});

Route::prefix('admin')->middleware('admin.auth')->group(function () {
    Route::post('/ingredients', [IngredientController::class, 'store'])
        ->name('admin.ingredients.store');

    Route::put('/ingredients/{id}', [IngredientController::class, 'update'])
        ->name('admin.ingredients.update');

    Route::delete('/ingredients/{id}', [IngredientController::class, 'destroy'])
        ->name('admin.ingredients.destroy');

    Route::delete('/ingredients', [IngredientController::class, 'bulkDestroy'])
        ->name('admin.ingredients.bulk-destroy');
});

Route::prefix('admin')->middleware('admin.auth')->group(function () {
    Route::post('/drivers', [DriverController::class, 'store'])
        ->name('admin.drivers.store');

    Route::delete('/drivers/{id}', [DriverController::class, 'destroy'])
        ->name('admin.drivers.destroy');

    Route::post('/drivers/{id}/inactive', [DriverController::class, 'setInactive'])
        ->name('admin.drivers.inactive');

    Route::post('/drivers/{id}/activate', [DriverController::class, 'setActive'])
        ->name('admin.drivers.activate');

    Route::post('/drivers/{id}/archive', [DriverController::class, 'setArchived'])
        ->name('admin.drivers.archive');
});

Route::prefix('admin')->middleware('admin.auth')->group(function () {
    Route::post('/admins', [AdminController::class, 'store'])
        ->name('admin.admins.store');
});

Route::prefix('admin')->middleware('admin.auth')->group(function () {

    Route::post('/flavors', [FlavorController::class, 'flavorstore'])
        ->name('admin.flavors.store');

    Route::put('/flavors/{id}', [FlavorController::class, 'flavorupdate'])
        ->name('admin.flavors.update');

    Route::delete('/flavors/{id}', [FlavorController::class, 'flavordestroy'])
        ->name('admin.flavors.destroy');

    Route::delete('/flavors', [FlavorController::class, 'bulkDestroy'])
        ->name('admin.flavors.bulk-destroy');

});

Route::prefix('admin')->middleware('admin.auth')->group(function () {

    Route::post('/gallons', [GallonController::class, 'gallonstore'])
        ->name('admin.gallons.store');

Route::put('/gallons/{id}', [GallonController::class, 'gallonupdate'])
    ->name('admin.gallons.update');

Route::delete('/gallons/{id}', [GallonController::class, 'gallondestroy'])
    ->name('admin.gallons.destroy');

Route::delete('/gallons', [GallonController::class, 'bulkDestroy'])
    ->name('admin.gallons.bulk-destroy');

});

Route::prefix('admin')->name('admin.')->middleware('admin.auth')->group(function () {
    Route::get('/orders/list', [AdminOrderController::class, 'listJson'])
        ->name('orders.list');
    Route::get('/orders/drivers', [AdminOrderController::class, 'availableDriversJson'])
        ->name('orders.drivers');
    Route::get('/orders/{id}', [AdminOrderController::class, 'showJson'])
        ->name('orders.show');
    Route::post('/orders/walkin', [AdminOrderController::class, 'storeWalkIn'])
        ->name('orders.walkin');
    Route::put('/orders/{id}', [AdminOrderController::class, 'updateWalkIn'])
        ->name('orders.update');
    Route::post('/orders/{id}/assign', [AdminOrderController::class, 'assignDriver'])
        ->name('orders.assign');

    Route::get('/notifications', [AdminNotificationController::class, 'index'])
        ->name('notifications.index');
    Route::post('/notifications/{id}/read', [AdminNotificationController::class, 'markRead'])
        ->name('notifications.mark-read');
    Route::post('/notifications/read-all', [AdminNotificationController::class, 'markAllRead'])
        ->name('notifications.mark-all-read');

    Route::get('/chat/customers', [AdminChatController::class, 'customers'])
        ->name('chat.customers');
    Route::get('/chat/unread-summary', [AdminChatController::class, 'unreadSummary'])
        ->name('chat.unread-summary');
    Route::get('/chat/customers/{id}', [AdminChatController::class, 'show'])
        ->name('chat.customers.show');
    Route::get('/chat/customers/{id}/messages', [AdminChatController::class, 'messagesSince'])
        ->name('chat.customers.messages');
    Route::post('/chat/customers/{id}/messages', [AdminChatController::class, 'sendMessage'])
        ->name('chat.customers.send');
});


