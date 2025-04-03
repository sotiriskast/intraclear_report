import React from 'react';

const ErrorDisplay = ({ message, onRetry, redirectUrl }) => (
    <div className="p-6">
        <div className="bg-red-50 border-l-4 border-red-500 p-4">
            <div className="flex items-center">
                <svg className="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-11a1 1 0 112 0v4a1 1 0 11-2 0V7zm1 8a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"
                          clipRule="evenodd" />
                </svg>
                <p className="ml-3 text-sm text-red-700">{message}</p>
            </div>
        </div>
        {redirectUrl ? (
            <button onClick={() => window.location.href = redirectUrl}
                    className="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md">
                Go to Login Page
            </button>
        ) : (
            <button onClick={onRetry}
                    className="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md">
                Retry
            </button>
        )}
    </div>
);

export default ErrorDisplay;
