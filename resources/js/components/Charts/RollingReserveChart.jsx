import React from 'react';
import { ResponsiveContainer, BarChart, Bar, CartesianGrid, XAxis, YAxis, Tooltip, Legend } from 'recharts';

// Format large numbers with K, M abbreviations
const formatYAxisTick = (value) => {
    if (value >= 1000000) {
        return `${(value / 1000000).toLocaleString()} M`;
    } else if (value >= 1000) {
        return `${(value / 1000).toLocaleString()} K`;
    }
    return value.toLocaleString();
};

const RollingReserveChart = ({ reserveData = {} }) => {
    // Safely access pending_reserves with a default empty object
    const pendingReserves = reserveData?.pending_reserves || {};

    const data = Object.entries(pendingReserves).map(([currency, amount]) => ({
        currency,
        amount: Number(amount || 0)
    }));

    return (
        <div className="bg-white shadow rounded-lg border p-4" style={{ height: '400px' }}>
            <h3 className="text-lg font-medium text-gray-900 mb-4">Rolling Reserve Balance by Currency</h3>
            <div className="h-[320px]">
                {data.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={data}
                            margin={{ top: 10, right: 30, left: 50, bottom: 30 }}
                        >
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis
                                dataKey="currency"
                                tick={{ fontSize: 14, fontWeight: 'bold' }}
                            />
                            <YAxis
                                tickFormatter={formatYAxisTick}
                                width={60}
                                tick={{ fontSize: 12 }}
                            />
                            <Tooltip
                                formatter={(value) => [`${value.toLocaleString()}`, 'Amount']}
                                labelFormatter={(label) => `Currency: ${label}`}
                            />
                            <Legend wrapperStyle={{ paddingTop: '10px' }} />
                            <Bar
                                dataKey="amount"
                                name="Pending Amount"
                                fill="#8884d8"
                                radius={[4, 4, 0, 0]} // Slightly rounded top corners
                            />
                        </BarChart>
                    </ResponsiveContainer>
                ) : (
                    <div className="flex justify-center items-center h-full">
                        <p className="text-gray-500 text-center">
                            No reserve data available
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
};

export default RollingReserveChart;
