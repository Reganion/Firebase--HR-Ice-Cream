// ---------------------------------------------------------------------------
// Copy into your Flutter app (e.g. lib/driver/delivery/shipment_api.dart).
// Import Auth in your app and pass Auth.apiBaseUrl into the Uri helpers.
//
// Firestore order IDs are strings. Do NOT use int? / int.tryParse for shipment
// IDs in URLs — non-numeric IDs become null and break /driver/shipments/{id}.
// ---------------------------------------------------------------------------

/// Resolves Firestore order document id from widget arg or loaded JSON map.
String? resolveDriverShipmentId({
  String? shipmentId,
  Map<String, dynamic>? shipment,
}) {
  final w = shipmentId?.trim();
  if (w != null && w.isNotEmpty) return w;
  final id = shipment?['id'];
  if (id == null) return null;
  final s = id.toString().trim();
  return s.isEmpty ? null : s;
}

String _trimBase(String apiBaseUrl) => apiBaseUrl.replaceAll(RegExp(r'/+$'), '');

/// Web origin for building proof image URLs (strip `/api/v1` from [Auth.apiBaseUrl]).
/// Example: `http://10.0.2.2/hr-ice-cream/public/api/v1` → `http://10.0.2.2/hr-ice-cream/public`
String driverShipmentApiOrigin(String apiBaseUrl) {
  var s = apiBaseUrl.trim().replaceAll(RegExp(r'/+$'), '');
  if (s.toLowerCase().endsWith('/api/v1')) {
    s = s.substring(0, s.length - '/api/v1'.length);
  }
  return s.replaceAll(RegExp(r'/+$'), '');
}

/// GET …/driver/shipments/{id}
Uri driverShipmentGetUri(String apiBaseUrl, String shipmentId) {
  final id = Uri.encodeComponent(shipmentId);
  return Uri.parse('${_trimBase(apiBaseUrl)}/driver/shipments/$id');
}

/// POST …/driver/shipments/{id}/{action}
/// [action] examples: accept, reject, deliver, complete
Uri driverShipmentActionUri(String apiBaseUrl, String shipmentId, String action) {
  final id = Uri.encodeComponent(shipmentId);
  final a = Uri.encodeComponent(action);
  return Uri.parse('${_trimBase(apiBaseUrl)}/driver/shipments/$id/$a');
}

/// GET …/driver/shipments/{id}/messages — list thread (JSON `data` array).
Uri driverShipmentMessagesUri(String apiBaseUrl, String shipmentId) {
  final id = Uri.encodeComponent(shipmentId);
  return Uri.parse('${_trimBase(apiBaseUrl)}/driver/shipments/$id/messages');
}

/// POST …/driver/shipments/{id}/messages — body: `{ "message": "..." }`.
Uri driverShipmentMessagesPostUri(String apiBaseUrl, String shipmentId) {
  return driverShipmentMessagesUri(apiBaseUrl, shipmentId);
}

/// POST …/driver/shipments/{id}/messages/read
Uri driverShipmentMessagesReadUri(String apiBaseUrl, String shipmentId) {
  final id = Uri.encodeComponent(shipmentId);
  return Uri.parse('${_trimBase(apiBaseUrl)}/driver/shipments/$id/messages/read');
}

/// POST …/geo/customer/location — customer app: `{ "order_id", "lat", "lng" }` (Bearer customer token).
Uri customerOrderLocationPostUri(String apiBaseUrl) {
  return Uri.parse('${_trimBase(apiBaseUrl)}/geo/customer/location');
}
