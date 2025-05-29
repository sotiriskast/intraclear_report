<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Transaction Details') }}
            </h2>
            <a href="{{ route('decta.reports.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Reports
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Transaction Overview Card -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white px-6 py-4">
                    <h3 class="text-xl font-semibold flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Transaction Overview
                    </h3>
                </div>

                <div class="p-6">
                    <div id="transactionOverview" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Content will be loaded via JavaScript -->
                        <div class="text-center py-8">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500 mx-auto"></div>
                            <p class="mt-2 text-sm text-gray-500">Loading transaction details...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Details Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Core Transaction Data -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="bg-blue-50 px-6 py-4 border-b border-gray-200">
                        <h4 class="text-lg font-medium text-gray-900">Core Transaction Data</h4>
                    </div>
                    <div class="p-6">
                        <div id="coreTransactionData" class="space-y-4">
                            <!-- Content loaded via JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Merchant Information -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="bg-green-50 px-6 py-4 border-b border-gray-200">
                        <h4 class="text-lg font-medium text-gray-900">Merchant Information</h4>
                    </div>
                    <div class="p-6">
                        <div id="merchantInformation" class="space-y-4">
                            <!-- Content loaded via JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Card Information -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="bg-yellow-50 px-6 py-4 border-b border-gray-200">
                        <h4 class="text-lg font-medium text-gray-900">Card Information</h4>
                    </div>
                    <div class="p-6">
                        <div id="cardInformation" class="space-y-4">
                            <!-- Content loaded via JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Matching Status -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="bg-purple-50 px-6 py-4 border-b border-gray-200">
                        <h4 class="text-lg font-medium text-gray-900">Matching Status</h4>
                    </div>
                    <div class="p-6">
                        <div id="matchingStatus" class="space-y-4">
                            <!-- Content loaded via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gateway Information (Full Width) -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="bg-indigo-50 px-6 py-4 border-b border-gray-200">
                    <h4 class="text-lg font-medium text-gray-900">Gateway Information</h4>
                </div>
                <div class="p-6">
                    <div id="gatewayInformation">
                        <!-- Content loaded via JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Matching Attempts History -->
            <div id="matchingAttemptsSection" class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="bg-red-50 px-6 py-4 border-b border-gray-200">
                    <h4 class="text-lg font-medium text-gray-900">Matching Attempts History</h4>
                </div>
                <div class="p-6">
                    <div id="matchingAttempts">
                        <!-- Content loaded via JavaScript -->
                    </div>
                </div>
            </div>

            <!-- File Information -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h4 class="text-lg font-medium text-gray-900">File Information</h4>
                </div>
                <div class="p-6">
                    <div id="fileInformation">
                        <!-- Content loaded via JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Actions Panel -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="bg-orange-50 px-6 py-4 border-b border-gray-200">
                    <h4 class="text-lg font-medium text-gray-900">Actions</h4>
                </div>
                <div class="p-6">
                    <div class="flex flex-wrap gap-4">
                        <button id="retryMatchingBtn"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Retry Matching
                        </button>

                        <button id="manualMatchBtn"
                                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                            </svg>
                            Manual Match
                        </button>

                        <button id="exportTransactionBtn"
                                class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export Details
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Match Modal -->
    <div id="manualMatchModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Manual Transaction Matching</h3>
                    <button id="closeModalBtn" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form id="manualMatchForm" class="space-y-4">
                    <div>
                        <label for="gateway_transaction_id" class="block text-sm font-medium text-gray-700">Gateway Transaction ID</label>
                        <input type="number" id="gateway_transaction_id" name="gateway_transaction_id"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>

                    <div>
                        <label for="account_id" class="block text-sm font-medium text-gray-700">Account ID</label>
                        <input type="number" id="account_id" name="account_id"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>

                    <div>
                        <label for="shop_id" class="block text-sm font-medium text-gray-700">Shop ID</label>
                        <input type="number" id="shop_id" name="shop_id"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>

                    <div>
                        <label for="trx_id" class="block text-sm font-medium text-gray-700">TRX ID</label>
                        <input type="text" id="trx_id" name="trx_id"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" id="cancelMatchBtn"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-150">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition duration-150">
                            Submit Match
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentId = getPaymentIdFromUrl();

            if (paymentId) {
                loadTransactionDetails(paymentId);
            } else {
                showError('Payment ID not found in URL');
            }

            // Setup event listeners
            setupEventListeners();
        });

        function getPaymentIdFromUrl() {
            const urlParts = window.location.pathname.split('/');
            return urlParts[urlParts.length - 1];
        }

        function loadTransactionDetails(paymentId) {
            fetch(`/decta/reports/transaction/${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayTransactionDetails(data.data);
                    } else {
                        showError(data.message || 'Failed to load transaction details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Network error occurred while loading transaction details');
                });
        }

        function displayTransactionDetails(transaction) {
            // Update page title with payment ID
            document.title = `Transaction ${transaction.payment_id} - Decta Reports`;

            // Display overview cards
            displayOverview(transaction);

            // Display detailed sections
            displayCoreTransactionData(transaction.transaction_details);
            displayMerchantInformation(transaction.merchant_details);
            displayCardInformation(transaction.card_details);
            displayMatchingStatus(transaction.matching_status);
            displayGatewayInformation(transaction.gateway_info);
            displayMatchingAttempts(transaction.matching_status.attempts);
            displayFileInformation(transaction.file_info);
        }

        function displayOverview(transaction) {
            const overview = document.getElementById('transactionOverview');
            const amount = transaction.transaction_details.amount;
            const currency = transaction.transaction_details.currency || 'EUR';
            const isMatched = transaction.matching_status.is_matched;
            const status = transaction.matching_status.status;

            overview.innerHTML = `
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900">${transaction.payment_id}</div>
                    <div class="text-sm text-gray-500">Payment ID</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900">${currency} ${amount.toFixed(2)}</div>
                    <div class="text-sm text-gray-500">Amount</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold ${isMatched ? 'text-green-600' : 'text-red-600'}">
                        ${isMatched ? '✓ Matched' : '✗ Unmatched'}
                    </div>
                    <div class="text-sm text-gray-500">Status</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900">${new Date(transaction.transaction_details.date_time).toLocaleDateString()}</div>
                    <div class="text-sm text-gray-500">Transaction Date</div>
                </div>
            `;
        }

        function displayCoreTransactionData(data) {
            const container = document.getElementById('coreTransactionData');
            container.innerHTML = `
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Amount</label>
                        <div class="mt-1 text-sm text-gray-900">${data.currency || 'EUR'} ${data.amount.toFixed(2)}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Currency</label>
                        <div class="mt-1 text-sm text-gray-900">${data.currency || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Transaction Type</label>
                        <div class="mt-1 text-sm text-gray-900">${data.type || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Date & Time</label>
                        <div class="mt-1 text-sm text-gray-900">${new Date(data.date_time).toLocaleString()}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Approval ID</label>
                        <div class="mt-1 text-sm text-gray-900">${data.approval_id || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Return Reference</label>
                        <div class="mt-1 text-sm text-gray-900">${data.return_reference || 'N/A'}</div>
                    </div>
                </div>
            `;
        }

        function displayMerchantInformation(data) {
            const container = document.getElementById('merchantInformation');
            container.innerHTML = `
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Merchant ID</label>
                        <div class="mt-1 text-sm text-gray-900">${data.id || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Merchant Name</label>
                        <div class="mt-1 text-sm text-gray-900">${data.name || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Legal Name</label>
                        <div class="mt-1 text-sm text-gray-900">${data.legal_name || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Terminal ID</label>
                        <div class="mt-1 text-sm text-gray-900">${data.terminal_id || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Country</label>
                        <div class="mt-1 text-sm text-gray-900">${data.country || 'N/A'}</div>
                    </div>
                </div>
            `;
        }

        function displayCardInformation(data) {
            const container = document.getElementById('cardInformation');
            container.innerHTML = `
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Card Number (Masked)</label>
                        <div class="mt-1 text-sm text-gray-900 font-mono">${data.masked_number || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Card Type</label>
                        <div class="mt-1 text-sm text-gray-900">${data.type || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Product Type</label>
                        <div class="mt-1 text-sm text-gray-900">${data.product_type || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Product Class</label>
                        <div class="mt-1 text-sm text-gray-900">${data.product_class || 'N/A'}</div>
                    </div>
                </div>
            `;
        }

        function displayMatchingStatus(data) {
            const container = document.getElementById('matchingStatus');
            const statusColor = data.is_matched ? 'text-green-600' : 'text-red-600';
            const statusIcon = data.is_matched ? '✓' : '✗';

            container.innerHTML = `
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Match Status</label>
                        <div class="mt-1 text-sm font-bold ${statusColor}">${statusIcon} ${data.is_matched ? 'Matched' : 'Unmatched'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Processing Status</label>
                        <div class="mt-1 text-sm text-gray-900">${data.status}</div>
                    </div>
                    ${data.matched_at ? `
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Matched At</label>
                        <div class="mt-1 text-sm text-gray-900">${new Date(data.matched_at).toLocaleString()}</div>
                    </div>
                    ` : ''}
                    ${data.error_message ? `
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Error Message</label>
                        <div class="mt-1 text-sm text-red-600">${data.error_message}</div>
                    </div>
                    ` : ''}
                </div>
            `;
        }

        function displayGatewayInformation(data) {
            const container = document.getElementById('gatewayInformation');

            if (!data.transaction_id && !data.account_id) {
                container.innerHTML = '<p class="text-gray-500">No gateway information available</p>';
                return;
            }

            container.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Transaction ID</label>
                        <div class="mt-1 text-sm text-gray-900">${data.transaction_id || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Account ID</label>
                        <div class="mt-1 text-sm text-gray-900">${data.account_id || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Shop ID</label>
                        <div class="mt-1 text-sm text-gray-900">${data.shop_id || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">TRX ID</label>
                        <div class="mt-1 text-sm text-gray-900">${data.trx_id || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Gateway Date</label>
                        <div class="mt-1 text-sm text-gray-900">${data.transaction_date ? new Date(data.transaction_date).toLocaleString() : 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Bank Response Date</label>
                        <div class="mt-1 text-sm text-gray-900">${data.bank_response_date ? new Date(data.bank_response_date).toLocaleString() : 'N/A'}</div>
                    </div>
                </div>
            `;
        }

        function displayMatchingAttempts(attempts) {
            const container = document.getElementById('matchingAttempts');
            const section = document.getElementById('matchingAttemptsSection');

            if (!attempts || attempts.length === 0) {
                section.style.display = 'none';
                return;
            }

            section.style.display = 'block';

            let html = '<div class="space-y-4">';
            attempts.forEach((attempt, index) => {
                html += `
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <div class="flex justify-between items-start mb-2">
                            <h5 class="font-medium text-gray-900">Attempt ${index + 1}</h5>
                            <span class="text-sm text-gray-500">${new Date(attempt.attempted_at).toLocaleString()}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="font-medium">Strategy:</span> ${attempt.strategy || 'N/A'}
                            </div>
                            <div>
                                <span class="font-medium">Result:</span> ${attempt.result || 'N/A'}
                            </div>
                        </div>
                        ${attempt.search_criteria ? `
                        <div class="mt-2 text-sm">
                            <span class="font-medium">Search Criteria:</span>
                            <pre class="mt-1 bg-gray-100 p-2 rounded text-xs overflow-x-auto">${JSON.stringify(attempt.search_criteria, null, 2)}</pre>
                        </div>
                        ` : ''}
                    </div>
                `;
            });
            html += '</div>';

            container.innerHTML = html;
        }

        function displayFileInformation(data) {
            const container = document.getElementById('fileInformation');
            container.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Filename</label>
                        <div class="mt-1 text-sm text-gray-900">${data.filename || 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Processed At</label>
                        <div class="mt-1 text-sm text-gray-900">${data.processed_at ? new Date(data.processed_at).toLocaleString() : 'N/A'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">File Status</label>
                        <div class="mt-1 text-sm text-gray-900">${data.status || 'N/A'}</div>
                    </div>
                </div>
            `;
        }

        function setupEventListeners() {
            // Manual match modal
            document.getElementById('manualMatchBtn').addEventListener('click', () => {
                document.getElementById('manualMatchModal').classList.remove('hidden');
            });

            document.getElementById('closeModalBtn').addEventListener('click', closeModal);
            document.getElementById('cancelMatchBtn').addEventListener('click', closeModal);

            // Manual match form
            document.getElementById('manualMatchForm').addEventListener('submit', handleManualMatch);

            // Other action buttons
            document.getElementById('retryMatchingBtn').addEventListener('click', handleRetryMatching);
            document.getElementById('exportTransactionBtn').addEventListener('click', handleExport);
        }

        function closeModal() {
            document.getElementById('manualMatchModal').classList.add('hidden');
        }

        function handleManualMatch(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());
            data.transaction_id = getPaymentIdFromUrl();

            // Submit manual match
            fetch('/decta/dashboard/manual-match', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        closeModal();
                        location.reload(); // Refresh to show updated data
                    } else {
                        alert('Manual match failed: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error occurred');
                });
        }

        function handleRetryMatching() {
            if (confirm('Are you sure you want to retry matching for this transaction?')) {
                // Implementation for retry matching
                alert('Retry matching functionality to be implemented');
            }
        }

        function handleExport() {
            const paymentId = getPaymentIdFromUrl();
            window.open(`/decta/reports/transaction/${paymentId}?export=json`, '_blank');
        }

        function showError(message) {
            const container = document.getElementById('transactionOverview');
            container.innerHTML = `
                <div class="col-span-4 text-center py-8">
                    <div class="text-red-500 text-lg font-medium">${message}</div>
                </div>
            `;
        }
    </script>
</x-app-layout>
