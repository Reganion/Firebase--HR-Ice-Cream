<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\DriverWelcomePasswordMail;
use App\Services\FirestoreService;
use App\Support\FirestoreCacheKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class DriverController extends Controller
{
    private const STATUS_AVAILABLE = 'available';
    private const STATUS_DEACTIVATE = 'deactivate';
    private const STATUS_ARCHIVE = 'archive';

    public function __construct(private FirestoreService $firestore)
    {
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'phone'        => 'required|string|max:11|regex:/^[0-9]+$/',
            'email'        => 'required|email|max:255',
            'license_no'   => 'required|string|max:100',
            'license_type' => 'required|string|max:100',
            'image'        => 'nullable|image|max:2048',
        ], [
            'name.required'         => 'Driver name is required.',
            'name.unique'           => 'A driver with this full name already exists.',
            'phone.required'        => 'Phone number is required.',
            'phone.regex'           => 'Phone number must contain numbers only and be at most 11 digits.',
            'phone.unique'          => 'A driver with this phone number already exists.',
            'email.required'        => 'Email address is required.',
            'email.email'           => 'Please enter a valid email address.',
            'email.unique'          => 'A driver with this email address already exists.',
            'license_no.required'   => 'License number is required.',
            'license_type.required' => 'License type is required.',
        ]);

        $email = strtolower(trim((string) $request->email));
        if ($this->firestore->firstWhere('drivers', 'email', $email)) {
            return back()->withInput()->withErrors(['email' => 'A driver with this email address already exists.']);
        }
        if ($this->firestore->firstWhere('drivers', 'phone', (string) $request->phone)) {
            return back()->withInput()->withErrors(['phone' => 'A driver with this phone number already exists.']);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $dir = public_path('img/drivers');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $image = $request->file('image');
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $image->getClientOriginalName());
            $image->move($dir, $filename);
            $imagePath = 'img/drivers/' . $filename;
        }

        $drivers = $this->firestore->all('drivers', 'created_at', 'desc');
        $lastDriver = $drivers[0] ?? null;
        $nextNum = $lastDriver && !empty($lastDriver['driver_code'])
            ? (int) preg_replace('/\D/', '', (string) $lastDriver['driver_code']) + 1
            : 1;
        $driverCode = 'DRV' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
        $temporaryPassword = Str::upper(Str::random(10));

        $docId = $this->firestore->add('drivers', [
            'name'         => $request->name,
            'phone'        => $request->phone,
            'email'        => $email,
            'license_no'   => $request->license_no,
            'license_type' => $request->license_type,
            'image'        => $imagePath,
            'status'       => self::STATUS_AVAILABLE,
            'driver_code'  => $driverCode,
            'password'     => Hash::make($temporaryPassword),
        ]);
        $driver = $this->firestore->get('drivers', $docId) ?? ['email' => $email, 'name' => $request->name];
        FirestoreCacheKeys::invalidateDrivers();

        try {
            Mail::to((string) ($driver['email'] ?? $email))->send(
                new DriverWelcomePasswordMail((object) $driver, $temporaryPassword)
            );
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Driver was added, but sending the password email failed.');
        }

        return back()->with('success', 'Driver added successfully.');
    }

    public function destroy($id)
    {
        $driver = $this->firestore->get('drivers', (string) $id);
        if (!$driver) {
            return back()->with('error', 'Driver not found.');
        }
        $this->firestore->update('drivers', (string) $id, ['status' => self::STATUS_DEACTIVATE]);

        return back()->with('success', 'Driver removed successfully.');
    }

    public function setInactive($id)
    {
        $driver = $this->firestore->get('drivers', (string) $id);
        if (!$driver) {
            return back()->with('error', 'Driver not found.');
        }
        $this->firestore->update('drivers', (string) $id, ['status' => self::STATUS_DEACTIVATE]);
        FirestoreCacheKeys::invalidateDrivers();

        return back()->with('success', 'Driver set to inactive.');
    }

    public function setActive($id)
    {
        $driver = $this->firestore->get('drivers', (string) $id);
        if (!$driver) {
            return back()->with('error', 'Driver not found.');
        }
        $this->firestore->update('drivers', (string) $id, ['status' => self::STATUS_AVAILABLE]);
        FirestoreCacheKeys::invalidateDrivers();

        return back()->with('success', 'Driver activated successfully.');
    }

    public function setArchived($id)
    {
        $driver = $this->firestore->get('drivers', (string) $id);
        if (!$driver) {
            return back()->with('error', 'Driver not found.');
        }
        $this->firestore->update('drivers', (string) $id, ['status' => self::STATUS_ARCHIVE]);
        FirestoreCacheKeys::invalidateDrivers();

        return back()->with('success', 'Driver archived successfully.');
    }
}
