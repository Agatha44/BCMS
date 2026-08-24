import React from 'react';

// Simple Transactions landing page migrated from the legacy app.
// Note: CollectionsManagement already hosts the detailed transaction tabs;
// this page is a lightweight wrapper/overview.
export default function Transactions() {
  return (
    <div className="space-y-6 bg-white">
      {/* Breadcrumb */}
      <div className="text-sm text-gray-600 dark:text-gray-400">
        Home &gt; Transactions
      </div>

      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-50">Transactions</h1>
        <p className="text-gray-600 dark:text-gray-400 mt-1">
          View and manage all toll transactions
        </p>
      </div>

      {/* Content */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div className="text-center py-12">
          <div className="text-6xl mb-4">💳</div>
          <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-2">
            Transaction Management
          </h3>
          <p className="text-gray-600 dark:text-gray-400">
            Monitor, search, and analyze toll transactions
          </p>
          <button
            type="button"
            className="mt-4 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700"
          >
            Export Transactions
          </button>
        </div>
      </div>
    </div>
  );
}


