import { createApp } from 'vue';
import MerchantCharts from './components/MerchantCharts.vue';

// Initialize Vue app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    initMerchantCharts();

    // Re-init charts when Livewire updates the component
    document.addEventListener('livewire:update', () => {
        initMerchantCharts();
    });
});

function initMerchantCharts() {
    const chartContainer = document.getElementById('vue-merchant-charts');

    if (chartContainer) {
        // Get data from the container's data attributes
        let feeHistory = [];
        let rollingReserves = {};

        try {
            feeHistory = JSON.parse(chartContainer.dataset.feeHistory || '[]');
            rollingReserves = JSON.parse(chartContainer.dataset.rollingReserves || '{}');

            console.log('Parsed data for Vue charts:', {
                feeHistory,
                rollingReserves
            });
        } catch (error) {
            console.error('Error parsing chart data:', error);
            console.log('Raw fee history data:', chartContainer.dataset.feeHistory);
            console.log('Raw rolling reserves data:', chartContainer.dataset.rollingReserves);
        }

        // Check if Vue app is already mounted
        if (chartContainer._vueApp) {
            // Update props on existing app
            const app = chartContainer._vueApp;

            // Get the component instance and update its props
            const instance = app._instance;
            if (instance && instance.props) {
                instance.props.feeHistory = feeHistory;
                instance.props.rollingReserves = rollingReserves;
            }
        } else {
            // Create Vue app
            const app = createApp(MerchantCharts, {
                feeHistory,
                rollingReserves,
                debug: true
            });

            // Mount app to container
            app.mount(chartContainer);

            // Store reference to the app
            chartContainer._vueApp = app;
        }
    }
}
