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
        $reports = DB::connection('pgsql')
            ->table('settlement_reports')
            ->select([
                'settlement_reports.*',
                'merchants.name as merchant_name',
                'merchants.account_id'
            ])
            ->join('merchants', 'merchants.id', '=', 'settlement_reports.merchant_id')
            ->when($this->search, function ($query) {
                // Remove spaces and convert to lowercase for comparison
                $searchTerm = '%' . str_replace(' ', '', strtolower($this->search)) . '%';

                return $query->where(function ($q) use ($searchTerm) {
                    $q->whereRaw("LOWER(REPLACE(merchants.name::text, ' ', '')) LIKE ?", [$searchTerm])
                        ->orWhereRaw("LOWER(REPLACE(merchants.account_id::text, ' ', '')) LIKE ?", [$searchTerm])
                        // Multiple date format searches for start_date
                        ->orWhereRaw("LOWER(REPLACE(TO_CHAR(settlement_reports.start_date, 'YYYY-MM-DD'), ' ', '')) LIKE ?", [$searchTerm])
                        ->orWhereRaw("LOWER(REPLACE(TO_CHAR(settlement_reports.start_date, 'MM-DD-YYYY'), ' ', '')) LIKE ?", [$searchTerm])
                        ->orWhereRaw("LOWER(REPLACE(TO_CHAR(settlement_reports.start_date, 'DD-MM-YYYY'), ' ', '')) LIKE ?", [$searchTerm])
                        ->orWhereRaw("LOWER(REPLACE(TO_CHAR(settlement_reports.start_date, 'YYYYMMDD'), ' ', '')) LIKE ?", [$searchTerm])
                        ->orWhereRaw("LOWER(REPLACE(TO_CHAR(settlement_reports.start_date, 'MMDDYYYY'), ' ', '')) LIKE ?", [$searchTerm])
                        ->orWhereRaw("LOWER(REPLACE(TO_CHAR(settlement_reports.start_date, 'DDMMYYYY'), ' ', '')) LIKE ?", [$searchTerm])
                        ->orWhereRaw("LOWER(REPLACE(TO_CHAR(settlement_reports.start_date, 'MM/DD/YYYY'), ' ', '')) LIKE ?", [$searchTerm])
                        ->orWhereRaw("LOWER(REPLACE(TO_CHAR(settlement_reports.start_date, 'DD/MM/YYYY'), ' ', '')) LIKE ?", [$searchTerm])
                        ->orWhereRaw("LOWER(REPLACE(TO_CHAR(settlement_reports.start_date, 'YYYY/MM/DD'), ' ', '')) LIKE ?", [$searchTerm]);
                });
            })
            ->orderBy('settlement_reports.created_at', 'desc')
            ->paginate(15);

        return view('livewire.settlement-reports-list', [
            'reports' => $reports
        ]);
    }
}
