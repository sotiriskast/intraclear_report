import React from 'react';
import { ResponsiveContainer, ComposedChart, Bar, CartesianGrid, XAxis, YAxis, Tooltip, Legend } from 'recharts';

// Color palette with professional, distinguishable colors
const TYPE_COLORS = {
    'MDR': '#3B82F6',         // Soft Blue
    'Transaction': '#10B981', // Soft Green
    'Payout': '#F59E0B',      // Soft Yellow/Orange
    'Refund': '#EF4444',      // Soft Red
    'Declined': '#6366F1',    // Soft Indigo
    'Chargeback': '#06B6D4',  // Soft Cyan
    'Setup': '#8B5CF6',       // Soft Purple
    'Monthly Fee': '#64748B', // Soft Slate
    'Visa High Risk Fee': '#FF6B6B', // Soft Coral
    'Mastercard High Risk Fee': '#4ECDC4' // Soft Teal
};

// Robust color retrieval function with fallback
const getTypeColor = (feeType) => {
    // Normalize the fee type to handle variations
    const normalizedType = feeType
        .replace(/\s+/g, ' ')
        .replace('High Risk', 'High Risk Fee')
        .trim();

    // Check exact match first
    if (TYPE_COLORS[normalizedType]) {
        return TYPE_COLORS[normalizedType];
    }

    // Try partial match
    const matchedKey = Object.keys(TYPE_COLORS).find(key =>
        normalizedType.includes(key)
    );

    // Return matched color or default grey
    return matchedKey
        ? TYPE_COLORS[matchedKey]
        : '#888888';
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

    // Find max value for MDR and other fees
    const calculateAxisScales = () => {
        let maxMDR = 0;
        let maxOtherFees = 0;

        formattedData.forEach(month => {
            // Calculate MDR max
            if ('MDR' in month) {
                maxMDR = Math.max(maxMDR, month.MDR);
            }

            // Calculate other fees max
            const otherFeeTypes = feeTypes.filter(type => type !== 'MDR');
            const otherFeesTotal = otherFeeTypes.reduce((total, type) => {
                return total + (month[type] || 0);
            }, 0);
            maxOtherFees = Math.max(maxOtherFees, otherFeesTotal);
        });

        // Round up to next significant number
        const roundUpToSignificant = (value) => {
            if (value <= 100) return 100;
            if (value <= 250) return 250;
            if (value <= 500) return 500;
            if (value <= 1000) return 1000;
            return Math.ceil(value / 1000) * 1000;
        };

        return {
            maxMDR: roundUpToSignificant(maxMDR),
            maxOtherFees: roundUpToSignificant(maxOtherFees)
        };
    };

    const { maxMDR, maxOtherFees } = calculateAxisScales();

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
                            margin={{ top: 20, right: 65, left: 65, bottom: 80 }}
                            barSize={18}
                            barGap={6}
                            barCategoryGap={16}
                        >
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis
                                dataKey="month"
                                angle={-45}
                                textAnchor="end"
                                height={60}
                                tick={{ fontSize: 13, fontWeight: 'bold' }}
                                tickMargin={10}
                                // Display a more reasonable number of ticks when we have a lot of data
                                interval={formattedData.length > 12 ? Math.floor(formattedData.length / 6) : 0}
                            />
                            {/* Left Y axis for MDR Fee */}
                            <YAxis
                                yAxisId="left"
                                orientation="left"
                                domain={[0, maxMDR]}
                                tickFormatter={(value) =>
                                    value >= 1000 ? `${(value / 1000).toFixed(1)}K` : value
                                }
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
                                domain={[0, maxOtherFees]}
                                tickFormatter={(value) =>
                                    value >= 1000 ? `${(value / 1000).toFixed(1)}K` : value
                                }
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
                                verticalAlign="bottom"
                                height={60}
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
