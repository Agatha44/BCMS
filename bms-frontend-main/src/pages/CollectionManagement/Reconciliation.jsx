import React from 'react';

export default function Reconciliation() {
  return (
    <div className="space-y-6">
      <div className="bg-white rounded-lg shadow p-6">
        <div className="text-center py-12">
          <div className="text-6xl mb-4">📋</div>
          <h3 className="text-lg font-medium text-gray-900 mb-2">Financial Reconciliation</h3>
          <p className="text-gray-600">Match transactions with financial records and resolve discrepancies</p>
          <button className="mt-4 bg-[#7A2326] text-white px-4 py-2 rounded-lg hover:bg-[#5d1c1f]">
            Start Reconciliation
          </button>
        </div>
      </div>
    </div>
  );
}


