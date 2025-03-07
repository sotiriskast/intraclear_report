import React from 'react';
import { ResponsiveContainer, ComposedChart, Bar, CartesianGrid, XAxis, YAxis, Tooltip, Legend } from 'recharts';

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

const FeeHistoryChart = ({ feeHistory = [] }) => {
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

                // Extract only the fee type from the key
                const feeType = key.split('_')[0];
                if (feeType) {
                    types.add(feeType);
                }
            });
        });

        return Array.from(types);
    };

    const feeTypes = extractFeeTypes();

    // Format the data for display - using only fee_amount_eur
    const formattedData = sortedFeeHistory.map(month => {
        const result = {
            month: `${month.month} ${month.year}`
        };

        // Add values for each fee type
        feeTypes.forEach(feeType => {
            const key = `${feeType}_EUR`;
            if (key in month) {
                result[feeType] = Number(month[key]) || 0;
            } else {
                result[feeType] = 0;
            }
        });

        return result;
    });

    // Find max value for each axis to set scales
    const getMaxMDRValue = () => {
        let max = 0;
        formattedData.forEach(month => {
            if ('MDR' in month && month.MDR > max) {
                max = month.MDR;
            }
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
                if (type !== 'MDR' && type in month && month[type] > max) {
                    max = month[type];
                }
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
        return [
            `${value.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })} EUR`,
            name
        ];
    };

    // Custom tick formatter
    const formatAxisTick = (value) => {
        if (value >= 1000) {
            return `${(value / 1000).toFixed(1)}K`;
        }
        return value;
    };

    // Custom tooltip component
    const CustomTooltip = ({ active, payload, label }) => {
        if (active && payload && payload.length) {
            return (
                <div className="bg-white p-3 border-2 shadow-md rounded-md">
                    <p className="font-bold text-lg border-b pb-2 mb-2">{label}</p>
                    <div className="space-y-2">
                        {payload.map((entry, index) => {
                            if (entry.value === 0) return null;

                            return (
                                <div key={`item-${index}`} className="flex items-center">
                                    <div
                                        className="w-3 h-3 rounded-full mr-2"
                                        style={{ backgroundColor: entry.color }}
                                    />
                                    <span className="mr-2 font-medium">{entry.name}:</span>
                                    <span className="ml-auto font-bold">
                                        {entry.value.toLocaleString(undefined, {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        })} EUR
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            );
        }
        return null;
    };

    return (
        <div className="bg-white shadow rounded-lg border p-4" style={{ height: '450px', width: '100%' }}>
            <div className="flex justify-between items-center mb-6">
                <h3 className="text-xl font-bold text-gray-800">Monthly Fee History (EUR)</h3>
            </div>
            <div className="h-[370px] w-full">
                {formattedData.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                        <ComposedChart
                            data={formattedData}
                            margin={{ top: 20, right: 65, left: 65, bottom: 40 }}
                            barSize={18}
                            barGap={6}
                            barCategoryGap={16}
                        >
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis
                                dataKey="month"
                                angle={-15}
                                textAnchor="end"
                                height={60}
                                tick={{ fontSize: 13, fontWeight: 'bold' }}
                                tickMargin={10}
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
                                    style: { textAnchor: 'middle', fill: '#666', fontWeight: 'bold' }
                                }}
                                tick={{ fontSize: 12 }}
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
                                    style: { textAnchor: 'middle', fill: '#666', fontWeight: 'bold' }
                                }}
                                tick={{ fontSize: 12 }}
                            />
                            <Tooltip
                                content={<CustomTooltip />}
                                wrapperStyle={{ zIndex: 1000 }}
                            />
                            <Legend
                                verticalAlign="top"
                                height={40}
                                wrapperStyle={{ fontSize: '13px', paddingTop: '10px' }}
                                iconSize={12}
                                iconType="circle"
                            />

                            {/* Render MDR Fee bar */}
                            {feeTypes.includes('MDR') && (
                                <Bar
                                    yAxisId="left"
                                    dataKey="MDR"
                                    name="MDR"
                                    fill={getTypeColor('MDR')}
                                    radius={[8, 8, 0, 0]}
                                    strokeWidth={3}
                                    stroke="#ffffff"
                                />
                            )}

                            {/* Render other fee types */}
                            {feeTypes.filter(type => type !== 'MDR').map(feeType => (
                                <Bar
                                    key={feeType}
                                    yAxisId="right"
                                    dataKey={feeType}
                                    name={feeType}
                                    fill={getTypeColor(feeType)}
                                    radius={[6, 6, 0, 0]}
                                    strokeWidth={2}
                                    stroke="#ffffff"
                                />
                            ))}
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
