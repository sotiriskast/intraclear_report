import React, {useState, useEffect, useCallback} from 'react';
import {
    BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
    LineChart, Line, PieChart, Pie, Cell, Sector
} from 'recharts';

// Color palette for charts
const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884d8', '#82ca9d'];

/**
 * Dashboard component for visualizing merchant rolling reserves and fee data
 */
const MerchantDashboard = ({merchantId: initialMerchantId}) => {
    const [isAuthenticated, setIsAuthenticated] = useState(true);
    const [merchants, setMerchants] = useState([]);
    const [currencies, setCurrencies] = useState(['EUR', 'USD', 'GBP']);
    const [selectedMerchant, setSelectedMerchant] = useState(initialMerchantId || null);
    const [selectedCurrency, setSelectedCurrency] = useState('EUR');
    const [loading, setLoading] = useState(false);
    const [reserveData, setReserveData] = useState({
        pending_reserves: {},
        statistics: {pending_count: 0, released_count: 0},
        upcoming_releases: {}
    });
    const [feeHistory, setFeeHistory] = useState([]);
    const [upcomingReleases, setUpcomingReleases] = useState([]);
    const [error, setError] = useState(null);
    const [activeTab, setActiveTab] = useState('reserves');
    const [activePieIndex, setActivePieIndex] = useState(0);

    // Helper function to make authenticated API calls
    const fetchAPI = useCallback(async (url) => {
        const token = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = token ? token.getAttribute('content') : '';

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin' // Important for cookies/session
        });

        if (!response.ok) {
            if (response.status === 401) {
                setIsAuthenticated(false);
                throw new Error('Authentication required');
            }
            throw new Error(`API error: ${response.statusText}`);
        }

        return await response.json();
    }, []);

    // Fetch merchants list
    useEffect(() => {
        const fetchMerchants = async () => {
            try {
                setLoading(true);

                const result = await fetchAPI('/api/v1/dashboard/merchants');

                if (result.success && result.data) {
                    setMerchants(result.data);
                    if (!selectedMerchant && result.data.length > 0) {
                        setSelectedMerchant(result.data[0].id);
                    }
                }
            } catch (err) {
                console.error('Error fetching merchants:', err);
                setError('Failed to load merchants: ' + err.message);
            } finally {
                setLoading(false);
            }
        };

        fetchMerchants();
    }, [fetchAPI, selectedMerchant]);

    // Fetch reserve and fee data when selected merchant or currency changes
    useEffect(() => {
        const fetchData = async () => {
            if (!selectedMerchant) return;

            setLoading(true);
            setError(null);

            try {
                // Fetch rolling reserve data
                const reserveData = await fetchAPI(`/api/v1/dashboard/rolling-reserve/summary?currency=${selectedCurrency}`);
                if (reserveData.success) {
                    setReserveData(reserveData.data);
                }

                // Fetch upcoming releases
                const releasesData = await fetchAPI(`/api/v1/dashboard/rolling-reserve?status=pending&currency=${selectedCurrency}`);
                if (releasesData.success) {
                    // Process upcoming releases by month
                    const releases = releasesData.data;
                    const byMonth = {};

                    // Group by month and year
                    releases.forEach(release => {
                        const releaseDate = new Date(release.release.due_date);
                        const monthYear = `${releaseDate.getMonth()}-${releaseDate.getFullYear()}`;
                        const monthName = releaseDate.toLocaleString('default', {month: 'short'});
                        const year = releaseDate.getFullYear();

                        if (!byMonth[monthYear]) {
                            byMonth[monthYear] = {
                                month: monthName,
                                year: year,
                                fullDate: releaseDate,
                                [selectedCurrency]: 0
                            };
                        }

                        byMonth[monthYear][selectedCurrency] += release.amount;
                    });

                    // Convert to array and sort by date
                    const releasesByMonth = Object.values(byMonth)
                        .sort((a, b) => a.fullDate - b.fullDate)
                        .slice(0, 6); // Get next 6 months

                    setUpcomingReleases(releasesByMonth);
                }

                // Fetch fee history
                const feeData = await fetchAPI(`/api/v1/dashboard/fees/history?merchant_id=${selectedMerchant}&currency=${selectedCurrency}`);

                if (feeData.success) {
                    // Process fee history by month
                    const fees = feeData.data;
                    const feesByMonth = {};

                    fees.forEach(fee => {
                        const feeDate = new Date(fee.applied_date);
                        const monthYear = `${feeDate.getMonth()}-${feeDate.getFullYear()}`;
                        const monthName = feeDate.toLocaleString('default', {month: 'short'});

                        if (!feesByMonth[monthYear]) {
                            feesByMonth[monthYear] = {
                                month: monthName,
                                year: feeDate.getFullYear(),
                                fullDate: feeDate
                            };
                        }

                        if (!feesByMonth[monthYear][fee.fee_type]) {
                            feesByMonth[monthYear][fee.fee_type] = 0;
                        }

                        feesByMonth[monthYear][fee.fee_type] += fee.fee_amount_eur;
                    });

                    // Convert to array and sort by date (recent 6 months)
                    const feeHistoryByMonth = Object.values(feesByMonth)
                        .sort((a, b) => b.fullDate - a.fullDate) // Sort descending
                        .slice(0, 6) // Get last 6 months
                        .reverse(); // Reverse to show oldest to newest

                    setFeeHistory(feeHistoryByMonth);
                }
            } catch (err) {
                console.error('Error fetching data:', err);
                setError('Failed to load data: ' + err.message);

                // If authentication error, redirect to login
                if (!isAuthenticated) {
                    window.location.href = '/login';
                }
            } finally {
                setLoading(false);
            }
        };

        fetchData();
    }, [fetchAPI, selectedMerchant, selectedCurrency, isAuthenticated]);

    // If not authenticated, show login message
    if (!isAuthenticated) {
        return (
            <div className="p-6 text-center">
                <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                    <div className="flex">
                        <div className="flex-shrink-0">
                            <svg className="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg"
                                 viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd"
                                      d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                      clipRule="evenodd"/>
                            </svg>
                        </div>
                        <div className="ml-3">
                            <p className="text-sm text-yellow-700">
                                Please log in to view the dashboard
                            </p>
                        </div>
                    </div>
                </div>
                <button
                    onClick={() => window.location.href = '/login'}
                    className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700"
                >
                    Go to Login Page
                </button>
            </div>
        );
    }

    if (loading && !selectedMerchant) {
        return (
            <div className="flex justify-center items-center h-full">
                <div className="flex flex-col items-center space-y-4">
                    <div className="animate-spin h-12 w-12 text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-full w-full" fill="none"
                             viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor"
                                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <p className="text-xl font-semibold">Loading merchants...</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="p-6">
                <div className="bg-red-50 border-l-4 border-red-500 p-4">
                    <div className="flex">
                        <div className="flex-shrink-0">
                            <svg className="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                 fill="currentColor" aria-hidden="true">
                                <path fillRule="evenodd"
                                      d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                      clipRule="evenodd"/>
                            </svg>
                        </div>
                        <div className="ml-3">
                            <h3 className="text-sm font-medium text-red-800">Error</h3>
                            <div className="mt-2 text-sm text-red-700">
                                <p>{error}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <button
                    className="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    onClick={() => window.location.reload()}
                >
                    Retry
                </button>
            </div>
        );
    }

    // Format data for the rolling reserve balance chart
    const prepareRollingReserveChartData = () => {
        return Object.entries(reserveData.pending_reserves || {}).map(([currency, amount]) => ({
            currency,
            amount: Number(amount)
        }));
    };

    // Calculate total fees collected across all types
    const calculateTotalFees = () => {
        if (!feeHistory.length) return 0;

        // Sum up all fee types across all months
        return feeHistory.reduce((total, month) => {
            const monthTotal = Object.entries(month)
                .filter(([key]) => key !== 'month' && key !== 'year' && key !== 'fullDate')
                .reduce((sum, [_, value]) => sum + Number(value), 0);
            return total + monthTotal;
        }, 0);
    };

    // Get fee types from fee history data
    const getFeeTypes = () => {
        const types = new Set();
        feeHistory.forEach(month => {
            Object.keys(month).forEach(key => {
                if (key !== 'month' && key !== 'year' && key !== 'fullDate') {
                    types.add(key);
                }
            });
        });
        return Array.from(types);
    };

    // Calculate fee distribution by type
    const calculateFeeDistribution = () => {
        const distribution = {};
        const feeTypes = getFeeTypes();

        feeTypes.forEach(type => {
            distribution[type] = feeHistory.reduce((sum, month) => sum + (Number(month[type]) || 0), 0);
        });

        return Object.entries(distribution).map(([name, value]) => ({name, value}));
    };

    // Active shape for pie chart
    const renderActiveShape = (props) => {
        const {
            cx, cy, midAngle, innerRadius, outerRadius, startAngle, endAngle,
            fill, payload, percent, value
        } = props;
        const sin = Math.sin(-midAngle * Math.PI / 180);
        const cos = Math.cos(-midAngle * Math.PI / 180);
        const sx = cx + (outerRadius + 10) * cos;
        const sy = cy + (outerRadius + 10) * sin;
        const mx = cx + (outerRadius + 30) * cos;
        const my = cy + (outerRadius + 30) * sin;
        const ex = mx + (cos >= 0 ? 1 : -1) * 22;
        const ey = my;
        const textAnchor = cos >= 0 ? 'start' : 'end';

        return (
            <g>
                <text x={cx} y={cy} dy={8} textAnchor="middle" fill={fill}>
                    {payload.name}
                </text>
                <path d={`M${sx},${sy}L${mx},${my}L${ex},${ey}`} stroke={fill} fill="none"/>
                <circle cx={ex} cy={ey} r={2} fill={fill} stroke="none"/>
                <text x={ex + (cos >= 0 ? 1 : -1) * 12} y={ey} textAnchor={textAnchor} fill="#333">
                    {`${value.toFixed(2)} €`}
                </text>
                <text x={ex + (cos >= 0 ? 1 : -1) * 12} y={ey} dy={18} textAnchor={textAnchor} fill="#999">
                    {`(${(percent * 100).toFixed(2)}%)`}
                </text>
            </g>
        );
    };

    return (
        <div className="w-full max-w-7xl mx-auto p-4 space-y-6">
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h2 className="text-xl font-bold text-gray-900">Merchant Rolling Reserve Dashboard</h2>
                <div className="flex flex-col sm:flex-row gap-4">
                    <div>
                        <label htmlFor="merchant-select"
                               className="block text-sm font-medium text-gray-700">Merchant</label>
                        <select
                            id="merchant-select"
                            className="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                            value={selectedMerchant?.toString() || ''}
                            onChange={(e) => setSelectedMerchant(Number(e.target.value))}
                            disabled={loading}
                        >
                            <option value="" disabled>Select Merchant</option>
                            {merchants.map(merchant => (
                                <option key={merchant.id} value={merchant.id.toString()}>
                                    {merchant.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label htmlFor="currency-select"
                               className="block text-sm font-medium text-gray-700">Currency</label>
                        <select
                            id="currency-select"
                            className="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                            value={selectedCurrency}
                            onChange={(e) => setSelectedCurrency(e.target.value)}
                            disabled={loading}
                        >
                            {currencies.map(currency => (
                                <option key={currency} value={currency}>
                                    {currency}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>

            {loading && (
                <div className="flex justify-center items-center py-12">
                    <div className="animate-spin h-8 w-8 text-blue-600 mr-2">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-full w-full" fill="none"
                             viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor"
                                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <p className="text-lg">Loading dashboard data...</p>
                </div>
            )}

            {!loading && (
                <>
                    {/* Summary Cards */}
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <div className="bg-white overflow-hidden shadow rounded-lg">
                            <div className="px-4 py-5 sm:p-6">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-sm font-medium text-gray-500">Next Release Amount</h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 text-gray-500"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div className="mt-1">
                                    <p className="text-2xl font-bold text-gray-900">
                                        {upcomingReleases[0] && upcomingReleases[0][selectedCurrency] ?
                                            `${Number(upcomingReleases[0][selectedCurrency]).toFixed(2)} ${selectedCurrency}` :
                                            '0.00 ' + selectedCurrency}
                                    </p>
                                    <p className="text-xs text-gray-500">
                                        {upcomingReleases[0] ?
                                            `Expected in ${upcomingReleases[0].month} ${upcomingReleases[0].year}` :
                                            'No upcoming releases'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow rounded-lg">
                            <div className="px-4 py-5 sm:p-6">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-sm font-medium text-gray-500">Total Released</h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 text-gray-500"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                              d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                    </svg>
                                </div>
                                <div className="mt-1">
                                    <p className="text-2xl font-bold text-gray-900">
                                        {reserveData.statistics.released_count || 0} entries
                                    </p>
                                    <p className="text-xs text-gray-500">
                                        Historical releases
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow rounded-lg">
                            <div className="px-4 py-5 sm:p-6">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-sm font-medium text-gray-500">Total Fees Collected</h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 text-gray-500"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                              d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                    </svg>
                                </div>
                                <div className="mt-1">
                                    <p className="text-2xl font-bold text-gray-900">
                                        {calculateTotalFees().toFixed(2)} EUR
                                    </p>
                                    <p className="text-xs text-gray-500">
                                        For all fee types
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Tabs */}
                    <div className="bg-white shadow rounded-lg overflow-hidden">
                        <div className="border-b border-gray-200">
                            <nav className="-mb-px flex" aria-label="Tabs">
                                <button
                                    onClick={() => setActiveTab('reserves')}
                                    className={`${
                                        activeTab === 'reserves'
                                            ? 'border-blue-500 text-blue-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    } w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm`}
                                >
                                    Rolling Reserves
                                </button>
                                <button
                                    onClick={() => setActiveTab('upcoming')}
                                    className={`${
                                        activeTab === 'upcoming'
                                            ? 'border-blue-500 text-blue-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    } w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm`}
                                >
                                    Upcoming Releases
                                </button>
                                <button
                                    onClick={() => setActiveTab('fees')}
                                    className={`${
                                        activeTab === 'fees'
                                            ? 'border-blue-500 text-blue-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    } w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm`}
                                >
                                    Fee Analysis
                                </button>
                            </nav>
                        </div>

                        {/* Tab Content - Rolling Reserves */}
                        {activeTab === 'reserves' && (
                            <div className="p-4 space-y-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="bg-white overflow-hidden shadow rounded-lg border">
                                        <div className="px-4 py-5 border-b border-gray-200 sm:px-6">
                                            <h3 className="text-lg leading-6 font-medium text-gray-900">
                                                Rolling Reserve Balance by Currency
                                            </h3>
                                            <p className="mt-1 max-w-2xl text-sm text-gray-500">
                                                Current pending reserve amounts across all currencies
                                            </p>
                                        </div>
                                        <div className="px-4 py-5 sm:p-6 h-80">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <BarChart
                                                    data={prepareRollingReserveChartData()}
                                                    margin={{top: 20, right: 30, left: 20, bottom: 5}}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3"/>
                                                    <XAxis dataKey="currency"/>
                                                    <YAxis/>
                                                    <Tooltip formatter={(value) => [`${value.toFixed(2)}`, 'Amount']}/>
                                                    <Legend/>
                                                    <Bar dataKey="amount" name="Pending Amount" fill="#8884d8"/>
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </div>

                                    <div className="bg-white overflow-hidden shadow rounded-lg border">
                                        <div className="px-4 py-5 border-b border-gray-200 sm:px-6">
                                            <h3 className="text-lg leading-6 font-medium text-gray-900">
                                                Reserve Status Distribution
                                            </h3>
                                            <p className="mt-1 max-w-2xl text-sm text-gray-500">
                                                Pending vs Released reserves
                                            </p>
                                        </div>
                                        <div className="px-4 py-5 sm:p-6 h-80">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <PieChart>
                                                    <Pie
                                                        activeIndex={activePieIndex}
                                                        activeShape={renderActiveShape}
                                                        data={[
                                                            {
                                                                name: 'Pending',
                                                                value: reserveData.statistics.pending_count || 0
                                                            },
                                                            {
                                                                name: 'Released',
                                                                value: reserveData.statistics.released_count || 0
                                                            }
                                                        ]}
                                                        cx="50%"
                                                        cy="50%"
                                                        innerRadius={70}
                                                        outerRadius={90}
                                                        fill="#8884d8"
                                                        dataKey="value"
                                                        onMouseEnter={(_, index) => setActivePieIndex(index)}
                                                    >
                                                        {[
                                                            {
                                                                name: 'Pending',
                                                                value: reserveData.statistics.pending_count || 0
                                                            },
                                                            {
                                                                name: 'Released',
                                                                value: reserveData.statistics.released_count || 0
                                                            }
                                                        ].map((entry, index) => (
                                                            <Cell key={`cell-${index}`}
                                                                  fill={COLORS[index % COLORS.length]}/>
                                                        ))}
                                                    </Pie>
                                                    <Tooltip formatter={(value) => [`${value}`, 'Count']}/>
                                                </PieChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Tab Content - Upcoming Releases */}
                        {activeTab === 'upcoming' && (
                            <div className="p-4">
                                <div className="bg-white overflow-hidden shadow rounded-lg border">
                                    <div className="px-4 py-5 border-b border-gray-200 sm:px-6">
                                        <h3 className="text-lg leading-6 font-medium text-gray-900">
                                            Upcoming Reserve Releases (6 Month Projection)
                                        </h3>
                                        <p className="mt-1 max-w-2xl text-sm text-gray-500">
                                            Projected release amounts by month and currency
                                        </p>
                                    </div>
                                    <div className="px-4 py-5 sm:p-6 h-96">
                                        {upcomingReleases.length > 0 ? (
                                            <ResponsiveContainer width="100%" height="100%">
                                                <LineChart
                                                    data={upcomingReleases}
                                                    margin={{top: 20, right: 30, left: 20, bottom: 50}}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3"/>
                                                    <XAxis
                                                        dataKey={(data) => `${data.month} ${data.year}`}
                                                        interval="preserveStartEnd"
                                                        angle={-45}
                                                        textAnchor="end"
                                                    />
                                                    <YAxis
                                                        label={{
                                                            value: `Amount (${selectedCurrency})`,
                                                            angle: -90,
                                                            position: 'insideLeft',
                                                            offset: 10
                                                        }}
                                                    />
                                                    <Tooltip
                                                        formatter={(value) => [`${value.toFixed(2)} ${selectedCurrency}`, 'Amount']}
                                                        labelFormatter={(label) => label}
                                                    />
                                                    <Legend
                                                        verticalAlign="top"
                                                        height={36}
                                                    />
                                                    <Line
                                                        type="monotone"
                                                        dataKey={selectedCurrency}
                                                        stroke="#8884d8"
                                                        strokeWidth={3}
                                                        dot={{r: 6}}
                                                    />
                                                </LineChart>
                                            </ResponsiveContainer>
                                        ) : (
                                            <div className="flex justify-center items-center h-full">
                                                <p className="text-gray-500 text-center">
                                                    No upcoming reserve releases found for {selectedCurrency}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Tab Content - Fee Analysis */}
                        {activeTab === 'fees' && (
                            <div className="p-4 space-y-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="bg-white overflow-hidden shadow rounded-lg border">
                                        <div className="px-4 py-5 border-b border-gray-200 sm:px-6">
                                            <h3 className="text-lg leading-6 font-medium text-gray-900">
                                                Monthly Fee History
                                            </h3>
                                            <p className="mt-1 max-w-2xl text-sm text-gray-500">
                                                Fee breakdown over the last 6 months
                                            </p>
                                        </div>
                                        <div className="px-4 py-5 sm:p-6 h-80">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <BarChart
                                                    data={feeHistory}
                                                    margin={{top: 20, right: 30, left: 20, bottom: 50}}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3"/>
                                                    <XAxis
                                                        dataKey={(data) => `${data.month} ${data.year}`}
                                                        interval="preserveStartEnd"
                                                        angle={-45}
                                                        textAnchor="end"
                                                    />
                                                    <YAxis
                                                        label={{
                                                            value: 'Amount (EUR)',
                                                            angle: -90,
                                                            position: 'insideLeft',
                                                            offset: 10
                                                        }}
                                                    />
                                                    <Tooltip
                                                        formatter={(value) => [`${value.toFixed(2)} EUR`, 'Amount']}
                                                        labelFormatter={(label) => label}
                                                    />
                                                    <Legend
                                                        verticalAlign="top"
                                                        height={36}
                                                    />
                                                    {getFeeTypes().map((type, index) => (
                                                        <Bar
                                                            key={type}
                                                            dataKey={type}
                                                            name={type}
                                                            fill={COLORS[index % COLORS.length]}
                                                            stackId="a"
                                                        />
                                                    ))}
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </div>

                                    <div className="bg-white overflow-hidden shadow rounded-lg border">
                                        <div className="px-4 py-5 border-b border-gray-200 sm:px-6">
                                            <h3 className="text-lg leading-6 font-medium text-gray-900">
                                                Fee Type Distribution
                                            </h3>
                                            <p className="mt-1 max-w-2xl text-sm text-gray-500">
                                                Breakdown of fees by type
                                            </p>
                                        </div>
                                        <div className="px-4 py-5 sm:p-6 h-80">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <PieChart>
                                                    <Pie
                                                        activeIndex={activePieIndex}
                                                        activeShape={renderActiveShape}
                                                        data={calculateFeeDistribution()}
                                                        cx="50%"
                                                        cy="50%"
                                                        innerRadius={70}
                                                        outerRadius={90}
                                                        fill="#8884d8"
                                                        dataKey="value"
                                                        onMouseEnter={(_, index) => setActivePieIndex(index)}
                                                    >
                                                        {calculateFeeDistribution().map((entry, index) => (
                                                            <Cell key={`cell-${index}`}
                                                                  fill={COLORS[index % COLORS.length]}/>
                                                        ))}
                                                    </Pie>
                                                    <Tooltip
                                                        formatter={(value) => [`${value.toFixed(2)} EUR`, 'Amount']}
                                                        labelFormatter={(label) => label}
                                                    />
                                                </PieChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </>
            )}
        </div>
    );
};

export default MerchantDashboard;
