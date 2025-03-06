import React, { useState } from 'react';
import { ResponsiveContainer, PieChart, Pie, Tooltip, Cell } from 'recharts';

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884d8', '#82ca9d'];

const renderActiveShape = (props) => {
    const { cx, cy, midAngle, innerRadius, outerRadius, fill, payload, percent, value } = props;
    const sin = Math.sin(-midAngle * Math.PI / 180);
    const cos = Math.cos(-midAngle * Math.PI / 180);
    const sx = cx + (outerRadius + 10) * cos;
    const sy = cy + (outerRadius + 10) * sin;
    const mx = cx + (outerRadius + 30) * cos;
    const my = cy + (outerRadius + 30) * sin;
    const ex = mx + (cos >= 0 ? 1 : -1) * 22;
    const ey = my;
    const textAnchor = cos >= 0 ? 'start' : 'end';

    return (
        <g>
            <text x={cx} y={cy} dy={8} textAnchor="middle" fill={fill}>
                {payload.name}
            </text>
            <path d={`M${sx},${sy}L${mx},${my}L${ex},${ey}`} stroke={fill} fill="none" />
            <circle cx={ex} cy={ey} r={2} fill={fill} stroke="none" />
            <text x={ex + (cos >= 0 ? 1 : -1) * 12} y={ey} textAnchor={textAnchor} fill="#333">
                {`${value.toFixed(2)} €`}
            </text>
            <text x={ex + (cos >= 0 ? 1 : -1) * 12} y={ey} dy={18} textAnchor={textAnchor} fill="#999">
                {`(${(percent * 100).toFixed(2)}%)`}
            </text>
        </g>
    );
};

const ReserveDistributionPie = ({ statistics = {} }) => {
    const [activeIndex, setActiveIndex] = useState(0);

    // Safely access statistics properties with default values
    const pendingCount = statistics?.pending_count || 0;
    const releasedCount = statistics?.released_count || 0;

    const data = [
        { name: 'Pending', value: pendingCount },
        { name: 'Released', value: releasedCount }
    ];

    return (
        <div className="bg-white shadow rounded-lg border p-4" style={{ height: '400px' }}>
            <h3 className="text-lg font-medium text-gray-900 mb-4">Reserve Status Distribution</h3>
            <div className="h-[320px]">
                {(pendingCount > 0 || releasedCount > 0) ? (
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie
                                activeIndex={activeIndex}
                                activeShape={renderActiveShape}
                                data={data}
                                cx="50%"
                                cy="50%"
                                innerRadius={70}
                                outerRadius={90}
                                fill="#8884d8"
                                dataKey="value"
                                onMouseEnter={(_, index) => setActiveIndex(index)}
                            >
                                {data.map((entry, index) => (
                                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                ))}
                            </Pie>
                            <Tooltip formatter={(value) => [`${value}`, 'Count']} />
                        </PieChart>
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

export default ReserveDistributionPie;
