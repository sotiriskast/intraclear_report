import React, { useState } from 'react';
import { ResponsiveContainer, BarChart, Bar, CartesianGrid, XAxis, YAxis, Tooltip, Cell, Legend } from 'recharts';

// Colors for fee types
const TYPE_COLORS = {
    'MDR': '#0088FE',
    'Transaction': '#00C49F',
    'Payout': '#FFBB28',
    'Refund': '#FF8042',
    'Declined': '#8884d8',
    'Chargeback': '#82ca9d',
    'Setup': '#d884a8'
};

// Currency-specific color tints
const CURRENCY_TINT = {
    'EUR': 1.0,  // 100% - original color
    'USD': 0.85, // 85% - slightly darker
    'GBP': 0.7,  // 70% - darker
    'JPY': 0.55  // 55% - darkest
};

// Function to adjust color based on currency - with safety checks
const adjustColor = (baseColor, currency) => {
    if (!baseColor || typeof baseColor !== 'string' || !baseColor.startsWith('#') || baseColor.length < 7) {
        // Return a fallback color if baseColor is invalid
        return '#888888';
    }

    try {
        // Parse the hex color
        const r = parseInt(baseColor.slice(1, 3), 16);
        const g = parseInt(baseColor.slice(3, 5), 16);
        const b = parseInt(baseColor.slice(5, 7), 16);

        // Get the tint factor for the currency with fallback
        const tint = (currency && CURRENCY_TINT[currency]) ? CURRENCY_TINT[currency] : 1.0;

        // Apply the tint
        const newR = Math.round(r * tint);
        const newG = Math.round(g * tint);
        const newB = Math.round(b * tint);

        // Convert back to hex with padding
        return `#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`;
    } catch (error) {
        // Return a fallback color if any error occurs
        console.error('Error adjusting color:', error);
        return '#888888';
    }
};

// Get color for a fee type with safety check
const getTypeColor = (feeType) => {
    return TYPE_COLORS[feeType] || '#888888';
};

