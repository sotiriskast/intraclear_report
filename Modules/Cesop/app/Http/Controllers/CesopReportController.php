<?php


namespace Modules\Cesop\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Cesop\Services\CesopReportService;
use Carbon\Carbon;

class CesopReportController extends Controller
{
    /**
     * @var CesopReportService
     */
    protected $reportService;

    /**
     * Constructor
     *
     * @param CesopReportService $reportService
     */
    public function __construct(CesopReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Display the CESOP report generation interface.
     *
     * @return Renderable
     */
    public function index()
    {
        $availableQuarters = $this->reportService->getAvailableQuarters();
        $availableMerchants = $this->reportService->getAvailableMerchants();

        return view('cesop::report.index', compact('availableQuarters', 'availableMerchants'));
    }

    /**
     * Get shops for a specific merchant (for AJAX)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getShops(Request $request)
    {
        $merchantId = $request->input('merchant_id');

        if (!$merchantId) {
            return response()->json(['success' => false, 'message' => 'Merchant ID is required']);
        }

        $shops = $this->reportService->getShopsForMerchant($merchantId);

        return response()->json([
            'success' => true,
            'data' => $shops
        ]);
    }

    /**
     * Generate a preview of the CESOP report
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function preview(Request $request)
    {
        // Validate request inputs
        $validated = $request->validate([
            'quarter' => 'required|integer|between:1,4',
            'year' => 'required|integer|between:2000,2050',
            'merchants' => 'nullable|array',
            'shops' => 'nullable|array',
            'threshold' => 'nullable|integer|min:1'
        ]);

        // Set default threshold if not provided
        $threshold = $validated['threshold'] ?? 25;

        // Convert empty arrays to empty values for proper filtering
        $merchantIds = !empty($validated['merchants']) ? $validated['merchants'] : [];
        $shopIds = !empty($validated['shops']) ? $validated['shops'] : [];

        // Generate preview
        $result = $this->reportService->previewReport(
            $validated['quarter'],
            $validated['year'],
            $merchantIds,
            $shopIds,
            $threshold
        );

        return response()->json($result);
    }

    /**
     * Generate the full CESOP report
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function generate(Request $request)
    {
        // Validate request inputs
        $validated = $request->validate([
            'quarter' => 'required|integer|between:1,4',
            'year' => 'required|integer|between:2000,2050',
            'merchants' => 'nullable|array',
            'shops' => 'nullable|array',
            'threshold' => 'nullable|integer|min:1',
            'format' => 'nullable|in:xml,json'
        ]);

        // Set default threshold if not provided
        $threshold = $validated['threshold'] ?? 25;

        // Convert empty arrays to empty values for proper filtering
        $merchantIds = !empty($validated['merchants']) ? $validated['merchants'] : [];
        $shopIds = !empty($validated['shops']) ? $validated['shops'] : [];

        // Generate report
        $result = $this->reportService->generateReport(
            $validated['quarter'],
            $validated['year'],
            $merchantIds,
            $shopIds,
            $threshold
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        $format = $request->input('format', 'xml');

        if ($format === 'json') {
            return response()->json($result);
        }

        // Generate filename
        $quarter = $validated['quarter'];
        $year = $validated['year'];
        $filename = "cesop_report_q{$quarter}_{$year}_" . date('Ymd_His') . ".xml";

        // Return XML as downloadable file
        return response($result['data']['xml'])
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Download the generated report
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function download(Request $request)
    {
        // Similar to generate but always forces download
        return $this->generate($request);
    }
}
