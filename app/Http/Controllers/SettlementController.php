<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSettlementReportsJob;
use App\Traits\DebugHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

class SettlementController extends Controller
{
    use DebugHelper;

    public function index()
    {
        $this->debugLog('Loading settlement reports index');

        try {
            $reports = DB::connection('mariadb')
                ->table('settlement_reports')
                ->select([
                    'settlement_reports.*',
                    'merchants.name as merchant_name',
                    'merchants.account_id'
                ])
                ->join('merchants', 'merchants.id', '=', 'settlement_reports.merchant_id')
                ->orderBy('settlement_reports.created_at', 'desc')
                ->paginate(10);

            $this->debugLog('Reports fetched successfully', [
                'count' => $reports->count(),
                'total' => $reports->total()
            ]);

            return view('settlements.index', compact('reports'));
        } catch (\Exception $e) {
            $this->debugLog('Error loading settlement reports', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 'error');

            throw $e;
        }
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'merchant_id' => 'nullable|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'currency' => 'nullable|string|size:3',
        ]);

        try {
            // Dispatch the job
            GenerateSettlementReportsJob::dispatch($validated, auth()->id())->onQueue('reports');

            return back()->with('success', 'Report generation has started. You will be notified when it\'s ready.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to start report generation: ' . $e->getMessage()]);
        }
    }

    public function download(Request $request)
    {
        $path = $request->query('path');

        if (!$path || !Storage::exists($path)) {
            return back()->withErrors(['error' => 'Report file not found.']);
        }

        return Storage::download($path);
    }

    public function showGenerateForm()
    {
        $this->debugLog('Loading generate form');

        try {
            $merchants = DB::connection('mariadb')
                ->table('merchants')
                ->where('active', 1)
                ->select(['account_id', 'name'])
                ->get();

            $currencies = DB::connection('payment_gateway_mysql')
                ->table('transactions')
                ->select('currency')
                ->distinct()
                ->pluck('currency');

            $this->debugLog('Form data loaded', [
                'merchant_count' => $merchants->count(),
                'currency_count' => $currencies->count()
            ]);

            return view('settlements.generate', compact('merchants', 'currencies'));
        } catch (\Exception $e) {
            $this->debugLog('Error loading generate form', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 'error');

            throw $e;
        }
    }


}
