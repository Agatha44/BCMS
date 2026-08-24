import { List } from 'lucide-react';

import SoDTabbedPage from './components/SoDTabbedPage.jsx';
import TollTransactions from './collections/TollTransactions.jsx';

const tabs = [
  {
    key: 'all',
    label: 'All Receipts',
    icon: <List size={16} />,
    content: <TollTransactions />,
  },
];

const ReceiptManagementPage = () => <SoDTabbedPage tabs={tabs} />;

export default ReceiptManagementPage;
