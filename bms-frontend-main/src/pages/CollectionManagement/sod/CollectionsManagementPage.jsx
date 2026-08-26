import SoDTabbedPage from './components/SoDTabbedPage.jsx';
import TollTransactions from './collections/TollTransactions.jsx';
import TollBundles from './collections/TollBundles.jsx';
import TopUps from './collections/TopUps.jsx';
import AdvertsBilling from './collections/AdvertsBilling.jsx';
import EventPayments from './collections/EventPayments.jsx';
import IncidentFine from './collections/IncidentFine.jsx';
import OverloadFines from './collections/OverloadFines.jsx';
import FineChargeBilling from './collections/FineChargeBilling.jsx';
import EndOfShift from './collections/EndOfShift.jsx';

const tabs = [
    {
        key: 'tolls',
        label: 'Toll',
        content: <TollTransactions />,
    },
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
    {
        key: 'advertisements',
        label: 'Advertisement',
        content: <AdvertsBilling />,
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
        key: 'overloads',
        label: 'Overload',
        content: <OverloadFines />,
    },
    {
        key: 'fineCharges',
        label: 'Fine',
        content: <FineChargeBilling />,
    },
    {
        key: 'endOfShift',
        label: 'End-of-shift',
        content: <EndOfShift />,
    },
];

const CollectionsManagementPage = () => <SoDTabbedPage tabs={tabs} />;

export default CollectionsManagementPage;
