<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirestoreService;
use App\Support\FirestoreCacheKeys;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class IngredientController extends Controller
{
    public function __construct(private FirestoreService $firestore)
    {
    }

    private function saveIngredientImage(?UploadedFile $file, ?string $existingPath = null): ?string
    {
        if ($file === null || !$file->isValid()) {
            return $existingPath;
        }
        $dir = public_path('ingredients');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->move($dir, $filename);
        return 'ingredients/' . $filename;
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'type'     => 'required|in:Ingredients,Flavor',
            'quantity' => 'required|numeric|min:0',
            'unit'     => 'required',
            'image'    => 'nullable|image|max:2048',
        ]);

        $imagePath = $this->saveIngredientImage($request->file('image'));

        $this->firestore->add('ingredients', [
            'name'     => $request->name,
            'type'     => $request->type,
            'quantity' => $request->quantity,
            'unit'     => $request->unit,
            'image'    => $imagePath,
            'status'   => $request->quantity > 0 ? 'available' : 'out',
        ]);
        FirestoreCacheKeys::invalidateIngredients();

        return back()->with('success', 'Ingredient added successfully');
    }

    public function update(Request $request, $id)
    {
        $ingredient = $this->firestore->get('ingredients', (string) $id);
        if (!$ingredient) {
            return back()->with('error', 'Ingredient not found.');
        }

        $request->validate([
            'name'     => 'required|string|max:255',
            'type'     => 'required|in:Ingredients,Flavor',
            'quantity' => 'required|numeric|min:0',
            'unit'     => 'required',
            'image'    => 'nullable|image|max:2048',
        ]);

        $imagePath = $this->saveIngredientImage(
            $request->file('image'),
            $ingredient['image'] ?? null
        );

        $this->firestore->update('ingredients', (string) $id, [
            'name'     => $request->name,
            'type'     => $request->type,
            'quantity' => $request->quantity,
            'unit'     => $request->unit,
            'image'    => $imagePath,
            'status'   => $request->quantity > 0 ? 'available' : 'out',
        ]);

        return back()->with('success', 'Ingredient updated successfully');
    }

    public function destroy($id)
    {
        $ingredient = $this->firestore->get('ingredients', (string) $id);
        if (!$ingredient) {
            return back()->with('error', 'Ingredient not found.');
        }
        $this->firestore->delete('ingredients', (string) $id);
        FirestoreCacheKeys::invalidateIngredients();
        return back()->with('success', 'Ingredient deleted successfully');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ingredient_ids' => ['required', 'array', 'min:1'],
            'ingredient_ids.*' => ['string'],
        ]);

        $ids = collect($validated['ingredient_ids'])
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $deletedCount = 0;
        foreach ($ids as $docId) {
            if ($this->firestore->get('ingredients', $docId)) {
                $this->firestore->delete('ingredients', $docId);
                $deletedCount++;
            }
        }
        FirestoreCacheKeys::invalidateIngredients();

        return back()->with('success', $deletedCount . ' selected item(s) deleted successfully');
    }
}
