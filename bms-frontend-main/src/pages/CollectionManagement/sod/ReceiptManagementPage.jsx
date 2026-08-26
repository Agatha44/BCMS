import SoDTabbedPage from './components/SoDTabbedPage.jsx';
import TollTransactions from './collections/TollTransactions.jsx';
import AdvertsBilling from './collections/AdvertsBilling.jsx';
import EventPayments from './collections/EventPayments.jsx';
import IncidentFine from './collections/IncidentFine.jsx';
import FineChargeBilling from './collections/FineChargeBilling.jsx';

const tabs = [
  {
    key: 'tolls',
    label: 'Toll',
    content: <TollTransactions />,
  },
  {
    key: 'events',
    label: 'Event',
    content: <EventPayments />,
  },
  {
    key: 'incidents',
    label: 'Incident',
    content: <IncidentFine />,
  },
  {
    key: 'fineCharges',
    label: 'Fine',
    content: <FineChargeBilling />,
  },
  {
    key: 'advertisements',
    label: 'Advertisement',
    content: <AdvertsBilling />,
  },
];

const ReceiptManagementPage = () => <SoDTabbedPage tabs={tabs} />;

export default ReceiptManagementPage;
