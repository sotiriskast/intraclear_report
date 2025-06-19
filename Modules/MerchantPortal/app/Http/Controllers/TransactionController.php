<?php
namespace Modules\MerchantPortal\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\MerchantPortal\Repositories\MerchantTransactionRepository;

class TransactionController extends Controller
{
    public function __construct(
        private MerchantTransactionRepository $transactionRepository
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $merchantId = $user->merchant_id;

        $filters = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'status' => 'nullable|string|in:pending,processing,matched,failed',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'amount_min' => 'nullable|numeric|min:0',
            'amount_max' => 'nullable|numeric|gte:amount_min',
            'payment_id' => 'nullable|string',
            'merchant_name' => 'nullable|string',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        $transactions = $this->transactionRepository->getByMerchantWithFilters(
            $merchantId,
            $filters,
            $filters['per_page'] ?? 25
        );

        // Format transactions for display
        $transactions->getCollection()->transform(function ($transaction) {
            $transaction->amount = $transaction->tr_amount / 100;
            $transaction->transaction_id = $transaction->payment_id;
            $transaction->created_at = $transaction->tr_date_time;
            $transaction->payment_method = $transaction->card_type_name;
            $transaction->currency = $transaction->tr_ccy;
            return $transaction;
        });

        if ($request->expectsJson()) {
            return response()->json($transactions);
        }

        return view('merchantportal::transactions.index', [
            'transactions' => $transactions,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $user = auth()->user();
        $merchantId = $user->merchant_id;

        $transaction = $this->transactionRepository->findByIdAndMerchant($id, $merchantId);

        if (!$transaction) {
            abort(404, 'Transaction not found');
        }

        // Format transaction for display
        $transaction->amount = $transaction->tr_amount / 100;
        $transaction->transaction_id = $transaction->payment_id;
        $transaction->created_at = $transaction->tr_date_time;
        $transaction->payment_method = $transaction->card_type_name;
        $transaction->currency = $transaction->tr_ccy;
        $transaction->customer_email = null; // Not available in DectaTransaction
        $transaction->reference_id = $transaction->acq_ref_nr;
        $transaction->description = $transaction->merchant_name;

        if ($request->expectsJson()) {
            return response()->json($transaction);
        }

        return view('merchantportal::transactions.show', [
            'transaction' => $transaction,
        ]);
    }

    public function analytics(Request $request)
    {
        $user = auth()->user();
        $merchantId = $user->merchant_id;

        $analytics = [
            'payment_types' => $this->transactionRepository->getTransactionsByPaymentType($merchantId),
            'countries' => $this->transactionRepository->getTransactionsByCountry($merchantId),
            'monthly_stats' => $this->transactionRepository->getMonthlyStatsByMerchant($merchantId),
        ];

        return response()->json($analytics);
    }
}
