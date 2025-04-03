// api.js - Enhanced with retry logic and better error handling

/**
 * Fetch data from API with retry mechanism for rate limiting
 * @param {string} url - API endpoint to fetch
 * @param {Object} options - Fetch options
 * @param {number} maxRetries - Maximum number of retries
 * @param {number} retryDelay - Base delay between retries in ms
 * @returns {Promise<Object>} - API response
 */
export const fetchAPI = async (url, options = {}, maxRetries = 3, retryDelay = 1000) => {
    let retries = 0;

    while (retries <= maxRetries) {
        try {
            const response = await fetch(url, {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...options.headers
                },
                ...options
            });

            if (!response.ok) {
                // Special handling for rate limiting
                if (response.status === 429) {
                    // If we're out of retries, throw the error
                    if (retries === maxRetries) {
                        throw new Error('Too many requests. Please try again later.');
                    }

                    // Otherwise, wait with exponential backoff and retry
                    const waitTime = retryDelay * Math.pow(2, retries);
                    console.log(`Rate limited. Retrying in ${waitTime}ms (retry ${retries + 1}/${maxRetries})...`);
                    await new Promise(resolve => setTimeout(resolve, waitTime));
                    retries++;
                    continue;
                }

                // Handle other error responses
                if (response.status === 401) {
                    throw new Error('Authentication required');
                } else {
                    const errorText = await response.text();
                    throw new Error(`API Error (${response.status}): ${errorText || response.statusText}`);
                }
            }

            const data = await response.json();
            return data;
        } catch (error) {
            // If it's not a rate limiting error or we're out of retries, rethrow
            if (error.message !== 'Too many requests. Please try again later.' || retries === maxRetries) {
                throw error;
            }

            retries++;
        }
    }
};
