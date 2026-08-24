import { useState } from 'react';
import { FileText, RotateCcw } from 'lucide-react';
import { App, Button, Form, Input, Modal } from 'antd';
import PropTypes from 'prop-types';
import Swal from 'sweetalert2';

import { apiService } from '../../../../services/api.jsx';

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

BrandModalHeader.propTypes = {
  title: PropTypes.string.isRequired,
  onClose: PropTypes.func.isRequired,
};

const Field = ({ label, value }) => (
  <div className="min-w-0 py-1.5">
    <span className="block text-xs font-semibold tracking-[0.01em]" style={{ color: BRAND }}>
      {label}
    </span>
    <div className="mt-0.5 text-sm font-medium text-black">
      {value != null && value !== '' ? value : <span className="text-slate-400">N/A</span>}
    </div>
  </div>
);

Field.propTypes = {
  label: PropTypes.string.isRequired,
  value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
};

function getBillPermissions(bill) {
  const isCancelled = bill?.is_cancelled_display;
  const status = isCancelled ? 'CANCELLED' : String(bill?.status_display || '').toUpperCase();
  const canCancel = !isCancelled && status !== 'PAID' && bill?.can_cancel !== false;
  const canReuse =
    !isCancelled && (bill?.can_reuse || (status === 'PAID' && bill?.receipt_display && bill.receipt_display !== '-'));

  return { isCancelled, status, canCancel, canReuse };
}

export default function EndOfShiftBillViewModal({
  open,
  bill = null,
  onClose,
  onUpdated = undefined,
  onOpenOrderForm = undefined,
}) {
  const { message } = App.useApp();
  const [showCancelModal, setShowCancelModal] = useState(false);
  const [cancelSubmitting, setCancelSubmitting] = useState(false);
  const [cancelForm] = Form.useForm();

  if (!bill) return null;

  const { isCancelled, status, canCancel, canReuse } = getBillPermissions(bill);
  const statusLabel = isCancelled ? 'Cancelled' : status === 'PAID' ? 'Paid' : status === 'PENDING' ? 'Pending' : status || 'N/A';

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
    if (!bill?.id || !canCancel) return;

    setCancelSubmitting(true);
    try {
      const resp = await apiService.cancelEndOfShiftBill({
        id: bill.id,
        cancel_reason: values.cancel_reason?.trim() || 'Cancelled from BMS',
      });
      if (!resp.success) {
        message.error(resp.message || 'Failed to cancel bill');
        return;
      }
      message.success(resp.message || 'Bill cancelled');
      setShowCancelModal(false);
      cancelForm.resetFields();
      onUpdated?.();
      onClose?.();
    } catch {
      message.error('Failed to cancel bill');
    } finally {
      setCancelSubmitting(false);
    }
  };

  const handleReuse = async () => {
    const resp = await apiService.reuseEndOfShiftBill({ id: bill.id });
    if (!resp.success) {
      await Swal.fire({ icon: 'error', title: 'Failed', text: resp.message || 'Failed to reuse bill' });
      return;
    }
    await Swal.fire({ icon: 'success', title: 'Reuse requested', timer: 1500, showConfirmButton: false });
    onUpdated?.();
    onClose?.();
  };

  const handleOrderForm = () => {
    handleClose();
    onOpenOrderForm?.(bill);
  };

  return (
    <>
    <Modal
      open={open}
      onCancel={handleClose}
      footer={null}
      width={640}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      className="brand-modal"
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <BrandModalHeader title="Shift Bill Details" onClose={handleClose} />

      <div className="px-6 py-5">
        <h4
          className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
          style={{ color: BRAND }}
        >
          Bill Information
        </h4>
        <div className="grid grid-cols-1 gap-x-5 md:grid-cols-2">
          <Field label="Shift Date" value={bill.shift_date_display} />
          <Field label="Shift" value={bill.shift_name_display} />
          <Field label="Control Number" value={bill.control_display} />
          <Field label="Receipt" value={bill.receipt_display} />
          <Field label="Bank Receipt" value={bill.bank_receipt_display} />
          <Field label="Amount" value={bill.amount_display} />
          <Field label="Status" value={statusLabel} />
        </div>

        <div className="mt-6 flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white pt-3">
          <Button
            danger
            disabled={!canCancel || cancelSubmitting}
            onClick={() => {
              cancelForm.resetFields();
              setShowCancelModal(true);
            }}
            className="!inline-flex items-center gap-1.5"
          >
            Cancel Bill
          </Button>
          <Button
            disabled={!canReuse}
            onClick={handleReuse}
            className="!inline-flex items-center gap-1.5"
            icon={<RotateCcw size={14} />}
          >
            Reuse
          </Button>
          <Button
            onClick={handleOrderForm}
            className="!inline-flex items-center gap-1.5"
            style={{ backgroundColor: BRAND, borderColor: BRAND, color: '#fff' }}
            onMouseEnter={(e) => {
              e.currentTarget.style.backgroundColor = BRAND_DARK;
              e.currentTarget.style.borderColor = BRAND_DARK;
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.backgroundColor = BRAND;
              e.currentTarget.style.borderColor = BRAND;
            }}
            icon={<FileText size={14} />}
          >
            Order Form
          </Button>
          <Button onClick={handleClose} disabled={cancelSubmitting}>Close</Button>
        </div>
      </div>
    </Modal>

    <Modal
      open={showCancelModal}
      onCancel={closeCancelModal}
      footer={null}
      width={480}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      maskClosable={!cancelSubmitting}
      keyboard={!cancelSubmitting}
      className="brand-modal"
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
      zIndex={1100}
    >
      <BrandModalHeader title="Cancel Bill" onClose={closeCancelModal} />
      <Form
        form={cancelForm}
        layout="vertical"
        onFinish={handleConfirmCancel}
        requiredMark={false}
      >
        <div className="px-6 py-5">
          <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3">
            <p className="text-sm text-amber-900">
              This will cancel the shift bill. Continue?
            </p>
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

EndOfShiftBillViewModal.propTypes = {
  open: PropTypes.bool.isRequired,
  bill: PropTypes.object,
  onClose: PropTypes.func.isRequired,
  onUpdated: PropTypes.func,
  onOpenOrderForm: PropTypes.func,
};
