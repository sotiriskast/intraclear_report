<?php
namespace Modules\MerchantPortal\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\MerchantPortal\Repositories\MerchantRollingReserveRepository;

class RollingReserveController extends Controller
{
    public function __construct(
        private MerchantRollingReserveRepository $reserveRepository
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $merchantId = $user->merchant_id;

        $filters = $request->validate([
            'status' => 'nullable|string|in:pending,released',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'currency' => 'nullable|string|size:3',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        $reserves = $this->reserveRepository->getByMerchantWithFilters(
            $merchantId,
            $filters,
            $filters['per_page'] ?? 25
        );

        $summary = $this->reserveRepository->getSummaryByMerchant($merchantId);
        $timeline = $this->reserveRepository->getTimelineByMerchant($merchantId);

        // Format reserves for display
        $reserves->getCollection()->transform(function ($reserve) {
            $reserve->amount = $reserve->reserve_amount_eur / 100;
            $reserve->original_amount_display = $reserve->original_amount / 100;
            return $reserve;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'reserves' => $reserves,
                'summary' => $summary,
                'timeline' => $timeline,
            ]);
        }

        return view('merchantportal::rolling-reserves.index', [
            'reserves' => $reserves,
            'filters' => $filters,
            'summary' => $summary,
            'timeline' => $timeline,
        ]);
    }

    public function summary(Request $request)
    {
        $user = auth()->user();
        $merchantId = $user->merchant_id;

        $summary = $this->reserveRepository->getSummaryByMerchant($merchantId);

        return response()->json($summary);
    }
}
