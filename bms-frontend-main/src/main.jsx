import React from 'react'
import ReactDOM from 'react-dom/client'
import {Provider} from "react-redux";
import { store } from './store';

import App from './App.jsx'
import {QueryClient, QueryClientProvider} from "@tanstack/react-query";
const queryClient = new QueryClient()
ReactDOM.createRoot(document.getElementById('root')).render(
    <React.StrictMode>
        <QueryClientProvider client={queryClient}>
            <Provider store={store}>
                <App/>
            </Provider>
        </QueryClientProvider>

    </React.StrictMode>,
)
