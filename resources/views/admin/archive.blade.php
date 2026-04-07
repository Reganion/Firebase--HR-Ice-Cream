@extends('admin.layout.layout')

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="icon" href="{{ asset('img/logo.png') }}">
    @section('title', 'Driver Archive')
    <link rel="stylesheet" href="{{ asset('assets/css/Admin/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/Admin/driver.css') }}">

</head>

<body>
    @section('content')
        @include('admin.partials.alert')
        <div class="content-area drivers-page">
            <div class="driver-header">
                <div class="driver-left">
                    <a href="{{ route('admin.drivers') }}" class="btn-archive">
                        <span class="material-symbols-outlined">arrow_back</span>
                        Back
                    </a>
                    <h2>Archived Drivers</h2>
                </div>

                <div class="driver-actions">
                    <div class="driver-search">
                        <span class="material-symbols-outlined">search</span>
                        <input type="text" id="archiveSearchInput" placeholder="Search by driver name">
                    </div>
                </div>
            </div>

            <div class="drivers-body">
                <div class="drivers-scroll">
                    <div class="driver-grid" id="archiveDriverGrid">
                        @forelse ($drivers as $driver)
                            @php
                                $driverCode = $driver->driver_code ?? 'DRV' . str_pad((string)$driver->id, 3, '0', STR_PAD_LEFT);
                            @endphp
                            <div class="driver-card" data-driver-name="{{ strtolower($driver->name ?? '') }}">
                                <img src="{{ (isset($driver->image) && $driver->image) ? asset($driver->image) : asset('img/default-user.png') }}" alt="{{ $driver->name ?? '' }}">
                                <div class="driver-name-row">
                                    <h4>{{ $driver->name }}</h4>
                                    <span class="code">{{ $driverCode }}</span>
                                </div>
                                <div class="driver-info">
                                    <p><strong>Phone</strong><span>{{ $driver->phone ?? '—' }}</span></p>
                                    <p><strong>Email</strong><span class="driver-email">{{ $driver->email ?? '—' }}</span></p>
                                    <p><strong>License No.</strong><span>{{ $driver->license_no ?? '—' }}</span></p>
                                    <p><strong>Type</strong><span>{{ $driver->license_type ?? '—' }}</span></p>
                                </div>
                                <div class="driver-status-row">
                                    <span class="status archive">Archived</span>
                                </div>
                            </div>
                        @empty
                            <div style="grid-column:1/-1;text-align:center;padding:40px;color:#666;">
                                No archived drivers found.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

    @endsection
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const searchInput = document.getElementById("archiveSearchInput");
            const cards = Array.from(document.querySelectorAll("#archiveDriverGrid .driver-card"));

            function applyArchiveSearch() {
                const keyword = (searchInput?.value || "").trim().toLowerCase();
                cards.forEach((card) => {
                    const name = card.dataset.driverName || "";
                    card.style.display = name.includes(keyword) ? "block" : "none";
                });
            }

            searchInput?.addEventListener("input", applyArchiveSearch);
        });
    </script>
</body>

</html>
