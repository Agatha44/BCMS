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

const EmployeeBenefitFormFields = ({
  disabled = false,
  benefitTypeOptions = [],
  hideActiveToggle = false,
  employees = [],
  employeesLoading = false,
  onEmployeeChange = null,
  onBenefitTypeChange = null,
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
          onChange={onEmployeeChange ?? undefined}
          filterOption={(input, option) =>
            String(option?.label ?? '')
              .toLowerCase()
              .includes(String(input ?? '').toLowerCase())
          }
        />
      </Form.Item>

      <Form.Item
        label="Benefit Type"
        name="benefit_type_id"
        rules={[{ required: true, message: 'Benefit type is required' }]}
      >
        <Select
          placeholder="Select benefit type"
          options={benefitTypeOptions}
          disabled={disabled}
          loading={employeesLoading}
          showSearch
          allowClear
          optionFilterProp="label"
          onChange={onBenefitTypeChange ?? undefined}
          filterOption={(input, option) =>
            String(option?.label ?? '')
              .toLowerCase()
              .includes(String(input ?? '').toLowerCase())
          }
        />
      </Form.Item>

      <Form.Item
        label="Benefit Amount"
        name="benefit_amount"
        rules={[{ required: true, message: 'Benefit amount is required' }]}
      >
        <InputNumber className="w-full" min={0} step={0.01} disabled={disabled} />
      </Form.Item>

      <Form.Item label="Taxable Amount" name="taxable_amount">
        <InputNumber className="w-full" min={0} step={0.01} disabled={disabled} />
      </Form.Item>

      <Form.Item label="Tax Free Amount" name="tax_free_amount">
        <InputNumber className="w-full" min={0} step={0.01} disabled={disabled} />
      </Form.Item>

      <Form.Item
        label="Effective Start Date"
        name="effective_start_date"
        rules={[{ required: true, message: 'Start date is required' }]}
      >
        <DatePicker className="w-full" disabled={disabled} />
      </Form.Item>

      <Form.Item label="Effective End Date" name="effective_end_date">
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

EmployeeBenefitFormFields.propTypes = {
  disabled: PropTypes.bool,
  benefitTypeOptions: PropTypes.arrayOf(
    PropTypes.shape({
      value: PropTypes.oneOfType([PropTypes.number, PropTypes.string]).isRequired,
      label: PropTypes.string.isRequired,
    })
  ),
  hideActiveToggle: PropTypes.bool,
  employees: PropTypes.arrayOf(PropTypes.object), // eslint-disable-line react/forbid-prop-types
  employeesLoading: PropTypes.bool,
  onEmployeeChange: PropTypes.func,
  onBenefitTypeChange: PropTypes.func,
};

export default EmployeeBenefitFormFields;

