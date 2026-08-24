import { DatePicker, Form, Input, InputNumber, Select, Switch } from 'antd';
import PropTypes from 'prop-types';
import { useMemo } from 'react';
import { formatEmployeeName } from '../../../common/utils/employeeUtils.jsx';

const buildStaffOption = (employee) => {
  const nationalId = employee?.national_id ?? employee?.nationalId;
  const pfNumber = employee?.pfno;
  const fullName = formatEmployeeName(employee);
  const label = `${pfNumber || '—'} - ${fullName}`;
  return {
    value: nationalId != null ? String(nationalId) : '',
    label,
  };
};

const monthOptions = Array.from({ length: 12 }).map((_, i) => ({
  value: i + 1,
  label: String(i + 1).padStart(2, '0'),
}));

const EmployeeArrearsFormFields = ({
  disabled = false,
  hideActiveToggle = false,
  employees = [],
  employeesLoading = false,
  arrearsReasonOptions = [],
  arrearsReasonLoading = false,
}) => {
  const employeeSelectOptions = useMemo(() => {
    return (employees || [])
      .map((emp) => buildStaffOption(emp))
      .filter((o) => o.value);
  }, [employees]);

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-3">
      <Form.Item
        label="Employee name"
        name="employee_national_id"
        rules={[{ required: true, message: 'Please select an employee' }]}
      >
        <Select
          placeholder="Search employee by PF number or name"
          disabled={disabled}
          loading={employeesLoading}
          showSearch
          allowClear
          optionFilterProp="label"
          options={employeeSelectOptions}
          filterOption={(input, option) =>
            String(option?.label ?? '')
              .toLowerCase()
              .includes(String(input ?? '').toLowerCase())
          }
        />
      </Form.Item>

      <Form.Item
        label="Arrears Reason"
        name="arrears_reason_id"
        rules={[{ required: true, message: 'Arrears reason is required' }]}
      >
        <Select
          placeholder="Select arrears reason"
          options={arrearsReasonOptions}
          loading={arrearsReasonLoading}
          disabled={disabled}
          showSearch
          allowClear
          optionFilterProp="label"
          filterOption={(input, option) =>
            String(option?.label ?? '')
              .toLowerCase()
              .includes(String(input ?? '').toLowerCase())
          }
        />
      </Form.Item>

      <Form.Item label="Payroll Month" name="payroll_month" rules={[{ required: true, message: 'Payroll month is required' }]}>
        <Select options={monthOptions} placeholder="Select month" disabled={disabled} />
      </Form.Item>

      <Form.Item label="Payroll Year" name="payroll_year" rules={[{ required: true, message: 'Payroll year is required' }]}>
        <InputNumber className="w-full" min={2000} step={1} disabled={disabled} />
      </Form.Item>

      <Form.Item label="Arrears Amount" name="arrears_amount" rules={[{ required: true, message: 'Arrears amount is required' }]}>
        <InputNumber className="w-full" min={0} step={0.01} disabled={disabled} />
      </Form.Item>

      <Form.Item label="Arrears Date" name="arrears_date" rules={[{ required: true, message: 'Arrears date is required' }]}>
        <DatePicker className="w-full" disabled={disabled} />
      </Form.Item>

      <Form.Item label="Notes" name="notes" className="sm:col-span-2 lg:col-span-3">
        <Input.TextArea rows={2} disabled={disabled} />
      </Form.Item>

      {!hideActiveToggle ? (
        <Form.Item label="Active" name="is_active" valuePropName="checked">
          <Switch disabled={disabled} />
        </Form.Item>
      ) : null}
    </div>
  );
};

EmployeeArrearsFormFields.propTypes = {
  disabled: PropTypes.bool,
  hideActiveToggle: PropTypes.bool,
  employees: PropTypes.arrayOf(PropTypes.object), // eslint-disable-line react/forbid-prop-types
  employeesLoading: PropTypes.bool,
  arrearsReasonOptions: PropTypes.arrayOf(
    PropTypes.shape({
      value: PropTypes.oneOfType([PropTypes.number, PropTypes.string]).isRequired,
      label: PropTypes.string.isRequired,
    })
  ),
  arrearsReasonLoading: PropTypes.bool,
};

export default EmployeeArrearsFormFields;

