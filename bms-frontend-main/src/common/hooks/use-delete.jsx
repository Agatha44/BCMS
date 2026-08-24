import {useMutation} from "@tanstack/react-query";

/**
 * @desc A custom hook to perform DELETE requests.
 * @event  onSuccess
 * This function will fire when the mutation is successful and will be passed the mutation's result.
 * It Receives error, variables, context from mutate call
 *  @event  onError
 *  This function will fire if the mutation encounters an error and will be passed the error. It receives data, variables, context
 *  @event  onSettled
 *  This function will fire when the mutation is either successfully fetched or encounters an error and be passed either the data or error. It receives data, error, variables, context
 *  @event onMutate
 *  This custom hook can be used to perform optimistic update which receives variables from mutateFn
 *  @see https://tanstack.com/query/latest/docs/framework/react/guides/mutations
 * @param {Function} callback - The function to make the DELETE request. It should returnS a promise.
 * // Function definition
 *   const deleteTodo = (id) => {
 *   return axios.delete('https://api.example.com/todos', id);
 * };
 *  //
 * @param {Object} [options] - Optional configuration for the mutation.
 * @returns {Object} An object containing the mutation status, data, error, and mutate function.
 * @example Usage of status and fetchStatus
 *      status === 'idle -  The mutation is currently idle or in a fresh/reset state ;
 *     status === 'pending -  The mutation is currently running
 *     status === 'success - The mutation was successful and mutation data is available
 *    status === 'error - Error: {error.message};
 *
 * @example Usage of helpers
 *
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
export function useDelete(callback=()=> {}, options= {

    onSuccess: () => {},
    onError: () => {},
    onSettled: () => {},
    onMutate: () => {},
    mutationKey: ['key-11'],
    retry: 3,
})
{
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