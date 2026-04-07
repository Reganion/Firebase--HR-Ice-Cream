# Notifications API – Flutter customer app

Use these endpoints with **Authorization: Bearer {token}** or **X-Session-Token** (same as login).

Base URL: `/api/v1` (e.g. `https://your-domain.com/api/v1`).

---

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/notifications` | List notifications (paginated) |
| GET | `/api/v1/notifications/unread-count` | Get unread count only (for badge) |
| POST | `/api/v1/notifications/{id}/read` | Mark one notification as read |
| POST | `/api/v1/notifications/read-all` | Mark all as read |

---

## 1. List notifications

**GET** `/api/v1/notifications`

**Headers:** `Authorization: Bearer {token}` or `X-Session-Token: {token}`

**Query (optional):**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 20 | Items per page (max 50) |
| `unread_only` | 0/1 | 0 | If 1, return only unread |

**Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "customer_id": 5,
      "type": "order_placed",
      "title": "Strawberry",
      "message": "Your order has been placed successfully.",
      "image_url": "img/flavors/strawberry.png",
      "related_type": "Order",
      "related_id": 12,
      "read_at": null,
      "data": { "transaction_id": "ABC123XYZ" },
      "created_at": "2026-02-05T10:00:00.000000Z",
      "updated_at": "2026-02-05T10:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  },
  "unread_count": 1
}
```

**Notification types:** `order_placed`, `order_delivered`, `order_status`, `promo`.

**Flutter:** Use `data` for the list; use `unread_count` for the badge. `read_at` is `null` when unread.

---

## 2. Unread count (badge)

**GET** `/api/v1/notifications/unread-count`

**Response 200:**

```json
{
  "success": true,
  "unread_count": 3
}
```

Call this when opening the app or the notifications screen to show a badge.

---

## 3. Mark one as read

**POST** `/api/v1/notifications/{id}/read`

**Headers:** `Authorization: Bearer {token}`, `Content-Type: application/json`

**Body:** `{}` (empty JSON object is fine)

**Response 200:**

```json
{
  "success": true,
  "message": "Marked as read."
}
```

**Response 404:** Notification not found or not owned by this customer.

---

## 4. Mark all as read

**POST** `/api/v1/notifications/read-all`

**Headers:** `Authorization: Bearer {token}`, `Content-Type: application/json`

**Body:** `{}`

**Response 200:**

```json
{
  "success": true,
  "message": "All marked as read."
}
```

---

## Flutter usage example

### Get token

Use the same session token you get from `/api/v1/login` or `/api/v1/verify-otp` and store it (e.g. `Auth.sessionToken` or shared preferences).

### List notifications

```dart
final baseUrl = 'https://your-domain.com/api/v1';
final token = Auth.sessionToken; // your stored token

final res = await http.get(
  Uri.parse('$baseUrl/notifications?per_page=20&page=1'),
  headers: {
    'Authorization': 'Bearer $token',
    'Accept': 'application/json',
  },
);
if (res.statusCode == 200) {
  final json = jsonDecode(res.body) as Map<String, dynamic>;
  final list = json['data'] as List<dynamic>? ?? [];
  final unreadCount = json['unread_count'] as int? ?? 0;
  // Update badge and list
}
```

### Unread count for badge

```dart
final res = await http.get(
  Uri.parse('$baseUrl/notifications/unread-count'),
  headers: {'Authorization': 'Bearer $token'},
);
if (res.statusCode == 200) {
  final json = jsonDecode(res.body) as Map<String, dynamic>;
  final count = json['unread_count'] as int? ?? 0;
  setState(() => _unreadCount = count);
}
```

### Mark as read when user taps a notification

```dart
Future<void> markAsRead(int id) async {
  final res = await http.post(
    Uri.parse('$baseUrl/notifications/$id/read'),
    headers: {
      'Authorization': 'Bearer $token',
      'Content-Type': 'application/json',
    },
    body: '{}',
  );
  if (res.statusCode == 200) {
    // Update local list: set read_at for this id, decrement badge
  }
}
```

### Mark all as read

```dart
final res = await http.post(
  Uri.parse('$baseUrl/notifications/read-all'),
  headers: {
    'Authorization': 'Bearer $token',
    'Content-Type': 'application/json',
  },
  body: '{}',
);
```

---

## When notifications are created

- **order_placed** – When the customer places an order via `POST /api/v1/orders` (authenticated).
- **order_delivered** / **order_status** – Can be created from the admin panel or backend when order status changes (you can hook into your order update logic and create `CustomerNotification` for the customer).

Example backend code to create a customer notification:

```php
use App\Support\CustomerNotification;

CustomerNotification::create([
    'customer_id'  => $customerId,
    'type'         => CustomerNotification::TYPE_ORDER_DELIVERED,
    'title'        => $order->product_name,
    'message'      => 'Your order has been delivered.',
    'related_type' => 'Order',
    'related_id'   => $order->id,
]);
```
