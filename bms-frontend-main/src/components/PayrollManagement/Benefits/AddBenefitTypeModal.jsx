import { App, Button, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { payrollService } from '../../../services/payrollService.js';
import BenefitTypeFormFields from './BenefitTypeFormFields.jsx';
import { apiService } from '../../../services/api.jsx';

const toApiDate = (d) => {
  if (!d) return null;
  if (typeof d.format === 'function') return d.format('YYYY-MM-DD');
  return null;
};

const AddBenefitTypeModal = ({ open, onClose, onSaved = null }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [saving, setSaving] = useState(false);
  const [employmentTypes, setEmploymentTypes] = useState([]);
  const [employmentTypesLoading, setEmploymentTypesLoading] = useState(false);
  const [departments, setDepartments] = useState([]);
  const [departmentsLoading, setDepartmentsLoading] = useState(false);

  useEffect(() => {
    if (!open) return;

    const fetchEmploymentTypes = async () => {
      setEmploymentTypesLoading(true);
      try {
        const response = await apiService.getActiveBridgeEmploymentTypes();
        if (response?.success && Array.isArray(response.data)) {
          setEmploymentTypes(response.data);
        } else if (response?.success && response?.data && Array.isArray(response.data?.items)) {
          setEmploymentTypes(response.data.items);
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

  useEffect(() => {
    if (!open) return;

    const fetchDepartments = async () => {
      setDepartmentsLoading(true);
      try {
        const response = await apiService.getActiveBmsDepartments();
        if (response?.success && Array.isArray(response.data)) {
          setDepartments(response.data);
        } else {
          setDepartments([]);
        }
      } catch (e) {
        setDepartments([]);
      } finally {
        setDepartmentsLoading(false);
      }
    };

    fetchDepartments();
  }, [open]);

  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      const payload = {
        benefit_name: values.benefit_name,
        benefit_code: values.benefit_code,
        calculation_type: values.calculation_type,
        calculation_value: values.calculation_value != null && values.calculation_value !== '' ? String(values.calculation_value) : null,
        is_taxable: values.is_taxable ? 1 : 0,
        is_active: values.is_active ? 1 : 0,
        // contract_type must send the ID (emptype_id)
        contract_type: values.contract_type ?? null,
        department_section: values.department_section ?? null,
        job_title_position: values.job_title_position ?? null,
        start_date: toApiDate(values.start_date),
        end_date: toApiDate(values.end_date),
      };

      const response = await payrollService.createBenefitType(payload);

      if (!response?.success) {
        message.error(response?.message || 'Could not save benefit type.');
        return;
      }

      message.success(response?.message || 'Benefit type saved');
      onSaved?.();
    } catch (e) {
      if (e?.errorFields) return;
      message.error(e?.message || 'Failed to save benefit type');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      title="Add Benefit Type"
      open={open}
      onCancel={onClose}
      destroyOnHidden
      afterClose={() => form.resetFields()}
      footer={[
        <Button key="cancel" onClick={onClose} disabled={saving}>
          Close
        </Button>,
        <Button key="save" type="primary" onClick={save} loading={saving}>
          Add Benefit Type
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
          is_taxable: false,
          is_active: true,
        }}
      >
        <BenefitTypeFormFields
          disabled={false}
          employmentTypes={employmentTypes}
          employmentTypesLoading={employmentTypesLoading}
          departments={departments}
          departmentsLoading={departmentsLoading}
        />
      </Form>
    </Modal>
  );
};

AddBenefitTypeModal.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default AddBenefitTypeModal;

