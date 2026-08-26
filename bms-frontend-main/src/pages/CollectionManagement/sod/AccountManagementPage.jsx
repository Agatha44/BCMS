import AccountsManagement from '../AccountsManagement.jsx';
import FundTransferList from './FundTransferList.jsx';
import SoDTabbedPage from './components/SoDTabbedPage.jsx';

const tabs = [
    {
        key: 'all',
        label: 'All Accounts',
        content: <AccountsManagement />,
    },
    {
        key: 'fund-transfer',
        label: 'Fund Transfer',
        content: <FundTransferList />,
    },
];

const AccountManagementPage = () => (
    <SoDTabbedPage tabs={tabs} />
);

export default AccountManagementPage;
