<?php

namespace Modules\AdminPaymentReport\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\AdminPaymentHistory\Models\PaymentHistory;
use Modules\AppPayments\Support\PaymentManager;

class PaymentReportService
{
    public function __construct(
        protected PaymentManager $payments,
    ) {}

    public function resolveDateRange(?string $from, ?string $to): array
    {
        $endDate = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        $startDate = $from ? Carbon::parse($from)->startOfDay() : $endDate->copy()->subDays(29)->startOfDay();

        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        return [$startDate, $endDate];
    }

    public function paymentInfo(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $periodDays = max(1, $startDate->diffInDays($endDate) + 1);
        $previousEnd = $startDate->copy()->subSecond();
        $previousStart = $startDate->copy()->subDays($periodDays)->startOfDay();

        $currentIncome = $this->totalIncome($startDate, $endDate);
        $previousIncome = $this->totalIncome($previousStart, $previousEnd);

        $successTransactions = $this->baseQuery($startDate, $endDate)->where('status', 1)->count();
        $previousSuccess = $this->baseQuery($previousStart, $previousEnd)->where('status', 1)->count();

        $refundedTransactions = $this->baseQuery($startDate, $endDate)->where('status', 0)->count();
        $previousRefunded = $this->baseQuery($previousStart, $previousEnd)->where('status', 0)->count();
        $totalTransactions = $this->baseQuery($startDate, $endDate)->count();
        $refundedAmount = (float) $this->baseQuery($startDate, $endDate)->where('status', 0)->sum('amount');
        $activeDays = $this->baseQuery($startDate, $endDate)
            ->where('status', 1)
            ->get(['created'])
            ->groupBy(fn ($payment) => Carbon::createFromTimestamp((int) $payment->created)->toDateString())
            ->count();
        $avgOrderValue = $successTransactions > 0 ? round($currentIncome / $successTransactions, 2) : 0.0;
        $refundRate = $totalTransactions > 0 ? round(($refundedTransactions / $totalTransactions) * 100, 2) : 0.0;
        $netIncome = round($currentIncome - $refundedAmount, 2);

        return [
            'total_income' => $currentIncome,
            'net_income' => $netIncome,
            'income_growth' => $this->calcGrowth($previousIncome, $currentIncome),
            'total_transactions' => $totalTransactions,
            'success_transactions' => $successTransactions,
            'success_growth' => $this->calcGrowth($previousSuccess, $successTransactions),
            'refunded_transactions' => $refundedTransactions,
            'refund_growth' => $this->calcGrowth($previousRefunded, $refundedTransactions),
            'refunded_amount' => $refundedAmount,
            'avg_order_value' => $avgOrderValue,
            'refund_rate' => $refundRate,
            'active_days' => $activeDays,
            'top_gateway' => $this->topGateway($startDate, $endDate),
        ];
    }

    public function totalIncome(CarbonInterface $startDate, CarbonInterface $endDate): float
    {
        return (float) $this->baseQuery($startDate, $endDate)
            ->where('status', 1)
            ->sum('amount');
    }

    public function paymentByGateway(CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        $rows = $this->baseQuery($startDate, $endDate)
            ->selectRaw('`from`, SUM(amount) as total, COUNT(*) as transactions')
            ->where('status', 1)
            ->groupBy('from')
            ->orderByDesc('total')
            ->get();

        $max = max(1, (float) $rows->max('total'));
        $sum = max(1, (float) $rows->sum('total'));

        return $rows->map(fn ($row) => [
            'name' => $this->normalizeGatewayName($row->from),
            'total' => round((float) $row->total, 2),
            'transactions' => (int) $row->transactions,
            'share' => round((((float) $row->total) / $sum) * 100, 2),
            'width' => round((((float) $row->total) / $max) * 100, 2),
        ]);
    }

    public function incomeByDay(CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        $groupedRows = $this->baseQuery($startDate, $endDate)
            ->where('status', 1)
            ->get()
            ->groupBy(fn ($payment) => Carbon::createFromTimestamp((int) $payment->created)->toDateString());

        $rows = $groupedRows->map(fn (Collection $group) => (float) $group->sum('amount'));

        $days = collect();
        $cursor = Carbon::parse($startDate)->startOfDay();
        $endCursor = Carbon::parse($endDate)->endOfDay();
        $max = max(1, (float) $rows->max());

        while ($cursor->lessThanOrEqualTo($endCursor)) {
            $key = $cursor->toDateString();
            $total = (float) ($rows[$key] ?? 0);

            $days->push([
                'label' => $cursor->format('M d'),
                'total' => round($total, 2),
                'transactions' => (int) ($groupedRows->has($key) ? $groupedRows->get($key, collect())->count() : 0),
                'avg_ticket' => $groupedRows->has($key) ? round($total / max(1, $groupedRows->get($key, collect())->count()), 2) : 0.0,
                'height' => $total > 0 ? max(8, round(($total / $max) * 100, 2)) : 0,
            ]);

            $cursor->addDay();
        }

        return $days;
    }

