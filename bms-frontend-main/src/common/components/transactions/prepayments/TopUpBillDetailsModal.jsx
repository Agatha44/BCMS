import { useMemo, useState } from 'react';
import { App, Button, Form, Input, Modal, Tag } from 'antd';
import { Ban, Copy, FileText } from 'lucide-react';

import BrandModalHeader from '../../../../pages/CollectionManagement/sod/components/BrandModalHeader.jsx';
import { apiService } from '../../../../services/api.jsx';
import { formatMoney } from '../../../utils/numberFormat.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';
const EMPTY_VALUE = 'N/A';

const formatDateTime = (value) => {
  if (value == null || value === '') return null;
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const ReadOnlyField = ({ label, value, mono = false }) => (
  <div className="min-w-0 py-1.5">
    <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
      {label}
    </span>
    <div className={`mt-0.5 text-sm font-medium text-black ${mono ? 'font-mono' : ''}`}>
      {value != null && value !== '' && value !== '—' ? (
        value
      ) : (
        <span className="font-normal text-slate-400">{EMPTY_VALUE}</span>
      )}
    </div>
  </div>
);

const SectionHeading = ({ children }) => (
  <h4
    className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
    style={{ color: BRAND }}
  >
    {children}
  </h4>
);

const billStatusTag = (bill) => {
  const raw = String(bill?.bill_status ?? '').trim();
  const lower = raw.toLowerCase();
  if (lower === 'paid') return { color: 'green', label: raw };
  if (lower === 'cancelled') return { color: 'red', label: raw };
  if (lower === 'expired') return { color: 'red', label: raw };
  if (lower === 'unpaid') return { color: 'gold', label: raw };
  return { color: 'default', label: raw || EMPTY_VALUE };
};

export const normalizeTopUpBill = (row = {}) => {
  const isCancelled =
    Number(row.is_cancelled) === 1 || String(row.bill_status || '').toUpperCase() === 'CANCELLED';
  const isPaid =
    !!row.trx_dt_tm ||
    !!row.psp_receipt_num ||
    String(row.bill_status || '').toUpperCase() === 'PAID';

  return {
    ...row,
    control_number: row.gepg_control_number || row.api_control_number || row.contr_num || null,
    can_cancel: row.can_cancel ?? (!isCancelled && !isPaid),
    can_print: row.can_print ?? isPaid,
  };
};

export default function TopUpBillDetailsModal({ isOpen, bill, onClose, onActionSuccess }) {
  const { message } = App.useApp();
  const [showCancelModal, setShowCancelModal] = useState(false);
  const [cancelSubmitting, setCancelSubmitting] = useState(false);
  const [cancelForm] = Form.useForm();

  const normalizedBill = useMemo(() => (bill ? normalizeTopUpBill(bill) : null), [bill]);

  const summary = useMemo(() => {
    if (!normalizedBill) return null;
    const status = billStatusTag(normalizedBill);
    return {
      controlNumber: normalizedBill.control_number,
      amount: formatMoney(normalizedBill.bill_amount),
      description: normalizedBill.bill_desc,
      status,
    };
  }, [normalizedBill]);

  const copyControlNumber = async () => {
    if (!summary?.controlNumber) return;
    try {
      await navigator.clipboard.writeText(String(summary.controlNumber));
      message.success('Control number copied');
    } catch {
      message.error('Could not copy');
    }
  };

  const handleClose = () => {
    if (cancelSubmitting) return;
    setShowCancelModal(false);
    cancelForm.resetFields();
    onClose?.();
  };

  const closeCancelModal = () => {
    if (cancelSubmitting) return;
    setShowCancelModal(false);
    cancelForm.resetFields();
  };

  const handleConfirmCancel = async (values) => {
    if (!normalizedBill?.id || !normalizedBill?.can_cancel) return;

    setCancelSubmitting(true);
    try {
      const resp = await apiService.cancelTopUpBill({
        id: normalizedBill.id,
        cancel_reason: values.cancel_reason?.trim() || 'Cancelled from BMS',
      });
      if (!resp?.success) {
        message.error(resp?.message || 'Could not cancel bill.');
        return;
      }
      message.success(resp?.message || 'Bill cancelled.');
      setShowCancelModal(false);
      cancelForm.resetFields();
      onActionSuccess?.(resp?.data || normalizedBill);
      handleClose();
    } catch (e) {
      message.error(e?.message || 'An error occurred while cancelling the bill.');
    } finally {
      setCancelSubmitting(false);
    }
  };

  const handleViewReceipt = () => {
    if (!normalizedBill?.id || !normalizedBill?.can_print) return;
    window.open(apiService.getPrepaymentReceiptUrl(normalizedBill.id), '_blank', 'noopener,noreferrer');
  };

  return (
    <>
      <Modal
        open={isOpen && !!normalizedBill}
        onCancel={handleClose}
        footer={null}
        width={680}
        centered
        destroyOnClose
        title={null}
        closable={false}
        maskClosable={!cancelSubmitting}
        keyboard={!cancelSubmitting}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      >
        <BrandModalHeader title="Prepayment Bill Details" onClose={handleClose} />

        {normalizedBill && summary && (
          <>
            <div className="max-h-[70vh] overflow-y-auto px-6 py-5">
              <div className="mb-5 rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <div>
                    <span
                      className="text-[11px] font-semibold uppercase tracking-[0.06em]"
                      style={{ color: BRAND }}
                    >
                      Control Number
                    </span>
                    <div className="mt-0.5 flex flex-wrap items-center gap-2">
                      <span className="font-mono text-sm font-semibold text-black">
                        {summary.controlNumber || EMPTY_VALUE}
                      </span>
                      {summary.controlNumber && (
                        <Button size="small" icon={<Copy size={12} />} onClick={copyControlNumber}>
                          Copy
                        </Button>
                      )}
                      <Tag color={summary.status.color} className="!m-0 text-xs">
                        {summary.status.label}
                      </Tag>
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <ReadOnlyField label="Bill Amount" value={summary.amount} mono />
                    <ReadOnlyField label="Bill ID" value={normalizedBill.id} mono />
                  </div>
                </div>
                {summary.description && (
                  <p className="mt-3 border-t border-slate-200 pt-2 text-sm text-slate-700">
                    {summary.description}
                  </p>
                )}
              </div>

              <SectionHeading>Account</SectionHeading>
              <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                <ReadOnlyField label="Account No" value={normalizedBill.account_no} mono />
                <ReadOnlyField label="Payer Name" value={normalizedBill.payer_name} />
                <ReadOnlyField label="TIN" value={normalizedBill.tin} mono />
                <ReadOnlyField label="Source" value={normalizedBill.source} />
              </div>

              <SectionHeading>Payment</SectionHeading>
              <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                <ReadOnlyField label="PSP Receipt" value={normalizedBill.psp_receipt_num} mono />
                <ReadOnlyField label="Receipt Number" value={normalizedBill.receipt_number} mono />
                <ReadOnlyField label="Payment Date" value={formatDateTime(normalizedBill.payment_date || normalizedBill.trx_dt_tm)} />
                <ReadOnlyField
                  label="Paid Amount"
                  value={
                    normalizedBill.paid_amt != null
                      ? formatMoney(normalizedBill.paid_amt)
                      : normalizedBill.bill_amount != null
                        ? formatMoney(normalizedBill.bill_amount)
                        : null
                  }
                  mono
                />
              </div>

              {(normalizedBill.cancel_reason || normalizedBill.bill_cancel_date) && (
                <>
                  <SectionHeading>Cancellation</SectionHeading>
                  <div className="mb-5 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                    <ReadOnlyField label="Cancel Reason" value={normalizedBill.cancel_reason} />
                    <ReadOnlyField label="Cancelled At" value={formatDateTime(normalizedBill.bill_cancel_date)} />
                  </div>
                </>
              )}

              <SectionHeading>Timeline</SectionHeading>
              <div className="grid grid-cols-1 gap-x-6 sm:grid-cols-3">
                <ReadOnlyField label="Generated" value={formatDateTime(normalizedBill.bill_gen_at)} />
                <ReadOnlyField label="Expires" value={formatDateTime(normalizedBill.bill_exp_dt)} />
                <ReadOnlyField label="Transaction Date" value={formatDateTime(normalizedBill.trx_dt_tm)} />
              </div>
            </div>

            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
              <Button
                danger
                icon={<Ban size={14} />}
                disabled={!normalizedBill.can_cancel || cancelSubmitting}
                onClick={() => {
                  cancelForm.resetFields();
                  setShowCancelModal(true);
                }}
                className="!border-red-300 !text-red-700 hover:!bg-red-50"
              >
                Cancel Bill
              </Button>
              <Button
                icon={<FileText size={14} />}
                disabled={!normalizedBill.can_print || cancelSubmitting}
                onClick={handleViewReceipt}
                style={{ borderColor: BRAND, color: BRAND }}
                className="hover:!bg-[#fff5f5]"
              >
                View Receipt
              </Button>
              <Button onClick={handleClose} disabled={cancelSubmitting}>
                Close
              </Button>
            </div>
          </>
        )}
      </Modal>

      <Modal
        open={showCancelModal}
        onCancel={closeCancelModal}
        footer={null}
        width={480}
        centered
        destroyOnClose
        title={null}
        closable={false}
        maskClosable={!cancelSubmitting}
        keyboard={!cancelSubmitting}
        className="brand-modal"
        styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
        zIndex={1100}
      >
        <BrandModalHeader title="Cancel Bill" onClose={closeCancelModal} />
        <Form form={cancelForm} layout="vertical" onFinish={handleConfirmCancel} requiredMark={false}>
          <div className="px-6 py-5">
            <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3">
              <p className="text-sm text-amber-900">This will cancel the prepayment bill. Continue?</p>
            </div>
            <Form.Item
              name="cancel_reason"
              label={
                <span className="text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
                  Cancellation Reason
                </span>
              }
              rules={[
                { required: true, message: 'Please provide a cancellation reason' },
                { whitespace: true, message: 'Please provide a cancellation reason' },
              ]}
            >
              <Input.TextArea
                rows={3}
                placeholder="Enter reason for cancellation"
                disabled={cancelSubmitting}
                className="!rounded-lg"
              />
            </Form.Item>
          </div>
          <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
            <Button
              type="primary"
              htmlType="submit"
              loading={cancelSubmitting}
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
              Yes, cancel
            </Button>
            <Button onClick={closeCancelModal} disabled={cancelSubmitting}>
              Close
            </Button>
          </div>
        </Form>
      </Modal>
    </>
  );
}
