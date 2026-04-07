@extends('admin.layout.layout')

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="icon" href="{{ asset('img/logo.png') }}">
    @section('title', 'Records')
    <link rel="stylesheet" href="{{ asset('assets/css/Admin/order.css') }}">
    <style>
        .records-skeleton-row td {
            padding: 14px 12px;
            vertical-align: middle;
        }

        .records-skeleton-bar {
            height: 12px;
            border-radius: 6px;
            max-width: 100%;
            background: linear-gradient(90deg, #e8e8e8 0%, #f4f4f4 45%, #e8e8e8 90%);
            background-size: 220% 100%;
            animation: recordsSk 1.1s ease-in-out infinite;
        }

        @keyframes recordsSk {
            0% {
                background-position: 220% 0;
            }

            100% {
                background-position: -220% 0;
            }
        }
    </style>
</head>

<body>
    @section('content')
        <div class="content-area orders-page">

            <!-- HEADER -->
            <div class="orders-header">
                <h2>Records</h2>

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
                            <label><input type="checkbox" value="completed"> Completed</label>
                            <label><input type="checkbox" value="cancelled"> Cancelled</label>
                        </div>
                    </div>

                    <div class="orders-date-filter" title="Filter by date (delivery date)">
                        <span class="material-symbols-outlined orders-date-filter-icon">calendar_today</span>
                        <input type="date" id="filterDateFrom" class="orders-date-input" aria-label="From date">
                        <span class="orders-date-sep">–</span>
                        <input type="date" id="filterDateTo" class="orders-date-input" aria-label="To date">
                    </div>
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

                <div class="table-scroll">
                    <table class="orders-data orders-data-body">
                        <colgroup>
                            <col style="width: 18%;">
                            <col style="width: 22%;">
                            <col style="width: 20%;">
                            <col style="width: 22%;">
                            <col style="width: 12%;">
                        </colgroup>
                        <tbody id="records-tbody">
                            @for ($i = 0; $i < 8; $i++)
                                <tr class="records-skeleton-row" aria-hidden="true">
                                    <td>
                                        <div class="records-skeleton-bar" style="width:{{ 55 + ($i % 3) * 12 }}%;"></div>
                                    </td>
                                    <td>
                                        <div class="records-skeleton-bar" style="width:{{ 70 + ($i % 2) * 10 }}%;"></div>
                                    </td>
                                    <td>
                                        <div class="records-skeleton-bar" style="width:45%;"></div>
                                    </td>
                                    <td>
                                        <div class="records-skeleton-bar" style="width:{{ 60 + ($i % 4) * 8 }}%;"></div>
                                    </td>
                                    <td>
                                        <div class="records-skeleton-bar" style="width:32px;margin-left:auto;"></div>
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
                <div id="showingText" class="showing-text" style="display:none;"></div>

                <div class="pagination-wrapper orders-pagination" id="recordsPagination">
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

    <!-- ORDER DETAILS MODAL -->
    <div class="orders-view-modal orders-view-modal--orders" id="orderDetailsModal">
        <div class="orders-view-card">
            <div class="orders-view-header">
                <h3>Order Details</h3>
                <button class="orders-view-close" id="closeOrderDetails">&times;</button>
            </div>
            <div class="orders-view-content">
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
                <div class="details-section-card">
                    <h4 class="details-section-title">Customer Info</h4>
                    <div class="customer-profile">
                        <img id="detailsCustomerImage" src="{{ asset('img/default-user.png') }}" alt="Customer" class="customer-avatar">
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
                <div class="details-section-card">
                    <h4 class="details-section-title">Items</h4>
                    <div class="item-detail">
                        <img id="detailsProductImage" src="{{ asset('img/default-product.png') }}" alt="Product" class="product-image">
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
        document.addEventListener("DOMContentLoaded", () => {
            let allRows = [];
            const recordsTbody = document.getElementById("records-tbody");
            const searchInput = document.getElementById("searchInput");
            const filterBtn = document.getElementById("filterBtn");
            const filterDropdown = document.getElementById("filterDropdown");
            const filterChecks = filterDropdown.querySelectorAll("input[type='checkbox']");
            const filterDateFrom = document.getElementById("filterDateFrom");
            const filterDateTo = document.getElementById("filterDateTo");
            const prevBtn = document.getElementById("prevBtn");
            const nextBtn = document.getElementById("nextBtn");
            const pageNumbers = document.getElementById("pageNumbers");
            const pagination = document.querySelector(".pagination-wrapper");
            const showingText = document.getElementById("showingText");
            const rowsPerPage = 10;
            let currentPage = 1;
            let filteredRows = [];

            function normalizeStatusKey(status) {
                const s = String(status || '').trim().toLowerCase();
                if (s === 'walk-in' || s === 'walk_in' || s === 'walk in') return 'walk_in';
                if (s === 'preparing') return 'preparing';
                if (s === 'assigned') return 'assigned';
                if (s === 'completed' || s === 'delivered') return 'completed';
                if (s === 'cancelled') return 'cancelled';
                return 'pending';
            }

            filterBtn.onclick = () => {
                filterDropdown.style.display = filterDropdown.style.display === "block" ? "none" : "block";
            };
            document.addEventListener("click", e => {
                if (!filterBtn.contains(e.target) && !filterDropdown.contains(e.target)) {
                    filterDropdown.style.display = "none";
                }
            });

            function applyFilters(resetPage = true) {
                const keyword = searchInput.value.toLowerCase().trim();
                const activeStatuses = Array.from(filterChecks).filter(c => c.checked).map(c => c.value);
                const dateFrom = (filterDateFrom && filterDateFrom.value) || '';
                const dateTo = (filterDateTo && filterDateTo.value) || '';

                filteredRows = allRows.filter(row => {
                    const product = (row.dataset.productName || '').toLowerCase();
                    const customer = (row.dataset.customerName || '').toLowerCase();
                    const transactionId = (row.dataset.transactionId || '').toLowerCase();
                    const statusClass = row.dataset.statusKey || normalizeStatusKey(row.dataset.status || '');
                    const matchSearch = !keyword || product.includes(keyword) || customer.includes(keyword) || transactionId.includes(keyword);
                    const matchStatus = activeStatuses.length === 0 || activeStatuses.includes(statusClass);
                    let matchDate = true;
                    if (dateFrom || dateTo) {
                        const raw = (row.dataset.deliveryDate || '').trim();
                        const rowDate = raw ? raw.substring(0, 10) : '';
                        if (!rowDate) matchDate = false;
                        else {
                            if (dateFrom && rowDate < dateFrom) matchDate = false;
                            if (dateTo && rowDate > dateTo) matchDate = false;
                        }
                    }
                    return matchSearch && matchStatus && matchDate;
                });

                if (resetPage) currentPage = 1;
                const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
                if (totalPages > 0 && currentPage > totalPages) currentPage = totalPages;
                render(currentPage);
            }

            const urlParams = new URLSearchParams(window.location.search);
            const customerKeyword = (urlParams.get("customer") || "").trim();
            if (customerKeyword !== "") searchInput.value = customerKeyword;

            searchInput.addEventListener("input", applyFilters);
            filterChecks.forEach(cb => cb.addEventListener("change", applyFilters));
            if (filterDateFrom) filterDateFrom.addEventListener("change", applyFilters);
            if (filterDateTo) filterDateTo.addEventListener("change", applyFilters);

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
                filteredRows.slice((page - 1) * rowsPerPage, page * rowsPerPage).forEach(r => r.style.display = "table-row");
                pageNumbers.innerHTML = "";
                for (let i = 1; i <= totalPages; i++) {
                    const btn = document.createElement("button");
                    btn.type = "button";
                    btn.textContent = i;
                    btn.className = "page-num" + (i === page ? " active" : "");
                    btn.setAttribute("aria-label", "Page " + i);
                    btn.setAttribute("aria-current", i === page ? "page" : "false");
                    (function(pageNum) {
                        btn.onclick = function() { currentPage = pageNum; render(pageNum); };
                    })(i);
                    pageNumbers.appendChild(btn);
                }
                prevBtn.disabled = page <= 1;
                nextBtn.disabled = page >= totalPages || totalPages <= 0;
            }
            prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; render(currentPage); } };
            nextBtn.onclick = () => {
                const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
                if (currentPage < totalPages) { currentPage++; render(currentPage); }
            };

            function esc(s) {
                if (s == null || s === undefined) return '';
                const d = document.createElement('div');
                d.textContent = s;
                return d.innerHTML;
            }
            function escAttr(s) {
                return esc(s).replace(/"/g, '&quot;');
            }

            function buildRecordRow(order) {
                const statusKey = normalizeStatusKey(order.status);
                const statusDisplay = String(order.status || '').trim() || 'Pending';
                const amountFormatted = typeof order.amount === 'number' ? order.amount.toFixed(2) : (parseFloat(order.amount) || 0).toFixed(2);
                const downpaymentValue = (typeof order.downpayment !== 'undefined' && order.downpayment !== null) ? order.downpayment : 0;
                const actionHtml = '<div style="display:flex;gap:6px;align-items:center;">' +
                    '<button type="button" class="action-btn view-order" title="View order details">' +
                    '<span class="material-symbols-outlined">visibility</span></button></div>';
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
                    ' data-driver-id="' + escAttr(order.driver_id || '') + '"' +
                    ' data-driver-name="' + escAttr(order.driver_name || '') + '"' +
                    ' data-driver-phone="' + escAttr(order.driver_phone || '') + '"' +
                    ' data-driver-image-url="' + escAttr(order.driver_image_url || '') + '">' +
                    '<td><strong>#' + esc(order.transaction_id) + '</strong><small>' + esc(order.created_at_formatted) + '</small></td>' +
                    '<td class="delivery-schedule-cell"><strong>' + esc(order.delivery_date_formatted) + ', ' + esc(order.delivery_time_formatted) + '</strong><small class="delivery-address">' + esc(order.delivery_address) + '</small></td>' +
                    '<td><strong>₱' + esc(amountFormatted.replace(/\B(?=(\d{3})+(?!\d))/g, ',')) + '</strong><small>' + esc(order.payment_method) + '</small></td>' +
                    '<td><span class="status-badge ' + escAttr(statusKey) + '">● ' + esc(statusDisplay) + '</span></td>' +
                    '<td>' + actionHtml + '</td></tr>';
            }

            async function loadRecords() {
                try {
                    const res = await fetch('{{ route('admin.orders.list') }}?scope=records', {
                        method: 'GET',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin'
                    });
                    if (!res.ok) {
                        recordsTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:30px;">Failed to load records.</td></tr>';
                        return;
                    }
                    const data = await res.json();
                    const orders = data.orders || [];
                    if (orders.length === 0) {
                        recordsTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:30px;">No records found.</td></tr>';
                        return;
                    }
                    recordsTbody.innerHTML = orders.map(buildRecordRow).join('');
                    allRows = Array.from(document.querySelectorAll(".orders-data tbody tr[data-order-id]"));
                    applyFilters(false);
                } catch (e) {
                    recordsTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:30px;">Failed to load records.</td></tr>';
                }
            }

            loadRecords();
        });
    </script>

    <script>
        const orderDetailsModal = document.getElementById("orderDetailsModal");
        const closeOrderDetails = document.getElementById("closeOrderDetails");
        const orderShowBaseUrl = "{{ url('admin/orders') }}";

        function normalizeStatusForDisplay(status) {
            const s = String(status || '').trim().toLowerCase();
            if (s === 'walk-in' || s === 'walk_in' || s === 'walk in') return 'Walk-In';
            if (s === 'preparing') return 'Preparing';
            if (s === 'assigned') return 'Assigned';
            if (s === 'completed' || s === 'delivered') return 'Delivered';
            if (s === 'cancelled') return 'Cancelled';
            return 'Pending';
        }
        function getStatusClass(status) {
            const s = String(status || '').trim().toLowerCase();
            if (s === 'walk-in' || s === 'walk_in' || s === 'walk in') return 'walk_in';
            if (s === 'preparing') return 'preparing';
            if (s === 'assigned') return 'assigned';
            if (s === 'completed' || s === 'delivered') return 'completed';
            if (s === 'cancelled') return 'cancelled';
            return 'pending';
        }
        function formatCurrency(amount) {
            return '₱' + parseFloat(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
                customer_name: row.dataset.customerName || '',
                customer_phone: row.dataset.customerPhone || '',
                customer_email: row.dataset.customerEmail || '',
                customer_image_url: row.dataset.customerImage || '',
                delivery_address: row.dataset.deliveryAddress || '',
                product_name: row.dataset.productName || '',
                product_type: row.dataset.productType || '',
                gallon_size: row.dataset.gallonSize || '',
                product_image_url: row.dataset.productImage || '',
                amount: amount, downpayment: downpayment, balance: balance,
                quantity: parseInt(row.dataset.quantity || '1', 10) || 1,
                payment_method: row.dataset.paymentMethod || '',
                driver_name: row.dataset.driverName || ''
            };
        }
        function fillOrderDetailsModal(orderData) {
            const amount = parseFloat(orderData.amount || 0) || 0;
            const downpayment = parseFloat(orderData.downpayment || 0) || 0;
            const balance = ('balance' in orderData && orderData.balance != null) ? parseFloat(orderData.balance || 0) || 0 : Math.max(0, amount - downpayment);
            const quantity = parseInt(orderData.quantity || orderData.qty || 1, 10) || 1;
            const status = normalizeStatusForDisplay(orderData.status);
            const statusClass = getStatusClass(orderData.status);
            const productType = orderData.product_type || '—';
            const gallonSize = orderData.gallon_size || '—';
            const driverName = orderData.driver_name || (orderData.driver && orderData.driver.name) || 'No driver assigned';
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
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.order) fillOrderDetailsModal(data.order);
                else fillOrderDetailsModal(buildOrderDetailsFromRow(row));
            } catch (e) {
                fillOrderDetailsModal(buildOrderDetailsFromRow(row));
            }
        }
        document.addEventListener("click", function(e) {
            const viewBtn = e.target.closest(".view-order");
            if (viewBtn) {
                const row = viewBtn.closest("tr[data-order-id]");
                if (row) { e.preventDefault(); openOrderDetailsModal(row); }
            }
        });
        closeOrderDetails.addEventListener("click", () => orderDetailsModal.classList.remove("show"));
        orderDetailsModal.addEventListener("click", function(e) {
            if (e.target === orderDetailsModal) orderDetailsModal.classList.remove("show");
        });
        document.addEventListener("keydown", function(e) {
            if (e.key === 'Escape' && orderDetailsModal.classList.contains("show")) orderDetailsModal.classList.remove("show");
        });
    </script>
</body>
</html>
