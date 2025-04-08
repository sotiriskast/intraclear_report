import React, { useState, useEffect } from 'react';
import RollingReserveChart from './Charts/RollingReserveChart.jsx';
import ReserveDistributionPie from './Charts/ReserveDistributionPie.jsx';
import UpcomingReleasesChart from './Charts/UpcomingReleasesChart.jsx';
import FeeHistoryChart from './Charts/FeeHistoryChart.jsx';
import FeeDistributionChart from './Charts/FeeDistributionChart.jsx';

const DashboardTabs = ({ reserveData = {}, feeHistory = [], upcomingReleases = [], currencies = [] }) => {
    const [activeTab, setActiveTab] = useState('reserves');

    // Ensure proper structure of reserveData
    const safeReserveData = {
        pending_reserves: reserveData?.pending_reserves || {},
        statistics: reserveData?.statistics || { pending_count: 0, released_count: 0 }
    };

    // Debugging to help identify data issues
    useEffect(() => {
        console.log('DashboardTabs - Reserve Data:', reserveData);
        console.log('DashboardTabs - Safe Reserve Data:', safeReserveData);
    }, [reserveData]);

    // Map tabs to more readable text
    const tabLabels = {
        reserves: 'Rolling Reserves',
        upcoming: 'Upcoming Releases',
        fees: 'Fee Analysis'
    };

    return (
        <div className="bg-white shadow rounded-lg overflow-hidden border">
            <div className="border-b">
                <nav className="flex" aria-label="Tabs">
                    {Object.keys(tabLabels).map((tab) => (
                        <button
                            key={tab}
                            onClick={() => setActiveTab(tab)}
                            className={`w-1/3 py-4 px-4 text-center border-b-2 font-medium text-sm transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 ${
                                activeTab === tab
                                    ? 'border-blue-500 text-blue-600 bg-blue-50'
                                    : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:bg-zinc-50 hover:border-zinc-300'
                            }`}
                            aria-selected={activeTab === tab}
                        >
                            {tabLabels[tab]}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Fixed height content container with consistent padding */}
            <div className="p-6 min-h-[800px]">
                {activeTab === 'reserves' && (
                    <div className="grid gap-8 md:grid-cols-2 h-full">
                        <RollingReserveChart reserveData={safeReserveData} />
                        <ReserveDistributionPie statistics={safeReserveData.statistics} />
                    </div>
                )}

                {activeTab === 'upcoming' && (
                    <div className="h-full">
                        <UpcomingReleasesChart
                            upcomingReleases={upcomingReleases}
                            currencies={currencies}
                        />
                    </div>
                )}

                {activeTab === 'fees' && (
                    <div className="flex flex-col space-y-8">
                        {/* Fee Distribution Chart - Full width */}
                        <div className="w-full bg-zinc-50 p-4 rounded-lg shadow-sm border border-zinc-200">
                            <FeeDistributionChart
                                feeHistory={feeHistory}
                            />
                        </div>

                        {/* Fee History Chart - Full width */}
                        <div className="w-full bg-zinc-50 p-4 rounded-lg shadow-sm border border-zinc-200">
                            <FeeHistoryChart
                                feeHistory={feeHistory}
                            />
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default DashboardTabs;
