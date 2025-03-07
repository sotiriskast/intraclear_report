import React from 'react';

const SummaryCards = ({ upcomingReleases = [], reserveData = {}, feeHistory = [], currencies = [] }) => {
    // Get the next releases for each currency
    const getNextReleasesByCurrency = () => {
        const result = {};

        if (upcomingReleases.length === 0) return result;

        // Get the earliest release date
        const sortedReleases = [...upcomingReleases].sort(
            (a, b) => new Date(a.fullDate) - new Date(b.fullDate)
        );

        const nextRelease = sortedReleases[0];

        // For each currency, get the release amount if available
        currencies.forEach(currency => {
            if (nextRelease && nextRelease[currency]) {
                result[currency] = {
                    amount: Number(nextRelease[currency]).toFixed(2),
                    month: nextRelease.month,
                    year: nextRelease.year
                };
            } else {
                result[currency] = {
                    amount: '0.00',
                    month: null,
                    year: null
                };
            }
        });

        return result;
    };

    // Get pending reserves for each currency
    const getPendingReservesByCurrency = () => {
        const result = {};

        currencies.forEach(currency => {
            const amount = reserveData?.pending_reserves?.[currency]
                ? Number(reserveData.pending_reserves[currency]).toFixed(2)
                : '0.00';

            result[currency] = amount;
        });

        return result;
    };

    // Safely access statistics with optional chaining and default values
    const totalReleased = reserveData?.statistics?.released_count || 0;

    // Calculate total fees in EUR (fees are already in EUR in the data)
    const calculateTotalFees = () => {
        if (!feeHistory || feeHistory.length === 0) return 0;

        return feeHistory.reduce((total, month) => {
            if (!month) return total;

            const monthTotal = Object.entries(month)
                .filter(([key]) =>
                    key !== 'month' &&
                    key !== 'year' &&
                    key !== 'fullDate' &&
                    key.endsWith('_EUR')
                )
                .reduce((sum, [_, value]) => sum + Number(value || 0), 0);

            return total + monthTotal;
        }, 0);
    };

    const nextReleases = getNextReleasesByCurrency();
    const pendingReserves = getPendingReservesByCurrency();

    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            {/* Next Release Card */}
            <div className="bg-white shadow rounded-lg p-4">
                <h3 className="text-sm font-medium text-gray-500">Next Release Amount</h3>
                <div className="mt-2 space-y-1">
                    {Object.entries(nextReleases).map(([currency, data]) => (
                        <div key={currency} className="flex justify-between items-center">
                            <span className="text-sm font-medium">{currency}:</span>
                            <span className="text-base font-bold">{data.amount}</span>
                        </div>
                    ))}
                </div>
                <p className="text-xs text-gray-500 mt-2">
                    {nextReleases[currencies[0]]?.month
                        ? `Expected in ${nextReleases[currencies[0]].month} ${nextReleases[currencies[0]].year}`
                        : 'No upcoming releases'
                    }
                </p>
            </div>

            {/* Total Pending Reserve Card */}
            <div className="bg-white shadow rounded-lg p-4">
                <h3 className="text-sm font-medium text-gray-500">Total Pending Reserve</h3>
                <div className="mt-2 space-y-1">
                    {Object.entries(pendingReserves).map(([currency, amount]) => (
                        <div key={currency} className="flex justify-between items-center">
                            <span className="text-sm font-medium">{currency}:</span>
                            <span className="text-base font-bold">{amount}</span>
                        </div>
                    ))}
                </div>
                <p className="text-xs text-gray-500 mt-2">Currently held in reserve</p>
            </div>

            {/* Total Released Card */}
            <div className="bg-white shadow rounded-lg p-4">
                <h3 className="text-sm font-medium text-gray-500">Total Released</h3>
                <p className="text-2xl font-bold text-gray-900">{totalReleased} entries</p>
                <p className="text-xs text-gray-500">Historical releases</p>
            </div>

            {/* Total Fees Collected Card */}
            <div className="bg-white shadow rounded-lg p-4">
                <h3 className="text-sm font-medium text-gray-500">Total Fees Collected</h3>
                <p className="text-2xl font-bold text-gray-900">{`${calculateTotalFees().toFixed(2)} EUR`}</p>
                <p className="text-xs text-gray-500">For all fee types</p>
            </div>
        </div>
    );
};

export default SummaryCards;
