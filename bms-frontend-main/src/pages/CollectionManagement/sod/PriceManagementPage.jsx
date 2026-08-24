import { List } from 'lucide-react';

import PriceManagement from '../PriceManagement.jsx';
import SoDTabbedPage from './components/SoDTabbedPage.jsx';

const tabs = [
  {
    key: 'prices',
    label: 'Toll prices',
    icon: <List size={16} />,
    content: <PriceManagement />,
  },
];

const PriceManagementPage = () => (
  <SoDTabbedPage tabs={tabs} />
);

export default PriceManagementPage;
