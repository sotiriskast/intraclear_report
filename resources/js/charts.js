

// Utility function for random colors
function generateColors(count) {
    const colors = [];
    for (let i = 0; i < count; i++) {
        colors.push(`hsl(${(i * 360) / count}, 70%, 50%)`);
    }
    return colors;
}

export function initializeCharts() {
    return {
        charts: {},

        initCharts() {
            requestAnimationFrame(() => {
                this.initFeeHistoryChart();
                this.initReserveDistributionChart();
                this.initFeeTypeChart();
                this.initReserveTrendChart();
            });
        },

        // Chart initialization methods
        initFeeHistoryChart() {
            const ctx = document.getElementById('feeHistoryChart')?.getContext('2d');
            if (!ctx) return;

            this.charts.feeHistory = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Fee Amount (EUR)',
                        data: [],
                        borderColor: '#4F46E5',
                        tension: 0.4,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Fee History Trend'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '€' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        },

        initReserveDistributionChart() {
            const ctx = document.getElementById('reserveDistributionChart')?.getContext('2d');
            if (!ctx) return;

            this.charts.reserveDistribution = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: generateColors(5)
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        title: {
                            display: true,
                            text: 'Reserve Distribution by Currency'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += '€' + context.raw.toLocaleString();
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        },

        destroyCharts() {
            Object.values(this.charts).forEach(chart => chart.destroy());
            this.charts = {};
        },

        // Update methods for each chart
        updateFeeHistoryChart() {
            if (!this.feeHistorySummary || !this.charts.feeHistory) return;

            const dates = this.feeHistorySummary.map(fee =>
                new Date(fee.applied_date).toLocaleDateString()
            );
            const amounts = this.feeHistorySummary.map(fee =>
                fee.fee_amount_eur / 100
            );

            this.charts.feeHistory.data.labels = dates;
            this.charts.feeHistory.data.datasets[0].data = amounts;
            this.charts.feeHistory.update('none'); // Use 'none' for better performance
        },

        updateReserveDistributionChart() {
            if (!this.rollingReserveSummary?.pending_reserves || !this.charts.reserveDistribution) return;

            const currencies = Object.keys(this.rollingReserveSummary.pending_reserves);
            const amounts = Object.values(this.rollingReserveSummary.pending_reserves);

            this.charts.reserveDistribution.data.labels = currencies;
            this.charts.reserveDistribution.data.datasets[0].data = amounts;
            this.charts.reserveDistribution.update('none');
        }
    };
}
