# Address API – Flutter integration

Use these with **Authorization: Bearer {token}** or **X-Session-Token** (same as other protected routes).

---

## Customer addresses (separate table, multiple addresses per customer)

The **customer_addresses** table is linked to **customers** by `customer_id`. Each customer can have multiple saved addresses.

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/v1/addresses` | List all addresses for the logged-in customer |
| POST | `/api/v1/addresses` | Add a new address |
| GET | `/api/v1/addresses/{id}` | Get one address (must belong to you) |
| PUT / PATCH | `/api/v1/addresses/{id}` | Update an address |
| DELETE | `/api/v1/addresses/{id}` | Delete an address |
| POST | `/api/v1/addresses/{id}/default` | Set this address as the default |

### POST /api/v1/addresses – body (all optional except at least one field)

| Field | Type | Description |
|-------|------|-------------|
| `firstname` | string | Receiver first name (max 50) |
| `lastname` | string | Receiver last name (max 50) |
| `contact_no` | string | Contact number (max 20) |
| `province` | string | Province (max 100) |
| `city` | string | City (max 100) |
| `barangay` | string | Barangay (max 100) |
| `postal_code` | string | Postal code (max 20) |
| `street_name` | string | Street, building, house no. (max 255) |
| `label_as` | string | e.g. "Home", "Work", "Other" (max 50) |
| `reason` | string | Optional note (max 500) |
| `is_default` | boolean | Set as default (first address is default if not sent) |

### Response shape for one address

```json
{
  "id": 1,
  "customer_id": 1,
  "firstname": "Kyle",
  "lastname": "Antiniero",
  "contact_no": "9702117640",
  "province": "Cebu",
  "city": "Lapu-Lapu",
  "barangay": "Pajac",
  "postal_code": "6015",
  "street_name": "Bebe Garingo Apt., Fuentes Road Pajac",
  "label_as": "Home",
  "reason": null,
  "is_default": true,
  "full_address": "Bebe Garingo Apt., Fuentes Road Pajac, Pajac, Lapu-Lapu City, Cebu, 6015",
  "created_at": "2026-02-04T12:00:00.000000Z",
  "updated_at": "2026-02-04T12:00:00.000000Z"
}
```

### Flutter: load list in AddressDetailsPage

```dart
// GET /api/v1/addresses
final response = await http.get(
  Uri.parse('${Auth.apiBaseUrl}/addresses'),
  headers: {'Authorization': 'Bearer $token'},
);
// response body: { "success": true, "data": { "addresses": [...], "count": n } }
```

### Flutter: add address from AddressFormPage

```dart
// POST /api/v1/addresses
await http.post(
  Uri.parse('${Auth.apiBaseUrl}/addresses'),
  headers: {
    'Authorization': 'Bearer $token',
    'Content-Type': 'application/json',
  },
  body: jsonEncode({
    'firstname': firstNameController.text.trim(),
    'lastname': lastNameController.text.trim(),
    'contact_no': contactController.text.trim(),
    'province': selectedProvince,
    'city': selectedCity,
    'barangay': selectedBarangay,
    'postal_code': postalCode,
    'street_name': streetController.text.trim(),
    'label_as': labels[selectedLabelIndex],
    'is_default': true, // or false
  }),
);
```

### Flutter: set default address

```dart
// POST /api/v1/addresses/{id}/default
await http.post(
  Uri.parse('${Auth.apiBaseUrl}/addresses/$addressId/default'),
  headers: {'Authorization': 'Bearer $token'},
);
```

---

## Single-address update (on customer record)

| Method | URL | Description |
|--------|-----|-------------|
| PUT or POST | `/api/v1/address` | Update the logged-in customer’s address. All fields are optional; send only what you want to update. |

### Request body (JSON)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `province` | string | No | Province (max 100) |
| `city` | string | No | City (max 100) |
| `barangay` | string | No | Barangay (max 100) |
| `postal_code` | string | No | Postal code (max 20) |
| `street_name` | string | No | Street, building, house no. (max 255) |
| `label_as` | string | No | e.g. "Home", "Work", "Other" (max 50) |
| `reason` | string | No | Optional note (max 500) |

At least one field must be sent.

### Example

```json
POST /api/v1/address
Authorization: Bearer {token}
Content-Type: application/json

{
  "province": "Cebu",
  "city": "Mandaue",
  "barangay": "Maguikay",
  "postal_code": "6014",
  "street_name": "Briones st., ACLC College of Mandaue",
  "label_as": "Home"
}
```

### Success response (200)

```json
{
  "success": true,
  "message": "Address updated successfully.",
  "customer": {
    "id": 1,
    "firstname": "...",
    "lastname": "...",
    "email": "...",
    "contact_no": "...",
    "province": "Cebu",
    "city": "Mandaue",
    "barangay": "Maguikay",
    "postal_code": "6014",
    "street_name": "Briones st., ACLC College of Mandaue",
    "label_as": "Home",
    "reason": null,
    "full_address": "Briones st., ACLC College of Mandaue, Maguikay, Mandaue City, Cebu, 6014",
    "image": "...",
    "image_url": "...",
    "status": "active"
  }
}
```

## Getting address

Address fields are included in:

- **GET /api/v1/account** – `account` object
- **GET /api/v1/profile** – `customer` object
- **GET /api/v1/me** – `customer` object

So after login or when you call `Auth().fetchAccount()`, the response already contains `province`, `city`, `barangay`, `postal_code`, `street_name`, `label_as`, `reason`, and `full_address` (computed). Use these to pre-fill the Address Details / Address Form in Flutter.

## Flutter: calling the API from AddressFormPage

When saving from `AddressFormPage` or `ManageAddressPage`, call:

1. **POST /api/v1/address** with body (snake_case to match API):

```dart
// Example using your Auth base URL
final token = await Auth.getToken();
final response = await http.post(
  Uri.parse('${Auth.apiBaseUrl}/address'),
  headers: {
    'Authorization': 'Bearer $token',
    'Content-Type': 'application/json',
  },
  body: jsonEncode({
    'province': selectedProvince,
    'city': selectedCity,
    'barangay': selectedBarangay,
    'postal_code': postalCode,
    'street_name': streetController.text.trim(),
    'label_as': labels[selectedLabelIndex], // e.g. "Home", "Work", "Other"
  }),
);
```

2. On success, you can either keep the local list and append the new address (as you do now) or refetch account so the single saved address is in sync with the server. The API stores **one** address per customer; multiple “addresses” in the app can be kept locally or you can switch the UI to show only the one from the server.
