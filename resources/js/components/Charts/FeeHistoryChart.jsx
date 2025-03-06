import React from 'react';
import { ResponsiveContainer, ComposedChart, Bar, CartesianGrid, XAxis, YAxis, Tooltip, Legend } from 'recharts';

// Colors for fee types and currencies
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

const FeeHistoryChart = ({ feeHistory = [], currencies = [] }) => {
    // Sort the fee history chronologically
    const sortedFeeHistory = [...feeHistory].sort((a, b) => {
        if (!a.fullDate || !b.fullDate) return 0;
        return new Date(a.fullDate) - new Date(b.fullDate);
    });

    // Extract all fee types from the data
    const extractFeeTypes = () => {
        const types = new Set();

        sortedFeeHistory.forEach(month => {
            Object.keys(month).forEach(key => {
                // Skip non-fee fields
                if (key === 'month' || key === 'year' || key === 'fullDate') return;

                // Parse the fee type and currency (format: "FeeType_Currency")
                const parts = key.split('_');
                if (parts.length >= 1) {
                    types.add(parts[0]);
                }
            });
        });

        return Array.from(types);
    };

    const feeTypes = extractFeeTypes();

    // Format the data for display
    const formattedData = sortedFeeHistory.map(month => {
        const result = {
            month: `${month.month} ${month.year}`
        };

        // Add values for each fee type and currency
        Object.entries(month).forEach(([key, value]) => {
            if (key !== 'month' && key !== 'year' && key !== 'fullDate') {
                result[key] = Number(value) || 0;
            }
        });

        return result;
    });

    // Find max value for each axis to set scales
    const getMaxMDRValue = () => {
        let max = 0;
        formattedData.forEach(month => {
            currencies.forEach(currency => {
                const mdrValue = month[`MDR_${currency}`] || 0;
                if (mdrValue > max) max = mdrValue;
            });
        });

        // Round up to next significant number
        if (max <= 1000) return 1000;
        if (max <= 2000) return 2000;
        if (max <= 5000) return 5000;
        return Math.ceil(max / 1000) * 1000;
    };

    const getMaxOtherFeeValue = () => {
        let max = 0;
        formattedData.forEach(month => {
            feeTypes.forEach(type => {
                if (type === 'MDR') return; // Skip MDR fees

                currencies.forEach(currency => {
                    const value = month[`${type}_${currency}`] || 0;
                    if (value > max) max = value;
                });
            });
        });

        // Round up to next significant number
        if (max <= 50) return 50;
        if (max <= 100) return 100;
        if (max <= 200) return 200;
        if (max <= 500) return 500;
        return Math.ceil(max / 100) * 100;
    };

    const maxMDRValue = getMaxMDRValue();
    const maxOtherFeeValue = getMaxOtherFeeValue();

    // Custom tooltip formatter
    const formatTooltip = (value, name) => {
        const parts = name.split('_');
        const feeType = parts[0] || '';
        const currency = parts[1] || '';

        return [
            `${value.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })} ${currency}`,
            `${feeType} (${currency})`
        ];
    };

    // Custom legend formatter
    const formatLegend = (value) => {
        const parts = value.split('_');
        if (parts.length < 2) return value;
        return `${parts[0]} (${parts[1]})`;
    };

    // Custom tick formatter
    const formatAxisTick = (value) => {
        if (value >= 1000) {
            return `${(value / 1000).toFixed(1)}K`;
        }
        return value;
    };

    return (
        <div className="bg-white shadow rounded-lg border p-4" style={{ height: '400px', width: '100%' }}>
            <h3 className="text-lg font-medium text-gray-900 mb-4">Monthly Fee History</h3>
            <div className="h-[320px] w-full">
                {formattedData.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                        <ComposedChart
                            data={formattedData}
                            margin={{ top: 20, right: 65, left: 65, bottom: 30 }}
                            barSize={25}
                            barGap={2}
                            barCategoryGap={8}
                        >
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis
                                dataKey="month"
                                angle={0}
                                textAnchor="middle"
                                height={50}
                                tick={{ fontSize: 12, fontWeight: 'bold' }}
                                interval={0}
                            />
                            {/* Left Y axis for MDR Fee */}
                            <YAxis
                                yAxisId="left"
                                orientation="left"
                                domain={[0, maxMDRValue]}
                                tickFormatter={formatAxisTick}
                                width={60}
                                label={{
                                    value: 'MDR Fee',
                                    angle: -90,
                                    position: 'insideLeft',
                                    offset: -15,
                                    style: { textAnchor: 'middle', fill: '#666' }
                                }}
                                tick={{ fontSize: 11 }}
                            />
                            {/* Right Y axis for other fees */}
                            <YAxis
                                yAxisId="right"
                                orientation="right"
                                domain={[0, maxOtherFeeValue]}
                                tickFormatter={formatAxisTick}
                                width={60}
                                label={{
                                    value: 'Other Fees',
                                    angle: 90,
                                    position: 'insideRight',
                                    offset: -15,
                                    style: { textAnchor: 'middle', fill: '#666' }
                                }}
                                tick={{ fontSize: 11 }}
                            />
                            <Tooltip
                                formatter={formatTooltip}
                                itemSorter={(item) => -item.value}
                                wrapperStyle={{ fontSize: '12px' }}
                            />
                            <Legend
                                verticalAlign="top"
                                height={36}
                                formatter={formatLegend}
                                wrapperStyle={{ fontSize: '12px' }}
                                iconSize={10}
                                iconType="circle"
                            />

                            {/* Render MDR Fee bars for each currency */}
                            {currencies.map(currency => {
                                const dataKey = `MDR_${currency}`;
                                const baseColor = getTypeColor('MDR');
                                const barColor = adjustColor(baseColor, currency);

                                return (
                                    <Bar
                                        key={dataKey}
                                        yAxisId="left"
                                        dataKey={dataKey}
                                        name={dataKey}
                                        fill={barColor}
                                        radius={[4, 4, 0, 0]}
                                    />
                                );
                            })}

                            {/* Render other fee types for each currency */}
                            {feeTypes.filter(type => type !== 'MDR').map(feeType =>
                                currencies.map(currency => {
                                    const dataKey = `${feeType}_${currency}`;
                                    const baseColor = getTypeColor(feeType);
                                    const barColor = adjustColor(baseColor, currency);

                                    return (
                                        <Bar
                                            key={dataKey}
                                            yAxisId="right"
                                            dataKey={dataKey}
                                            name={dataKey}
                                            fill={barColor}
                                            radius={[4, 4, 0, 0]}
                                        />
                                    );
                                })
                            )}
                        </ComposedChart>
                    </ResponsiveContainer>
                ) : (
                    <div className="flex justify-center items-center h-full">
                        <p className="text-gray-500 text-center">
                            No fee history data available
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
};

export default FeeHistoryChart;
