<?php

namespace Modules\Cesop\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Cesop\Services\CesopReportService;
use Modules\Cesop\Services\CesopXmlValidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CesopReportController extends Controller
{
    /**
     * @var CesopReportService
     */
    protected $reportService;

    /**
     * @var CesopXmlValidator
     */
    protected $xmlValidator;

    /**
     * Constructor
     *
     * @param CesopReportService $reportService
     * @param CesopXmlValidator $xmlValidator
     */
    public function __construct(CesopReportService $reportService, CesopXmlValidator $xmlValidator)
    {
        $this->reportService = $reportService;
        $this->xmlValidator = $xmlValidator;
    }

    /**
     * Display the CESOP report generation interface.
     *
     * @return \Illuminate\Contracts\Support\Renderable
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
            'threshold' => 'nullable|integer|min:1',
            'psp_data' => 'nullable|array'
        ]);

        // Set default threshold if not provided
        $threshold = $validated['threshold'] ?? 25;

        // Convert empty arrays to empty values for proper filtering
        $merchantIds = !empty($validated['merchants']) ? $validated['merchants'] : [];
        $shopIds = !empty($validated['shops']) ? $validated['shops'] : [];
        $pspData = !empty($validated['psp_data']) ? $validated['psp_data'] : null;

        // Generate preview
        $result = $this->reportService->previewReport(
            $validated['quarter'],
            $validated['year'],
            $merchantIds,
            $shopIds,
            $threshold,
            $pspData
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
            'format' => 'nullable|in:xml,json',
            'psp_data' => 'nullable|array',
            'validate' => 'nullable|boolean',
            'output_path' => 'nullable|string'
        ]);

        // Set default threshold if not provided
        $threshold = $validated['threshold'] ?? 25;

        // Convert empty arrays to empty values for proper filtering
        $merchantIds = !empty($validated['merchants']) ? $validated['merchants'] : [];
        $shopIds = !empty($validated['shops']) ? $validated['shops'] : [];
        $pspData = !empty($validated['psp_data']) ? $validated['psp_data'] : null;
        $format = $validated['format'] ?? 'xml';
        $shouldValidate = $validated['validate'] ?? false;
        $outputPath = $validated['output_path'] ?? null;

        // Generate report
        $result = $this->reportService->generateReport(
            $validated['quarter'],
            $validated['year'],
            $merchantIds,
            $shopIds,
            $threshold,
            $pspData
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        $xml = $result['data']['xml'];
        $stats = $result['data']['stats'];
        $period = $result['data']['period'];

        // Handle validation if requested
        if ($shouldValidate) {
            // Generate a temporary file for validation
            $tempFilePath = tempnam(sys_get_temp_dir(), 'cesop_');
            file_put_contents($tempFilePath, $xml);

            try {
                $validationResult = $this->xmlValidator->validateXmlFile($tempFilePath);

                // Add validation results to the response
                $result['validation'] = $validationResult;

                // Remove temporary file
                unlink($tempFilePath);
            } catch (\Exception $e) {
                Log::error('CESOP XML Validation Error: ' . $e->getMessage());
                // Optionally add error to result
                $result['validation_error'] = $e->getMessage();
            }
        }

        // Save XML to file if output path is provided
        if ($outputPath) {
            // Ensure directory exists
            $directory = dirname($outputPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($outputPath, $xml);
        }

        // Return response based on format
        if ($format === 'json') {
            return response()->json($result);
        }

        // Generate filename
        $quarter = $period['quarter'];
        $year = $period['year'];
        $pspCountry = $pspData['country'] ?? config('cesop.psp.country', 'CY');
        $pspBic = $pspData['bic'] ?? config('cesop.psp.bic', 'ABCDEF12XXX');

        $filename = sprintf(
            'PMT-Q%d-%d-%s-%s-1-1.xml',
            $quarter,
            $year,
            $pspCountry,
            $pspBic
        );

        // Return XML as downloadable file
        return response($xml)
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
    /**
     * Import an Excel file and convert to CESOP XML format
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
}
