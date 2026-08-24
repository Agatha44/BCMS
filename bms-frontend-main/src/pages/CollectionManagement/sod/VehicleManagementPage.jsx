import {ClipboardList, List} from 'lucide-react';
import SoDTabbedPage from './components/SoDTabbedPage.jsx';
import VehicleTransactions from './vehicles/VehicleTransactions.jsx';
import VehiclesList from './vehicles/VehiclesList.jsx';

const tabs = [
    {
        key: 'all',
        label: 'All Vehicles',
        icon: <List size={16} />,
        content: <VehiclesList />,
    },
    {
        key: 'transactions',
        label: 'Vehicle Transactions',
        icon: <ClipboardList size={16} />,
        content: <VehicleTransactions />,
    },
];

const VehicleManagementPage = () => (
    <SoDTabbedPage tabs={tabs} />
);

export default VehicleManagementPage;
