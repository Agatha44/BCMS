import {ArrowUpCircle, ClipboardList} from 'lucide-react';
import SoDTabbedPage from './components/SoDTabbedPage.jsx';

const tabs = [
    {key: 'topup', label: 'Topup Prepayment', icon: <ArrowUpCircle size={16} />},
    {key: 'transactions', label: 'Prepayment Transactions', icon: <ClipboardList size={16} />},
];

const PrepaymentManagementPage = () => (
    <SoDTabbedPage tabs={tabs} />
);

export default PrepaymentManagementPage;
