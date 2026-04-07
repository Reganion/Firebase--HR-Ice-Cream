# Driver Notifications API

Base URL: `/api/v1/driver`

Headers:
- `Authorization: Bearer {driver_token}`
- `Accept: application/json`

## Endpoints

- `GET /notifications`
- `GET /notifications/unread-count`
- `POST /notifications/{id}/read`
- `POST /notifications/read-all`
- `DELETE /notifications/{id}`
- `DELETE /notifications`

## Response shape (list)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "driver_id": 2,
      "type": "shipment_assigned",
      "title": "New order is available!",
      "message": "Admin just assigned you. Click to see full details.",
      "image_url": "img/default-product.png",
      "related_type": "Order",
      "related_id": 55,
      "read_at": null,
      "data": {
        "transaction_id": "ABCD1234",
        "status": "assigned"
      },
      "created_at": "2026-03-17T12:30:00.000000Z",
      "updated_at": "2026-03-17T12:30:00.000000Z"
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

## Auto-created notifications

- When admin assigns an order to a driver (`AdminOrderController@assignDriver`)
  - Title: `New order is available!`
  - Message: `Admin just assigned you. Click to see full details.`
- When driver completes a shipment (`ApiDriverShipmentController@complete`)
  - Title: `Delivered Successfully`
  - Message: `Booking has been delivered completely.`

