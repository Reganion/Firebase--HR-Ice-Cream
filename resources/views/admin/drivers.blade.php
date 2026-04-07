@extends('admin.layout.layout')

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="icon" href="{{ asset('img/logo.png') }}">
    @section('title', 'Driver Management')
    <link rel="stylesheet" href="{{ asset('assets/css/Admin/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/Admin/driver.css') }}">
    <style>
        button.is-loading {
            pointer-events: none !important;
            cursor: not-allowed !important;
            opacity: 0.78 !important;
            transition: all 0.2s ease;
        }

        button.is-loading .btn-loading-wrap {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        button.is-loading .btn-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            display: inline-block;
            animation: btn-spin 0.7s linear infinite;
            vertical-align: middle;
        }

        @keyframes btn-spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>

</head>

<body>
    @section('content')
        @include('admin.partials.alert')
        <div class="content-area drivers-page">

            <!-- HEADER -->
            <div class="driver-header">

                <!-- LEFT SIDE -->
                <div class="driver-left">
                    <h2>Driver list</h2>

                    @php
                        $statusFilterMap = ['available' => 'available', 'on_route' => 'on', 'off_duty' => 'off', 'deactivate' => 'deactivate'];
                        $driverCountAll = $drivers->count();
                        $driverCountAvailable = $drivers->where('status', 'available')->count();
                        $driverCountOnRoute = $drivers->where('status', 'on_route')->count();
                        $driverCountOffDuty = $drivers->where('status', 'off_duty')->count();
                        $driverCountInactive = $drivers->where('status', 'deactivate')->count();
                    @endphp
                    <div class="driver-tabs">
                        <button class="active" data-filter="all">All ({{ $driverCountAll }})</button>
                        <button data-filter="available">Available ({{ $driverCountAvailable }})</button>
                        <button data-filter="on">On Route ({{ $driverCountOnRoute }})</button>
                        <button data-filter="off">Off Duty ({{ $driverCountOffDuty }})</button>
                        <button data-filter="deactivate">Inactive ({{ $driverCountInactive }})</button>
                    </div>

                </div>

                <!-- RIGHT SIDE -->
                <div class="driver-actions">
                    <div class="driver-search">
                        <span class="material-symbols-outlined">search</span>
                        <input type="text" placeholder="Search by driver name">
                    </div>

                    <button class="btn-add" id="addDriverBtn">
                        <span class="material-symbols-outlined">add</span> Add New Driver
                    </button>

                    <a href="{{ route('admin.archive') }}" class="btn-archive" title="Archive">
                        <span class="material-symbols-outlined">archive</span>
                        Archive
                    </a>
                </div>

            </div>


            <!-- PAGE BODY -->
            <div class="drivers-body">

                <!-- SCROLLABLE GRID -->
                <div class="drivers-scroll">
                    <div class="driver-grid">
                        @php
                            $statusMap = ['available' => 'available', 'on_route' => 'on', 'off_duty' => 'off', 'deactivate' => 'deactivate'];
                            $statusLabel = ['available' => 'Available', 'on_route' => 'On Route', 'off_duty' => 'Off Duty', 'deactivate' => 'Inactive'];
                        @endphp
                        @forelse ($drivers as $driver)
                            @php
                                $driverStatus = $driver->status ?? 'available';
                                $filterValue = $statusMap[$driverStatus] ?? 'available';
                                $labelValue = $statusLabel[$driverStatus] ?? 'Available';
                                $driverCode = $driver->driver_code ?? 'DRV' . str_pad((string)$driver->id, 3, '0', STR_PAD_LEFT);
                            @endphp
                            <div class="driver-card" data-status="{{ $filterValue }}">
                                <div class="card-actions">
                                    <button type="button" class="card-archive-btn" title="Archive" aria-label="Archive driver" data-driver-id="{{ $driver->id }}" data-driver-name="{{ $driver->name }}"><span class="material-symbols-outlined">archive</span></button>
                                    <button type="button" class="card-inactive-btn {{ $driverStatus === 'deactivate' ? 'is-inactive' : '' }}" title="{{ $driverStatus === 'deactivate' ? 'Activate' : 'Set inactive' }}" aria-label="{{ $driverStatus === 'deactivate' ? 'Activate driver' : 'Set driver inactive' }}" data-driver-id="{{ $driver->id }}" data-driver-name="{{ $driver->name }}" data-driver-status="{{ $driverStatus }}"><span class="material-symbols-outlined">do_not_disturb</span></button>
                                </div>
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
                                    <span class="status {{ $filterValue }}">{{ $labelValue }}</span>
                                </div>
                            </div>
                        @empty
                            <div style="grid-column:1/-1;text-align:center;padding:40px;color:#666;">
                                No drivers yet. Click "Add New Driver" to add one.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- PAGINATION (STICKS BOTTOM) -->
                <div class="pagination-wrapper">
                    <button class="page-btn prev" disabled>
                        <span class="material-symbols-outlined">arrow_left_alt</span>
                        Previous
                    </button>
                    <div class="page-numbers" id="pageNumbers"></div>
                    <button class="page-btn next">
                        Next
                        <span class="material-symbols-outlined">arrow_right_alt</span>
                    </button>
                </div>

            </div>

        </div>

        <!-- ADD NEW DRIVER MODAL -->
        <div class="modal-overlay" id="addDriverModal">
            <form class="modal-card modal-driver" action="{{ route('admin.drivers.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h3>Add New Driver</h3>
                    <button type="button" class="modal-close" id="closeAddDriverModal">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="upload-box">
                        <div class="upload-preview" id="driverImagePreview">
                            <span class="material-symbols-outlined">person</span>
                        </div>
                        <div>
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Profile Picture</label>
                            </div>
                            <input type="file" id="driverImage" name="image" accept="image/*" hidden>
                            <button type="button" class="btn-upload" onclick="document.getElementById('driverImage').click()">Upload</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Driver Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" placeholder="Enter Name" value="{{ old('name') }}" maxlength="255" class="{{ $errors->has('name') ? 'invalid' : '' }}">
                        @error('name')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone Number <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="phone"
                                id="driverPhoneInput"
                                placeholder="Enter Phone Number"
                                value="{{ old('phone') }}"
                                maxlength="11"
                                class="{{ $errors->has('phone') ? 'invalid' : '' }}"
                            >
                            @error('phone')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" placeholder="Enter Email" value="{{ old('email') }}" maxlength="255" class="{{ $errors->has('email') ? 'invalid' : '' }}">
                            @error('email')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>License No. <span class="text-danger">*</span></label>
                            <input type="text" name="license_no" placeholder="Enter License No." value="{{ old('license_no') }}" maxlength="100" class="{{ $errors->has('license_no') ? 'invalid' : '' }}">
                            @error('license_no')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>License Type <span class="text-danger">*</span></label>
                            <div class="custom-select {{ $errors->has('license_type') ? 'invalid' : '' }}" id="licenseTypeSelect">
                                <div class="select-trigger">
                                    <span class="selected-text">Select Type</span>
                                    <span class="material-symbols-outlined">expand_more</span>
                                </div>
                                <div class="select-options">
                                    <div class="option" data-value="Professional">Professional</div>
                                    <div class="option" data-value="Non-Professional">Non-Professional</div>
                                </div>
                                <input type="hidden" name="license_type" id="licenseTypeInput" value="{{ old('license_type') }}">
                            </div>
                            @error('license_type')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="cancelAddDriver">Cancel</button>
                    <button type="submit" class="btn-save">Add Driver</button>
                </div>
            </form>
        </div>

        <div class="delete-overlay" id="statusDriverConfirmModal">
            <form class="delete-modal" method="POST" id="statusDriverForm">
                @csrf

                <h3 class="delete-title">
                    Are you sure you want to update
                    <span id="statusDriverName">this driver</span>?
                </h3>

                <p class="delete-text" id="statusDriverText">This action will update the driver status.</p>

                <div class="delete-actions">
                    <button type="button" class="btn-delete-cancel" id="cancelStatusDriverAction">
                        No, Cancel
                    </button>

                    <button type="submit" class="btn-delete-confirm" id="confirmStatusDriverAction">
                        Yes, Continue
                    </button>
                </div>
            </form>
        </div>

        </div>
    @endsection
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            function setButtonLoadingState(button, isLoading, loadingText) {
                if (!button) return;

                if (isLoading) {
                    if (button.classList.contains("is-loading")) return;
                    button.dataset.originalHtml = button.innerHTML;
                    button.classList.add("is-loading");
                    button.disabled = true;
                    button.setAttribute("aria-busy", "true");
                    const text = loadingText || button.dataset.loadingText || "Processing...";
                    button.innerHTML =
                        '<span class="btn-loading-wrap"><span class="btn-spinner" aria-hidden="true"></span><span>' +
                        text + "</span></span>";
                    return;
                }

                button.classList.remove("is-loading");
                button.disabled = false;
                button.removeAttribute("aria-busy");
                if (button.dataset.originalHtml) {
                    button.innerHTML = button.dataset.originalHtml;
                    delete button.dataset.originalHtml;
                }
            }

            const pendingAlertMessage = sessionStorage.getItem("driverPendingAlertMessage");
            const pendingAlertType = sessionStorage.getItem("driverPendingAlertType");
            if (
                pendingAlertMessage &&
                typeof window.showGlobalAlert === "function" &&
                !document.getElementById("globalAlert")
            ) {
                window.showGlobalAlert(pendingAlertMessage, pendingAlertType || "success");
            }
            sessionStorage.removeItem("driverPendingAlertMessage");
            sessionStorage.removeItem("driverPendingAlertType");

            const allCards = Array.from(document.querySelectorAll(".driver-card"));
            const tabs = document.querySelectorAll(".driver-tabs button");
            const searchInput = document.querySelector(".driver-search input");

            const pageNumbers = document.getElementById("pageNumbers");
            const prevBtn = document.querySelector(".page-btn.prev");
            const nextBtn = document.querySelector(".page-btn.next");

            const itemsPerPage = 10;
            let currentPage = 1;
            let activeFilter = "all";
            let filteredCards = [...allCards];

            function applyEmailOverflowTooltip() {
                const emailEls = document.querySelectorAll(".driver-email");
                emailEls.forEach((el) => {
                    // Add tooltip only when text is visually truncated.
                    if (el.scrollWidth > el.clientWidth) {
                        el.title = el.textContent.trim();
                    } else {
                        el.removeAttribute("title");
                    }
                });
            }

            /* =========================
               APPLY FILTERS (TAB + SEARCH)
            ========================= */
            function applyFilters() {
                const searchValue = searchInput.value.toLowerCase();

                filteredCards = allCards.filter(card => {
                    const statusMatch =
                        activeFilter === "all" ||
                        card.dataset.status === activeFilter;

                    const name = card.querySelector("h4").textContent.toLowerCase();
                    const searchMatch = name.includes(searchValue);

                    return statusMatch && searchMatch;
                });

                currentPage = 1;
                renderPage();
            }

            /* =========================
               TAB FILTER
            ========================= */
            tabs.forEach(tab => {
                tab.addEventListener("click", () => {
                    tabs.forEach(t => t.classList.remove("active"));
                    tab.classList.add("active");

                    activeFilter = tab.dataset.filter;
                    applyFilters();
                });
            });

            /* =========================
               SEARCH
            ========================= */
            searchInput.addEventListener("input", applyFilters);

            /* =========================
               PAGINATION
            ========================= */
            function renderPage() {
                const start = (currentPage - 1) * itemsPerPage;
                const end = start + itemsPerPage;

                allCards.forEach(card => card.style.display = "none");

                filteredCards.slice(start, end).forEach(card => {
                    card.style.display = "block";
                });

                renderPagination();
                applyEmailOverflowTooltip();
            }

            function renderPagination() {
                pageNumbers.innerHTML = "";
                const totalPages = Math.ceil(filteredCards.length / itemsPerPage);

                for (let i = 1; i <= totalPages; i++) {
                    const span = document.createElement("span");
                    span.textContent = i;
                    if (i === currentPage) span.classList.add("active");

                    span.addEventListener("click", () => {
                        currentPage = i;
                        renderPage();
                    });

                    pageNumbers.appendChild(span);
                }

                prevBtn.disabled = currentPage === 1;
                nextBtn.disabled = currentPage === totalPages || totalPages === 0;
            }

            prevBtn.addEventListener("click", () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderPage();
                }
            });

            nextBtn.addEventListener("click", () => {
                const totalPages = Math.ceil(filteredCards.length / itemsPerPage);
                if (currentPage < totalPages) {
                    currentPage++;
                    renderPage();
                }
            });

            renderPage();
            window.addEventListener("resize", applyEmailOverflowTooltip);

            /* ===================== ADD DRIVER MODAL ===================== */
            const addDriverModal = document.getElementById("addDriverModal");
            const addDriverForm = addDriverModal?.querySelector("form");

            addDriverForm?.addEventListener("submit", (e) => {
                const submitBtn = addDriverForm.querySelector("button[type='submit']");
                if (submitBtn && submitBtn.classList.contains("is-loading")) {
                    e.preventDefault();
                    return;
                }
                setButtonLoadingState(submitBtn, true, "Adding...");
                sessionStorage.setItem("driverPendingAlertMessage", "Driver added successfully");
                sessionStorage.setItem("driverPendingAlertType", "success");
            });

            const driverPhoneInput = document.getElementById("driverPhoneInput");
            if (driverPhoneInput) {
                driverPhoneInput.addEventListener("input", () => {
                    // Keep only digits and cap at 11 characters
                    driverPhoneInput.value = driverPhoneInput.value.replace(/[^0-9]/g, "").slice(0, 11);
                });
            }

            function closeDriverModalSmooth() {
                const card = addDriverModal.querySelector(".modal-card");
                card.style.transform = "translateX(120%)";
                card.style.opacity = "0";
                setTimeout(() => {
                    addDriverModal.classList.remove("show");
                    card.style.transform = "";
                    card.style.opacity = "";
                }, 300);
            }

            document.getElementById("addDriverBtn").onclick = () => {
                addDriverModal.classList.add("show");
            };

            document.getElementById("closeAddDriverModal").onclick = closeDriverModalSmooth;
            document.getElementById("cancelAddDriver").onclick = () => {
                setButtonLoadingState(addDriverForm?.querySelector("button[type='submit']"), false);
                closeDriverModalSmooth();
            };

            addDriverModal.onclick = (e) => {
                if (e.target === addDriverModal) {
                    setButtonLoadingState(addDriverForm?.querySelector("button[type='submit']"), false);
                    closeDriverModalSmooth();
                }
            };

            document.getElementById("driverImage").addEventListener("change", function() {
                const file = this.files[0];
                const preview = document.getElementById("driverImagePreview");
                if (!file) return;
                const reader = new FileReader();
                reader.onload = () => {
                    preview.innerHTML = `<img src="${reader.result}" alt="Preview">`;
                };
                reader.readAsDataURL(file);
            });

            const licenseSelect = document.getElementById("licenseTypeSelect");
            const licenseTrigger = licenseSelect.querySelector(".select-trigger");
            const licenseSelected = licenseSelect.querySelector(".selected-text");
            const licenseInput = document.getElementById("licenseTypeInput");
            licenseSelect.querySelectorAll(".option").forEach(opt => {
                opt.onclick = () => {
                    licenseInput.value = opt.dataset.value;
                    licenseSelected.textContent = opt.textContent;
                    licenseSelect.classList.remove("open");
                };
            });
            licenseTrigger.onclick = (e) => {
                e.preventDefault();
                document.querySelectorAll(".modal-driver .custom-select").forEach(s => {
                    if (s !== licenseSelect) s.classList.remove("open");
                });
                licenseSelect.classList.toggle("open");
            };
            document.addEventListener("click", (e) => {
                if (!licenseSelect.contains(e.target)) licenseSelect.classList.remove("open");
            });

            const statusDriverModal = document.getElementById("statusDriverConfirmModal");
            const statusDriverName = document.getElementById("statusDriverName");
            const statusDriverText = document.getElementById("statusDriverText");
            const statusDriverForm = document.getElementById("statusDriverForm");
            const cancelStatusDriverAction = document.getElementById("cancelStatusDriverAction");
            const confirmStatusDriverAction = document.getElementById("confirmStatusDriverAction");

            function openStatusDriverModal(driverId, driverName, actionType) {
                statusDriverName.textContent = driverName || "this driver";
                if (actionType === "archive") {
                    statusDriverForm.action = `/admin/drivers/${driverId}/archive`;
                } else if (actionType === "activate") {
                    statusDriverForm.action = `/admin/drivers/${driverId}/activate`;
                } else {
                    statusDriverForm.action = `/admin/drivers/${driverId}/inactive`;
                }

                if (actionType === "archive") {
                    statusDriverText.textContent = "If you archive this driver, it will be moved to the Archive page.";
                    confirmStatusDriverAction.textContent = "Yes, Archive";
                } else if (actionType === "activate") {
                    statusDriverText.textContent = "This inactive driver will be activated and can receive assignments again.";
                    confirmStatusDriverAction.textContent = "Yes, Activate";
                } else {
                    statusDriverText.textContent = "If you set this driver to inactive, it will appear under the Inactive filter.";
                    confirmStatusDriverAction.textContent = "Yes, Set Inactive";
                }

                statusDriverForm.dataset.actionType = actionType;
                statusDriverModal.classList.add("show");
            }

            statusDriverForm?.addEventListener("submit", (e) => {
                if (confirmStatusDriverAction.classList.contains("is-loading")) {
                    e.preventDefault();
                    return;
                }
                const actionType = statusDriverForm.dataset.actionType;
                const loadingText = actionType === "archive" ? "Archiving..." : (actionType === "activate" ? "Activating..." : "Updating...");
                setButtonLoadingState(confirmStatusDriverAction, true, loadingText);
                if (actionType === "archive") {
                    sessionStorage.setItem("driverPendingAlertMessage", "Driver archived successfully");
                } else if (actionType === "activate") {
                    sessionStorage.setItem("driverPendingAlertMessage", "Driver activated successfully");
                } else {
                    sessionStorage.setItem("driverPendingAlertMessage", "Driver set to inactive");
                }
                sessionStorage.setItem("driverPendingAlertType", "success");
            });

            document.querySelectorAll(".card-inactive-btn").forEach((btn) => {
                btn.addEventListener("click", () => {
                    const driverId = btn.dataset.driverId;
                    const driverName = btn.dataset.driverName || "this driver";
                    const driverStatus = btn.dataset.driverStatus || "";
                    const actionType = driverStatus === "deactivate" ? "activate" : "inactive";
                    openStatusDriverModal(driverId, driverName, actionType);
                });
            });

            document.querySelectorAll(".card-archive-btn").forEach((btn) => {
                btn.addEventListener("click", () => {
                    const driverId = btn.dataset.driverId;
                    const driverName = btn.dataset.driverName || "this driver";
                    openStatusDriverModal(driverId, driverName, "archive");
                });
            });

            cancelStatusDriverAction.addEventListener("click", () => {
                setButtonLoadingState(confirmStatusDriverAction, false);
                statusDriverModal.classList.remove("show");
            });

            statusDriverModal.addEventListener("click", (e) => {
                if (e.target === statusDriverModal) {
                    setButtonLoadingState(confirmStatusDriverAction, false);
                    statusDriverModal.classList.remove("show");
                }
            });
        });

        document.addEventListener("DOMContentLoaded", () => {
            const alert = document.getElementById("globalAlert");
            if (!alert) return;
            setTimeout(() => {
                alert.style.transition = "opacity 0.3s ease, transform 0.3s ease";
                alert.style.opacity = "0";
                alert.style.transform = "translateY(10px)";
                setTimeout(() => alert.remove(), 300);
            }, 3000);
        });
    </script>


</body>

</html>
