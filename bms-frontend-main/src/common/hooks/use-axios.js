import { useCallback, useEffect, useRef, useState } from 'react';
import axiosInstance from '../api/base.js';
import { getToken } from '../api/handlers.js';

const cache = {};

export default function useAxios(endpoint, method = 'GET', body = null) {
    const [data, setData] = useState(cache[endpoint] || null);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(true);
    const cacheRef = useRef(cache);
    const accessToken = getToken();

    const fetchData = useCallback(async () => {
        if (cacheRef.current[endpoint]) {
            setData(cacheRef.current[endpoint]);
            setLoading(false);
        } else {
            setLoading(true);
        }

        try {
            let response;
            const headers = {
                Authorization: `Bearer ${accessToken}`
            };
            const config = { headers };
            switch (method) {
                case 'POST':
                    response = await axiosInstance.post(endpoint, body, config);
                    break;
                case 'PUT':
                    response = await axiosInstance.put(endpoint, body, config);
                    break;
                case 'DELETE':
                    response = await axiosInstance.delete(endpoint, config);
                    break;
                default:
                    response = await axiosInstance.get(endpoint, config);
            }
            cacheRef.current[endpoint] = response.data;
            setData(response.data);
        } catch (error) {
            setError(error);
        } finally {
            setLoading(false);
        }
    }, [endpoint, accessToken, method, body]);

    useEffect(() => {
        fetchData().then((r) => r);
    }, [fetchData]);

    return { data, error, loading, refetch: fetchData };
}
