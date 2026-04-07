# QrphPage: Auto-detect payment and redirect to home if paid

After the user pays via GCash, the app **automatically** detects success and **redirects to the home page** — no need to press "I have paid, check status".

- **Backend**: Unchanged. When the app calls `GET /orders/downpayment/status/{invoice}`, the backend checks PayMongo; if payment succeeded it creates the Order, marks the invoice paid, and returns `invoice_status: 'paid'`.
- **Flutter**: Poll the status endpoint every few seconds. When the response is `paid`, stop polling and **redirect to Home** (with or without a short success dialog).

---

## 1. Add import

At the top of the file with other imports:

```dart
import 'dart:async';
```

---

## 2. Add state and timer in _QrphPageState

Add these fields next to your other state (e.g. after `_cancelling`):

```dart
  bool _checkingStatus = false;
  bool _cancelling = false;
  Timer? _pollTimer;
  bool _alreadyHandledPaid = false;
```

---

## 3. Start polling in initState and cancel in dispose

Add `initState` and `dispose` (if you don’t have them yet):

```dart
  @override
  void initState() {
    super.initState();
    _startPolling();
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _pollTimer = null;
    super.dispose();
  }

  void _startPolling() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 4), (_) => _pollStatus());
  }

  void _stopPolling() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }
```

---

## 4. Add silent poll method (no “pending” snackbar)

This method is used by the timer. It only shows UI when paid or failed; for “pending” it does nothing so the user isn’t spammed.

```dart
  Future<void> _pollStatus() async {
    if (_alreadyHandledPaid || !mounted) return;
    final token = await Auth.getToken();
    if (token == null || token.isEmpty) return;
    final base = Auth.apiBaseUrl;
    try {
      final res = await http.get(
        Uri.parse('$base/orders/downpayment/status/${widget.invoiceId}'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );
      if (!mounted || _alreadyHandledPaid) return;
      if (res.statusCode != 200) return;
      final body = jsonDecode(res.body) as Map<String, dynamic>;
      final data = (body['data'] as Map?) ?? {};
      final invoiceStatus = (data['invoice_status'] ?? '').toString();
      final orderStatus = (data['order_status'] ?? '').toString();
      final paymentStatus = (data['payment_status'] ?? '').toString();

      if (invoiceStatus == 'paid') {
        _alreadyHandledPaid = true;
        _stopPolling();
        if (!mounted) return;
        // Redirect to home page when paid (clear stack so user can't go back to QR)
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (_) => const HomePage()),
          (route) => false,
        );
        // Optional: show a quick success message on home
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Downpayment received. Your order is pending confirmation.'),
              behavior: SnackBarBehavior.floating,
            ),
          );
        }
      } else if (invoiceStatus == 'failed' || orderStatus == 'cancelled' || paymentStatus == 'failed') {
        _stopPolling();
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Downpayment failed or was cancelled. Please try again.'),
            behavior: SnackBarBehavior.floating,
          ),
        );
        Navigator.pop(context);
      }
      // If still pending, do nothing; next poll will run in 4 seconds.
    } catch (_) {
      // ignore errors in background poll
    }
  }
```

---

## 5. When the user taps “I have paid”, stop polling and run the same logic

In your existing `_checkStatus()`:

- At the start, set `_alreadyHandledPaid = true` and call `_stopPolling()` once you’ve decided to show the success dialog (so the timer doesn’t also trigger navigation).
- Optionally, after a successful paid response in `_checkStatus()`, call `_stopPolling()` and then show the dialog and navigate (same as in `_pollStatus()`). That way manual check and auto-poll share the same behavior.

Simplest approach: in `_checkStatus()`, when you get a 200 response and are about to handle it, call `_stopPolling()` and, if paid, set `_alreadyHandledPaid = true` before showing the dialog. That way only one path (button or poll) can navigate.

---

## 6. Optional: keep or remove the button

- **Keep**: User can tap “I have paid, check status” for an immediate check; polling still runs in the background until paid or failed.
- **Remove**: Rely only on auto-polling; you can hide or remove the ElevatedButton for “I have paid, check status”.

---

## Result

- User opens QR page → polling starts every 4 seconds.
- User pays in GCash → on the next poll the backend returns `invoice_status: 'paid'` (and creates the order).
- App stops the timer and **redirects to the home page** (with optional SnackBar). No dialog required.
- No need to press the button; the order is created and the invoice marked paid as soon as the status endpoint sees the payment.

### Optional: show dialog before redirect

If you prefer to show an AlertDialog before going home, use this instead of the block above when `invoiceStatus == 'paid'`:

```dart
      if (invoiceStatus == 'paid') {
        _alreadyHandledPaid = true;
        _stopPolling();
        if (!mounted) return;
        await showDialog<void>(
          context: context,
          barrierDismissible: false,
          builder: (ctx) => AlertDialog(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            title: const Text('Downpayment received'),
            content: const Text(
              'Your downpayment was successful. Your order is now pending confirmation.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(ctx).pop(),
                child: const Text('OK'),
              ),
            ],
          ),
        );
        if (!mounted) return;
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (_) => const HomePage()),
          (route) => false,
        );
      }
```
