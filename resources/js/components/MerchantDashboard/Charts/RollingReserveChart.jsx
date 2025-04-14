import React from 'react';
import { ResponsiveContainer, BarChart, Bar, CartesianGrid, XAxis, YAxis, Tooltip, Legend, Label, Cell } from 'recharts';

// Format large numbers with K, M abbreviations
const formatYAxisTick = (value) => {
    if (value >= 1000000) {
        return `${(value / 1000000).toLocaleString()} M`;
    } else if (value >= 1000) {
        return `${(value / 1000).toLocaleString()} K`;
    }
    return value.toLocaleString();
};

// Currency-specific colors
const CURRENCY_COLORS = {
    'EUR': '#2979FF', // Bright Blue
    'USD': '#00E676', // Bright Green
    'GBP': '#FFEA00', // Bright Yellow
    'JPY': '#FF3D00'  // Bright Orange/Red
};

const RollingReserveChart = ({ reserveData = {} }) => {
    // Safely access pending_reserves with a default empty object
    const pendingReserves = reserveData?.pending_reserves || {};

    // Check if we have JPY values and if they need scaling
    const hasLargeJPY = pendingReserves.JPY && pendingReserves.JPY > 100000;

    // Create data array and handle very large JPY values
    const data = Object.entries(pendingReserves).map(([currency, amount]) => {
        // Ensure we're working with numbers by parsing the values
        let displayAmount = parseFloat(amount || 0);
        let originalAmount = displayAmount;

        // Scale down JPY if it's at least 10x larger than the next largest currency
        if (currency === 'JPY' && hasLargeJPY) {
            const otherCurrencyMax = Math.max(
                ...Object.entries(pendingReserves)
                    .filter(([curr]) => curr !== 'JPY')
                    .map(([_, value]) => parseFloat(value || 0))
            );

            if (displayAmount > otherCurrencyMax * 10) {
                displayAmount = displayAmount / 100; // Scale down for better visualization
            }
        }

        return {
            currency,
            amount: displayAmount,
            originalAmount
        };
    });

    // Custom tooltip with better information
    const CustomTooltip = ({ active, payload }) => {
        if (active && payload && payload.length) {
            const { currency, amount, originalAmount } = payload[0].payload;
            const isScaled = currency === 'JPY' && amount !== originalAmount;

            return (
                <div className="bg-white p-3 border-2 shadow-md rounded-md">
                    <p className="font-bold text-lg border-b pb-2 mb-2">{currency}</p>
                    <p className="text-lg">
                        <span className="font-medium">
                            {currency === 'JPY' 
                                ? originalAmount.toLocaleString(undefined, { maximumFractionDigits: 0 }) 
                                : originalAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </span> {currency}
                    </p>
                    {isScaled && (
                        <p className="text-xs text-gray-500 mt-1">
                            *Scaled down in chart for better visualization
                        </p>
                    )}
                </div>
            );
        }
        return null;
    };

    return (
        <div className="bg-white shadow rounded-lg border p-4" style={{ height: '400px' }}>
            <h3 className="text-lg font-medium text-gray-900 mb-4">Rolling Reserve Balance by Currency</h3>
            <div className="h-[320px]">
                {data.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={data}
                            margin={{ top: 10, right: 30, left: 40, bottom: 30 }}
                        >
                            <CartesianGrid strokeDasharray="3 3" vertical={false} />
                            <XAxis
                                dataKey="currency"
                                tick={{ fontSize: 14, fontWeight: 'bold' }}
                            />
                            <YAxis
                                tickFormatter={formatYAxisTick}
                                width={60}
                                tick={{ fontSize: 12 }}
                            >
                                <Label
                                    value="Amount"
                                    angle={-90}
                                    position="insideLeft"
                                    style={{ textAnchor: 'middle', fill: '#666' }}
                                />
                            </YAxis>
                            <Tooltip content={<CustomTooltip />} />
                            <Legend wrapperStyle={{ paddingTop: '10px' }} />
                            <Bar
                                dataKey="amount"
                                name="Pending Amount"
                                radius={[6, 6, 0, 0]} // More rounded top corners
                            >
                                {data.map((entry, index) => (
                                    <Cell
                                        key={`cell-${index}`}
                                        fill={CURRENCY_COLORS[entry.currency] || '#8884d8'}
                                    />
                                ))}
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                ) : (
                    <div className="flex justify-center items-center h-full flex-col">
                        <p className="text-gray-500 text-center font-medium text-lg mb-2">
                            No reserve data available
                        </p>
                        <p className="text-gray-400 text-center text-sm">
                            Reserve balances will appear here when data is available
                        </p>
                    </div>
                )}
            </div>
            {hasLargeJPY && (
                <div className="text-xs text-center text-gray-500 mt-2">
                    Note: JPY amount is scaled down for better visualization
                </div>
            )}
        </div>
    );
};

export default RollingReserveChart;
