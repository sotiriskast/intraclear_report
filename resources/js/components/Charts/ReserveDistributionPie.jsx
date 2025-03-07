import React, { useState } from 'react';
import { ResponsiveContainer, PieChart, Pie, Tooltip, Cell, Legend, Label } from 'recharts';

// More vibrant, distinct colors for better visibility
const COLORS = ['#0088FE', '#00C49F'];

const ReserveDistributionPie = ({ statistics = {} }) => {
    const [activeIndex, setActiveIndex] = useState(0);

    // Safely access statistics properties with default values
    const pendingCount = statistics?.pending_count || 0;
    const releasedCount = statistics?.released_count || 0;

    const data = [
        { name: 'Pending', value: pendingCount },
        { name: 'Released', value: releasedCount }
    ];

    // Custom tooltip for better readability
    const CustomTooltip = ({ active, payload }) => {
        if (active && payload && payload.length) {
            const { name, value } = payload[0].payload;
            const total = pendingCount + releasedCount;
            const percentage = total > 0 ? ((value / total) * 100).toFixed(2) : '0.00';

            return (
                <div className="bg-white p-3 border-2 shadow-md rounded-md">
                    <p className="font-bold text-lg">{name} Reserves</p>
                    <p className="text-lg">
                        <span className="font-medium">{value}</span> entries
                    </p>
                    <p className="text-sm text-gray-600">
                        {percentage}% of total reserves
                    </p>
                </div>
            );
        }
        return null;
    };

    // Custom label to show percentage
    const renderCustomizedLabel = ({ cx, cy, midAngle, innerRadius, outerRadius, percent, index, name }) => {
        const RADIAN = Math.PI / 180;
        const radius = innerRadius + (outerRadius - innerRadius) * 0.5;
        const x = cx + radius * Math.cos(-midAngle * RADIAN);
        const y = cy + radius * Math.sin(-midAngle * RADIAN);

        return (
            <text
                x={x}
                y={y}
                fill="white"
                textAnchor="middle"
                dominantBaseline="central"
                fontWeight="bold"
                fontSize="14"
            >
                {`${(percent * 100).toFixed(0)}% ${name}`}
            </text>
        );
    };

    return (
        <div className="bg-white shadow rounded-lg border p-4" style={{ height: '400px' }}>
            <h3 className="text-lg font-medium text-gray-900 mb-4">Reserve Status Distribution</h3>
            <div className="h-[320px]">
                {(pendingCount > 0 || releasedCount > 0) ? (
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie
                                data={data}
                                cx="50%"
                                cy="50%"
                                innerRadius={70}
                                outerRadius={130}
                                fill="#8884d8"
                                paddingAngle={2}
                                dataKey="value"
                                labelLine={false}
                                label={renderCustomizedLabel}
                                onMouseEnter={(_, index) => setActiveIndex(index)}
                            >
                                {data.map((entry, index) => (
                                    <Cell
                                        key={`cell-${index}`}
                                        fill={COLORS[index % COLORS.length]}
                                        stroke="#fff"
                                        strokeWidth={2}
                                    />
                                ))}
                                {pendingCount === 0 && releasedCount === 0 && (
                                    <Label
                                        value="No Data Available"
                                        position="center"
                                        fill="#666"
                                        fontSize={16}
                                    />
                                )}
                            </Pie>
                            <Tooltip content={<CustomTooltip />} />
                            <Legend
                                verticalAlign="bottom"
                                align="center"
                                layout="horizontal"
                                iconSize={12}
                                iconType="circle"
                                wrapperStyle={{ paddingTop: 20 }}
                            />
                        </PieChart>
                    </ResponsiveContainer>
                ) : (
                    <div className="flex justify-center items-center h-full flex-col">
                        <p className="text-gray-500 text-center font-medium text-lg mb-2">
                            No reserve data available
                        </p>
                        <p className="text-gray-400 text-center text-sm">
                            Reserve statistics will appear here when data is available
                        </p>
                    </div>
                )}
            </div>
            {pendingCount > 0 && releasedCount === 0 && (
                <div className="text-xs text-center text-gray-500 mt-2">
                    Note: 100% of reserves ({pendingCount} entries) are in Pending status
                </div>
            )}
        </div>
    );
};

export default ReserveDistributionPie;
