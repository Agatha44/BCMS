import { Plus, ClipboardList } from 'lucide-react';

import BundleSubscriptionsList from './bundles/BundleSubscriptionsList.jsx';
import SoDTabbedPage from './components/SoDTabbedPage.jsx';

const tabs = [
  {
    key: 'subscriptions',
    label: 'Subscriptions',
    icon: <ClipboardList size={16} />,
    content: <BundleSubscriptionsList />,
  },
  { key: 'buy', label: 'Buy Bundle', icon: <Plus size={16} /> },
];

const BundleManagementPage = () => (
  <SoDTabbedPage tabs={tabs} />
);

export default BundleManagementPage;
