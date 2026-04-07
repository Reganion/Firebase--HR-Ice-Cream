@extends('admin.layout.layout')

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <link rel="icon" href="{{ asset('img/logo.png') }}" />
    @section('title', 'Dashboard')
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">


    <link rel="stylesheet" href="{{ asset('assets/css/Admin/dashboard.css') }}">
</head>

<body>
    @section('content')
        @php
            $topSellersLabelsData = $topSellersLabels ?? ['No Sales Data'];
            $topSellersValuesData = $topSellersValues ?? [1];
            $monthlySalesValuesData = $monthlySalesValues ?? array_fill(0, 12, 0);
        @endphp
        <div class="dashboard-page">

            <!-- SUMMARY BOXES -->
            <div class="summary-boxes">
                <div class="summary-box summary-total">
                    <h4>Total Orders</h4>
                    <h2>{{ $totalOrders ?? 0 }} <span class="icon"></span></h2>
                    <p>Last month: <strong>{{ $totalLastMonth ?? 0 }}</strong></p>
                </div>

                <div class="summary-box summary-assigned">
                    <h4>Assigned Orders</h4>
                    <h2>{{ $assignedCount ?? 0 }} <span class="icon"></span></h2>
                    <p>Last month: <strong>{{ $assignedLastMonth ?? 0 }}</strong></p>
                </div>

                <div class="summary-box summary-pending">
                    <h4>Pending Orders</h4>
                    <h2>{{ $pendingCount ?? 0 }} <span class="icon"></span></h2>
                    <p>Last month: <strong>{{ $pendingLastMonth ?? 0 }}</strong></p>
                </div>

                <div class="summary-box summary-completed">
                    <h4>Completed Orders</h4>
                    <h2>{{ $completedCount ?? 0 }} <span class="icon"></span></h2>
                    <p>Last month: <strong>{{ $completedLastMonth ?? 0 }}</strong></p>
                </div>
            </div>

            <!-- MAIN PANEL -->
            <div class="dashboard-main-panel">
                <div class="main-panel-grid">
                    <div class="panel-section chart-panel">
                        <div class="panel-header">
                            <h4>Top flavors this month</h4>
                            <select class="month-filter js-month-filter" data-chart="pie" aria-label="Filter top sellers by month">
                                @foreach ($availableMonths ?? [now()->format('Y-m')] as $month)
                                    <option value="{{ $month }}" {{ ($selectedMonth ?? now()->format('Y-m')) === $month ? 'selected' : '' }}>
                                        {{ $month === now()->format('Y-m') ? 'This month' : \Carbon\Carbon::createFromFormat('Y-m', $month)->format('M Y') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="chart-canvas-wrap">
                            <canvas id="topSellersPieChart"></canvas>
                        </div>
                    </div>
                    <div class="panel-section chart-panel">
                        <div class="panel-header">
                            <h4>Total sales this year</h4>
                            <select class="year-filter js-year-filter" data-chart="bar" aria-label="Filter monthly sales by year">
                                @foreach ($availableYears ?? [now()->year] as $year)
                                    <option value="{{ $year }}" {{ (int) ($selectedYear ?? now()->year) === (int) $year ? 'selected' : '' }}>
                                        {{ (int) $year === (int) now()->year ? 'This year' : $year }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="chart-canvas-wrap">
                            <canvas id="monthlySalesBarChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="orders-view-modal orders-view-modal--orders" id="completedOrdersModal">
            <div class="orders-view-card">
                <div class="orders-view-header">
                    <h3 id="completedOrdersModalTitle">Completed Orders</h3>
                    <button class="orders-view-close" id="closeCompletedOrdersModal">&times;</button>
                </div>
                <div class="orders-view-content">
                    <div class="details-section-card">
                        <div class="completed-orders-summary">
                            <div class="completed-orders-summary-item">
                                <span>Total Sales</span>
                                <strong id="completedOrdersTotalPrice">₱0.00</strong>
                            </div>
                            <div class="completed-orders-summary-item">
                                <span>Total Orders</span>
                                <strong id="completedOrdersTotalCount">0</strong>
                            </div>
                        </div>
                        <h4 class="details-section-title">Completed Orders List</h4>
                        <div id="completedOrdersList" class="completed-orders-list">
                            <p class="completed-orders-empty">Select a month bar to load orders.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            (function() {
                if (typeof Chart === 'undefined') return;
                const isPhone = window.matchMedia('(max-width: 480px)').matches;
                const selectedYear = Number(@json($selectedYear ?? now()->year));
                const selectedMonth = @json($selectedMonth ?? now()->format('Y-m'));
                const dashboardChartDataUrl = @json(route('admin.dashboard.chart-data'));
                const dashboardCompletedOrdersUrl = @json(route('admin.dashboard.completed-orders'));
                const currentYear = Number(new Date().getFullYear());
                const now = new Date();
                const currentMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

                const monthlySalesLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const yStep = 5000;
                const initialTopSellersLabels = @json($topSellersLabelsData);
                const initialTopSellersValues = @json($topSellersValuesData);
                const initialMonthlySalesValues = (@json($monthlySalesValuesData) || []).map(v => Number(v) || 0);

                const piePercentLabelPlugin = {
                    id: 'piePercentLabelPlugin',
                    afterDatasetsDraw(chart) {
                        const { ctx } = chart;
                        const meta = chart.getDatasetMeta(0);
                        const data = chart.data.datasets[0].data || [];
                        const total = data.reduce((sum, v) => sum + (Number(v) || 0), 0);
                        ctx.save();
                        ctx.fillStyle = '#1f2937';
                        ctx.font = `${isPhone ? 11 : 13}px Inter, sans-serif`;
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';

                        meta.data.forEach((arc, index) => {
                            const value = Number(data[index]) || 0;
                            if (value <= 0) return;
                            const pos = arc.tooltipPosition();
                            const percent = total > 0 ? Math.round((value / total) * 100) : 0;
                            ctx.fillText(`${percent}%`, pos.x, pos.y);
                        });
                        ctx.restore();
                    }
                };

                const pieEl = document.getElementById('topSellersPieChart');
                const barEl = document.getElementById('monthlySalesBarChart');
                const completedOrdersModal = document.getElementById('completedOrdersModal');
                const completedOrdersModalTitle = document.getElementById('completedOrdersModalTitle');
                const completedOrdersTotalPrice = document.getElementById('completedOrdersTotalPrice');
                const completedOrdersTotalCount = document.getElementById('completedOrdersTotalCount');
                const completedOrdersList = document.getElementById('completedOrdersList');
                const closeCompletedOrdersModal = document.getElementById('closeCompletedOrdersModal');
                let pieChartInstance = null;
                let barChartInstance = null;

                function formatCurrency(value) {
                    return `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                }

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text == null ? '' : String(text);
                    return div.innerHTML;
                }

                function renderCompletedOrdersList(orders) {
                    if (!completedOrdersList) return;
                    const safeOrders = Array.isArray(orders) ? orders : [];
                    const totalPrice = safeOrders.reduce((sum, order) => sum + (Number(order.amount) || 0), 0);
                    if (completedOrdersTotalPrice) completedOrdersTotalPrice.textContent = formatCurrency(totalPrice);
                    if (completedOrdersTotalCount) completedOrdersTotalCount.textContent = String(safeOrders.length);
                    if (!safeOrders.length) {
                        completedOrdersList.innerHTML = '<p class="completed-orders-empty">No completed orders for this month.</p>';
                        return;
                    }

                    completedOrdersList.innerHTML = safeOrders.map(order => `
                        <article class="completed-order-item">
                            <div class="completed-order-main">
                                <img src="${escapeHtml(order.product_image_url || "{{ asset('img/default-product.png') }}")}" alt="Product">
                                <div class="completed-order-info">
                                    <strong>${escapeHtml(order.product_name || 'Unknown product')}</strong>
                                    <small>#${escapeHtml(order.transaction_id || '—')}</small>
                                    <small>${escapeHtml(order.customer_name || '—')} · ${escapeHtml(order.delivery_date_formatted || '—')} ${escapeHtml(order.delivery_time_formatted || '')}</small>
                                </div>
                            </div>
                            <div class="completed-order-meta">
                                <span class="status-badge-details completed">Completed</span>
                                <strong>${formatCurrency(order.amount)}</strong>
                            </div>
                        </article>
                    `).join('');
                }

                async function openCompletedOrdersModal(monthIndex) {
                    const monthNum = Number(monthIndex) + 1;
                    const monthLabel = monthlySalesLabels[monthIndex] || 'Month';
                    const activeYear = Number(barYear) || selectedYear;
                    if (completedOrdersModalTitle) {
                        completedOrdersModalTitle.textContent = `Completed Orders - ${monthLabel} ${activeYear}`;
                    }
                    if (completedOrdersTotalPrice) completedOrdersTotalPrice.textContent = '...';
                    if (completedOrdersTotalCount) completedOrdersTotalCount.textContent = '...';
                    if (completedOrdersList) {
                        completedOrdersList.innerHTML = '<p class="completed-orders-empty">Loading completed orders...</p>';
                    }
                    if (completedOrdersModal) completedOrdersModal.classList.add('show');

                    try {
                        const endpoint = new URL(dashboardCompletedOrdersUrl, window.location.origin);
                        endpoint.searchParams.set('year', String(activeYear));
                        endpoint.searchParams.set('month', String(monthNum));
                        const response = await fetch(endpoint.toString(), {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        });
                        if (!response.ok) throw new Error('Failed to load completed orders');
                        const payload = await response.json();
                        renderCompletedOrdersList(payload.orders || []);
                    } catch (error) {
                        if (completedOrdersList) {
                            completedOrdersList.innerHTML = '<p class="completed-orders-empty">Failed to load completed orders.</p>';
                        }
                        console.error(error);
                    }
                }

                function renderPieChart(labels, values) {
                    if (!pieEl) return;
                    if (!pieChartInstance) {
                        pieChartInstance = new Chart(pieEl, {
                            type: 'pie',
                            data: {
                                labels: labels,
                                datasets: [{
                                    data: values,
                                    backgroundColor: ['#f4b531', '#ff1f6a', '#8daa3d'],
                                    borderWidth: 0
                                }]
                            },
                            plugins: [piePercentLabelPlugin],
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: {
                                    duration: 420,
                                    easing: 'easeOutCubic'
                                },
                                plugins: {
                                    legend: {
                                        position: 'top',
                                        align: 'center',
                                        labels: {
                                            usePointStyle: true,
                                            pointStyle: 'rect',
                                            boxWidth: isPhone ? 10 : 14,
                                            color: '#374151',
                                            font: {
                                                size: isPhone ? 10 : 14
                                            }
                                        }
                                    }
                                }
                            }
                        });
                        return;
                    }

                    pieChartInstance.data.labels = labels;
                    pieChartInstance.data.datasets[0].data = values;
                    pieChartInstance.update();
                }

                function renderBarChart(values) {
                    if (!barEl) return;
                    const cleanValues = (values || []).map(v => Number(v) || 0);
                    const monthlyMaxValue = Math.max(...cleanValues, 0);
                    const yAxisMax = Math.max(30000, Math.ceil(monthlyMaxValue / yStep) * yStep);
                    if (!barChartInstance) {
                        barChartInstance = new Chart(barEl, {
                            type: 'bar',
                            data: {
                                labels: monthlySalesLabels,
                                datasets: [{
                                    label: 'Sales',
                                    data: cleanValues,
                                    backgroundColor: '#3b82f6',
                                    borderRadius: 2,
                                    maxBarThickness: 30
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                onClick: function(event, elements) {
                                    if (!elements || !elements.length) return;
                                    const index = Number(elements[0].index);
                                    if (!Number.isFinite(index)) return;
                                    openCompletedOrdersModal(index);
                                },
                                onHover: function(event, elements) {
                                    const target = event?.native?.target;
                                    if (target && target.style) {
                                        target.style.cursor = elements && elements.length ? 'pointer' : 'default';
                                    }
                                },
                                animation: {
                                    duration: 420,
                                    easing: 'easeOutCubic'
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        max: yAxisMax,
                                        grid: {
                                            color: '#dfe3ea',
                                            drawBorder: false
                                        },
                                        ticks: {
                                            stepSize: yStep,
                                            color: '#4b5563',
                                            font: {
                                                size: isPhone ? 10 : 12
                                            },
                                            callback: function(value) {
                                                return Number(value).toLocaleString('en-PH');
                                            }
                                        }
                                    },
                                    x: {
                                        grid: {
                                            display: false,
                                            drawBorder: false
                                        },
                                        ticks: {
                                            autoSkip: false,
                                            maxRotation: isPhone ? 50 : 0,
                                            minRotation: isPhone ? 50 : 0,
                                            color: '#374151',
                                            font: {
                                                size: isPhone ? 10 : 12
                                            }
                                        }
                                    }
                                }
                            }
                        });
                        return;
                    }

                    barChartInstance.data.datasets[0].data = cleanValues;
                    barChartInstance.options.scales.y.max = yAxisMax;
                    barChartInstance.update();
                }

                function formatYearLabel(year) {
                    const yearNum = Number(year) || currentYear;
                    return yearNum === currentYear ? 'This year' : String(yearNum);
                }

                async function fetchDashboardData(year, month) {
                    const endpoint = new URL(dashboardChartDataUrl, window.location.origin);
                    endpoint.searchParams.set('year', String(year));
                    endpoint.searchParams.set('month', String(month));
                    const response = await fetch(endpoint.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });
                    if (!response.ok) {
                        throw new Error('Failed to fetch dashboard chart data');
                    }
                    return response.json();
                }

                function formatMonthLabel(monthKey) {
                    if (!monthKey || monthKey === currentMonth) return 'This month';
                    const [y, m] = String(monthKey).split('-').map(Number);
                    if (!y || !m) return monthKey;
                    const names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    return `${names[m - 1] || ''} ${y}`;
                }

                function syncMonthOptionsForFilter(filter, months, activeMonth) {
                    const list = Array.from(new Set((months || []).filter(Boolean)));
                    if (!list.length) return;
                    filter.innerHTML = list
                        .map(m => `<option value="${m}">${formatMonthLabel(m)}</option>`)
                        .join('');
                    filter.value = String(activeMonth);
                }

                function syncYearOptionsForFilter(filter, years, activeYear) {
                    const uniqueYears = Array.from(new Set((years || []).map(y => Number(y)).filter(Boolean)));
                    if (!uniqueYears.length) return;
                    filter.innerHTML = uniqueYears
                        .map(year => `<option value="${year}">${formatYearLabel(year)}</option>`)
                        .join('');
                    filter.value = String(activeYear);
                }

                function setFilterLoading(filter, isLoading) {
                    filter.disabled = isLoading;
                    filter.style.opacity = isLoading ? '0.72' : '1';
                    filter.style.cursor = isLoading ? 'wait' : 'pointer';
                }

                function readSavedFilterYear(chartType) {
                    try {
                        const key = chartType === 'bar' ? 'admin.dashboard.barYear' : null;
                        if (!key) return null;
                        const raw = window.localStorage.getItem(key);
                        const year = Number(raw);
                        return Number.isFinite(year) && year > 0 ? year : null;
                    } catch (error) {
                        return null;
                    }
                }

                function saveFilterYear(chartType, year) {
                    try {
                        const key = chartType === 'bar' ? 'admin.dashboard.barYear' : null;
                        if (key) window.localStorage.setItem(key, String(year));
                    } catch (error) {
                        // Ignore storage failures (private mode, storage blocked, etc.)
                    }
                }

                function readSavedFilterMonth() {
                    try {
                        const raw = window.localStorage.getItem('admin.dashboard.pieMonth');
                        if (raw && /^\d{4}-\d{2}$/.test(raw)) return raw;
                        return null;
                    } catch (error) {
                        return null;
                    }
                }

                function saveFilterMonth(month) {
                    try {
                        window.localStorage.setItem('admin.dashboard.pieMonth', String(month));
                    } catch (error) {
                        // Ignore storage failures
                    }
                }

                function renderDashboardCharts(payload) {
                    const labels = Array.isArray(payload.topSellersLabels) && payload.topSellersLabels.length
                        ? payload.topSellersLabels
                        : ['No Sales Data'];
                    const values = Array.isArray(payload.topSellersValues) && payload.topSellersValues.length
                        ? payload.topSellersValues
                        : [1];
                    const monthlyValues = Array.isArray(payload.monthlySalesValues)
                        ? payload.monthlySalesValues
                        : Array(12).fill(0);
                    renderPieChart(labels, values);
                    renderBarChart(monthlyValues);
                }

                renderDashboardCharts({
                    topSellersLabels: initialTopSellersLabels,
                    topSellersValues: initialTopSellersValues,
                    monthlySalesValues: initialMonthlySalesValues
                });

                function resizeCharts() {
                    if (pieChartInstance) pieChartInstance.resize();
                    if (barChartInstance) barChartInstance.resize();
                }
                window.addEventListener('resize', resizeCharts);
                setTimeout(resizeCharts, 100);
                requestAnimationFrame(resizeCharts);

                let pieMonth = readSavedFilterMonth() || selectedMonth;
                let barYear = readSavedFilterYear('bar') || selectedYear;
                const monthFilters = Array.from(document.querySelectorAll('.js-month-filter'));
                const yearFilters = Array.from(document.querySelectorAll('.js-year-filter'));

                monthFilters.forEach(filter => {
                    filter.value = String(pieMonth);
                    filter.addEventListener('change', async function() {
                        const selected = this.value || currentMonth;
                        setFilterLoading(this, true);
                        try {
                            const data = await fetchDashboardData(barYear, selected);
                            const active = data.selectedMonth || selected;
                            syncMonthOptionsForFilter(this, data.availableMonths || [], active);
                            pieMonth = active;
                            saveFilterMonth(active);
                            const labels = Array.isArray(data.topSellersLabels) && data.topSellersLabels.length
                                ? data.topSellersLabels
                                : ['No Sales Data'];
                            const values = Array.isArray(data.topSellersValues) && data.topSellersValues.length
                                ? data.topSellersValues
                                : [1];
                            renderPieChart(labels, values);
                        } catch (error) {
                            console.error(error);
                        } finally {
                            setFilterLoading(this, false);
                        }
                    });
                });

                yearFilters.forEach(filter => {
                    filter.value = String(barYear);
                    filter.addEventListener('change', async function() {
                        const selected = Number(this.value) || selectedYear;
                        setFilterLoading(this, true);
                        try {
                            const data = await fetchDashboardData(selected, pieMonth);
                            const active = Number(data.selectedYear) || selected;
                            syncYearOptionsForFilter(this, data.availableYears || [], active);
                            barYear = active;
                            saveFilterYear('bar', active);
                            const values = Array.isArray(data.monthlySalesValues)
                                ? data.monthlySalesValues
                                : Array(12).fill(0);
                            renderBarChart(values);
                        } catch (error) {
                            console.error(error);
                        } finally {
                            setFilterLoading(this, false);
                        }
                    });
                });

                const pieFilter = monthFilters[0];
                const barFilter = yearFilters[0];
                if (pieFilter && pieMonth !== selectedMonth) {
                    pieFilter.value = String(pieMonth);
                    pieFilter.dispatchEvent(new Event('change'));
                }
                if (barFilter && barYear !== selectedYear) {
                    barFilter.value = String(barYear);
                    barFilter.dispatchEvent(new Event('change'));
                }

                if (closeCompletedOrdersModal && completedOrdersModal) {
                    closeCompletedOrdersModal.addEventListener('click', () => {
                        completedOrdersModal.classList.remove('show');
                    });
                    completedOrdersModal.addEventListener('click', (event) => {
                        if (event.target === completedOrdersModal) {
                            completedOrdersModal.classList.remove('show');
                        }
                    });
                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape' && completedOrdersModal.classList.contains('show')) {
                            completedOrdersModal.classList.remove('show');
                        }
                    });
                }
            })();
        </script>
    @endsection

</body>

</html>
