import {FileText, ClipboardList} from 'lucide-react';

import BundleBills from '../../../../common/components/transactions/bundles/BundleBills.jsx';
import VehicleBundleSubscriptions from './VehicleBundleSubscriptions.jsx';
import InlineSoDTabs from '../components/InlineSoDTabs.jsx';

const tabs = [
    {
        key: 'bundleBills',
        label: 'Bundle Bills',
        icon: <FileText size={16} />,
        content: <BundleBills />,
    },
    {
        key: 'bundleSubscriptions',
        label: 'Vehicle Bundle Subscriptions',
        icon: <ClipboardList size={16} />,
        content: <VehicleBundleSubscriptions />,
    },
];

export default function TollBundles() {
    return <InlineSoDTabs tabs={tabs} defaultActiveKey="bundleBills" />;
}
