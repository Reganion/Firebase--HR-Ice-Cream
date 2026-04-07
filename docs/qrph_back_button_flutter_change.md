# QrphPage: Mark invoice failed when Back button is pressed

So that the invoice status becomes `failed` when the user presses the back arrow (and they can place a new order with updated details), call the cancel API on back, then pop.

## 1. Add a method to cancel and go back (mark invoice failed, return to Place Order)

Add this method in `_QrphPageState` (e.g. after `_cancelDownpaymentAndExit`):

```dart
  /// Back arrow: mark invoice as failed via API, then return to Place Order screen.
  Future<void> _cancelAndGoBack() async {
    final token = await Auth.getToken();
    if (token == null || token.isEmpty) {
      if (mounted) Navigator.pop(context);
      return;
    }
    final base = Auth.apiBaseUrl;
    try {
      await http.post(
        Uri.parse('$base/orders/downpayment/cancel/${widget.invoiceId}'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );
    } catch (_) {
      // still go back even if request fails
    }
    if (!mounted) return;
    Navigator.pop(context);
  }
```

## 2. Use it for the back button

Replace the back button's `onPressed` from:

```dart
onPressed: () => Navigator.pop(context),
```

to:

```dart
onPressed: _cancelAndGoBack,
```

So the `leading` part of the AppBar becomes:

```dart
        leading: Padding(
          padding: const EdgeInsets.only(left: 8),
          child: IconButton(
            icon: const Icon(Icons.arrow_back, color: Colors.black, size: 24),
            onPressed: _cancelAndGoBack,
          ),
        ),
```

Result:
- **Back arrow** → POST cancel → invoice status set to `failed` → pop to Place Order. User can change details and tap Place Order again; backend will create a new invoice.
- **X/Close** → same cancel API → then navigate to Home (unchanged).
