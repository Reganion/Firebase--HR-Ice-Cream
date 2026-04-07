# Firebase Real-Time Chat & Notifications

The Laravel backend **dual-writes** chat messages and customer notifications to **Firebase Realtime Database**. Your Flutter (or other) app can listen to these paths for instant updates without polling the REST API.

Use the same Firebase project and Realtime Database URL as in `FIREBASE_SETUP.md`. Configure the Firebase client SDK in your app (e.g. `firebase_database` in Flutter) with your Realtime Database URL.

---

## Chat (real time)

- **Path:** `chats/{customerId}/messages`
- Each message is stored at `chats/{customerId}/messages/{messageId}` where `messageId` is the MySQL message ID.
- **Payload per message:** `id`, `sender_type` (`"admin"` | `"customer"`), `body`, `image_url`, `created_at` (ISO 8601), `read_at` (ISO 8601 or null).

**Flutter (example):** Listen for new messages and read-status changes:

- Use `FirebaseDatabase.instance.ref('chats/$customerId/messages')`.
- `onChildAdded`: new message.
- `onChildChanged`: e.g. `read_at` updated (admin/customer read the message).

You can still use the REST API (`GET /api/v1/chat/messages`, `POST /api/v1/chat/messages`, `POST /api/v1/chat/read`) for sending and for initial load; Firebase gives you real-time updates on top.

---

## Notifications (real time)

- **Path (items):** `notifications/{customerId}/items`
- Each notification is at `notifications/{customerId}/items/{notificationId}` (MySQL notification ID).
- **Payload per notification:** `id`, `customer_id`, `type`, `title`, `message`, `image_url`, `related_type`, `related_id`, `read_at`, `data`, `created_at`.
- **Path (trigger):** `notifications/{customerId}/last_updated` — an object with `value` (ISO 8601 timestamp). Updated whenever a notification is added or marked read so you can listen to a single path for “something changed”.

**Flutter (example):**

- Listen to `notifications/$customerId/items` with `onChildAdded` for new notifications.
- Listen to `notifications/$customerId/last_updated` for any change (new or read); then refresh list or unread count (e.g. from REST `GET /api/v1/notifications` or use the Firebase payloads).

---

## Summary

| Data           | Firebase path                               | Use |
|----------------|---------------------------------------------|-----|
| Chat messages  | `chats/{customerId}/messages`               | Real-time new messages and read receipts. |
| Notifications  | `notifications/{customerId}/items`          | Real-time new notifications and read status. |
| Notifications  | `notifications/{customerId}/last_updated`    | Simple “changed” trigger for badge/refresh. |

REST APIs for chat and notifications are unchanged; Firebase adds real-time sync alongside them.

---

## Admin notifications (real time)

- **Path (trigger):** `admin/notifications_last_updated`  
  When any admin notification is created (new order, delivery success, profile update, etc.), the backend updates this. The admin layout listens here and refetches the notification list so the badge and dropdown update in real time.
- **Path (new order alert):** `admin/latest_order_alert`  
  When the notification type is "new order", the backend also writes a payload here (`title`, `subtitle`, `highlight`, `order_id`, `image_url`). The layout listens and shows a **toast** ("New order! …") and flashes the browser tab title so the admin sees new orders immediately.

---

## Admin orders dashboard (real time)

- **Path:** `admin/orders_last_updated`
- The Laravel backend updates this path (timestamp) whenever an order is created, updated, or a driver is assigned. The admin orders page listens here and refetches the order list from the API when the value changes, so the table updates in real time without polling.
- **Firebase Realtime Database rules:** To allow the admin dashboard (browser) to read this path, you can add rules in Firebase Console → Realtime Database → Rules, for example:

```json
"admin": {
  "orders_last_updated": {
    ".read": true,
    ".write": false
  }
}
```

(Only your Laravel server writes via the Admin SDK; the front end only reads this trigger.)

---

## Troubleshooting: “I have to refresh to see new orders/notifications”

- **Real-time only (no polling):** The admin panel uses **only** Firebase Realtime Database for live updates. There is no polling, so you must have Firebase configured for new orders and notifications to appear without a page refresh.
- **Required for real-time:**  
  1. Add to `.env`: `FIREBASE_DATABASE_URL=https://icecream-14ae7-default-rtdb.firebaseio.com` (or your project URL). The app reads this via `config('services.firebase_realtime_url')`. Run `php artisan config:clear` after changing `.env`.  
  2. In Firebase Console → Realtime Database → Rules, allow read on `admin/orders_last_updated`, `admin/notifications_last_updated`, `admin/latest_order_alert`, and `admin/chat_last_updated`.

---

## Why real-time might not work (checklist)

Real-time needs **both** the **browser** to receive Firebase updates and the **server** to write to Firebase. If either side fails, you won’t see live updates.

