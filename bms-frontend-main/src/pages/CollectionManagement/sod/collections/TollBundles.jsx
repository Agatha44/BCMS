import BundleBills from '../../../../common/components/transactions/bundles/BundleBills.jsx';
import VehicleBundleSubscriptions from './VehicleBundleSubscriptions.jsx';
import InlineSoDTabs from '../components/InlineSoDTabs.jsx';

const tabs = [
    {
        key: 'bundleBills',
        label: 'Bundle Bill',
        content: <BundleBills />,
    },
    {
        key: 'bundleSubscriptions',
        label: 'Bundle Subscription',
        content: <VehicleBundleSubscriptions />,
    },
];

export default function TollBundles() {
    return <InlineSoDTabs tabs={tabs} defaultActiveKey="bundleBills" />;
}
