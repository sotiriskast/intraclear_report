import React from 'react';

const CurrencySelector = ({ currencies, selectedCurrency, setSelectedCurrency, loading }) => (
    <div>
        <label htmlFor="currency-select" className="block text-sm font-medium text-gray-700">
            Currency
        </label>
        <select
            id="currency-select"
            className="mt-1 block w-full rounded-md border-gray-300 focus:ring-blue-500"
            value={selectedCurrency}
            onChange={(e) => setSelectedCurrency(e.target.value)}
            disabled={loading}
        >
            {currencies.map((currency) => (
                <option key={currency} value={currency}>
                    {currency}
                </option>
            ))}
        </select>
    </div>
);

export default CurrencySelector;
