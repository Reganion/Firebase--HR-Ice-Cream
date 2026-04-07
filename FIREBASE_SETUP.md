# Firebase Realtime Database Setup

Your app uses **Firebase Realtime Database** (not Firestore) at:

**https://icecream-14ae7-default-rtdb.firebaseio.com/**

## 1. Configure Laravel

In your `.env` file, set:

```env
# Realtime Database URL (from Firebase Console > Realtime Database)
FIREBASE_DATABASE_URL=https://icecream-14ae7-default-rtdb.firebaseio.com

# Service Account JSON path (from Firebase Console > Project settings > Service accounts > Generate new private key)
FIREBASE_CREDENTIALS=storage/app/firebase/icecream-14ae7-firebase-adminsdk-fbsvc-430ee15fce.json
```

- **FIREBASE_DATABASE_URL** – Your Realtime Database URL. You already have: `https://icecream-14ae7-default-rtdb.firebaseio.com`
- **FIREBASE_CREDENTIALS** – Path to the Service Account JSON file (relative to project root or absolute). Place the downloaded JSON in `storage/app/firebase/` and set this variable to that path.

## 2. Get Service Account credentials (if you haven’t yet)

1. Go to [Firebase Console](https://console.firebase.google.com/) → your project **icecream-14ae7**.
2. Click the **gear icon** → **Project settings** → **Service accounts**.
3. Click **Generate new private key** and download the JSON file.
4. Save it as e.g. `storage/app/firebase/icecream-14ae7-firebase-adminsdk-fbsvc-430ee15fce.json`.
5. Set `FIREBASE_CREDENTIALS` in `.env` to that path (as above).

**Security:** Do not commit the JSON file to Git. Add `storage/app/firebase/*.json` to `.gitignore` if needed.

## 3. Realtime Database rules (optional)

In Firebase Console → **Realtime Database** → **Rules**, you can restrict read/write. For development you might use:

```json
{
  "rules": {
    ".read": "auth != null",
    ".write": "auth != null"
  }
}
```

With the **Admin SDK** (your Laravel app uses the Service Account), the server can read/write regardless of these rules when using the SDK. Rules mainly affect client-side access.

## 4. How the app uses the database

The app uses the `FirebaseRealtimeService` (backed by `firebase.database`) to read/write data. Collections in the Realtime Database are stored as keys under the root, for example:

- `customers` – customer accounts  
- `users` – admin users  
- `flavors` – ice cream flavors  
- `orders` – orders  
- `drivers` – drivers  
- `gallons` – gallon sizes  
- `ingredients` – ingredients  
- `feedback` – testimonials  

You do **not** need to create these keys manually. They are created when the app writes data (e.g. when a customer registers or you add a flavor in admin).

## 5. After changing .env

Run:

```bash
php artisan config:clear
```

Then use the app as usual; it will use your Realtime Database at `https://icecream-14ae7-default-rtdb.firebaseio.com/`.
