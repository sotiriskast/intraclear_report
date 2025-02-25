<template>
    <div>
        <!-- Debug section to verify data is received -->
        <div v-if="debug" class="mb-4 p-4 bg-gray-100 rounded">
            <p class="font-bold">Debug Info:</p>
            <p>Fee History Items: {{ feeHistory.length }}</p>
            <p>Rolling Reserves Keys: {{ Object.keys(rollingReserves).join(', ') }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Fee History Chart -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Fee History Trend</h2>
                <div class="h-80">
                    <LineChart
                        v-if="feeChartData.labels.length > 0"
                        :chart-data="feeChartData"
                        :options="feeChartOptions"
                    />
                    <div v-else class="h-full flex items-center justify-center text-gray-400">
                        No fee data available
                    </div>
                </div>
            </div>

            <!-- Reserve Distribution Chart -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Reserve Distribution</h2>
                <div class="h-80">
                    <DoughnutChart
                        v-if="reserveChartData.labels.length > 0"
                        :chart-data="reserveChartData"
                        :options="reserveChartOptions"
                    />
                    <div v-else class="h-full flex items-center justify-center text-gray-400">
                        No reserve data available
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { defineComponent, ref, computed, watch, onMounted } from 'vue';
import { LineChart, DoughnutChart } from 'vue-chart-3';
import { Chart, registerables } from 'chart.js';

// Register Chart.js components
Chart.register(...registerables);

export default defineComponent({
    name: 'MerchantCharts',
    components: {
        LineChart,
        DoughnutChart
    },
    props: {
        feeHistory: {
            type: Array,
            default: () => []
        },
        rollingReserves: {
            type: Object,
            default: () => ({})
        },
        debug: {
            type: Boolean,
            default: true
        }
    },
    setup(props) {
        // Log props on mount to verify data
        onMounted(() => {
            console.log('MerchantCharts mounted with props:', props);
        });

        // Format fee data for chart
        const feeChartData = computed(() => {
            console.log('Calculating fee chart data with:', props.feeHistory);

            if (!props.feeHistory || props.feeHistory.length === 0) {
                return { labels: [], datasets: [] };
            }

            const sortedFees = [...props.feeHistory].sort((a, b) =>
                new Date(a.applied_date) - new Date(b.applied_date)
            );

            const labels = sortedFees.map(fee => {
                const date = new Date(fee.applied_date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });

            const data = sortedFees.map(fee => fee.fee_amount_eur / 100);

            return {
                labels,
                datasets: [
                    {
                        label: 'Fee Amount (EUR)',
                        data,
                        borderColor: '#4F46E5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            };
        });

        // Fee chart options
        const feeChartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '€' + context.parsed.y.toLocaleString();
                        }
                    }
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
        };

        // Format reserve data for chart
        const reserveChartData = computed(() => {
            console.log('Calculating reserve chart data with:', props.rollingReserves);

            if (!props.rollingReserves || !props.rollingReserves.pending_reserves) {
                return { labels: [], datasets: [] };
            }

            const pendingReserves = props.rollingReserves.pending_reserves || {};

            const labels = Object.keys(pendingReserves);
            const data = Object.values(pendingReserves);

            return {
                labels,
                datasets: [
                    {
                        data,
                        backgroundColor: [
                            '#4F46E5', '#06B6D4', '#10B981', '#F59E0B', '#EF4444'
                        ],
                        borderWidth: 1
                    }
                ]
            };
        });

        // Reserve chart options
        const reserveChartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            return label + ': €' + value.toLocaleString();
                        }
                    }
                }
            }
        };

        return {
            feeChartData,
            feeChartOptions,
            reserveChartData,
            reserveChartOptions
        };
    }
});
</script>
