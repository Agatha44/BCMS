import { useEffect, useMemo, useState } from 'react';
import { ArrowRightLeft, Save } from 'lucide-react';
import { Button, Form, Modal, Select } from 'antd';
import Swal from 'sweetalert2';

import BrandModalHeader from '../sod/components/BrandModalHeader.jsx';
import BmsDatePicker from '../../../common/components/forms/BmsDatePicker.jsx';
import MoneyText from '../../../common/components/MoneyText.jsx';
import { apiService } from '../../../services/api.jsx';
import { extractApiList, extractShiftList } from '../../../common/utils/apiList.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';

const SectionHeading = ({ children }) => (
  <h4
    className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
    style={{ color: BRAND }}
  >
    {children}
  </h4>
);

const ReadOnlyField = ({ label, value }) => (
  <div className="min-w-0 py-1.5">
    <span
      className="block text-xs font-semibold tracking-[0.01em]"
      style={{ color: BRAND }}
    >
      {label}
    </span>
    <div className="mt-0.5 text-sm font-medium text-black">
      {value != null && value !== '' ? value : <span className="text-slate-400">N/A</span>}
    </div>
  </div>
);

const counterDateFromRecord = (record) => {
  if (!record?.open_counter) return null;
  const d = new Date(record.open_counter);
  if (Number.isNaN(d.getTime())) return null;
  return d.toISOString().slice(0, 10);
};

const unwrapShiftPayload = (response) => {
  const payload = response?.data ?? response;
  if (payload && typeof payload === 'object' && payload.status === 0) {
    throw new Error(payload.message || payload.error || 'Request failed');
  }
  return payload;
};

