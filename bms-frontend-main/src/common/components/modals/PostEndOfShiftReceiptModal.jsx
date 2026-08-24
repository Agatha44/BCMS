import { useEffect, useMemo, useState } from 'react';
import { CheckCircle2, X } from 'lucide-react';
import { Button, Modal, Steps } from 'antd';
import PropTypes from 'prop-types';
import Swal from 'sweetalert2';

import BmsDatePicker, { BmsDatePickerField } from '../forms/BmsDatePicker.jsx';
import { formatMoney } from '../../utils/numberFormat.js';
import { apiService } from '../../../services/api.jsx';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const BrandModalHeader = ({ title, onClose }) => (
  <div className="flex h-10 items-center justify-between bg-[#962E32] px-4 text-white">
    <h2 className="m-0 text-sm font-semibold leading-none text-white">{title}</h2>
    <button
      type="button"
      aria-label="Close"
      onClick={onClose}
      className="flex h-7 w-7 items-center justify-center rounded text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/40"
    >
      <X size={16} />
    </button>
  </div>
);

BrandModalHeader.propTypes = {
  title: PropTypes.string.isRequired,
  onClose: PropTypes.func.isRequired,
};

const inputClassName =
  'w-full border border-gray-300 rounded-lg px-4 py-3 bg-white text-gray-700 text-sm focus:ring-2 focus:ring-[#962E32] focus:border-[#962E32] transition-colors';