const FeeDistributionChart = ({ feeHistory = [], currencies = [] }) => {
    // Group by currency or by fee type
    const [groupByCurrency, setGroupByCurrency] = useState(false);

    // Calculate fee distribution across fee types and currencies
    const calculateDistribution = () => {
        // First, calculate totals by fee type and currency
        const distributionByCurrencyAndType = {};
        let grandTotal = 0;

        feeHistory.forEach(month => {
            if (!month) return;

            Object.entries(month).forEach(([key, value]) => {
                if (key === 'month' || key === 'year' || key === 'fullDate') return;

                // Parse the fee type and currency (format: "FeeType_Currency")
                const parts = key.split('_');
                if (parts.length < 2) return;

                const feeType = parts[0];
                const currency = parts[1];

                if (!feeType || !currency) return;

                // Initialize if needed
                if (!distributionByCurrencyAndType[currency]) {
                    distributionByCurrencyAndType[currency] = {};
                }

                if (!distributionByCurrencyAndType[currency][feeType]) {
                    distributionByCurrencyAndType[currency][feeType] = 0;
                }

                // Add the value
                const numValue = Number(value);
                if (!isNaN(numValue) && numValue > 0) {
                    distributionByCurrencyAndType[currency][feeType] += numValue;
                    grandTotal += numValue;
                }
            });
        });

        // Format based on grouping preference
        if (groupByCurrency) {
            // Group by currency first, then by fee type
            return Object.entries(distributionByCurrencyAndType).map(([currency, types]) => {
                const totalForCurrency = Object.values(types).reduce((sum, value) => sum + value, 0);

                return {
                    name: currency,
                    value: totalForCurrency,
                    percentage: (totalForCurrency / grandTotal) * 100,
                    types: Object.entries(types).map(([type, value]) => ({
                        type,
                        value,
                        percentage: (value / totalForCurrency) * 100
                    })).sort((a, b) => b.value - a.value)
                };
            }).sort((a, b) => b.value - a.value);
        } else {
            // Group by fee type first, then by currency
            const byType = {};

            // Convert the data structure
            Object.entries(distributionByCurrencyAndType).forEach(([currency, types]) => {
                Object.entries(types).forEach(([type, value]) => {
                    if (!byType[type]) {
                        byType[type] = {
                            total: 0,
                            byCurrency: {}
                        };
                    }

                    byType[type].total += value;
                    byType[type].byCurrency[currency] = value;
                });
            });

            // Format for the chart
            return Object.entries(byType).map(([type, data]) => ({
                name: type,
                value: data.total,
                percentage: (data.total / grandTotal) * 100,
                currencies: Object.entries(data.byCurrency).map(([currency, value]) => ({
                    currency,
                    value,
                    percentage: (value / data.total) * 100
                })).sort((a, b) => b.value - a.value)
            })).sort((a, b) => b.value - a.value);
        }
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

    // Get color for a bar
    const getBarColor = (item) => {
        if (groupByCurrency) {
            // For currency grouping, use the currency colors
            return adjustColor('#0088FE', item.name);
        } else {
            // For fee type grouping, use the fee type colors
            return getTypeColor(item.name);
        }
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
                    x={x + width + 10}
                    y={y + height / 2}
                    fill="#333"
                    textAnchor="start"
                    dominantBaseline="middle"
                    fontSize={12}
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

    // Custom tooltip component for detailed breakdown
    const CustomTooltip = ({ active, payload, label }) => {
        if (active && payload && payload.length && payload[0].payload) {
            const dataItem = data.find(item => item.name === payload[0].payload.name);

            if (!dataItem) return null;

            return (
                <div className="bg-white p-3 border shadow-md rounded-md">
                    <p className="font-bold mb-2">{payload[0].payload.name}</p>
                    <p className="text-sm mb-1">
                        {`Total: ${formatCurrency(dataItem.value)} EUR (${dataItem.percentage.toFixed(2)}%)`}
                    </p>
                    <div className="mt-2 border-t pt-1">
                        <p className="text-xs font-medium mb-1">Breakdown:</p>
                        <div className="space-y-1">
                            {groupByCurrency && dataItem.types
                                ? dataItem.types.map((type, idx) => (
                                    <div key={idx} className="flex justify-between text-xs">
                                        <span className="flex items-center">
                                            <span
                                                className="w-2 h-2 inline-block mr-1 rounded-full"
                                                style={{ backgroundColor: getTypeColor(type.type) }}
                                            ></span>
                                            {type.type}:
                                        </span>
                                        <span>{`${formatCurrency(type.value)} EUR (${type.percentage.toFixed(1)}%)`}</span>
                                    </div>
                                ))
                                : dataItem.currencies && dataItem.currencies.map((curr, idx) => (
                                <div key={idx} className="flex justify-between text-xs">
                                        <span className="flex items-center">
                                            <span
                                                className="w-2 h-2 inline-block mr-1 rounded-full"
                                                style={{ backgroundColor: adjustColor(getTypeColor(dataItem.name), curr.currency) }}
                                            ></span>
                                            {curr.currency}:
                                        </span>
                                    <span>{`${formatCurrency(curr.value)} EUR (${curr.percentage.toFixed(1)}%)`}</span>
                                </div>
                            ))
                            }
                        </div>
                    </div>
                </div>
            );
        }

        return null;
    };

    return (
        <div className="bg-white shadow rounded-lg border p-4" style={{ height: '400px', width: '100%' }}>
            <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-medium text-gray-900">Fee Type Distribution</h3>
                <div className="flex space-x-2">
                    <button
                        onClick={() => setGroupByCurrency(false)}
                        className={`px-3 py-1 text-xs rounded-md ${
                            !groupByCurrency
                                ? 'bg-blue-500 text-white'
                                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                        }`}
                    >
                        By Fee Type
                    </button>
                    <button
                        onClick={() => setGroupByCurrency(true)}
                        className={`px-3 py-1 text-xs rounded-md ${
                            groupByCurrency
                                ? 'bg-blue-500 text-white'
                                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                        }`}
                    >
                        By Currency
                    </button>
                </div>
            </div>
            <div className="h-[300px] w-full">
                {data.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={data}
                            layout="vertical"
                            margin={{ top: 20, right: 170, left: 80, bottom: 20 }}
                            barSize={30}
                        >
                            <CartesianGrid strokeDasharray="3 3" horizontal={true} vertical={false} />
                            <XAxis
                                type="number"
                                domain={getXAxisDomain()}
                                tickFormatter={formatXAxisTick}
                                tick={{ fontSize: 11 }}
                            />
                            <YAxis
                                type="category"
                                dataKey="name"
                                width={80}
                                tick={{ fontSize: 12, fontWeight: 'bold' }}
                            />
                            <Tooltip content={<CustomTooltip />} />
                            <Legend
                                verticalAlign="top"
                                height={0}
                                formatter={(value) => value}
                                wrapperStyle={{ fontSize: '12px', display: 'none' }}
                            />
                            <Bar
                                dataKey="value"
                                radius={[0, 4, 4, 0]}
                                label={renderCustomBarLabel}
                                isAnimationActive={false}
                            >
                                {data.map((entry, index) => (
                                    <Cell
                                        key={`cell-${index}`}
                                        fill={getBarColor(entry)}
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
