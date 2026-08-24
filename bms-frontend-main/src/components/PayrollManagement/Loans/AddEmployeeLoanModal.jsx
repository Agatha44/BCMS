import { App, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import { useCallback, useEffect, useState } from 'react';
import { extractArrayFromResponse } from '../../../common/utils/employeeUtils.jsx';
import { apiService } from '../../../services/api.jsx';
import { payrollService } from '../../../services/payrollService.js';
import {
  EMPLOYEE_LOAN_MODAL_STYLES,
  EMPLOYEE_LOAN_MODAL_WIDTH,
} from './employeeLoanConstants.js';
import EmployeeLoanFormFields from './EmployeeLoanFormFields.jsx';
import EmployeeLoanModalFooter from './EmployeeLoanModalFooter.jsx';
import { buildEmployeeLoanPayload, parseActiveLoanTypeOptions } from './employeeLoanUtils.js';
import { useEmployeeLoanForm } from './useEmployeeLoanForm.js';
import './employee-loan-form.css';

const AddEmployeeLoanModal = ({ open, onClose, onSaved = null }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [saving, setSaving] = useState(false);
  const [typeOptions, setTypeOptions] = useState([]);
  const [employees, setEmployees] = useState([]);
  const [listsLoading, setListsLoading] = useState(false);

  const { onLoanTypeChange, onRecalculate, resetLoanType } = useEmployeeLoanForm(form, {
    applyDefaultsOnTypeChange: true,
  });

  const loadReferenceData = useCallback(async () => {
    setListsLoading(true);
    try {
      const [empResponse, loanResponse] = await Promise.all([
        apiService.getActiveBridgeEmployees({ per_page: 1000 }),
        payrollService.getActiveLoanTypes(),
      ]);

      if (empResponse.success && empResponse.data) {
        const { items } = extractArrayFromResponse(empResponse.data);
        setEmployees(items || []);
      } else {
        setEmployees([]);
      }

      setTypeOptions(
        loanResponse?.success ? parseActiveLoanTypeOptions(loanResponse.data) : []
      );
    } catch (e) {
      setEmployees([]);
      setTypeOptions([]);
      message.error(e.message || 'Failed to load form data');
    } finally {
      setListsLoading(false);
    }
  }, [message]);

  useEffect(() => {
    if (open) loadReferenceData();
  }, [open, loadReferenceData]);

  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      const response = await payrollService.createEmployeeLoan(
        buildEmployeeLoanPayload(values, { isActive: values.is_active ? 1 : 0 })
      );

      if (!response?.success) {
        message.error(response?.message || 'Could not save employee loan.');
        return;
      }

      message.success(response?.message || 'Employee loan saved');
      onSaved?.();
    } catch (e) {
      if (e?.errorFields) return;
      message.error(e?.message || 'Failed to save employee loan');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      className="employee-loan-modal"
      title="Add Employee Loan"
      open={open}
      onCancel={onClose}
      destroyOnHidden
      afterClose={() => {
        form.resetFields();
        resetLoanType();
      }}
      width={EMPLOYEE_LOAN_MODAL_WIDTH}
      styles={EMPLOYEE_LOAN_MODAL_STYLES}
      footer={
        <EmployeeLoanModalFooter
          saving={saving}
          onClose={onClose}
          primaryLabel="Add Employee Loan"
          onPrimary={save}
        />
      }
    >
      <Form form={form} layout="vertical" requiredMark initialValues={{ is_active: true }}>
        <EmployeeLoanFormFields
          hideReferenceNumber
          loanTypeOptions={typeOptions}
          employees={employees}
          employeesLoading={listsLoading}
          onLoanTypeChange={onLoanTypeChange}
          onPrincipalAmountChange={onRecalculate}
          onRepaymentMonthsChange={onRecalculate}
          onEffectiveStartDateChange={onRecalculate}
        />
      </Form>
    </Modal>
  );
};

AddEmployeeLoanModal.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default AddEmployeeLoanModal;
