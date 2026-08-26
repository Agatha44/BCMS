import { useEffect, useState } from 'react';
import { X } from 'lucide-react';
import { Button, DatePicker, Form, Input, InputNumber, Modal } from 'antd';
import PropTypes from 'prop-types';
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
      <X size={16} />
    </button>
  </div>
);

BrandModalHeader.propTypes = {
  title: PropTypes.string.isRequired,
  onClose: PropTypes.func.isRequired,
};

export default function CreateEventBillModal({ isOpen, onClose, onCreated }) {
  const [submitting, setSubmitting] = useState(false);
  const [form] = Form.useForm();

  const closeModal = () => {
    if (submitting) return;
    form.resetFields();
    onClose?.();
  };

  useEffect(() => {
    if (!isOpen) return;
    form.resetFields();
  }, [form, isOpen]);

  const handleSubmit = async (values) => {
    setSubmitting(true);
    try {
      const payload = {
        payer_name: values.payer_name.trim(),
        receiver_name: values.receiver_name?.trim() || undefined,
        email: values.email.trim(),
        phone_number: values.phone_number.trim(),
        event_date: values.event_date.format('YYYY-MM-DD'),
        event_description: values.event_description.trim(),
        bill_amount: Number(values.bill_amount),
      };

      const resp = await apiService.createEventBill(payload);
      if (!resp.success) {
        await Swal.fire({ icon: 'error', title: 'Failed', text: resp.message || 'Failed to create event bill' });
        return;
      }

      onCreated?.(resp);
      closeModal();
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Modal
      open={isOpen}
      onCancel={closeModal}
      footer={null}
      width={680}
      centered
      destroyOnHidden
      title={null}
      closable={false}
      maskClosable={false}
      keyboard={false}
      className="brand-modal"
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <BrandModalHeader title="Bill" onClose={closeModal} />
      <Form
        id="create-event-bill-form"
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        requiredMark={false}
        className="px-6 py-5"
      >
        <div className="mb-4">
          <h4
            className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
            style={{ color: BRAND }}
          >
            Event Information
          </h4>

          <div className="grid grid-cols-1 gap-x-5 md:grid-cols-2">
            <Form.Item
              name="payer_name"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Payer Name</span>}
              rules={[{ required: true, message: 'Payer name is required' }]}
            >
              <Input size="large" placeholder="Customer or company name" disabled={submitting} />
            </Form.Item>

            <Form.Item
              name="receiver_name"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Receiver Name</span>}
            >
              <Input size="large" placeholder="Optional receiver name" disabled={submitting} />
            </Form.Item>

            <Form.Item
              name="phone_number"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Phone Number</span>}
              rules={[{ required: true, message: 'Phone number is required' }]}
            >
              <Input size="large" placeholder="0712345678" disabled={submitting} />
            </Form.Item>

            <Form.Item
              name="email"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Email</span>}
              rules={[
                { required: true, message: 'Email is required' },
                { type: 'email', message: 'Enter a valid email address' },
              ]}
            >
              <Input size="large" placeholder="customer@example.com" disabled={submitting} />
            </Form.Item>

            <Form.Item
              name="event_date"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Event Date</span>}
              rules={[{ required: true, message: 'Event date is required' }]}
            >
              <DatePicker size="large" className="w-full" disabled={submitting} />
            </Form.Item>

            <Form.Item
              name="bill_amount"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Amount (TZS)</span>}
              rules={[
                { required: true, message: 'Amount is required' },
                { type: 'number', min: 1, message: 'Amount must be at least 1' },
              ]}
            >
              <InputNumber
                size="large"
                min={1}
                step={1000}
                style={{ width: '100%' }}
                placeholder="Enter bill amount"
                disabled={submitting}
                formatter={(value) => `${value ?? ''}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
                parser={(value) => (value || '').replace(/[^\d.]/g, '')}
              />
            </Form.Item>

            <Form.Item
              name="event_description"
              className="md:col-span-2"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Event Description</span>}
              rules={[{ required: true, message: 'Event description is required' }]}
            >
              <Input.TextArea
                rows={3}
                placeholder="Describe the event or service being billed"
                disabled={submitting}
              />
            </Form.Item>
          </div>
        </div>

        <div className="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
          <Button
            type="primary"
            htmlType="submit"
            loading={submitting}
            style={{ backgroundColor: BRAND, borderColor: BRAND }}
            onMouseEnter={(e) => {
              if (!e.currentTarget.disabled) {
                e.currentTarget.style.backgroundColor = BRAND_DARK;
                e.currentTarget.style.borderColor = BRAND_DARK;
              }
            }}
            onMouseLeave={(e) => {
              if (!e.currentTarget.disabled) {
                e.currentTarget.style.backgroundColor = BRAND;
                e.currentTarget.style.borderColor = BRAND;
              }
            }}
          >
            Bill
          </Button>
          <Button onClick={closeModal} disabled={submitting}>Close</Button>
        </div>
      </Form>
    </Modal>
  );
}

CreateEventBillModal.propTypes = {
  isOpen: PropTypes.bool.isRequired,
  onClose: PropTypes.func,
  onCreated: PropTypes.func,
};
