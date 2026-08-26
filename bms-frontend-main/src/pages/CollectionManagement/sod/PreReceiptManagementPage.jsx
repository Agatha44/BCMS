import SoDTabbedPage from './components/SoDTabbedPage.jsx';
import TollBundles from './collections/TollBundles.jsx';
import TopUps from './collections/TopUps.jsx';

const tabs = [
  {
    key: 'bundles',
    label: 'Bundle',
    content: <TollBundles />,
  },
  {
    key: 'prepayments',
    label: 'Prepayment',
    content: <TopUps />,
  },
];

const PreReceiptManagementPage = () => <SoDTabbedPage tabs={tabs} />;

export default PreReceiptManagementPage;
