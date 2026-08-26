import { useEffect, useState } from 'react';
import { X } from 'lucide-react';
import { Button, Form, Input, InputNumber, Modal } from 'antd';
import PropTypes from 'prop-types';
import Swal from 'sweetalert2';

import { overloadFineService } from '../../../services/overloadFineService.js';

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

const fieldLabel = (text) => (
  <span className="text-sm font-medium" style={{ color: BRAND }}>
    {text}
  </span>
);

export default function CreateOverloadChargeModal({ isOpen, onClose, onCreated }) {
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
    form.setFieldsValue({ bill_amount: 0 });
  }, [form, isOpen]);

  const handleSubmit = async (values) => {
    setSubmitting(true);
    try {
      const payload = {
        first_name: values.first_name.trim(),
        middle_name: values.middle_name?.trim() || '',
        surname: values.surname.trim(),
        pyr_cell_num: values.pyr_cell_num.trim(),
        pyr_email: values.pyr_email?.trim() || '',
        tin_number: values.tin_number?.trim() || '',
        bill_amount: Number(values.bill_amount),
        ticket_num: values.ticket_num?.trim() || '',
        vehicle_num: String(values.vehicle_num || '').trim().toUpperCase(),
        bill_desc: values.bill_desc?.trim() || '',
      };

      await overloadFineService.create(payload);
      onCreated?.();
      closeModal();
    } catch (error) {
      await Swal.fire({
        icon: 'error',
        title: 'Failed',
        text: error?.message || 'Failed to create overload charge',
      });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Modal
      open={isOpen}
      onCancel={closeModal}
      footer={null}
      width={720}
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
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        requiredMark={false}
        className="px-6 py-5"
        initialValues={{ bill_amount: 0 }}
      >
        <h4
          className="mb-3 border-b border-slate-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.12em]"
          style={{ color: BRAND }}
        >
          Charge Information
        </h4>

        <div className="grid grid-cols-1 gap-x-5 md:grid-cols-2">
          <Form.Item
            name="first_name"
            label={fieldLabel('First Name')}
            rules={[{ required: true, message: 'First name is required' }]}
          >
            <Input size="large" placeholder="First name" disabled={submitting} />
          </Form.Item>

          <Form.Item name="middle_name" label={fieldLabel('Middle Name')}>
            <Input size="large" placeholder="Middle name" disabled={submitting} />
          </Form.Item>

          <Form.Item
            name="surname"
            label={fieldLabel('Surname')}
            rules={[{ required: true, message: 'Surname is required' }]}
          >
            <Input size="large" placeholder="Surname" disabled={submitting} />
          </Form.Item>

          <Form.Item
            name="pyr_cell_num"
            label={fieldLabel('Phone Number')}
            rules={[{ required: true, message: 'Phone number is required' }]}
          >
            <Input size="large" placeholder="255712345678" disabled={submitting} />
          </Form.Item>

          <Form.Item
            name="pyr_email"
            label={fieldLabel('Email')}
            rules={[{ type: 'email', message: 'Enter a valid email address' }]}
          >
            <Input size="large" placeholder="customer@example.com" disabled={submitting} />
          </Form.Item>

          <Form.Item name="tin_number" label={fieldLabel('TIN Number')}>
            <Input size="large" placeholder="TIN number" disabled={submitting} />
          </Form.Item>

          <Form.Item
            name="bill_amount"
            label={fieldLabel('Charge Amount')}
            rules={[
              { required: true, message: 'Charge amount is required' },
              { type: 'number', min: 0, message: 'Amount must be zero or greater' },
            ]}
          >
            <InputNumber
              size="large"
              min={0}
              step={1000}
              style={{ width: '100%' }}
              placeholder="0"
              disabled={submitting}
              formatter={(value) => `${value ?? ''}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
              parser={(value) => (value || '').replace(/[^\d.]/g, '')}
            />
          </Form.Item>

          <Form.Item name="ticket_num" label={fieldLabel('Ticket Number')}>
            <Input size="large" placeholder="Ticket number" disabled={submitting} />
          </Form.Item>

          <Form.Item
            name="vehicle_num"
            label={fieldLabel('Vehicle Number')}
            rules={[{ required: true, message: 'Vehicle number is required' }]}
            normalize={(value) => (value ? String(value).toUpperCase() : value)}
            className="md:col-span-2"
          >
            <Input
              size="large"
              placeholder="Plate number"
              disabled={submitting}
              className="font-mono uppercase"
            />
          </Form.Item>

          <Form.Item
            name="bill_desc"
            label={fieldLabel('Charge Description')}
            className="md:col-span-2"
          >
            <Input.TextArea
              rows={3}
              placeholder="Describe the overload charge"
              disabled={submitting}
            />
          </Form.Item>
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
          <Button onClick={closeModal} disabled={submitting}>
            Close
          </Button>
        </div>
      </Form>
    </Modal>
  );
}

CreateOverloadChargeModal.propTypes = {
  isOpen: PropTypes.bool.isRequired,
  onClose: PropTypes.func,
  onCreated: PropTypes.func,
};
