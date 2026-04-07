<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            margin: 0;
            padding: 32px;
        }

        .container {
            max-width: 760px;
            margin: 0 auto;
        }

        .card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 18px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, .06);
        }

        h1,
        h2 {
            margin-top: 0;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
        }

        input {
            width: 90%;
            padding: 10px;
            border: 1px solid #d4dae6;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        button {
            background: #1d4ed8;
            color: #fff;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
        }

        .logout {
            background: #dc2626;
        }

        .muted {
            color: #6b7280;
            font-size: 13px;
            margin-top: 4px;
        }

        .alert {
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .ok {
            background: #e7f9ee;
            color: #166534;
        }

        .err {
            background: #fdecec;
            color: #991b1b;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        @media (max-width: 700px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>SuperAdmin Panel</h1>

        @if (session('success'))
            <div class="alert ok">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert err">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert err">
                <strong>There are errors:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!$isLoggedIn)
            <div class="card">
                <h2>Login</h2>
                <form method="POST" action="{{ route('superadmin.login') }}">
                    @csrf
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required>

                    <label>Password</label>
                    <input type="password" name="password" required>

                    <button type="submit">Login</button>
                </form>

            </div>

            @if (!$hasSuperAdmin)
                <div class="card">
                    <h2>Initial Setup (First SuperAdmin)</h2>
                    <form method="POST" action="{{ route('superadmin.setup') }}">
                        @csrf
                        <div class="grid">
                            <div>
                                <label>Name</label>
                                <input type="text" name="name" value="{{ old('name') }}" required>
                            </div>
                            <div>
                                <label>Email</label>
                                <input type="email" name="email" value="{{ old('email') }}" required>
                            </div>
                        </div>
                        <label>Password</label>
                        <input type="password" name="password" required>

                        <label>Confirm Password</label>
                        <input type="password" name="password_confirmation" required>

                        <button type="submit">Create First SuperAdmin</button>
                    </form>
                </div>
            @endif
        @else
            <div class="card">
                <h2>Welcome, {{ $superAdmin['name'] ?? 'SuperAdmin' }}</h2>
                <p class="muted">You are logged in with Firestore-backed credentials.</p>
                <form method="POST" action="{{ route('superadmin.logout') }}">
                    @csrf
                    <button type="submit" class="logout">Logout</button>
                </form>
            </div>

            <div class="card">
                <h2>Create Admin Account (Firestore)</h2>
                <form method="POST" action="{{ route('superadmin.create-admin') }}">
                    @csrf
                    <div class="grid">
                        <div>
                            <label>First Name</label>
                            <input type="text" name="first_name" value="{{ old('first_name') }}" required>
                        </div>
                        <div>
                            <label>Last Name</label>
                            <input type="text" name="last_name" value="{{ old('last_name') }}" required>
                        </div>
                        <div>
                            <label>Admin Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" required>
                        </div>
                    </div>
                    <label>Password</label>
                    <input type="password" name="password" required>

                    <label>Confirm Password</label>
                    <input type="password" name="password_confirmation" required>

                    <button type="submit">Create Admin</button>
                </form>
                <p class="muted">New accounts are stored in Firestore collection: <code>admins</code>.</p>
            </div>
        @endif
    </div>
</body>

</html>
