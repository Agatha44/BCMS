import { App, Button, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { payrollService } from '../../../services/payrollService.js';
import DeductionTypeFormFields from './DeductionTypeFormFields.jsx';
import { apiService } from '../../../services/api.jsx';

const toApiDate = (d) => {
  if (!d) return null;
  // dayjs object in antd v5
  if (typeof d.format === 'function') return d.format('YYYY-MM-DD');
  return null;
};

const AddDeductionTypeModal = ({ open, onClose, onSaved = null }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [saving, setSaving] = useState(false);
  const [employmentTypes, setEmploymentTypes] = useState([]);
  const [employmentTypesLoading, setEmploymentTypesLoading] = useState(false);

  useEffect(() => {
    if (!open) return;

    const fetchEmploymentTypes = async () => {
      setEmploymentTypesLoading(true);
      try {
        const res = await apiService.getActiveBridgeEmploymentTypes();
        if (res?.success && Array.isArray(res.data)) {
          setEmploymentTypes(res.data);
        } else if (res?.success && res?.data && Array.isArray(res.data?.items)) {
          setEmploymentTypes(res.data.items);
        } else {
          setEmploymentTypes([]);
        }
      } catch (e) {
        setEmploymentTypes([]);
      } finally {
        setEmploymentTypesLoading(false);
      }
    };

    fetchEmploymentTypes();
  }, [open]);

  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      const payload = {
        deduction_name: values.deduction_name,
        deduction_code: values.deduction_code,
        calculation_type: values.calculation_type,
        calculation_value:
          values.calculation_value != null && values.calculation_value !== '' ? String(values.calculation_value) : null,
        employee_contribution_percentage:
          values.employee_contribution_percentage != null && values.employee_contribution_percentage !== ''
            ? String(values.employee_contribution_percentage)
            : null,
        employer_contribution_percentage:
          values.employer_contribution_percentage != null && values.employer_contribution_percentage !== ''
            ? String(values.employer_contribution_percentage)
            : null,
        is_before_tax: values.is_before_tax ? 1 : 0,
        is_mandatory: values.is_mandatory ? 1 : 0,
        is_active: values.is_active ? 1 : 0,
        // contract_type must send the ID (emptype_id)
        contract_type: values.contract_type ?? null,
        start_date: toApiDate(values.start_date),
        end_date: toApiDate(values.end_date),
      };

      const res = await payrollService.createDeductionType(payload);

      if (!res?.success) {
        message.error(res?.message || 'Could not save deduction type.');
        return;
      }

      message.success(res.message || 'Deduction type saved');
      onSaved?.();
    } catch (e) {
      if (e?.errorFields) return; // antd validation
      message.error(e?.message || 'Failed to save deduction type');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      title="Add Deduction Type"
      open={open}
      onCancel={onClose}
      destroyOnHidden
      afterClose={() => form.resetFields()}
      footer={[
        <Button key="cancel" onClick={onClose} disabled={saving}>
          Close
        </Button>,
        <Button key="save" type="primary" onClick={save} loading={saving}>
          Add Deduction Type
        </Button>,
      ]}
      width={1200}
      styles={{ body: { maxHeight: 'none', overflow: 'visible', paddingTop: 16 } }}
    >
      <Form
        form={form}
        layout="vertical"
        initialValues={{
          calculation_type: 'fixed',
          is_before_tax: false,
          is_mandatory: false,
          is_active: true,
        }}
      >
        <DeductionTypeFormFields
          disabled={false}
          employmentTypes={employmentTypes}
          employmentTypesLoading={employmentTypesLoading}
        />
      </Form>
    </Modal>
  );
};

AddDeductionTypeModal.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default AddDeductionTypeModal;

