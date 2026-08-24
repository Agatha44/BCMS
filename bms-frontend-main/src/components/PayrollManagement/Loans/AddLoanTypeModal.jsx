import { App, Button, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { apiService } from '../../../services/api.jsx';
import { payrollService } from '../../../services/payrollService.js';
import LoanTypeFormFields from './LoanTypeFormFields.jsx';

const toApiDate = (d) => {
  if (!d) return null;
  if (typeof d.format === 'function') return d.format('YYYY-MM-DD');
  return null;
};

const AddLoanTypeModal = ({ open, onClose, onSaved = null }) => {
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
        loan_name: values.loan_name,
        loan_code: values.loan_code,
        has_interest: values.has_interest ? 1 : 0,
        interest_percentage:
          values.has_interest && values.interest_percentage != null && values.interest_percentage !== ''
            ? String(values.interest_percentage)
            : null,
        interest_calculation_method: values.has_interest ? values.interest_calculation_method ?? 'flat' : null,
        minimum_loan_amount:
          values.minimum_loan_amount != null && values.minimum_loan_amount !== '' ? String(values.minimum_loan_amount) : null,
        maximum_loan_amount:
          values.maximum_loan_amount != null && values.maximum_loan_amount !== '' ? String(values.maximum_loan_amount) : null,
        minimum_repayment_months: values.minimum_repayment_months ?? null,
        maximum_repayment_months: values.maximum_repayment_months ?? null,
        is_active: values.is_active ? 1 : 0,
        priority: values.priority ?? null,
        contract_type: values.contract_type ?? null,
        department_section: values.department_section ?? null,
        job_title_position: values.job_title_position ?? null,
        start_date: toApiDate(values.start_date),
        end_date: toApiDate(values.end_date),
      };

      const response = await payrollService.createLoanType(payload);
      if (!response?.success) {
        message.error(response?.message || 'Could not save loan type.');
        return;
      }

      message.success(response?.message || 'Loan type saved');
      onSaved?.();
    } catch (e) {
      if (e?.errorFields) return;
      message.error(e?.message || 'Failed to save loan type');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      title="Add Loan Type"
      open={open}
      onCancel={onClose}
      destroyOnHidden
      afterClose={() => form.resetFields()}
      footer={[
        <Button key="cancel" onClick={onClose} disabled={saving}>
          Close
        </Button>,
        <Button key="save" type="primary" onClick={save} loading={saving} style={{ backgroundColor: '#962E32', borderColor: '#962E32' }}>
          Add Loan Type
        </Button>,
      ]}
      width={1200}
      styles={{ body: { maxHeight: 'none', overflow: 'visible', paddingTop: 16 } }}
    >
      <Form
        form={form}
        layout="vertical"
        initialValues={{
          has_interest: false,
          interest_calculation_method: 'flat',
          is_active: true,
          priority: 0,
        }}
      >
        <LoanTypeFormFields
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

AddLoanTypeModal.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default AddLoanTypeModal;

