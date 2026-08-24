import { useEffect, useMemo, useState } from 'react';
import { CheckCircle2, Copy, X } from 'lucide-react';
import { Button, Modal } from 'antd';
import PropTypes from 'prop-types';
import Swal from 'sweetalert2';

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

const buildBillDescription = (shiftId, shiftName) => {
  if (shiftId === 1 || String(shiftName || '').toLowerCase().includes('morning')) {
    return 'Morning Shift Bill';
  }
  if (shiftId === 2 || String(shiftName || '').toLowerCase().includes('afternoon')) {
    return 'Afternoon Shift Bill';
  }
  return 'Evening Shift Bill';
};

export default function CreateEndOfShiftBillModal({
  isOpen,
  shiftId = '',
  shiftDate = '',
  onClose = undefined,
  onCreated = undefined,
}) {
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [details, setDetails] = useState(null);
  const [success, setSuccess] = useState(null);

  const shiftName = details?.shift_name ?? details?.data?.shift_name ?? '';
  const billAmount = useMemo(() => {
    const d = details?.data ?? details;
    return d?.billingAmount ?? d?.billing_amount ?? d?.sum_per_shift;
  }, [details]);

  const billDescription = useMemo(
    () => buildBillDescription(Number(shiftId), shiftName),
    [shiftId, shiftName]
  );

  const reset = () => {
    setDetails(null);
    setSuccess(null);
    setLoading(false);
    setSubmitting(false);
  };

  const closeModal = () => {
    if (loading || submitting) return;
    reset();
    onClose?.();
  };

  useEffect(() => {
    if (!isOpen || !shiftId || !shiftDate) return;

    let cancelled = false;
    const load = async () => {
      setLoading(true);
      setDetails(null);
      setSuccess(null);
      try {
        const resp = await apiService.getEndOfShiftShiftAmount({
          shift_id: Number(shiftId),
          shift_date: shiftDate,
        });
        if (cancelled) return;
        if (!resp.success) {
          await Swal.fire({ icon: 'error', title: 'Failed', text: resp.message || 'Failed to load shift billing amount' });
          closeModal();
          return;
        }
        setDetails(resp.data ?? resp);
      } catch (e) {
        if (!cancelled) {
          await Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load shift billing amount' });
          closeModal();
        }
        console.error(e);
      } finally {
        if (!cancelled) setLoading(false);
      }
    };

    load();
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen, shiftId, shiftDate]);

  const handleSubmit = async () => {
    if (!billAmount) return;
    setSubmitting(true);
    try {
      const resp = await apiService.createEndOfShiftBill({
        shift_id: Number(shiftId),
        shift_date: shiftDate,
        bill_amount: Number(billAmount),
        bill_desc: billDescription,
      });
      if (!resp.success) {
        await Swal.fire({ icon: 'error', title: 'Failed', text: resp.message || 'Failed to create shift bill' });
        return;
      }
      const data = resp.data ?? {};
      setSuccess({
        control_number: data.control_number ?? data.control_num ?? data.contr_num,
        payment_ref: data.payment_ref,
        bill_amount: data.bill_amount ?? billAmount,
        message: resp.message,
      });
      onCreated?.(resp);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <Modal
        open={isOpen && !success}
        onCancel={closeModal}
        footer={null}
        width={640}
        centered
        destroyOnHidden
        title={null}
        closable={false}
        maskClosable={!loading && !submitting}
        keyboard={!loading && !submitting}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Shift Collection Billing" onClose={closeModal} />
        <div className="px-6 py-5">
          {loading ? (
            <p className="py-8 text-center text-sm text-slate-500">Loading shift billing details…</p>
          ) : (
            <div className="space-y-5">
              <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div className="min-w-0 py-1.5">
                  <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Shift Name
                  </span>
                  <div className="mt-0.5 text-sm font-medium text-black">{shiftName || <span className="text-slate-400">N/A</span>}</div>
                </div>
                <div className="min-w-0 py-1.5">
                  <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Shift Date
                  </span>
                  <div className="mt-0.5 text-sm font-medium text-black">{shiftDate || <span className="text-slate-400">N/A</span>}</div>
                </div>
                <div className="min-w-0 py-1.5">
                  <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Bill Description
                  </span>
                  <div className="mt-0.5 text-sm font-medium text-black">{billDescription}</div>
                </div>
                <div className="min-w-0 py-1.5">
                  <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                    Bill Amount
                  </span>
                  <div className="mt-0.5 font-mono text-sm font-semibold text-black">{formatMoney(billAmount)}</div>
                </div>
              </div>
              <div className="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                <Button onClick={closeModal} disabled={submitting}>
                  Close
                </Button>
                <Button
                  type="primary"
                  loading={submitting}
                  disabled={!billAmount}
                  onClick={handleSubmit}
                  style={{ backgroundColor: BRAND, borderColor: BRAND }}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.backgroundColor = BRAND_DARK;
                    e.currentTarget.style.borderColor = BRAND_DARK;
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.backgroundColor = BRAND;
                    e.currentTarget.style.borderColor = BRAND;
                  }}
                >
                  Submit
                </Button>
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
        <BrandModalHeader title="Shift Bill Created" onClose={closeModal} />
        <div className="px-6 py-6 text-center">
          <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-50 text-green-600">
            <CheckCircle2 size={28} />
          </div>
          <h3 className="text-base font-semibold text-slate-900">Control number request sent</h3>
          {success?.message && <p className="mt-1 text-xs text-slate-500">{success.message}</p>}
          <div className="mt-4 space-y-2 rounded-md border border-slate-200 bg-slate-50 p-4 text-left text-sm">
            <div>
              <span className="font-semibold" style={{ color: BRAND }}>
                Control Number:
              </span>{' '}
              <span className="font-mono">{success?.control_number || EMPTY_VALUE}</span>
              {success?.control_number ? (
                <button
                  type="button"
                  className="ml-2 inline-flex items-center text-xs font-medium text-[#962E32] hover:underline"
                  onClick={() => navigator.clipboard?.writeText(String(success.control_number))}
                >
                  <Copy size={12} className="mr-1" />
                  Copy
                </button>
              ) : null}
            </div>
            <div>
              <span className="font-semibold" style={{ color: BRAND }}>
                Payment Ref:
              </span>{' '}
              <span className="font-mono">{success?.payment_ref || EMPTY_VALUE}</span>
            </div>
            <div>
              <span className="font-semibold" style={{ color: BRAND }}>
                Amount:
              </span>{' '}
              {success?.bill_amount != null ? formatMoney(success.bill_amount) : EMPTY_VALUE}
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

CreateEndOfShiftBillModal.propTypes = {
  isOpen: PropTypes.bool.isRequired,
  shiftId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
  shiftDate: PropTypes.string,
  onClose: PropTypes.func,
  onCreated: PropTypes.func,
};
