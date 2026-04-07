<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirestoreService;
use App\Support\FirestoreCacheKeys;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class FlavorController extends Controller
{
    public function __construct(private FirestoreService $firestore)
    {
    }

    private function saveFlavorImage(?UploadedFile $file, ?string $existingPath = null): ?string
    {
        if ($file === null || !$file->isValid()) {
            return $existingPath;
        }
        $dir = public_path('flavors');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->move($dir, $filename);
        return 'flavors/' . $filename;
    }

    public function flavorstore(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'flavor_type'  => 'required|string|max:255',
            'category'     => 'required|in:Plain Flavor,Special Flavor,1 Topping,2 Toppings',
            'price'        => 'required|numeric|min:0',
            'image'        => 'nullable|image|max:2048',
            'mobile_image' => 'nullable|image|max:2048',
        ]);

        $imagePath = $this->saveFlavorImage($request->file('image'));
        $mobileImagePath = $this->saveFlavorImage($request->file('mobile_image'));

        $this->firestore->add('flavors', [
            'name'         => $request->name,
            'flavor_type'  => $request->flavor_type,
            'category'     => $request->category,
            'price'        => $request->price,
            'image'        => $imagePath,
            'mobile_image' => $mobileImagePath,
            'status'       => $request->price > 0 ? 'available' : 'out',
        ]);
        FirestoreCacheKeys::invalidateFlavors();

        return back()->with('success', 'Flavor added successfully');
    }

    public function flavorupdate(Request $request, $id)
    {
        $flavor = $this->firestore->get('flavors', (string) $id);
        if (!$flavor) {
            return back()->with('error', 'Flavor not found.');
        }

        $request->validate([
            'name'         => 'required|string|max:255',
            'flavor_type'  => 'required|string|max:255',
            'category'     => 'required',
            'price'        => 'required|numeric|min:0',
            'image'        => 'nullable|image|max:2048',
            'mobile_image' => 'nullable|image|max:2048',
        ]);

        $imagePath = $this->saveFlavorImage(
            $request->file('image'),
            $flavor['image'] ?? null
        );
        $mobileImagePath = $this->saveFlavorImage(
            $request->file('mobile_image'),
            $flavor['mobile_image'] ?? null
        );

        $this->firestore->update('flavors', (string) $id, [
            'name'         => $request->name,
            'flavor_type'  => $request->flavor_type,
            'category'     => $request->category,
            'price'        => $request->price,
            'image'        => $imagePath,
            'mobile_image' => $mobileImagePath,
            'status'       => $request->price > 0 ? 'available' : 'out',
        ]);
        FirestoreCacheKeys::invalidateFlavors();

        return back()->with('success', 'Flavor updated successfully');
    }

    public function flavordestroy($id)
    {
        $flavor = $this->firestore->get('flavors', (string) $id);
        if (!$flavor) {
            return back()->with('error', 'Flavor not found.');
        }
        $this->firestore->delete('flavors', (string) $id);
        FirestoreCacheKeys::invalidateFlavors();
        return back()->with('success', 'Flavor deleted successfully');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'flavor_ids' => ['required', 'array', 'min:1'],
            'flavor_ids.*' => ['string'],
        ]);

        $ids = collect($validated['flavor_ids'])
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $deletedCount = 0;
        foreach ($ids as $docId) {
            if ($this->firestore->get('flavors', $docId)) {
                $this->firestore->delete('flavors', $docId);
                $deletedCount++;
            }
        }
        FirestoreCacheKeys::invalidateFlavors();

        return back()->with('success', $deletedCount . ' selected item(s) deleted successfully');
    }
}
