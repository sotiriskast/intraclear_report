import React from 'react';

const MerchantSelector = ({ merchants, selectedMerchant, setSelectedMerchant, loading }) => (
    <div>
        <label htmlFor="merchant-select" className="block text-sm font-medium text-gray-700">
            Merchant
        </label>
        <select
            id="merchant-select"
            className="mt-1 block w-full rounded-md border-gray-300 focus:ring-blue-500"
            value={selectedMerchant?.toString() || ''}
            onChange={(e) => setSelectedMerchant(e.target.value ? Number(e.target.value) : null)}
            disabled={loading}
        >
            <option value="">All Merchants</option>
            {merchants.map((merchant) => (
                <option key={merchant.id} value={merchant.id.toString()}>
                    {merchant.name}
                </option>
            ))}
        </select>
    </div>
);

export default MerchantSelector;
