import React from 'react';
import { ResponsiveContainer, LineChart, Line, CartesianGrid, XAxis, YAxis, Tooltip, Legend } from 'recharts';

// Currency colors
const CURRENCY_COLORS = {
    'EUR': '#0088FE',
    'USD': '#00C49F',
    'GBP': '#FFBB28',
    'JPY': '#FF8042'
};

// Format large numbers with K, M abbreviations
const formatYAxisTick = (value) => {
    if (value >= 1000000) {
        return `${(value / 1000000).toFixed(1)}M`;
    } else if (value >= 1000) {
        return `${(value / 1000).toFixed(1)}K`;
    }
    return value.toLocaleString();
};

const UpcomingReleasesChart = ({ upcomingReleases = [], currencies = [] }) => {
    // Sort upcoming releases chronologically
    const sortedReleases = [...upcomingReleases].sort((a, b) => {
        return new Date(a.fullDate) - new Date(b.fullDate);
    });

    // Format data for display
    const formattedData = sortedReleases.map(release => ({
        month: `${release.month} ${release.year}`,
        ...currencies.reduce((acc, curr) => {
            acc[curr] = release[curr] || 0;
            return acc;
        }, {})
    }));

    // Custom tooltip formatter
    const formatTooltip = (value, name) => {
        return [
            `${value.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })}`,
            name
        ];
    };

    return (
        <div className="bg-white shadow rounded-lg border p-4" style={{ height: '480px', minHeight: '480px' }}>
            <h3 className="text-lg font-medium text-gray-900 mb-4">
                Upcoming Reserve Releases (6 Month Projection)
            </h3>
            <div className="h-[400px]">
                {formattedData.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart
                            data={formattedData}
                            margin={{ top: 20, right: 30, left: 60, bottom: 60 }}
                        >
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis
                                dataKey="month"
                                interval="preserveStartEnd"
                                angle={-30}
                                textAnchor="end"
                                height={60}
                                dy={20}
                                tick={{ fontSize: 12 }}
                            />
                            <YAxis
                                label={{
                                    value: 'Amount',
                                    angle: -90,
                                    position: 'insideLeft',
                                    offset: -40
                                }}
                                tickFormatter={formatYAxisTick}
                                width={60}
                                tick={{ fontSize: 12 }}
                            />
                            <Tooltip
                                formatter={formatTooltip}
                                labelFormatter={(label) => `Release Month: ${label}`}
                            />
                            <Legend verticalAlign="top" height={36} />

                            {/* Render a line for each currency */}
                            {currencies.map((currency, index) => (
                                <Line
                                    key={currency}
                                    type="monotone"
                                    dataKey={currency}
                                    name={currency}
                                    stroke={CURRENCY_COLORS[currency] || `#${Math.floor(Math.random()*16777215).toString(16)}`}
                                    strokeWidth={3}
                                    dot={{ r: 6 }}
                                    activeDot={{ r: 8 }}
                                />
                            ))}
                        </LineChart>
                    </ResponsiveContainer>
                ) : (
                    <div className="flex justify-center items-center h-full">
                        <p className="text-gray-500 text-center">
                            No upcoming reserve releases found
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
};

export default UpcomingReleasesChart;
