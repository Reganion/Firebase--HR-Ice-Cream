@extends('admin.layout.layout')

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="icon" href="{{ asset('img/logo.png') }}">
    @section('title', 'Order Management')
    <link rel="stylesheet" href="{{ asset('assets/css/Admin/order.css') }}">
    {{-- Firebase loaded once in admin layout; use window.FIREBASE_DATABASE_URL --}}
    <style>
        button.is-loading {
            pointer-events: none !important;
            cursor: not-allowed !important;
            opacity: 0.75 !important;
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
        <div class="content-area orders-page">

            <!-- HEADER -->
            <div class="orders-header">
                <h2>Order list</h2>

                <div class="orders-actions">
                    <div class="orders-search">
                        <span class="material-symbols-outlined">search</span>
                        <input type="text" id="searchInput" placeholder="Search by product or customer name">
                    </div>

                    <div class="filter-wrapper">
                        <button class="btn-filter" id="filterBtn">
                            <span class="material-symbols-outlined">tune</span>
                            Filter
                        </button>

                        <div class="filter-dropdown" id="filterDropdown">
                            <label><input type="checkbox" value="pending"> Pending</label>
                            <label><input type="checkbox" value="preparing"> Preparing</label>
                            <label><input type="checkbox" value="assigned"> Assigned</label>
                            <label><input type="checkbox" value="ready"> Ready</label>
                            <label><input type="checkbox" value="out_for_delivery"> Out for Delivery</label>
                            <label><input type="checkbox" value="completed"> Completed</label>
                            <label><input type="checkbox" value="cancelled"> Cancelled</label>
                            <label><input type="checkbox" value="walk_in"> Walk-In</label>
                        </div>
                    </div>

                    <button class="btn-add" id="addOrderBtn">
                        <span class="material-symbols-outlined">add</span>
                        New Order
                    </button>
                </div>
            </div>


            <!-- TABLE WRAPPER -->
            <div class="orders-table">

                <div class="table-header">
                    <table class="orders-data orders-data-head">
                        <colgroup>
                            <col style="width: 18%;">
                            <col style="width: 22%;">
                            <col style="width: 20%;">
                            <col style="width: 22%;">
                            <col style="width: 12%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Delivery Schedule</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>

                <!-- SCROLL AREA (BODY ONLY) -->
                <div class="table-scroll">
                    <table class="orders-data orders-data-body">
                        <colgroup>
                            <col style="width: 18%;">
                            <col style="width: 22%;">
                            <col style="width: 20%;">
                            <col style="width: 22%;">
                            <col style="width: 12%;">
                        </colgroup>
                        <tbody id="orders-tbody">
                            @forelse ($orders as $order)
                                @php
                                    $statusRaw = trim((string) ($order->status ?? ''));
                                    $statusLower = strtolower($statusRaw);
                                    $statusKey = match ($statusLower) {
                                        'walk-in', 'walk_in', 'walk in' => 'walk_in',
                                        'preparing' => 'preparing',
                                        'assigned' => 'assigned',
                                        'ready' => 'ready',
                                        'out for delivery', 'out_for_delivery' => 'out_for_delivery',
                                        'completed', 'delivered' => 'completed',
                                        'cancelled' => 'cancelled',
                                        default => 'pending',
                                    };
                                @endphp
                                <tr data-order-id="{{ $order->id }}"
                                    data-product-name="{{ e($order->product_name ?? '') }}"
                                    data-product-type="{{ e($order->product_type ?? '') }}"
                                    data-product-image="{{ asset((!empty($order->product_image) && file_exists(public_path($order->product_image))) ? $order->product_image : 'img/default-product.png') }}"
                                    data-gallon-size="{{ e($order->gallon_size ?? '') }}"
                                    data-transaction-id="{{ e($order->transaction_id ?? '') }}"
                                    data-customer-name="{{ e($order->customer_name ?? '') }}"
                                    data-customer-phone="{{ e($order->customer_phone ?? '') }}"
                                    data-customer-email="{{ e($order->customer_email ?? '') }}"
                                    data-customer-image="{{ asset($order->customer_image ?? 'img/default-user.png') }}"
                                    data-delivery-date="{{ $order->delivery_date ? \Carbon\Carbon::parse($order->delivery_date)->format('Y-m-d') : '' }}"
                                    data-delivery-time="{{ $order->delivery_time ? \Carbon\Carbon::parse($order->delivery_time)->format('H:i') : '' }}"
                                    data-delivery-address="{{ e($order->delivery_address ?? '') }}"
                                    data-amount="{{ $order->amount ?? '' }}"
                                    data-downpayment="{{ $order->downpayment ?? '' }}"
                                    data-quantity="{{ (int) ($order->qty ?? 1) }}"
                                    data-payment-method="{{ e($order->payment_method ?? '') }}"
                                    data-status="{{ e($order->status ?? '') }}" data-status-key="{{ $statusKey }}"
                                    data-status-driver="{{ strtolower((string) ($order->status_driver ?? 'pending')) }}"
                                    data-driver-id="{{ $order->driver_id ?? '' }}"
                                    data-driver-name="{{ e($order->driver_name ?? '') }}"
                                    data-driver-phone="{{ e($order->driver_phone ?? '') }}"
                                    data-driver-image-url="{{ !empty($order->driver_image_url) ? $order->driver_image_url : '' }}">
                                    <!-- TRANSACTION -->
                                    <td>
                                        <strong>#{{ $order->transaction_id }}</strong>
                                        <small>{{ $order->created_at ? \Carbon\Carbon::parse($order->created_at)->format('d M Y') : '—' }}</small>
                                    </td>

                                    <!-- DELIVERY -->
                                    <td class="delivery-schedule-cell">
                                        <strong>{{ \Carbon\Carbon::parse($order->delivery_date)->format('d M') }},
                                            {{ \Carbon\Carbon::parse($order->delivery_time)->format('h:i A') }}</strong>
                                        <small class="delivery-address">{{ $order->delivery_address }}</small>
                                    </td>

                                    <!-- AMOUNT -->
                                    <td>
                                        <strong>₱{{ number_format($order->amount, 2) }}</strong>
                                        <small>{{ $order->payment_method }}</small>
                                    </td>

                                    <!-- STATUS -->
                                    <td>
                                        <span class="status-badge {{ $statusKey }}">
                                            ● {{ $statusRaw !== '' ? $statusRaw : 'Pending' }}
                                        </span>
                                    </td>

                                    <!-- ACTION -->
                                    <td>
                                        <div style="display: flex; gap: 6px; align-items: center;">


                                            {{-- WALK-IN → ASSIGN + EDIT --}}
                                            @if ($statusKey === 'walk_in')
                                                <button type="button" class="action-btn assign" title="Assign driver">
                                                    <span class="material-symbols-outlined">person_check</span>
                                                </button>
                                                <button type="button" class="action-btn edit-order" title="Edit order">
                                                    <span class="material-symbols-outlined">edit</span>
                                                </button>

                                                {{-- ASSIGNED → REASSIGN + EDIT --}}
                                            @elseif ($statusKey === 'assigned')
                                                <button type="button" class="action-btn reassign" title="Re-assign driver">
                                                    <span class="material-symbols-outlined">person_edit</span>
                                                </button>
                                                <button type="button" class="action-btn edit-order" title="Edit order">
                                                    <span class="material-symbols-outlined">edit</span>
                                                </button>

                                                {{-- READY → VIEW ONLY (no reassign/edit) --}}
                                            @elseif ($statusKey === 'ready')
                                                {{-- Only view button below --}}

                                                {{-- PREPARING → EDIT ONLY (change status to Ready) --}}
                                            @elseif ($statusKey === 'preparing')
                                                <button type="button" class="action-btn edit-order" title="Edit order">
                                                    <span class="material-symbols-outlined">edit</span>
                                                </button>

                                                {{-- PENDING ONLY → ASSIGN + EDIT --}}
                                            @elseif ($statusKey === 'pending')
                                                <button type="button" class="action-btn assign" title="Assign driver">
                                                    <span class="material-symbols-outlined">person_check</span>
                                                </button>
                                                <button type="button" class="action-btn edit-order" title="Edit order">
                                                    <span class="material-symbols-outlined">edit</span>
                                                </button>
                                            @endif

                                            {{-- ALWAYS VIEW --}}
                                            <button type="button" class="action-btn view-order" title="View order details">
                                                <span class="material-symbols-outlined">visibility</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:30px;">
                                        No orders found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    </table>
                </div>
                <div id="showingText" class="showing-text" style="display:none;"></div>

                <!-- PAGINATION -->
                <div class="pagination-wrapper orders-pagination" id="ordersPagination">
                    <button type="button" class="nav-btn" id="prevBtn" aria-label="Previous page">
                        <span class="material-symbols-outlined">arrow_left_alt</span>
                        Previous
                    </button>
                    <div id="pageNumbers" class="pagination-numbers" role="navigation" aria-label="Page numbers"></div>
                    <button type="button" class="nav-btn" id="nextBtn" aria-label="Next page">
                        Next
                        <span class="material-symbols-outlined">arrow_right_alt</span>
                    </button>
                </div>

            </div>
        </div>
    @endsection
    <!-- ASSIGN / RE-ASSIGN DRIVER MODAL -->
    <div class="assign-modal" id="assignModal" data-order-id="">
        <div class="assign-card">

            <!-- HEADER -->
            <div class="assign-header">
                <h3 id="assignModalTitle">Assign Driver</h3>
                <button class="close-assign" id="closeAssign" type="button">&times;</button>
            </div>

            <!-- ORDER INFO -->
            <div class="assign-order">
                <div class="order-left">
                    <img id="assignProductImage" src="{{ asset('img/gallon.png') }}" alt="">
                    <div>
                        <strong id="assignProduct">—</strong>
                        <small id="assignSchedule">—</small>
                        <small id="assignTxn">—</small>
                    </div>
                </div>

                <div class="order-right">
                    <strong id="assignCustomer">—</strong>
                    <small id="assignPhone">—</small>
                    <small id="assignAddress">—</small>
                </div>
            </div>

            <!-- CURRENT ASSIGNED DRIVER (for Re-Assign only) -->
            <div class="assign-section" id="assignCurrentDriverSection" style="display: none;">
                <span class="available-badge" style="background: #e3f2fd; color: #1976d2;">Assigned Driver</span>
                <div class="driver-list" style="margin-top: 8px;">
                    <div class="driver-item" style="cursor: default;">
                        <div class="driver-info">
                            <img id="assignCurrentDriverImage" src="{{ asset('img/default-user.png') }}"
                                alt="">
                            <div>
                                <strong id="assignCurrentDriverName">—</strong>
                                <small id="assignCurrentDriverPhone">—</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AVAILABLE DRIVERS -->
            <div class="assign-section">
                <span class="available-badge">Available driver</span>
                <div class="driver-list" id="assignDriverList">
                    <div class="driver-list-loading" id="assignDriverListLoading">Loading drivers…</div>
                    <div class="driver-list-items" id="assignDriverListItems"></div>
                </div>
            </div>

        </div>
    </div>
    <!-- ADD ORDER MODAL -->
    <div class="add-modal" id="addModal">
        <div class="add-card">

            <!-- HEADER -->
            <div class="assign-header">
                <h3>Add New Order</h3>
                <button class="close-assign" id="closeAdd">&times;</button>
            </div>

            <!-- FORM -->
            <div class="assign-section">
                <form method="POST" action="{{ route('admin.orders.walkin') }}" id="addOrderForm">
                    @csrf

                    <div class="order-form-container">

                        <!-- CUSTOMER DETAILS SECTION -->
                        <div class="form-section">
                            <h4 class="section-heading">Customer Details</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" name="customer_name" placeholder="Full Name" required
                                        class="form-input">
                                </div>
                                <div class="form-group">
                                    <label>Contact Number</label>
                                    <input type="text" name="customer_phone" placeholder="Contact Number" required
                                        class="form-input">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Delivery Address</label>
                                <textarea name="delivery_address" placeholder="Delivery Address" required class="form-input"></textarea>
                            </div>
                        </div>

                        <!-- ORDER DETAILS SECTION -->
                        <div class="form-section">
                            <h4 class="section-heading">Order Details</h4>
                            <div class="form-group">
                                <label>Flavor</label>
                                <div class="custom-select" id="flavorSelect">
                                    <div class="select-trigger">
                                        <span class="selected-text">Select Flavor</span>
                                        <span class="material-symbols-outlined">expand_more</span>
                                    </div>
                                    <div class="select-options">
                                        @foreach ($flavors as $flavor)
                                            <div class="option" data-value="{{ $flavor->name }}"
                                                data-category="{{ $flavor->category }}"
                                                data-price="{{ $flavor->price }}">
                                                {{ $flavor->name }}
                                            </div>
                                        @endforeach
                                    </div>
                                    <input type="hidden" name="product_name" id="selectedFlavor" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Flavor Type</label>
                                    <input type="text" class="form-input" id="flavorTypeDisplay"
                                        placeholder="Select Flavor first" readonly>
                                    <input type="hidden" name="product_type" id="flavorTypeInput" required>
                                </div>
                                <div class="form-group">
                                    <label>Gallon</label>
                                    <div class="custom-select" id="gallonSelect">
                                        <div class="select-trigger">
                                            <span class="selected-text">Select Size</span>
                                            <span class="material-symbols-outlined">expand_more</span>
                                        </div>
                                        <div class="select-options">
                                            @foreach ($gallons as $gallon)
                                                <div class="option" data-value="{{ $gallon->size }}"
                                                    data-price="{{ $gallon->addon_price }}">
                                                    {{ $gallon->size }}
                                                </div>
                                            @endforeach
                                        </div>
                                        <input type="hidden" name="gallon_size" id="selectedGallon" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Delivery Date</label>
                                    <input type="date" name="delivery_date" required class="form-input">
                                </div>
                                <div class="form-group">
                                    <label>Delivery Time</label>
                                    <input type="time" name="delivery_time" placeholder="---" required
                                        class="form-input">
                                </div>
                            </div>
                        </div>

                        <!-- PAYMENT SECTION -->
                        <div class="form-section">
                            <h4 class="section-heading">Payment</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Flavor cost</label>
                                    <input type="text" class="form-input" id="flavorCostDisplay"
                                        placeholder="Select Flavor first" readonly>
                                    <input type="hidden" id="flavorCost" value="0">
                                </div>
                                <div class="form-group">
                                    <label>Gallon cost</label>
                                    <input type="text" class="form-input" id="gallonCostDisplay"
                                        placeholder="Select Gallon first" readonly>
                                    <input type="hidden" id="gallonCost" value="0">
                                </div>
                            </div>
                            <div class="form-row" id="paymentFieldsRow">
                                <div class="form-group">
                                    <label>Delivery Fee</label>
                                    <div class="custom-select" id="deliveryFeeSelect">
                                        <div class="select-trigger" id="deliveryFeeTrigger">
                                            <span class="selected-text">Select fee</span>
                                            <span class="material-symbols-outlined">expand_more</span>
                                        </div>
                                        <div class="select-options">
                                            <div class="option" data-value="0">₱0.00</div>
                                            <div class="option" data-value="100">₱100.00</div>
                                            <div class="option" data-value="150">₱150.00</div>
                                            <div class="option" data-value="200">₱200.00</div>
                                        </div>
                                        <input type="hidden" id="deliveryFee" value="0">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Total cost</label>
                                    <input type="text" class="form-input" id="totalCostDisplay" placeholder="---"
                                        readonly>
                                    <input type="hidden" name="amount" id="totalCost" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Payment Method</label>
                                <div class="custom-select" id="paymentMethodSelect">
                                    <div class="select-trigger">
                                        <span class="selected-text">Payment Method</span>
                                        <span class="material-symbols-outlined">expand_more</span>
                                    </div>
                                    <div class="select-options">
                                        <div class="option" data-value="Cash of Delivery">Cash of Delivery</div>
                                        <div class="option" data-value="GCash">GCash</div>
                                    </div>
                                    <input type="hidden" name="payment_method" id="paymentMethod" required>
                                </div>
                            </div>
                        </div>

                        <!-- STATUS -->
                        <input type="hidden" name="status" value="Walk-in">

                        <button type="submit" class="assign-driver-btn" style="width:100%;">
                            Add Order
                        </button>

                    </div>
                </form>
            </div>

        </div>
    </div>

    <!-- EDIT WALK-IN ORDER MODAL -->
    <div class="add-modal" id="editModal">
        <div class="add-card">
            <div class="assign-header">
                <h3>Edit Order</h3>
                <button type="button" class="close-assign" id="closeEdit">&times;</button>
            </div>
            <div class="assign-section">
                <form method="POST" id="editOrderForm" action="">
                    @csrf
                    @method('PUT')
                    <div class="order-form-container">

                        <!-- CUSTOMER DETAILS (same layout as Add New Order) -->
                        <div class="form-section">
                            <h4 class="section-heading">Customer Details</h4>
                            <div class="form-row">
                                <div class="form-group" data-full-edit-field>
                                    <label>Full Name</label>
                                    <input type="text" name="customer_name" id="editCustomerName"
                                        placeholder="Full Name" required class="form-input">
                                </div>
                                <div class="form-group" data-full-edit-field>
                                    <label>Contact Number</label>
                                    <input type="text" name="customer_phone" id="editCustomerPhone"
                                        placeholder="Contact Number" required class="form-input">
                                </div>
                            </div>
                            <div class="form-group" data-full-edit-field>
                                <label>Delivery Address</label>
                                <textarea name="delivery_address" id="editDeliveryAddress"
                                    placeholder="Delivery Address" required class="form-input"></textarea>
                            </div>
                        </div>

                        <!-- ORDER DETAILS -->
                        <div class="form-section">
                            <h4 class="section-heading">Order Details</h4>
                            <div class="form-group">
                                <label>Flavor</label>
                                <div class="custom-select" id="editFlavorSelect">
                                    <div class="select-trigger">
                                        <span class="selected-text" id="editProductNameText">Select Flavor</span>
                                        <span class="material-symbols-outlined">expand_more</span>
                                    </div>
                                    <div class="select-options">
                                        @foreach ($flavors as $flavor)
                                            <div class="option" data-value="{{ $flavor->name }}"
                                                data-category="{{ $flavor->category ?? '' }}"
                                                data-price="{{ $flavor->price ?? 0 }}">{{ $flavor->name }}</div>
                                        @endforeach
                                    </div>
                                    <input type="hidden" name="product_name" id="editProductName" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group" data-full-edit-field>
                                    <label>Flavor Type</label>
                                    <input type="text" class="form-input" id="editFlavorTypeDisplay"
                                        placeholder="Select Flavor first" readonly>
                                    <input type="hidden" name="product_type" id="editProductType" required>
                                </div>
                                <div class="form-group">
                                    <label>Gallon</label>
                                    <div class="custom-select" id="editGallonSelect">
                                        <div class="select-trigger">
                                            <span class="selected-text" id="editGallonSizeText">Select Size</span>
                                            <span class="material-symbols-outlined">expand_more</span>
                                        </div>
                                        <div class="select-options">
                                            @foreach ($gallons as $gallon)
                                                <div class="option" data-value="{{ $gallon->size }}"
                                                    data-price="{{ $gallon->addon_price ?? 0 }}">{{ $gallon->size }}
                                                </div>
                                            @endforeach
                                        </div>
                                        <input type="hidden" name="gallon_size" id="editGallonSize" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group" data-full-edit-field>
                                    <label>Delivery Date</label>
                                    <input type="date" name="delivery_date" id="editDeliveryDate" required
                                        class="form-input">
                                </div>
                                <div class="form-group" data-full-edit-field>
                                    <label>Delivery Time</label>
                                    <input type="time" name="delivery_time" id="editDeliveryTime" required
                                        class="form-input">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Quantity</label>
                                <input type="number" name="qty" id="editQty" min="1" required class="form-input">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" id="editStatus" class="form-input" required>
                                    <option value="pending">Pending</option>
                                    <option value="preparing">Preparing</option>
                                    <option value="assigned">Assigned</option>
                                    <option value="ready">Ready</option>
                                    <option value="out_for_delivery">Out for Delivery</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="walk_in">Walk-In</option>
                                </select>
                            </div>
                        </div>

                        <!-- PAYMENT (mirrors Add New Order pricing row) -->
                        <div class="form-section">
                            <h4 class="section-heading">Payment</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Flavor cost</label>
                                    <input type="text" id="editFlavorCostDisplay" class="form-input" readonly
                                        value="₱0.00" placeholder="Select Flavor first">
                                </div>
                                <div class="form-group">
                                    <label>Gallon cost</label>
                                    <input type="text" id="editGallonCostDisplay" class="form-input" readonly
                                        value="₱0.00" placeholder="Select Gallon first">
                                </div>
                            </div>
                            <div class="form-group" data-full-edit-field>
                                <label>Total cost</label>
                                <input type="number" name="amount" id="editAmount" placeholder="—" required
                                    class="form-input" step="0.01">
                            </div>
                            <div class="form-group" data-full-edit-field>
                                <label>Payment Method</label>
                                <div class="custom-select" id="editPaymentSelect">
                                    <div class="select-trigger">
                                        <span class="selected-text" id="editPaymentMethodText">Payment Method</span>
                                        <span class="material-symbols-outlined">expand_more</span>
                                    </div>
                                    <div class="select-options">
                                        <div class="option" data-value="Cash of Delivery">Cash of Delivery</div>
                                        <div class="option" data-value="GCash">GCash</div>
                                        <div class="option" data-value="Paymaya">Paymaya</div>
                                    </div>
                                    <input type="hidden" name="payment_method" id="editPaymentMethod" required>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="assign-driver-btn" style="width:100%;">Update Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ORDER DETAILS MODAL (slide-over on RIGHT for Orders page only) -->
    <div class="orders-view-modal orders-view-modal--orders" id="orderDetailsModal">
        <div class="orders-view-card">
            <!-- HEADER -->
            <div class="orders-view-header">
                <h3>Order Details</h3>
                <button class="orders-view-close" id="closeOrderDetails">&times;</button>
            </div>

            <!-- CONTENT -->
            <div class="orders-view-content">
                <!-- ORDER SUMMARY -->
                <div class="details-section-card">
                    <h4 class="details-section-title">Order Summary</h4>
                    <div class="details-row">
                        <div class="details-label">
                            <span class="material-symbols-outlined">check_circle</span>
                            Order Status
                        </div>
                        <div class="details-value">
                            <span class="status-badge-details" id="detailsStatus">—</span>
                        </div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">
                            <span class="material-symbols-outlined">local_shipping</span>
                            Assigned Driver
                        </div>
                        <div class="details-value" id="detailsAssignedDriver">—</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">
                            <span class="material-symbols-outlined">sell</span>
                            Transaction Number
                        </div>
                        <div class="details-value" id="detailsTransactionId">—</div>
                    </div>
                </div>

                <!-- CUSTOMER INFO -->
                <div class="details-section-card">
                    <h4 class="details-section-title">Customer Info</h4>
                    <div class="customer-profile">
                        <img id="detailsCustomerImage" src="{{ asset('img/default-user.png') }}" alt="Customer"
                            class="customer-avatar">
                        <div class="customer-info">
                            <strong id="detailsCustomerName">—</strong>
                            <small id="detailsCustomerEmail">—</small>
                        </div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Phone Number</div>
                        <div class="details-value" id="detailsCustomerPhone">—</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Delivery Address</div>
                        <div class="details-value" id="detailsDeliveryAddress">—</div>
                    </div>
                </div>

                <!-- ITEMS -->
                <div class="details-section-card">
                    <h4 class="details-section-title">Items</h4>
                    <div class="item-detail">
                        <img id="detailsProductImage" src="{{ asset('img/default-product.png') }}" alt="Product"
                            class="product-image">
                        <div class="item-info">
                            <strong id="detailsProductName">—</strong>
                            <small id="detailsProductType">—</small>
                        </div>
                        <div class="item-right">
                            <span class="quantity-badge" id="detailsQuantity">Quantity 1</span>
                            <div class="item-price" id="detailsProductPrice">—</div>
                        </div>
                    </div>
                </div>

                <!-- PAYMENT -->
                <div class="details-section-card">
                    <h4 class="details-section-title">Payment</h4>
                    <div class="details-row">
                        <div class="details-label">Subtotal</div>
                        <div class="details-value" id="detailsSubtotal">—</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Gallon</div>
                        <div class="details-value" id="detailsGallon">—</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Downpayment</div>
                        <div class="details-value" id="detailsDownpayment">—</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Balance</div>
                        <div class="details-value" id="detailsBalance">—</div>
                    </div>
                    <div class="details-row total-row">
                        <div class="details-label">Total</div>
                        <div class="details-value" id="detailsTotal">—</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
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
                    text + '</span></span>';
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

        // Initialize delivery fee as disabled on page load
        document.addEventListener("DOMContentLoaded", function() {
            const deliveryFeeSelect = document.getElementById("deliveryFeeSelect");
            const deliveryFeeTrigger = document.getElementById("deliveryFeeTrigger");
            if (deliveryFeeSelect && deliveryFeeTrigger) {
                deliveryFeeSelect.style.pointerEvents = 'none';
                deliveryFeeSelect.style.opacity = '0.5';
                deliveryFeeTrigger.style.cursor = 'not-allowed';
                deliveryFeeTrigger.style.opacity = '0.5';
            }
        });

        document.addEventListener("click", function(e) {

            // OPEN / CLOSE CUSTOM SELECT
            const trigger = e.target.closest(".select-trigger");
            if (trigger) {
                const select = trigger.closest(".custom-select");
                document.querySelectorAll(".custom-select").forEach(s => {
                    if (s !== select) s.classList.remove("open");
                });
                select.classList.toggle("open");
                return;
            }

            // OPTION SELECTED
            const option = e.target.closest(".option");
            if (option) {
                const select = option.closest(".custom-select");
                const hiddenInput = select.querySelector("input[type='hidden']");
                const text = select.querySelector(".selected-text");

                hiddenInput.value = option.dataset.value;
                text.textContent = option.textContent;
                select.classList.remove("open");

                // Handle flavor selection
                if (select.id === 'flavorSelect') {
                    const flavorName = option.dataset.value;
                    const flavorCategory = option.dataset.category;
                    const flavorPrice = parseFloat(option.dataset.price) || 0;

                    document.getElementById("selectedFlavor").value = flavorName;
                    document.getElementById("flavorTypeDisplay").value = flavorCategory || '';
                    document.getElementById("flavorTypeInput").value = flavorCategory || '';
                    document.getElementById("flavorCost").value = flavorPrice;
                    document.getElementById("flavorCostDisplay").value = '₱' + flavorPrice.toFixed(2);

                    calculateTotalCost();
                }

                // Handle gallon selection
                if (select.id === 'gallonSelect') {
                    const gallonSize = option.dataset.value;
                    const gallonPrice = parseFloat(option.dataset.price) || 0;

                    document.getElementById("selectedGallon").value = gallonSize;
                    document.getElementById("gallonCost").value = gallonPrice;
                    document.getElementById("gallonCostDisplay").value = '₱' + gallonPrice.toFixed(2);

                    calculateTotalCost();
                }

                // Handle payment method selection
                if (select.id === 'paymentMethodSelect') {
                    const paymentMethod = option.dataset.value;
                    document.getElementById("paymentMethod").value = paymentMethod;

                    // Enable/disable delivery fee and total cost for GCash and Cash of Delivery
                    const deliveryFeeSelect = document.getElementById("deliveryFeeSelect");
                    const deliveryFeeTrigger = document.getElementById("deliveryFeeTrigger");
                    const totalCostDisplay = document.getElementById("totalCostDisplay");

                    if (paymentMethod === 'GCash' || paymentMethod === 'Cash of Delivery') {
                        // Enable delivery fee dropdown
                        deliveryFeeSelect.style.pointerEvents = 'auto';
                        deliveryFeeSelect.style.opacity = '1';
                        deliveryFeeTrigger.style.cursor = 'pointer';
                        deliveryFeeTrigger.style.opacity = '1';
                        calculateTotalCost();
                    } else {
                        // Disable delivery fee dropdown for Paymaya
                        deliveryFeeSelect.style.pointerEvents = 'none';
                        deliveryFeeSelect.style.opacity = '0.5';
                        deliveryFeeTrigger.style.cursor = 'not-allowed';
                        deliveryFeeTrigger.style.opacity = '0.5';
                        document.getElementById("deliveryFee").value = '0';
                        // Reset delivery fee display
                        document.querySelector("#deliveryFeeSelect .selected-text").textContent = 'Select fee';
                        // Still calculate total for Paymaya (without delivery fee)
                        calculateTotalCost();
                    }
                }

                // Handle delivery fee selection
                if (select.id === 'deliveryFeeSelect') {
                    const deliveryFee = parseFloat(option.dataset.value) || 0;
                    document.getElementById("deliveryFee").value = deliveryFee;
                    calculateTotalCost();
                }

                // AUTO-FILL FLAVOR TYPE (for edit modal compatibility)
                if (option.dataset.category && !select.id.includes('edit')) {
                    const flavorTypeDisplay = document.getElementById("flavorTypeDisplay");
                    const flavorTypeInput = document.getElementById("flavorTypeInput");
                    if (flavorTypeDisplay && flavorTypeInput) {
                        flavorTypeDisplay.value = option.dataset.category;
                        flavorTypeInput.value = option.dataset.category;
                    }
                }

                return;
            }

            // CLOSE ALL ON OUTSIDE CLICK
            document.querySelectorAll(".custom-select").forEach(s => s.classList.remove("open"));
        });

        // Calculate total cost
        function calculateTotalCost() {
            const flavorCost = parseFloat(document.getElementById("flavorCost").value) || 0;
            const gallonCost = parseFloat(document.getElementById("gallonCost").value) || 0;
            const paymentMethod = document.getElementById("paymentMethod").value;

            let deliveryFee = 0;
            if (paymentMethod === 'GCash' || paymentMethod === 'Cash of Delivery') {
                deliveryFee = parseFloat(document.getElementById("deliveryFee").value) || 0;
            }

            const totalCost = flavorCost + gallonCost + deliveryFee;

            // Always set the total cost value
            document.getElementById("totalCost").value = totalCost.toFixed(2);

            // Display total cost only for GCash and Cash of Delivery
            if (paymentMethod === 'GCash' || paymentMethod === 'Cash of Delivery') {
                document.getElementById("totalCostDisplay").value = '₱' + totalCost.toFixed(2);
            } else {
                document.getElementById("totalCostDisplay").value = '---';
            }
        }

        // Form validation before submission
        document.getElementById("addOrderForm").addEventListener("submit", function(e) {
            const paymentMethod = document.getElementById("paymentMethod").value;
            const totalCost = document.getElementById("totalCost").value;
            const submitBtn = this.querySelector("button[type='submit']");

            if (submitBtn && submitBtn.classList.contains("is-loading")) {
                e.preventDefault();
                return false;
            }

            if (!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method');
                return false;
            }

            if (paymentMethod === 'GCash' || paymentMethod === 'Cash of Delivery') {
                const deliveryFee = document.getElementById("deliveryFee").value;
                if (!deliveryFee || deliveryFee === '0') {
                    // Allow submission even if delivery fee is 0
                }
            }

            if (!totalCost || parseFloat(totalCost) <= 0) {
                e.preventDefault();
                alert('Please ensure all costs are calculated correctly');
                return false;
            }

            setButtonLoadingState(submitBtn, true, "Adding...");
        });
    </script>


    <script>
        /* ======================
            ADD ORDER MODAL (FIXED)
            ====================== */
        const addModal = document.getElementById("addModal");

        // OPEN (event delegation)
        document.addEventListener("click", function(e) {
            const addBtn = e.target.closest("#addOrderBtn");
            if (!addBtn) return;

            e.preventDefault();
            addModal.classList.add("show");
        });

        // CLOSE BUTTON
        document.addEventListener("click", function(e) {
            const closeBtn = e.target.closest("#closeAdd");
            if (!closeBtn) return;

            resetAddOrderForm();
            addModal.classList.remove("show");
        });

        // CLOSE ON BACKDROP
        addModal.addEventListener("click", function(e) {
            if (e.target === addModal) {
                resetAddOrderForm();
                addModal.classList.remove("show");
            }
        });

        // Reset form when modal closes
        function resetAddOrderForm() {
            document.getElementById("addOrderForm").reset();
            document.getElementById("flavorCostDisplay").value = '';
            document.getElementById("gallonCostDisplay").value = '';
            document.getElementById("totalCostDisplay").value = '---';
            document.getElementById("flavorTypeDisplay").value = '';
            setButtonLoadingState(document.querySelector("#addOrderForm button[type='submit']"), false);

            // Reset delivery fee dropdown
            const deliveryFeeSelect = document.getElementById("deliveryFeeSelect");
            const deliveryFeeTrigger = document.getElementById("deliveryFeeTrigger");
            deliveryFeeSelect.style.pointerEvents = 'none';
            deliveryFeeSelect.style.opacity = '0.5';
            deliveryFeeTrigger.style.cursor = 'not-allowed';
            deliveryFeeTrigger.style.opacity = '0.5';
            document.getElementById("deliveryFee").value = '0';

            document.querySelectorAll(
                "#flavorSelect .selected-text, #gallonSelect .selected-text, #paymentMethodSelect .selected-text, #deliveryFeeSelect .selected-text"
                ).forEach(el => {
                if (el.closest("#flavorSelect")) el.textContent = 'Select Flavor';
                else if (el.closest("#gallonSelect")) el.textContent = 'Select Size';
                else if (el.closest("#paymentMethodSelect")) el.textContent = 'Payment Method';
                else if (el.closest("#deliveryFeeSelect")) el.textContent = 'Select fee';
            });
        }
    </script>

    <script>
        /* ======================
               EDIT WALK-IN ORDER MODAL
            ====================== */
        const editModal = document.getElementById("editModal");
        const editForm = document.getElementById("editOrderForm");
        const editFullFields = Array.from(editForm.querySelectorAll("[data-full-edit-field]"));
        const editProductTypeInput = document.getElementById("editProductType");
        const editGallonSizeInput = document.getElementById("editGallonSize");
        const editPaymentMethodInput = document.getElementById("editPaymentMethod");
        const editQtyInput = document.getElementById("editQty");
        const editStatusSelect = document.getElementById("editStatus");
        const editAmountInput = document.getElementById("editAmount");
        const editFlavorCostDisplay = document.getElementById("editFlavorCostDisplay");
        const editGallonCostDisplay = document.getElementById("editGallonCostDisplay");
        const editFlavorOptions = Array.from(document.querySelectorAll('#editFlavorSelect .option'));
        const editGallonOptions = Array.from(document.querySelectorAll('#editGallonSelect .option'));

        function formatPeso(amount) {
            const num = Number.isFinite(amount) ? amount : 0;
            return '₱' + num.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function getFlavorUnitPrice(name) {
            const key = String(name || '').trim().toLowerCase();
            const match = editFlavorOptions.find((opt) => String(opt.dataset.value || '').trim().toLowerCase() === key);
            return match ? (parseFloat(match.dataset.price || '0') || 0) : 0;
        }

        function getGallonUnitPrice(size) {
            const key = String(size || '').trim().toLowerCase();
            const match = editGallonOptions.find((opt) => String(opt.dataset.value || '').trim().toLowerCase() === key);
            return match ? (parseFloat(match.dataset.price || '0') || 0) : 0;
        }

        function calculateEditPricing() {
            const qty = Math.max(1, parseInt(editQtyInput.value || '1', 10) || 1);
            const flavorPrice = getFlavorUnitPrice(document.getElementById("editProductName").value);
            const gallonPrice = getGallonUnitPrice(document.getElementById("editGallonSize").value);
            let unitPrice = flavorPrice + gallonPrice;

            if (unitPrice <= 0) {
                const fallbackAmount = parseFloat(editAmountInput.value || '0') || 0;
                unitPrice = fallbackAmount > 0 ? (fallbackAmount / qty) : 0;
            }

            const total = unitPrice * qty;
            editFlavorCostDisplay.value = formatPeso(flavorPrice);
            editGallonCostDisplay.value = formatPeso(gallonPrice);
            editAmountInput.value = total.toFixed(2);
        }

        function setEditStatusOptions(limitedEdit, statusKey, statusDriver) {
            const normalized = normalizeEditStatusKey(statusKey);
            const driverStatus = String(statusDriver || '').trim().toLowerCase();
            Array.from(editStatusSelect.options).forEach((option) => {
                let show;
                if (!limitedEdit) {
                    // Full edit: show all statuses
                    show = true;
                } else if (normalized === 'pending') {
                    // Pending: only pending visible
                    show = option.value === 'pending';
                } else if (normalized === 'preparing') {
                    // Preparing: only Preparing and Ready
                    show = option.value === 'preparing' || option.value === 'ready';
                } else if (normalized === 'assigned') {
                    // Assigned: only allow Preparing/Ready when driver has accepted
                    if (driverStatus === 'accepted') {
                        show = option.value === 'assigned' || option.value === 'preparing';
                    } else {
                        show = option.value === 'assigned';
                    }
                } else {
                    // Other limited-edit states
                    show = option.value === 'preparing' || option.value === normalized;
                }
                option.hidden = !show;
                option.disabled = !show;
            });

            editStatusSelect.value = normalized;
        }

        function normalizeEditStatusKey(status) {
            const s = String(status || '').trim().toLowerCase();
            if (s === 'walk-in' || s === 'walk_in' || s === 'walk in') return 'walk_in';
            if (s === 'preparing') return 'preparing';
            if (s === 'assigned') return 'assigned';
            if (s === 'ready') return 'ready';
            if (s === 'out for delivery' || s === 'out_for_delivery') return 'out_for_delivery';
            if (s === 'completed' || s === 'delivered') return 'completed';
            if (s === 'cancelled') return 'cancelled';
            return 'pending';
        }

        function setEditFullFieldState(wrapper, enabled) {
            wrapper.style.display = enabled ? '' : 'none';
            wrapper.querySelectorAll('input, textarea, select').forEach((el) => {
                const isHidden = el.type === 'hidden';
                const isReadonlyDisplay = el.id === 'editFlavorTypeDisplay';
                if (!enabled) {
                    el.dataset.prevRequired = el.required ? '1' : '0';
                    el.required = false;
                    if (!isReadonlyDisplay) {
                        el.disabled = true;
                    }
                } else {
                    el.disabled = false;
                    if (el.dataset.prevRequired === '1') {
                        el.required = true;
                    }
                }
                if (isHidden && !enabled) {
                    el.disabled = true;
                }
            });

            const customSelect = wrapper.querySelector('.custom-select');
            if (customSelect) {
                customSelect.classList.toggle('disabled', !enabled);
            }
        }

        function configureEditFormFields(statusKey, statusDriver) {
            const normalized = normalizeEditStatusKey(statusKey);
            const limitedEdit = normalized === 'pending' || normalized === 'assigned' || normalized === 'preparing';

            editFullFields.forEach((wrapper) => setEditFullFieldState(wrapper, !limitedEdit));
            setEditStatusOptions(limitedEdit, normalized, statusDriver);

            // Walk-in: hide and lock the status field completely
            const statusWrapper = editStatusSelect.closest('.form-group');
            if (normalized === 'walk_in') {
                if (statusWrapper) statusWrapper.style.display = 'none';
                editStatusSelect.disabled = true;
            } else {
                if (statusWrapper) statusWrapper.style.display = '';
                editStatusSelect.disabled = false;
            }

            // Keep these hidden values posted for limited edit updates.
            editProductTypeInput.disabled = false;
            editGallonSizeInput.disabled = false;
            editPaymentMethodInput.disabled = false;
        }

        document.addEventListener("click", function(e) {
            const editBtn = e.target.closest(".edit-order");
            if (!editBtn) return;

            const row = editBtn.closest("tr");
            if (!row || !row.dataset.orderId) return;

            const id = row.dataset.orderId;
            const baseUrl = "{{ url('admin/orders') }}";
            editForm.action = baseUrl + "/" + id;

            document.getElementById("editProductName").value = row.dataset.productName || '';
            document.getElementById("editProductNameText").textContent = row.dataset.productName || 'Select Flavor';
            document.getElementById("editProductType").value = row.dataset.productType || '';
            document.getElementById("editFlavorTypeDisplay").value = row.dataset.productType || '';
            document.getElementById("editQty").value = row.dataset.quantity || '1';

            document.getElementById("editGallonSize").value = row.dataset.gallonSize || '';
            document.getElementById("editGallonSizeText").textContent = row.dataset.gallonSize || 'Size of Gallon';

            document.getElementById("editCustomerName").value = row.dataset.customerName || '';
            document.getElementById("editCustomerPhone").value = row.dataset.customerPhone || '';
            document.getElementById("editDeliveryDate").value = row.dataset.deliveryDate || '';
            document.getElementById("editDeliveryTime").value = row.dataset.deliveryTime || '';
            document.getElementById("editDeliveryAddress").value = row.dataset.deliveryAddress || '';
            document.getElementById("editAmount").value = row.dataset.amount || '';

            document.getElementById("editPaymentMethod").value = row.dataset.paymentMethod || '';
            document.getElementById("editPaymentMethodText").textContent = row.dataset.paymentMethod ||
                'Payment Method';
            const rowStatus = row.dataset.statusKey || normalizeEditStatusKey(row.dataset.status || '');
            const rowStatusDriver = row.dataset.statusDriver || 'pending';
            document.getElementById("editStatus").value = normalizeEditStatusKey(row.dataset.status || row.dataset.statusKey || '');

            calculateEditPricing();
            configureEditFormFields(rowStatus, rowStatusDriver);

            editModal.classList.add("show");
        });

        document.addEventListener("click", function(e) {
            if (e.target.closest("#closeEdit")) {
                setButtonLoadingState(editForm.querySelector("button[type='submit']"), false);
                editModal.classList.remove("show");
            }
        });
        editModal.addEventListener("click", function(e) {
            if (e.target === editModal) {
                setButtonLoadingState(editForm.querySelector("button[type='submit']"), false);
                editModal.classList.remove("show");
            }
        });

        // Edit modal: when option selected in flavor/gallon/payment selects, update hidden + display
        document.addEventListener("click", function(e) {
            const option = e.target.closest(".option");
            if (!option) return;
            const select = option.closest(".custom-select");
            if (!select) return;
            if (select.classList.contains('disabled')) return;
            const hiddenInput = select.querySelector("input[type='hidden']");
            const textEl = select.querySelector(".selected-text");
            if (hiddenInput && textEl) {
                hiddenInput.value = option.dataset.value || option.textContent.trim();
                textEl.textContent = option.textContent.trim();
                select.classList.remove("open");
                if (option.dataset.category && select.closest("#editOrderForm")) {
                    document.getElementById("editFlavorTypeDisplay").value = option.dataset.category;
                    document.getElementById("editProductType").value = option.dataset.category;
                }
                if (select.id === 'editFlavorSelect' || select.id === 'editGallonSelect') {
                    calculateEditPricing();
                }
            }
        });

        editQtyInput.addEventListener('input', calculateEditPricing);
        editForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector("button[type='submit']");
            if (submitBtn && submitBtn.classList.contains("is-loading")) {
                e.preventDefault();
                return;
            }
            setButtonLoadingState(submitBtn, true, "Updating...");
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {

            let allRows = Array.from(document.querySelectorAll(".orders-data tbody tr[data-order-id]"));
            const ordersTbody = document.getElementById("orders-tbody");
            const searchInput = document.getElementById("searchInput");

            const filterBtn = document.getElementById("filterBtn");
            const filterDropdown = document.getElementById("filterDropdown");
            const filterChecks = filterDropdown.querySelectorAll("input[type='checkbox']");

            const prevBtn = document.getElementById("prevBtn");
            const nextBtn = document.getElementById("nextBtn");
            const pageNumbers = document.getElementById("pageNumbers");
            const pagination = document.querySelector(".pagination-wrapper");
            const showingText = document.getElementById("showingText");

            const rowsPerPage = 10;
            let currentPage = 1;
            let filteredRows = [...allRows];

            function normalizeStatusKey(status) {
                const s = String(status || '').trim().toLowerCase();
                if (s === 'walk-in' || s === 'walk_in' || s === 'walk in') return 'walk_in';
                if (s === 'preparing') return 'preparing';
                if (s === 'assigned') return 'assigned';
                if (s === 'ready') return 'ready';
                if (s === 'out for delivery' || s === 'out_for_delivery') return 'out_for_delivery';
                if (s === 'completed' || s === 'delivered') return 'completed';
                if (s === 'cancelled') return 'cancelled';
                return 'pending';
            }

            /* ======================
               FILTER TOGGLE
            ====================== */
            filterBtn.onclick = () => {
                filterDropdown.style.display =
                    filterDropdown.style.display === "block" ? "none" : "block";
            };

            document.addEventListener("click", e => {
                if (!filterBtn.contains(e.target) && !filterDropdown.contains(e.target)) {
                    filterDropdown.style.display = "none";
                }
            });

            /* ======================
               SEARCH + FILTER
            ====================== */
            function applyFilters(resetPage = true) {
                const keyword = searchInput.value.toLowerCase().trim();
                const activeStatuses = Array.from(filterChecks)
                    .filter(c => c.checked)
                    .map(c => c.value);

                filteredRows = allRows.filter(row => {
                    const product = (row.dataset.productName || '').toLowerCase();
                    const customer = (row.dataset.customerName || '').toLowerCase();
                    const transactionId = (row.dataset.transactionId || '').toLowerCase();

                    const statusClass = row.dataset.statusKey || normalizeStatusKey(row.dataset.status ||
                        '');

                    const matchSearch = !keyword ||
                        product.includes(keyword) ||
                        customer.includes(keyword) ||
                        transactionId.includes(keyword);

                    const matchStatus =
                        activeStatuses.length === 0 || activeStatuses.includes(statusClass);

                    return matchSearch && matchStatus;
                });

                if (resetPage) currentPage = 1;
                const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
                if (totalPages > 0 && currentPage > totalPages) currentPage = totalPages;
                render(currentPage);
            }

            const urlParams = new URLSearchParams(window.location.search);
            const customerKeyword = (urlParams.get("customer") || "").trim();
            if (customerKeyword !== "") {
                searchInput.value = customerKeyword;
            }

            searchInput.addEventListener("input", applyFilters);
            filterChecks.forEach(cb => cb.addEventListener("change", applyFilters));

            /* ======================
               PAGINATION
            ====================== */
            function render(page) {
                const totalRows = filteredRows.length;
                const totalPages = Math.ceil(totalRows / rowsPerPage);

                allRows.forEach(r => r.style.display = "none");

                if (totalRows === 0) {
                    pagination.style.setProperty("display", "none", "important");
                    if (showingText) {
                        showingText.style.display = "block";
                        showingText.textContent = "No results found";
                    }
                    return;
                }

                /* When data is not beyond 10: hide pagination, show "Showing X data" only */
                const dataBeyond10 = totalRows > rowsPerPage;
                if (dataBeyond10) {
                    pagination.style.setProperty("display", "flex", "important");
                    if (showingText) showingText.style.display = "none";
                } else {
                    pagination.style.setProperty("display", "none", "important");
                    if (showingText) {
                        showingText.style.display = "block";
                        showingText.textContent = "Showing " + totalRows + " data";
                    }
                }

                filteredRows
                    .slice((page - 1) * rowsPerPage, page * rowsPerPage)
                    .forEach(r => r.style.display = "table-row");

                pageNumbers.innerHTML = "";
                for (let i = 1; i <= totalPages; i++) {
                    const btn = document.createElement("button");
                    btn.type = "button";
                    btn.textContent = i;
                    btn.className = "page-num" + (i === page ? " active" : "");
                    btn.setAttribute("aria-label", "Page " + i);
                    btn.setAttribute("aria-current", i === page ? "page" : "false");
                    (function(pageNum) {
                        btn.onclick = function() {
                            currentPage = pageNum;
                            render(pageNum);
                        };
                    })(i);
                    pageNumbers.appendChild(btn);
                }

                prevBtn.disabled = page <= 1;
                nextBtn.disabled = page >= totalPages || totalPages <= 0;
            }

            prevBtn.onclick = () => {
                if (currentPage > 1) {
                    currentPage--;
                    render(currentPage);
                }
            };
            nextBtn.onclick = () => {
                const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
                if (currentPage < totalPages) {
                    currentPage++;
                    render(currentPage);
                }
            };

            /* ======================
               REALTIME: build one row from API order
            ====================== */
            function esc(s) {
                if (s == null || s === undefined) return '';
                const d = document.createElement('div');
                d.textContent = s;
                return d.innerHTML;
            }

            function escAttr(s) {
                return esc(s).replace(/"/g, '&quot;');
            }

            function buildOrderRow(order) {
                const statusKey = normalizeStatusKey(order.status);
                const statusDisplay = String(order.status || '').trim() || 'Pending';
                const amountFormatted = typeof order.amount === 'number' ? order.amount.toFixed(2) : (parseFloat(
                    order.amount) || 0).toFixed(2);
                const downpaymentValue = (typeof order.downpayment !== 'undefined' && order.downpayment !== null)
                    ? order.downpayment
                    : 0;
                let actionHtml = '<div style="display:flex;gap:6px;align-items:center;">';

                // WALK-IN → ASSIGN + EDIT
                if (statusKey === 'walk_in') {
                    actionHtml += `
                <button type="button" class="action-btn assign">
                    <span class="material-symbols-outlined">person_check</span>
                </button>
                <button type="button" class="action-btn edit-order">
                    <span class="material-symbols-outlined">edit</span>
                </button>`;
                }

                // ASSIGNED → REASSIGN + EDIT
                else if (statusKey === 'assigned') {
                    actionHtml += `
                <button class="action-btn reassign">
                    <span class="material-symbols-outlined">person_edit</span>
                </button>
                <button type="button" class="action-btn edit-order">
                    <span class="material-symbols-outlined">edit</span>
                </button>`;
                }
                // READY → view only (no reassign/edit)

                // PREPARING → EDIT ONLY (change status to Ready)
                else if (statusKey === 'preparing') {
                    actionHtml += `
                <button type="button" class="action-btn edit-order">
                    <span class="material-symbols-outlined">edit</span>
                </button>`;
                }

                // PENDING → ASSIGN + EDIT
                else if (statusKey === 'pending') {
                    actionHtml += `
                <button class="action-btn assign">
                    <span class="material-symbols-outlined">person_check</span>
                </button>
                <button type="button" class="action-btn edit-order">
                    <span class="material-symbols-outlined">edit</span>
                </button>`;
                }

                actionHtml += `
                <button type="button" class="action-btn view-order">
                    <span class="material-symbols-outlined">visibility</span>
                </button>`;

                actionHtml += '</div>';
                return '<tr data-order-id="' + escAttr(String(order.id)) + '"' +
                    ' data-product-name="' + escAttr(order.product_name) + '"' +
                    ' data-product-type="' + escAttr(order.product_type) + '"' +
                    ' data-product-image="' + escAttr(order.product_image_url) + '"' +
                    ' data-gallon-size="' + escAttr(order.gallon_size) + '"' +
                    ' data-transaction-id="' + escAttr(order.transaction_id) + '"' +
                    ' data-customer-name="' + escAttr(order.customer_name) + '"' +
                    ' data-customer-phone="' + escAttr(order.customer_phone) + '"' +
                    ' data-customer-email="' + escAttr(order.customer_email || '') + '"' +
                    ' data-customer-image="' + escAttr(order.customer_image_url || '') + '"' +
                    ' data-delivery-date="' + escAttr(order.delivery_date) + '"' +
                    ' data-delivery-time="' + escAttr(order.delivery_time) + '"' +
                    ' data-delivery-address="' + escAttr(order.delivery_address) + '"' +
                    ' data-amount="' + escAttr(String(order.amount)) + '"' +
                    ' data-downpayment="' + escAttr(String(downpaymentValue)) + '"' +
                    ' data-quantity="' + escAttr(String(order.quantity ?? order.qty ?? 1)) + '"' +
                    ' data-payment-method="' + escAttr(order.payment_method) + '"' +
                    ' data-status="' + escAttr(order.status) + '"' +
                    ' data-status-key="' + escAttr(statusKey) + '"' +
                    ' data-status-driver="' + escAttr((order.status_driver || 'pending').toLowerCase()) + '"' +
                    ' data-driver-id="' + escAttr(order.driver_id || '') + '"' +
                    ' data-driver-name="' + escAttr(order.driver_name || '') + '"' +
                    ' data-driver-phone="' + escAttr(order.driver_phone || '') + '"' +
                    ' data-driver-image-url="' + escAttr(order.driver_image_url || '') + '">' +
                    '<td><strong>#' + esc(order.transaction_id) + '</strong><small>' + esc(order
                        .created_at_formatted) + '</small></td>' +
                    '<td class="delivery-schedule-cell"><strong>' + esc(order.delivery_date_formatted) + ', ' + esc(
                        order.delivery_time_formatted) + '</strong><small class="delivery-address">' + esc(order
                        .delivery_address) + '</small></td>' +
                    '<td><strong>₱' + esc(amountFormatted.replace(/\B(?=(\d{3})+(?!\d))/g, ',')) +
                    '</strong><small>' + esc(order.payment_method) + '</small></td>' +
                    '<td><span class="status-badge ' + escAttr(statusKey) + '">● ' + esc(statusDisplay) +
                    '</span></td>' +
                    '<td>' + actionHtml + '</td></tr>';
            }

            async function fetchAndRefreshOrders() {
                try {
                    const res = await fetch('{{ route('admin.orders.list') }}?scope=this_month', {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    const orders = data.orders || [];
                    if (orders.length === 0) {
                        ordersTbody.innerHTML =
                            '<tr><td colspan="7" style="text-align:center; padding:30px;">No orders found</td></tr>';
                    } else {
                        ordersTbody.innerHTML = orders.map(buildOrderRow).join('');
                    }
                    allRows = Array.from(document.querySelectorAll('.orders-data tbody tr[data-order-id]'));
                    applyFilters(false);
                } catch (e) {
                    /* ignore network errors */ }
            }
            window.fetchAndRefreshOrders = fetchAndRefreshOrders;

            // Real-time only via Firebase Realtime Database (no polling)
            const firebaseDbUrl = window.FIREBASE_DATABASE_URL || @json(config('services.firebase_realtime_url') ?: (config('firebase.projects.app.database.url') ?? ''));
            if (firebaseDbUrl && typeof firebase !== 'undefined' && firebase.database) {
                try {
                    if (!firebase.apps.length) {
                        firebase.initializeApp({ databaseURL: firebaseDbUrl });
                    }
                    const db = firebase.database();
                    const ordersUpdatedRef = db.ref('admin/orders_last_updated');
                    let lastUpdatedValue = null;
                    ordersUpdatedRef.on('value', function(snapshot) {
                        const val = snapshot.val();
                        const ts = val && val.value ? val.value : (val && val.updated_at ? val.updated_at : null);
                        if (lastUpdatedValue !== null && ts != null && lastUpdatedValue !== ts) {
                            lastUpdatedValue = ts;
                            fetchAndRefreshOrders();
                        } else if (lastUpdatedValue === null) {
                            lastUpdatedValue = ts || '';
                        }
                    });
                } catch (e) {
                    console.warn('Firebase orders listener failed.', e);
                }
            }

            render(currentPage);
        });
    </script>
    <script>
        /* ======================
            ASSIGN / RE-ASSIGN MODAL
            ====================== */
        const assignModal = document.getElementById("assignModal");
        const closeAssign = document.getElementById("closeAssign");
        const assignModalTitle = document.getElementById("assignModalTitle");
        const assignProductImage = document.getElementById("assignProductImage");
        const assignProduct = document.getElementById("assignProduct");
        const assignSchedule = document.getElementById("assignSchedule");
        const assignTxn = document.getElementById("assignTxn");
        const assignCustomer = document.getElementById("assignCustomer");
        const assignPhone = document.getElementById("assignPhone");
        const assignAddress = document.getElementById("assignAddress");
        const assignCurrentDriverSection = document.getElementById("assignCurrentDriverSection");
        const assignCurrentDriverImage = document.getElementById("assignCurrentDriverImage");
        const assignCurrentDriverName = document.getElementById("assignCurrentDriverName");
        const assignCurrentDriverPhone = document.getElementById("assignCurrentDriverPhone");
        const assignDriverListLoading = document.getElementById("assignDriverListLoading");
        const assignDriverListItems = document.getElementById("assignDriverListItems");

        const assignDriverUrl = "{{ route('admin.orders.drivers') }}";
        const assignOrderUrlBase = "{{ url('admin/orders') }}";

        function formatSchedule(dateStr, timeStr) {
            if (!dateStr || !timeStr) return '—';
            try {
                const d = new Date(dateStr + 'T' + timeStr);
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const day = d.getDate();
                const month = months[d.getMonth()];
                let h = d.getHours();
                const m = d.getMinutes();
                const ampm = h >= 12 ? 'pm' : 'am';
                h = h % 12 || 12;
                return day + ' ' + month + ', ' + (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ' ' + ampm;
            } catch (e) {
                return '—';
            }
        }

        function openAssignModal(row, isReassign) {
            const orderId = row.dataset.orderId;
            if (!orderId) return;

            assignModal.dataset.orderId = orderId;
            assignProductImage.src = row.dataset.productImage || '';
            assignProduct.textContent = row.dataset.productName || '—';
            assignSchedule.textContent = formatSchedule(row.dataset.deliveryDate, row.dataset.deliveryTime);
            assignTxn.textContent = '#' + (row.dataset.transactionId || '—');
            assignCustomer.textContent = row.dataset.customerName || '—';
            assignPhone.textContent = row.dataset.customerPhone || '—';
            assignAddress.textContent = row.dataset.deliveryAddress || '—';

            if (isReassign) {
                assignModalTitle.textContent = 'Re-Assign Driver';
                assignCurrentDriverSection.style.display = 'block';
                assignCurrentDriverImage.src = row.dataset.driverImageUrl || "{{ asset('img/default-user.png') }}";
                assignCurrentDriverName.textContent = row.dataset.driverName || '—';
                assignCurrentDriverPhone.textContent = row.dataset.driverPhone || '—';
            } else {
                assignModalTitle.textContent = 'Assign Driver';
                assignCurrentDriverSection.style.display = 'none';
            }

            assignDriverListLoading.style.display = 'block';
            assignDriverListItems.innerHTML = '';

            fetch(assignDriverUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                .then(function(res) {
                    return res.json();
                })
                .then(function(data) {
                    assignDriverListLoading.style.display = 'none';
                    const drivers = data.drivers || [];
                    const currentDriverId = (row.dataset.driverId || '').toString();
                    const buttonLabel = isReassign ? 'Re-Assign' : 'Assign';
                    drivers.forEach(function(d) {
                        const item = document.createElement('div');
                        item.className = 'driver-item';
                        item.innerHTML =
                            '<div class="driver-info">' +
                            '<img src="' + (d.image_url || '') + '" alt="">' +
                            '<div><strong>' + (d.name || '') + '</strong><small>' + (d.phone || '') +
                            '</small></div>' +
                            '</div>' +
                            '<button type="button" class="assign-driver-btn" data-driver-id="' + (d.id || '') +
                            '">' + buttonLabel + '</button>';
                        assignDriverListItems.appendChild(item);
                    });
                    if (drivers.length === 0) {
                        assignDriverListItems.innerHTML =
                            '<p style="padding:12px; color:#666;">No drivers available.</p>';
                    }
                })
                .catch(function() {
                    assignDriverListLoading.style.display = 'none';
                    assignDriverListItems.innerHTML =
                    '<p style="padding:12px; color:#c00;">Failed to load drivers.</p>';
                });

            assignModal.classList.add('show');
        }

        document.addEventListener("click", function(e) {
            const assignBtn = e.target.closest(".action-btn.assign");
            const reassignBtn = e.target.closest(".action-btn.reassign");
            const row = (assignBtn || reassignBtn) ? (assignBtn || reassignBtn).closest("tr") : null;
            if (assignBtn && row) {
                e.preventDefault();
                openAssignModal(row, false);
                return;
            }
            if (reassignBtn && row) {
                e.preventDefault();
                openAssignModal(row, true);
                return;
            }

            const assignDriverBtn = e.target.closest(".assign-driver-btn[data-driver-id]");
            if (assignDriverBtn && assignModal.classList.contains("show")) {
                e.preventDefault();
                if (assignDriverBtn.classList.contains("is-loading")) return;
                const orderId = assignModal.dataset.orderId;
                const driverId = assignDriverBtn.dataset.driverId;
                if (!orderId || !driverId) return;
                const isReassign = assignModalTitle.textContent.toLowerCase().includes('re-assign');
                const allDriverButtons = Array.from(assignDriverListItems.querySelectorAll(
                    ".assign-driver-btn[data-driver-id]"));
                allDriverButtons.forEach(function(btn) {
                    btn.disabled = true;
                });
                setButtonLoadingState(assignDriverBtn, true, isReassign ? "Re-Assigning..." : "Assigning...");

                const assignPostUrl = assignOrderUrlBase + '/' + orderId + '/assign';
                var csrfToken = document.querySelector('meta[name="csrf-token"]') && document.querySelector(
                    'meta[name="csrf-token"]').getAttribute('content');
                if (!csrfToken) csrfToken = '{{ csrf_token() }}';
                fetch(assignPostUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            driver_id: String(driverId)
                        })
                    })
                    .then(function(res) {
                        return res.json().then(function(data) {
                            return {
                                ok: res.ok,
                                data: data
                            };
                        });
                    })
                    .then(function(result) {
                        if (result.ok && result.data.success) {
                            assignModal.classList.remove('show');
                            if (typeof fetchAndRefreshOrders === 'function') fetchAndRefreshOrders();
                        } else {
                            allDriverButtons.forEach(function(btn) {
                                btn.disabled = false;
                            });
                            setButtonLoadingState(assignDriverBtn, false);
                            alert(result.data.message || 'Failed to assign driver.');
                        }
                    })
                    .catch(function() {
                        allDriverButtons.forEach(function(btn) {
                            btn.disabled = false;
                        });
                        setButtonLoadingState(assignDriverBtn, false);
                        alert('Failed to assign driver.');
                    });
            }
        });

        closeAssign.addEventListener("click", function() {
            assignModal.classList.remove("show");
        });

        assignModal.addEventListener("click", function(e) {
            if (e.target === assignModal) assignModal.classList.remove("show");
        });
    </script>

    <script>
        /* ======================
            ORDER DETAILS MODAL
            ====================== */
        const orderDetailsModal = document.getElementById("orderDetailsModal");
        const closeOrderDetails = document.getElementById("closeOrderDetails");
        const orderShowBaseUrl = "{{ url('admin/orders') }}";

        function normalizeStatusForDisplay(status) {
            const s = String(status || '').trim().toLowerCase();
            if (s === 'walk-in' || s === 'walk_in' || s === 'walk in') return 'Walk-In';
            if (s === 'preparing') return 'Preparing';
            if (s === 'assigned') return 'Assigned';
            if (s === 'ready') return 'Ready';
            if (s === 'out for delivery' || s === 'out_for_delivery') return 'Out for Delivery';
            if (s === 'completed' || s === 'delivered') return 'Delivered';
            if (s === 'cancelled') return 'Cancelled';
            return 'Pending';
        }

        function getStatusClass(status) {
            const s = String(status || '').trim().toLowerCase();
            if (s === 'walk-in' || s === 'walk_in' || s === 'walk in') return 'walk_in';
            if (s === 'preparing') return 'preparing';
            if (s === 'assigned') return 'assigned';
            if (s === 'ready') return 'ready';
            if (s === 'out for delivery' || s === 'out_for_delivery') return 'out_for_delivery';
            if (s === 'completed' || s === 'delivered') return 'completed';
            if (s === 'cancelled') return 'cancelled';
            return 'pending';
        }

        function formatCurrency(amount) {
            return '₱' + parseFloat(amount || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function setOrderDetailsLoading() {
            document.getElementById("detailsStatus").textContent = "Loading...";
            document.getElementById("detailsStatus").className = 'status-badge-details pending';
            document.getElementById("detailsTransactionId").textContent = '—';
            document.getElementById("detailsCustomerImage").src = "{{ asset('img/default-user.png') }}";
            document.getElementById("detailsCustomerName").textContent = 'Loading...';
            document.getElementById("detailsCustomerEmail").textContent = '—';
            document.getElementById("detailsCustomerPhone").textContent = '—';
            document.getElementById("detailsDeliveryAddress").textContent = '—';
            document.getElementById("detailsProductImage").src = "{{ asset('img/default-product.png') }}";
            document.getElementById("detailsProductName").textContent = 'Loading...';
            document.getElementById("detailsProductType").textContent = '—';
            document.getElementById("detailsProductPrice").textContent = '—';
            document.getElementById("detailsQuantity").textContent = 'Quantity —';
            document.getElementById("detailsGallon").textContent = '—';
            document.getElementById("detailsSubtotal").textContent = '—';
            document.getElementById("detailsAssignedDriver").textContent = '—';
            document.getElementById("detailsDownpayment").textContent = formatCurrency(0);
            document.getElementById("detailsBalance").textContent = formatCurrency(0);
            document.getElementById("detailsTotal").textContent = '—';
        }

        function buildOrderDetailsFromRow(row) {
            const amount = parseFloat(row.dataset.amount || '0') || 0;
            const downpayment = parseFloat(row.dataset.downpayment || '0') || 0;
            const balance = Math.max(0, amount - downpayment);
            return {
                transaction_id: row.dataset.transactionId || '',
                status: row.dataset.status || '',
                status_driver: (row.dataset.statusDriver || 'pending').toLowerCase(),
                customer_name: row.dataset.customerName || '',
                customer_phone: row.dataset.customerPhone || '',
                customer_email: row.dataset.customerEmail || '',
                customer_image_url: row.dataset.customerImage || '',
                delivery_address: row.dataset.deliveryAddress || '',
                product_name: row.dataset.productName || '',
                product_type: row.dataset.productType || '',
                gallon_size: row.dataset.gallonSize || '',
                product_image_url: row.dataset.productImage || '',
                amount: amount,
                downpayment: downpayment,
                balance: balance,
                quantity: parseInt(row.dataset.quantity || '1', 10) || 1,
                payment_method: row.dataset.paymentMethod || '',
                driver_id: row.dataset.driverId || '',
                driver_name: row.dataset.driverName || ''
            };
        }

        function fillOrderDetailsModal(orderData) {
            const amount = parseFloat(orderData.amount || 0) || 0;
            const downpayment = parseFloat(orderData.downpayment || 0) || 0;
            const balance = ('balance' in orderData && orderData.balance !== null && orderData.balance !== undefined)
                ? parseFloat(orderData.balance || 0) || 0
                : Math.max(0, amount - downpayment);
            const quantity = parseInt(orderData.quantity || orderData.qty || 1, 10) || 1;
            const status = normalizeStatusForDisplay(orderData.status);
            const statusClass = getStatusClass(orderData.status);
            const productType = orderData.product_type || '—';
            const gallonSize = orderData.gallon_size || '—';
            const statusDriver = (orderData.status_driver || 'pending').toString().toLowerCase();
            const hasDriver = !!(orderData.driver_id || orderData.driver_name || (orderData.driver && orderData.driver.name));
            const driverName = (statusDriver === 'pending' && hasDriver)
                ? 'Pending'
                : (orderData.driver_name || (orderData.driver && orderData.driver.name) || 'No driver assigned');

            document.getElementById("detailsStatus").textContent = status;
            document.getElementById("detailsStatus").className = 'status-badge-details ' + statusClass;
            document.getElementById("detailsTransactionId").textContent = '#' + (orderData.transaction_id || '—');

            document.getElementById("detailsCustomerImage").src = orderData.customer_image_url || "{{ asset('img/default-user.png') }}";
            document.getElementById("detailsCustomerName").textContent = orderData.customer_name || '—';
            document.getElementById("detailsCustomerEmail").textContent = orderData.customer_email || 'No email provided';
            document.getElementById("detailsCustomerPhone").textContent = orderData.customer_phone || '—';
            document.getElementById("detailsDeliveryAddress").textContent = orderData.delivery_address || '—';

            document.getElementById("detailsProductImage").src = orderData.product_image_url || "{{ asset('img/default-product.png') }}";
            document.getElementById("detailsProductName").textContent = orderData.product_name || '—';
            document.getElementById("detailsProductType").textContent = productType + ' (' + gallonSize + ')';
            document.getElementById("detailsQuantity").textContent = 'Quantity ' + quantity;
            document.getElementById("detailsProductPrice").textContent = formatCurrency(amount);

            document.getElementById("detailsSubtotal").textContent = formatCurrency(amount);
            document.getElementById("detailsGallon").textContent = gallonSize;
            document.getElementById("detailsAssignedDriver").textContent = driverName;
            document.getElementById("detailsDownpayment").textContent = formatCurrency(downpayment);
            document.getElementById("detailsBalance").textContent = formatCurrency(balance);
            document.getElementById("detailsTotal").textContent = formatCurrency(amount);
        }

        async function openOrderDetailsModal(row) {
            if (!row || !row.dataset.orderId) return;
            setOrderDetailsLoading();
            orderDetailsModal.classList.add("show");

            try {
                const res = await fetch(orderShowBaseUrl + '/' + encodeURIComponent(row.dataset.orderId), {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.order) {
                    fillOrderDetailsModal(data.order);
                } else {
                    fillOrderDetailsModal(buildOrderDetailsFromRow(row));
                }
            } catch (e) {
                fillOrderDetailsModal(buildOrderDetailsFromRow(row));
            }
        }

        // Handle view order button clicks
        document.addEventListener("click", function(e) {
            const viewBtn = e.target.closest(".view-order");
            if (viewBtn) {
                const row = viewBtn.closest("tr[data-order-id]");
                if (row) {
                    e.preventDefault();
                    openOrderDetailsModal(row);
                }
            }
        });

        // Close modal
        closeOrderDetails.addEventListener("click", function() {
            orderDetailsModal.classList.remove("show");
        });

        // Close on backdrop click
        orderDetailsModal.addEventListener("click", function(e) {
            if (e.target === orderDetailsModal) {
                orderDetailsModal.classList.remove("show");
            }
        });

        // Close on Escape key
        document.addEventListener("keydown", function(e) {
            if (e.key === 'Escape' && orderDetailsModal.classList.contains("show")) {
                orderDetailsModal.classList.remove("show");
            }
        });
    </script>

</body>

</html>
