<!DOCTYPE html>
<html>
<head>
    <title>QRPH Payment</title>
</head>
<body>
    <h2>Scan this QR to pay via QRPH</h2>

    @if($qrData)
        <img src="{{ $qrData }}" alt="QRPH Payment Code">
    @else
        <p>guba imo code balika</p>
    @endif
</body>
</html>