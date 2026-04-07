<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirestoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function __construct(private FirestoreService $firestore)
    {
    }

    /**
     * Store a newly created admin (from Add Admin page).
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|max:255',
            'image'      => 'nullable|image|max:2048',
            'password'   => 'required|string|confirmed|min:6',
        ], [
            'first_name.required'  => 'First name is required.',
            'last_name.required'  => 'Last name is required.',
            'email.required'      => 'Email is required.',
            'password.required'   => 'Password is required.',
            'password.confirmed'  => 'Passwords do not match.',
            'password.min'        => 'Password must be at least 6 characters.',
        ]);

        $email = strtolower(trim((string) $request->email));
        $existing = $this->firestore->firstWhere('admins', 'email', $email);
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

        return redirect()->route('admin.admins.create')->with('success', 'Admin created successfully.');
    }
}
