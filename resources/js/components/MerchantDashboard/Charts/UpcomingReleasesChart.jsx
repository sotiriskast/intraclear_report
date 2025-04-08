import React, { useState } from 'react';
import { ResponsiveContainer, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend } from 'recharts';

// Currency colors - bright and distinct
const CURRENCY_COLORS = {
    'EUR': '#2979FF', // Bright Blue
    'USD': '#00E676', // Bright Green
    'GBP': '#FFEA00', // Bright Yellow
    'JPY': '#FF3D00'  // Bright Orange/Red
};

// Exchange rates (approximate) for normalization
const EXCHANGE_RATES = {
    'EUR': 1,
    'USD': 0.92,  // 1 USD ≈ 0.92 EUR
    'GBP': 1.17,  // 1 GBP ≈ 1.17 EUR
    'JPY': 0.0061 // 1 JPY ≈ 0.0061 EUR (approximately)
};

const UpcomingReleasesChart = ({ upcomingReleases = [], currencies = [] }) => {
    // View modes: "byCurrency" (default), "eurEquivalent" (all in EUR), "separateCharts"
    const [viewMode, setViewMode] = useState('eurEquivalent');

    // Format data for weekly display
    const formatWeeklyData = () => {
        // Convert the monthly data to weekly
        const weeklyData = [];

        // Sort releases by date
        const sortedReleases = [...upcomingReleases].sort(
            (a, b) => new Date(a.fullDate) - new Date(b.fullDate)
        );

        // If no releases, return empty array
        if (sortedReleases.length === 0) return [];

        // Get start date from first release
        const startDate = new Date(sortedReleases[0].fullDate);

        // Generate data for all upcoming releases (not limited to 12 weeks)
        // Calculate how many weeks we need based on the releases
        const endDate = new Date(sortedReleases[sortedReleases.length - 1]?.fullDate || startDate);
        const weekCount = Math.ceil((endDate - startDate) / (7 * 24 * 60 * 60 * 1000)) + 1;
        
        // Generate at least 12 weeks, or more if needed for all data
        const weeksToGenerate = Math.max(12, weekCount);
        
        for (let i = 0; i < weeksToGenerate; i++) {
            const weekStartDate = new Date(startDate);
            weekStartDate.setDate(startDate.getDate() + (i * 7));

            const weekEndDate = new Date(weekStartDate);
            weekEndDate.setDate(weekStartDate.getDate() + 6);

            const weekLabel = `Week ${i + 1} (${weekStartDate.toLocaleDateString('default', { month: 'short', day: 'numeric' })})`;

            // Initialize week data with all currencies at 0
            const weekData = {
                week: weekLabel,
                weekStart: weekStartDate,
                weekEnd: weekEndDate,
                weekNumber: i + 1,
                formattedDate: `${weekStartDate.toLocaleDateString('default', { month: 'short', day: 'numeric' })} - ${weekEndDate.toLocaleDateString('default', { month: 'short', day: 'numeric' })}`,
                // Initialize total EUR amount for consolidated view
                totalEUR: 0
            };

            // Initialize all currencies with 0
            currencies.forEach(curr => {
                weekData[curr] = 0;
                weekData[`${curr}_EUR`] = 0; // Store EUR equivalent separately
            });

            // Find releases that fall in this week
            sortedReleases.forEach(release => {
                const releaseDate = new Date(release.fullDate);
                if (releaseDate >= weekStartDate && releaseDate <= weekEndDate) {
                    // Add values for each currency
                    currencies.forEach(curr => {
                        if (release[curr]) {
                            weekData[curr] += release[curr];

                            // Calculate EUR equivalent based on currency
                            if (curr === 'EUR') {
                                // EUR amount is already in EUR
                                weekData[`${curr}_EUR`] += release[curr];
                                weekData.totalEUR += release[curr];
                            } else {
                                // For other currencies, apply conversion rate
                                const eurValue = release[curr] * EXCHANGE_RATES[curr];
                                weekData[`${curr}_EUR`] += eurValue;
                                weekData.totalEUR += eurValue;
                            }
                        }
                    });
                }
            });

            weeklyData.push(weekData);
        }

        return weeklyData;
    };

    const weeklyData = formatWeeklyData();

    // Format large numbers with K, M abbreviations
    const formatYAxisTick = (value) => {
        if (value === 0) return '0';
        if (value >= 1000000) {
            return `${(value / 1000000).toFixed(1)}M`;
        } else if (value >= 1000) {
            return `${(value / 1000).toFixed(1)}K`;
        }
        return value.toLocaleString();
    };

    // Custom tooltip for better readability
    const CustomTooltip = ({ active, payload, label }) => {
        if (active && payload && payload.length) {
            // Find the corresponding data item
            const weekItem = weeklyData.find(item => item.week === label);

            return (
                <div className="bg-white p-4 border-2 shadow-md rounded-md">
                    <p className="font-bold text-lg mb-1">{label}</p>
                    <p className="text-sm text-gray-600 mb-3">{weekItem?.formattedDate}</p>

                    {viewMode === 'eurEquivalent' ? (
                        // For EUR consolidated view
                        <div className="space-y-2">
                            <div className="flex items-center">
                                <div
                                    className="w-4 h-4 rounded-full mr-2"
                                    style={{ backgroundColor: '#2979FF' }}
                                />
                                <span className="font-medium">Total (EUR):</span>
                                <span className="ml-2 font-bold">
                                    {weekItem?.totalEUR.toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    })}
                                </span>
                            </div>

                            {/* Show breakdown by original currency */}
                            <div className="mt-2 pt-2 border-t border-gray-200">
                                <p className="text-xs text-gray-500 mb-1">Original Currencies:</p>
                                {currencies.map(curr => {
                                    if (weekItem && weekItem[curr] > 0) {
                                        const eurEquiv = weekItem[`${curr}_EUR`];
                                        return (
                                            <div key={curr} className="flex items-center text-xs">
                                                <div
                                                    className="w-3 h-3 rounded-full mr-1"
                                                    style={{ backgroundColor: CURRENCY_COLORS[curr] }}
                                                />
                                                <span>{curr}:</span>
                                                <span className="ml-1 font-medium">
                                                    {curr === 'JPY'
                                                        ? weekItem[curr].toLocaleString(undefined, {
                                                            maximumFractionDigits: 0
                                                        })
                                                        : weekItem[curr].toLocaleString(undefined, {
                                                            minimumFractionDigits: 2,
                                                            maximumFractionDigits: 2
                                                        })
                                                    }
                                                    <span className="text-gray-500 ml-1">
                                                        (≈ {eurEquiv.toLocaleString(undefined, {
                                                        minimumFractionDigits: 2,
                                                        maximumFractionDigits: 2
                                                    })} EUR)
                                                    </span>
                                                </span>
                                            </div>
                                        );
                                    }
                                    return null;
                                })}
                            </div>
                        </div>
                    ) : (
                        // For currency-specific views
                        <div className="space-y-2">
                            {payload.map((entry, index) => {
                                if (entry.value === 0) return null;

                                // Extract the currency from the dataKey
                                const currencyCode = entry.dataKey.split('_')[0] || entry.dataKey;

                                // Calculate EUR equivalent if not showing EUR
                                const showEquivalent = currencyCode !== 'EUR' && currencyCode !== 'totalEUR';
                                const eurEquivalent = showEquivalent ?
                                    entry.value * EXCHANGE_RATES[currencyCode] : null;

                                return (
                                    <div key={`currency-${index}`} className="flex items-center">
                                        <div
                                            className="w-4 h-4 rounded-full mr-2"
                                            style={{ backgroundColor: entry.color }}
                                        />
                                        <span className="font-medium">{currencyCode}:</span>
                                        <span className="ml-2 font-bold">
                                            {currencyCode === 'JPY'
                                                ? entry.value.toLocaleString(undefined, {
                                                    maximumFractionDigits: 0
                                                })
                                                : entry.value.toLocaleString(undefined, {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2
                                                })
                                            }
                                            {showEquivalent && (
                                                <span className="text-sm text-gray-500 ml-1">
                                                    (≈ {eurEquivalent.toLocaleString(undefined, {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2
                                                })} EUR)
                                                </span>
                                            )}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            );
        }
        return null;
    };

    // If no data, show a message
    if (weeklyData.length === 0) {
        return (
            <div className="bg-white shadow rounded-lg border p-4" style={{ minHeight: '650px', height: 'auto' }}>
                <h3 className="text-xl font-bold text-gray-800 mb-4">
                    Weekly Upcoming Releases
                </h3>
                <div className="flex justify-center items-center" style={{ height: '400px' }}>
                    <div className="text-center p-6">
                        <p className="text-xl font-semibold text-gray-700 mb-2">No Upcoming Releases</p>
                        <p className="text-gray-500">There are no scheduled releases in the upcoming weeks.</p>
                    </div>
                </div>
            </div>
        );
    }

    // Find weeks with actual releases (any currency > 0)
    const weeksWithReleases = weeklyData.filter(week =>
        currencies.some(curr => week[curr] > 0)
    );

    return (
        <div className="bg-white shadow rounded-lg border p-4" style={{ minHeight: '650px', height: 'auto' }}>
            <div className="flex justify-between items-center mb-4">
                <h3 className="text-xl font-bold text-gray-800">
                    Weekly Upcoming Releases
                </h3>
                <div className="flex space-x-2">
                    <button
                        onClick={() => setViewMode('byCurrency')}
                        className={`px-3 py-1 text-xs rounded-md ${
                            viewMode === 'byCurrency'
                                ? 'bg-blue-500 text-white'
                                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                        }`}
                    >
                        By Currency
                    </button>
                    <button
                        onClick={() => setViewMode('eurEquivalent')}
                        className={`px-3 py-1 text-xs rounded-md ${
                            viewMode === 'eurEquivalent'
                                ? 'bg-blue-500 text-white'
                                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                        }`}
                    >
                        EUR Only
                    </button>
                    <button
                        onClick={() => setViewMode('separateCharts')}
                        className={`px-3 py-1 text-xs rounded-md ${
                            viewMode === 'separateCharts'
                                ? 'bg-blue-500 text-white'
                                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                        }`}
                    >
                        Separate Charts
                    </button>
                </div>
            </div>

            {viewMode === 'separateCharts' ? (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6" style={{ minHeight: '400px', height: 'auto' }}>
                    {currencies.map(currency => {
                        // Only show charts for currencies that have data
                        const hasData = weeklyData.some(week => week[currency] > 0);
                        if (!hasData) return null;

                        return (
                            <div key={currency} className="bg-gray-50 p-4 rounded-md border" style={{ height: '250px', minHeight: '250px' }}>
                                <h4 className="font-bold text-center mb-2">{currency}</h4>
                                <ResponsiveContainer width="100%" height="85%">
                                    <BarChart
                                        data={weeklyData}
                                        margin={{ top: 10, right: 10, left: 10, bottom: 20 }}
                                    >
                                        <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                        <XAxis
                                            dataKey="weekNumber"
                                            tick={{ fontSize: 10 }}
                                            tickFormatter={v => `W${v}`}
                                            height={30}
                                        />
                                        <YAxis
                                            tickFormatter={formatYAxisTick}
                                            width={45}
                                            tick={{ fontSize: 10 }}
                                        />
                                        <Tooltip content={<CustomTooltip />} />
                                        <Bar
                                            dataKey={currency}
                                            name={currency}
                                            fill={CURRENCY_COLORS[currency]}
                                            stroke="#ffffff"
                                            strokeWidth={2}
                                            radius={[4, 4, 0, 0]}
                                        />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        );
                    })}
                </div>
            ) : viewMode === 'eurEquivalent' ? (
                // Single chart showing all amounts in EUR
                <div style={{ height: '350px', minHeight: '350px' }}>
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={weeklyData}
                            margin={{ top: 20, right: 30, left: 20, bottom: 70 }}
                        >
                            <CartesianGrid strokeDasharray="3 3" vertical={false} />
                            <XAxis
                                dataKey="week"
                                tick={{ fontSize: 12, fontWeight: 'bold' }}
                                height={80}
                                tickMargin={10}
                                angle={-25}
                                textAnchor="end"
                                // Show fewer ticks when we have a lot of data
                                interval={weeklyData.length > 12 ? Math.ceil(weeklyData.length / 8) : 0}
                            />
                            <YAxis
                                tickFormatter={formatYAxisTick}
                                width={80}
                                label={{
                                    value: 'Amount (EUR)',
                                    angle: -90,
                                    position: 'insideLeft',
                                    style: { textAnchor: 'middle' }
                                }}
                            />
                            <Tooltip content={<CustomTooltip />} />
                            <Legend verticalAlign="top" height={40} />
                            <Bar
                                dataKey="totalEUR"
                                name="Total (EUR)"
                                fill="#2979FF"
                                stroke="#ffffff"
                                strokeWidth={2}
                                radius={[4, 4, 0, 0]}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            ) : (
                // Default view - show all currencies in one chart
                <div style={{ height: '350px', minHeight: '350px' }}>
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={weeklyData}
                            margin={{ top: 20, right: 30, left: 20, bottom: 70 }}
                            barGap={8}
                        >
                            <CartesianGrid strokeDasharray="3 3" vertical={false} />
                            <XAxis
                                dataKey="week"
                                tick={{ fontSize: 12, fontWeight: 'bold' }}
                                height={80}
                                tickMargin={10}
                                angle={-25}
                                textAnchor="end"
                                // Show fewer ticks when we have a lot of data
                                interval={weeklyData.length > 12 ? Math.ceil(weeklyData.length / 8) : 0}
                            />
                            <YAxis
                                tickFormatter={formatYAxisTick}
                                width={80}
                            />
                            <Tooltip content={<CustomTooltip />} />
                            <Legend
                                wrapperStyle={{ paddingTop: '20px' }}
                                verticalAlign="bottom"
                                height={60}
                            />

                            {/* Create bars for each currency */}
                            {currencies.map(currency => (
                                <Bar
                                    key={currency}
                                    dataKey={currency}
                                    name={currency}
                                    fill={CURRENCY_COLORS[currency] || '#999999'}
                                    stroke="#ffffff"
                                    strokeWidth={2}
                                    radius={[4, 4, 0, 0]}
                                />
                            ))}
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            )}

            {/* Summary table for more precise numbers - only show weeks with releases */}
            <div className="mt-6 overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead>
                    <tr>
                        <th className="px-4 py-2 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">
                            Week
                        </th>
                        <th className="px-4 py-2 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">
                            Dates
                        </th>
                        {viewMode === 'eurEquivalent' ? (
                            <th className="px-4 py-2 text-right text-sm font-medium text-gray-500 uppercase tracking-wider">
                                EUR Equivalent
                            </th>
                        ) : (
                            currencies.map(curr => (
                                <th key={curr} className="px-4 py-2 text-right text-sm font-medium text-gray-500 uppercase tracking-wider">
                                    {curr}
                                </th>
                            ))
                        )}
                    </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                    {weeksWithReleases.length > 0 ? (
                        weeksWithReleases.map((item, index) => (
                            <tr key={index} className={index % 2 === 0 ? 'bg-gray-50' : 'bg-white'}>
                                <td className="px-4 py-2 text-sm font-medium text-gray-900">
                                    Week {item.weekNumber}
                                </td>
                                <td className="px-4 py-2 text-sm text-gray-500">
                                    {item.formattedDate}
                                </td>

                                {viewMode === 'eurEquivalent' ? (
                                    <td className="px-4 py-2 text-sm text-right font-medium text-blue-600">
                                        {item.totalEUR.toLocaleString(undefined, {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        })} EUR
                                    </td>
                                ) : (
                                    currencies.map(curr => (
                                        <td key={curr} className="px-4 py-2 text-sm text-right font-medium"
                                            style={{ color: item[curr] > 0 ? CURRENCY_COLORS[curr] : '#999' }}>
                                            {curr === 'JPY'
                                                ? item[curr].toLocaleString(undefined, {
                                                    maximumFractionDigits: 0
                                                })
                                                : item[curr].toLocaleString(undefined, {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2
                                                })
                                            }
                                        </td>
                                    ))
                                )}
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td colSpan={viewMode === 'eurEquivalent' ? 3 : 2 + currencies.length} className="px-4 py-4 text-sm text-gray-500 text-center">
                                No releases scheduled in the upcoming weeks
                            </td>
                        </tr>
                    )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default UpcomingReleasesChart;
