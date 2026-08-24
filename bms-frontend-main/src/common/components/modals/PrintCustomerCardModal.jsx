import { useEffect, useMemo, useState } from 'react';
import { Printer } from 'lucide-react';
import AsyncSelect from 'react-select/async';
import { Button, Modal, QRCode } from 'antd';
import Swal from 'sweetalert2';

import { apiService } from '../../../services/api.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';

const BrandModalHeader = ({ title, onClose }) => (
  <div className="flex h-10 items-center justify-between bg-[#962E32] px-4 text-white">
    <h2 className="m-0 text-sm font-semibold leading-none text-white">{title}</h2>
    <button
      type="button"
      aria-label="Close"
      onClick={onClose}
      className="flex h-7 w-7 items-center justify-center rounded text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/40"
    >
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
        <line x1="18" y1="6" x2="6" y2="18" />
        <line x1="6" y1="6" x2="18" y2="18" />
      </svg>
    </button>
  </div>
);

function pickCardFromResponse(data) {
  if (!data || typeof data !== 'object') return null;
  const nested = data.card ?? data.customer_card ?? data.data?.card;
  if (nested && nested.id != null) return nested;
  if (data.id != null) return data;
  return null;
}

export default function PrintCustomerCardModal({ isOpen, onClose, onCardCreated }) {
  const [step, setStep] = useState(1);
  const [selectedAccount, setSelectedAccount] = useState(null);
  const [createdCard, setCreatedCard] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [printing, setPrinting] = useState(false);
  const [lastPdfUrl, setLastPdfUrl] = useState(null);

  const closeModal = () => {
    if (submitting || printing) return;
    if (lastPdfUrl) {
      URL.revokeObjectURL(lastPdfUrl);
      setLastPdfUrl(null);
    }
    setStep(1);
    setSelectedAccount(null);
    setCreatedCard(null);
    onClose?.();
  };

  useEffect(() => {
    if (!isOpen) return;
    setStep(1);
    setSelectedAccount(null);
    setCreatedCard(null);
    setLastPdfUrl((prev) => {
      if (prev) URL.revokeObjectURL(prev);
      return null;
    });
  }, [isOpen]);

  const customerDisplay = useMemo(() => {
    const a = selectedAccount;
    if (!a) return { accountNo: '', fullName: '' };
    const fullName = `${a.first_name || ''} ${a.middle_name || ''} ${a.surname || ''}`.replace(/\s+/g, ' ').trim();
    return {
      accountNo: a.account_no || '',
      fullName: fullName || '—',
    };
  }, [selectedAccount]);

  const handleContinue = async () => {
    if (!selectedAccount?.account_no?.trim()) {
      await Swal.fire({ icon: 'warning', title: 'Account required', text: 'Search and select a customer account.' });
      return;
    }

    setSubmitting(true);
    try {
      const payload = {
        account_no: String(selectedAccount.account_no).trim(),
      };
      const resp = await apiService.createCustomerCard(payload);
      if (!resp.success) {
        await Swal.fire({
          icon: 'error',
          title: 'Could not create card request',
          text: resp.message || 'Request failed',
        });
        return;
      }

      const card = pickCardFromResponse(resp.data);
      if (!card || !card.id) {
        await Swal.fire({
          icon: 'warning',
          title: 'Unexpected response',
          text: 'Card was created but the response did not include a card id. Check the API payload shape.',
        });
        return;
      }

      setCreatedCard(card);
      setStep(2);
      onCardCreated?.();
    } catch (e) {
      await Swal.fire({ icon: 'error', title: 'Error', text: e?.message || 'Something went wrong' });
    } finally {
      setSubmitting(false);
    }
  };

  const handlePrint = async () => {
    const id = createdCard?.id;
    if (!id) {
      await Swal.fire({ icon: 'warning', title: 'Missing card', text: 'No card id available for printing.' });
      return;
    }

    setPrinting(true);
    try {
      if (lastPdfUrl) {
        URL.revokeObjectURL(lastPdfUrl);
        setLastPdfUrl(null);
      }
      const resp = await apiService.getCustomerCardPdf(id);
      if (!resp.success || !resp.data) {
        await Swal.fire({ icon: 'error', title: 'Print failed', text: resp.message || 'Could not load PDF' });
        return;
      }
      const url = resp.data;
      setLastPdfUrl(url);
      window.open(url, '_blank', 'noopener,noreferrer');
    } catch (e) {
      await Swal.fire({ icon: 'error', title: 'Error', text: e?.message || 'Could not open PDF' });
    } finally {
      setPrinting(false);
    }
  };

  const previewPayload = createdCard?.preview_qr_payload || '';
  const cardRef = createdCard?.card_reference || '—';

  return (
    <Modal
      open={isOpen}
      onCancel={closeModal}
      footer={null}
      width={780}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      maskClosable={!submitting && !printing}
      keyboard={!submitting && !printing}
      className="brand-modal"
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <BrandModalHeader title="Print Card" onClose={closeModal} />

      <div className="px-6 py-5">
        {step === 1 && (
          <div className="space-y-5">
            <p className="text-sm text-slate-600">Search by account number or customer name, then continue to create the card request and preview.</p>
            <div>
              <label className="block text-xs font-semibold tracking-[0.01em] mb-1.5" style={{ color: BRAND }}>Account</label>
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
                      label: `${account.account_no} — ${account.first_name || ''} ${account.middle_name || ''} ${account.surname || ''}`.trim(),
                    }));
                  } catch {
                    return [];
                  }
                }}
                value={
                  selectedAccount
                    ? {
                        value: selectedAccount,
                        label: `${selectedAccount.account_no} — ${selectedAccount.first_name || ''} ${selectedAccount.middle_name || ''} ${selectedAccount.surname || ''}`.trim(),
                      }
                    : null
                }
                onChange={(option) => setSelectedAccount(option?.value || null)}
                placeholder="Search account number or name..."
                isClearable
                isDisabled={submitting}
                menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                styles={{
                  control: (base) => ({ ...base, borderColor: '#d1d5db', borderRadius: '0.375rem', minHeight: '40px' }),
                  menuPortal: (base) => ({ ...base, zIndex: 10000 }),
                  menu: (base) => ({ ...base, zIndex: 10000 }),
                }}
              />
            </div>
          </div>
        )}

        {step === 2 && (
          <div className="space-y-5">
            <div className="rounded-md border border-slate-200 bg-slate-50 p-4">
              <h4 className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]" style={{ color: BRAND }}>
                Customer
              </h4>
              <div className="grid grid-cols-1 gap-y-2 md:grid-cols-2">
                <div>
                  <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>Account No.</span>
                  <span className="mt-0.5 block font-mono text-sm font-medium text-black">{customerDisplay.accountNo || '—'}</span>
                </div>
                <div>
                  <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>Name</span>
                  <span className="mt-0.5 block text-sm font-medium text-black">{customerDisplay.fullName || '—'}</span>
                </div>
              </div>
            </div>

            <div className="flex flex-col items-center rounded-md border border-slate-200 bg-white p-6 min-h-[280px]">
              <p className="mb-4 text-xs font-semibold uppercase tracking-[0.12em]" style={{ color: BRAND }}>Card Preview</p>
              {previewPayload ? (
                <div className="p-4 bg-white rounded-md border border-slate-100 shadow-sm">
                  <QRCode value={previewPayload} size={180} type="canvas" />
                </div>
              ) : (
                <p className="text-sm text-amber-700 text-center max-w-md">No preview QR payload from server yet.</p>
              )}
              <p className="mt-3 break-all text-center text-2xl font-mono font-semibold tracking-wide text-black">
                {cardRef}
              </p>
            </div>
          </div>
        )}
      </div>

      <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
        {step === 1 && (
          <Button
            type="primary"
            onClick={handleContinue}
            loading={submitting}
            disabled={submitting}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
            onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = BRAND_DARK; e.currentTarget.style.borderColor = BRAND_DARK; }}
            onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = BRAND; e.currentTarget.style.borderColor = BRAND; }}
          >
            Submit
          </Button>
        )}
        {step === 2 && (
          <Button
            type="primary"
            icon={<Printer size={14} />}
            onClick={handlePrint}
            loading={printing}
            disabled={printing || !createdCard?.id}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
            onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = BRAND_DARK; e.currentTarget.style.borderColor = BRAND_DARK; }}
            onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = BRAND; e.currentTarget.style.borderColor = BRAND; }}
          >
            Print
          </Button>
        )}
        <Button onClick={closeModal} disabled={submitting || printing}>Close</Button>
      </div>
    </Modal>
  );
}
