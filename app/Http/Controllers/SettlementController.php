<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSettlementReportsJob;
use App\Traits\DebugHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SettlementController handles settlement report generation and management
 *
 * @property DebugHelper Provides logging and debugging functionality
 *
 * Methods:
 * - index(): Displays list of settlement reports
 * - generate(): Initiates settlement report generation
 * - showGenerateForm(): Shows form to generate new reports
 * - download(): Downloads individual settlement report
 * - downloadZip(): Downloads archived settlement reports
 * - archives(): Shows list of settlement report archives
 */
class SettlementController extends Controller
{
    use DebugHelper;
    /**
     * Display a listing of settlement reports
     *
     * @return View
     * @throws \Exception If database query fails
     */
    public function index(): View
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
    /**
     * Generate new settlement reports
     *
     * @param Request $request Contains generation parameters
     * @return RedirectResponse
     */
    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'merchant_id' => 'nullable|integer',
            'start_date' => 'date',
            'end_date' => 'date|after:start_date',
        ]);

        try {
            // Dispatch the job with the validated data
            GenerateSettlementReportsJob::dispatch(
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null,
                $validated['merchant_id'] ?? null,
            )->onQueue('reports');

            // Store the parameters in session for the download form
            if ($request->has('download')) {
                session()->flash('generate_params', $validated);
            }
            return back()->with('success', 'Report generation has started. You will be notified when it\'s ready.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to start report generation: ' . $e->getMessage()]);
        }
    }
    /**
     * Show the form for generating new settlement reports
     *
     * @return View
     * @throws \Exception If database query fails
     */
    public function showGenerateForm(): View
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
    /**
     * Display the settlement archives page
     *
     * @return View
     * @throws \Exception If database query fails
     */
    public function archives(): View
    {
        $this->debugLog('Loading settlement archives index');

        try {
            $archives = DB::connection('mariadb')
                ->table('settlement_report_archives')
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            $this->debugLog('Archives fetched successfully', [
                'count' => $archives->count(),
                'total' => $archives->total()
            ]);

            return view('settlements.archives', compact('archives'));
        } catch (\Exception $e) {
            $this->debugLog('Error loading settlement archives', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 'error');

            throw $e;
        }
    }
    /**
     * Download a specific settlement report
     *
     * @param int $id The ID of the settlement report
     * @return StreamedResponse|RedirectResponse
     */
    public function download($id): StreamedResponse|RedirectResponse
    {
        try {
            $report = DB::connection('mariadb')
                ->table('settlement_reports')
                ->where('id', $id)
                ->first();

            if (!$report) {
                session()->flash('error', 'Report not found.');
                return redirect()->route('settlements.index');
            }

            if (!Storage::exists($report->report_path)) {
                session()->flash('error', 'Report file not found in storage.');
                return redirect()->route('settlements.index');
            }

            return Storage::download(
                $report->report_path,
                basename($report->report_path),
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            );

        } catch (\Exception $e) {
            $this->debugLog('Error downloading report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 'error');

            session()->flash('error', 'Failed to download report.');
            return redirect()->route('settlements.index');
        }
    }
    /**
     * Download a settlement report archive
     *
     * @param int $id The ID of the archive
     * @return StreamedResponse|RedirectResponse
     */
    public function downloadZip($id): StreamedResponse|RedirectResponse
    {
        try {
            $archive = DB::connection('mariadb')
                ->table('settlement_report_archives')
                ->where('id', $id)
                ->first();

            if (!$archive) {
                session()->flash('error', 'Archive not found.');
                return redirect()->route('settlements.archives');
            }

            if (!Storage::exists($archive->zip_path)) {
                session()->flash('error', 'Archive file not found in storage.');
                return redirect()->route('settlements.archives');
            }

            return Storage::download(
                $archive->zip_path,
                basename($archive->zip_path),
                ['Content-Type' => 'application/zip']
            );

        } catch (\Exception $e) {
            $this->debugLog('Error downloading ZIP archive', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 'error');

            session()->flash('error', 'Failed to download archive.');
            return redirect()->route('settlements.archives');
        }
    }


}