export default function ShiftTransferModal({ open, record, operator, onClose, onTransferred }) {
  const [form] = Form.useForm();
  const [shifts, setShifts] = useState([]);
  const [lanes, setLanes] = useState([]);
  const [preview, setPreview] = useState(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const currentShiftName = useMemo(() => {
    const shiftId = record?.shift_id;
    const match = shifts.find((s) => String(s.id) === String(shiftId));
    return match?.name ?? record?.name ?? record?.shift_name ?? null;
  }, [record, shifts]);

  useEffect(() => {
    if (!open) return;

    const loadLookups = async () => {
      try {
        const [shiftsRes, lanesRes] = await Promise.all([
          apiService.getEndOfShiftShifts(),
          apiService.getLanesList(),
        ]);
        if (shiftsRes?.success) setShifts(extractShiftList(shiftsRes));
        if (lanesRes?.success) setLanes(extractApiList(lanesRes.data));
      } catch {
        /* optional */
      }
    };

    loadLookups();
  }, [open]);

  useEffect(() => {
    if (!open || !record) return;

    form.setFieldsValue({
      user_id: record.user_id ?? operator?.value ?? null,
      counter_date: counterDateFromRecord(record),
      lane_id: record.lane_id ? String(record.lane_id) : undefined,
      shift_id: record.shift_id ? String(record.shift_id) : undefined,
      new_shift_id: undefined,
    });
    setPreview(null);
  }, [open, record, operator, form]);

  const handleClose = () => {
    if (submitting || previewLoading) return;
    form.resetFields();
    setPreview(null);
    onClose?.();
  };

  const buildPayload = () => {
    const values = form.getFieldsValue();
    return {
      user_id: Number(values.user_id),
      counter_date: values.counter_date,
      lane_id: Number(values.lane_id),
      shift_id: Number(values.shift_id),
    };
  };

  const handlePreview = async () => {
    try {
      await form.validateFields(['user_id', 'counter_date', 'lane_id', 'shift_id']);
    } catch {
      return;
    }

    setPreviewLoading(true);
    setPreview(null);
    try {
      const response = await apiService.getWrongShiftAmount(buildPayload());
      const payload = unwrapShiftPayload(response);
      setPreview(payload);
    } catch (error) {
      await Swal.fire({
        icon: 'error',
        title: 'Preview failed',
        text: error instanceof Error ? error.message : 'Unable to load shift amount.',
      });
    } finally {
      setPreviewLoading(false);
    }
  };

  const handleSubmit = async () => {
    try {
      await form.validateFields();
    } catch {
      return;
    }

    const values = form.getFieldsValue();
    const payload = {
      ...buildPayload(),
      new_shift_id: Number(values.new_shift_id),
    };

    if (payload.shift_id === payload.new_shift_id) {
      await Swal.fire({
        icon: 'warning',
        title: 'Same shift selected',
        text: 'Choose a different target shift to transfer this record.',
      });
      return;
    }

    const confirm = await Swal.fire({
      icon: 'question',
      title: 'Apply shift transfer?',
      html: preview
        ? `This will move <strong>${preview.transaction_count ?? 0}</strong> transaction(s) totaling <strong>TZS ${Number(preview.total_amount ?? 0).toLocaleString()}</strong> to the selected shift.`
        : 'Transactions in this counter session will be moved to the selected shift.',
      showCancelButton: true,
      confirmButtonText: 'Transfer',
      confirmButtonColor: BRAND,
    });

    if (!confirm.isConfirmed) return;

    setSubmitting(true);
    try {
      const response = await apiService.updateWrongShift(payload);
      const result = unwrapShiftPayload(response);

      await Swal.fire({
        icon: 'success',
        title: 'Shift transferred',
        text:
          result?.message ||
          `Updated ${result?.number_of_transactions_updated ?? result?.toll_transactions_updated ?? 0} transaction(s).`,
        timer: 2500,
        showConfirmButton: false,
      });

      form.resetFields();
      setPreview(null);
      onTransferred?.();
    } catch (error) {
      await Swal.fire({
        icon: 'error',
        title: 'Transfer failed',
        text: error instanceof Error ? error.message : 'Unable to apply shift transfer.',
      });
    } finally {
      setSubmitting(false);
    }
  };

  if (!record) return null;

  return (
    <Modal
      open={open}
      onCancel={handleClose}
      footer={null}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      width={640}
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <BrandModalHeader title="Shift Record Transfer" onClose={handleClose} />

      <Form form={form} layout="vertical" className="px-6 py-5">
        <SectionHeading>Session Details</SectionHeading>
        <div className="mb-4 grid gap-x-6 sm:grid-cols-2">
          <ReadOnlyField label="Operator" value={operator?.label} />
          <ReadOnlyField label="Current Shift" value={currentShiftName} />
        </div>

        <div className="grid gap-x-4 sm:grid-cols-2">
          <Form.Item name="user_id" hidden>
            <input type="hidden" />
          </Form.Item>

          <Form.Item
            label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Counter Date</span>}
            name="counter_date"
            rules={[{ required: true, message: 'Counter date is required' }]}
          >
            <BmsDatePicker size="large" />
          </Form.Item>

          <Form.Item
            label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Lane</span>}
            name="lane_id"
            rules={[{ required: true, message: 'Lane is required' }]}
          >
            <Select
              placeholder="Select lane"
              options={lanes.map((lane) => ({
                value: String(lane.id),
                label: lane.lane_no ?? lane.name ?? lane.id,
              }))}
            />
          </Form.Item>

          <Form.Item
            label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Current Shift</span>}
            name="shift_id"
            rules={[{ required: true, message: 'Current shift is required' }]}
          >
            <Select
              placeholder="Select current shift"
              options={shifts.map((shift) => ({
                value: String(shift.id),
                label: shift.name ?? shift.id,
              }))}
            />
          </Form.Item>

          <Form.Item
            label={<span className="text-xs font-semibold" style={{ color: BRAND }}>Transfer To Shift</span>}
            name="new_shift_id"
            rules={[{ required: true, message: 'Target shift is required' }]}
          >
            <Select
              placeholder="Select target shift"
              options={shifts.map((shift) => ({
                value: String(shift.id),
                label: shift.name ?? shift.id,
              }))}
            />
          </Form.Item>
        </div>

        {preview ? (
          <div className="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
            <SectionHeading>Transfer Preview</SectionHeading>
            <div className="grid gap-x-6 sm:grid-cols-2">
              <ReadOnlyField
                label="Transaction Count"
                value={preview.transaction_count ?? 0}
              />
              <ReadOnlyField
                label="Total Amount"
                value={<MoneyText value={preview.total_amount ?? 0} />}
              />
            </div>
          </div>
        ) : null}
      </Form>

      <div className="flex items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-3">
        <Button
          icon={<ArrowRightLeft size={16} />}
          loading={previewLoading}
          onClick={handlePreview}
        >
          Preview Amount
        </Button>
        <Button
          type="primary"
          icon={<Save size={16} />}
          loading={submitting}
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
          Transfer Shift
        </Button>
        <Button onClick={handleClose}>Close</Button>
      </div>
    </Modal>
  );
}
