import {Settings, DollarSign, Layers} from 'lucide-react';
import SoDTabbedPage from './components/SoDTabbedPage.jsx';

const tabs = [
    {key: 'toll-rates', label: 'Toll Rates', icon: <DollarSign size={16} />},
    {key: 'vehicle-categories', label: 'Vehicle Categories', icon: <Layers size={16} />},
    {key: 'configuration', label: 'System Configuration', icon: <Settings size={16} />},
];

const SystemSettingsPage = () => (
    <SoDTabbedPage tabs={tabs} />
);

export default SystemSettingsPage;
