import { useEffect, useState } from 'react';
import { Megaphone, X } from 'lucide-react';
import { Button, DatePicker, Form, Input, InputNumber, Modal, Select } from 'antd';
import PropTypes from 'prop-types';
import Swal from 'sweetalert2';

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

const advertTypeOptions = [
  { value: 'Digital Billboard', label: 'Digital Billboard' },
  { value: 'Static Billboard', label: 'Static Billboard' },
  { value: 'Banner', label: 'Banner' },
  { value: 'Other', label: 'Other' },
];

export default function CreateAdvertBillModal({ isOpen, onClose, onCreated }) {
  const [submitting, setSubmitting] = useState(false);
  const [form] = Form.useForm();
  const payerNamePreview = Form.useWatch('payer_name', form);

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
        email: values.email.trim(),
        phone_number: values.phone_number.trim(),
        advertisement_type: values.advertisement_type,
        bill_amount: Number(values.bill_amount),
        advert_start_date: values.advert_start_date.format('YYYY-MM-DD'),
        advert_end_date: values.advert_end_date.format('YYYY-MM-DD'),
      };

      const resp = await apiService.createAdvertBill(payload);
      if (!resp.success) {
        await Swal.fire({ icon: 'error', title: 'Failed', text: resp.message || 'Failed to create advert bill' });
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
      maskClosable={!submitting}
      keyboard={!submitting}
      className="brand-modal"
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <BrandModalHeader title="Create Advert Bill" onClose={closeModal} />
      <Form
        id="create-advert-bill-form"
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
            Advertisement Information
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
              name="advertisement_type"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Advertisement Type</span>}
              rules={[{ required: true, message: 'Advertisement type is required' }]}
            >
              <Select
                size="large"
                placeholder="Select advertisement type"
                options={advertTypeOptions}
                disabled={submitting}
              />
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
              name="advert_start_date"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Start Date</span>}
              rules={[{ required: true, message: 'Start date is required' }]}
            >
              <DatePicker size="large" className="w-full" disabled={submitting} />
            </Form.Item>

            <Form.Item
              name="advert_end_date"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>End Date</span>}
              rules={[{ required: true, message: 'End date is required' }]}
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

            <div className="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500">
              The bill description will follow the Bridge Core format:
              <div className="mt-1 font-medium text-black">
                Advertisement Charges for {payerNamePreview || EMPTY_VALUE}
              </div>
            </div>
          </div>
        </div>

        <div className="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
          <Button
            type="primary"
            htmlType="submit"
            loading={submitting}
            icon={<Megaphone size={14} />}
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
            Create Advert Bill
          </Button>
          <Button onClick={closeModal} disabled={submitting}>Close</Button>
        </div>
      </Form>
    </Modal>
  );
}

CreateAdvertBillModal.propTypes = {
  isOpen: PropTypes.bool.isRequired,
  onClose: PropTypes.func,
  onCreated: PropTypes.func,
};

