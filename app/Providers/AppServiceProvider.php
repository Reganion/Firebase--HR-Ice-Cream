<?php

namespace App\Providers;

use App\Support\AdminNotification;
use App\Services\FirestoreService;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Notifications live in Firestore, not SQL/Eloquent — no model observers here.

        View::composer([
            'admin.layout.layout',
            'admin.account',
            'admin.support-centre',
            'Admin.Layout.layout',
            'Admin.account',
            'Admin.support-centre',
            'Admin.*',
            'admin.*',
        ], function ($view) {
            $adminUser = null;
            $adminNotifications = collect();
            if (session()->has('admin_id')) {
                $adminId = (string) session('admin_id');
                $adminEmail = strtolower(trim((string) session('admin_email', '')));
                $firestore = app(FirestoreService::class);
                $adminUser = $firestore->resolveAdminForSession($adminId, $adminEmail);

                if ($adminUser) {
                    if (!empty($adminUser['otp_expires_at'])) {
                        $adminUser['otp_expires_at'] = Carbon::parse((string) $adminUser['otp_expires_at']);
                    }
                    $resolvedId = (string) ($adminUser['id'] ?? '');
                    $adminNotifications = collect($firestore->rememberWhereAdminNotificationsForUser($resolvedId, 30))
                        ->filter(fn ($n) => AdminNotification::shouldShowInAdminFeed($n))
                        ->sortByDesc(fn ($n) => (string) ($n['created_at'] ?? ''))
                        ->take(50)
                        ->map(fn (array $n) => (object) $n)
                        ->values();
                    $adminUser = (object) $adminUser;
                }
            }
            $view->with('adminUser', $adminUser)->with('adminNotifications', $adminNotifications);
        });
    }
}
