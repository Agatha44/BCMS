import React, { useState } from 'react';
import LaneConfigurations from './configuration/LaneConfigurations.jsx';
import POSManagement from './configuration/POSManagement.jsx';
import PriceManagement from './PriceManagement.jsx';

const BRAND = '#962E32';

export default function ConfigurationManagement() {
  const [activeTab, setActiveTab] = useState('lane');

  const tabClass = (key) =>
    `border-b-2 px-1 py-4 text-sm font-medium transition-colors ${
      activeTab === key
        ? 'text-[#962E32]'
        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
    }`;

  return (
    <div className="space-y-6">
      <div className="border-b border-slate-200">
        <nav className="-mb-px flex space-x-8">
          <button
            type="button"
            onClick={() => setActiveTab('lane')}
            className={tabClass('lane')}
            style={activeTab === 'lane' ? { borderColor: BRAND, color: BRAND } : undefined}
          >
            Lane Configuration
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('pos')}
            className={tabClass('pos')}
            style={activeTab === 'pos' ? { borderColor: BRAND, color: BRAND } : undefined}
          >
            POS Configuration
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('prices')}
            className={tabClass('prices')}
            style={activeTab === 'prices' ? { borderColor: BRAND, color: BRAND } : undefined}
          >
            Toll Prices
          </button>
        </nav>
      </div>

      <div>
        {activeTab === 'lane' && <LaneConfigurations hideBreadcrumb />}
        {activeTab === 'pos' && <POSManagement hideBreadcrumb />}
        {activeTab === 'prices' && <PriceManagement />}
      </div>
    </div>
  );
}
