import { List } from 'lucide-react';

import SoDTabbedPage from './components/SoDTabbedPage.jsx';

const tabs = [
  {
    key: 'all',
    label: 'All Pre-receipts',
    icon: <List size={16} />,
    description: 'Pre-receipt records will appear here once this section is connected.',
  },
];

const PreReceiptManagementPage = () => <SoDTabbedPage tabs={tabs} />;

export default PreReceiptManagementPage;
