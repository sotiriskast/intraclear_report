<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Transaction Details - {{ $transaction->payment_id }}
            </h2>

        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <a href="{{ route('decta.transactions.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to List
            </a>
            <!-- Transaction Status Card -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Transaction Status</h3>
                        <div class="flex items-center space-x-4">
                            @if($transaction->is_matched)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    Matched
                                </span>
                            @else
                                @switch($transaction->status)
                                    @case('pending')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                            </svg>
                                            Pending
                                        </span>
                                        @break
                                    @case('failed')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                            Failed
                                        </span>
                                        @break
                                    @default
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                            {{ ucfirst($transaction->status) }}
                                        </span>
                                @endswitch
                            @endif
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Payment ID</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $transaction->payment_id }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Transaction Date</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $transaction->tr_date_time ? $transaction->tr_date_time->format('Y-m-d H:i:s') : 'N/A' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Source File</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $transaction->dectaFile->filename ?? 'N/A' }}</dd>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Details Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Transaction Information -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Transaction Information</h3>
                    </div>
                    <div class="px-6 py-4">
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Amount</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <span class="font-semibold text-lg">
                                        {{ $transaction->tr_amount ? number_format($transaction->tr_amount / 100, 2) : 'N/A' }}
                                        {{ $transaction->tr_ccy }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Transaction Type</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $transaction->tr_type ?: 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Approval ID</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $transaction->tr_approval_id ?: 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Return Reference</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $transaction->tr_ret_ref_nr ?: 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Processing Date</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $transaction->tr_processing_date ? $transaction->tr_processing_date->format('Y-m-d H:i:s') : 'N/A' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Batch ID</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $transaction->tr_batch_id ?: 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Merchant Information -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Merchant Information</h3>
                    </div>
                    <div class="px-6 py-4">
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Merchant Name</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $transaction->merchant_name ?: 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Merchant ID</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $transaction->merchant_id ?: 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Legal Name</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $transaction->merchant_legal_name ?: 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Terminal ID</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $transaction->terminal_id ?: 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">IBAN Code</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $transaction->merchant_iban_code ?: 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Country</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $transaction->merchant_country ?: 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Card Information -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Card Information</h3>
                    </div>
                    <div class="px-6 py-4">
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Masked Card Number</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $transaction->card ?: 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Card Type</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if($transaction->card_type_name)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $transaction->card_type_name === 'VISA' ? 'bg-blue-100 text-blue-800' :
                                               ($transaction->card_type_name === 'MC' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') }}">
                                            {{ $transaction->card_type_name }}
                                        </span>
                                    @else
                                        N/A
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Product Type</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $transaction->card_product_type ?: 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Product Class</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $transaction->card_product_class ?: 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Issuer Country</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $transaction->issuer_country ?: 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Gateway Information -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Gateway Matching Information</h3>
                    </div>
                    <div class="px-6 py-4">
                        @if($transaction->is_matched)
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Gateway Transaction ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $transaction->gateway_transaction_id ?: 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Gateway Account ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $transaction->gateway_account_id ?: 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Gateway Shop ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $transaction->gateway_shop_id ?: 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Gateway TRX ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $transaction->gateway_trx_id ?: 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Matched At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $transaction->matched_at ? $transaction->matched_at->format('Y-m-d H:i:s') : 'N/A' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Gateway Transaction Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $transaction->gateway_transaction_date ?: 'N/A' }}</dd>
                                </div>
                            </dl>
                        @else
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500">No gateway match found</p>
                                @if($transaction->error_message)
                                    <p class="mt-1 text-xs text-red-600">{{ $transaction->error_message }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Matching Attempts (if any) -->
            @if($transaction->matching_attempts)
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Matching Attempts</h3>
                    </div>
                    <div class="px-6 py-4">
                        <div class="space-y-4">
                            @foreach(json_decode($transaction->matching_attempts, true) ?? [] as $attempt)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium text-gray-900">
                                            Attempt {{ $loop->iteration }}
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            {{ isset($attempt['attempted_at']) ? \Carbon\Carbon::parse($attempt['attempted_at'])->format('Y-m-d H:i:s') : 'N/A' }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                        <div>
                                            <span class="font-medium text-gray-700">Strategy:</span>
                                            <span class="text-gray-600">{{ $attempt['strategy'] ?? 'N/A' }}</span>
                                        </div>
                                        <div>
                                            <span class="font-medium text-gray-700">Result:</span>
                                            <span class="text-gray-600">{{ $attempt['result'] ?? 'N/A' }}</span>
                                        </div>
                                        @if(isset($attempt['error_message']))
                                            <div class="md:col-span-2">
                                                <span class="font-medium text-gray-700">Error:</span>
                                                <span class="text-red-600">{{ $attempt['error_message'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- Technical Details -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Technical Details</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                        <div>
                            <dt class="font-medium text-gray-500">ACQ Ref Number</dt>
                            <dd class="mt-1 text-gray-900 font-mono">{{ $transaction->acq_ref_nr ?: 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">MSC</dt>
                            <dd class="mt-1 text-gray-900 font-mono">{{ $transaction->msc ?: 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">MCC</dt>
                            <dd class="mt-1 text-gray-900 font-mono">{{ $transaction->mcc ?: 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Proc Code</dt>
                            <dd class="mt-1 text-gray-900 font-mono">{{ $transaction->proc_code ?: 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">ECI/SLI</dt>
                            <dd class="mt-1 text-gray-900 font-mono">{{ $transaction->eci_sli ?: 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">SCA Exemption</dt>
                            <dd class="mt-1 text-gray-900">{{ $transaction->sca_exemption ?: 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Point Code</dt>
                            <dd class="mt-1 text-gray-900 font-mono">{{ $transaction->point_code ?: 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">POS Environment</dt>
                            <dd class="mt-1 text-gray-900">{{ $transaction->pos_env_indicator ?: 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">PAR</dt>
                            <dd class="mt-1 text-gray-900 font-mono">{{ $transaction->par ?: 'N/A' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- System Information -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">System Information</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="font-medium text-gray-500">Created At</dt>
                            <dd class="mt-1 text-gray-900">{{ $transaction->created_at ? $transaction->created_at->format('Y-m-d H:i:s') : 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Updated At</dt>
                            <dd class="mt-1 text-gray-900">{{ $transaction->updated_at ? $transaction->updated_at->format('Y-m-d H:i:s') : 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Database ID</dt>
                            <dd class="mt-1 text-gray-900 font-mono">{{ $transaction->id }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">File ID</dt>
                            <dd class="mt-1 text-gray-900 font-mono">{{ $transaction->decta_file_id }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
