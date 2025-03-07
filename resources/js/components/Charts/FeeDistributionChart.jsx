import React from 'react';
import { ResponsiveContainer, BarChart, Bar, CartesianGrid, XAxis, YAxis, Tooltip, Cell, Legend } from 'recharts';

// Colors for fee types - extremely bright, high contrast colors
const TYPE_COLORS = {
    'MDR': '#2979FF',         // Bright Blue
    'Transaction': '#00E676', // Bright Green
    'Payout': '#FFEA00',      // Bright Yellow
    'Refund': '#FF3D00',      // Bright Orange/Red
    'Declined': '#D500F9',    // Bright Purple
    'Chargeback': '#00E5FF',  // Bright Cyan
    'Setup': '#FF9100'        // Bright Orange
};

// Get color for a fee type with safety check
const getTypeColor = (feeType) => {
    return TYPE_COLORS[feeType] || '#888888';
};

const FeeDistributionChart = ({ feeHistory = [] }) => {
    // Calculate fee distribution using only fee_amount_eur values
    const calculateDistribution = () => {
        const feesByType = {};
        let grandTotal = 0;

        feeHistory.forEach(month => {
            if (!month) return;

            Object.entries(month).forEach(([key, value]) => {
                if (key === 'month' || key === 'year' || key === 'fullDate') return;

                // Parse the fee type (format: "FeeType_EUR")
                const parts = key.split('_');
                if (parts.length < 2 || parts[1] !== 'EUR') return;

                const feeType = parts[0];
                if (!feeType) return;

                // Initialize if needed
                if (!feesByType[feeType]) {
                    feesByType[feeType] = 0;
                }

                // Add the value
                const numValue = Number(value);
                if (!isNaN(numValue) && numValue > 0) {
                    feesByType[feeType] += numValue;
                    grandTotal += numValue;
                }
            });
        });

        // Format for the chart
        return Object.entries(feesByType).map(([type, value]) => ({
            name: type,
            value: value,
            percentage: (value / grandTotal) * 100
        })).sort((a, b) => b.value - a.value);
    };

    const data = calculateDistribution();

    // Format for currency display
    const formatCurrency = (value) => {
        if (value >= 1000000) {
            return `${(value / 1000000).toFixed(1)}M`;
        } else if (value >= 1000) {
            return `${(value / 1000).toFixed(1)}K`;
        }
        return value.toFixed(0);
    };

    // Custom label for bars
    const renderCustomBarLabel = (props) => {
        const { x, y, width, value, name, height } = props;

        // Find corresponding data entry
        const dataItem = data.find(item => item.name === name && Math.abs(item.value - value) < 0.01);
        if (!dataItem) return null;

        const percentage = dataItem.percentage.toFixed(1);

        return (
            <g>
                <text
                    x={x + width + 15}
                    y={y + height / 2}
                    fill="#333"
                    textAnchor="start"
                    dominantBaseline="middle"
                    fontSize={13}
                    fontWeight="bold"
                >
                    {`${formatCurrency(value)} EUR (${percentage}%)`}
                </text>
            </g>
        );
    };

    // Calculate the appropriate domain for the x-axis
    const getXAxisDomain = () => {
        if (data.length === 0) return [0, 10];

        const maxValue = Math.max(...data.map(item => item.value));
        // Round up to a clean number
        if (maxValue <= 1000) return [0, 1000];
        if (maxValue <= 2000) return [0, 2000];
        if (maxValue <= 5000) return [0, 5000];
        return [0, Math.ceil(maxValue / 1000) * 1000];
    };

    // Format X-axis ticks
    const formatXAxisTick = (value) => {
        if (value >= 1000) {
            return `${value / 1000}K`;
        }
        return value;
    };

    // Custom tooltip component
    const CustomTooltip = ({ active, payload, label }) => {
        if (active && payload && payload.length && payload[0].payload) {
            const dataItem = data.find(item => item.name === payload[0].payload.name);

            if (!dataItem) return null;

            const bgColor = getTypeColor(dataItem.name);

            return (
                <div className="border-2 shadow-md rounded-md overflow-hidden">
                    <div style={{ backgroundColor: bgColor, padding: '8px 12px', color: 'white' }}>
                        <p className="font-bold text-lg">{dataItem.name}</p>
                    </div>
                    <div className="bg-white p-3">
                        <p className="text-sm mb-1 font-bold">
                            {`${formatCurrency(dataItem.value)} EUR`}
                        </p>
                        <p className="text-sm text-gray-600">
                            {`${dataItem.percentage.toFixed(2)}% of total fees`}
                        </p>
                    </div>
                </div>
            );
        }

        return null;
    };

    return (
        <div className="bg-white shadow rounded-lg border p-4" style={{ height: '400px', width: '100%' }}>
            <div className="flex justify-between items-center mb-6">
                <h3 className="text-xl font-bold text-gray-800">Fee Type Distribution (EUR)</h3>
            </div>
            <div className="h-[330px] w-full">
                {data.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={data}
                            layout="vertical"
                            margin={{ top: 20, right: 190, left: 100, bottom: 20 }}
                            barSize={26}
                            barGap={12}
                        >
                            <CartesianGrid strokeDasharray="3 3" horizontal={true} vertical={false} />
                            <XAxis
                                type="number"
                                domain={getXAxisDomain()}
                                tickFormatter={formatXAxisTick}
                                tick={{ fontSize: 12 }}
                            />
                            <YAxis
                                type="category"
                                dataKey="name"
                                width={100}
                                tick={{ fontSize: 13, fontWeight: 'bold' }}
                                tickMargin={10}
                            />
                            <Tooltip content={<CustomTooltip />} />
                            <Legend
                                verticalAlign="top"
                                height={36}
                                formatter={(value) => value}
                                wrapperStyle={{ fontSize: '12px' }}
                            />
                            <Bar
                                dataKey="value"
                                radius={[0, 8, 8, 0]}
                                label={renderCustomBarLabel}
                                isAnimationActive={false}
                                animationDuration={1000}
                            >
                                {data.map((entry, index) => (
                                    <Cell
                                        key={`cell-${index}`}
                                        fill={getTypeColor(entry.name)}
                                        stroke="#ffffff"
                                        strokeWidth={3}
                                    />
                                ))}
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                ) : (
                    <div className="flex justify-center items-center h-full">
                        <p className="text-gray-500 text-center">
                            No fee distribution data available
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
};

export default FeeDistributionChart;
