# Customer Chat API – Flutter app

Use these endpoints with **Authorization: Bearer {token}** or **X-Session-Token** (same as login).  
The customer chats with **admin** (one conversation per customer).

Base URL: `/api/v1` (e.g. `https://your-domain.com/api/v1`).

---

## Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/chat` | Chat summary (last message, unread count) |
| GET | `/api/v1/chat/messages` | List messages with admin (paginated) |
| POST | `/api/v1/chat/messages` | Send a text or image message to admin |
| POST | `/api/v1/chat/read` | Mark admin messages as read |

---

## 1. Chat summary (badge / last message)

**GET** `/api/v1/chat`

**Headers:** `Authorization: Bearer {token}` or `X-Session-Token: {token}`

**Response 200:**

```json
{
  "success": true,
  "data": {
    "last_message": {
      "id": 5,
      "sender_type": "admin",
      "body": "Your order will arrive by 3 PM.",
      "image_url": null,
      "created_at": "2026-02-06T14:30:00.000000Z"
    },
    "unread_count": 2
  }
}
```

If there are no messages, `last_message` is `null` and `unread_count` is `0`.

**Flutter:** Use `unread_count` for the chat badge; use `last_message` for a preview in the chat list.

---

## 2. List messages (conversation with admin)

**GET** `/api/v1/chat/messages`

**Headers:** `Authorization: Bearer {token}` or `X-Session-Token: {token}`

**Query (optional):**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 50 | Items per page (max 100) |

**Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "sender_type": "customer",
      "body": "Hi, when is my order arriving?",
      "image_url": null,
      "created_at": "2026-02-06T10:00:00.000000Z",
      "read_at": null
    },
    {
      "id": 2,
      "sender_type": "admin",
      "body": "Your order will arrive by 3 PM.",
      "image_url": null,
      "created_at": "2026-02-06T10:05:00.000000Z",
      "read_at": "2026-02-06T10:06:00.000000Z"
    },
    {
      "id": 3,
      "sender_type": "admin",
      "body": null,
      "image_url": "https://your-domain.com/storage/chat/xyz.jpg",
      "created_at": "2026-02-06T10:10:00.000000Z",
      "read_at": null
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total": 3
  }
}
```

**sender_type:** `"admin"` or `"customer"`.  
**body:** Text message; can be `null` if the message is image-only.  
**image_url:** Full URL to image; `null` if text-only.

---

## 3. Send message (text or image)

**POST** `/api/v1/chat/messages`

**Headers:**  
- `Authorization: Bearer {token}` or `X-Session-Token: {token}`  
- `Content-Type: multipart/form-data` (when sending image) or `application/x-www-form-urlencoded` (text only)

**Body (form):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `body` | string | No* | Message text |
| `image` | file | No* | Image file (e.g. image/jpeg, image/png) |

*At least one of `body` or `image` is required.

**Example (text):**  
`body=Hello, I have a question about my order.`

**Example (image):**  
`body=` (optional) and `image=<file>`

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 6,
    "sender_type": "customer",
    "body": "Hello, I have a question.",
    "image_url": null,
    "created_at": "2026-02-06T15:00:00.000000Z",
    "read_at": null
  }
}
```

**Response 422 (validation):**

```json
{
  "success": false,
  "message": "Provide a message (body) or an image."
}
```

**Flutter:** Use `multipart/form-data` when attaching an image; add the session token to the request header.

---

## 4. Mark messages as read

**POST** `/api/v1/chat/read`

**Headers:** `Authorization: Bearer {token}` or `X-Session-Token: {token}`

Marks all **admin** messages in this conversation as read (for the current customer).

**Response 200:**

```json
{
  "success": true,
  "message": "Marked as read."
}
```

**Flutter:** Call this when the user opens the chat screen so the unread badge updates.

---

## Summary

- **Chat is one thread per customer** with admin (no multiple conversations).
- **sender_type:** `admin` = message from admin (dashboard), `customer` = message from the logged-in customer.
- **Images** are stored on the server; response includes full `image_url`.
- Use **GET /api/v1/chat** for badge and last message; **GET /api/v1/chat/messages** for the full thread; **POST /api/v1/chat/messages** to send; **POST /api/v1/chat/read** when opening the chat.