### 1. Firebase URL not set in the browser

- **Symptom:** The admin page never subscribes to Firebase.
- **Cause:** `window.FIREBASE_DATABASE_URL` is empty (from `config('services.firebase_realtime_url')` or `config('firebase.projects.app.database.url')`).
- **Fix:** Add to `.env`: `FIREBASE_DATABASE_URL=https://icecream-14ae7-default-rtdb.firebaseio.com`. Run `php artisan config:clear`, then hard-refresh the admin page (Ctrl+F5).
- **Check:** In the browser console: `console.log(window.FIREBASE_DATABASE_URL)`. It should be your URL, not `""` or `null`.

### 2. Firebase Realtime Database rules block read

- **Symptom:** URL is set but the client never gets data (or permission errors in console).
- **Cause:** Realtime Database rules don’t allow read on the `admin/*` paths.
- **Fix:** Firebase Console → Realtime Database → Rules. Allow read for `admin`, e.g. `"admin": { ".read": true, ".write": false }`.
- **Check:** Network tab for requests to `firebaseio.com`; 401/403 means rules are blocking.

### 3. Backend not writing to Firebase

- **Symptom:** Browser is subscribed and rules are open, but new orders/notifications don’t trigger updates.
- **Cause:** Laravel isn’t writing. The backend uses the **Kreait Admin SDK**, which needs:
  - **Realtime Database URL** (same as frontend), and
  - **Service account credentials** (JSON key file). Without valid credentials, writes can throw and be caught, so nothing appears in Firebase.
- **Fix:** Publish the package config (`php artisan vendor:publish --tag=firebase-config` or similar), set the database URL and the path to your service account JSON. Ensure the JSON file exists and is readable.
- **Check:** Run `php check-firebase.php` from the project root. It should print the URL and “SUCCESS”. If it fails, check `storage/logs/laravel.log` for Firebase/Kreait exceptions when you create an order or notification.

### 4. Backend and frontend use different database URLs

- **Symptom:** `check-firebase.php` succeeds but the admin panel still doesn’t update.
- **Cause:** Backend and frontend point at different Realtime Database URLs.
- **Fix:** Use the **exact same** URL in Laravel config (for Kreait) and in `.env` as `FIREBASE_DATABASE_URL`. Run `php artisan config:clear`.

### 5. JavaScript error or listener not attached

- **Symptom:** URL set, rules open, backend writes, but UI doesn’t update.
- **Cause:** JS error before the Firebase listener runs, or listener on wrong path.
- **Check:** Browser console (F12) for errors. Confirm the listener runs (e.g. log inside the `on('value')` callback when you trigger an order from another tab).

**Quick browser check:** In the admin page console run:

```js
console.log('URL:', window.FIREBASE_DATABASE_URL);
console.log('Firebase:', typeof firebase !== 'undefined' ? 'loaded' : 'NOT loaded');
console.log('Database:', typeof firebase !== 'undefined' && firebase.database ? 'yes' : 'no');
```

- Empty **URL** → fix `.env` and config (§1).
- **Firebase** or **Database** missing → SDK not loaded; check layout/network.
- All three OK but no updates → likely rules (§2) or backend not writing (§3–4).

---

## Admin chat panel (real time)

- **Path (trigger):** `admin/chat_last_updated`  
  The backend updates this when a **customer** sends a message (API chat). The admin layout listens here and refreshes the unread summary (badge and chat heads) without polling.
- **Path (per-conversation):** `chats/{customerId}/messages`  
  When the admin has a conversation open, the layout listens to this path. New messages (from customer or admin) appear via `child_added` so the thread updates in real time.
- **Firebase rules:** Allow read on these paths for the admin dashboard, e.g.:

```json
"admin": {
  "orders_last_updated": { ".read": true, ".write": false },
  "chat_last_updated": { ".read": true, ".write": false },
  "notifications_last_updated": { ".read": true, ".write": false },
  "latest_order_alert": { ".read": true, ".write": false }
},
"chats": {
  ".read": true,
  ".write": false
}
```

(Only your Laravel server writes; the browser only reads.)

---

## Order messages (driver ↔ customer per order)

- **Path:** `order_messages/{orderId}/messages/{messageId}`  
  When a driver or customer sends a message via the API (`POST .../messages`), it is synced here. When either marks messages as read, `read_at` is updated in Firebase so the other side sees read receipts in real time.
- **Payload per message:** `id`, `order_id`, `driver_id`, `customer_id`, `sender_type`, `message`, `is_mine`, `created_at`, `read_at`, and optionally `customer_status`.
- **Flutter / app:** Listen to `order_messages/{orderId}/messages` with `onChildAdded` for new messages and `onChildChanged` for `read_at` updates. Use the same `orderId` as the current shipment/order.
