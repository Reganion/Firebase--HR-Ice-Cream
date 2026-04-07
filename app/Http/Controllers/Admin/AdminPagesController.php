<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirestoreService;
use App\Support\FirestoreCacheKeys;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminPagesController extends Controller
{
    public function __construct(private FirestoreService $firestore)
    {
    }

    public function login(Request $request)
    {
        if ($request->session()->has('admin_id')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function dashboard(Request $request)
    {
        $now = Carbon::now();
        $startOfThisMonth = $now->copy()->startOfMonth();
        $endOfThisMonth = $now->copy()->endOfMonth();
        $throughToday = $now->copy()->endOfDay(); // Summary boxes: from start of this month through today only
        // Last month = calendar month before this one (same date logic as bar chart & completed-orders modal)
        $firstDayLastMonth = $now->copy()->startOfMonth()->subMonthNoOverflow();
        $startOfLastMonth = $firstDayLastMonth->copy()->startOfMonth();
        $endOfLastMonth = $firstDayLastMonth->copy()->endOfMonth();
        $currentYear = (int) $now->year;
        $currentMonth = $now->format('Y-m');
        $availableYears = $this->getDashboardAvailableYears($currentYear);
        $availableMonths = $this->getDashboardAvailableMonths($currentMonth);
        $selectedYear = $currentYear;
        $selectedMonth = $request->query('month', $currentMonth);
        if (!in_array($selectedMonth, $availableMonths, true)) {
            $selectedMonth = $currentMonth;
        }

        // Summary boxes: orders from start of this month through today
        $totalOrders = $this->countOrdersBetween($startOfThisMonth, $throughToday);
        $assignedCount = $this->countOrdersByStatusBetween('assigned', $startOfThisMonth, $throughToday);
        $pendingCount = $this->countOrdersByStatusBetween('pending', $startOfThisMonth, $throughToday);
        $completedCount = $this->countOrdersByStatusBetween('completed', $startOfThisMonth, $throughToday);

        // Use same date as bar chart & modal: COALESCE(delivery_date, created_at) so "last month" matches
        $totalLastMonth = $this->countOrdersByOrderDateBetween($startOfLastMonth, $endOfLastMonth);
        $assignedLastMonth = $this->countOrdersByStatusAndOrderDateBetween('assigned', $startOfLastMonth, $endOfLastMonth);
        $pendingLastMonth = $this->countOrdersByStatusAndOrderDateBetween('pending', $startOfLastMonth, $endOfLastMonth);
        $completedLastMonth = $this->countOrdersByStatusAndOrderDateBetween('completed', $startOfLastMonth, $endOfLastMonth);

        $topSellersForMonth = $this->getTopSellersForMonth($selectedMonth);
        $chartData = $this->getDashboardChartData($selectedYear);
        $topSellersLabels = $topSellersForMonth['topSellersLabels'];
        $topSellersValues = $topSellersForMonth['topSellersValues'];
        $monthlySalesValues = $chartData['monthlySalesValues'];

        return view('admin.dashboard', compact(
            'totalOrders',
            'assignedCount',
            'pendingCount',
            'completedCount',
            'totalLastMonth',
            'assignedLastMonth',
            'pendingLastMonth',
            'completedLastMonth',
            'selectedYear',
            'availableYears',
            'selectedMonth',
            'availableMonths',
            'topSellersLabels',
            'topSellersValues',
            'monthlySalesValues'
        ));
    }

    /**
     * Count orders by created_at in the date range (same as Orders page: "from start of this month").
     * Includes all statuses so Total Orders matches the count on the Orders page.
     */
    private function countOrdersBetween(Carbon $startDate, Carbon $endDate): int
    {
        return collect($this->firestore->rememberAll('orders', 45))
            ->filter(function (array $order) use ($startDate, $endDate) {
                if (empty($order['created_at'])) {
                    return false;
                }
                $created = Carbon::parse((string) $order['created_at']);
                return $created->betweenIncluded($startDate, $endDate);
            })
            ->count();
    }

    /**
     * Count orders by status, using created_at for date range (consistent with Orders page and Total).
     * 'completed' matches both completed and delivered.
     */
    private function countOrdersByStatusBetween(string $status, Carbon $startDate, Carbon $endDate): int
    {
        $status = strtolower(trim($status));
        return collect($this->firestore->rememberAll('orders', 45))
            ->filter(function (array $order) use ($startDate, $endDate, $status) {
                if (empty($order['created_at'])) {
                    return false;
                }
                $created = Carbon::parse((string) $order['created_at']);
                if (!$created->betweenIncluded($startDate, $endDate)) {
                    return false;
                }
                $s = strtolower(trim((string) ($order['status'] ?? '')));
                if ($status === 'completed') {
                    return in_array($s, ['completed', 'delivered'], true);
                }
                return $s === $status;
            })
            ->count();
    }

    /**
     * Count orders by order date (delivery_date or created_at) in the range.
     * Matches bar chart and completed-orders modal so "last month" summary is consistent.
     */
    private function countOrdersByOrderDateBetween(Carbon $startDate, Carbon $endDate): int
    {
        return collect($this->firestore->rememberAll('orders', 45))
            ->filter(function (array $order) use ($startDate, $endDate) {
                $date = !empty($order['delivery_date'])
                    ? Carbon::parse((string) $order['delivery_date'])
                    : (!empty($order['created_at']) ? Carbon::parse((string) $order['created_at']) : null);
                if ($date === null) {
                    return false;
                }
                return $date->toDateString() >= $startDate->toDateString()
                    && $date->toDateString() <= $endDate->toDateString();
            })
            ->count();
    }

    /**
     * Count orders by status, using order date (delivery_date or created_at) for the range.
     * 'completed' matches both completed and delivered. Used for "last month" summary to match bar chart.
     */
    private function countOrdersByStatusAndOrderDateBetween(string $status, Carbon $startDate, Carbon $endDate): int
    {
        $status = strtolower(trim($status));
        return collect($this->firestore->rememberAll('orders', 45))
            ->filter(function (array $order) use ($startDate, $endDate, $status) {
                $date = !empty($order['delivery_date'])
                    ? Carbon::parse((string) $order['delivery_date'])
                    : (!empty($order['created_at']) ? Carbon::parse((string) $order['created_at']) : null);
                if ($date === null) {
                    return false;
                }
                $inRange = $date->toDateString() >= $startDate->toDateString()
                    && $date->toDateString() <= $endDate->toDateString();
                if (!$inRange) {
                    return false;
                }
                $s = strtolower(trim((string) ($order['status'] ?? '')));
                if ($status === 'completed') {
                    return in_array($s, ['completed', 'delivered'], true);
                }
                return $s === $status;
            })
            ->count();
    }

    public function dashboardChartData(Request $request)
    {
        $now = Carbon::now();
        $currentYear = (int) $now->year;
        $currentMonth = $now->format('Y-m');
        $availableYears = $this->getDashboardAvailableYears($currentYear);
        $availableMonths = $this->getDashboardAvailableMonths($currentMonth);
        $selectedYear = (int) $request->query('year', $currentYear);
        $selectedMonth = (string) $request->query('month', $currentMonth);
        if (!in_array($selectedYear, $availableYears, true)) {
            $selectedYear = $currentYear;
        }
        if (!in_array($selectedMonth, $availableMonths, true)) {
            $selectedMonth = $currentMonth;
        }

        $topSellersForMonth = $this->getTopSellersForMonth($selectedMonth);
        $chartData = $this->getDashboardChartData($selectedYear);

        return response()->json([
            'selectedYear' => $selectedYear,
            'availableYears' => $availableYears,
            'selectedMonth' => $selectedMonth,
            'availableMonths' => $availableMonths,
            'topSellersLabels' => $topSellersForMonth['topSellersLabels'],
            'topSellersValues' => $topSellersForMonth['topSellersValues'],
            'monthlySalesValues' => $chartData['monthlySalesValues'],
        ]);
    }

    public function dashboardCompletedOrders(Request $request)
    {
        $now = Carbon::now();
        $year = (int) $request->query('year', $now->year);
        $month = (int) $request->query('month', $now->month);
        $month = max(1, min(12, $month));

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $orders = collect($this->firestore->rememberAll('orders', 45))
            ->filter(function (array $order) use ($startOfMonth, $endOfMonth) {
                $status = strtolower(trim((string) ($order['status'] ?? '')));
                if (!in_array($status, ['completed', 'delivered'], true)) {
                    return false;
                }
                $date = !empty($order['delivery_date'])
                    ? Carbon::parse((string) $order['delivery_date'])
                    : (!empty($order['created_at']) ? Carbon::parse((string) $order['created_at']) : null);
                if ($date === null) {
                    return false;
                }
                $d = $date->toDateString();
                return $d >= $startOfMonth && $d <= $endOfMonth;
            })
            ->sortByDesc(function (array $order) {
                return (string) ($order['delivery_date'] ?? $order['created_at'] ?? '');
            })
            ->values()
            ->map(function (array $order) {
                $deliveryDate = !empty($order['delivery_date']) ? Carbon::parse((string) $order['delivery_date']) : null;
                $deliveryTime = !empty($order['delivery_time']) ? Carbon::parse((string) $order['delivery_time']) : null;
                $createdAt = !empty($order['created_at']) ? Carbon::parse((string) $order['created_at']) : null;
                return [
                    'id' => $order['id'] ?? '',
                    'transaction_id' => $order['transaction_id'] ?? '',
                    'customer_name' => $order['customer_name'] ?? '',
                    'product_name' => $order['product_name'] ?? '',
                    'product_type' => $order['product_type'] ?? '',
                    'gallon_size' => $order['gallon_size'] ?? '',
                    'delivery_address' => $order['delivery_address'] ?? '',
                    'customer_phone' => $order['customer_phone'] ?? '',
                    'customer_image_url' => asset((string) ($order['customer_image'] ?? 'img/default-user.png')),
                    'product_image_url' => asset((string) ($order['product_image'] ?? 'img/default-product.png')),
                    'payment_method' => $order['payment_method'] ?? '',
                    'amount' => (float) ($order['amount'] ?? 0),
                    'status' => 'completed',
                    'delivery_date_formatted' => $deliveryDate ? $deliveryDate->format('d M Y') : '—',
                    'delivery_time_formatted' => $deliveryTime ? $deliveryTime->format('h:i A') : '—',
                    'created_at_formatted' => $createdAt ? $createdAt->format('d M Y') : '—',
                ];
            })
            ->values();

        return response()->json([
            'selectedYear' => $year,
            'selectedMonth' => str_pad((string) $month, 2, '0', STR_PAD_LEFT),
            'orders' => $orders,
        ]);
    }

    private function getDashboardAvailableYears(int $fallbackYear): array
    {
        $years = collect($this->firestore->rememberAll('orders', 45))
            ->map(function (array $order) {
                $date = !empty($order['delivery_date'])
                    ? Carbon::parse((string) $order['delivery_date'])
                    : (!empty($order['created_at']) ? Carbon::parse((string) $order['created_at']) : null);
                return $date ? (int) $date->year : 0;
            })
            ->filter(fn ($year) => $year > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        if (empty($years)) {
            return [$fallbackYear];
        }

        if (!in_array($fallbackYear, $years, true)) {
            array_unshift($years, $fallbackYear);
        }

        return array_values(array_unique($years));
    }

    private function getDashboardAvailableMonths(string $fallbackMonth): array
    {
        $months = collect($this->firestore->rememberAll('orders', 45))
            ->map(function (array $order) {
                $date = !empty($order['delivery_date'])
                    ? Carbon::parse((string) $order['delivery_date'])
                    : (!empty($order['created_at']) ? Carbon::parse((string) $order['created_at']) : null);
                return $date ? $date->format('Y-m') : '';
            })
            ->map(fn ($m) => (string) $m)
            ->filter(fn ($m) => $m !== '')
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        if (empty($months)) {
            return [$fallbackMonth];
        }

        if (!in_array($fallbackMonth, $months, true)) {
            array_unshift($months, $fallbackMonth);
        }

        return array_values(array_unique($months));
    }

    private function getTopSellersForMonth(string $yearMonth): array
    {
        $date = Carbon::createFromFormat('Y-m', $yearMonth);
        $startOfMonth = $date->copy()->startOfMonth()->toDateString();
        $endOfMonth = $date->copy()->endOfMonth()->toDateString();

        $buildTopSellersQuery = function () use ($startOfMonth, $endOfMonth) {
            $rows = collect($this->firestore->rememberAll('orders', 45))
                ->filter(function (array $order) use ($startOfMonth, $endOfMonth) {
                    $date = !empty($order['delivery_date'])
                        ? Carbon::parse((string) $order['delivery_date'])
                        : (!empty($order['created_at']) ? Carbon::parse((string) $order['created_at']) : null);
                    if ($date === null) {
                        return false;
                    }
                    $d = $date->toDateString();
                    if (!($d >= $startOfMonth && $d <= $endOfMonth)) {
                        return false;
                    }
                    $status = strtolower(trim((string) ($order['status'] ?? '')));
                    return in_array($status, ['completed', 'delivered'], true);
                })
                ->groupBy(fn (array $order) => trim((string) ($order['product_name'] ?? '')))
                ->map(function ($group, $name) {
                    return (object) [
                        'product_name' => $name,
                        'total_qty' => collect($group)->sum(fn (array $o) => (int) ($o['qty'] ?? 1)),
                    ];
                })
                ->sortByDesc('total_qty')
                ->take(3)
                ->values();

            return $rows;
        };

        $topSellers = $buildTopSellersQuery();

        $topSellers = $topSellers
            ->filter(function ($row) {
                return trim((string) ($row->product_name ?? '')) !== '';
            })
            ->values();

        if ($topSellers->isEmpty()) {
            return [
                'topSellersLabels' => ['No Sales Data'],
                'topSellersValues' => [1],
            ];
        }

        $topSellersLabels = $topSellers
            ->map(function ($row) {
                $name = trim((string) ($row->product_name ?? ''));
                return $name !== '' ? $name : 'Unknown Product';
            })
            ->values()
            ->all();
        $topSellersValues = $topSellers->pluck('total_qty')->map(fn ($v) => (int) $v)->values()->all();

        return [
            'topSellersLabels' => $topSellersLabels,
            'topSellersValues' => $topSellersValues,
        ];
    }

    private function getDashboardChartData(int $year): array
    {
        $startOfYear = Carbon::create($year, 1, 1)->toDateString();
        $endOfYear = Carbon::create($year, 12, 31)->toDateString();

        $completedOrders = collect($this->firestore->rememberAll('orders', 45))
            ->filter(function (array $order) use ($startOfYear, $endOfYear) {
                $status = strtolower(trim((string) ($order['status'] ?? '')));
                if (!in_array($status, ['completed', 'delivered'], true)) {
                    return false;
                }
                $date = !empty($order['delivery_date'])
                    ? Carbon::parse((string) $order['delivery_date'])
                    : (!empty($order['created_at']) ? Carbon::parse((string) $order['created_at']) : null);
                if ($date === null) {
                    return false;
                }
                $d = $date->toDateString();
                return $d >= $startOfYear && $d <= $endOfYear;
            })
            ->values();

        $topSellers = $completedOrders
            ->groupBy(fn (array $order) => trim((string) ($order['product_name'] ?? '')))
            ->map(function ($group, $name) {
                return (object) [
                    'product_name' => $name,
                    'total_qty' => collect($group)->sum(fn (array $o) => (int) ($o['qty'] ?? 1)),
                ];
            })
            ->sortByDesc('total_qty')
            ->take(3)
            ->values();

        if ($topSellers->isEmpty()) {
            $topSellersLabels = ['No Sales Data'];
            $topSellersValues = [1];
        } else {
            $topSellersLabels = $topSellers
                ->map(function ($row) {
                    $name = trim((string) ($row->product_name ?? ''));
                    return $name !== '' ? $name : 'Unknown Product';
                })
                ->values()
                ->all();
            $topSellersValues = $topSellers->pluck('total_qty')->map(fn ($v) => (int) $v)->values()->all();
        }

        $salesByMonth = $completedOrders
            ->groupBy(function (array $order) {
                $date = !empty($order['delivery_date'])
                    ? Carbon::parse((string) $order['delivery_date'])
                    : Carbon::parse((string) $order['created_at']);
                return (int) $date->month;
            })
            ->map(fn ($group) => (float) collect($group)->sum(fn (array $o) => (float) ($o['amount'] ?? 0)));

        $monthlySalesValues = collect(range(1, 12))
            ->map(fn ($month) => (int) round((float) ($salesByMonth[$month] ?? 0)))
            ->values()
            ->all();

        return [
            'topSellersLabels' => $topSellersLabels,
            'topSellersValues' => $topSellersValues,
            'monthlySalesValues' => $monthlySalesValues,
        ];
    }

    public function flavors(Request $request)
    {
        $sortBy = $request->query('sort_by', 'created_at');
        $order = strtolower($request->query('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $flavors = collect($this->firestore->rememberAll('flavors', 120));
        $flavorTypesRaw = collect($this->firestore->rememberAll('ingredients', 120))
            ->filter(fn (array $row) => strtolower((string) ($row['type'] ?? '')) === 'flavor')
            ->sortBy(fn (array $row) => strtolower((string) ($row['name'] ?? '')))
            ->values();

        if ($sortBy === 'price') {
            $flavors = $order === 'asc'
                ? $flavors->sortBy(fn (array $row) => (float) ($row['price'] ?? 0))
                : $flavors->sortByDesc(fn (array $row) => (float) ($row['price'] ?? 0));
        } elseif ($sortBy === 'status') {
            $flavorStatusByName = $flavorTypesRaw
                ->mapWithKeys(fn (array $row) => [strtolower((string) ($row['name'] ?? '')) => strtolower((string) ($row['status'] ?? 'out'))]);

            $flavors = $order === 'asc'
                ? $flavors->sortBy(fn (array $row) => ($flavorStatusByName[strtolower((string) ($row['flavor_type'] ?? ''))] ?? 'out') === 'available' ? 0 : 1)
                : $flavors->sortByDesc(fn (array $row) => ($flavorStatusByName[strtolower((string) ($row['flavor_type'] ?? ''))] ?? 'out') === 'available' ? 0 : 1);
        } else {
            $flavors = $order === 'asc'
                ? $flavors->sortBy(fn (array $row) => (string) ($row['created_at'] ?? ''))
                : $flavors->sortByDesc(fn (array $row) => (string) ($row['created_at'] ?? ''));
        }

        $flavors = $flavors->values()->map(fn (array $row) => (object) $row);
        $flavorTypes = $flavorTypesRaw->map(fn (array $row) => (object) $row);

        return view('admin.flavors', compact('flavors', 'flavorTypes', 'sortBy', 'order'));
    }

    public function ingredients()
    {
        $ingredients = collect($this->firestore->rememberAll('ingredients', 120))
            ->sortBy(fn (array $row) => strtolower((string) ($row['name'] ?? '')))
            ->values()
            ->map(fn (array $row) => (object) $row);
        return view('admin.ingredients', compact('ingredients'));
    }

    public function gallon()
    {
        $gallons = collect($this->firestore->rememberAll('gallons', 120))
            ->sortBy(fn (array $row) => strtolower((string) ($row['size'] ?? '')))
            ->values()
            ->map(fn (array $row) => (object) $row);
        return view('admin.gallon', compact('gallons'));
    }

    public function orders()
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $statusRank = function (string $status): int {
            $s = strtolower(trim($status));
            return match (true) {
                $s === 'pending' => 1,
                $s === 'preparing' => 2,
                in_array($s, ['walk_in', 'walk-in', 'walk in', 'walkin'], true) => 3,
                $s === 'assigned' => 4,
                $s === 'ready' => 5,
                in_array($s, ['out for delivery', 'out_for_delivery'], true) => 6,
                in_array($s, ['completed', 'delivered'], true) => 7,
                $s === 'cancelled' => 8,
                default => 9,
            };
        };

        $orders = collect($this->firestore->rememberAll('orders', 45))
            ->filter(function (array $order) use ($startOfMonth) {
                if (empty($order['created_at'])) {
                    return false;
                }
                return Carbon::parse((string) $order['created_at'])->gte($startOfMonth);
            })
            ->sort(function (array $a, array $b) use ($statusRank) {
                $aRank = $statusRank((string) ($a['status'] ?? ''));
                $bRank = $statusRank((string) ($b['status'] ?? ''));
                if ($aRank !== $bRank) {
                    return $aRank <=> $bRank;
                }
                $aCreated = (string) ($a['created_at'] ?? '');
                $bCreated = (string) ($b['created_at'] ?? '');
                return strcmp($bCreated, $aCreated);
            })
            ->values()
            ->map(fn (array $row) => (object) $row);

        $flavors = collect($this->firestore->rememberAll('flavors', 120))
            ->sortBy(fn (array $row) => strtolower((string) ($row['name'] ?? '')))
            ->values()
            ->map(fn (array $row) => (object) $row);

        $gallons = collect($this->firestore->rememberAll('gallons', 120))
            ->sortBy(fn (array $row) => strtolower((string) ($row['size'] ?? '')))
            ->values()
            ->map(fn (array $row) => (object) $row);

        return view('admin.orders', compact('orders', 'flavors', 'gallons'));
    }

    public function records()
    {
        return view('admin.records');
    }

    public function supportCentre()
    {
        return view('admin.support-centre');
    }

    public function drivers()
    {
        $allowedStatuses = ['available', 'on_route', 'off_duty', 'deactivate'];
        $onlineKeyPrefix = 'api_driver_online:';

        // Single Firestore read (was loading all drivers twice per request).
        // No list-cache here: this action may PATCH driver status to match online keys.
        $hadDriverWrite = false;
        $drivers = collect($this->firestore->all('drivers'))
            ->filter(fn (array $driver) => in_array(strtolower((string) ($driver['status'] ?? '')), $allowedStatuses, true))
            ->sortByDesc(fn (array $driver) => (string) ($driver['created_at'] ?? ''))
            ->values()
            ->map(function (array $driver) use ($onlineKeyPrefix, &$hadDriverWrite): array {
                $status = strtolower((string) ($driver['status'] ?? ''));
                if (in_array($status, ['deactivate', 'archive', 'on_route'], true)) {
                    return $driver;
                }

                $driverId = (string) ($driver['id'] ?? '');
                if ($driverId === '') {
                    return $driver;
                }

                $isOnline = Cache::has($onlineKeyPrefix.$driverId);
                $expectedStatus = $isOnline ? 'available' : 'off_duty';

                if ($status !== $expectedStatus) {
                    $driver['status'] = $expectedStatus;
                    $this->firestore->update('drivers', $driverId, ['status' => $expectedStatus]);
                    $hadDriverWrite = true;
                }

                return $driver;
            })
            ->map(fn (array $driver) => (object) $driver);

        if ($hadDriverWrite) {
            FirestoreCacheKeys::invalidateDrivers();
        }

        return view('admin.drivers', compact('drivers'));
    }

    public function customer()
    {
        $addressesByCustomerId = collect($this->firestore->rememberAll('customer_addresses', 90))
            ->groupBy(fn (array $address) => (string) ($address['customer_id'] ?? ''));

        $customers = collect($this->firestore->rememberAll('customers', 90))
            ->sortByDesc(fn (array $customer) => (string) ($customer['created_at'] ?? ''))
            ->values()
            ->map(function (array $customer) use ($addressesByCustomerId) {
                $customerId = (string) ($customer['id'] ?? '');
                $firstName = trim((string) ($customer['firstname'] ?? ''));
                $lastName = trim((string) ($customer['lastname'] ?? ''));
                $fullName = trim($firstName . ' ' . $lastName);

                $addresses = collect($addressesByCustomerId->get($customerId, []))
                    ->sortByDesc(fn (array $address) => (int) ($address['is_default'] ?? 0))
                    ->sortByDesc(fn (array $address) => (string) ($address['created_at'] ?? ''))
                    ->map(fn (array $address) => (object) $address)
                    ->values();

                $customer['full_name'] = $fullName !== '' ? $fullName : 'Customer';
                $customer['status'] = strtolower((string) ($customer['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active';
                $customer['addresses'] = $addresses;

                return (object) $customer;
            });

        return view('admin.customer', compact('customers'));
    }

    public function account()
    {
        return view('admin.account');
    }

    public function reports()
    {
        return view('admin.reports');
    }

    public function archive()
    {
        $drivers = collect($this->firestore->rememberAll('drivers', 60))
            ->filter(fn (array $driver) => strtolower((string) ($driver['status'] ?? '')) === 'archive')
            ->sortByDesc(fn (array $driver) => (string) ($driver['created_at'] ?? ''))
            ->values()
            ->map(fn (array $driver) => (object) $driver);

        return view('admin.archive', compact('drivers'));
    }

}
