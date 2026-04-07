// ---------------------------------------------------------------------------
// Apply these edits in your Flutter app (ConfirmDeliveryPage & CompleteDeliveryPage).
//
// Problem 1 — Chat/call hidden on ConfirmDeliveryPage:
//   Chat and call were wrapped in `if (_showDeliverNow)`, so they only appeared
//   after "Accept Book". The API allows driver↔customer messages as soon as the
//   order is assigned to this driver (pending or accepted). Show chat/call
//   whenever the shipment is loaded, not only after accept.
//
// Problem 2 — Chat icon:
//   Prefer `Symbols.chat_bubble_rounded` (renders reliably with Material Symbols).
//
// Problem 3 — ChatPage (messages.dart):
//   Use GET/POST `${Auth.apiBaseUrl}/driver/shipments/{orderId}/messages` with
//   the same Firestore order id as `_shipmentId` / `shipment['id']` (string).
//   Response lists messages under JSON key `data` (not `messages`).
//   If API returns 422 "Shipment has no linked customer account", hide send or
//   show a short notice — backend now also returns `customer_id` on shipment.
// ---------------------------------------------------------------------------

// --- ConfirmDeliveryPage: replace the Customer Row (chat/call section) ---

/*
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Customer',
                                ...
                              ),
                              Text(
                                (_shipment?['customer_name'] ?? '—').toString(),
                                ...
                              ),
                            ],
                          ),
                        ),
                        if (!_loading && _error.isEmpty) ...[
                          SizedBox(width: isCompact ? 8 : 10),
                          _ActionIconButton(
                            icon: Symbols.chat_bubble_rounded,
                            onTap: _openChat,
                          ),
                          SizedBox(width: isCompact ? 8 : 10),
                          _ActionIconButton(
                            icon: Symbols.call,
                            onTap: _openCall,
                          ),
                        ],
                      ],
                    ),
*/

// Optional: pass customer_id into ChatPage if your widget supports it:
/*
        ChatPage(
          shipmentId: id,
          relatedShipmentIds: [id],
          customerName: customerName,
          customerPhone: customerPhone,
          // customerId: (_shipment?['customer_id'] ?? '').toString(),
        ),
*/

// --- CompleteDeliveryPage: icon only ---
/*
                        _CompleteActionIconButton(
                          icon: Symbols.chat_bubble_rounded,
                          onTap: _openChatForCustomer,
                        ),
*/
