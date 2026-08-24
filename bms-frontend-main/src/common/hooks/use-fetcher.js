import { useQuery } from "@tanstack/react-query";

/**
 * @desc A custom hook that accepts a callback function and a key for identifying cached data.
 * @desc Callback definition should be
 * const fetchTodos = () =>   return axios.get('/https://demo-api')
 *
 * @param {Function} callback - The function to fetch data.
 * @param {string} referKey - A unique identifier for the cached data.
 * @param staleTime - A time in milliseconds use to invalidate the cached data
 * @param gcTime - The time in milliseconds used to initiate a new fetch
 * @returns {Object} An object containing the query status, fetch status, data, and error.
 * @example Usage of status and fetchStatus
 *     status === 'pending - The query has no data yet ;
 *     status === 'success - The query was successful and data is available ;
 *    status === 'error - Error: {error.message};
 *    fetchStatus === 'fetching - The query is currently fetching;
 *    fetchStatus === 'idle - The query is not doing anything at the moment ;
 *    fetchStatus === 'paused - The query wanted to fetch, but it is paused ;
 */
export function useFetcher(callback = () => {}, referKey = "key", staleTime=1000 * 60 * 60, gcTime=1000 * 60 * 60) {
    const { data, error, status, fetchStatus } = useQuery({
        queryKey: [referKey],
        queryFn: async () => {
            const response = await callback();
            return response.data;
        },
        staleTime: staleTime,
        gcTime: gcTime,
        retry: 3
    });

    return {
        status,
        fetchStatus,
        data,
        error,
    };
}
