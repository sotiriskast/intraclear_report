import React, { useState, useEffect, Suspense } from 'react';
import MerchantSelector from './MerchantSelector.jsx';
import SummaryCards from './SummaryCards.jsx';
import DashboardTabs from './DashboardTabs.jsx';
import LoadingSpinner from './Utilities/LoadingSpinner';
import ErrorDisplay from './Utilities/ErrorDisplay';
import { fetchAPI } from '../../services/api';

const MerchantDashboard = ({ merchantId: initialMerchantId }) => {
    const [isAuthenticated, setIsAuthenticated] = useState(true);
    const [merchants, setMerchants] = useState([]);
    const [currencies] = useState(['EUR', 'USD', 'GBP', 'JPY']);
    const [selectedMerchant, setSelectedMerchant] = useState(null);
    const [loading, setLoading] = useState(false);
    const [reserveData, setReserveData] = useState({
        pending_reserves: {},
        total_reserved_eur: 0,
        statistics: { pending_count: 0, released_count: 0 }
    });
    const [feeHistory, setFeeHistory] = useState([]);
    const [upcomingReleases, setUpcomingReleases] = useState([]);
    const [error, setError] = useState(null);

    // Fetch merchants list
    useEffect(() => {
        const fetchMerchants = async () => {
            setLoading(true);
            try {
                const result = await fetchAPI('/api/v1/dashboard/merchants');
                if (result.success && result.data) {
                    setMerchants(result.data);
                    // Always start with All Merchants
                    setSelectedMerchant(null);
                }
            } catch (err) {
                setError('Failed to load merchants: ' + err.message);
            } finally {
                setLoading(false);
            }
        };

        fetchMerchants();
    }, []);

    // Fetch dashboard data
    useEffect(() => {
        const fetchDashboardData = async () => {
            setLoading(true);
            setError(null);

            // Reset all data
            setReserveData({
                pending_reserves: {},
                total_reserved_eur: 0,
                statistics: { pending_count: 0, released_count: 0 }
            });
            setFeeHistory([]);
            setUpcomingReleases([]);

            try {
                // Construct base query parameters
                // When selectedMerchant is null, we want to fetch all merchants' data
                const baseParams = selectedMerchant
                    ? `merchant_id=${selectedMerchant}`
                    : '';

                // Fetch ALL data concurrently
                const [reservesResponse, feeResponse] = await Promise.all([
                    fetchAPI(`/api/v1/dashboard/rolling-reserve?${baseParams}`), // Remove &status=pending to get all reserves
                    fetchAPI(`/api/v1/dashboard/fees/history?${baseParams}`)
                ]);

                // Process Reserves
                if (reservesResponse.success) {
                    const reserves = reservesResponse.data.reserves || [];
                    const totalReservedEur = reservesResponse.data.total_reserved_eur || 0;

                    // Get statistics directly from the API response
                    const statistics = reservesResponse.data.statistics || {
                        pending_count: 0,
                        released_count: 0
                    };

                    // For debugging
                    console.log('API Response Statistics:', statistics);

                    // Aggregate pending reserves by currency
                    const pendingReserves = {};

                    reserves.forEach(reserve => {
                        // Only aggregate pending reserves for the chart
                        if (reserve.status !== 'pending') return;

                        const currency = reserve.currency || reserve.original_currency;

                        // Aggregate reserve amounts - ensure we're dealing with numbers
                        if (!pendingReserves[currency]) {
                            pendingReserves[currency] = 0;
                        }
                        // Parse as float and add to ensure proper addition
                        pendingReserves[currency] += parseFloat(reserve.amount || 0);
                    });

                    // Update reserve data
                    setReserveData({
                        pending_reserves: pendingReserves,
                        total_reserved_eur: totalReservedEur,
                        statistics: statistics  // Use statistics from API directly
                    });

                    // Process Upcoming Releases
                    const byMonth = {};
                    reserves.forEach(release => {
                        // Only process pending reserves for upcoming releases
                        if (release.status !== 'pending' || !release.release || !release.release.due_date) return;

                        const releaseDate = new Date(release.release.due_date);
                        const monthKey = `${releaseDate.getMonth()}-${releaseDate.getFullYear()}`;

                        if (!byMonth[monthKey]) {
                            byMonth[monthKey] = {
                                month: releaseDate.toLocaleString('default', { month: 'short' }),
                                year: releaseDate.getFullYear(),
                                fullDate: releaseDate,
                                EUR: 0,
                                USD: 0,
                                GBP: 0,
                                JPY: 0
                            };
                        }

                        const currency = release.currency || release.original_currency || 'EUR';
                        // Ensure we're adding numbers properly
                        byMonth[monthKey][currency] = (byMonth[monthKey][currency] || 0) + parseFloat(release.amount || 0);
                    });

                    // Show all upcoming releases without limiting to 6
                    const sortedReleases = Object.values(byMonth)
                        .sort((a, b) => a.fullDate - b.fullDate);

                    setUpcomingReleases(sortedReleases);
                }

                // Process Fee History
                if (feeResponse.success) {
                    const fees = feeResponse.data || [];
                    const feesByMonth = {};

                    fees.forEach(fee => {
                        const feeDate = new Date(fee.applied_date);
                        const monthKey = `${feeDate.getMonth()}-${feeDate.getFullYear()}`;

                        if (!feesByMonth[monthKey]) {
                            feesByMonth[monthKey] = {
                                month: feeDate.toLocaleString('default', { month: 'short' }),
                                year: feeDate.getFullYear(),
                                fullDate: feeDate
                            };
                        }

                        const feeType = fee.fee_type || 'Unknown';
                        const feeKey = `${feeType}_EUR`;

                        // Ensure we're adding numbers properly with proper parsing
                        feesByMonth[monthKey][feeKey] =
                            (feesByMonth[monthKey][feeKey] || 0) + parseFloat(fee.fee_amount_eur || 0);
                    });

                    // Don't limit to just 6 months, show all history
                    const sortedFees = Object.values(feesByMonth)
                        .sort((a, b) => a.fullDate - b.fullDate);

                    setFeeHistory(sortedFees);
                }
            } catch (err) {
                setError('Failed to load dashboard data: ' + err.message);
                if (err.message.includes('Authentication required')) {
                    setIsAuthenticated(false);
                    window.location.href = '/login';
                }
            } finally {
                setLoading(false);
            }
        };

        // Always attempt to fetch data, even when selectedMerchant is null
        fetchDashboardData();
    }, [selectedMerchant]);

    if (!isAuthenticated) {
        return <ErrorDisplay message="Please log in to view the dashboard" redirectUrl="/login" />;
    }

    if (loading && merchants.length === 0) {
        return <LoadingSpinner message="Loading merchants..." />;
    }

    if (error) {
        return <ErrorDisplay message={error} onRetry={() => window.location.reload()} />;
    }

    return (
        <div className="w-full max-w-7xl mx-auto p-4 space-y-6 bg-zinc-50 min-h-svh">
            <div className="bg-white p-4 rounded-lg shadow-sm border border-zinc-200 mb-6">
                <div className="flex flex-col md:flex-row justify-between items-center gap-4">
                    <h2 className="text-xl font-bold text-zinc-800">Merchant Rolling Reserve Dashboard</h2>
                    <div className="flex flex-col sm:flex-row gap-4 w-full md:w-auto">
                        <MerchantSelector
                            merchants={merchants}
                            selectedMerchant={selectedMerchant}
                            setSelectedMerchant={setSelectedMerchant}
                            loading={loading}
                        />
                    </div>
                </div>
            </div>

            {loading ? (
                <div className="flex justify-center items-center py-20">
                    <LoadingSpinner message="Loading dashboard data..." />
                </div>
            ) : (
                <Suspense fallback={<LoadingSpinner message="Loading components..." />}>
                    <SummaryCards
                        upcomingReleases={upcomingReleases}
                        reserveData={reserveData}
                        feeHistory={feeHistory}
                        currencies={currencies}
                    />
                    <DashboardTabs
                        reserveData={reserveData}
                        feeHistory={feeHistory}
                        upcomingReleases={upcomingReleases}
                        currencies={currencies}
                    />
                </Suspense>
            )}
        </div>
    );
};

export default MerchantDashboard;
