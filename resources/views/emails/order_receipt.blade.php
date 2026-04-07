<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Receipt</title>
    <style>
        /* Import a modern font */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            padding: 20px;
            margin: 0;
        }

        .receipt-container {
            max-width: 650px;
            margin: auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            border-top: 5px solid #ff5e5e;
        }

        h2 {
            text-align: center;
            color: #ff5e5e;
            margin-bottom: 5px;
        }

        p.receipt-no {
            text-align: center;
            font-size: 14px;
            color: #999;
            margin-bottom: 30px;
        }

        h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .customer-info p {
            margin: 4px 0;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        table img {
            width: 90px;
            border-radius: 10px;
        }

        .total {
            text-align: right;
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-top: 20px;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            color: #aaa;
            font-size: 13px;
            line-height: 1.5;
        }

        /* Modern button-like link for reference if needed */
        .btn {
            display: inline-block;
            padding: 10px 25px;
            margin-top: 15px;
            background: #ff5e5e;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <h2>Thank you for your purchase!</h2>
        <p class="receipt-no">Receipt No: <strong>{{ $order->receipt_no }}</strong></p>

        <h3>Customer Info</h3>
        <div class="customer-info">
            <p>{{ $order->customer_firstname }} {{ $order->customer_lastname }}</p>
            <p>{{ $order->customer_email }}</p>
        </div>

        <h3>Order Details</h3>
        <table>
            <tr>
                <td>
                    <img src="{{ asset($order->flavor_image) }}" alt="{{ $order->flavor_name }}">
                </td>
                <td>
                    <strong>{{ $order->flavor_name }}</strong><br>
                    Price: ₱{{ number_format($order->flavor_price, 2) }}
                </td>
            </tr>
        </table>

        <div class="total">
            Total: ₱{{ number_format($order->total_price, 2) }}
        </div>

        <div class="footer">
            We hope you enjoy your order!<br>
            <strong>Dirty Ice Cream</strong>
        </div>
    </div>
</body>
</html>
