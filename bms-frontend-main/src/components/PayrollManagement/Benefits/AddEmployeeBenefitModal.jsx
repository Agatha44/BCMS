import { App, Button, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { extractArrayFromResponse } from '../../../common/utils/employeeUtils.jsx';
import { round2 } from '../../../common/utils/numberFormat.js';
import { apiService } from '../../../services/api.jsx';
import { payrollService } from '../../../services/payrollService.js';
import EmployeeBenefitFormFields from './EmployeeBenefitFormFields.jsx';

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

const findBenefitType = (benefitTypes, benefitTypeId) =>
  (benefitTypes || []).find(
    (t) => String(t.benefit_type_id ?? '') === String(benefitTypeId ?? '')
  );

const AddEmployeeBenefitModal = ({ open, onClose, onSaved = null }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [saving, setSaving] = useState(false);
  const [benefitTypes, setBenefitTypes] = useState([]);
  const [employees, setEmployees] = useState([]);
  const [listsLoading, setListsLoading] = useState(false);

  const typeOptions = useMemo(
    () =>
      benefitTypes
        .filter((t) => t?.benefit_type_id != null)
        .map((t) => ({
          value: t.benefit_type_id,
          label: `${t.benefit_name} (${t.benefit_code})`,
        })),
    [benefitTypes]
  );

  const loadReferenceData = useCallback(async () => {
    setListsLoading(true);
    try {
      const [empResponse, benResponse] = await Promise.all([
        apiService.getActiveBridgeEmployees({ per_page: 1000 }),
        payrollService.getActiveBenefitTypes(),
      ]);

      if (empResponse.success && empResponse.data) {
        const { items } = extractArrayFromResponse(empResponse.data);
        setEmployees(items || []);
      } else {
        setEmployees([]);
      }

      if (benResponse?.success) {
        setBenefitTypes(
          (benResponse.data || []).filter((t) => t?.benefit_type_id != null)
        );
      } else {
        setBenefitTypes([]);
      }
    } catch (e) {
      setEmployees([]);
      setBenefitTypes([]);
      message.error(e.message || 'Failed to load form data');
    } finally {
      setListsLoading(false);
    }
  }, [message]);

  useEffect(() => {
    if (!open) return;
    loadReferenceData();
  }, [open, loadReferenceData]);

  const applyBenefitDefaults = useCallback(
    (employeeId, benefitTypeId) => {
      if (!employeeId || benefitTypeId == null || benefitTypeId === '') return;

      const employee = findEmployee(employees, employeeId);
      const benefitType = findBenefitType(benefitTypes, benefitTypeId);

      if (!employee || !benefitType) return;

      const basicSalary = parseFloat(employee.basicsalary ?? employee.basic_salary) || 0;
      const calcValue = parseFloat(benefitType.calculation_value) || 0;
      const isPercentage =
        String(benefitType.calculation_type || '').toLowerCase().trim() === 'percentage';
      const isTaxable =
        Number(benefitType.is_taxable) === 1 || benefitType.is_taxable === true;

      if (!basicSalary) {
        message.warning(
          'This employee has no basic salary on record. Enter benefit amounts manually.'
        );
        form.setFieldsValue({
          benefit_amount: null,
          taxable_amount: null,
          tax_free_amount: null,
        });
        return;
      }

      const benefitAmount = isPercentage
        ? round2((basicSalary * calcValue) / 100)
        : calcValue;

      form.setFieldsValue({
        benefit_amount: benefitAmount,
        taxable_amount: isTaxable ? benefitAmount : 0,
        tax_free_amount: isTaxable ? 0 : benefitAmount,
      });
    },
    [employees, benefitTypes, form, message]
  );

  const onEmployeeChange = useCallback(
    (nationalId) => {
      applyBenefitDefaults(nationalId, form.getFieldValue('benefit_type_id'));
    },
    [applyBenefitDefaults, form]
  );

  const onBenefitTypeChange = useCallback(
    (benefitTypeId) => {
      applyBenefitDefaults(form.getFieldValue('employee_national_id'), benefitTypeId);
    },
    [applyBenefitDefaults, form]
  );

  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      const payload = {
        employee_national_id: values.employee_national_id,
        benefit_type_id: values.benefit_type_id,
        benefit_amount: numOrNull(values.benefit_amount),
        taxable_amount: numOrNull(values.taxable_amount),
        tax_free_amount: numOrNull(values.tax_free_amount),
        effective_start_date: toApiDate(values.effective_start_date),
        effective_end_date: toApiDate(values.effective_end_date),
        is_active: values.is_active ? 1 : 0,
        notes: values.notes ?? null,
      };

      const response = await payrollService.createEmployeeBenefit(payload);

      if (!response?.success) {
        message.error(response?.message || 'Could not save employee benefit.');
        return;
      }

      message.success(response?.message || 'Employee benefit saved');
      onSaved?.();
    } catch (e) {
      if (e?.errorFields) return;
      message.error(e?.message || 'Failed to save employee benefit');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      title="Add Employee Benefit"
      open={open}
      onCancel={onClose}
      destroyOnHidden
      afterClose={() => form.resetFields()}
      footer={[
        <Button key="cancel" onClick={onClose} disabled={saving}>
          Close
        </Button>,
        <Button key="save" type="primary" onClick={save} loading={saving}>
          Add Employee Benefit
        </Button>,
      ]}
      width={1200}
      styles={{ body: { maxHeight: 'none', overflow: 'visible', paddingTop: 16 } }}
    >
      <Form
        form={form}
        layout="vertical"
        initialValues={{
          is_active: true,
        }}
      >
        <EmployeeBenefitFormFields
          disabled={false}
          benefitTypeOptions={typeOptions}
          employees={employees}
          employeesLoading={listsLoading}
          onEmployeeChange={onEmployeeChange}
          onBenefitTypeChange={onBenefitTypeChange}
        />
      </Form>
    </Modal>
  );
};

AddEmployeeBenefitModal.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default AddEmployeeBenefitModal;
