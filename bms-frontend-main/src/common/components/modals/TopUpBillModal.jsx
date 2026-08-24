import { useEffect, useMemo, useState } from 'react';
import { X } from 'lucide-react';
import AsyncSelect from 'react-select/async';
import Swal from 'sweetalert2';

import { apiService } from '../../../services/api.jsx';

const inputClassName =
  'w-full border border-gray-300 rounded-lg px-4 py-3 bg-white text-gray-700 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors';

const defaultForm = {
  account_no: '',
  tin: '',
  amount: '',
  description: '',
};

export default function TopUpBillModal({ isOpen, onClose, onSubmitted }) {
  const [submitting, setSubmitting] = useState(false);
  const [selectedAccount, setSelectedAccount] = useState(null);
  const [form, setForm] = useState(defaultForm);

  const closeModal = () => {
    if (submitting) return;
    setSelectedAccount(null);
    setForm(defaultForm);
    onClose?.();
  };

  useEffect(() => {
    if (!isOpen) return;
    // reset each time opened
    setSelectedAccount(null);
    setForm(defaultForm);
  }, [isOpen]);

  const accountDisplay = useMemo(() => {
    const a = selectedAccount || null;
    return {
      label:
        a?.account_no || '',
      owner:
        `${a?.first_name || ''} ${a?.middle_name || ''} ${a?.surname || ''}`.replace(/\s+/g, ' ').trim(),
    };
  }, [selectedAccount]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div className="p-6">
          <div className="flex justify-between items-center mb-6">
            <h3 className="text-xl font-semibold text-gray-900">Top Up Bill</h3>
            <button
              type="button"
              onClick={closeModal}
              className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
              aria-label="Close modal"
              disabled={submitting}
            >
              <X size={20} />
            </button>
          </div>

          <form
            className="space-y-5"
            onSubmit={async (e) => {
              e.preventDefault();

              if (!form.account_no?.trim()) {
                await Swal.fire({ icon: 'warning', title: 'Account required', text: 'Please select an account.' });
                return;
              }
              const amountNumber = Number(String(form.amount).replaceAll(',', '').trim());
              if (!Number.isFinite(amountNumber) || amountNumber <= 0) {
                await Swal.fire({ icon: 'warning', title: 'Amount required', text: 'Please enter a valid bill amount.' });
                return;
              }
              if (!form.description?.trim()) {
                await Swal.fire({ icon: 'warning', title: 'Description required', text: 'Please enter bill description.' });
                return;
              }

              setSubmitting(true);
              try {
                const payload = {
                  account_no: form.account_no.trim(),
                  tin: form.tin?.trim() || undefined,
                  amount: amountNumber,
                  description: form.description.trim(),
                };

                const resp = await apiService.getTopUpBills(payload);
                if (!resp.success) {
                  await Swal.fire({ icon: 'error', title: 'Failed', text: resp.message || 'Failed to request top up bill' });
                  return;
                }

                await Swal.fire({ icon: 'success', title: 'Success', text: resp.message || 'Top up bill requested successfully', timer: 2000, showConfirmButton: false });
                onSubmitted?.(resp);
                closeModal();
              } finally {
                setSubmitting(false);
              }
            }}
          >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Account Details</label>
                <AsyncSelect
                  cacheOptions
                  defaultOptions={false}
                  loadOptions={async (inputValue) => {
                    const search = inputValue?.trim() || '';
                    if (!search || search.length < 2) return [];
                    try {
                      const res = await apiService.searchAccounts({ search, page: 1, per_page: 20 });
                      const items = res.success ? res.data?.accounts : [];
                      if (!Array.isArray(items)) return [];
                      return items.map((account) => ({
                        value: account,
                        label: `${account.account_no} - ${account.first_name} ${account.middle_name || ''} ${account.surname}`.trim(),
                      }));
                    } catch {
                      return [];
                    }
                  }}
                  value={
                    selectedAccount
                      ? {
                          value: selectedAccount,
                          label: `${selectedAccount.account_no} - ${selectedAccount.first_name} ${selectedAccount.middle_name || ''} ${selectedAccount.surname}`.trim(),
                        }
                      : null
                  }
                  onChange={(option) => {
                    const account = option?.value || null;
                    setSelectedAccount(account);
                    setForm((prev) => ({
                      ...prev,
                      account_no: account?.account_no ?? '',
                      tin: prev.tin || account?.tin || account?.tax_identification_number || '',
                    }));
                  }}
                  placeholder="Search accounts by account number or name..."
                  isClearable
                  isDisabled={submitting}
                  menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                  styles={{
                    control: (base) => ({
                      ...base,
                      borderColor: '#d1d5db',
                      borderRadius: '0.5rem',
                      minHeight: '50px',
                    }),
                    menuPortal: (base) => ({ ...base, zIndex: 9999 }),
                    menu: (base) => ({ ...base, zIndex: 9999 }),
                  }}
                />
                {accountDisplay.label ? (
                  <div className="mt-2 text-xs text-gray-500">
                    Selected: <span className="font-medium text-gray-700">{accountDisplay.label}</span>
                    {accountDisplay.owner ? (
                      <>
                        {' '}
                        (<span className="font-medium text-gray-700">{accountDisplay.owner}</span>)
                      </>
                    ) : null}
                  </div>
                ) : null}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Bill Amount</label>
                <input
                  value={form.amount}
                  onChange={(e) => setForm((prev) => ({ ...prev, amount: e.target.value }))}
                  className={inputClassName}
                  type="number"
                  min="0"
                  step="1"
                  placeholder="Enter amount"
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Tax Identification Number</label>
                <input
                  value={form.tin}
                  onChange={(e) => setForm((prev) => ({ ...prev, tin: e.target.value }))}
                  className={inputClassName}
                  type="text"
                  placeholder="TIN"
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Bill Description</label>
                <textarea
                  value={form.description}
                  onChange={(e) => setForm((prev) => ({ ...prev, description: e.target.value }))}
                  className="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  rows={3}
                  placeholder="Description"
                  disabled={submitting}
                />
              </div>
            </div>

            <div className="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
              <button
                type="button"
                onClick={closeModal}
                className="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                disabled={submitting}
              >
                Cancel
              </button>
              <button
                type="submit"
                className="px-4 py-2 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                style={{ backgroundColor: '#902D30' }}
                onMouseEnter={(e) => {
                  if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = '#7a2528';
                }}
                onMouseLeave={(e) => {
                  if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = '#902D30';
                }}
                disabled={submitting}
              >
                {submitting ? 'Requesting...' : 'Request Top Up'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}

