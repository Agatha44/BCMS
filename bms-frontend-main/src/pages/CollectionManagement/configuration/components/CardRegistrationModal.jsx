import React, { useState, useEffect } from 'react';
import { X, AlertCircle } from 'lucide-react';
import Swal from 'sweetalert2';
import AsyncSelect from 'react-select/async';
import Select from 'react-select';
import { apiService } from '../../../../services/api.jsx';

// JS version of the legacy CardRegistrationModal.tsx,
// adapted to bcms-admin-pro paths and apiService,
// placed alongside TerminalDetailsModal in configuration/components.
export default function CardRegistrationModal({
  isOpen,
  onClose,
  onSuccess,
  initialAccount = null
}) {
  const [selectedAccount, setSelectedAccount] = useState(null);
  const [cardReference, setCardReference] = useState('');
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const reasonOptions = [
    { value: 'Card Replacement', label: 'Card Replacement' },
    { value: 'New Card Registration', label: 'New Card Registration' },
    { value: 'Card Update', label: 'Card Update' },
    { value: 'Lost Card', label: 'Lost Card' },
    { value: 'Damaged Card', label: 'Damaged Card' },
    { value: 'System Update', label: 'System Update' },
    { value: 'Other', label: 'Other' }
  ];

  useEffect(() => {
    if (!isOpen) {
      setSelectedAccount(null);
      setCardReference('');
      setReason('');
      setSubmitting(false);
    } else if (initialAccount) {
      const accountData = {
        id: initialAccount.id,
        account_no: initialAccount.account_no,
        first_name: initialAccount.first_name,
        middle_name: initialAccount.middle_name,
        surname: initialAccount.surname,
        phone: initialAccount.phone,
        email: initialAccount.email,
        account_balance: initialAccount.account_balance,
        card_reference: initialAccount.card_reference,
        status: initialAccount.status
      };
      setSelectedAccount(accountData);
      setCardReference(initialAccount.card_reference || '');
      setReason('');
    }
  }, [isOpen, initialAccount]);

  const loadOptions = async (inputValue) => {
    if (!inputValue || inputValue.trim().length < 2) return [];

    try {
      const response = await apiService.searchAccounts({
        search: inputValue.trim(),
        per_page: 20
      });

      if (response.success && response.data?.accounts) {
        const accounts = response.data.accounts;
        return accounts.map((account) => ({
          value: account,
          label:
            `${account.account_no} - ${account.first_name} ${account.middle_name || ''} ${account.surname}`
              .trim() + ` (${account.phone})`
        }));
      }
      return [];
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error('Search error:', error);
      return [];
    }
  };

  const handleSelectAccount = (selectedOption) => {
    if (selectedOption) {
      setSelectedAccount(selectedOption.value);
      setCardReference(selectedOption.value.card_reference || '');
    } else {
      setSelectedAccount(null);
      setCardReference('');
    }
  };

  const handleSubmit = async () => {
    if (!selectedAccount) {
      await Swal.fire({
        icon: 'warning',
        title: 'Account Required',
        text: 'Please select an account first',
        confirmButtonColor: '#3b82f6'
      });
      return;
    }

    if (!cardReference.trim()) {
      await Swal.fire({
        icon: 'warning',
        title: 'Card Reference Required',
        text: 'Please enter a card reference',
        confirmButtonColor: '#3b82f6'
      });
      return;
    }

    if (!reason.trim()) {
      await Swal.fire({
        icon: 'warning',
        title: 'Reason Required',
        text: 'Please select a reason for this card registration or update',
        confirmButtonColor: '#3b82f6'
      });
      return;
    }

    setSubmitting(true);
    try {
      const response = await apiService.registerCardToAccount({
        account_id: selectedAccount.id,
        card_reference: cardReference.trim(),
        reason: reason.trim()
      });

      if (response.success) {
        const isUpdate = !!selectedAccount.card_reference;
        await Swal.fire({
          icon: 'success',
          title: isUpdate ? 'Card Updated Successfully' : 'Card Registered Successfully',
          text: `Card reference ${cardReference} has been ${isUpdate ? 'updated' : 'registered'} to account ${selectedAccount.account_no}`,
          confirmButtonColor: '#10b981'
        });

        setSelectedAccount(null);
        setCardReference('');
        setReason('');

        if (onSuccess) onSuccess();
        onClose();
      } else {
        throw new Error(response.message || 'Card registration failed');
      }
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error('Card registration error:', error);
      await Swal.fire({
        icon: 'error',
        title: 'Registration Failed',
        text: error?.message || 'Failed to register card to account',
        confirmButtonColor: '#ef4444'
      });
    } finally {
      setSubmitting(false);
    }
  };

  const handleKeyPress = (e) => {
    if (e.key === 'Enter' && selectedAccount) {
      handleSubmit();
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <h2 className="text-xl font-semibold text-gray-900">
            {selectedAccount?.card_reference ? 'Update Card' : 'Register Card'}
          </h2>
          <button
            type="button"
            onClick={onClose}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <X className="h-5 w-5 text-gray-500" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-6 max-h-[calc(90vh-140px)] overflow-y-auto">
          {!selectedAccount && (
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Search Account
                </label>
                <AsyncSelect
                  cacheOptions
                  loadOptions={loadOptions}
                  defaultOptions={false}
                  onChange={handleSelectAccount}
                  placeholder="Search by account number, first name, last name, or phone number..."
                  noOptionsMessage={({ inputValue }) =>
                    inputValue.length < 2
                      ? 'Type at least 2 characters to search...'
                      : 'No accounts found'
                  }
                  loadingMessage={() => 'Searching accounts...'}
                  menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                  styles={{
                    control: (base) => ({
                      ...base,
                      borderColor: '#d1d5db',
                      borderRadius: '0.5rem',
                      minHeight: '42px',
                      '&:hover': { borderColor: '#9ca3af' }
                    }),
                    placeholder: (base) => ({
                      ...base,
                      color: '#9ca3af'
                    }),
                    menuPortal: (base) => ({
                      ...base,
                      zIndex: 9999
                    }),
                    menu: (base) => ({
                      ...base,
                      borderRadius: '0.5rem',
                      zIndex: 9999
                    }),
                    option: (base, state) => ({
                      ...base,
                      backgroundColor: state.isSelected
                        ? '#3b82f6'
                        : state.isFocused
                        ? '#eff6ff'
                        : 'white',
                      color: state.isSelected ? 'white' : '#1f2937',
                      '&:active': {
                        backgroundColor: '#3b82f6',
                        color: 'white'
                      }
                    })
                  }}
                  components={{ IndicatorSeparator: () => null }}
                />
                <p className="mt-2 text-xs text-gray-500">
                  Start typing account number, name, or phone number to search
                </p>
              </div>
            </div>
          )}

          {selectedAccount && (
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Card Reference
                </label>
                <input
                  type="text"
                  value={cardReference}
                  onChange={(e) => setCardReference(e.target.value)}
                  onKeyPress={handleKeyPress}
                  placeholder="Enter card reference"
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
                <p className="mt-1 text-xs text-gray-500">
                  Enter the card reference number that will be used for POS transactions
                </p>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Reason <span className="text-red-500">*</span>
                </label>
                <Select
                  value={reason ? reasonOptions.find((opt) => opt.value === reason) : null}
                  onChange={(opt) => setReason(opt ? opt.value : '')}
                  options={reasonOptions}
                  placeholder="Select a reason..."
                  isClearable
                  styles={{
                    control: (base) => ({
                      ...base,
                      borderColor: '#d1d5db',
                      borderRadius: '0.5rem',
                      minHeight: '42px',
                      '&:hover': { borderColor: '#9ca3af' }
                    }),
                    placeholder: (base) => ({
                      ...base,
                      color: '#9ca3af'
                    }),
                    menuPortal: (base) => ({
                      ...base,
                      zIndex: 9999
                    }),
                    menu: (base) => ({
                      ...base,
                      borderRadius: '0.5rem',
                      zIndex: 9999
                    }),
                    option: (base, state) => ({
                      ...base,
                      backgroundColor: state.isSelected
                        ? '#3b82f6'
                        : state.isFocused
                        ? '#eff6ff'
                        : 'white',
                      color: state.isSelected ? 'white' : '#1f2937',
                      '&:active': {
                        backgroundColor: '#3b82f6',
                        color: 'white'
                      }
                    })
                  }}
                  menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                  components={{ IndicatorSeparator: () => null }}
                />
                <p className="mt-1 text-xs text-gray-500">
                  Select a reason for this card registration or update
                </p>
              </div>

              {selectedAccount.card_reference && (
                <div className="p-3 bg-orange-50 border border-orange-200 rounded-lg">
                  <div className="flex items-center space-x-2">
                    <AlertCircle className="h-4 w-4 text-orange-600" />
                    <span className="text-sm text-orange-800">
                      This account already has a card reference:{' '}
                      <strong>{selectedAccount.card_reference}</strong>
                    </span>
                  </div>
                  <p className="mt-1 text-xs text-orange-700">
                    Registering a new card will replace the existing one and create a history record.
                  </p>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end space-x-3 p-6 border-t border-gray-200 bg-gray-50">
          {selectedAccount && (
            <button
              type="button"
              onClick={handleSubmit}
              disabled={submitting || !cardReference.trim() || !reason.trim()}
              className="px-4 py-2 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2"
              style={{ backgroundColor: '#902D30' }}
              onMouseEnter={(e) => {
                if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = '#7a2528';
              }}
              onMouseLeave={(e) => {
                if (!e.currentTarget.disabled) e.currentTarget.style.backgroundColor = '#902D30';
              }}
            >
              <span>Submit</span>
            </button>
          )}
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
}


