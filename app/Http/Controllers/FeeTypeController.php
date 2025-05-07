<?php

namespace App\Http\Controllers;

use App\Models\FeeType;
use App\Services\DynamicLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeeTypeController extends Controller
{
    protected $logger;

    /**
     * Constructor to inject dependencies
     */
    public function __construct(DynamicLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Display a listing of fee types
     */
    public function index()
    {
        $feeTypes = FeeType::withTrashed()->latest()->paginate(15);

        return view('admin.fee-types.index', compact('feeTypes'));
    }

    /**
     * Show the form for creating a new fee type
     */
    public function create()
    {
        return view('admin.fee-types.create');
    }

    /**
     * Store a newly created fee type
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'key' => 'required|string|unique:fee_types,key,NULL,id,deleted_at,NULL',
            'frequency_type' => 'required|in:transaction,daily,weekly,monthly,yearly,one_time',
            'is_percentage' => 'boolean',
        ]);

        try {
            $feeType = FeeType::create([
                'name' => $validated['name'],
                'key' => $validated['key'],
                'frequency_type' => $validated['frequency_type'],
                'is_percentage' => $validated['is_percentage'] ?? false,
            ]);

            $this->logger->log('info', 'Fee Type created successfully', [
                'fee_type_id' => $feeType->id,
                'name' => $feeType->name
            ]);

            return redirect()->route('admin.fee-types.index')
                ->with('message', 'Fee Type created successfully.');
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error creating fee type: '.$e->getMessage());

            return redirect()->back()
                ->with('error', 'Error creating fee type: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the form for editing the fee type
     */
    public function edit(FeeType $feeType)
    {
        return view('admin.fee-types.edit', compact('feeType'));
    }

    /**
     * Update the specified fee type
     */
    public function update(Request $request, FeeType $feeType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'key' => [
                'required',
                'string',
                Rule::unique('fee_types', 'key')
                    ->ignore($feeType->id)
                    ->whereNull('deleted_at')
            ],
            'frequency_type' => 'required|in:transaction,daily,weekly,monthly,yearly,one_time',
            'is_percentage' => 'boolean',
        ]);

        try {
            $feeType->update([
                'name' => $validated['name'],
                'key' => $validated['key'],
                'frequency_type' => $validated['frequency_type'],
                'is_percentage' => $validated['is_percentage'] ?? false,
            ]);

            $this->logger->log('info', 'Fee Type updated successfully', [
                'fee_type_id' => $feeType->id,
                'name' => $feeType->name
            ]);

            return redirect()->route('admin.fee-types.index')
                ->with('message', 'Fee Type updated successfully.');
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error updating fee type: '.$e->getMessage(), [
                'fee_type_id' => $feeType->id
            ]);

            return redirect()->back()
                ->with('error', 'Error updating fee type: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified fee type
     */
    public function destroy(FeeType $feeType)
    {
        try {
            $feeType->delete();

            $this->logger->log('info', 'Fee Type deleted successfully', [
                'fee_type_id' => $feeType->id,
                'name' => $feeType->name
            ]);

            return redirect()->route('admin.fee-types.index')
                ->with('message', 'Fee Type deleted successfully.');
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error deleting fee type: '.$e->getMessage(), [
                'fee_type_id' => $feeType->id
            ]);

            return redirect()->route('admin.fee-types.index')
                ->with('error', $e->getMessage());
        }
    }
}
