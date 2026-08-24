import { useMutation } from "@tanstack/react-query";
/**
 * @desc A custom hook to perform POST requests.
 * @desc {Function} onSuccess -  This function will fire when the mutation is successful and will be passed the mutation's result.
 *  @desc {Function} onError - This function will fire if the mutation encounters an error and will be passed the error.
 *  @desc {Function} onSettled - This function will fire when the mutation is either successfully fetched or encounters an error and be passed either the data or error
 *  @desc {Hook} onMutate -  This custom hook can be used to perform optimistic update which receives variables from mutateFn
 * @param {Function} callback - The function to make the POST request. It should return a promise.
 * // Function definition
 *   const postTodo = (newTodo) => {
 *   return axios.post('https://api.example.com/todos', newTodo);
 * };
 *  //
 * @param {Object} [options] - Optional configuration for the mutation.
 * @returns {Object} An object containing the mutation status, data, error, and mutation function.
 * @example Usage of status and fetchStatus
 *      status === 'idle -  The mutation is currently idle or in a fresh/reset state ;
 *     status === 'pending -  The mutation is currently running
 *     status === 'success - The mutation was successful and mutation data is available
 *    status === 'error - Error: {error.message};
 *
 * @example Usage of helpers
 *   onMutate: (variables) => {
 *     // A mutation is about to happen!
 *
 *     // Optionally return a context containing data to use when for example rolling back
 *     return { id: 1 }
 *   },
 *
 *   onError: (error, variables, context) => {
 *     // An error happened!
 *     console.log(`rolling back optimistic update with id ${context.id}`)
 *   },
 *
 *   onSuccess: (data, variables, context) => {
 *     // Boom baby!
 *   },
 *
 *   onSettled: (data, error, variables, context) => {
 *     // Error or success... doesn't matter!
 *   },
 *
 */
export function usePostRequest(callback, options = {
    gcTime: 0,
    retry: 3,
    onSuccess: () => {},
    onError: () => {},
    onSettled: () => {},
    onMutate: () => {},
    mutationKey: 'key-11'
}) {
    const { mutate, data, error, status } = useMutation({
        mutationFn: callback,
       ...options,
    });

    return {
        mutate,
        data,
        error,
        status,
    };
}
