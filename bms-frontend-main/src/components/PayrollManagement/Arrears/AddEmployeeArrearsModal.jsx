import { App, Button, Form, Modal } from 'antd';
import PropTypes from 'prop-types';
import { useCallback, useEffect, useMemo, useState } from 'react';
import dayjs from 'dayjs';
import { extractArrayFromResponse } from '../../../common/utils/employeeUtils.jsx';
import { apiService } from '../../../services/api.jsx';
import { payrollService } from '../../../services/payrollService.js';
import EmployeeArrearsFormFields from './EmployeeArrearsFormFields.jsx';

const toApiDate = (d) => {
  if (!d) return null;
  if (typeof d.format === 'function') return d.format('YYYY-MM-DD');
  return null;
};

const numOrNull = (v) => {
  if (v === undefined || v === null || v === '') return null;
  return String(v);
};

const AddEmployeeArrearsModal = ({ open, onClose, onSaved = null }) => {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [saving, setSaving] = useState(false);
  const [employees, setEmployees] = useState([]);
  const [employeesLoading, setEmployeesLoading] = useState(false);
  const [reasonOptions, setReasonOptions] = useState([]);
  const [reasonsLoading, setReasonsLoading] = useState(false);

  const now = useMemo(() => dayjs(), []);

  const loadEmployees = useCallback(async () => {
    setEmployeesLoading(true);
    try {
      const response = await apiService.getActiveBridgeEmployees({ per_page: 1000 });
      if (response.success && response.data) {
        const { items } = extractArrayFromResponse(response.data);
        setEmployees(items || []);
      } else {
        setEmployees([]);
      }
    } catch (e) {
      setEmployees([]);
      message.error(e.message || 'Failed to load employees');
    } finally {
      setEmployeesLoading(false);
    }
  }, [message]);

  const loadArrearsReasons = useCallback(async () => {
    setReasonsLoading(true);
    try {
      const response = await payrollService.getActiveArrearsReasons();
      if (!response?.success) {
        setReasonOptions([]);
        return;
      }
      setReasonOptions(
        (response.data || [])
          .filter((t) => t?.arrears_reason_id != null)
          .map((t) => ({
            value: t.arrears_reason_id,
            label: `${t.reason_name} (${t.reason_code})`,
          }))
      );
    } finally {
      setReasonsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!open) return;
    loadEmployees();
    loadArrearsReasons();
  }, [open, loadEmployees, loadArrearsReasons]);

  const save = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      const payload = {
        employee_national_id: values.employee_national_id,
        arrears_reason_id: values.arrears_reason_id,
        payroll_month: values.payroll_month,
        payroll_year: values.payroll_year,
        arrears_amount: numOrNull(values.arrears_amount),
        arrears_date: toApiDate(values.arrears_date),
        is_active: values.is_active ? 1 : 0,
        notes: values.notes ?? null,
      };

      const response = await payrollService.createEmployeeArrear(payload);
      if (!response?.success) {
        message.error(response?.message || 'Could not save employee arrears.');
        return;
      }

      message.success(response?.message || 'Employee arrears saved');
      onSaved?.();
    } catch (e) {
      if (e?.errorFields) return;
      message.error(e?.message || 'Failed to save employee arrears');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      title="Add Employee Arrears"
      open={open}
      onCancel={onClose}
      destroyOnHidden
      afterClose={() => form.resetFields()}
      footer={[
        <Button key="cancel" onClick={onClose} disabled={saving}>
          Close
        </Button>,
        <Button key="save" type="primary" onClick={save} loading={saving}>
          Add Employee Arrears
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
          payroll_month: now.month() + 1,
          payroll_year: now.year(),
          arrears_date: now.startOf('month'),
        }}
      >
        <EmployeeArrearsFormFields
          disabled={false}
          employees={employees}
          employeesLoading={employeesLoading}
          arrearsReasonOptions={reasonOptions}
          arrearsReasonLoading={reasonsLoading}
        />
      </Form>
    </Modal>
  );
};

AddEmployeeArrearsModal.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func,
};

export default AddEmployeeArrearsModal;

