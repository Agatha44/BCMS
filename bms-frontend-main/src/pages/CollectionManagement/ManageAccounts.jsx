import { useState } from 'react';
import AccountsManagement from './AccountsManagement.jsx';
import CardsManagement from './CardsManagement.jsx';

const tabLabels = {
  accounts: 'Accounts',
  cards: 'Cards',
};

const TabButton = ({ active, onClick, children }) => (
  <button
    type="button"
    onClick={onClick}
    className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors whitespace-nowrap ${
      active ? '' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
    }`}
    style={active ? { borderColor: '#902D30', color: '#902D30' } : undefined}
  >
    {children}
  </button>
);

export default function ManageAccounts() {
  const [activeTab, setActiveTab] = useState('accounts');

  return (
    <div className="space-y-6">
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex space-x-8 overflow-x-auto">
          <TabButton active={activeTab === 'accounts'} onClick={() => setActiveTab('accounts')}>
            {tabLabels.accounts}
          </TabButton>
          <TabButton active={activeTab === 'cards'} onClick={() => setActiveTab('cards')}>
            {tabLabels.cards}
          </TabButton>
        </nav>
      </div>

      <div>
        {activeTab === 'accounts' && <AccountsManagement />}
        {activeTab === 'cards' && <CardsManagement />}
      </div>
    </div>
  );
}
