<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\FirestoreService;
use App\Support\FirestoreCacheKeys;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SuperAdminAuthController extends Controller
{
    private const SESSION_KEY = 'superadmin_firestore_id';

    public function __construct(private FirestoreService $firestore)
    {
    }

    public function page(Request $request): View
    {
        $superAdminId = (string) $request->session()->get(self::SESSION_KEY, '');
        $superAdmin = $superAdminId !== ''
            ? $this->firestore->get('superadmins', $superAdminId)
            : null;

        if ($superAdmin === null && $superAdminId !== '') {
            $request->session()->forget(self::SESSION_KEY);
        }

        return view('SuperAdmin.superadmin', [
            'isLoggedIn' => $superAdmin !== null,
            'superAdmin' => $superAdmin,
            'hasSuperAdmin' => Cache::remember(
                FirestoreCacheKeys::SUPERADMIN_HAS_ANY,
                600,
                fn () => count($this->firestore->all('superadmins')) > 0
            ),
        ]);
    }

    public function setup(Request $request): RedirectResponse
    {
        if (Cache::remember(
            FirestoreCacheKeys::SUPERADMIN_HAS_ANY,
            600,
            fn () => count($this->firestore->all('superadmins')) > 0
        )) {
            return back()->with('error', 'SuperAdmin already exists. Please use login.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $docId = $this->firestore->add('superadmins', [
            'name' => trim((string) $validated['name']),
            'email' => $email,
            'password' => Hash::make((string) $validated['password']),
            'status' => 'active',
        ]);

        $request->session()->put(self::SESSION_KEY, $docId);
        Cache::forget(FirestoreCacheKeys::SUPERADMIN_HAS_ANY);

        return redirect()->route('superadmin.home')->with('success', 'SuperAdmin account created.');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:190',
            'password' => 'required|string',
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $superAdmin = $this->firestore->firstWhere('superadmins', 'email', $email);

        if (!$superAdmin || !Hash::check((string) $validated['password'], (string) ($superAdmin['password'] ?? ''))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['password' => 'Invalid email or password.']);
        }

        $request->session()->put(self::SESSION_KEY, $superAdmin['id']);

        return redirect()->route('superadmin.home')->with('success', 'Welcome back!');
    }

    public function createAdmin(Request $request): RedirectResponse
    {
        $superAdminId = (string) $request->session()->get(self::SESSION_KEY, '');
        if ($superAdminId === '') {
            return redirect()->route('superadmin.page')->with('error', 'Please login first.');
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:120',
            'last_name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $firstName = trim((string) $validated['first_name']);
        $lastName = trim((string) $validated['last_name']);
        $fullName = trim($firstName . ' ' . $lastName);
        $email = strtolower(trim((string) $validated['email']));

        $existingAdmin = $this->firestore->firstWhere('admins', 'email', $email);
        if ($existingAdmin) {
            return back()
                ->withInput($request->only('first_name', 'last_name', 'email'))
                ->withErrors(['email' => 'Email already exists in Firestore admins.']);
        }

        $this->firestore->add('admins', [
            'name' => $fullName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make((string) $validated['password']),
            'created_by_superadmin_id' => $superAdminId,
            'status' => 'active',
        ]);

        return redirect()->route('superadmin.home')->with('success', 'Admin account created in Firestore.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('superadmin.page')->with('success', 'Logged out.');
    }
}
