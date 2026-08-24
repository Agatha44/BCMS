import { App, Button, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { payrollService } from '../../../services/payrollService.js';
import ArrearsReasonFormFields from './ArrearsReasonFormFields.jsx';

const toApiDate = (d) => {
  if (!d) return null;
  if (typeof d.format === 'function') return d.format('YYYY-MM-DD');
  return null;
};

const AddArrearsReasonModal = ({ open, onClose, onSaved = null }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [saving, setSaving] = useState(false);

  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      const payload = {
        reason_name: values.reason_name,
        reason_code: values.reason_code,
        is_taxable: Number(values.is_taxable) === 1 ? 1 : 0,
        is_active: values.is_active ? 1 : 0,
        start_date: toApiDate(values.start_date),
        end_date: toApiDate(values.end_date),
      };

      const res = await payrollService.createArrearsReason(payload);
      if (!res?.success) {
        message.error(res?.message || 'Could not save arrears reason.');
        return;
      }

      message.success(res?.message || 'Arrears reason saved');
      onSaved?.();
    } catch (e) {
      if (e?.errorFields) return;
      message.error(e?.message || 'Failed to save arrears reason');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      title="Add Arrears Reason"
      open={open}
      onCancel={onClose}
      destroyOnHidden
      afterClose={() => form.resetFields()}
      footer={[
        <Button key="cancel" onClick={onClose} disabled={saving}>
          Close
        </Button>,
        <Button key="save" type="primary" onClick={save} loading={saving}>
          Add Arrears Reason
        </Button>,
      ]}
      width={1200}
      styles={{ body: { maxHeight: 'none', overflow: 'visible', paddingTop: 16 } }}
    >
      <Form
        form={form}
        layout="vertical"
        initialValues={{
          is_taxable: 1,
          is_active: true,
        }}
      >
        <ArrearsReasonFormFields disabled={false} />
      </Form>
    </Modal>
  );
};

AddArrearsReasonModal.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default AddArrearsReasonModal;

