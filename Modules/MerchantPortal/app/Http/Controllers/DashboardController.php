<?php
namespace Modules\MerchantPortal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Modules\MerchantPortal\Services\MerchantDashboardService;

    class DashboardController extends Controller
    {
        public function __construct(
            private MerchantDashboardService $dashboardService
        ) {}

        public function index(Request $request)
        {
            $user = auth()->user();
            $merchantId = $user->merchant_id;

            $dashboardData = $this->dashboardService->getDashboardData($merchantId);

            return view('merchantportal::dashboard.index', [
                'data' => $dashboardData,
                'merchant' => $user->merchant,
            ]);
        }

        public function overview(Request $request)
        {
            $user = auth()->user();
            $merchantId = $user->merchant_id;

            $overviewData = $this->dashboardService->getOverviewData($merchantId);

            return response()->json($overviewData);
        }
}