    public function incomeByPlan(CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        $rows = PaymentHistory::query()
            ->selectRaw('plans.name as plan_name, SUM(payment_history.amount) as total')
            ->leftJoin('plans', 'plans.id', '=', 'payment_history.plan_id')
            ->where('payment_history.status', 1)
            ->whereBetween('payment_history.created', [$startDate->timestamp, $endDate->timestamp])
            ->groupBy('plans.name')
            ->orderByDesc('total')
            ->get();

        $sum = max(1, (float) $rows->sum('total'));

        return $rows->map(fn ($row) => [
            'name' => $row->plan_name ?: __('Unknown'),
            'total' => round((float) $row->total, 2),
            'percentage' => round((((float) $row->total) / $sum) * 100, 1),
        ]);
    }

    public function latestPayments(int $limit = 10): Collection
    {
        return PaymentHistory::query()
            ->with(['user:id,name,email', 'plan:id,name'])
            ->where('status', 1)
            ->orderByDesc('created')
            ->limit($limit)
            ->get();
    }

    public function paymentStatusMix(CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        $rows = $this->baseQuery($startDate, $endDate)
            ->selectRaw('status, COUNT(*) as transactions, SUM(amount) as total')
            ->groupBy('status')
            ->get();

        $totalTransactions = max(1, (int) $rows->sum('transactions'));

        return $rows->map(function ($row) use ($totalTransactions) {
            $status = (int) $row->status;

            return [
                'status' => $status,
                'label' => match ($status) {
                    1 => __('Success'),
                    0 => __('Refunded'),
                    2 => __('Pending'),
                    default => __('Unknown'),
                },
                'tone' => match ($status) {
                    1 => '#10b981',
                    0 => '#f43f5e',
                    2 => '#f59e0b',
                    default => '#64748b',
                },
                'transactions' => (int) $row->transactions,
                'total' => round((float) $row->total, 2),
                'share' => round((((int) $row->transactions) / $totalTransactions) * 100, 2),
            ];
        })->sortByDesc('transactions')->values();
    }

    public function hourlyIncomeHeatmap(CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        $payments = $this->baseQuery($startDate, $endDate)
            ->where('status', 1)
            ->get(['created', 'amount']);

        $buckets = collect(range(0, 23))->mapWithKeys(fn (int $hour) => [$hour => [
            'hour' => $hour,
            'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
            'total' => 0.0,
            'transactions' => 0,
        ]]);

        foreach ($payments as $payment) {
            $hour = Carbon::createFromTimestamp((int) $payment->created)->hour;
            $bucket = $buckets->get($hour);
            $bucket['total'] += (float) $payment->amount;
            $bucket['transactions'] += 1;
            $buckets->put($hour, $bucket);
        }

        $max = max(1, (float) $buckets->max('total'));

        return $buckets->values()->map(function (array $bucket) use ($max) {
            $intensity = $bucket['total'] > 0 ? round(($bucket['total'] / $max) * 100, 2) : 0.0;

            return [
                'hour' => $bucket['hour'],
                'label' => $bucket['label'],
                'total' => round((float) $bucket['total'], 2),
                'transactions' => (int) $bucket['transactions'],
                'intensity' => $intensity,
            ];
        });
    }

    public function weekdayPerformance(CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        $dayMeta = collect([
            0 => __('Sunday'),
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
        ]);

        $buckets = collect(range(0, 6))->mapWithKeys(fn (int $day) => [$day => [
            'day' => $day,
            'label' => $dayMeta[$day],
            'total' => 0.0,
            'transactions' => 0,
        ]]);

        $payments = $this->baseQuery($startDate, $endDate)
            ->where('status', 1)
            ->get(['created', 'amount']);

        foreach ($payments as $payment) {
            $day = Carbon::createFromTimestamp((int) $payment->created)->dayOfWeek;
            $bucket = $buckets->get($day);
            $bucket['total'] += (float) $payment->amount;
            $bucket['transactions'] += 1;
            $buckets->put($day, $bucket);
        }

        $max = max(1, (float) $buckets->max('total'));

        return $buckets->values()->map(function (array $bucket) use ($max) {
            $avg = $bucket['transactions'] > 0 ? round($bucket['total'] / $bucket['transactions'], 2) : 0.0;

            return [
                'day' => $bucket['day'],
                'label' => $bucket['label'],
                'total' => round((float) $bucket['total'], 2),
                'transactions' => (int) $bucket['transactions'],
                'avg' => $avg,
                'width' => $bucket['total'] > 0 ? round(($bucket['total'] / $max) * 100, 2) : 0.0,
            ];
        });
    }

    protected function topGateway(CarbonInterface $startDate, CarbonInterface $endDate): string
    {
        $result = $this->baseQuery($startDate, $endDate)
            ->where('status', 1)
            ->selectRaw('`from`, SUM(amount) as total')
            ->groupBy('from')
            ->orderByDesc('total')
            ->first();

        return $this->normalizeGatewayName($result?->from);
    }

    protected function baseQuery(CarbonInterface $startDate, CarbonInterface $endDate)
    {
        return PaymentHistory::query()
            ->whereBetween('created', [$startDate->timestamp, $endDate->timestamp]);
    }

    protected function calcGrowth(float|int $previous, float|int $current): float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0 ? 100.0 : 0.0;
        }

        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 2);
    }

    protected function normalizeGatewayName(?string $gateway): string
    {
        $gateway = strtolower(trim((string) $gateway));

        if ($gateway === '') {
            return __('Unknown');
        }

        if ($this->payments->has($gateway)) {
            $definition = $this->payments->definition($gateway);

            return $definition->reportLabel ?: $definition->title;
        }

        return ucfirst($gateway);
    }
}
