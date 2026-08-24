import { useState } from 'react';
import Reports from './Reports.jsx';
import AccountantReports from './AccountantReports.jsx';

const BRAND = '#962E32';

const reportTabs = {
  reports: 'Reports',
  accountants: 'Accountant Reports',
};

export default function BridgeReports() {
  const [activeTab, setActiveTab] = useState('reports');

  const tabClass = (key) =>
    `border-b-2 px-1 py-4 text-sm font-medium transition-colors ${
      activeTab === key
        ? 'text-[#962E32]'
        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
    }`;

  return (
    <div className="space-y-6">
      <div className="border-b border-slate-200">
        <nav className="-mb-px flex space-x-8 overflow-x-auto">
          <button
            type="button"
            onClick={() => setActiveTab('reports')}
            className={tabClass('reports')}
            style={activeTab === 'reports' ? { borderColor: BRAND, color: BRAND } : undefined}
          >
            {reportTabs.reports}
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('accountants')}
            className={tabClass('accountants')}
            style={activeTab === 'accountants' ? { borderColor: BRAND, color: BRAND } : undefined}
          >
            {reportTabs.accountants}
          </button>
        </nav>
      </div>

      <div>{activeTab === 'reports' ? <Reports /> : <AccountantReports />}</div>
    </div>
  );
}
