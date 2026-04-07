# Firestore Setup

This project already includes `kreait/laravel-firebase` and a reusable `App\Services\FirestoreService`.

## 1) Configure `.env`

Add/update these variables:

```env
FIREBASE_CREDENTIALS=storage/app/firebase/your-project-firebase-adminsdk.json
GOOGLE_CLOUD_PROJECT=your-gcp-project-id
```

Notes:
- `FIREBASE_CREDENTIALS` should point to your Firebase service account JSON.
- `GOOGLE_CLOUD_PROJECT` is optional if the service account contains `project_id`.

## 2) Place credentials file

Save your Firebase Admin SDK JSON file in:

`storage/app/firebase/`

Do not commit this file to git.

## 3) Clear config cache

```bash
php artisan config:clear
```

## 4) Verify connection

Run:

```bash
php check-firestore.php
```

If connected, you will see:

`*** SUCCESS: Firestore is connected. ***`

## 5) Use in code

Inject and use:

```php
use App\Services\FirestoreService;

public function __construct(private FirestoreService $firestore) {}
```

Then call helpers like:
- `$this->firestore->add('orders', [...])`
- `$this->firestore->get('orders', $id)`
- `$this->firestore->where('orders', 'status', 'pending')`

## 6) Admin panel: Firestore real-time signals (`admin_signals/broadcast`)

The admin layout can listen to **Firestore** (instead of only Realtime Database) when you add the **Web app** config from Firebase Console → Project settings → Your apps → Web.

Add to `.env`:

```env
FIREBASE_WEB_API_KEY=...
FIREBASE_WEB_AUTH_DOMAIN=your-project.firebaseapp.com
FIREBASE_PROJECT_ID=your-gcp-project-id
FIREBASE_WEB_STORAGE_BUCKET=...
FIREBASE_WEB_MESSAGING_SENDER_ID=...
FIREBASE_WEB_APP_ID=...
```

Laravel merges `admin_signals/broadcast` when notifications, orders, or chat change (see `FirestoreService::mergeAdminBroadcast`).

**Firestore security rules** (dev-friendly example — tighten for production):

```
match /admin_signals/broadcast {
  allow read: if true;
  allow write: if false;
}
```

Only the Admin SDK (your Laravel app) should write this document. Keep `FIREBASE_DATABASE_URL` set if you still use Realtime Database for chat message sync under `chats/`.
