<?php

namespace App\Livewire;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class SettlementReportsList extends Component
{
    use WithPagination;

    public $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {

        $reports = DB::connection('mariadb')
            ->table('settlement_reports')
            ->select([
                'settlement_reports.*',
                'merchants.name as merchant_name',
                'merchants.account_id'
            ])
            ->join('merchants', 'merchants.id', '=', 'settlement_reports.merchant_id')
            ->when($this->search, function ($query) {
                return $query->where(function ($q) {
                    $q->where('merchants.name', 'like', '%' . $this->search . '%')
                        ->orWhere('merchants.account_id', 'like', '%' . $this->search . '%')
                        ->orWhereRaw("DATE_FORMAT(settlement_reports.start_date, '%Y-%m-%d') LIKE ?", ['%' . $this->search . '%'])
                        ->orWhereRaw("DATE_FORMAT(settlement_reports.end_date, '%Y-%m-%d') LIKE ?", ['%' . $this->search . '%']);
                });
            })
            ->orderBy('settlement_reports.created_at', 'desc')
            ->paginate(15);
        return view('livewire.settlement-reports-list', [
            'reports' => $reports
        ]);
    }
}
