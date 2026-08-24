import {useEffect} from "react";
import {useDelete} from "../../common/hooks/use-delete.jsx";
import axios from "axios";
import {Button, App} from "antd";

function deleteTodo()
{
    return axios('https://jsonplaceholder.typicode.com/posts/1', {
        method: 'DELETE',

    })
}
const SupportPage = () => {
    const { message } = App.useApp();
    const {data, error, mutate, status } = useDelete(deleteTodo, {
     onSuccess: (data, variables,context) => {
         console.log(data, context, variables)
     },
     onError: (error, variables, context)=> {
         console.log(error, context, variables)
     },
     onMutate: (variables)=> {
         console.log(variables, 'from onMutate')

     },
 })

    useEffect(() => {
        if(data) message.info("There is data")
    }, [data, message])

    useEffect(() => {
        if(error) message.error(error?.message)
    }, [error, message])

    useEffect(() => {
        if(status === "pending") message.info('deleting todo', 3)
    }, [status, message])

    useEffect(() => {
        if(status === "success") message.info('todo successfully deleted')
    }, [status, message])

    return (
        <div className="">
            <p>
                Status: {status}
            </p>
            <Button onClick={mutate}>Delete Todo</Button>
        </div>
    );
};

export default SupportPage;