import {ArrowRightLeft, List} from 'lucide-react';

import AccountsManagement from '../AccountsManagement.jsx';
import FundTransferList from './FundTransferList.jsx';
import SoDTabbedPage from './components/SoDTabbedPage.jsx';

const tabs = [
    {
        key: 'all',
        label: 'All Accounts',
        icon: <List size={16} />,
        content: <AccountsManagement />,
    },
    {
        key: 'fund-transfer',
        label: 'Fund Transfer',
        icon: <ArrowRightLeft size={16} />,
        content: <FundTransferList />,
    },
    // {
    //     key: 'cards',
    //     label: 'Cards',
    //     icon: <WalletCards size={16} />,
    //     content: <CardsManagement />,
    // },
];

const AccountManagementPage = () => (
    <SoDTabbedPage tabs={tabs} />
);

export default AccountManagementPage;
