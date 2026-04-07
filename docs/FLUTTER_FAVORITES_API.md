# Favorites API – Flutter integration

Use these endpoints with **Authorization: Bearer {token}** or **X-Session-Token** (same as orders).

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/favorites` | List current user's favorite flavors |
| POST | `/api/v1/favorites` | Toggle favorite: body `{ "flavor_id": 1 }` – adds if not in list, removes if already in list. Response includes `is_favorite: true/false`. |
| GET | `/api/v1/favorites/check?flavor_id=1` | Check if a flavor is in favorites (for UI state) |
| DELETE | `/api/v1/favorites/{flavor_id}` | Remove flavor from favorites |

---

## Flutter changes (MenuPage)

### 1. Include `id` in each item (from API)

In the `items` getter, when building from `_apiFlavors`, add `"id"` so the heart can send `flavor_id`:

```dart
return api.map((e) {
  final id = e["id"]; // from API
  final name = e["name"] as String? ?? "";
  // ... existing fields ...
  return <String, dynamic>{
    "id": id,  // add this (int or null for fallback items)
    "name": name,
    "price": price,
    "image": image.isEmpty ? "lib/client/order/images/sb.png" : image,
    "big_image": image.isEmpty ? "lib/client/order/images/sbB.png" : image,
    "category": category,
    "isNetworkImage": image.isNotEmpty,
  };
}).toList();
```

For `_fallbackItems` you can use `"id": null` or omit; the heart can no-op when `id == null` (or when not logged in).

### 2. State for favorite and check on open

```dart
bool _isFavorite = false;

Future<void> _checkFavorite() async {
  final id = selectedItem?["id"];
  if (id == null || id is! int) {
    setState(() => _isFavorite = false);
    return;
  }
  final token = Auth.sessionToken; // or however you store the login token
  if (token == null || token.isEmpty) {
    setState(() => _isFavorite = false);
    return;
  }
  final base = Auth.apiBaseUrl;
  try {
    final res = await http.get(
      Uri.parse('$base/favorites/check?flavor_id=$id'),
      headers: {'Authorization': 'Bearer $token'},
    );
    if (!mounted) return;
    if (res.statusCode == 200) {
      final body = jsonDecode(res.body) as Map<String, dynamic>?;
      setState(() => _isFavorite = body?['is_favorite'] == true);
    } else {
      setState(() => _isFavorite = false);
    }
  } catch (_) {
    if (mounted) setState(() => _isFavorite = false);
  }
}
```

When you set `selectedItem` (e.g. in grid tap or `_applyInitialSelection`), call `_checkFavorite()` after `setState`.

### 3. Toggle favorite when heart is tapped

```dart
Future<void> _toggleFavorite() async {
  final id = selectedItem?["id"];
  if (id == null || id is! int) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Cannot add this item to favorites.'), behavior: SnackBarBehavior.floating),
    );
    return;
  }
  final token = Auth.sessionToken;
  if (token == null || token.isEmpty) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Please log in to add favorites.'), behavior: SnackBarBehavior.floating),
    );
    return;
  }
  final base = Auth.apiBaseUrl;
  try {
    final res = await http.post(
      Uri.parse('$base/favorites'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $token',
      },
      body: jsonEncode({'flavor_id': id}),
    );
    if (!mounted) return;
    if (res.statusCode == 200) {
      final body = jsonDecode(res.body) as Map<String, dynamic>?;
      final isFav = body?['is_favorite'] == true;
      setState(() => _isFavorite = isFav);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(isFav ? 'Added to favorites.' : 'Removed from favorites.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  } catch (_) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Something went wrong.'), behavior: SnackBarBehavior.floating),
      );
    }
  }
}
```

### 4. Heart icon in detail (buildStrawberryDetail)

Replace the static heart with a tappable one that reflects `_isFavorite`:

```dart
// Favorite icon
Positioned(
  top: 26,
  right: 14,
  child: GestureDetector(
    onTap: _toggleFavorite,
    child: Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.90),
        shape: BoxShape.circle,
      ),
      child: Icon(
        _isFavorite ? Icons.favorite : Icons.favorite_border,
        size: 22,
        color: const Color(0xFFE3001B),
        fill: _isFavorite ? 1 : 0,
      ),
    ),
  ),
),
```

### 5. Call _checkFavorite when opening detail

Where you set `selectedItem` and then open the detail (e.g. in the grid `onTap` and in `_applyInitialSelection`), after `setState` run:

```dart
WidgetsBinding.instance.addPostFrameCallback((_) {
  if (!mounted) return;
  _bigImageController.jumpToPage(0);
  _startBigImageAutoSlide(count: 2);
  _checkFavorite();  // add this
});
```

And when `selectedItem` is set from `_applyInitialSelection`, also call `_checkFavorite()` once the widget is built (e.g. in the same `addPostFrameCallback` where you already call `_applyInitialSelection` in `initState`, or when `selectedItem` gets set there).

---

## Summary

- Backend: migration `favorites` (customer_id, flavor_id), `Favorite` model, `ApiFavoriteController`, routes under `api.customer`.
- Flutter: add `id` to items from API; add `_isFavorite` and `_checkFavorite()` / `_toggleFavorite()`; wire heart icon to `_toggleFavorite` and call `_checkFavorite()` when opening the detail.
