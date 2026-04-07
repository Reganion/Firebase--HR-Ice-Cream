<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'H&R Ice Cream Admin') </title>
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/Admin/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/Admin/notification.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/Admin/dashboard.css') }}">
    {{-- Firebase: RTDB (chat sync) + optional Firestore (admin_signals/broadcast for notifications/toasts/badge) --}}
    <script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-database-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-firestore-compat.js"></script>
    <script>window.FIREBASE_DATABASE_URL = @json(config('services.firebase_realtime_url') ?: (config('firebase.projects.app.database.url') ?? ''));</script>
    @php
        $fw = config('services.firebase_web', []);
        $firebaseWebPublic = array_filter([
            'apiKey' => (string) ($fw['api_key'] ?? ''),
            'authDomain' => (string) ($fw['auth_domain'] ?? ''),
            'projectId' => (string) ($fw['project_id'] ?? ''),
            'storageBucket' => (string) ($fw['storage_bucket'] ?? ''),
            'messagingSenderId' => (string) ($fw['messaging_sender_id'] ?? ''),
            'appId' => (string) ($fw['app_id'] ?? ''),
        ], fn ($v) => $v !== '');
    @endphp
    <script>window.FIREBASE_WEB_CONFIG = @json($firebaseWebPublic);</script>
    <style>
        .logout-confirm-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 4000;
            padding: 0;
        }

        .logout-confirm-modal.open {
            display: flex;
        }

        .logout-confirm-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
        }

        .logout-confirm-card {
            position: relative;
            width: 420px;
            max-width: calc(100% - 32px);
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            padding: 26px;
            z-index: 1;
            text-align: left;
        }

        .logout-confirm-title {
            margin: 0 0 10px;
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }

        .logout-confirm-text {
            margin: 0 0 24px;
            font-size: 14px;
            line-height: 1.5;
            color: #6b7280;
        }

        .logout-confirm-actions {
            display: flex;
            justify-content: center;
            gap: 14px;
        }

        .logout-confirm-btn {
            border: 0;
            border-radius: 999px;
            padding: 10px 18px;
            cursor: pointer;
        }

        .logout-confirm-btn.cancel {
            background: #eef2f7;
            color: #374151;
            font-weight: 500;
        }

        .logout-confirm-btn.confirm {
            background: #ef4444;
            color: #fff;
            font-weight: 600;
        }

        .logout-confirm-btn.confirm:hover {
            background: #dc2626;
        }

        .logout-confirm-btn:disabled {
            opacity: 0.75;
            cursor: not-allowed;
        }

        .logout-confirm-btn.confirm.is-loading {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .logout-confirm-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.45);
            border-top-color: #fff;
            border-radius: 50%;
            animation: logoutSpin 0.75s linear infinite;
        }

        @keyframes logoutSpin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 600px) {
            .logout-confirm-card {
                width: calc(100% - 24px);
                max-width: none;
                padding: 20px 16px;
                border-radius: 14px;
            }

            .logout-confirm-actions {
                flex-direction: column;
                gap: 10px;
            }

            .logout-confirm-btn.cancel,
            .logout-confirm-btn.confirm {
                width: 100%;
                min-height: 42px;
            }
        }

        .menu li a.support-centre-link {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .menu li a.support-centre-link .menu-text {
            flex: 1;
            min-width: 0;
        }

        .menu li a.support-centre-link .menu-unread-badge {
            min-width: 20px;
            height: 20px;
            border-radius: 999px;
            padding: 0 6px;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            background: #ef4444;
            box-shadow: 0 1px 6px rgba(239, 68, 68, 0.35);
        }

        .menu li a.support-centre-link .menu-unread-badge.show {
            display: inline-flex;
        }

        /* Top bar: Support / chat unread (matches notification chip) */
        .support-top-chat {
            width: 40px;
            height: 40px;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            flex-shrink: 0;
            text-decoration: none;
            color: #333;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .support-top-chat:hover {
            background: #f3f4f6;
            color: #111;
        }

        .support-top-chat.has-unread .material-symbols-outlined {
            font-variation-settings: "FILL" 1, "wght" 400, "GRAD" 0, "opsz" 24;
        }

        .support-top-badge {
            display: none;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            color: #fff;
            background: #dc3545;
            border-radius: 50%;
            border: 2px solid #fff;
            box-sizing: border-box;
        }

        .support-top-badge.show {
            display: flex;
        }
    </style>
</head>

<body>
    <!-- Toast alerts: drop from top, slide up on exit -->
    <div class="admin-toast-container" id="adminToastContainer" aria-live="polite"></div>
    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>
    <aside class="sidebar">
        <div>
            <div class="logo">
                <img src="{{ asset('img/logo.png') }}" alt="H&R Logo" />
            </div>

            <div class="menu-label">MENU</div>
            <ul class="menu">
                <li class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <a href="{{ route('admin.dashboard') }}">
                        <span class="material-symbols-outlined">grid_view</span>
                        Dashboard
                    </a>
                </li>

                <li
                    class="dropdown-parent {{ request()->routeIs('admin.flavors', 'admin.ingredients', 'admin.gallon') ? 'open' : '' }}">
                    <a class="dropdown">
                        <div class="left-label">
                            <span class="material-symbols-outlined">icecream</span>
                            Product
                        </div>
                        <span class="material-symbols-outlined arrow-icon">keyboard_arrow_down</span>
                    </a>

                    <ul class="submenu">
                        <li class="{{ request()->routeIs('admin.flavors') ? 'active' : '' }}">
                            <a href="{{ route('admin.flavors') }}">Flavor</a>
                        </li>
                        <li class="{{ request()->routeIs('admin.ingredients') ? 'active' : '' }}">
                            <a href="{{ route('admin.ingredients') }}">Ingredients</a>
                        </li>
                        <li class="{{ request()->routeIs('admin.gallon') ? 'active' : '' }}">
                            <a href="{{ route('admin.gallon') }}">Gallon</a>
                        </li>
                    </ul>
                </li>

                <li class="{{ request()->routeIs('admin.orders') ? 'active' : '' }}">
                    <a href="{{ route('admin.orders') }}">
                        <span class="material-symbols-outlined">shopping_basket</span>
                        Orders
                    </a>
                </li>

                <li class="{{ request()->routeIs('admin.records') ? 'active' : '' }}">
                    <a href="{{ route('admin.records') }}">
                        <span class="material-symbols-outlined">history</span>
                        Records
                    </a>
                </li>

                <li class="{{ request()->routeIs('admin.drivers') ? 'active' : '' }}">
                    <a href="{{ route('admin.drivers') }}">
                        <span class="material-symbols-outlined">groups</span>
                        Drivers
                    </a>
                </li>
                <li class="{{ request()->routeIs('admin.customer') ? 'active' : '' }}">
                    <a href="{{ route('admin.customer') }}">
                        <span class="material-symbols-outlined">person</span>
                        Customer
                    </a>
                </li>
                <li class="{{ request()->routeIs('admin.support-centre') ? 'active' : '' }}">
                    <a href="{{ route('admin.support-centre') }}" class="support-centre-link">
                        <span class="material-symbols-outlined">support_agent</span>
                        <span class="menu-text">Support Centre</span>
                        <span class="menu-unread-badge" id="supportCentreMenuBadge" aria-label="Unread support messages">0</span>
                    </a>
                </li>
            </ul>
        </div>

        <ul class="account-settings">
            <li class="settings-label">SETTINGS</li>

            <li class="{{ request()->routeIs('admin.account') ? 'active' : '' }}">
                <a href="{{ route('admin.account') }}">
                    <span class="material-symbols-outlined">account_circle</span>
                    Account
                </a>
            </li>

            <li><a href="{{ route('admin.logout') }}" class="logout-link"><span class="material-symbols-outlined">logout</span>Logout</a>
            </li>
        </ul>
    </aside>

    @php
        $adminUser = $adminUser ?? null;
    @endphp
    <div class="main-content">
        <div class="top-bar">
            <div class="user">
                <img src="{{ data_get($adminUser, 'image') ? asset(data_get($adminUser, 'image')) : asset('img/default-user.png') }}" alt="Profile">
                <div class="user-info">
                    <strong>Hello, {{ trim((string) data_get($adminUser, 'first_name', '') . ' ' . (string) data_get($adminUser, 'last_name', '')) ?: 'Admin' }}</strong>
                    <small id="manila-date"></small>
                </div>
            </div>

            <div class="right-side">
                <a href="{{ route('admin.support-centre') }}" class="support-top-chat" id="supportTopBarChat" aria-label="Support Centre">
                    <span class="material-symbols-outlined" aria-hidden="true">forum</span>
                    <span class="support-top-badge" id="supportTopBarBadge" aria-hidden="true">0</span>
                </a>

                @php
                    $adminUnreadCount = isset($adminNotifications) ? $adminNotifications->whereNull('read_at')->count() : 0;
                @endphp
                <div class="notification {{ $adminUnreadCount > 0 ? 'has-unread' : '' }}" id="notifBtn">
                    <span class="material-symbols-outlined notif-bell">notifications</span>
                    <span class="notif-badge {{ $adminUnreadCount === 0 ? 'zero' : '' }}" id="notifBadge" data-count="{{ $adminUnreadCount }}">{{ $adminUnreadCount }}</span>

                    <!-- DROPDOWN -->
                    <div class="notification-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <span class="title">Notifications</span>

                            <div class="mark-read {{ $adminUnreadCount === 0 ? 'read' : '' }}" id="markAllReadBtn">
                                <span class="material-symbols-outlined">done_all</span>
                                <span>Mark all as read</span>
                            </div>
                        </div>

                        <div class="notif-tabs">
                            <span class="notif-tab active" data-tab="all">All</span>
                            <span class="notif-tab unread-tab" data-tab="unread">Unread <span class="unread-count">{{ isset($adminNotifications) ? $adminNotifications->whereNull('read_at')->count() : 0 }}</span></span>
                        </div>

                        <div class="notif-list">
                            @forelse (isset($adminNotifications) ? $adminNotifications : [] as $notif)
                                <div class="notif-item {{ $notif->read_at ? '' : 'unread' }}" data-id="{{ $notif->id }}" data-type="{{ $notif->type }}" data-related-id="{{ ($notif->related_type === 'Order') ? ($notif->related_id ?? '') : '' }}">
                                    @if (($notif->related_type === 'Order') && $notif->image_url)
                                        <div class="notif-icon profile">
                                            <img src="{{ asset($notif->image_url) }}" alt="">
                                        </div>
                                    @elseif ($notif->related_type === 'Order')
                                        <div class="notif-icon delivered">
                                            <span class="material-symbols-outlined">shopping_cart</span>
                                        </div>
                                    @else
                                        <div class="notif-icon delivered">
                                            <span class="material-symbols-outlined">notifications_active</span>
                                        </div>
                                    @endif
                                    <div class="notif-text">
                                        <span>
                                            <strong>{{ $notif->title ?? 'Notification' }}</strong>@if (!empty($notif->data['subtitle']))
                                                <span class="muted"> {{ $notif->data['subtitle'] }}</span>@endif
                                            @if (!empty($notif->data['highlight']))
                                                <span class="highlight"> {{ $notif->data['highlight'] }}</span>
                                            @endif
                                            @if ($notif->message && empty($notif->data['subtitle']))
                                                <span class="muted"> {{ $notif->message }}</span>
                                            @endif
                                        </span>
                                        <span class="notif-time">{{ filled($notif->created_at ?? null) ? \Carbon\Carbon::parse($notif->created_at)->diffForHumans() : '' }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="notif-empty">No notifications yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="content-area">
            @yield('content')
        </div>
    </div>

    <div class="logout-confirm-modal" id="logoutConfirmModal" aria-hidden="true">
        <div class="logout-confirm-backdrop" id="logoutConfirmBackdrop"></div>
        <div class="logout-confirm-card" role="dialog" aria-modal="true" aria-labelledby="logoutConfirmTitle">
            <h3 class="logout-confirm-title" id="logoutConfirmTitle">
                Are you sure you want to logout?
            </h3>
            <p class="logout-confirm-text">You will need to login again to access your account.</p>
            <div class="logout-confirm-actions">
                <button type="button" class="logout-confirm-btn cancel" id="logoutConfirmCancel">Cancel</button>
                <button type="button" class="logout-confirm-btn confirm" id="logoutConfirmOk">Logout</button>
            </div>
        </div>
    </div>

<script>
    // ========================
    // Dropdown Menu Toggle (Product sidebar)
    // ========================
    const dropdownParent = document.querySelector('.dropdown-parent');
    if (dropdownParent) {
        const dropdownLink = dropdownParent.querySelector('a.dropdown');
        if (dropdownLink) {
            dropdownLink.addEventListener('click', (e) => {
                e.preventDefault();
                dropdownParent.classList.toggle('open');
            });
        }
    }

    // ========================
    // Manila Time Display
    // ========================
    function updateManilaTime() {
        const options = {
            timeZone: "Asia/Manila",
            weekday: "long",
            year: "numeric",
            month: "long",
            day: "2-digit"
        };
        const now = new Date();
        const formatted = now.toLocaleDateString("en-US", options);
        document.getElementById("manila-date").textContent = formatted;
    }
    setInterval(updateManilaTime, 1000);
    updateManilaTime();

    // ========================
    // Hamburger Menu Toggle
    // ========================
    const hamburger = document.createElement('div');
    hamburger.className = 'hamburger';
    hamburger.innerHTML = '<span class="material-symbols-outlined">menu</span>';
    document.querySelector('.top-bar').prepend(hamburger);

    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    hamburger.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        if (sidebarOverlay) {
            sidebarOverlay.classList.toggle('visible', sidebar.classList.contains('active'));
            sidebarOverlay.setAttribute('aria-hidden', sidebar.classList.contains('active') ? 'false' : 'true');
        }
    });

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('visible');
            sidebarOverlay.setAttribute('aria-hidden', 'true');
        });
    }

    document.addEventListener('click', (e) => {
        if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
            sidebar.classList.remove('active');
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('visible');
                sidebarOverlay.setAttribute('aria-hidden', 'true');
            }
        }
    });

    // ========================
    // Adjust Top-Bar for Non-Dashboard Pages
    // ========================
    document.addEventListener('DOMContentLoaded', () => {
        const isDashboard = "{{ request()->routeIs('admin.dashboard') }}" === '1';
        if (!isDashboard) {
            const topBar = document.querySelector('.top-bar');
            const user = document.querySelector('.user');
            const rightSide = document.querySelector('.right-side');

            function adjustTopBar() {
                if (window.innerWidth <= 600) {
                    topBar.style.flexDirection = 'row';
                    topBar.style.alignItems = 'center';
                    user.style.order = '1';
                    user.style.flex = '1';
                    rightSide.style.order = '2';
                    rightSide.style.width = 'auto';
                    rightSide.style.marginLeft = 'auto';
                    rightSide.style.justifyContent = 'flex-end';
                } else {
                    topBar.style.flexDirection = '';
                    topBar.style.alignItems = '';
                    user.style.order = '';
                    user.style.flex = '';
                    rightSide.style.order = '';
                    rightSide.style.width = '';
                    rightSide.style.marginLeft = '';
                    rightSide.style.justifyContent = '';
                }
            }

            adjustTopBar();
            window.addEventListener('resize', adjustTopBar);
        }
    });

    // ========================
    // Notifications Dropdown
    // ========================
    const notifBtn = document.getElementById("notifBtn");
    const notifDropdown = document.getElementById("notifDropdown");
    const notifBadge = document.getElementById("notifBadge");
    const markAll = document.querySelector(".mark-read");
    const unreadCountElem = document.querySelector(".unread-count");
    const tabs = document.querySelectorAll(".notif-tabs .notif-tab");
    const notifList = document.querySelector(".notif-list");
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // Keep badge count in sync; only update from DOM when we actually mark read (not when opening dropdown)
    function updateUnreadCount() {
        const unreadItems = document.querySelectorAll("#notifDropdown .notif-item.unread");
        const count = unreadItems.length;
        if (unreadCountElem) {
            unreadCountElem.textContent = count;
            unreadCountElem.style.display = count === 0 ? "none" : "inline-block";
        }
        if (notifBadge) {
            notifBadge.textContent = count;
            notifBadge.setAttribute("data-count", count);
            notifBadge.classList.toggle("zero", count === 0);
        }
        if (notifBtn) {
            notifBtn.classList.toggle("has-unread", count > 0);
        }
        if (markAll) {
            markAll.classList.toggle("read", count === 0);
        }
    }

    // Adjust dropdown position only for mobile
    function adjustNotifDropdownMobile() {
        const isDashboard = "{{ request()->routeIs('admin.dashboard') }}" === '1';
        if (window.innerWidth <= 600 && notifDropdown) {
            notifDropdown.style.width = "90vw";
            notifDropdown.style.right = "5px";
            notifDropdown.style.bottom = "5px";

            if (isDashboard) {
                notifDropdown.style.top = "160px";
            } else {
                notifDropdown.style.top = ""; // reset for non-dashboard pages
            }
        } else if (notifDropdown) {
            // Reset mobile-specific styles on desktop
            notifDropdown.style.top = "";
            notifDropdown.style.right = "";
            notifDropdown.style.bottom = "";
            notifDropdown.style.width = "";
        }
    }

    // Call on load and resize
    adjustNotifDropdownMobile();
    window.addEventListener("resize", adjustNotifDropdownMobile);

    // Toggle dropdown on bell click (do not recalculate count — badge stays correct)
    if (notifBtn) {
        notifBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            e.preventDefault();
            const isOpen = notifDropdown.style.display === "block";
            notifDropdown.style.display = isOpen ? "none" : "block";
        });
    }

    // Prevent dropdown from closing when clicking inside
    notifDropdown.addEventListener("click", (e) => e.stopPropagation());

    // Close dropdown when clicking outside
    document.addEventListener("click", function(e) {
        if (!notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
            notifDropdown.style.display = "none";
        }
    });

    // Click on individual notification: mark as read
    if (notifList) {
        notifList.addEventListener("click", async (e) => {
            const item = e.target.closest(".notif-item[data-id]");
            if (!item) return;

            if (item.classList.contains("unread")) {
                const id = item.getAttribute("data-id");
                try {
                    const res = await fetch(`/admin/notifications/${id}/read`, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": csrfToken,
                            "Accept": "application/json",
                            "X-Requested-With": "XMLHttpRequest"
                        },
                        credentials: "same-origin",
                        body: JSON.stringify({})
                    });
                    if (res.ok) {
                        item.classList.remove("unread");
                        updateUnreadCount();
                    }
                } catch (err) {
                    console.error("Mark read failed", err);
                }
            }
        });
    }

    // Mark all as read (API + UI) — persists in DB
    if (markAll) {
        markAll.addEventListener("click", async () => {
            if (markAll.classList.contains("read")) return;
            try {
                const res = await fetch("{{ route('admin.notifications.mark-all-read') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrfToken,
                        "Accept": "application/json",
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    credentials: "same-origin",
                    body: JSON.stringify({})
                });
                const data = res.ok ? await res.json().catch(() => ({})) : null;
                if (res.ok && (data === null || data.success !== false)) {
                    document.querySelectorAll(".notif-item.unread").forEach(item => {
                        item.classList.remove("unread");
                        const activeTab = document.querySelector(".notif-tab.active");
                        if (activeTab && activeTab.getAttribute("data-tab") === "unread") {
                            item.style.display = "none";
                        }
                    });
                    updateUnreadCount();
                    markAll.classList.add("read");
                }
            } catch (err) {
                console.error("Mark all read failed", err);
            }
        });
    }

    // Tab switching (All / Unread)
    tabs.forEach(tab => {
        tab.addEventListener("click", () => {
            tabs.forEach(t => t.classList.remove("active"));
            tab.classList.add("active");

            const allItems = document.querySelectorAll(".notif-item[data-id]");
            const tabType = tab.getAttribute("data-tab");
            if (tabType === "all") {
                allItems.forEach(item => { item.style.display = "flex"; });
            } else if (tabType === "unread") {
                allItems.forEach(item => {
                    item.style.display = item.classList.contains("unread") ? "flex" : "none";
                });
            }
        });
    });

    // Initialize unread count
    updateUnreadCount();

    // ========================
    // Real-time notifications (polling)
    // ========================
    const NOTIFICATIONS_POLL_URL = "{{ route('admin.notifications.index') }}";
    const NOTIFICATIONS_POLL_INTERVAL_MS = 10000; // 10 seconds
    const TOAST_DURATION_MS = 5000;
    // Baseline = IDs we already know about (no toast for these). First poll after load sets baseline only.
    let lastSeenNotifIds = new Set(Array.from(document.querySelectorAll(".notif-item[data-id]")).map(el => String(el.getAttribute("data-id"))));
    let firstPollDone = false;

    function getToastMessage(notif) {
        const data = notif.data || {};
        const subtitle = (data.subtitle || '').trim();
        const highlight = (data.highlight || '').trim();
        const name = (notif.title || 'Someone').trim();
        if (subtitle && highlight) {
            return name + " " + subtitle + " " + highlight;
        }
        if (notif.message) return name + " — " + notif.message;
        if (notif.type === 'order_new') return name + " placed a new order.";
        return name;
    }

    function showToast(notif) {
        const container = document.getElementById("adminToastContainer");
        if (!container) return;
        const message = getToastMessage(notif);
        const hasImage = (notif.type === 'profile_update' || notif.type === 'address_update' || notif.type === 'order_new') && notif.image_url;
        const iconHtml = hasImage
            ? `<div class="toast-icon"><img src="${escapeHtml(notif.image_url)}" alt=""></div>`
            : `<div class="toast-icon"><span class="material-symbols-outlined" style="font-size:22px">notifications_active</span></div>`;
        const el = document.createElement("div");
        el.className = "admin-toast";
        el.setAttribute("role", "alert");
        el.innerHTML = iconHtml + `<span class="toast-message">${escapeHtml(message)}</span><button type="button" class="toast-close" aria-label="Close"><span class="material-symbols-outlined" style="font-size:18px">close</span></button>`;
        container.appendChild(el);
        requestAnimationFrame(() => el.classList.add("toast-enter"));

        function dismiss() {
            el.classList.remove("toast-enter");
            el.classList.add("toast-exit");
            setTimeout(() => el.remove(), 350);
        }

        el.querySelector(".toast-close").addEventListener("click", dismiss);
        const t = setTimeout(dismiss, TOAST_DURATION_MS);
        el._toastTimer = t;
    }

    function buildNotifIcon(notif) {
        const isOrderNotif = String(notif.related_type || '').toLowerCase() === 'order';
        const hasImage = isOrderNotif && notif.image_url;
        if (hasImage) {
            return `<div class="notif-icon profile"><img src="${escapeHtml(notif.image_url)}" alt=""></div>`;
        }
        if (isOrderNotif) {
            return `<div class="notif-icon delivered"><span class="material-symbols-outlined">shopping_cart</span></div>`;
        }
        return `<div class="notif-icon delivered"><span class="material-symbols-outlined">notifications_active</span></div>`;
    }

    function escapeHtml(str) {
        if (str == null) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function renderNotifItem(notif) {
        const isUnread = !notif.read_at;
        const data = notif.data || {};
        const subtitle = data.subtitle ? ` <span class="muted">${escapeHtml(data.subtitle)}</span>` : '';
        const highlight = data.highlight ? ` <span class="highlight">${escapeHtml(data.highlight)}</span>` : '';
        const message = notif.message && !data.subtitle ? ` <span class="muted">${escapeHtml(notif.message)}</span>` : '';
        const isOrder = String(notif.related_type || '').toLowerCase() === 'order' && notif.related_id;
        const dataType = escapeHtml(String(notif.type || ''));
        const dataRelatedId = isOrder ? escapeHtml(String(notif.related_id)) : '';
        return `<div class="notif-item ${isUnread ? 'unread' : ''}" data-id="${escapeHtml(String(notif.id))}" data-type="${dataType}" data-related-id="${dataRelatedId}">${buildNotifIcon(notif)}<div class="notif-text"><span><strong>${escapeHtml(notif.title)}</strong>${subtitle}${highlight}${message}</span><span class="notif-time">${escapeHtml(notif.created_at_human || '')}</span></div></div>`;
    }

    let notificationsFetchController = null;
    let notificationsRefreshTimer = null;
    let notificationsRefreshInFlight = false;

    function scheduleNotificationsRefresh(delayMs) {
        const wait = Number.isFinite(delayMs) ? Math.max(0, delayMs) : 220;
        if (notificationsRefreshTimer) clearTimeout(notificationsRefreshTimer);
        notificationsRefreshTimer = setTimeout(() => {
            fetchAndRenderNotifications();
        }, wait);
    }

    async function fetchAndRenderNotifications() {
        if (!NOTIFICATIONS_POLL_URL || !notifList) return;
        if (notificationsRefreshInFlight) {
            scheduleNotificationsRefresh(240);
            return;
        }
        if (notificationsFetchController) notificationsFetchController.abort();
        notificationsFetchController = new AbortController();
        notificationsRefreshInFlight = true;
        try {
            const res = await fetch(NOTIFICATIONS_POLL_URL, {
                headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" },
                credentials: "same-origin",
                signal: notificationsFetchController.signal
            });
            if (!res.ok) return;
            const data = await res.json().catch(() => ({}));
            const notifications = data.notifications || [];
            const unreadCount = data.unread_count != null ? data.unread_count : 0;
            if (notifications.length === 0) {
                notifList.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
            } else {
                notifList.innerHTML = notifications.map(renderNotifItem).join("");
            }
            updateUnreadCount();
        } catch (e) {
            if (e && e.name === "AbortError") return;
            /* ignore */
        } finally {
            notificationsRefreshInFlight = false;
            notificationsFetchController = null;
        }
    }

    /**
     * Firestore doc admin_signals/broadcast (written by Laravel mergeAdminBroadcast) or RTDB fallbacks.
     */
    function initFirebaseAppForAdmin() {
        if (typeof firebase === "undefined") return false;
        if (firebase.apps.length) return true;
        var opts = {};
        var w = window.FIREBASE_WEB_CONFIG || {};
        if (w.apiKey && w.projectId) {
            opts.apiKey = w.apiKey;
            opts.authDomain = w.authDomain || "";
            opts.projectId = w.projectId;
            opts.storageBucket = w.storageBucket || "";
            opts.messagingSenderId = w.messagingSenderId || "";
            opts.appId = w.appId || "";
        }
        if (window.FIREBASE_DATABASE_URL) {
            opts.databaseURL = window.FIREBASE_DATABASE_URL;
        }
        if (!opts.databaseURL && !opts.apiKey) return false;
        firebase.initializeApp(opts);
        return true;
    }

    if (initFirebaseAppForAdmin()) {
        var useFirestoreSignals = !!(window.FIREBASE_WEB_CONFIG && window.FIREBASE_WEB_CONFIG.apiKey && window.FIREBASE_WEB_CONFIG.projectId && typeof firebase.firestore === "function");
        if (useFirestoreSignals) {
            try {
                var fs = firebase.firestore();
                var notifLastValFs = null;
                var orderAlertLastFs = null;
                var chatLastValFs = null;
                fs.doc("admin_signals/broadcast").onSnapshot(function(snap) {
                    var d = snap.exists ? snap.data() : {};
                    var nAt = d.notifications_at || "";
                    if (nAt && notifLastValFs !== null && notifLastValFs !== nAt) {
                        scheduleNotificationsRefresh(220);
                    }
                    if (notifLastValFs === null) notifLastValFs = nAt || "";
                    else if (nAt) notifLastValFs = nAt;

                    var oa = d.order_alert || null;
                    var ots = oa && oa.value ? String(oa.value) : "";
                    if (oa && ots && orderAlertLastFs !== null && orderAlertLastFs !== ots) {
                        var title = (oa.title || "New order").trim();
                        var subtitle = (oa.subtitle || "").trim();
                        var highlight = (oa.highlight || "").trim();
                        var msg = [title, subtitle, highlight].filter(Boolean).join(" — ");
                        if (msg) {
                            showToast({ type: "order_new", title: title, data: { subtitle: subtitle, highlight: highlight }, image_url: oa.image_url || null });
                            var prevTitle = document.title;
                            document.title = "🔔 New order! – " + (prevTitle || "Admin");
                            setTimeout(function() { document.title = prevTitle; }, 3000);
                        }
                        scheduleNotificationsRefresh(220);
                    }
                    if (orderAlertLastFs === null) orderAlertLastFs = ots || "";
                    else if (ots) orderAlertLastFs = ots;

                    var cAt = d.chat_at || "";
                    if (cAt && chatLastValFs !== null && chatLastValFs !== cAt) {
                        document.dispatchEvent(new CustomEvent("admin:support-chat-signal"));
                    }
                    if (chatLastValFs === null) chatLastValFs = cAt || "";
                    else if (cAt) chatLastValFs = cAt;
                });
            } catch (e) {
                console.warn("Firestore admin_signals/broadcast listener failed; using RTDB if available.", e);
                useFirestoreSignals = false;
            }
        }
        if (!useFirestoreSignals && window.FIREBASE_DATABASE_URL && firebase.database) {
            try {
                var db = firebase.database();
                var notifLastVal = null;
                db.ref("admin/notifications_last_updated").on("value", function(snapshot) {
                    var val = snapshot.val();
                    var ts = val && val.value ? val.value : "";
                    if (ts && notifLastVal !== null && notifLastVal !== ts) scheduleNotificationsRefresh(220);
                    if (notifLastVal === null) notifLastVal = ts || "";
                });
                var lastOrderAlertVal = null;
                db.ref("admin/latest_order_alert").on("value", function(snapshot) {
                    var val = snapshot.val();
                    var ts = (val && val.value) ? val.value : "";
                    if (!val || !ts || lastOrderAlertVal === ts) { if (lastOrderAlertVal === null) lastOrderAlertVal = ts || ""; return; }
                    lastOrderAlertVal = ts;
                    var t = (val.title || "New order").trim();
                    var st = (val.subtitle || "").trim();
                    var hi = (val.highlight || "").trim();
                    var m = [t, st, hi].filter(Boolean).join(" — ");
                    if (m) {
                        showToast({ type: "order_new", title: t, data: { subtitle: st, highlight: hi }, image_url: val.image_url || null });
                        var pt = document.title;
                        document.title = "🔔 New order! – " + (pt || "Admin");
                        setTimeout(function() { document.title = pt; }, 3000);
                    }
                    scheduleNotificationsRefresh(220);
                });
                var chatLastRtdb = null;
                db.ref("admin/chat_last_updated").on("value", function(snapshot) {
                    var val = snapshot.val();
                    var ts = val && val.value ? String(val.value) : "";
                    if (ts && chatLastRtdb !== null && chatLastRtdb !== ts) {
                        document.dispatchEvent(new CustomEvent("admin:support-chat-signal"));
                    }
                    if (chatLastRtdb === null) chatLastRtdb = ts || "";
                    else if (ts) chatLastRtdb = ts;
                });
            } catch (e) {
                console.warn("Firebase Realtime Database admin listeners failed.", e);
            }
        }
    }

    fetchAndRenderNotifications();
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarBadgeEl = document.getElementById('supportCentreMenuBadge');
    const topBadgeEl = document.getElementById('supportTopBarBadge');
    const topChatEl = document.getElementById('supportTopBarChat');
    const unreadSummaryUrl = "{{ route('admin.chat.unread-summary') }}";
    if (!sidebarBadgeEl || !unreadSummaryUrl) return;

    let isFetchingUnread = false;

    /** Sidebar + top bar: customers / threads with at least one unread message (same as convo list). */
    function setConversationBadges(conversationCount) {
        const count = Number.isFinite(conversationCount) && conversationCount > 0 ? conversationCount : 0;
        const label = count > 99 ? '99+' : String(count);
        if (sidebarBadgeEl) {
            sidebarBadgeEl.textContent = label;
            sidebarBadgeEl.classList.toggle('show', count > 0);
            sidebarBadgeEl.setAttribute(
                'aria-label',
                count > 0 ? label + ' conversations with unread messages' : 'Unread support conversations'
            );
        }
        if (topBadgeEl) {
            topBadgeEl.textContent = label;
            topBadgeEl.classList.toggle('show', count > 0);
        }
        if (topChatEl) {
            topChatEl.classList.toggle('has-unread', count > 0);
            topChatEl.setAttribute(
                'aria-label',
                count > 0 ? ('Support Centre — ' + label + ' conversations with unread') : 'Support Centre'
            );
        }
    }

    async function refreshSupportUnreadBadge() {
        if (isFetchingUnread) return;
        isFetchingUnread = true;
        try {
            const res = await fetch(unreadSummaryUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            if (!res.ok) {
                isFetchingUnread = false;
                return;
            }
            const data = await res.json().catch(() => ({}));
            const senders = data?.senders;
            const convoCount = Array.isArray(senders) ? senders.length : 0;
            setConversationBadges(Number.isFinite(convoCount) ? convoCount : 0);
        } catch (e) {
            /* ignore unread badge fetch errors */
        } finally {
            isFetchingUnread = false;
        }
    }

    window.__refreshSupportUnreadBadge = refreshSupportUnreadBadge;

    refreshSupportUnreadBadge();

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) refreshSupportUnreadBadge();
    });

    document.addEventListener('support:unread-conversations-updated', function(event) {
        const d = event?.detail || {};
        let setConvo = false;

        if (d.unreadConversations != null && d.unreadConversations !== '') {
            const c = Number.parseInt(d.unreadConversations, 10);
            if (Number.isFinite(c)) {
                setConversationBadges(c);
                setConvo = true;
            }
        } else if (d.count != null && d.count !== '') {
            const c = Number.parseInt(d.count, 10);
            if (Number.isFinite(c)) {
                setConversationBadges(c);
                setConvo = true;
            }
        }

        if (!setConvo) {
            refreshSupportUnreadBadge();
        }
    });

    document.addEventListener('admin:support-chat-signal', function() {
        refreshSupportUnreadBadge();
    });
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const logoutConfirmModal = document.getElementById("logoutConfirmModal");
    const logoutConfirmBackdrop = document.getElementById("logoutConfirmBackdrop");
    const logoutConfirmCancel = document.getElementById("logoutConfirmCancel");
    const logoutConfirmOk = document.getElementById("logoutConfirmOk");
    const logoutConfirmOkDefaultHtml = logoutConfirmOk ? logoutConfirmOk.innerHTML : 'Logout';

    var pendingLogoutUrl = '';
    var isLoggingOut = false;
    function openLogoutConfirm(url) {
        pendingLogoutUrl = url || '';
        if (!logoutConfirmModal) return;
        isLoggingOut = false;
        if (logoutConfirmCancel) logoutConfirmCancel.disabled = false;
        if (logoutConfirmOk) {
            logoutConfirmOk.disabled = false;
            logoutConfirmOk.classList.remove('is-loading');
            logoutConfirmOk.innerHTML = logoutConfirmOkDefaultHtml;
        }
        logoutConfirmModal.classList.add('open');
        logoutConfirmModal.setAttribute('aria-hidden', 'false');
    }

    function closeLogoutConfirm() {
        if (isLoggingOut) return;
        pendingLogoutUrl = '';
        if (!logoutConfirmModal) return;
        logoutConfirmModal.classList.remove('open');
        logoutConfirmModal.setAttribute('aria-hidden', 'true');
    }

    function doConfirmedLogout() {
        if (!pendingLogoutUrl || isLoggingOut) return;
        isLoggingOut = true;
        if (logoutConfirmCancel) logoutConfirmCancel.disabled = true;
        if (logoutConfirmOk) {
            logoutConfirmOk.disabled = true;
            logoutConfirmOk.classList.add('is-loading');
            logoutConfirmOk.innerHTML = '<span class="logout-confirm-spinner" aria-hidden="true"></span><span>Logging out...</span>';
        }
        try {
            if (typeof pinnedHeads !== 'undefined') pinnedHeads = [];
            if (typeof closedHeadIds !== 'undefined' && closedHeadIds.clear) closedHeadIds.clear();
            if (typeof keepHeadsPinned !== 'undefined') keepHeadsPinned = false;
            if (typeof clearPinnedHeadsStorage === 'function') clearPinnedHeadsStorage();
            if (typeof hideChatHeads === 'function') hideChatHeads();
        } catch (e) { /* chat heads not available on this page */ }
        var separator = pendingLogoutUrl.indexOf('?') >= 0 ? '&' : '?';
        window.location.replace(pendingLogoutUrl + separator + 't=' + Date.now());
    }

    var logoutLinks = document.querySelectorAll('.logout-link');
    if (logoutLinks && logoutLinks.length) {
        logoutLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                openLogoutConfirm(link.getAttribute('href'));
            });
        });
    }

    if (logoutConfirmBackdrop) logoutConfirmBackdrop.addEventListener('click', closeLogoutConfirm);
    if (logoutConfirmCancel) logoutConfirmCancel.addEventListener('click', closeLogoutConfirm);
    if (logoutConfirmOk) logoutConfirmOk.addEventListener('click', doConfirmedLogout);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && logoutConfirmModal && logoutConfirmModal.classList.contains('open')) {
            closeLogoutConfirm();
        }
    });

});
</script>

</body>

</html>
