import { App, Button, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { extractArrayFromResponse } from '../../../common/utils/employeeUtils.jsx';
import { round2 } from '../../../common/utils/numberFormat.js';
import { apiService } from '../../../services/api.jsx';
import { payrollService } from '../../../services/payrollService.js';
import EmployeeDeductionFormFields from './EmployeeDeductionFormFields.jsx';

const toApiDate = (d) => {
  if (!d) return null;
  if (typeof d.format === 'function') return d.format('YYYY-MM-DD');
  return null;
};

const numOrNull = (v) => {
  if (v === undefined || v === null || v === '') return null;
  return String(v);
};

const findEmployee = (employees, nationalId) =>
  (employees || []).find(
    (e) => String(e.national_id ?? e.nationalId ?? '') === String(nationalId ?? '')
  );

const findDeductionType = (deductionTypes, deductionTypeId) =>
  (deductionTypes || []).find(
    (d) => String(d.deduction_type_id ?? '') === String(deductionTypeId ?? '')
  );

const AddEmployeeDeductionModal = ({ open, onClose, onSaved = null }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [saving, setSaving] = useState(false);
  const [deductionTypes, setDeductionTypes] = useState([]);
  const [employees, setEmployees] = useState([]);
  const [listsLoading, setListsLoading] = useState(false);

  const typeOptions = useMemo(
    () =>
      deductionTypes
        .filter((t) => t?.deduction_type_id != null)
        .map((t) => ({
          value: t.deduction_type_id,
          label: `${t.deduction_name} (${t.deduction_code})`,
        })),
    [deductionTypes]
  );

  const loadReferenceData = useCallback(async () => {
    setListsLoading(true);
    try {
      const [empResponse, dedResponse] = await Promise.all([
        apiService.getActiveBridgeEmployees({ per_page: 1000 }),
        payrollService.getActiveDeductionTypes(),
      ]);

      if (empResponse.success && empResponse.data) {
        const { items } = extractArrayFromResponse(empResponse.data);
        setEmployees(items || []);
      } else {
        setEmployees([]);
      }

      if (dedResponse?.success) {
        setDeductionTypes(
          (dedResponse.data || []).filter((t) => t?.deduction_type_id != null)
        );
      } else {
        setDeductionTypes([]);
      }
    } catch (e) {
      setEmployees([]);
      setDeductionTypes([]);
      message.error(e.message || 'Failed to load form data');
    } finally {
      setListsLoading(false);
    }
  }, [message]);

  useEffect(() => {
    if (!open) return;
    loadReferenceData();
  }, [open, loadReferenceData]);

  const applyDeductionDefaults = useCallback(
    (employeeId, deductionTypeId) => {
      if (!employeeId || deductionTypeId == null || deductionTypeId === '') return;

      const employee = findEmployee(employees, employeeId);
      const deductionType = findDeductionType(deductionTypes, deductionTypeId);

      if (!employee || !deductionType) return;

      const basicSalary = parseFloat(employee.basicsalary ?? employee.basic_salary) || 0;
      const empPct = parseFloat(deductionType.employee_contribution_percentage) || 0;
      const erPct = parseFloat(deductionType.employer_contribution_percentage) || 0;
      const isPercentage =
        String(deductionType.calculation_type || '').toLowerCase().trim() === 'percentage';

      const isBeforeTax =
        Number(deductionType.is_before_tax) === 1 || deductionType.is_before_tax === true;

      if (!basicSalary) {
        message.warning(
          'This employee has no basic salary on record. Enter deduction amounts manually.'
        );
        form.setFieldsValue({
          employee_contribution_percentage: isPercentage ? empPct : null,
          employer_contribution_percentage: isPercentage ? erPct : null,
          employee_contribution_amount: null,
          employer_contribution_amount: null,
          total_deduction_amount: null,
          is_before_tax: isBeforeTax,
        });
        return;
      }

      let employeeAmount = 0;
      let employerAmount = 0;

      if (isPercentage) {
        employeeAmount = round2((basicSalary * empPct) / 100);
        employerAmount = round2((basicSalary * erPct) / 100);
      } else {
        employeeAmount = parseFloat(deductionType.calculation_value) || 0;
        employerAmount = 0;
      }

      const totalAmount = round2(employeeAmount + employerAmount);

      form.setFieldsValue({
        employee_contribution_percentage: isPercentage ? empPct : null,
        employer_contribution_percentage: isPercentage ? erPct : null,
        employee_contribution_amount: employeeAmount,
        employer_contribution_amount: employerAmount,
        total_deduction_amount: totalAmount,
        is_before_tax: isBeforeTax,
      });
    },
    [employees, deductionTypes, form, message]
  );

  const onEmployeeChange = useCallback(
    (nationalId) => {
      applyDeductionDefaults(nationalId, form.getFieldValue('deduction_type_id'));
    },
    [applyDeductionDefaults, form]
  );

  const onDeductionTypeChange = useCallback(
    (deductionTypeId) => {
      applyDeductionDefaults(form.getFieldValue('employee_national_id'), deductionTypeId);
    },
    [applyDeductionDefaults, form]
  );

  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      const payload = {
        employee_national_id: values.employee_national_id,
        deduction_type_id: values.deduction_type_id,
        total_deduction_amount: numOrNull(values.total_deduction_amount),
        employee_contribution_percentage: numOrNull(values.employee_contribution_percentage),
        employee_contribution_amount: numOrNull(values.employee_contribution_amount),
        employer_contribution_percentage: numOrNull(values.employer_contribution_percentage),
        employer_contribution_amount: numOrNull(values.employer_contribution_amount),
        is_before_tax: values.is_before_tax ? 1 : 0,
        effective_start_date: toApiDate(values.effective_start_date),
        effective_end_date: toApiDate(values.effective_end_date),
        is_active: values.is_active ? 1 : 0,
        notes: values.notes ?? null,
      };

      const response = await payrollService.createEmployeeDeduction(payload);

      if (!response?.success) {
        message.error(response?.message || 'Could not save employee deduction.');
        return;
      }

      message.success(response?.message || 'Employee deduction saved');
      onSaved?.();
    } catch (e) {
      if (e?.errorFields) return;
      message.error(e?.message || 'Failed to save employee deduction');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      title="Add Employee Deduction"
      open={open}
      onCancel={onClose}
      destroyOnHidden
      afterClose={() => form.resetFields()}
      footer={[
        <Button key="cancel" onClick={onClose} disabled={saving}>
          Close
        </Button>,
        <Button key="save" type="primary" onClick={save} loading={saving}>
          Add Employee Deduction
        </Button>,
      ]}
      width={1200}
      styles={{ body: { maxHeight: 'none', overflow: 'visible', paddingTop: 16 } }}
    >
      <Form
        form={form}
        layout="vertical"
        initialValues={{
          is_before_tax: false,
          is_active: true,
        }}
      >
        <EmployeeDeductionFormFields
          disabled={false}
          deductionTypeOptions={typeOptions}
          employees={employees}
          employeesLoading={listsLoading}
          onEmployeeChange={onEmployeeChange}
          onDeductionTypeChange={onDeductionTypeChange}
        />
      </Form>
    </Modal>
  );
};

AddEmployeeDeductionModal.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default AddEmployeeDeductionModal;
