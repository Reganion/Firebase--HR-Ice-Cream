<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirestoreService;
use App\Support\FirestoreCacheKeys;
use Illuminate\Http\Request;

class GallonController extends Controller
{
    public function __construct(private FirestoreService $firestore)
    {
    }

    public function gallonstore(Request $request)
    {
        $data = $request->validate([
            'size' => 'required|string',
            'quantity' => 'required|integer',
            'addon_price' => 'required|numeric',
            'image' => 'nullable|image',
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('gallons'), $filename);
            $data['image'] = 'gallons/' . $filename;
        }

        $data['status'] = $data['quantity'] > 0 ? 'available' : 'out';

        $this->firestore->add('gallons', $data);
        FirestoreCacheKeys::invalidateGallons();

        return back()->with('success', 'Gallon added successfully');
    }

    public function gallonupdate(Request $request, $id)
    {
        $gallon = $this->firestore->get('gallons', (string) $id);
        if (!$gallon) {
            return back()->with('error', 'Gallon not found.');
        }

        $data = $request->validate([
            'size'        => 'required|string',
            'quantity'    => 'required|integer',
            'addon_price' => 'required|numeric',
            'image'       => 'nullable|image',
        ]);

        if ($request->hasFile('image')) {
            if (!empty($gallon['image']) && file_exists(public_path((string) $gallon['image']))) {
                @unlink(public_path((string) $gallon['image']));
            }
            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('gallons'), $filename);
            $data['image'] = 'gallons/' . $filename;
        } else {
            $data['image'] = $gallon['image'] ?? null;
        }

        $data['status'] = $data['quantity'] > 0 ? 'available' : 'out';

        $this->firestore->update('gallons', (string) $id, $data);
        FirestoreCacheKeys::invalidateGallons();

        return back()->with('success', 'Gallon updated successfully');
    }

    public function gallondestroy($id)
    {
        $gallon = $this->firestore->get('gallons', (string) $id);
        if (!$gallon) {
            return back()->with('error', 'Gallon not found.');
        }
        $this->firestore->delete('gallons', (string) $id);
        FirestoreCacheKeys::invalidateGallons();
        return back()->with('success', 'Gallon deleted successfully');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'gallon_ids' => ['required', 'array', 'min:1'],
            'gallon_ids.*' => ['string'],
        ]);

        $ids = collect($validated['gallon_ids'])
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $deletedCount = 0;
        foreach ($ids as $docId) {
            if ($this->firestore->get('gallons', $docId)) {
                $this->firestore->delete('gallons', $docId);
                $deletedCount++;
            }
        }
        FirestoreCacheKeys::invalidateGallons();

        return back()->with('success', $deletedCount . ' selected item(s) deleted successfully');
    }
}
