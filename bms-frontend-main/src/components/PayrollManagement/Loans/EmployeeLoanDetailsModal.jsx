import { App, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { extractArrayFromResponse } from '../../../common/utils/employeeUtils.jsx';
import CollectionLoader from '../../../pages/CollectionManagement/components/CollectionLoader.jsx';
import { apiService } from '../../../services/api.jsx';
import { payrollService } from '../../../services/payrollService.js';
import {
  EMPLOYEE_LOAN_MODAL_STYLES,
  EMPLOYEE_LOAN_MODAL_WIDTH,
} from './employeeLoanConstants.js';
import EmployeeLoanFormFields from './EmployeeLoanFormFields.jsx';
import EmployeeLoanModalFooter from './EmployeeLoanModalFooter.jsx';
import {
  buildEmployeeLoanPayload,
  getEmployeeLoanId,
  mapEmployeeLoanToFormValues,
  parseActiveLoanTypeOptions,
} from './employeeLoanUtils.js';
import { useEmployeeLoanForm } from './useEmployeeLoanForm.js';
import './employee-loan-form.css';

const EmployeeLoanDetailsModal = ({ open, record = null, canEdit = false, onClose, onSaved = null }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(false);
  const [currentRecord, setCurrentRecord] = useState(null);
  const [typeOptions, setTypeOptions] = useState([]);
  const [employees, setEmployees] = useState([]);
  const [employeesLoading, setEmployeesLoading] = useState(false);

  const { loadLoanType, onLoanTypeChange, onRecalculate, resetLoanType } = useEmployeeLoanForm(form, {
    preserveTotalRepaid: true,
  });

  const loadReferenceData = useCallback(async () => {
    setEmployeesLoading(true);
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
      setEmployeesLoading(false);
    }
  }, [message]);

  useEffect(() => {
    if (open) loadReferenceData();
  }, [open, loadReferenceData]);

  useEffect(() => {
    if (!open) return;

    const employeeLoanId = getEmployeeLoanId(record);
    if (!employeeLoanId) {
      setCurrentRecord(record || null);
      return;
    }

    const loadRecord = async () => {
      setLoading(true);
      try {
        const response = await payrollService.getEmployeeLoan(employeeLoanId);
        setCurrentRecord(response?.success ? response.data || null : record || null);
      } catch {
        setCurrentRecord(record || null);
      } finally {
        setLoading(false);
      }
    };

    loadRecord();
  }, [open, record]);

  const source = currentRecord || record;

  const initialValues = useMemo(() => mapEmployeeLoanToFormValues(source), [source]);

  useEffect(() => {
    if (open && initialValues) {
      form.setFieldsValue(initialValues);
    }
  }, [open, initialValues, form]);

  useEffect(() => {
    if (!open) return;
    loadLoanType(source?.loan_type_id);
  }, [open, source?.loan_type_id, loadLoanType]);

  const employeesForForm = useMemo(() => {
    if (!source?.employee_national_id) return employees;

    const nationalId = String(source.employee_national_id);
    const exists = employees.some((e) => String(e.national_id ?? e.nationalId ?? '') === nationalId);

    if (exists) return employees;

    return [
      ...employees,
      {
        national_id: source.employee_national_id,
        pfno: source.pf_number ?? source.pfno,
        pf_number: source.pf_number,
        full_name: source.employee_name,
      },
    ];
  }, [employees, source]);

  const headerExtra =
    source?.employee_name || source?.loan_name
      ? `${source.employee_name ?? ''}${source.employee_name && source.loan_name ? ' · ' : ''}${source.loan_name ?? ''}${source?.loan_code ? ` (${source.loan_code})` : ''}`
      : null;

  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      const employeeLoanId = getEmployeeLoanId(source);
      if (!employeeLoanId) {
        message.error('Employee loan ID is missing.');
        return;
      }

      const response = await payrollService.updateEmployeeLoan(
        employeeLoanId,
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
      title={
        <span>
          {canEdit ? 'Employee Loan (Edit)' : 'Employee Loan Details'}
          {headerExtra ? <span className="text-gray-500 font-normal text-sm block">{headerExtra}</span> : null}
        </span>
      }
      open={open}
      onCancel={onClose}
      destroyOnHidden
      afterClose={() => {
        setCurrentRecord(null);
        resetLoanType();
        setEmployees([]);
        form.resetFields();
      }}
      width={EMPLOYEE_LOAN_MODAL_WIDTH}
      styles={EMPLOYEE_LOAN_MODAL_STYLES}
      footer={
        <EmployeeLoanModalFooter
          saving={saving}
          onClose={onClose}
          closeLabel={canEdit ? 'Cancel' : 'Close'}
          primaryLabel="Update"
          onPrimary={save}
          showPrimary={canEdit}
        />
      }
    >
      {loading ? (
        <CollectionLoader size={64} compact />
      ) : (
        <Form form={form} layout="vertical" requiredMark>
          <EmployeeLoanFormFields
            disabled={!canEdit}
            showAuditFields
            loanTypeOptions={typeOptions}
            employees={employeesForForm}
            employeesLoading={employeesLoading}
            onLoanTypeChange={canEdit ? onLoanTypeChange : undefined}
            onPrincipalAmountChange={canEdit ? onRecalculate : undefined}
            onRepaymentMonthsChange={canEdit ? onRecalculate : undefined}
            onEffectiveStartDateChange={canEdit ? onRecalculate : undefined}
          />
        </Form>
      )}
    </Modal>
  );
};

EmployeeLoanDetailsModal.propTypes = {
  open: PropTypes.bool.isRequired,
  record: PropTypes.object, // eslint-disable-line react/forbid-prop-types
  canEdit: PropTypes.bool,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default EmployeeLoanDetailsModal;
