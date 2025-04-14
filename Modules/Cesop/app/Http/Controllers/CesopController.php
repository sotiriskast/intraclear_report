<?php

namespace Modules\Cesop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\DynamicLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Cesop\Services\PgpEncryptionService;

class CesopController extends Controller
{


    public function __construct(private readonly PgpEncryptionService $pgpService, private DynamicLogger $logger)
    {

    }

    public function index()
    {
        return view('cesop::index');
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'xml_file' => 'required|file|mimes:xml|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Store the uploaded XML file
            $xmlPath = $request->file('xml_file')->store('cesop/temp');

            // Validate against CESOP schema before encryption
            if (!$this->validateXml($xmlPath)) {
                return redirect()->back()
                    ->with('error', 'The XML file is not valid according to the CESOP schema.');
            }

            // Determine filename parameters
            $quarter = $request->input('quarter', 1);
            $year = $request->input('year', date('Y'));
            $countryCode = $request->input('country_code', 'CY');
            $pspId = $request->input('psp_id', 'YOURPSPID');

            // Generate filename
            $outputFilename = $this->pgpService->generateCesopFilename(
                $quarter, $year, $countryCode, $pspId
            );

            // Define output path
            $outputPath = 'cesop/encrypted/' . $outputFilename;

            // Encrypt the XML file
            $encryptedPath = $this->pgpService->encryptXmlFile($xmlPath, $outputPath);

            // Clean up the temporary file
            Storage::delete($xmlPath);

            return redirect()->route('cesop.success')
                ->with('success', 'File encrypted successfully')
                ->with('encrypted_file', $encryptedPath);

        } catch (\Exception $e) {
            $this->logger->log('error', 'CESOP encryption failed: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Encryption failed: ' . $e->getMessage());
        }
    }

    public function success()
    {
        if (!session('encrypted_file')) {
            return redirect()->route('cesop.index');
        }

        return view('cesop::success', [
            'encrypted_file' => session('encrypted_file')
        ]);
    }

    public function download($filename)
    {
        $path = 'cesop/encrypted/' . $filename;
        if (!Storage::exists($path)) {
            abort(404, 'File not found');
        }
        return Storage::download($path, $filename);
    }
    private function validateXml($xmlPath)
    {
        $xsdPath = resource_path('xsd/PaymentData.xsd');

        if (!file_exists($xsdPath)) {

            $this->logger->log('warning','XSD file not found at: ' . $xsdPath);

            return true;
        }
        $xml = new \DOMDocument();
        $xml->load(Storage::path($xmlPath));
        libxml_use_internal_errors(true);
        $isValid = $xml->schemaValidate($xsdPath);
        if (!$isValid) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $this->logger->log('error','XSD file not found at: ' . $xsdPath);
            }
            libxml_clear_errors();
        }
        return $isValid;
    }
}
