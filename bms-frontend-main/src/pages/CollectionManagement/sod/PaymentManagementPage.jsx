import {
    Megaphone,
    Target,
    AlertTriangle,
    ClipboardList,
} from 'lucide-react';
import SoDTabbedPage from './components/SoDTabbedPage.jsx';

const tabs = [
    {key: 'events', label: 'Pay Events', icon: <Megaphone size={16} />},
    {key: 'advertisements', label: 'Pay Advertisement', icon: <Target size={16} />},
    {key: 'fines', label: 'Pay Fines', icon: <AlertTriangle size={16} />},
    {key: 'records', label: 'Payment Records', icon: <ClipboardList size={16} />},
];

const PaymentManagementPage = () => (
    <SoDTabbedPage tabs={tabs} />
);

export default PaymentManagementPage;
