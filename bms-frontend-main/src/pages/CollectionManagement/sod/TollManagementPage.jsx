import {
    ArrowDownCircle,
    Pencil,
    DoorOpen,
    Printer,
    ClipboardList,
} from 'lucide-react';
import SoDTabbedPage from './components/SoDTabbedPage.jsx';

const tabs = [
    {key: 'receive', label: 'Receive Toll', icon: <ArrowDownCircle size={16} />},
    {key: 'update', label: 'Update Toll', icon: <Pencil size={16} />},
    {key: 'open-gate', label: 'Open Gate', icon: <DoorOpen size={16} />},
    {key: 'reprint-receipt', label: 'Reprint Receipt', icon: <Printer size={16} />},
    {key: 'transactions', label: 'Transaction History', icon: <ClipboardList size={16} />},
];

const TollManagementPage = () => (
    <SoDTabbedPage tabs={tabs} />
);

export default TollManagementPage;
