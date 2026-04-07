<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verify your email</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .box { max-width: 400px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h2 { margin: 0 0 16px; color: #333; font-size: 20px; }
        .otp { font-size: 28px; font-weight: bold; letter-spacing: 8px; color: #111; margin: 20px 0; padding: 16px; background: #f0f0f0; border-radius: 8px; text-align: center; }
        p { color: #555; margin: 0 0 12px; line-height: 1.5; }
        .expiry { font-size: 13px; color: #888; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Verify your email</h2>
        <p>Use this 4-digit code to verify your account:</p>
        <div class="otp">{{ $otp }}</div>
        <p>Enter this code on the verification page to complete signup.</p>
        <p class="expiry">This code expires in 10 minutes. If you didn't request this, you can ignore this email.</p>
    </div>
</body>
</html>
