import {ClipboardList, Receipt} from 'lucide-react';

import EndOfShiftDetails from '../../../../common/components/transactions/end_of_shift/EndOfShiftDetails.jsx';
import EndOfShiftBilling from '../../../../common/components/transactions/end_of_shift/EndOfShiftBilling.jsx';
import InlineSoDTabs from '../components/InlineSoDTabs.jsx';

const tabs = [
    {
        key: 'billing',
        label: 'Billing',
        icon: <Receipt size={16} />,
        content: <EndOfShiftBilling hideBreadcrumb />,
    },
    {
        key: 'details',
        label: 'Details',
        icon: <ClipboardList size={16} />,
        content: <EndOfShiftDetails hideBreadcrumb />,
    },
];

export default function EndOfShift() {
    return <InlineSoDTabs tabs={tabs} defaultActiveKey="billing" />;
}
