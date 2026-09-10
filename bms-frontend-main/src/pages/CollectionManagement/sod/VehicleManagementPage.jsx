import SoDTabbedPage from './components/SoDTabbedPage.jsx';
import VehicleTransactions from './vehicles/VehicleTransactions.jsx';
import VehiclesList from './vehicles/VehiclesList.jsx';

const tabs = [
    {
        key: 'all',
        label: 'Vehicle',
        content: <VehiclesList />,
    },
    {
        key: 'transactions',
        label: 'Transaction',
        content: <VehicleTransactions />,
    },
];

const VehicleManagementPage = () => (
    <SoDTabbedPage tabs={tabs} />
);

export default VehicleManagementPage;