export default function PostEndOfShiftReceiptModal({
  isOpen,
  onClose = undefined,
  onProcessed = undefined,
}) {
  const [currentStep, setCurrentStep] = useState(0);
  const [loading, setLoading] = useState(false);
  const [shifts, setShifts] = useState([]);
  const [success, setSuccess] = useState(null);

  const [form, setForm] = useState({
    shift_id: '',
    shift_date: '',
    bank_date: '',
    bank_receipt: '',
    bank_amount: '',
    reason: '',
  });
  const [details, setDetails] = useState(null);

  const closeModal = () => {
    if (loading) return;
    setCurrentStep(0);
    setForm({ shift_id: '', shift_date: '', bank_date: '', bank_receipt: '', bank_amount: '', reason: '' });
    setDetails(null);
    setSuccess(null);
    onClose?.();
  };

  useEffect(() => {
    if (!isOpen) return;
    apiService.getEndOfShiftShifts().then((resp) => {
      if (resp.success) {
        const list = Array.isArray(resp.data) ? resp.data : resp.data?.data ?? [];
        setShifts(list);
      }
    });
  }, [isOpen]);

  const detailsAmount = useMemo(() => {
    const d = details?.data ?? details;
    return d?.billingAmount ?? d?.billing_amount ?? d?.sum_per_shift ?? d?.amount;
  }, [details]);

  const canEnquire = Boolean(form.shift_id) && Boolean(form.shift_date);

  return (
    <>
      <Modal
        open={isOpen && !success}
        onCancel={closeModal}
        footer={null}
        width={720}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!loading}
        keyboard={!loading}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Post End of Shift Receipt" onClose={closeModal} />
        <div className="px-6 py-5">
          <div className="mb-6">
            <Steps current={currentStep} size="small" items={[{ title: 'Enquiry' }, { title: 'Confirm & Post' }]} />
          </div>

          {currentStep === 0 ? (
            <form
              className="space-y-5"
              onSubmit={async (e) => {
                e.preventDefault();
                if (!canEnquire) return;
                setLoading(true);
                try {
                  const resp = await apiService.getEndOfShiftShiftAmount({
                    shift_id: Number(form.shift_id),
                    shift_date: form.shift_date,
                  });
                  if (!resp.success) {
                    await Swal.fire({ icon: 'error', title: 'Failed', text: resp.message || 'Failed to load shift details' });
                    setDetails(null);
                    return;
                  }
                  setDetails(resp.data ?? resp);
                  setForm((prev) => ({
                    ...prev,
                    bank_amount: String(resp.data?.billingAmount ?? resp.data?.billing_amount ?? ''),
                  }));
                  setCurrentStep(1);
                } finally {
                  setLoading(false);
                }
              }}
            >
              <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                <BmsDatePickerField label="Shift Date">
                  <BmsDatePicker
                    value={form.shift_date}
                    onChange={(v) => setForm((prev) => ({ ...prev, shift_date: v }))}
                    disabled={loading}
                    size="large"
                  />
                </BmsDatePickerField>
                <div>
                  <label className="mb-2 block text-sm font-medium" style={{ color: BRAND }}>
                    Shift
                  </label>
                  <select
                    value={form.shift_id}
                    onChange={(e) => setForm((prev) => ({ ...prev, shift_id: e.target.value }))}
                    className={inputClassName}
                    disabled={loading}
                  >
                    <option value="">Select shift</option>
                    {shifts.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
              <div className="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                <Button onClick={closeModal} disabled={loading}>
                  Close
                </Button>
                <Button type="primary" htmlType="submit" loading={loading} disabled={!canEnquire} style={{ backgroundColor: BRAND, borderColor: BRAND }}>
                  Enquire Details
                </Button>
              </div>
            </form>
          ) : (
            <div className="space-y-5">
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Confirm billing amount <span className="font-mono font-semibold">{formatMoney(detailsAmount)}</span> for{' '}
                <span className="font-medium">{details?.shift_name ?? details?.data?.shift_name ?? 'selected shift'}</span> on {form.shift_date}.
              </div>
              <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                <BmsDatePickerField label="Bank Date">
                  <BmsDatePicker
                    value={form.bank_date}
                    onChange={(v) => setForm((p) => ({ ...p, bank_date: v }))}
                    disabled={loading}
                    size="large"
                  />
                </BmsDatePickerField>
                <div>
                  <label className="mb-2 block text-sm font-medium" style={{ color: BRAND }}>
                    Bank Receipt
                  </label>
                  <input value={form.bank_receipt} onChange={(e) => setForm((p) => ({ ...p, bank_receipt: e.target.value }))} className={inputClassName} type="text" placeholder="Bank receipt reference" disabled={loading} />
                </div>
                <div>
                  <label className="mb-2 block text-sm font-medium" style={{ color: BRAND }}>
                    Bank Amount (TZS)
                  </label>
                  <input value={form.bank_amount} onChange={(e) => setForm((p) => ({ ...p, bank_amount: e.target.value }))} className={inputClassName} type="number" min="1" disabled={loading} />
                </div>
                <div>
                  <label className="mb-2 block text-sm font-medium" style={{ color: BRAND }}>
                    Reason (optional)
                  </label>
                  <input value={form.reason} onChange={(e) => setForm((p) => ({ ...p, reason: e.target.value }))} className={inputClassName} type="text" disabled={loading} />
                </div>
              </div>
              <div className="flex items-center justify-between border-t border-slate-200 pt-4">
                <Button onClick={() => setCurrentStep(0)} disabled={loading}>
                  Previous
                </Button>
                <div className="flex gap-2">
                  <Button onClick={closeModal} disabled={loading}>
                    Close
                  </Button>
                  <Button
                    type="primary"
                    loading={loading}
                    style={{ backgroundColor: BRAND, borderColor: BRAND }}
                    onClick={async () => {
                      if (!form.bank_date || !form.bank_receipt) {
                        await Swal.fire({ icon: 'warning', title: 'Required', text: 'Bank date and bank receipt are required.' });
                        return;
                      }
                      const bankAmount = Number(String(form.bank_amount).replaceAll(',', ''));
                      if (!Number.isFinite(bankAmount) || bankAmount <= 0) {
                        await Swal.fire({ icon: 'warning', title: 'Invalid amount', text: 'Enter a valid bank amount.' });
                        return;
                      }
                      setLoading(true);
                      try {
                        const resp = await apiService.postShiftErpReceipt({
                          shift_id: Number(form.shift_id),
                          shift_date: form.shift_date,
                          bank_date: form.bank_date,
                          bank_receipt: form.bank_receipt.trim(),
                          bank_amount: bankAmount,
                          customer_name: 'NSSF',
                          activity: 'End of shift',
                          reason: form.reason?.trim() || undefined,
                        });
                        if (!resp.success) {
                          await Swal.fire({ icon: 'error', title: 'Failed', text: resp.message || 'Failed to process end of shift' });
                          return;
                        }
                        setSuccess({
                          receipt_number: resp.data?.receipt_number,
                          bank_amount: resp.data?.bank_amount,
                          bank_receipt: resp.data?.bank_receipt,
                          message: resp.message,
                        });
                        onProcessed?.(resp);
                      } finally {
                        setLoading(false);
                      }
                    }}
                  >
                    Proceed
                  </Button>
                </div>
              </div>
            </div>
          )}
        </div>
      </Modal>

      <Modal
        open={!!success}
        onCancel={closeModal}
        footer={null}
        width={520}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Receipt Posted" onClose={closeModal} />
        <div className="px-6 py-6 text-center">
          <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-50 text-green-600">
            <CheckCircle2 size={28} />
          </div>
          <h3 className="text-base font-semibold text-slate-900">End of shift posted successfully</h3>
          {success?.message && <p className="mt-1 text-xs text-slate-500">{success.message}</p>}
          <div className="mt-4 space-y-2 rounded-md border border-slate-200 bg-slate-50 p-4 text-left text-sm">
            <div>
              <span className="font-semibold" style={{ color: BRAND }}>
                Receipt:
              </span>{' '}
              <span className="font-mono">{success?.receipt_number || EMPTY_VALUE}</span>
            </div>
            <div>
              <span className="font-semibold" style={{ color: BRAND }}>
                Bank Receipt:
              </span>{' '}
              <span className="font-mono">{success?.bank_receipt || EMPTY_VALUE}</span>
            </div>
            <div>
              <span className="font-semibold" style={{ color: BRAND }}>
                Amount:
              </span>{' '}
              {success?.bank_amount != null ? formatMoney(success.bank_amount) : EMPTY_VALUE}
            </div>
          </div>
          <div className="mt-6 flex justify-end">
            <Button onClick={closeModal}>Close</Button>
          </div>
        </div>
      </Modal>
    </>
  );
}

PostEndOfShiftReceiptModal.propTypes = {
  isOpen: PropTypes.bool.isRequired,
  onClose: PropTypes.func,
  onProcessed: PropTypes.func,
};
