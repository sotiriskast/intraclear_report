import React, { useState, useEffect } from 'react';
import MerchantSelector from '../MerchantSelector';
import SummaryCards from '../SummaryCards';
import DashboardTabs from '../DashboardTabs';
import LoadingSpinner from '../Utilities/LoadingSpinner';
import ErrorDisplay from '../Utilities/ErrorDisplay';
import { fetchAPI } from '../../services/api';

const MerchantDashboard = ({ merchantId: initialMerchantId }) => {
    const [isAuthenticated, setIsAuthenticated] = useState(true);
    const [merchants, setMerchants] = useState([]);
    const [currencies] = useState(['EUR', 'USD', 'GBP', 'JPY']);
    const [selectedMerchant, setSelectedMerchant] = useState(initialMerchantId || null);
    const [loading, setLoading] = useState(false);
    const [reserveData, setReserveData] = useState({
        pending_reserves: {},
        statistics: { pending_count: 0, released_count: 0 }
    });
    const [feeHistory, setFeeHistory] = useState({});
    const [upcomingReleases, setUpcomingReleases] = useState({});
    const [error, setError] = useState(null);

    // Fetch merchants list
    useEffect(() => {
        const fetchMerchants = async () => {
            setLoading(true);
            try {
                const result = await fetchAPI('/api/v1/dashboard/merchants');
                if (result.success && result.data) {
                    setMerchants(result.data);
                    if (!selectedMerchant && result.data.length > 0) {
                        setSelectedMerchant(result.data[0].id);
                    }
                }
            } catch (err) {
                setError('Failed to load merchants: ' + err.message);
            } finally {
                setLoading(false);
            }
        };

        fetchMerchants();
    }, []);

    // Fetch reserve, fee, and upcoming releases data for all currencies when merchant changes
    useEffect(() => {
        const fetchDataForCurrency = async (currency) => {
            try {
                // Fetch rolling reserve summary
                const reserveUrl = selectedMerchant
                    ? `/api/v1/dashboard/rolling-reserve/summary?merchant_id=${selectedMerchant}&currency=${currency}`
                    : `/api/v1/dashboard/rolling-reserve/summary?currency=${currency}`;
                const reserveResponse = await fetchAPI(reserveUrl);

                if (reserveResponse.success) {
                    setReserveData(prevData => ({
                        ...prevData,
                        pending_reserves: {
                            ...prevData.pending_reserves,
                            ...reserveResponse.data?.pending_reserves
                        },
                        statistics: {
                            pending_count: (prevData.statistics?.pending_count || 0) + (reserveResponse.data?.statistics?.pending_count || 0),
                            released_count: (prevData.statistics?.released_count || 0) + (reserveResponse.data?.statistics?.released_count || 0)
                        }
                    }));
                }

                // Fetch upcoming releases
                const releasesUrl = selectedMerchant
                    ? `/api/v1/dashboard/rolling-reserve?merchant_id=${selectedMerchant}&status=pending&currency=${currency}`
                    : `/api/v1/dashboard/rolling-reserve?status=pending&currency=${currency}`;
                const releasesResponse = await fetchAPI(releasesUrl);

                if (releasesResponse.success) {
                    // Process releases by month
                    const releases = releasesResponse.data || [];
                    const byMonth = {};

                    releases.forEach(release => {
                        const releaseDate = new Date(release.release.due_date);
                        const key = `${releaseDate.getMonth()}-${releaseDate.getFullYear()}`;

                        if (!byMonth[key]) {
                            byMonth[key] = {
                                month: releaseDate.toLocaleString('default', { month: 'short' }),
                                year: releaseDate.getFullYear(),
                                fullDate: releaseDate
                            };
                        }

                        // Store value by currency
                        if (!byMonth[key][currency]) {
                            byMonth[key][currency] = 0;
                        }

                        byMonth[key][currency] += release.amount;
                    });

                    const releasesByMonth = Object.values(byMonth)
                        .sort((a, b) => a.fullDate - b.fullDate)
                        .slice(0, 6);

                    setUpcomingReleases(prevReleases => {
                        // Merge with existing releases
                        const mergedReleases = { ...prevReleases };

                        // For each month in the new data
                        releasesByMonth.forEach(monthData => {
                            const monthKey = `${monthData.month}-${monthData.year}`;

                            // If we don't have this month yet, add it
                            if (!mergedReleases[monthKey]) {
                                mergedReleases[monthKey] = {
                                    month: monthData.month,
                                    year: monthData.year,
                                    fullDate: monthData.fullDate
                                };
                            }

                            // Add the currency data
                            mergedReleases[monthKey][currency] = monthData[currency];
                        });

                        return mergedReleases;
                    });
                }

                // Fetch fee history
                const feeUrl = selectedMerchant
                    ? `/api/v1/dashboard/fees/history?merchant_id=${selectedMerchant}`
                    : `/api/v1/dashboard/fees/history`;
                const feeResponse = await fetchAPI(feeUrl);

                if (feeResponse.success) {
                    // Process fee history by month
                    const fees = feeResponse.data || [];
                    const feesByMonth = {};

                    fees.forEach(fee => {
                        const feeDate = new Date(fee.applied_date);
                        const key = `${feeDate.getMonth()}-${feeDate.getFullYear()}`;

                        if (!feesByMonth[key]) {
                            feesByMonth[key] = {
                                month: feeDate.toLocaleString('default', { month: 'short' }),
                                year: feeDate.getFullYear(),
                                fullDate: feeDate
                            };
                        }

                        // Create a key combining fee type and currency (always EUR)
                        const feeType = fee.fee_type || 'Unknown';
                        const feeKey = `${feeType}_EUR`;

                        // Add to the appropriate fee type, using fee_amount_eur from the API
                        feesByMonth[key][feeKey] = (feesByMonth[key][feeKey] || 0) + Number(fee.fee_amount_eur);
                    });

                    const feeHistoryByMonth = Object.values(feesByMonth)
                        .sort((a, b) => a.fullDate - b.fullDate) // Sort chronologically
                        .slice(0, 6);

                    setFeeHistory(feeHistoryByMonth);

                }
            } catch (err) {
                console.error(`Error fetching data for ${currency}:`, err);
                // Don't set error state for individual currency failures
            }
        };

        const fetchAllData = async () => {
            setLoading(true);
            setError(null);

            // Reset data stores for new merchant
            setReserveData({
                pending_reserves: {},
                statistics: { pending_count: 0, released_count: 0 }
            });
            setFeeHistory({});
            setUpcomingReleases({});

            try {
                // Fetch data for all currencies in parallel
                await Promise.all(currencies.map(currency => fetchDataForCurrency(currency)));
            } catch (err) {
                setError('Failed to load data: ' + err.message);
                if (err.message.includes('Authentication required')) {
                    setIsAuthenticated(false);
                    window.location.href = '/login';
                }
            } finally {
                setLoading(false);
            }
        };

        // Fetch data regardless of merchant selection (both for specific merchant or all merchants)
        fetchAllData();
    }, [selectedMerchant, currencies]);

    // Process upcoming releases and fee history from object to array format for charts
    const processedUpcomingReleases = Object.values(upcomingReleases)
        .sort((a, b) => new Date(a.fullDate) - new Date(b.fullDate));

    const processedFeeHistory = Object.values(feeHistory)
        .sort((a, b) => new Date(a.fullDate) - new Date(b.fullDate));

    if (!isAuthenticated) {
        return <ErrorDisplay message="Please log in to view the dashboard" redirectUrl="/login" />;
    }

    if (loading && !selectedMerchant && merchants.length === 0) {
        return <LoadingSpinner message="Loading merchants..." />;
    }

    if (error) {
        return <ErrorDisplay message={error} onRetry={() => window.location.reload()} />;
    }

    return (
        <div className="w-full max-w-7xl mx-auto p-4 space-y-6 bg-gray-50 min-h-screen">
            <div className="bg-white p-4 rounded-lg shadow-sm border mb-6">
                <div className="flex flex-col md:flex-row justify-between items-center gap-4">
                    <h2 className="text-xl font-bold text-gray-800">Merchant Rolling Reserve Dashboard</h2>
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
                <>
                    <SummaryCards
                        upcomingReleases={processedUpcomingReleases}
                        reserveData={reserveData}
                        feeHistory={processedFeeHistory}
                        currencies={currencies}
                    />
                    <DashboardTabs
                        reserveData={reserveData}
                        feeHistory={processedFeeHistory}
                        upcomingReleases={processedUpcomingReleases}
                        currencies={currencies}
                    />
                </>
            )}
        </div>
    );
};

export default MerchantDashboard;
