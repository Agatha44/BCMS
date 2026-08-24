import {
    Receipt,
    Package,
    Wallet,
    Megaphone,
    Calendar,
    AlertTriangle,
    Scale,
    Clock,
    Gavel,
} from 'lucide-react';

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
        label: 'Tolls',
        icon: <Receipt size={16} />,
        content: <TollTransactions />,
    },
    {
        key: 'bundles',
        label: 'Bundles',
        icon: <Package size={16} />,
        content: <TollBundles />,
    },
    {
        key: 'prepayments',
        label: 'Prepayments',
        icon: <Wallet size={16} />,
        content: <TopUps />,
    },
    {
        key: 'advertisements',
        label: 'Advertisements',
        icon: <Megaphone size={16} />,
        content: <AdvertsBilling />,
    },
    {
        key: 'events',
        label: 'Events',
        icon: <Calendar size={16} />,
        content: <EventPayments />,
    },
    {
        key: 'incidents',
        label: 'Incidents',
        icon: <AlertTriangle size={16} />,
        content: <IncidentFine />,
    },
    {
        key: 'overloads',
        label: 'Overloads',
        icon: <Scale size={16} />,
        content: <OverloadFines />,
    },
    {
        key: 'fineCharges',
        label: 'Fine Charge Billing',
        icon: <Gavel size={16} />,
        content: <FineChargeBilling />,
    },
    {
        key: 'endOfShift',
        label: 'End of Shift',
        icon: <Clock size={16} />,
        content: <EndOfShift />,
    },
];

const CollectionsManagementPage = () => <SoDTabbedPage tabs={tabs} />;

export default CollectionsManagementPage;
