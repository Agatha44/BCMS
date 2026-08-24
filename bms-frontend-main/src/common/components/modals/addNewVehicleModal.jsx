import { useEffect, useState } from 'react';
import { Loader2, Plus, X } from 'lucide-react';
import { Button, Modal } from 'antd';
import Swal from 'sweetalert2';
import AsyncSelect from 'react-select/async';
import Select from 'react-select';

import { apiService } from '../../../services/api.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';

const fieldLabel = (text, required = false) => (
  <span className="mb-1 block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
    {text}
    {required && <span className="ml-1 text-red-500">*</span>}
  </span>
);

export default function AddNewVehicleModal({ isOpen, onClose, onSuccess }) {
  const [creating, setCreating] = useState(false);
  const [bodyTypes, setBodyTypes] = useState([]);

  const [selectedAccount, setSelectedAccount] = useState(null);
  const [accountsSearchOpen, setAccountsSearchOpen] = useState(false);

  const [createForm, setCreateForm] = useState({
    rfid_tag_number: '',
    plate_no: '',
    body_type_id: 0,
    card_reference: '',
    account_id: null,
    account_no: '',
  });

  const resetForm = () => {
    setSelectedAccount(null);
    setAccountsSearchOpen(false);
    setCreateForm({
      rfid_tag_number: '',
      plate_no: '',
      body_type_id: 0,
      card_reference: '',
      account_id: null,
      account_no: '',
    });
  };

  const handleClose = () => {
    if (creating) return;
    resetForm();
    onClose?.();
  };

  const loadBodyTypes = async () => {
    try {
      const response = await apiService.getBodyTypes();
      if (response.success && Array.isArray(response.data?.body_types)) {
        setBodyTypes(response.data.body_types);
      } else if (response.success && Array.isArray(response.data)) {
        setBodyTypes(response.data);
      } else {
        setBodyTypes([]);
      }
    } catch (e) {
      // eslint-disable-next-line no-console
      console.error('Error loading body types:', e);
      setBodyTypes([]);
    }
  };

  useEffect(() => {
    if (!isOpen) return;
    resetForm();
    loadBodyTypes();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen]);

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

    if (!createForm.plate_no.trim()) {
      await Swal.fire({
        icon: 'warning',
        title: 'Plate Number Required',
        text: 'Please enter a plate number.',
      });
      return;
    }

    if (!createForm.body_type_id || Number(createForm.body_type_id) === 0) {
      await Swal.fire({
        icon: 'warning',
        title: 'Body Type Required',
        text: 'Please select a body type.',
      });
      return;
    }

    setCreating(true);
    try {
      const payload = {
        rfid_tag_number: createForm.rfid_tag_number?.trim() ? createForm.rfid_tag_number.trim() : undefined,
        plate_no: createForm.plate_no.trim(),
        body_type_id: Number(createForm.body_type_id),
        account_id: createForm.account_id ?? undefined,
        account_no: createForm.account_no?.trim() ? createForm.account_no.trim() : undefined,
        card_reference: createForm.card_reference?.trim() ? createForm.card_reference.trim() : undefined,
      };

      const response = await apiService.createVehicle(payload);

      if (response.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Vehicle created successfully',
          text: response.message || 'The vehicle was added.',
          timer: 1800,
          showConfirmButton: false,
        });

        resetForm();
        onClose?.();
        if (onSuccess) await onSuccess();
      } else {
        await Swal.fire({
          icon: 'error',
          title: 'Failed to create vehicle',
          text: response.message || 'Please try again.',
        });
      }
    } catch (err) {
      await Swal.fire({
        icon: 'error',
        title: 'Failed to create vehicle',
        text: err?.message || 'An error occurred.',
      });
      // eslint-disable-next-line no-console
      console.error('Error creating vehicle:', err);
    } finally {
      setCreating(false);
    }
  };

  return (
    <Modal
      open={isOpen}
      onCancel={handleClose}
      footer={null}
      width={720}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      maskClosable={!creating}
      keyboard={!creating}
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <div className="flex h-10 items-center justify-between bg-[#962E32] px-4 text-white">
        <h2 className="m-0 text-sm font-semibold leading-none text-white">Add New Vehicle</h2>
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
          <div>
            {fieldLabel('RFID Tag Number')}
            <input
              type="text"
              value={createForm.rfid_tag_number}
              onChange={(e) => setCreateForm((prev) => ({ ...prev, rfid_tag_number: e.target.value }))}
              className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm text-black transition focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/12"
              placeholder="Enter RFID tag number"
              disabled={creating}
            />
          </div>

          <div>
            {fieldLabel('Plate Number', true)}
            <input
              type="text"
              required
              value={createForm.plate_no}
              onChange={(e) => setCreateForm((prev) => ({ ...prev, plate_no: e.target.value }))}
              className="w-full rounded-lg border border-slate-300 px-4 py-2.5 font-mono text-sm text-black transition focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/12"
              placeholder="Enter plate number"
              disabled={creating}
            />
          </div>
        </div>

        <div>
          {fieldLabel('Body Type', true)}
          <Select
            value={
              createForm.body_type_id
                ? {
                    value: Number(createForm.body_type_id),
                    label:
                      bodyTypes.find((bodyType) => Number(bodyType.id) === Number(createForm.body_type_id))?.name ||
                      'Selected Body Type',
                  }
                : null
            }
            onChange={(opt) => {
              setCreateForm((prev) => ({
                ...prev,
                body_type_id: opt?.value ? Number(opt.value) : 0,
              }));
            }}
            options={bodyTypes.map((bodyType) => ({
              value: Number(bodyType.id),
              label: bodyType.name,
            }))}
            placeholder="Select body type"
            isClearable
            isSearchable
            menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
            styles={selectStyles}
            isDisabled={creating}
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
                    label: `${account.account_no} - ${account.first_name} ${account.middle_name || ''} ${account.surname}`.trim(),
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
                    label: `${selectedAccount.account_no} - ${selectedAccount.first_name} ${selectedAccount.middle_name || ''} ${selectedAccount.surname}`.trim(),
                  }
                : null
            }
            onChange={(opt) => {
              const account = opt?.value || null;
              setSelectedAccount(account);
              setCreateForm((prev) => ({
                ...prev,
                account_id: account?.id ?? null,
                account_no: account?.account_no ?? '',
                card_reference: account?.card_reference ? String(account.card_reference) : '',
              }));
            }}
            placeholder="Search accounts by account number, name, or phone..."
            isClearable
            menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
            styles={selectStyles}
            onMenuOpen={() => setAccountsSearchOpen(true)}
            onMenuClose={() => setAccountsSearchOpen(false)}
            isDisabled={creating}
          />
          {!accountsSearchOpen && (
            <p className="mt-1 text-xs text-slate-500">Start typing to search accounts (at least 2 characters).</p>
          )}
        </div>

        <div>
          {fieldLabel('Card Number')}
          <input
            type="text"
            value={createForm.card_reference}
            onChange={(e) => setCreateForm((prev) => ({ ...prev, card_reference: e.target.value }))}
            className="w-full rounded-lg border border-slate-300 px-4 py-2.5 font-mono text-sm text-black transition focus:border-[#962E32] focus:outline-none focus:ring-2 focus:ring-[#962E32]/12"
            placeholder="Enter card reference number"
            disabled={creating}
          />
        </div>
      </form>

      <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
        <Button
          type="primary"
          icon={creating ? <Loader2 size={14} className="animate-spin" /> : <Plus size={14} />}
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
          Add
        </Button>
        <Button onClick={handleClose} disabled={creating}>
          Close
        </Button>
      </div>
    </Modal>
  );
}
