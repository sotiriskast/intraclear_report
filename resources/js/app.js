import './bootstrap';
import Chart from 'chart.js/auto';
// Make Chart.js globally available
window.Chart = Chart;

// If you're using the charts initialization function as previously suggested:
import { initializeCharts } from './charts';
window.initializeCharts = initializeCharts;
