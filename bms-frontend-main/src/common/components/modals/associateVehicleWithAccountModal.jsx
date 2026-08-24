import { useEffect, useState } from 'react';
import { Link2, Loader2, X } from 'lucide-react';
import { Button, Modal } from 'antd';
import Swal from 'sweetalert2';
import AsyncSelect from 'react-select/async';

import { apiService } from '../../../services/api.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';

const fieldLabel = (text) => (
  <span className="mb-1 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
    {text}
  </span>
);

export default function AssociateVehicleWithAccountModal({ isOpen, onClose, onSuccess }) {
  const [creating, setCreating] = useState(false);

  const [selectedVehicle, setSelectedVehicle] = useState(null);
  const [selectedAccount, setSelectedAccount] = useState(null);

  const [form, setForm] = useState({
    rfid_tag_number: '',
    plate_no: '',
    account_id: null,
    account_no: '',
    card_reference: '',
  });

  const resetForm = () => {
    setSelectedVehicle(null);
    setSelectedAccount(null);
    setForm({
      rfid_tag_number: '',
      plate_no: '',
      account_id: null,
      account_no: '',
      card_reference: '',
    });
  };

  const handleClose = () => {
    if (creating) return;
    resetForm();
    onClose?.();
  };

  useEffect(() => {
    if (!isOpen) return;
    resetForm();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen]);

  const getVehicleLabel = (vehicle) => {
    if (!vehicle) return '';
    const plate = vehicle.plate_no || '-';
    const body = vehicle.body || '-';
    return body ? `${plate} - ${body}` : `${plate}`;
  };

  const getAccountLabel = (account) => {
    if (!account) return '';
    const name = `${account.first_name || ''} ${account.middle_name || ''} ${account.surname || ''}`.trim();
    return account.account_no ? `${account.account_no} - ${name}`.trim() : name || '-';
  };

  const selectStyles = {
    control: (base) => ({
      ...base,
      borderColor: '#cbd5e1',
      borderRadius: '0.5rem',
      minHeight: '42px',
    }),
    menuPortal: (base) => ({
      ...base,
      zIndex: 9999,
    }),
    menu: (base) => ({
      ...base,
      zIndex: 9999,
    }),
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!form.plate_no) {
      await Swal.fire({
        icon: 'warning',
        title: 'Vehicle Required',
        text: 'Please select a vehicle.',
      });
      return;
    }

    if (!form.account_id) {
      await Swal.fire({
        icon: 'warning',
        title: 'Account Required',
        text: 'Please select account details.',
      });
      return;
    }

    setCreating(true);
    try {
      const payload = {
        rfid_tag_number: form.rfid_tag_number.trim() || undefined,
        plate_num: form.plate_no,
        account_id: form.account_id,
        account_no: form.account_no?.trim() ? form.account_no.trim() : undefined,
        card_reference: form.card_reference.trim() || undefined,
      };

      const response = await apiService.associateVehicleWithAccount(payload);

      if (response.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Vehicle associated successfully',
          text: response.message || 'The vehicle was associated with the account.',
          timer: 1800,
          showConfirmButton: false,
        });

        resetForm();
        onClose?.();
        if (onSuccess) await onSuccess();
      } else {
        await Swal.fire({
          icon: 'error',
          title: 'Failed to associate vehicle',
          text: response.message || 'Please try again.',
        });
      }
    } catch (err) {
      await Swal.fire({
        icon: 'error',
        title: 'Failed to associate vehicle',
        text: err?.message || 'An error occurred.',
      });
      // eslint-disable-next-line no-console
      console.error('Error associating vehicle:', err);
    } finally {
      setCreating(false);
    }
  };

  return (
    <Modal
      open={isOpen}
      onCancel={handleClose}
      footer={null}
      width={800}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      maskClosable={!creating}
      keyboard={!creating}
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <div className="flex h-10 items-center justify-between bg-[#962E32] px-4 text-white">
        <h2 className="m-0 text-sm font-semibold leading-none text-white">Associate Vehicle with Account</h2>
        <button
          type="button"
          aria-label="Close"
          onClick={handleClose}
          disabled={creating}
          className="flex h-7 w-7 items-center justify-center rounded text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/40 disabled:opacity-50"
        >
          <X size={16} />
        </button>
      </div>

      <form onSubmit={handleSubmit} className="space-y-5 px-6 py-5">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div className="space-y-4">
            <div>
              {fieldLabel('RFID Tag Number')}
              <input
                type="text"
                value={form.rfid_tag_number}
                onChange={(e) => setForm((prev) => ({ ...prev, rfid_tag_number: e.target.value }))}
                className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm text-black transition focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/12"
                placeholder="Enter RFID tag number"
                disabled={creating}
              />
            </div>

            <div>
              {fieldLabel('Select Vehicle')}
              <AsyncSelect
                cacheOptions
                defaultOptions={false}
                loadOptions={async (inputValue) => {
                  const search = (inputValue || '').trim().toLowerCase();
                  if (!search || search.length < 2) return [];

                  try {
                    const response = await apiService.searchVehicles({
                      search,
                      per_page: 20,
                      page: 1,
                    });

                    if (response.success && Array.isArray(response.data)) {
                      return response.data.map((v) => ({
                        value: v.plate_no,
                        label: `${v.plate_no || '-'} - ${v.body || '-'}`,
                        vehicle: v,
                      }));
                    }

                    return [];
                  } catch (err) {
                    // eslint-disable-next-line no-console
                    console.error('Vehicle search error:', err);
                    return [];
                  }
                }}
                value={
                  selectedVehicle
                    ? {
                        value: selectedVehicle.plate_no,
                        label: getVehicleLabel(selectedVehicle),
                        vehicle: selectedVehicle,
                      }
                    : null
                }
                onChange={(opt) => {
                  const vehicle = opt?.vehicle || null;
                  setSelectedVehicle(vehicle);
                  setForm((prev) => ({
                    ...prev,
                    plate_no: opt?.value || '',
                  }));
                }}
                placeholder="Search vehicles by plate number..."
                isClearable
                isDisabled={creating}
                menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                styles={selectStyles}
              />
            </div>
          </div>

          <div className="space-y-4">
            <div>
              {fieldLabel('Card Number')}
              <input
                type="text"
                value={form.card_reference}
                onChange={(e) => setForm((prev) => ({ ...prev, card_reference: e.target.value }))}
                className="w-full rounded-lg border border-slate-300 px-4 py-2.5 font-mono text-sm text-black transition focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/12"
                placeholder="Enter card number"
                disabled={creating}
              />
            </div>

            <div>
              {fieldLabel('Account Details')}
              <AsyncSelect
                cacheOptions
                defaultOptions={false}
                loadOptions={async (inputValue) => {
                  const search = inputValue?.trim() || '';
                  if (!search || search.length < 2) return [];

                  try {
                    const response = await apiService.searchAccounts({
                      search,
                      per_page: 20,
                      page: 1,
                    });

                    if (response.success && response.data?.accounts) {
                      return response.data.accounts.map((account) => ({
                        value: account,
                        label: getAccountLabel(account),
                      }));
                    }

                    return [];
                  } catch (err) {
                    // eslint-disable-next-line no-console
                    console.error('Account search error:', err);
                    return [];
                  }
                }}
                value={
                  selectedAccount
                    ? {
                        value: selectedAccount,
                        label: getAccountLabel(selectedAccount),
                      }
                    : null
                }
                onChange={(opt) => {
                  const account = opt?.value || null;
                  setSelectedAccount(account);
                  setForm((prev) => ({
                    ...prev,
                    account_id: account?.id ?? null,
                    account_no: account?.account_no ?? '',
                    card_reference: account?.card_reference ? String(account.card_reference) : prev.card_reference,
                  }));
                }}
                placeholder="Filter as you type..."
                isClearable
                isDisabled={creating}
                menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                styles={selectStyles}
              />
            </div>
          </div>
        </div>
      </form>

      <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
        <Button
          type="primary"
          icon={creating ? <Loader2 size={14} className="animate-spin" /> : <Link2 size={14} />}
          onClick={handleSubmit}
          loading={creating}
          disabled={creating}
          style={{ backgroundColor: BRAND, borderColor: BRAND }}
          onMouseEnter={(e) => {
            if (creating) return;
            e.currentTarget.style.backgroundColor = BRAND_DARK;
            e.currentTarget.style.borderColor = BRAND_DARK;
          }}
          onMouseLeave={(e) => {
            if (creating) return;
            e.currentTarget.style.backgroundColor = BRAND;
            e.currentTarget.style.borderColor = BRAND;
          }}
        >
          Associate
        </Button>
        <Button onClick={handleClose} disabled={creating}>
          Close
        </Button>
      </div>
    </Modal>
  );
}
