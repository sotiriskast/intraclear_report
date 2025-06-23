<?php
namespace Modules\MerchantPortal\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\MerchantPortal\Repositories\MerchantShopRepository;

class ShopController extends Controller
{
    public function __construct(
        private MerchantShopRepository $shopRepository
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $merchantId = $user->merchant_id;

        $shops = $this->shopRepository->getByMerchant($merchantId);

        if ($request->expectsJson()) {
            return response()->json($shops);
        }

        return view('merchantportal::shops.index', [
            'shops' => $shops,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $user = auth()->user();
        $merchantId = $user->merchant_id;

        $shop = $this->shopRepository->findByIdAndMerchant($id, $merchantId);

        if (!$shop) {
            abort(404, 'Shop not found');
        }

        if ($request->expectsJson()) {
            return response()->json($shop);
        }

        return view('merchantportal::shops.show', [
            'shop' => $shop,
        ]);
    }
}
