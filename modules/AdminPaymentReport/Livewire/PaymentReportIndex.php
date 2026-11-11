<?php

namespace Modules\AdminPaymentReport\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\AdminPaymentReport\Services\PaymentReportService;

#[Title('Payment Report')]
class PaymentReportIndex extends Component
{
    protected PaymentReportService $paymentReport;

    #[Url(as: 'from', except: '')]
    public string $fromDate = '';

    #[Url(as: 'to', except: '')]
    public string $toDate = '';

    public function boot(PaymentReportService $paymentReport): void
    {
        $this->paymentReport = $paymentReport;
    }

    public function mount(): void
    {
        $this->syncResolvedRange();
    }

    public function resetFilters(): void
    {
        $this->fromDate = '';
        $this->toDate = '';
        $this->syncResolvedRange();
    }

    public function render(): View
    {
        [$startDate, $endDate] = $this->syncResolvedRange();

        return view('adminpaymentreport::livewire.index', [
            'rangeLabel' => $startDate->format('M d, Y').' - '.$endDate->format('M d, Y'),
            'info' => $this->paymentReport->paymentInfo($startDate, $endDate),
            'gatewayBreakdown' => $this->paymentReport->paymentByGateway($startDate, $endDate),
            'statusMix' => $this->paymentReport->paymentStatusMix($startDate, $endDate),
            'incomeByDay' => $this->paymentReport->incomeByDay($startDate, $endDate),
            'hourlyHeatmap' => $this->paymentReport->hourlyIncomeHeatmap($startDate, $endDate),
            'weekdayPerformance' => $this->paymentReport->weekdayPerformance($startDate, $endDate),
            'incomeByPlan' => $this->paymentReport->incomeByPlan($startDate, $endDate),
            'latestPayments' => $this->paymentReport->latestPayments(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Payment Report'),
        ]);
    }

    protected function syncResolvedRange(): array
    {
        [$startDate, $endDate] = $this->paymentReport->resolveDateRange(
            $this->fromDate,
            $this->toDate,
        );

        $this->fromDate = $startDate->toDateString();
        $this->toDate = $endDate->toDateString();

        return [$startDate, $endDate];
    }
}
