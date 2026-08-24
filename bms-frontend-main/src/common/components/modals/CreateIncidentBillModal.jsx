import { useState } from 'react';
import { ShieldAlert, X } from 'lucide-react';
import { Button, DatePicker, Form, Input, InputNumber, Modal, Select } from 'antd';
import PropTypes from 'prop-types';
import dayjs from 'dayjs';
import Swal from 'sweetalert2';

import { apiService } from '../../../services/api.jsx';
import { INCIDENT_TYPES, PAYMENT_TYPES } from '../../../pages/CollectionManagement/transactions/incidentFine.constants.js';

const BRAND = '#962E32';
const BRAND_DARK = '#7A2326';

const incidentTypeOptions = Object.entries(INCIDENT_TYPES).map(([value, label]) => ({
  value: Number(value),
  label,
}));

const paymentTypeOptions = Object.entries(PAYMENT_TYPES).map(([value, label]) => ({
  value: Number(value),
  label,
}));

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

export default function CreateIncidentBillModal({ isOpen, onClose, onCreated }) {
  const [submitting, setSubmitting] = useState(false);
  const [form] = Form.useForm();

  const closeModal = () => {
    if (submitting) return;
    onClose?.();
  };

  const handleAfterOpenChange = (open) => {
    if (!open) return;
    form.resetFields();
    form.setFieldsValue({ incident_date: dayjs() });
  };

  const handleSubmit = async (values) => {
    setSubmitting(true);
    try {
      const payload = {
        driver_name: values.driver_name.trim(),
        plate_number: values.plate_number.trim(),
        vehicle_owner: values.vehicle_owner.trim(),
        incident_date: values.incident_date.format('YYYY-MM-DD'),
        incident_nature: values.incident_nature,
        amount: Number(values.amount),
        phone_number: values.phone_number.trim(),
        police_rb: values.police_rb.trim(),
        payment_type: values.payment_type,
        payer_name: values.payer_name.trim(),
        email: values.email.trim(),
      };

      const resp = await apiService.createIncidentBill(payload);
      if (!resp.success) {
        await Swal.fire({ icon: 'error', title: 'Failed', text: resp.message || 'Failed to create incident bill' });
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
      width={760}
      centered
      destroyOnHidden
      afterOpenChange={handleAfterOpenChange}
      title={null}
      closable={false}
      maskClosable={!submitting}
      keyboard={!submitting}
      className="brand-modal"
      styles={{ body: { padding: 0 }, content: { padding: 0, overflow: 'hidden' } }}
    >
      <BrandModalHeader title="Create Incident Bill" onClose={closeModal} />
      <Form
        id="create-incident-bill-form"
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        requiredMark={false}
        className="px-6 py-5"
        initialValues={{ incident_date: dayjs() }}
      >
        <div className="mb-4">
          <h4
            className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
            style={{ color: BRAND }}
          >
            Incident Information
          </h4>

          <div className="grid grid-cols-1 gap-x-5 md:grid-cols-2">
            <Form.Item
              name="driver_name"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Driver Name</span>}
              rules={[{ required: true, message: 'Driver name is required' }]}
            >
              <Input size="large" placeholder="Driver full name" disabled={submitting} />
            </Form.Item>

            <Form.Item
              name="plate_number"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Plate Number</span>}
              rules={[{ required: true, message: 'Plate number is required' }]}
            >
              <Input size="large" placeholder="T123 ABC" disabled={submitting} />
            </Form.Item>

            <Form.Item
              name="vehicle_owner"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Vehicle Owner</span>}
              rules={[{ required: true, message: 'Vehicle owner is required' }]}
            >
              <Input size="large" placeholder="Owner name" disabled={submitting} />
            </Form.Item>

            <Form.Item
              name="incident_date"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Incident Date</span>}
              rules={[{ required: true, message: 'Incident date is required' }]}
            >
              <DatePicker size="large" className="w-full" disabled={submitting} disabledDate={(d) => d && d > dayjs().endOf('day')} />
            </Form.Item>

            <Form.Item
              name="incident_nature"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Incident Type</span>}
              rules={[{ required: true, message: 'Incident type is required' }]}
            >
              <Select size="large" placeholder="Select incident type" options={incidentTypeOptions} disabled={submitting} />
            </Form.Item>

            <Form.Item
              name="amount"
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
                placeholder="Enter fine amount"
                disabled={submitting}
                formatter={(value) => `${value ?? ''}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
                parser={(value) => (value || '').replace(/[^\d.]/g, '')}
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
              name="police_rb"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Police RB</span>}
              rules={[{ required: true, message: 'Police RB is required' }]}
            >
              <Input size="large" placeholder="Police report book reference" disabled={submitting} />
            </Form.Item>

            <Form.Item
              name="payment_type"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Paid By</span>}
              rules={[{ required: true, message: 'Paid by is required' }]}
            >
              <Select size="large" placeholder="Select payer type" options={paymentTypeOptions} disabled={submitting} />
            </Form.Item>

            <Form.Item
              name="payer_name"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Payer Name</span>}
              rules={[{ required: true, message: 'Payer name is required' }]}
            >
              <Input size="large" placeholder="Name of person paying" disabled={submitting} />
            </Form.Item>

            <Form.Item
              name="email"
              className="md:col-span-2"
              label={<span className="text-sm font-medium" style={{ color: BRAND }}>Email</span>}
              rules={[
                { required: true, message: 'Email is required' },
                { type: 'email', message: 'Enter a valid email address' },
              ]}
            >
              <Input size="large" placeholder="customer@example.com" disabled={submitting} />
            </Form.Item>
          </div>
        </div>

        <div className="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
          <Button
            type="primary"
            htmlType="submit"
            loading={submitting}
            icon={<ShieldAlert size={14} />}
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
            Create Incident Bill
          </Button>
          <Button onClick={closeModal} disabled={submitting}>Close</Button>
        </div>
      </Form>
    </Modal>
  );
}

CreateIncidentBillModal.propTypes = {
  isOpen: PropTypes.bool.isRequired,
  onClose: PropTypes.func,
  onCreated: PropTypes.func,
};
