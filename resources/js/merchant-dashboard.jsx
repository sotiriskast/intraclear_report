import React from 'react';
import { createRoot } from 'react-dom/client';
import MerchantDashboard from './components/MerchantDashboard';

document.addEventListener('DOMContentLoaded', () => {
    const element = document.getElementById('merchant-dashboard-root');

    if (element) {
        const merchantId = element.dataset.merchantId;
        const root = createRoot(element);

        root.render(
            <MerchantDashboard merchantId={merchantId} />
        );
    }
});
